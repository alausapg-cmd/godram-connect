<?php

namespace App\Services;

use App\Models\AchievementRule;
use App\Models\Certificate;
use App\Models\Exam;
use App\Models\Member;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Issues, renders and revokes certificates. Every certificate records the rule
 * that earned it; nothing is issued for simply opening lessons.
 */
class Certificates
{
    public function __construct(protected AuditLogger $audit) {}

    /** Issue the certificate a released, passing certification result earns. Returns null when none is due. */
    public function forExam(Exam $exam, ?Member $member, ?User $by = null): ?Certificate
    {
        if (! $member || ! $exam->awards_certificate || $exam->isPractice() || ! $exam->resultsReleased()) {
            return null;
        }
        $result = $exam->resultFor($member);
        if (! $result || ! $result->passed) {
            return null;
        }
        $existing = Certificate::where('member_id', $member->id)->whereIn('exam_attempt_id', $exam->attempts()->pluck('id'))->first();
        if ($existing) {
            return null;
        }
        $programme = $exam->course?->title ?? $exam->title;
        $rule = 'Passed the certification examination "'.$exam->title.'" with '.rtrim(rtrim(number_format($result->percent, 2), '0'), '.').'% (pass mark '.$exam->pass_mark.'%)'
            .($exam->requires_course_completion ? ', after completing every lesson of the training' : '').'.';

        return $this->issue($member, [
            'kind' => $exam->course_id ? 'training' : 'examination',
            'title' => $exam->course_id ? 'Certificate of Completion' : 'Examination Certificate',
            'achievement' => $exam->course_id ? 'has successfully completed the training and passed its examination' : 'has passed the examination',
            'programme' => $programme,
            'eligibility_rule' => $rule,
            'exam_attempt_id' => $result->attempt->id,
            'course_id' => $exam->course_id,
        ], $by);
    }

    public function forAchievement(AchievementRule $rule, Member $member): Certificate
    {
        return $this->issue($member, [
            'kind' => 'achievement',
            'title' => 'Certificate of Achievement',
            'achievement' => 'for reaching the milestone: '.$rule->name,
            'programme' => $rule->kindLabel(),
            'eligibility_rule' => 'Achievement rule "'.$rule->name.'": '.$rule->ruleText().'.',
            'achievement_rule_id' => $rule->id,
        ]);
    }

    /** A certificate an authorised officer issues by hand, with the reason recorded. */
    public function special(Member $member, string $title, string $achievement, ?string $programme, string $reason, User $by): Certificate
    {
        return $this->issue($member, [
            'kind' => 'recognition',
            'title' => $title,
            'achievement' => $achievement,
            'programme' => $programme,
            'eligibility_rule' => 'Special recognition approved by '.$by->name.': '.$reason,
        ], $by);
    }

    protected function issue(Member $member, array $data, ?User $by = null): Certificate
    {
        return DB::transaction(function () use ($member, $data, $by) {
            $certificate = Certificate::create($data + [
                'number' => $this->nextNumber(),
                'verify_code' => Str::upper(Str::random(8)),
                'member_id' => $member->id,
                'recipient_name' => $member->full_name,
                'issued_on' => today(),
                'issuing_authority' => config('godram.certificates.issuing_authority'),
                'verification_method' => 'Scan the QR code or visit '.route('certificates.lookup').' and enter the certificate ID.',
                'signatories' => $this->signatories(),
                'issued_by' => $by?->id,
                'is_demo' => $member->is_demo,
            ]);
            $this->audit->log('certificate.issued', $certificate, $certificate->number.' issued to '.$member->full_name.': '.$certificate->title.($certificate->programme ? ', '.$certificate->programme : ''), ['rule' => $certificate->eligibility_rule], $member->assembly(), $by?->id);

            return $certificate;
        });
    }

    public function revoke(Certificate $certificate, string $reason, User $by): void
    {
        $certificate->update(['status' => 'revoked', 'revoked_reason' => $reason]);
        $this->audit->log('certificate.revoked', $certificate, $certificate->number.' revoked: '.$reason, [], $certificate->member?->assembly(), $by->id);
    }

