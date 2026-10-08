<?php

namespace Database\Seeders;

use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Enrolment;
use App\Models\LessonProgress;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\PromptResponse;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SessionAttendance;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Database\Seeder;

/**
 * Sample GODRAM Virtual Academy training for previews (courses flagged is_demo).
 * Lesson texts are general drama-ministry teaching written as samples for the
 * National office to replace with its own curriculum.
 */
class AcademyDemoSeeder extends Seeder
{
    protected array $lessonIds = [];

    public function run(): void
    {
        $national = OrgUnit::root();
        $region1 = OrgUnit::where('type', 'region')->where('name', 'Region 1')->first();
        $agege = OrgUnit::where('type', 'district')->where('name', 'Agege')->first();
        if (! $national || ! $region1 || ! $agege) {
            return;
        }

        $nationalUser = User::where('email', 'national@demo.godram.test')->first();
        $trainer = $this->trainingAdministrator($national);
        $agegeCoordinator = $this->userFor('district_coordinator', $agege);

        $foundations = $this->foundations($national, $nationalUser, $trainer);
        $this->scriptwriting($region1, $this->userFor('regional_coordinator', $region1), $trainer);
        $this->acting($agege, $agegeCoordinator);
        $guide = $this->coordinatorsGuide($national, $trainer);
        $this->draft($national, $trainer);

        $this->learners($foundations, $region1, $nationalUser);
        $this->coordinatorLearners($guide);
    }

    protected function trainingAdministrator(OrgUnit $national): ?User
    {
        $existing = User::where('email', 'training@demo.godram.test')->first();
        if ($existing) {
            return $existing;
        }
        $assembly = OrgUnit::where('type', 'assembly')->orderBy('id')->first();
        $member = app(MembershipService::class)->register([
            'first_name' => 'Bolanle', 'last_name' => 'Akinyemi', 'gender' => 'female',
            'phone' => '+2348039990001', 'email' => 'training@demo.godram.test', 'is_demo' => true,
            'joined_on' => now()->subYears(6)->toDateString(),
        ], $assembly, null, true, DemoSeeder::PASSWORD);
        RoleAssignment::create([
            'member_id' => $member->id, 'role_id' => Role::where('key', 'training_administrator')->value('id'),
            'org_unit_id' => $national->id, 'starts_on' => now()->subYears(2)->toDateString(),
        ]);

        return $member->user;
    }

    protected function userFor(string $roleKey, OrgUnit $unit): ?User
    {
        $memberId = RoleAssignment::where('org_unit_id', $unit->id)->whereHas('role', fn ($q) => $q->where('key', $roleKey))->value('member_id');

        return $memberId ? User::where('member_id', $memberId)->first() : null;
    }

    protected function course(array $values, array $facilitators = []): Course
    {
        $course = Course::create($values + ['is_demo' => true, 'status' => 'published', 'published_at' => now()->subDays(20)]);
        foreach (array_values(array_filter($facilitators)) as $i => [$user, $title]) {
            if ($user?->member_id) {
                $course->facilitators()->create(['member_id' => $user->member_id, 'title' => $title, 'sort' => $i]);
            }
        }

        return $course;
    }

    /** sessions: [title, summary, live_at|null, [lessons...], prompts for live] ; lessons: [title, kind, attrs] */
    protected function outline(Course $course, array $sessions): array
    {
        $made = [];
        foreach ($sessions as $i => $s) {
            $session = $course->sessions()->create([
                'title' => $s['title'], 'summary' => $s['summary'] ?? null, 'sort' => $i,
                'live_at' => $s['live_at'] ?? null,
                'live_ends_at' => isset($s['live_at']) ? $s['live_at']->copy()->addMinutes(90) : null,
                'live_url' => $s['live_url'] ?? null, 'live_platform' => $s['live_platform'] ?? null,
            ]);
            foreach ($s['lessons'] ?? [] as $j => $lesson) {
                $prompts = $lesson['prompts'] ?? [];
                unset($lesson['prompts']);
                $model = $course->lessons()->create($lesson + ['course_session_id' => $session->id, 'sort' => $j]);
                foreach ($prompts as $k => $p) {
                    $course->prompts()->create($p + ['lesson_id' => $model->id, 'sort' => $k]);
                }
                $this->lessonIds[$course->id][] = $model->id;
            }
            foreach ($s['prompts'] ?? [] as $k => $p) {
                $course->prompts()->create($p + ['course_session_id' => $session->id, 'sort' => $k]);
            }
            $made[] = $session;
        }

        return $made;
    }

