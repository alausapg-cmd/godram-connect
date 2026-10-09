<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrolment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use App\Services\Achievements;
use App\Services\Cbt;
use App\Services\Certificates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Sample question bank, examinations, attempts and certificates for previews.
 * Attempts are sat through the real CBT engine, so scores and certificates are genuine.
 */
class ExamDemoSeeder extends Seeder
{
    protected array $categories = [];

    public function run(): void
    {
        $national = OrgUnit::root();
        $region1 = OrgUnit::where('type', 'region')->where('name', 'Region 1')->first();
        $agege = OrgUnit::where('type', 'district')->where('name', 'Agege')->first();
        $trainer = User::where('email', 'training@demo.godram.test')->first();
        $reviewer = User::where('email', 'national@demo.godram.test')->first();
        if (! $national || ! $region1 || ! $agege || ! $trainer) {
            return;
        }

        $this->bank($trainer, $reviewer);
        $this->signatures();

        $foundations = Course::where('title', 'Foundations of Drama Ministry')->first();
        $finalExam = $foundations ? $this->exam([
            'title' => 'Foundations of Drama Ministry: Final examination', 'course_id' => $foundations->id, 'org_unit_id' => $national->id,
            'instructions' => "Answer every question. You can move back and forth and flag questions to check before you finish.\nThis is a certification examination: a pass earns the GODRAM Certificate of Completion.",
            'mode' => 'certification', 'duration_minutes' => 25, 'pass_mark' => 70, 'max_attempts' => 2, 'result_policy' => 'best',
            'awards_certificate' => true, 'requires_course_completion' => true, 'release' => 'immediate',
        ], [['Christian Drama', null, 4], ['Acting', null, 3], ['Bible and Ministry', null, 3], ['Production', null, 2]], $trainer) : null;

        $national2026 = $this->exam([
            'title' => 'GODRAM Drama Ministers\' Certification 2026', 'org_unit_id' => $national->id,
            'instructions' => 'Open to every GODRAM member. It covers the whole ministry: drama, acting, writing, directing, production and the Word.',
            'mode' => 'certification', 'duration_minutes' => 30, 'pass_mark' => 70, 'max_attempts' => 2, 'result_policy' => 'best',
            'awards_certificate' => true, 'release' => 'immediate', 'closes_at' => now()->addDays(40),
        ], [['Christian Drama', null, 3], ['Acting', null, 3], ['Scriptwriting', 'easy', 1], ['Scriptwriting', 'moderate', 2], ['Directing', null, 3], ['Production', null, 2], ['Bible and Ministry', null, 3]], $trainer);

        $practice = $this->exam([
            'title' => 'Acting practice quiz', 'org_unit_id' => $agege->id,
            'instructions' => 'A short practice before the Acting Fundamentals Workshop. Try it as often as you like; answers and explanations are shown at the end.',
            'mode' => 'practice', 'duration_minutes' => 10, 'pass_mark' => 60, 'max_attempts' => null, 'result_policy' => 'best',
            'show_review' => true, 'release' => 'immediate', 'shuffle_options' => true,
        ], [['Acting', null, 6], ['Directing', 'easy', 2]], User::where('email', 'agege.district@demo.godram.test')->first() ?? $trainer);

        $script = Course::where('title', 'Scriptwriting for Evangelism')->first();
        $written = $this->exam([
            'title' => 'Scriptwriting: written assessment', 'org_unit_id' => $region1->id, 'course_id' => $script?->id,
            'instructions' => 'Includes one written answer marked by an examiner. Results are released by the Regional training team.',
            'mode' => 'certification', 'duration_minutes' => 20, 'pass_mark' => 60, 'max_attempts' => 1,
            'awards_certificate' => true, 'release' => 'manual',
        ], [['Scriptwriting', 'easy', 2], ['Scriptwriting', 'moderate', 3], ['Scriptwriting', 'difficult', 1]], User::where('email', 'region1@demo.godram.test')->first() ?? $trainer);
        if ($script) {
            $written->forceFill(['org_unit_id' => $script->org_unit_id])->save();
        }

        $cbt = app(Cbt::class);
        $showcase = User::where('email', 'agege-central.member@demo.godram.test')->first()?->member;

        // The national certification: members across Region 1 over the past fortnight.
        $candidates = Member::placedWithinPaths([$region1->path])->whereHas('user')->where('status', 'active')->whereNotNull('approved_at')
            ->inRandomOrder()->limit(26)->get();
        if ($showcase) {
            $candidates = $candidates->reject(fn ($m) => $m->id === $showcase->id)->prepend($showcase);
        }
        foreach ($candidates as $i => $member) {
            $skill = $i === 0 ? 0.92 : [0.45, 0.6, 0.7, 0.78, 0.85, 0.95][mt_rand(0, 5)];
            $attempt = $this->sit($cbt, $national2026, $member, now()->subDays(mt_rand(1, 14))->setTime(mt_rand(8, 20), mt_rand(0, 59)), $skill, auto: $i === 5);
            if ($attempt && ! $attempt->passed && $i % 3 === 0) {
                $this->sit($cbt, $national2026, $member, $attempt->submitted_at->copy()->addDays(1), min(0.97, $skill + 0.2));
            }
        }
        // One candidate sitting it right now, for the "in progress" view.
        $live = Member::placedWithinPaths([$national->path])->whereHas('user')->where('status', 'active')->whereNotNull('approved_at')
            ->whereNotIn('id', $candidates->pluck('id'))->inRandomOrder()->first();
        if ($live) {
            Carbon::setTestNow(now()->subMinutes(6));
            $attempt = $cbt->start($national2026, $live, '102.89.'.mt_rand(1, 254).'.'.mt_rand(1, 254), 'Mozilla/5.0 (Linux; Android 12; TECNO KG5) Chrome/127');
            Carbon::setTestNow();
            foreach ($attempt->questions->take(9) as $item) {
                $cbt->save($attempt, $item->position, $this->answer($item->snapshot, 0.8), 1);
            }
        }

        // The Foundations final, for members who completed every lesson.
        if ($finalExam) {
            $finished = Enrolment::where('course_id', $foundations->id)->where('status', 'completed')->with('member')->get();
            foreach ($finished as $enrolment) {
                $this->sit($cbt, $finalExam, $enrolment->member, now()->subDays(mt_rand(0, 3))->setTime(mt_rand(9, 21), mt_rand(0, 59)), [0.55, 0.75, 0.88, 0.95][mt_rand(0, 3)]);
            }
        }

        // The written assessment: results held, some written answers still to mark.
        if ($script) {
            foreach (Member::placedWithinPaths([$region1->path])->whereHas('user')->where('status', 'active')->inRandomOrder()->limit(8)->get() as $member) {
                $script->enrolments()->firstOrCreate(['member_id' => $member->id], ['status' => 'active']);
                $this->sit($cbt, $written, $member, now()->subDays(mt_rand(1, 5))->setTime(mt_rand(9, 21), 0), [0.6, 0.8, 0.9][mt_rand(0, 2)]);
            }
            $examiner = User::where('email', 'region1@demo.godram.test')->first() ?? $trainer;
            foreach ($written->attempts()->where('needs_marking', true)->take(3)->get() as $attempt) {
                foreach ($attempt->questions as $item) {
                    if ($item->snapshot['type'] === 'open' && $item->isAnswered()) {
                        $cbt->mark($item, round($item->snapshot['marks'] * [0.5, 0.7, 0.9][mt_rand(0, 2)] * 2) / 2, $examiner);
                    }
                }
            }
        }

        // Agege members trying the practice quiz.
        foreach (Member::placedWithinPaths([$agege->path])->whereHas('user')->where('status', 'active')->where('id', '!=', $showcase?->id ?? 0)->inRandomOrder()->limit(6)->get() as $member) {
            $this->sit($cbt, $practice, $member, now()->subDays(mt_rand(0, 4))->setTime(mt_rand(9, 21), 0), [0.5, 0.7, 0.9][mt_rand(0, 2)]);
        }

        // A special recognition certificate, and the milestones members have already reached.
        if ($showcase) {
            app(Certificates::class)->special($showcase, 'Certificate of Recognition', 'is recognised for faithful service in drama evangelism', 'Agege District Easter Outreach 2026', 'Led the Agege Central drama team at six outreach performances during the Easter campaign.', $reviewer ?? $trainer);
        }
        app(Achievements::class)->evaluateAll();
    }

