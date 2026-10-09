<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Models\AchievementRule;
use App\Models\Certificate;
use App\Models\Enrolment;
use App\Models\Member;
use App\Services\Achievements;
use App\Services\AuditLogger;
use App\Services\Certificates;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** My certificates, public verification, and certificate administration. */
class CertificateController extends Controller
{
    public function __construct(protected Certificates $certificates, protected AuditLogger $audit) {}

    public function mine(Request $request)
    {
        $member = $request->user()->member;
        abort_unless($member, 403);

        // Members see the timeline from when they joined to now, with certificates and milestones together.
        $certificates = $member->certificates()->get();
        $achievements = $member->achievements()->with('certificate')->get();
        $timeline = collect()
            ->concat($achievements->map(fn ($a) => (object) ['date' => $a->awarded_on, 'title' => $a->title, 'text' => $a->description, 'kind' => 'achievement', 'certificate' => $a->certificate]))
            ->concat($certificates->filter(fn ($c) => ! $achievements->contains('certificate_id', $c->id))->map(fn ($c) => (object) ['date' => $c->issued_on, 'title' => $c->title, 'text' => $c->programme, 'kind' => 'certificate', 'certificate' => $c]))
            ->concat($member->examAttempts()->where('passed', true)->with('exam')->get()->unique('exam_id')->map(fn ($a) => (object) ['date' => $a->submitted_at, 'title' => 'Passed '.$a->exam->title, 'text' => $a->exam->resultsReleased() ? rtrim(rtrim(number_format($a->percent, 1), '0'), '.').'%' : null, 'kind' => 'exam', 'certificate' => null]))
            ->concat(Enrolment::where('member_id', $member->id)->where('status', 'completed')->with('course')->get()->map(fn ($e) => (object) ['date' => $e->completed_at, 'title' => 'Completed '.$e->course->title, 'text' => null, 'kind' => 'training', 'certificate' => null]))
            ->when($member->joined_on, fn ($c) => $c->push((object) ['date' => $member->joined_on, 'title' => 'Joined GODRAM', 'text' => $member->assembly()?->fullName(), 'kind' => 'joined', 'certificate' => null]))
            ->filter(fn ($i) => $i->date)->sortByDesc(fn ($i) => $i->date->timestamp)->values();

        $next = AchievementRule::where('is_active', true)->whereNotIn('id', $achievements->pluck('achievement_rule_id')->filter()->all() ?: [0])->get()
            ->map(fn ($r) => (object) ['rule' => $r, 'value' => app(Achievements::class)->metric($member, $r->metric)])
            ->filter(fn ($i) => $i->value > 0)->sortByDesc(fn ($i) => $i->value / max(1, $i->rule->threshold))->take(3)->values();

        return view('certificates.mine', compact('member', 'certificates', 'achievements', 'timeline', 'next'));
    }