    protected function foundations(OrgUnit $national, ?User $lead, ?User $trainer): Course
    {
        $course = $this->course([
            'title' => 'Foundations of Drama Ministry',
            'kind' => 'blended',
            'org_unit_id' => $national->id,
            'cover_path' => 'archive:godram-12.webp',
            'summary' => 'The calling, character and craft of a GODRAM drama minister, from the rehearsal room to the market square.',
            'description' => "This is the starting point for every GODRAM drama minister. It covers why God uses drama, the life of the person on stage, building a character truthfully and taking a short sketch to the street.\n\nThe course mixes readings you can do on your phone with two live classes and two practical assignments. (Sample course for the preview: the National office will replace it with its own curriculum.)",
            'outcomes' => "Explain why drama is a tool for ministry, from Scripture\nPrepare spiritually and practically before a ministration\nBuild a believable character without losing the message\nPlan and rehearse a ten-minute outreach sketch",
            'starts_on' => now()->subDays(14)->toDateString(),
            'ends_on' => now()->addDays(40)->toDateString(),
            'is_public' => true,
        ], [[$lead, 'Lead facilitator'], [$trainer, 'Training Administrator']]);

        $sessions = $this->outline($course, [
            [
                'title' => 'Called to minister through drama',
                'summary' => 'What makes drama a ministry and not only a performance.',
                'lessons' => [
                    [
                        'title' => 'Why God uses drama', 'kind' => 'text', 'minutes' => 8, 'is_preview' => true,
                        'summary' => 'From the prophets to the parables, God has always shown as well as told.',
                        'scripture' => '“All these things spake Jesus unto the multitude in parables; and without a parable spake he not unto them.” Matthew 13:34',
                        'body' => "Long before there were stages and microphones, God asked His messengers to act out His word. Ezekiel drew a city on a tile and lay beside it (Ezekiel 4). Jeremiah wore a yoke through the streets (Jeremiah 27). Jesus taught in stories that people could see in their minds.\n\nDrama ministry stands in that line. A good sketch lets a person **see** sin, grace and decision in a way a sermon alone sometimes cannot. The market woman who would never enter a church will stop for ten minutes to watch a story about a man who kept postponing his decision for Christ.\n\n## Ministry, not entertainment\n\nThe test of a ministration is not the applause. It is whether the message was clear and whether people met Christ. Entertainment asks *did they enjoy it?* Ministry asks *did they understand, and will they respond?*\n\n## What this means for you\n\n- Every rehearsal is preparation for ministry, so it begins and ends in prayer.\n- The script serves the message. If a scene is funny but confuses the message, it goes.\n- The minister is more important than the costume. People can tell when the actor believes what the character discovers.",
                        'key_points' => "God has always used acted-out messages: the prophets, the parables\nThe goal is a clear message and a response, not applause\nRehearsal is part of the ministry, so pray before and after\nCut anything that entertains but confuses the message",
                        'prompts' => [
                            ['type' => 'complete', 'question' => 'Complete the statement: Entertainment asks "did they enjoy it?" Ministry asks "did they understand, and will they ______?"', 'answer' => 'respond|respond to it', 'explanation' => 'A ministration is measured by understanding and response.'],
                            ['type' => 'poll', 'question' => 'Where has your team ministered most this year?', 'options' => ['In church services', 'Open-air and markets', 'Schools and campuses', 'Online']],
                        ],
                    ],
                    [
                        'title' => 'The life of the drama minister', 'kind' => 'text', 'minutes' => 7,
                        'summary' => 'Character off stage gives weight to the character on stage.',
                        'scripture' => '“And whatsoever ye do, do it heartily, as to the Lord, and not unto men.” Colossians 3:23',
                        'body' => "People who watch you minister on Sunday also see you on Monday. The message of a sketch is strengthened or weakened by the life of the people in it.\n\n## Three habits\n\n**Prayer.** Pray for the people who will watch, by name if you can. Pray for the cast.\n\n**Faithfulness.** Arrive on time for rehearsals. Learn your lines. Treat props and costumes as things entrusted to you.\n\n**Humility.** The lead role and the person who carries the speaker serve the same message. Celebrate others.\n\n## When you are tired\n\nMinistry through drama is demanding. Rest is not a lack of commitment. Tell your coordinator when you need a break before you burn out.",
                        'key_points' => "Your life off stage gives weight to the message on stage\nPray for the audience and the cast\nFaithfulness in small things: time, lines, props\nRest is part of faithful service",
                        'prompts' => [
                            ['type' => 'true_false', 'question' => 'True or false: only people with lead roles need to prepare spiritually.', 'answer' => 'False', 'explanation' => 'Everyone in the cast and crew serves the same message.'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'The minister behind the character',
                'summary' => 'Live class: building a believable character without losing the message.',
                'live_at' => now()->subDays(6)->setTime(18, 0),
                'live_url' => 'https://www.youtube.com/@GODRAMTV/live', 'live_platform' => 'youtube',
                'lessons' => [
                    [
                        'title' => 'Building a character truthfully', 'kind' => 'text', 'minutes' => 10,
                        'summary' => 'Ask the right questions and the character will come alive.',
                        'body' => "Audiences believe a character when the actor knows who the character is. Before your first rehearsal, answer these questions in a notebook.\n\n1. **What does my character want** in this scene?\n2. **What stands in the way?**\n3. **What does my character believe about God** at the start, and at the end?\n4. **How does my character walk, sit and speak?** Watch real people in the market or at the bus stop.\n\n## Playing a sinner without sinning\n\nYou may be asked to play a drunkard, a thief or a proud man. Show the behaviour clearly enough to be understood, but never in a way that glorifies it or that would be indecent. The point of the character is the turning point.\n\n## Staying in the message\n\nAt every rehearsal, the director should ask: does this scene still point to the message? A character can be funny, sad or frightening. A character must never be confusing.",
                        'key_points' => "Know what your character wants and what stands in the way\nObserve real people to find walk, voice and gesture\nShow sin clearly but never glorify it\nEvery scene must still point to the message",
                        'prompts' => [
                            ['type' => 'choice', 'question' => 'Which question should you answer first when building a character?', 'options' => ['What costume will I wear?', 'What does my character want?', 'How long is my part?'], 'answer' => 'What does my character want?'],
                        ],
                    ],
                    [
                        'title' => 'Voice and projection outdoors', 'kind' => 'text', 'minutes' => 6,
                        'summary' => 'Being heard at the back without shouting.',
                        'body' => "Open-air ministrations rarely have good sound. Your voice must carry.\n\n- **Breathe low.** Breathe into your belly, not your shoulders.\n- **Speak to the back row.** Pick a person at the back and speak to them.\n- **Slow down.** Outdoors, words get lost when you rush.\n- **Face the audience.** Cheat your body open even when talking to another character.\n\nWarm up for five minutes before every ministration: hum, stretch your jaw, and say tongue-twisters in Yoruba and English.",
                        'key_points' => "Breathe from the belly\nSpeak to the back row\nSlow down outdoors\nWarm up before every ministration",
                    ],
                ],
                'prompts' => [
                    ['type' => 'poll', 'question' => 'Which part of character work do you find hardest?', 'options' => ['Learning lines', 'Voice and projection', 'Staying in character', 'Showing emotion'], 'show_results' => true],
                    ['type' => 'short', 'question' => 'In one word, what does your character need to discover by the end of a gospel sketch?', 'answer' => 'grace|christ|jesus|salvation|truth'],
                ],
            ],
            [
                'title' => 'Taking drama to the street',
                'summary' => 'Live class: planning, rehearsing and leading a safe outreach ministration.',
                'live_at' => now()->addDays(3)->setTime(18, 0),
                'live_url' => 'https://www.youtube.com/@GODRAMTV/live', 'live_platform' => 'youtube',
                'lessons' => [
                    [
                        'title' => 'Planning a ten-minute outreach sketch', 'kind' => 'document', 'minutes' => 12,
                        'summary' => 'A simple plan you can use for any market, motor park or campus outreach.',
                        'body' => "Use the planning sheet below for every outreach sketch.\n\n## The shape of a ten-minute sketch\n\n- **Minute 0 to 1: Gather.** Music, a call, or a loud and funny opening to draw a crowd.\n- **Minute 1 to 6: The story.** One main character, one clear problem.\n- **Minute 6 to 8: The turning point.** The character meets the truth.\n- **Minute 8 to 10: The invitation.** A minister steps forward and speaks plainly.\n\n## Before you go\n\nGet permission from the market leaders or the motor park chairman. Tell your Assembly Coordinator where you will be. Bring follow-up cards and pens.",
                        'key_points' => "Gather, story, turning point, invitation\nOne main character and one clear problem\nGet permission and tell your coordinator\nHave follow-up cards ready",
                    ],
                    [
                        'title' => 'Order and safety at open-air ministrations', 'kind' => 'text', 'minutes' => 5,
                        'scripture' => '“Let all things be done decently and in order.” 1 Corinthians 14:40',
                        'body' => "Appoint one person to look after the crowd and the cast's belongings. Keep costumes modest and props safe: no real blades, no open flames. Plan where the cast changes. If tension rises, the leader ends the sketch calmly and the team prays and leaves together.",
                        'key_points' => "Appoint a crowd and belongings steward\nNo real blades or open flames\nThe leader may end a sketch calmly if tension rises",
                    ],
                ],
                'prompts' => [
                    ['type' => 'choice', 'question' => 'What comes straight after the turning point?', 'options' => ['The invitation', 'A second song', 'The cast bows'], 'answer' => 'The invitation'],
                ],
            ],
        ]);

        $course->assignments()->create([
            'title' => 'Write a ten-minute outreach sketch',
            'brief' => "Using the plan from Session 3, write a sketch for a market outreach.\n\n- One main character and one clear problem\n- A turning point that points to Christ\n- Stage directions in brackets\n\nType it below or attach a Word document.",
            'accepts' => ['text', 'file'], 'due_at' => now()->addDays(10)->setTime(23, 59), 'max_score' => 20,
            'course_session_id' => $sessions[2]->id, 'sort' => 0,
        ]);
        $course->assignments()->create([
            'title' => 'Record a one-minute monologue',
            'brief' => "Record yourself performing a one-minute monologue from the character you built in Session 2. Upload it to YouTube as **unlisted** or to Google Drive and paste the link.",
            'accepts' => ['link'], 'due_at' => now()->addDays(17)->setTime(23, 59), 'max_score' => 10,
            'course_session_id' => $sessions[1]->id, 'sort' => 1,
        ]);

        return $course;
    }

    protected function scriptwriting(OrgUnit $region, ?User $lead, ?User $trainer): void
    {
        $course = $this->course([
            'title' => 'Scriptwriting for Evangelism',
            'kind' => 'recorded', 'org_unit_id' => $region->id, 'cover_path' => 'archive:godram-35.webp',
            'summary' => 'Write short scripts that hold a crowd and point clearly to Christ. Self-paced, for members in Region 1.',
            'outcomes' => "Turn a Bible truth into a story\nWrite dialogue people believe\nFormat a script your team can rehearse from",
            'is_public' => true,
        ], [[$lead, 'Facilitator']]);
        $this->outline($course, [
            ['title' => 'From truth to story', 'lessons' => [
                ['title' => 'Start with one truth', 'kind' => 'text', 'minutes' => 6, 'is_preview' => true,
                    'scripture' => '“Write the vision, and make it plain upon tables, that he may run that readeth it.” Habakkuk 2:2',
                    'body' => "A short script can carry one truth well. Write it in a single sentence before you write anything else: *God's grace is greater than my past.* Every scene must serve that sentence.",
                    'key_points' => "One script, one truth\nWrite the truth in one sentence first"],
                ['title' => 'Characters people recognise', 'kind' => 'text', 'minutes' => 7,
                    'body' => "Write characters your audience meets every day: the conductor, the trader, the student. Give each one a want and a fear."],
            ]],
            ['title' => 'Writing and formatting', 'lessons' => [
                ['title' => 'Dialogue that sounds real', 'kind' => 'text', 'minutes' => 8,
                    'body' => "Read your lines aloud. Cut any line nobody would really say. Let characters interrupt each other. Use the language of your audience, including Yoruba, Pidgin or Hausa where it fits."],
                ['title' => 'Laying out a script', 'kind' => 'document', 'minutes' => 5,
                    'body' => "Character names in capitals, stage directions in brackets, a new line for every speech. Number the scenes. Put the cast list and the props list on the first page."],
            ]],
        ]);
        $course->assignments()->create(['title' => 'Write your one-sentence truth and first scene', 'brief' => 'Share the one sentence your script will carry, then the first scene.', 'accepts' => ['text', 'file'], 'sort' => 0]);
    }

    protected function acting(OrgUnit $district, ?User $lead): void
    {
        $course = $this->course([
            'title' => 'Acting Fundamentals Workshop',
            'kind' => 'live', 'org_unit_id' => $district->id, 'cover_path' => 'archive:godram-13.webp',
            'summary' => 'A practical evening workshop for every drama team in Agege District: movement, voice and stage presence.',
            'starts_on' => now()->addDays(12)->toDateString(), 'enrol_by' => now()->addDays(10)->toDateString(),
            'is_public' => false,
        ], [[$lead, 'District Coordinator']]);
        $this->outline($course, [
            ['title' => 'Movement, voice and presence', 'summary' => 'Bring comfortable clothes and water.',
                'live_at' => now()->addDays(12)->setTime(10, 0), 'live_platform' => 'meet', 'live_url' => 'https://meet.google.com/',
                'lessons' => [['title' => 'Before the workshop: warm-up routine', 'kind' => 'text', 'minutes' => 4,
                    'body' => "Practise this five-minute warm-up before you come: shoulder rolls, jaw stretches, humming up and down a scale, and three tongue-twisters.",
                    'key_points' => "Shoulders and neck\nJaw and lips\nHumming scale\nTongue-twisters"]]],
        ]);
    }

    protected function coordinatorsGuide(OrgUnit $national, ?User $trainer): Course
    {
        $course = $this->course([
            'title' => 'Using GODRAM CONNECT: a guide for coordinators',
            'kind' => 'recorded', 'org_unit_id' => $national->id, 'cover_path' => 'archive:godram-11.webp',
            'summary' => 'Short lessons on registering members, sending activity reports and keeping your Assembly informed.',
            'outcomes' => "Register members and confirm sign-ups\nSend an activity report with pictures\nPublish an announcement or an event for your area",
            'is_public' => false,
        ], [[$trainer, 'Training Administrator']]);
        $course->targets()->createMany([
            ['audience' => 'role', 'role_key' => 'assembly_coordinator'],
            ['audience' => 'role', 'role_key' => 'district_coordinator'],
            ['audience' => 'role', 'role_key' => 'regional_coordinator'],
        ]);
        $this->outline($course, [
            ['title' => 'Your members', 'lessons' => [
                ['title' => 'Registering a member', 'kind' => 'text', 'minutes' => 4,
                    'body' => "Open **Members**, then **Register a member**. Enter the phone number first: GODRAM CONNECT checks whether the person is already registered elsewhere. Every member gets a Member ID such as GDM-000123, which they can use to sign in.",
                    'key_points' => "Members, then Register a member\nPhone number first, to avoid duplicates\nEvery member gets a Member ID"],
                ['title' => 'Confirming sign-ups', 'kind' => 'text', 'minutes' => 3,
                    'body' => "When someone joins from the website and chooses your Assembly, they wait for you to confirm them. You will see them under **Sign-ups** on your dashboard."],
            ]],
            ['title' => 'Reporting', 'lessons' => [
                ['title' => 'Sending an activity report', 'kind' => 'text', 'minutes' => 6,
                    'body' => "Open **Reports**, then **New report**. Your draft saves itself as you type, even if the network drops. Add pictures, then **Send for review**. Your District Coordinator approves it; your Region and the National office can read it and comment.",
                    'key_points' => "Reports, then New report\nDrafts save themselves\nThe level above approves",
                    'prompts' => [['type' => 'choice', 'question' => 'Who approves an Assembly activity report?', 'options' => ['The Regional Coordinator', 'The District Coordinator', 'The National Coordinator'], 'answer' => 'The District Coordinator']]],
            ]],
        ]);

        return $course;
    }

    protected function draft(OrgUnit $national, ?User $trainer): void
    {
        $course = $this->course([
            'title' => 'Stage Management and Production Basics',
            'kind' => 'recorded', 'org_unit_id' => $national->id,
            'summary' => 'Props, costumes, cues and the running order: everything that happens so the actors can minister.',
            'status' => 'draft', 'published_at' => null,
        ], [[$trainer, 'Training Administrator']]);
        $this->outline($course, [['title' => 'The stage manager', 'lessons' => [
            ['title' => 'What a stage manager does', 'kind' => 'text', 'minutes' => 5, 'body' => 'Draft lesson being prepared by the Training Administrator.'],
        ]]]);
    }

    protected function learners(Course $course, OrgUnit $region, ?User $answerer): void
    {
        $lessons = $this->lessonIds[$course->id];
        $past = $course->sessions()->whereNotNull('live_at')->where('live_at', '<', now())->first();
        $assignment = $course->assignments()->orderBy('sort')->first();

        $members = Member::placedWithinPaths([$region->path])->whereHas('user')->where('status', 'active')->inRandomOrder()->limit(18)->get();
        $showcase = User::where('email', 'agege-central.member@demo.godram.test')->first()?->member;
        if ($showcase) {
            $members = $members->reject(fn ($m) => $m->id === $showcase->id)->prepend($showcase);
        }

        foreach ($members as $i => $member) {
            $enrolment = Enrolment::create(['course_id' => $course->id, 'member_id' => $member->id, 'status' => 'active', 'created_at' => now()->subDays(mt_rand(5, 14))]);
            $done = $i === 0 ? 3 : mt_rand(0, count($lessons));
            foreach (array_slice($lessons, 0, $done) as $lessonId) {
                LessonProgress::create(['enrolment_id' => $enrolment->id, 'lesson_id' => $lessonId, 'completed_at' => now()->subDays(mt_rand(0, 6))]);
            }
            $enrolment->forceFill([
                'last_lesson_id' => $lessons[min($done, count($lessons) - 1)],
                'status' => $done === count($lessons) ? 'completed' : 'active',
                'completed_at' => $done === count($lessons) ? now()->subDay() : null,
            ])->save();

            if ($past && ($i === 0 || mt_rand(0, 3) > 0)) {
                SessionAttendance::create(['course_session_id' => $past->id, 'member_id' => $member->id, 'status' => mt_rand(0, 2) ? 'attended' : 'joined', 'joined_at' => $past->live_at->copy()->addMinutes(mt_rand(0, 20))]);
            }
            if ($assignment && $done >= 4 && mt_rand(0, 1)) {
                AssignmentSubmission::create([
                    'assignment_id' => $assignment->id, 'member_id' => $member->id, 'submitted_at' => now()->subDays(mt_rand(0, 3)),
                    'body' => "MARKET DAY (sample)\n\n[A busy market. IYA BOSE arranges her tomatoes. A preacher's voice is heard in the distance.]\n\nIYA BOSE: Every Saturday the same noise. Tomorrow, tomorrow, I will listen tomorrow...",
                    'status' => 'submitted',
                ]);
            }
            if ($past && mt_rand(0, 3)) {
                foreach ($past->prompts()->where('type', 'poll')->get() as $poll) {
                    PromptResponse::create(['prompt_id' => $poll->id, 'user_id' => $member->user->id, 'response' => collect($poll->choices())->random()]);
                }
            }
            foreach ($course->prompts()->whereNotNull('lesson_id')->whereIn('lesson_id', array_slice($lessons, 0, $done))->where('type', 'poll')->get() as $poll) {
                PromptResponse::create(['prompt_id' => $poll->id, 'user_id' => $member->user->id, 'response' => collect($poll->choices())->random()]);
            }
        }

        if ($showcase?->user) {
            $course->questions()->create([
                'user_id' => $showcase->user->id, 'lesson_id' => $lessons[2],
                'body' => 'How do we play a drunkard convincingly without it becoming a comedy show?',
                'answer' => 'Play the need behind the drinking, not the staggering. One or two clear signs are enough; let the audience feel his emptiness so the turning point lands.',
                'answered_by' => $answerer?->id, 'answered_at' => now()->subDays(2), 'is_featured' => true,
            ]);
            $course->questions()->create(['user_id' => $showcase->user->id, 'lesson_id' => $lessons[1], 'body' => 'Can we use Yoruba and English in the same sketch?']);
        }
        if ($past && ($someone = $members->get(2)?->user)) {
            $course->questions()->create([
                'user_id' => $someone->id, 'course_session_id' => $past->id, 'body' => 'What if the crowd starts laughing at the wrong moment?',
                'answer' => 'Hold still and wait for the laugh to finish, then continue more slowly. Never break character to scold the audience.',
                'answered_by' => $answerer?->id, 'answered_at' => $past->live_at->copy()->addMinutes(50),
            ]);
        }
    }

    protected function coordinatorLearners(Course $course): void
    {
        $lessons = $this->lessonIds[$course->id];
        $users = User::whereIn('email', ['agege-central.assembly@demo.godram.test', 'agege.district@demo.godram.test'])->get();
        foreach ($users as $i => $user) {
            $enrolment = Enrolment::create(['course_id' => $course->id, 'member_id' => $user->member_id, 'status' => 'active']);
            foreach (array_slice($lessons, 0, $i ? 3 : 1) as $lessonId) {
                LessonProgress::create(['enrolment_id' => $enrolment->id, 'lesson_id' => $lessonId, 'completed_at' => now()->subDays(2)]);
            }
            $enrolment->forceFill(['last_lesson_id' => $lessons[$i ? 2 : 1]])->save();
        }
    }
}