    /** One candidate sits an exam through the real engine, answering with the given chance of being right. */
    protected function sit(Cbt $cbt, Exam $exam, Member $member, Carbon $at, float $skill, bool $auto = false): ?ExamAttempt
    {
        Carbon::setTestNow($at);
        try {
            $attempt = $cbt->start($exam, $member, '105.112.'.mt_rand(1, 254).'.'.mt_rand(1, 254), ['Mozilla/5.0 (Linux; Android 13; Infinix X6833B) Chrome/126', 'Mozilla/5.0 (Linux; Android 11; itel A665L) Chrome/125', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) Safari/604.1'][mt_rand(0, 2)]);
        } catch (\Throwable) {
            Carbon::setTestNow();

            return null;
        }
        $minutes = 0;
        foreach ($attempt->questions as $item) {
            $seconds = mt_rand(20, 75);
            $minutes += $seconds / 60;
            Carbon::setTestNow($at->copy()->addSeconds((int) ($minutes * 60)));
            if ($auto && $item->position > $attempt->questions->count() - 3) {
                continue;
            }
            $cbt->save($attempt, $item->position, $this->answer($item->snapshot, $skill), 1, mt_rand(0, 12) === 0, $seconds);
        }
        if (mt_rand(0, 4) === 0) {
            $cbt->signal($attempt, 'offline');
            $cbt->signal($attempt, 'online');
        }
        Carbon::setTestNow($auto ? $attempt->deadline_at->copy()->addMinute() : $at->copy()->addSeconds((int) ($minutes * 60) + 40));
        $attempt = $cbt->submit($attempt, $auto);
        Carbon::setTestNow();

        return $attempt;
    }

    /** A right answer with the given chance, otherwise a plausible wrong one. */
    protected function answer(array $s, float $skill): mixed
    {
        $right = mt_rand(1, 100) <= $skill * 100;
        $options = $s['options'] ?? [];
        $keys = isset($options[0]) ? array_column($options, 'key') : [];

        return match ($s['type']) {
            'single' => $right ? $s['answer']['key'] : collect($keys)->reject(fn ($k) => $k === $s['answer']['key'])->random(),
            'multiple' => $right ? $s['answer']['keys'] : array_values(array_slice($keys, 0, 1)),
            'true_false' => $right ? $s['answer']['value'] : ! $s['answer']['value'],
            'short', 'fill_blank' => $right ? $s['answer']['accepted'][0] : 'not sure',
            'ordering' => $right ? $s['answer'] : $keys,
            'matching' => collect($options['left'])->mapWithKeys(fn ($l, $n) => [$l['key'] => $right || $n > 0 ? $l['key'] : collect($options['right'])->pluck('key')->reject(fn ($k) => $k === $l['key'])->first()])->all(),
            'open' => $right
                ? 'I would open with the woman at the market stall arguing about price, because everyone in the audience knows that scene. The preacher passes but she is too busy. At the end she loses everything in a fire and remembers the words she ignored. The truth is Hebrews 3:15: today, if you hear His voice.'
                : 'Start with a song and then the drama.',
            default => null,
        };
    }

    protected function exam(array $values, array $blueprint, User $by): Exam
    {
        $exam = Exam::create($values + ['status' => 'published', 'created_by' => $by->id, 'is_demo' => true, 'opens_at' => now()->subDays(20)]);
        foreach ($blueprint as [$category, $difficulty, $count]) {
            $exam->blueprint()->create(['question_category_id' => $this->categories[$category], 'difficulty' => $difficulty, 'count' => $count]);
        }
        $exam->forceFill(['question_count' => collect($blueprint)->sum(2)])->save();

        return $exam;
    }

    /** Simple drawn signatures so preview certificates are not blank. Replace them under Certificates > Signatures. */
    protected function signatures(): void
    {
        foreach (['national_coordinator' => 3, 'training_administrator' => 7] as $role => $seed) {
            if (Storage::disk('local')->exists('signatures/'.$role.'.png')) {
                continue;
            }
            mt_srand($seed);
            $img = imagecreatetruecolor(420, 120);
            imagealphablending($img, false);
            imagesavealpha($img, true);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
            imagealphablending($img, true);
            $ink = imagecolorallocate($img, 22, 34, 90);
            imagesetthickness($img, 3);
            // Cursive loops: a point circling while it moves right, with the loops shrinking along the name.
            $x = $y = null;
            for ($t = 0.0; $t < 46; $t += 0.08) {
                $size = ($t < 8 ? 34 : 18) + mt_rand(0, 2);
                $nx = 30 + $t * 7.5 + cos($t * 1.9 + $seed) * $size * 0.45;
                $ny = 62 - sin($t * 1.9 + $seed) * $size * (0.8 + 0.2 * sin($t / 3));
                if ($x !== null) {
                    imageline($img, (int) $x, (int) $y, (int) $nx, (int) $ny, $ink);
                }
                [$x, $y] = [$nx, $ny];
            }
            imageline($img, 30, 98, 380, 88, $ink);
            ob_start();
            imagepng($img);
            Storage::disk('local')->put('signatures/'.$role.'.png', ob_get_clean());
            mt_srand();
        }
    }

    protected function bank(User $author, ?User $reviewer): void
    {
        foreach (['Christian Drama', 'Acting', 'Scriptwriting', 'Directing', 'Production', 'Bible and Ministry'] as $i => $name) {
            $this->categories[$name] = QuestionCategory::firstOrCreate(['name' => $name], ['sort' => $i])->id;
        }
        $choices = fn (array $texts) => collect($texts)->values()->map(fn ($t, $i) => ['key' => chr(97 + $i), 'text' => $t])->all();

        foreach ($this->questions() as $q) {
            [$category, $type, $difficulty, $stem, $data] = $q;
            $values = match ($type) {
                'single' => ['options' => $choices($data[0]), 'answer' => ['key' => chr(97 + $data[1])]],
                'multiple' => ['options' => $choices($data[0]), 'answer' => ['keys' => array_map(fn ($i) => chr(97 + $i), $data[1])]],
                'true_false' => ['answer' => ['value' => $data[0]]],
                'short', 'fill_blank' => ['answer' => ['accepted' => $data[0]]],
                'ordering' => ['options' => $choices($data[0])],
                'matching' => ['options' => collect($data[0])->values()->map(fn ($p, $i) => ['key' => chr(97 + $i), 'left' => $p[0], 'right' => $p[1]])->all()],
                default => [],
            };
            Question::create($values + [
                'question_category_id' => $this->categories[$category],
                'type' => $type,
                'difficulty' => $difficulty,
                'stem' => $stem,
                'scenario' => $q[6] ?? null,
                'explanation' => $q[5] ?? null,
                'marks' => $type === 'open' ? 5 : ($type === 'matching' ? 2 : 1),
                'shuffle_options' => ! (($data['keep_order'] ?? false)),
                'status' => 'approved',
                'author_id' => $author->id,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now()->subDays(25),
                'is_demo' => true,
            ]);
        }
        // Two questions still waiting for review, so the review queue has something in it.
        Question::create(['question_category_id' => $this->categories['Directing'], 'type' => 'single', 'difficulty' => 'moderate',
            'stem' => 'In a church hall with no stage lights, where should the most important moment of a scene be played?',
            'options' => $choices(['In the brightest part of the hall, close to the audience', 'At the back, for mystery', 'Wherever the actor feels comfortable', 'Behind the pulpit']),
            'answer' => ['key' => 'a'], 'explanation' => 'If the audience cannot see a face, they cannot receive the moment.', 'status' => 'draft', 'author_id' => $author->id, 'is_demo' => true]);
        Question::create(['question_category_id' => $this->categories['Production'], 'type' => 'true_false', 'difficulty' => 'easy',
            'stem' => 'A props table should be checked by the stage manager before every performance.', 'answer' => ['value' => true],
            'status' => 'draft', 'author_id' => $author->id, 'is_demo' => true]);
    }

    /** [category, type, difficulty, stem, data, explanation, scenario] */
    protected function questions(): array
    {
        return [
            // Christian Drama
            ['Christian Drama', 'single', 'easy', 'What is the first purpose of a GODRAM drama presentation?', [['To bring people to Christ through the Gospel', 'To entertain the congregation', 'To raise funds for the Assembly', 'To showcase the actors\' talent'], 0], 'Drama in GODRAM is a ministry. Entertainment and skill serve the message, never the other way round.'],
            ['Christian Drama', 'true_false', 'easy', 'GODRAM began in 1991 when Paul Adaramola started training drama groups in Lagos.', [true], 'With the permission of the Lagos District Overseer, Pastor S. A. Abiodun.'],
            ['Christian Drama', 'single', 'moderate', 'GOFAMINT recognised GODRAM as a national department in which year?', [['1996', '1991', '1999', '2004'], 0], 'Recognition followed the films "Your Choice" and "Ohun Too Yan".'],
            ['Christian Drama', 'multiple', 'moderate', 'Which of these should happen before a drama team ministers? Choose all that apply.', [['Prayer together as a team', 'Rehearsal of the full piece', 'Checking that the message is clear', 'Selling tickets at the door'], [0, 1, 2]], 'Prayer, preparation and a clear message come before any performance.'],
            ['Christian Drama', 'fill_blank', 'easy', 'A short drama performed to prepare hearts before a sermon is often called a curtain ___.', [['raiser', 'raisers']], 'A curtain raiser opens the programme and prepares the audience for the Word.'],
            ['Christian Drama', 'single', 'difficult', 'An audience laughs all through an evangelistic sketch, but nobody responds to the altar call. What is the most likely cause?', [['The comedy overshadowed the message', 'The audience was too small', 'The actors were not in costume', 'The sketch was too short'], 0], 'Humour can open hearts, but the message must land clearly by the end.'],
            ['Christian Drama', 'true_false', 'moderate', 'A drama minister\'s private life does not affect their ministry on stage.', [false], 'The minister behind the character matters: integrity off stage gives power on stage.'],
            ['Christian Drama', 'single', 'moderate', 'Which film was among those GODRAM produced?', [['Valley of Baca (Afonifoji Omije)', 'Burning Hell', 'The Ten Commandments', 'Pilgrim\'s Progress'], 0], 'The others were films GOFAMINT evangelists screened at crusades before GODRAM.'],

            // Acting
            ['Acting', 'single', 'easy', 'What does "projection" mean for an actor?', [['Making the voice carry clearly to the back of the audience', 'Shouting as loudly as possible', 'Using a microphone', 'Speaking very fast'], 0], 'Projection uses breath and placement, not strain.'],
            ['Acting', 'true_false', 'easy', 'An actor should keep their back to the audience for most of a scene.', [false], 'Open your body to the audience so they can see your face and hear your voice.'],
            ['Acting', 'single', 'moderate', 'You forget your line in the middle of an outdoor performance. What is the best thing to do?', [['Stay in character and carry the scene on in your own words', 'Stop and ask the director', 'Walk off stage', 'Start the scene again'], 0], 'Staying in character protects the moment. The audience rarely knows a line was missed.'],
            ['Acting', 'multiple', 'moderate', 'Which of these help an actor build a believable character? Choose all that apply.', [['Knowing what the character wants', 'Observing real people', 'Understanding the character\'s background', 'Copying a famous actor exactly'], [0, 1, 2]], 'Truthful characters come from want, observation and background.'],
            ['Acting', 'single', 'easy', 'Why do actors warm up before a performance?', [['To prepare the body and voice and avoid strain', 'To use up spare time', 'To impress the audience', 'Because the director says so'], 0]],
            ['Acting', 'single', 'difficult', 'In a scene, your character must weep over a lost son. Which approach is most truthful?', [['Recall a real feeling of loss and let it shape your actions', 'Rub your eyes to make them red', 'Cover your face so no one sees', 'Wail as loudly as you can'], 0], 'Emotion that comes from real memory reads as true; forced display does not.'],
            ['Acting', 'true_false', 'moderate', 'Stillness on stage can be as powerful as movement.', [true], 'A still actor draws the eye, especially after action.'],
            ['Acting', 'short', 'moderate', 'What is the name for a long speech by one character, spoken alone?', [['monologue', 'a monologue', 'soliloquy']], 'A monologue; when the character speaks their thoughts alone, it may be called a soliloquy.'],
            ['Acting', 'single', 'moderate', 'Outdoors without microphones, where should most of your voice come from?', [['Breath supported from the diaphragm', 'The throat', 'The nose', 'Whispering close to the front row'], 0]],

            // Scriptwriting
            ['Scriptwriting', 'single', 'easy', 'Before writing an evangelistic script, what should you settle first?', [['The one truth the script will carry', 'The costumes', 'The cast list', 'The length of the interval'], 0], 'Start with one truth. Everything else serves it.'],
            ['Scriptwriting', 'true_false', 'easy', 'Dialogue should sound like real people talking.', [true]],
            ['Scriptwriting', 'fill_blank', 'easy', 'Words in a script that describe action, not speech, are called stage ___.', [['directions', 'direction']], 'Stage directions tell the cast what happens and where.'],
            ['Scriptwriting', 'single', 'moderate', 'Which opening grabs a market-day audience fastest?', [['A familiar, lively scene they recognise at once', 'A narrator explaining the plot', 'A long prayer', 'A list of the characters'], 0], 'Open with something the audience recognises from their own lives.'],
            ['Scriptwriting', 'ordering', 'moderate', 'Put these stages of writing a script in order.', [['Choose the truth to carry', 'Create the characters', 'Outline the scenes', 'Write the dialogue', 'Read it aloud with the team and revise'], 'keep_order' => true]],
            ['Scriptwriting', 'multiple', 'moderate', 'Which make characters believable to a Nigerian street audience? Choose all that apply.', [['Names and speech people use every day', 'Situations from daily life', 'Clear wants and struggles', 'Long sermons in every line'], [0, 1, 2]]],
            ['Scriptwriting', 'single', 'moderate', 'Your ten-minute script runs to twenty minutes in rehearsal. What should you cut first?', [['Scenes that do not move the one truth forward', 'The ending', 'The altar call', 'All the humour'], 0]],
            ['Scriptwriting', 'open', 'difficult', 'Describe the first scene of a ten-minute outreach sketch for a market audience. Say what happens, who is in it, and how it points to the one truth of your script.', [], 'Full marks: a recognisable setting, a clear character with a want, and a link to one stated truth. Half marks: a scene without a clear truth.'],
            ['Scriptwriting', 'open', 'difficult', 'Write the closing lines of a short sketch about forgiveness, and explain how they lead the audience to the altar call.', [], 'Look for an ending that resolves the story and hands over naturally to the minister.'],

            // Directing
            ['Directing', 'single', 'easy', 'Who is responsible for the overall vision of a production?', [['The director', 'The lead actor', 'The stage manager', 'The usher'], 0]],
            ['Directing', 'true_false', 'easy', 'Blocking is the planned movement of actors on stage.', [true]],
            ['Directing', 'single', 'moderate', 'In an open-air ministration with the audience on three sides, how should the director stage the scene?', [['Move actors so every side sees faces in turn', 'Face the front only', 'Keep the actors in one line', 'Place the actors on the ground'], 0], 'Thrust staging needs movement so no side is left out.'],
            ['Directing', 'single', 'difficult', 'Two actors keep blocking each other in the key moment. What is the best fix?', [['Re-block so the speaker is open to the audience and the listener reacts', 'Ask them to speak louder', 'Cut the scene', 'Let them sort it out'], 0]],
            ['Directing', 'multiple', 'moderate', 'Which belong in a director\'s rehearsal plan? Choose all that apply.', [['Scenes to work on and in what order', 'Who is needed when', 'Notes from the last rehearsal', 'The offering amount'], [0, 1, 2]]],
            ['Directing', 'single', 'easy', 'What is a "read-through"?', [['The cast reading the whole script aloud together', 'The audience reading the programme', 'A rehearsal with costumes', 'The final performance'], 0]],

            // Production
            ['Production', 'single', 'easy', 'Who runs the show backstage during a performance?', [['The stage manager', 'The director', 'The scriptwriter', 'The pastor'], 0], 'Once the show starts, the stage manager runs it.'],
            ['Production', 'matching', 'moderate', 'Match each production role with its main task.', [[['Stage manager', 'Runs the performance and calls the cues'], ['Props master', 'Finds and looks after objects used on stage'], ['Costume team', 'Dresses the actors for each scene'], ['Sound team', 'Music, effects and microphones']]]],
            ['Production', 'ordering', 'moderate', 'Put these steps for an outreach performance in order.', [['Get permission from the Assembly and local authority', 'Rehearse with props and costumes', 'Pray and set up at the venue', 'Perform', 'Pack up and leave the ground clean'], 'keep_order' => true], 'Permission first, clean-up last.'],
            ['Production', 'true_false', 'moderate', 'For open-air ministrations a safety plan, including crowd control, should be agreed before the day.', [true], 'Order and safety protect the audience and the ministry\'s witness.'],
            ['Production', 'single', 'moderate', 'The generator fails in the middle of an evening performance. What should happen first?', [['The stage manager keeps calm and follows the agreed backup plan', 'Everyone leaves', 'The actors stop and wait', 'The audience is asked to fix it'], 0], 'A backup plan, such as torches and an unamplified version of the scene, should be agreed before the day.'],
            ['Production', 'single', 'easy', 'What is a "prop"?', [['An object an actor uses on stage', 'A part of the stage floor', 'A type of light', 'A script'], 0]],
            ['Production', 'multiple', 'difficult', 'Which should a production budget for an outreach include? Choose all that apply.', [['Transport', 'Sound and power', 'Costumes and props', 'Payment to the audience'], [0, 1, 2]]],

            // Bible and Ministry
            ['Bible and Ministry', 'single', 'easy', 'Which prophet acted out a siege of Jerusalem using a clay tablet?', [['Ezekiel', 'Jonah', 'Elijah', 'Amos'], 0], 'Ezekiel 4: God used a prophet\'s acted message.'],
            ['Bible and Ministry', 'single', 'moderate', 'Jesus often taught through stories. What are these stories called?', [['Parables', 'Psalms', 'Proverbs', 'Prophecies'], 0]],
            ['Bible and Ministry', 'true_false', 'easy', 'Ezekiel lay on his side for many days as a sign to Israel.', [true], 'Ezekiel 4:4–6.'],
            ['Bible and Ministry', 'fill_blank', 'moderate', '"Today, if you will hear His ___, harden not your hearts." (Hebrews 3:15)', [['voice']]],
            ['Bible and Ministry', 'single', 'moderate', 'Which verse best describes the drama minister\'s attitude to their gifts?', [['"Whatever you do, do it heartily, as to the Lord" (Colossians 3:23)', '"Eat, drink and be merry"', '"Every man for himself"', '"Let us make a name for ourselves"'], 0]],
            ['Bible and Ministry', 'matching', 'difficult', 'Match each acted sign with its prophet.', [[['Wore a yoke on his neck', 'Jeremiah'], ['Walked barefoot for three years', 'Isaiah'], ['Married a wayward wife', 'Hosea'], ['Shaved his head and divided the hair', 'Ezekiel']]], 'Jeremiah 27, Isaiah 20, Hosea 1, Ezekiel 5.'],
            ['Bible and Ministry', 'single', 'difficult', 'After a performance a young man wants to give his life to Christ. Who should speak with him?', [['A trained counsellor or minister, following the Assembly\'s follow-up plan', 'Whichever actor is nearest', 'Nobody: the drama has done its work', 'The sound team'], 0], 'Every outreach needs a follow-up plan with counsellors ready.'],
        ];
    }
}