    public function nextNumber(): string
    {
        $format = config('godram.certificates.number_format');
        $prefix = str_replace('{Y}', now()->format('Y'), Str::before($format, '{N}'));
        $last = Certificate::where('number', 'like', $prefix.'%')->lockForUpdate()->pluck('number')
            ->map(fn ($n) => (int) preg_replace('/\D/', '', Str::after($n, $prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), config('godram.certificates.number_digits'), '0', STR_PAD_LEFT).Str::after($format, '{N}');
    }

    /** The people who currently hold the signing roles. */
    public function signatories(): array
    {
        return collect(config('godram.certificates.signatory_roles'))->map(function ($key) {
            $role = Role::where('key', $key)->first();
            $holder = $role ? RoleAssignment::active()->where('role_id', $role->id)->with('member')->orderBy('starts_on')->first()?->member : null;

            return $role ? ['role' => $key, 'title' => $role->name, 'name' => $holder?->full_name, 'signature' => $this->signaturePath($key)] : null;
        })->filter()->values()->all();
    }

    public function signaturePath(string $roleKey): ?string
    {
        $path = 'signatures/'.$roleKey.'.png';

        return Storage::disk('local')->exists($path) ? $path : null;
    }

    /** Signatures are kept as PNG with transparency so they sit cleanly on the certificate. */
    public function storeSignature(string $roleKey, UploadedFile $file): void
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        abort_unless($image, 422, 'That file is not a picture we can read.');
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, 600 / $w);
        $out = imagecreatetruecolor((int) ($w * $scale), (int) ($h * $scale));
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $image, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        ob_start();
        imagepng($out);
        Storage::disk('local')->put('signatures/'.$roleKey.'.png', ob_get_clean());
    }

    public function qrDataUri(string $text): string
    {
        $options = new QROptions(['outputInterface' => QRGdImagePNG::class, 'scale' => 6, 'outputBase64' => true, 'quietzoneSize' => 1]);

        return (new QRCode($options))->render($text);
    }

    /** An unsaved sample of each design, so officers can see what members will receive. */
    public function specimen(string $kind): Certificate
    {
        $samples = [
            'training' => ['Certificate of Completion', 'has successfully completed the training and passed its examination', 'Foundations of Drama Ministry'],
            'examination' => ['Examination Certificate', 'has passed the examination', 'GODRAM Drama Ministers\' Certification '.now()->year],
            'achievement' => ['Certificate of Achievement', 'for reaching the milestone: Ten years of service', 'Years of service'],
            'recognition' => ['Certificate of Recognition', 'is recognised for outstanding service to the drama ministry', 'GODRAM National Convention '.now()->year],
        ];
        [$title, $achievement, $programme] = $samples[$kind];

        return new Certificate([
            'number' => str_replace(['{Y}', '{N}'], [now()->year, str_repeat('0', config('godram.certificates.number_digits') - 1).'1'], config('godram.certificates.number_format')),
            'recipient_name' => 'Adeola Grace Ogunleye', 'kind' => $kind, 'title' => $title, 'achievement' => $achievement, 'programme' => $programme,
            'issued_on' => today(), 'issuing_authority' => config('godram.certificates.issuing_authority'), 'signatories' => $this->signatories(),
        ]);
    }

    /** Each kind of certificate has its own design in resources/views/certificates/designs. */
    public function pdf(Certificate $certificate, bool $specimen = false): string
    {
        $image = fn (?string $path) => $path && Storage::disk('local')->exists($path)
            ? 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($path)) : null;
        $design = array_key_exists($certificate->kind, Certificate::KINDS) ? $certificate->kind : 'examination';
        $html = view('certificates.designs.'.$design, [
            'specimen' => $specimen,
            'certificate' => $certificate,
            'qr' => $this->qrDataUri($certificate->verifyUrl()),
            'logo' => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('icons/icon-192.png'))),
            'signatures' => collect($certificate->signatories)->map(fn ($s) => $s + ['image' => $image($s['signature'] ?? null)]),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
