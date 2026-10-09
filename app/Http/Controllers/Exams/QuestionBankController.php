<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Models\AttemptQuestion;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Video;
use App\Services\AuditLogger;
use App\Services\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The GODRAM question bank: write, review, version and retire questions. */
class QuestionBankController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index(Request $request)
    {
        abort_unless(can_do('exams.manage') || can_do('questions.review'), 403);
        $filters = $request->only(['category', 'type', 'difficulty', 'status', 'q']);
        $questions = Question::with('category', 'author')
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('question_category_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['difficulty'] ?? null, fn ($q, $v) => $q->where('difficulty', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v), fn ($q) => $q->where('status', '!=', 'retired'))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('stem', 'like', "%{$v}%")->orWhere('scenario', 'like', "%{$v}%")))
            ->withCount('uses')
            ->latest('updated_at')->paginate(30)->withQueryString();

        $stats = AttemptQuestion::whereIn('question_id', $questions->pluck('id'))->whereNotNull('marks_awarded')
            ->selectRaw('question_id, count(*) as presented, sum(case when is_correct = 1 then 1 else 0 end) as correct')
            ->groupBy('question_id')->get()->keyBy('question_id');

        return view('exams.bank.index', [
            'questions' => $questions,
            'stats' => $stats,
            'filters' => $filters,
            'categories' => QuestionCategory::orderBy('sort')->orderBy('name')->withCount(['questions' => fn ($q) => $q->approved()])->get(),
            'waiting' => Question::where('status', 'draft')->count(),
            'canReview' => can_do('questions.review'),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless(can_do('exams.manage'), 403);

        return view('exams.bank.form', [
            'question' => new Question(['type' => $request->query('type', 'single'), 'difficulty' => 'moderate', 'marks' => 1, 'shuffle_options' => true]),
            'categories' => QuestionCategory::orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ImageStore $files)
    {
        abort_unless(can_do('exams.manage'), 403);
        $question = new Question(['author_id' => $request->user()->id, 'status' => 'draft']);
        $this->fill($question, $request, $files);
        $question->save();
        $this->audit->log('question.created', $question, 'Question added to the bank: '.Str::limit($question->stem, 80));

        return redirect()->route('questions.index')->with('status', 'Question saved. It can be used in examinations once a reviewer approves it.');
    }

    public function edit(Question $question)
    {
        abort_unless(can_do('exams.manage'), 403);

        return view('exams.bank.form', [
            'question' => $question,
            'categories' => QuestionCategory::orderBy('sort')->orderBy('name')->get(),
            'used' => $question->uses()->count(),
        ]);
    }

    /** Editing an approved question makes a new version that needs review again. Past papers keep the old wording. */
    public function update(Request $request, Question $question, ImageStore $files)
    {
        abort_unless(can_do('exams.manage'), 403);
        $wasApproved = $question->status === 'approved';
        $this->fill($question, $request, $files);
        if ($question->isDirty()) {
            if ($wasApproved || $question->status === 'retired') {
                $question->version++;
                $question->status = 'draft';
                $question->reviewed_by = null;
                $question->reviewed_at = null;
            }
            $question->save();
            $this->audit->log('question.updated', $question, 'Question edited (version '.$question->version.'): '.Str::limit($question->stem, 80));
        }

        return redirect()->route('questions.index')->with('status', $wasApproved ? 'Saved as version '.$question->version.'. A reviewer needs to approve it again.' : 'Question saved.');
    }

    public function review(Request $request, Question $question)
    {
        abort_unless(can_do('questions.review'), 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'return', 'retire'])]]);
        if ($data['decision'] === 'approve' && $question->author_id === $request->user()->id && ! can_do('certificates.issue')) {
            return back()->withErrors(['decision' => 'Another reviewer needs to approve a question you wrote.']);
        }
        $question->forceFill([
            'status' => ['approve' => 'approved', 'return' => 'draft', 'retire' => 'retired'][$data['decision']],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();
        $this->audit->log('question.'.$data['decision'].'d', $question, 'Question '.$question->status.': '.Str::limit($question->stem, 80));

        return back()->with('status', ['approve' => 'Approved. It can now be drawn into examinations.', 'return' => 'Returned to draft.', 'retire' => 'Retired. It will not be drawn into new papers.'][$data['decision']]);
    }

    public function storeCategory(Request $request)
    {
        abort_unless(can_do('questions.review'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:question_categories,name']]);
        QuestionCategory::create($data + ['sort' => QuestionCategory::max('sort') + 1]);

        return back()->with('status', 'Category added.');
    }

    public function media(Question $question)
    {
        abort_unless(can_do('exams.manage') || can_do('questions.review'), 403);
        abort_unless($question->media_path && Storage::disk('local')->exists($question->media_path), 404);

        return response()->file(Storage::disk('local')->path($question->media_path));
    }

    protected function fill(Question $question, Request $request, ImageStore $files): void
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Question::TYPES))],
            'question_category_id' => ['required', 'exists:question_categories,id'],
            'difficulty' => ['required', Rule::in(array_keys(Question::DIFFICULTIES))],
            'scenario' => ['nullable', 'string', 'max:3000'],
            'stem' => ['required', 'string', 'max:2000'],
            'marks' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'explanation' => ['nullable', 'string', 'max:3000'],
            'options' => ['nullable', 'array', 'max:12'],
            'options.*' => ['nullable', 'string', 'max:500'],
            'correct' => ['nullable'],
            'lefts' => ['nullable', 'array', 'max:10'],
            'rights' => ['nullable', 'array', 'max:10'],
            'accepted' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'string', 'max:3000'],
            'tf' => ['nullable', 'in:true,false'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp3,m4a,ogg,wav,aac', 'max:20480'],
            'youtube_url' => ['nullable', 'string', 'max:255'],
            'remove_media' => ['nullable', 'boolean'],
        ], ['stem.required' => 'Write the question.']);

        [$options, $answer] = $this->answerFor($data);

        $question->fill([
            'type' => $data['type'],
            'question_category_id' => $data['question_category_id'],
            'difficulty' => $data['difficulty'],
            'scenario' => $data['scenario'] ?? null,
            'stem' => $data['stem'],
            'marks' => $data['marks'],
            'explanation' => $data['explanation'] ?? null,
            'options' => $options,
            'answer' => $answer,
            'shuffle_options' => $request->boolean('shuffle_options'),
        ]);

        if ($request->boolean('remove_media')) {
            $question->fill(['media_kind' => null, 'media_path' => null, 'youtube_id' => null]);
        }
        if (filled($data['youtube_url'] ?? null)) {
            $id = Video::youtubeIdFrom($data['youtube_url']) ?? throw ValidationException::withMessages(['youtube_url' => 'That does not look like a YouTube video link.']);
            $question->fill(['media_kind' => 'video', 'youtube_id' => $id, 'media_path' => null]);
        }
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $isAudio = str_starts_with((string) $file->getMimeType(), 'audio') || in_array(strtolower($file->getClientOriginalExtension()), ['mp3', 'm4a', 'ogg', 'wav', 'aac']);
            $question->fill([
                'media_kind' => $isAudio ? 'audio' : 'image',
                'media_path' => $isAudio ? $files->storeDocument($file, 'questions') : $files->storeImage($file, 'questions', 1200),
                'youtube_id' => null,
            ]);
        }
    }

    /** Turn the form's fields into the stored options and answer for each type. */
    protected function answerFor(array $data): array
    {
        $fail = fn (string $msg) => throw ValidationException::withMessages(['options' => $msg]);
        $lines = fn (?string $text) => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text))));

        switch ($data['type']) {
            case 'single':
            case 'multiple':
                $options = [];
                foreach ($data['options'] ?? [] as $i => $text) {
                    if (filled($text)) {
                        $options[] = ['key' => (string) $i, 'text' => trim($text)];
                    }
                }
                count($options) >= 2 || $fail('Give at least two answer choices.');
                $keys = array_column($options, 'key');
                $correct = array_values(array_intersect(array_map('strval', (array) ($data['correct'] ?? [])), $keys));
                $correct || $fail('Tick the correct answer.');
                if ($data['type'] === 'single') {
                    count($correct) === 1 || $fail('Tick exactly one correct answer, or choose "several answers".');

                    return [$this->rekey($options), ['key' => $this->letter(array_search($correct[0], $keys))]];
                }

                return [$this->rekey($options), ['keys' => array_map(fn ($k) => $this->letter(array_search($k, $keys)), $correct)]];
            case 'true_false':
                isset($data['tf']) || $fail('Choose whether the statement is true or false.');

                return [null, ['value' => $data['tf'] === 'true']];
            case 'short':
            case 'fill_blank':
                $accepted = $lines($data['accepted'] ?? '');
                $accepted || $fail('Give at least one accepted answer.');
                if ($data['type'] === 'fill_blank' && ! str_contains($data['stem'], '___')) {
                    throw ValidationException::withMessages(['stem' => 'Mark the blank in the sentence with three or more underscores: ___']);
                }

                return [null, ['accepted' => $accepted]];
            case 'ordering':
                $items = $lines($data['items'] ?? '');
                count($items) >= 3 || $fail('Give at least three steps, in the correct order.');

                return [collect($items)->values()->map(fn ($t, $i) => ['key' => $this->letter($i), 'text' => $t])->all(), null];
            case 'matching':
                $pairs = [];
                foreach ($data['lefts'] ?? [] as $i => $left) {
                    $right = $data['rights'][$i] ?? null;
                    if (filled($left) && filled($right)) {
                        $pairs[] = ['key' => $this->letter(count($pairs)), 'left' => trim($left), 'right' => trim($right)];
                    }
                }
                count($pairs) >= 3 || $fail('Give at least three pairs.');

                return [$pairs, null];
            default:
                return [null, null];
        }
    }

    protected function rekey(array $options): array
    {
        return collect($options)->values()->map(fn ($o, $i) => ['key' => $this->letter($i), 'text' => $o['text']])->all();
    }

    protected function letter(int $i): string
    {
        return chr(ord('a') + $i);
    }
}
