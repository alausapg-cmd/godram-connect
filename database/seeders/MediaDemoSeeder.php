<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Production;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Story;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Sample events, showcase items and stories for previews (all flagged is_demo).
 * Showcase items use real GODRAM archive pictures and only facts shown on them;
 * people in the stories are sample members.
 *
 * Videos are added only from config/demo_videos.php, which lists real GODRAM TV
 * links, so the preview never shows a broken player.
 */
class MediaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $national = OrgUnit::root();
        $content = User::where('email', 'content@demo.godram.test')->first();

        $productions = $this->productions($national, $content);
        $this->videos($content, $productions);
        $this->events($national, $productions);
        $this->stories($content, $productions);
    }

    protected function productions(?OrgUnit $national, ?User $by): array
    {
        $items = [
            'majemu' => ['Victory Drama Crusade: Majemu (Covenant)', 'major_event', 1994, 'godram-10', true,
                'Three days of drama ministry at Bolorunpelu, Egbe, from 22 to 24 July 1994.',
                "The Victory Drama Crusade brought \"Majemu (Covenant)\" to Bolorunpelu, Egbe, over three days in July 1994.\n\nThe poster from the crusade survives in the GODRAM archive, one of the earliest records of the ministry taking the Gospel to the community through drama."],
            'graduation' => ['The pioneering graduation', 'achievement', 1995, 'godram-25', false,
                'The first 41 drama ministers graduate from the GODRAM Institute of Christian Drama.',
                "In 1995 the GODRAM Institute of Christian Drama held its first graduation, at Ayantuga Street, Mushin. Forty-one drama ministers completed their training.\n\nThe Institute's work continues in the GODRAM Virtual Academy."],
            'gicd' => ['GICD intensive training programme', 'major_event', 1999, 'godram-31', false,
                'Ordinary and Advanced Certificates in Christian Drama, Odo Eran, Matori.',
                "In 1999 the GODRAM Institute of Christian Drama ran a week of intensive training at Odo Eran, Matori, leading to the Ordinary Certificate and the Advanced Certificate in Christian Drama.\n\nThe courses ranged from Christian drama and ministry and stage techniques to film production, directing and scriptwriting."],
            'gonacic' => ['GONACIC 2009', 'major_event', 2009, 'godram-21', false,
                'The National Cinematography Induction Course.',
                'The GODRAM National Cinematography Induction Course (GONACIC) trained drama ministers in film-making in 2009.'],
            'films' => ['GODRAM Films', 'film', null, 'godram-22', false,
                'Films from GODRAM, including "Valley of Baca" and "No Second Chance".',
                "GODRAM's films include \"Your Choice\" (Ohun Too Yan), \"A Little While\", \"God of Vengeance\", \"No Second Chance\", \"Born to Reign\", \"Valley of Baca\" (Afonifoji Omije), \"Terminated\", \"Die Alone\", \"On the Rock\", \"Shadow of the Almighty\", \"Attention\", \"The Chronicle\" and \"L'Oracle\"."],
            'handwriting' => ['The Handwriting', 'poster', null, 'godram-1', false, 'Film poster from the GODRAM archive.', null],
            'season' => ['There is a Season', 'poster', null, 'godram-29', false, 'A GACASA poster.', null],
            'majemu-aye' => ['Majemu Aye (Worldly Covenant)', 'poster', null, 'godram-20', false, 'Production brochure from the GODRAM archive.', null],
            'change' => ['Change', 'poster', null, 'godram-34', false, 'Production artwork.', null],
            'retreat' => ['Ministers Retreat 2006', 'major_event', 2006, 'godram-8', false, 'A retreat for GODRAM ministers.', null],
        ];

        $made = [];
        foreach ($items as $key => [$title, $kind, $year, $file, $featured, $summary, $body]) {
            $made[$key] = Production::create([
                'title' => $title, 'kind' => $kind, 'year' => $year, 'org_unit_id' => $national?->id,
                'summary' => $summary, 'body' => $body, 'cover_path' => 'archive:'.$file.'.webp',
                'is_featured' => $featured, 'status' => 'published', 'created_by' => $by?->id,
                'published_at' => now()->subDays(30 - count($made)), 'is_demo' => true,
            ]);
        }
        foreach (['godram-11', 'godram-12', 'godram-2', 'godram-13'] as $i => $file) {
            $made['graduation']->images()->create(['path' => 'archive:'.$file.'.webp', 'sort' => $i, 'caption' => collect(config('archive'))->firstWhere('file', $file.'.webp')['caption'] ?? null]);
        }

        return $made;
    }

    protected function videos(?User $by, array $productions): void
    {
        foreach (config('demo_videos', []) as $i => $v) {
            $id = Video::youtubeIdFrom($v['url'] ?? '');
            if (! $id || Video::where('youtube_id', $id)->exists()) {
                continue;
            }
            Video::create([
                'youtube_id' => $id, 'title' => $v['title'], 'category' => $v['category'] ?? 'drama_performances',
                'description' => $v['description'] ?? null, 'is_featured' => $i === 0, 'status' => 'published',
                'production_id' => isset($v['production']) ? ($productions[$v['production']]->id ?? null) : null,
                'submitted_by' => $by?->id, 'reviewed_by' => $by?->id, 'published_at' => now()->subDays($i), 'is_demo' => true,
            ]);
        }
    }

    protected function events(?OrgUnit $national, array $productions): void
    {
        $lagos = OrgUnit::ofType('district')->where('name', 'Lagos')->first();
        $agege = OrgUnit::ofType('district')->where('name', 'Agege')->first();
        $region1 = OrgUnit::ofType('region')->where('name', 'Region 1')->first();
        $ayantuga = OrgUnit::ofType('assembly')->where('name', 'like', 'Ayantuga%')->first();
        $mokola = OrgUnit::ofType('assembly')->where('name', 'Mokola')->first();
        $creator = fn (string $role, ?OrgUnit $unit) => $unit
            ? RoleAssignment::where('org_unit_id', $unit->id)->where('role_id', Role::where('key', $role)->value('id'))->first()?->member?->user?->id
            : null;

        $events = [
            ['GODRAM TV Sunday Night Live', 'national_programme', $national, now()->subMinutes(30), now()->addMinutes(90), null, true, 'https://www.youtube.com/@GODRAMTV/live', 'youtube', 'public', false, null,
                "Drama, worship and stories from across the GODRAM family, live on GODRAM TV.\n\nJoin from anywhere and share the link with someone who needs the message tonight."],
            ['GACASA 2026: Annual Conference of All Saint Artistes', 'convention', $national, now()->addWeeks(9)->setTime(9, 0), now()->addWeeks(9)->addDays(2)->setTime(17, 0), 'Ibadan', false, 'https://www.youtube.com/@GODRAMTV/live', 'youtube', 'public', true, 800,
                "The GODRAM Annual Conference of All Saint Artistes brings drama ministers from every Region together for three days of ministry, training, performances and fellowship.\n\nEvery Assembly should send at least two delegates. Register so the organisers can plan accommodation and feeding."],
            ['Christmas stage play: No Second Chance', 'performance', $region1, now()->addWeeks(11)->setTime(16, 0), now()->addWeeks(11)->setTime(19, 0), 'Ayantuga Assembly, Mushin', false, 'https://www.youtube.com/@GODRAMTV/live', 'youtube', 'public', false, null,
                "Region 1 presents \"No Second Chance\", a stage play on the urgency of the Gospel. Bring a friend who has never been to church."],
            ['Acting fundamentals workshop', 'workshop', $agege, now()->addDays(12)->setTime(10, 0), now()->addDays(12)->setTime(15, 0), 'Agege Central Assembly', false, null, null, 'members', true, 40,
                "A one-day workshop on voice, movement, character and stage presence, for new and returning drama ministers in Agege District.\n\nWear comfortable clothes and bring a notebook."],
            ['Lagos District combined Christmas rehearsal', 'district_programme', $lagos, now()->addDays(5)->setTime(10, 0), now()->addDays(5)->setTime(14, 0), 'District headquarters', false, null, null, 'members', false, null,
                'All Assemblies in Lagos District rehearse together for the Christmas production. Assembly Coordinators should bring their cast lists.'],
            ['Market square outreach', 'outreach', $ayantuga, now()->addDays(19)->setTime(11, 0), now()->addDays(19)->setTime(14, 0), 'Mushin market square', false, null, null, 'public', false, null,
                'A short open-air drama ministration followed by prayer and counselling. Volunteers for counselling should arrive by 10am.'],
            ['Online scriptwriting clinic', 'training', $national, now()->addDays(26)->setTime(19, 0), now()->addDays(26)->setTime(20, 30), null, true, 'https://meet.google.com/', 'meet', 'members', true, 100,
                'Bring a scene you are working on. Facilitators will read selected scripts and give feedback live.'],
            ['Easter drama night', 'performance', $mokola, now()->subDays(40)->setTime(18, 0), now()->subDays(40)->setTime(21, 0), 'Mokola Assembly', false, 'https://www.youtube.com/@GODRAMTV/live', 'youtube', 'public', false, null,
                'An evening of Easter drama and worship from Mokola Assembly.'],
        ];

        foreach ($events as [$title, $type, $unit, $start, $end, $location, $online, $stream, $platform, $visibility, $registration, $capacity, $description]) {
            $event = Event::create([
                'title' => $title, 'type' => $type, 'org_unit_id' => $unit?->id, 'description' => $description,
                'starts_at' => $start, 'ends_at' => $end, 'location' => $location, 'is_online' => $online,
                'stream_url' => $stream, 'stream_platform' => $platform, 'visibility' => $visibility,
                'registration_open' => $registration, 'capacity' => $capacity, 'status' => 'published', 'published_at' => now()->subDays(3),
                'created_by' => $creator(match ($unit?->type) { 'district' => 'district_coordinator', 'region' => 'regional_coordinator', 'assembly' => 'assembly_coordinator', default => 'national_coordinator' }, $unit),
                'production_id' => str_contains($title, 'No Second Chance') ? $productions['films']->id : null,
                'is_demo' => true,
            ]);

            $people = Member::approved()->inRandomOrder()->limit(mt_rand(2, 4))->get();
            foreach ($people as $i => $m) {
                $event->people()->create(['member_id' => $m->id, 'name' => $m->full_name, 'role' => $i === 0 ? ($type === 'workshop' || $type === 'training' ? 'facilitator' : 'host') : 'performer', 'sort' => $i]);
            }
            if ($registration) {
                foreach (Member::approved()->inRandomOrder()->limit(mt_rand(8, 25))->get() as $m) {
                    $event->registrations()->create(['member_id' => $m->id, 'name' => $m->full_name, 'phone' => $m->phone, 'email' => $m->email]);
                }
            }
        }
    }

    protected function stories(?User $reviewer, array $productions): void
    {
        $author = fn (string $email) => User::where('email', $email)->first();
        $stories = [
            ['How GODRAM began', 'milestone', 'national@demo.godram.test', 'published', true, 'godram-11',
                'In 1991 a handful of drama groups in Lagos began training together. Thirty-five years later, they are a national ministry.',
                "In 1991, with the permission of the Lagos District Overseer, Pastor S. A. Abiodun, Paul Adaramola began training drama groups in Lagos. Out of that work came the Gospel Drama Ministry: GODRAM.\n\nPaul Adaramola led the ministry, with Paul Alausa as its Secretary. The films that followed, beginning with \"Your Choice\" and \"Ohun Too Yan\", carried the message far beyond the church walls.\n\nIn 1996 GOFAMINT recognised GODRAM as a national department, the GOFAMINT Drama and Film Ministry. By 1999 its productions were being staged at the National Theatre, Iganmu, and at cultural centres in Ibadan, Benin and Port Harcourt.\n\nThe stage has changed. The story and the mission have not.",
                'The Stage. The Story. The Mission.', 'GODRAM', null],
            ['Forty-one drama ministers', 'milestone', 'content@demo.godram.test', 'published', false, 'godram-25',
                'In 1995 the GODRAM Institute of Christian Drama sent out its first graduates.',
                "The programme for the pioneering graduation of the GODRAM Institute of Christian Drama still survives: Ayantuga Street, Mushin, 1995.\n\nForty-one drama ministers completed the course that year. They were trained not only to act, but to minister: to carry the Gospel through the stage and the screen.\n\nToday that work continues in the GODRAM Virtual Academy, where a new generation of drama ministers will learn, practise and be certified.",
                null, null, 'graduation'],
            ['I came for the drama and stayed for Christ', 'testimony', 'agege-central.member@demo.godram.test', 'published', false, 'godram-12',
                'A sample testimony from a market outreach in Agege.',
                "I was selling at the market when the drama team set up. I only stopped because of the noise.\n\nThe play was about a man who kept postponing his decision for Christ. I saw myself in him. When the drama ended and the minister asked who wanted to give their life to Jesus, I raised my hand before I could think about it.\n\nThe Assembly followed me up that week. Two years later I joined the drama team myself. Now I am the one setting up in the market.",
                'I saw myself in him.', 'A member of Agege Central Assembly', null],
            ['From props to directing', 'creative_journey', 'akobo.member@demo.godram.test', 'published', false, 'godram-13',
                'A sample story of a member who started behind the scenes.',
                "My first job in GODRAM was carrying chairs and looking after props. Nobody notices props until something is missing.\n\nOver the years I watched the directors closely. I learned how they placed actors, how they used silence, how they built a scene towards the moment of decision.\n\nLast year my Assembly asked me to direct our Easter drama. I was afraid, but I remembered every rehearsal I had watched from the side of the stage.",
                null, null, null],
            ['Behind the scenes of a market outreach', 'behind_the_scenes', 'agege-central.assembly@demo.godram.test', 'published', false, 'godram-2',
                'A sample account of what it takes to stage drama in the open air.',
                "Open-air ministry is not the same as the church stage. There is no lighting, no curtain and plenty of competition for attention.\n\nWe rehearse with the noise of a generator running, so actors learn to project. Every scene is short. Every costume can be changed in seconds behind a parked bus.\n\nAnd we always plan the counselling before we plan the drama, because the drama is only the beginning.",
                null, null, null],
            ['The night our Assembly performed at the District convention', 'member', 'moniya.member@demo.godram.test', 'submitted', false, null,
                'A sample story waiting for review.',
                "We had rehearsed for six weeks. On the night, the power went off halfway through the second scene…",
                null, null, null],
        ];

        foreach ($stories as $i => [$title, $type, $email, $status, $featured, $cover, $standfirst, $body, $quote, $quoteBy, $production]) {
            $user = $author($email);
            Story::create([
                'title' => $title, 'type' => $type, 'standfirst' => $standfirst, 'body' => $body,
                'quote' => $quote, 'quote_by' => $quoteBy, 'cover_path' => $cover ? 'archive:'.$cover.'.webp' : null,
                'author_id' => $user?->id, 'org_unit_id' => $user?->member?->assembly()?->id,
                'production_id' => $production ? $productions[$production]->id : null,
                'status' => $status, 'is_featured' => $featured, 'reviewed_by' => $status === 'published' ? $reviewer?->id : null,
                'submitted_at' => now()->subDays(20 - $i), 'published_at' => $status === 'published' ? now()->subDays(14 - $i * 2) : null,
                'is_demo' => true,
            ]);
        }
    }
}