    public function download(Request $request, Certificate $certificate)
    {
        $mine = $request->user()->member_id && (int) $certificate->member_id === (int) $request->user()->member_id;
        abort_unless($mine || can_do('certificates.issue'), 404);
        abort_unless($certificate->isValid() || can_do('certificates.issue'), 410, 'This certificate has been revoked.');

        return response($this->certificates->pdf($certificate), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('view') ? 'inline' : 'attachment').'; filename="'.$certificate->number.'.pdf"',
        ]);
    }

    /** Public: enter a certificate ID. */
    public function lookup(Request $request)
    {
        if ($number = trim((string) $request->query('number'))) {
            return redirect()->route('certificates.verify', Str::upper($number));
        }

        return view('certificates.verify', ['certificate' => null, 'number' => null]);
    }

    /** Public: limited details only. No member number, phone, Assembly or score. */
    public function verify(string $number)
    {
        $certificate = Certificate::where('number', Str::upper($number))->first();

        return response()->view('certificates.verify', ['certificate' => $certificate, 'number' => Str::upper($number)], $certificate ? 200 : 404);
    }

    public function index(Request $request)
    {
        abort_unless(can_do('certificates.issue'), 403);
        $q = trim((string) $request->query('q'));
        $certificates = Certificate::with('member')
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('number', 'like', "%{$q}%")->orWhere('recipient_name', 'like', "%{$q}%")))
            ->when($request->query('kind'), fn ($query, $k) => $query->where('kind', $k))
            ->when($request->query('status'), fn ($query, $s) => $query->where('status', $s))
            ->latest('id')->paginate(30)->withQueryString();

        return view('certificates.manage.index', [
            'certificates' => $certificates,
            'q' => $q,
            'signatories' => $this->certificates->signatories(),
            'rules' => AchievementRule::withCount('awards')->orderBy('kind')->orderBy('threshold')->get(),
        ]);
    }

    public function specimen(string $kind)
    {
        abort_unless(can_do('certificates.issue'), 403);
        abort_unless(array_key_exists($kind, Certificate::KINDS), 404);

        return response($this->certificates->pdf($this->certificates->specimen($kind), specimen: true), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="specimen-'.$kind.'.pdf"',
        ]);
    }

    public function create()
    {
        abort_unless(can_do('certificates.issue'), 403);

        return view('certificates.manage.issue');
    }

    public function store(Request $request)
    {
        abort_unless(can_do('certificates.issue'), 403);
        $data = $request->validate([
            'member_no' => ['required', 'string'],
            'title' => ['required', 'string', 'max:120'],
            'achievement' => ['required', 'string', 'max:300'],
            'programme' => ['nullable', 'string', 'max:150'],
            'reason' => ['required', 'string', 'max:500'],
        ], ['reason.required' => 'Say why this certificate is being issued. It is kept as the eligibility rule.']);
        $member = Member::where('member_no', Str::upper(trim($data['member_no'])))->first();
        if (! $member) {
            return back()->withInput()->withErrors(['member_no' => 'No member has that Member ID.']);
        }
        $certificate = $this->certificates->special($member, $data['title'], $data['achievement'], $data['programme'] ?? null, $data['reason'], $request->user());

        return redirect()->route('certificates.manage.index', ['q' => $certificate->number])->with('status', $certificate->number.' issued to '.$member->full_name.'.');
    }

    public function revoke(Request $request, Certificate $certificate)
    {
        abort_unless(can_do('certificates.issue'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $this->certificates->revoke($certificate, $data['reason'], $request->user());

        return back()->with('status', $certificate->number.' revoked. The verification page now shows it as revoked.');
    }

    public function signature(Request $request)
    {
        abort_unless(can_do('certificates.issue'), 403);
        $data = $request->validate([
            'role' => ['required', Rule::in(config('godram.certificates.signatory_roles'))],
            'signature' => ['required', 'image', 'max:4096'],
        ]);
        $this->certificates->storeSignature($data['role'], $request->file('signature'));
        $this->audit->log('certificate.signature', null, 'Signature updated for '.str_replace('_', ' ', $data['role']));

        return back()->with('status', 'Signature saved. New certificates will carry it.');
    }

    public function storeRule(Request $request)
    {
        abort_unless(can_do('certificates.issue'), 403);
        $rule = AchievementRule::create($this->ruleData($request));
        $this->audit->log('achievement.rule_created', $rule, 'Achievement rule added: '.$rule->name.' ('.$rule->ruleText().')');

        return back()->with('status', 'Rule added. Members who already qualify receive it at the next daily check.');
    }

    public function updateRule(Request $request, AchievementRule $rule)
    {
        abort_unless(can_do('certificates.issue'), 403);
        $rule->update($this->ruleData($request));
        $this->audit->log('achievement.rule_updated', $rule, 'Achievement rule changed: '.$rule->name.' ('.$rule->ruleText().')'.($rule->is_active ? '' : ', switched off'));

        return back()->with('status', 'Rule saved.');
    }

    protected function ruleData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:300'],
            'kind' => ['required', Rule::in(array_keys(AchievementRule::KINDS))],
            'metric' => ['required', Rule::in(array_keys(AchievementRule::METRICS))],
            'threshold' => ['required', 'integer', 'min:1', 'max:10000'],
        ]) + ['issues_certificate' => $request->boolean('issues_certificate'), 'is_active' => $request->boolean('is_active', true)];
    }
}
