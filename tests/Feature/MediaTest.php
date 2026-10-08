<?php

namespace Tests\Feature;

use App\Models\Production;
use App\Models\Video;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use BuildsMinistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
    }

    public function test_youtube_links_are_understood(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ?si=abc',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube.com/live/dQw4w9WgXcQ?feature=share',
            'https://m.youtube.com/watch?feature=share&v=dQw4w9WgXcQ',
            'dQw4w9WgXcQ',
        ] as $link) {
            $this->assertSame('dQw4w9WgXcQ', Video::youtubeIdFrom($link), $link);
        }
        $this->assertNull(Video::youtubeIdFrom('https://www.facebook.com/watch?v=123'));
        $this->assertNull(Video::youtubeIdFrom('https://www.youtube.com/@GODRAMTV'));
    }

    public function test_coordinator_suggests_a_video_and_the_national_team_publishes_it(): void
    {
        $this->actingAs($this->users['agege'])->post(route('videos.store'), [
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'title' => 'Agege Easter play',
            'category' => 'drama_performances',
        ])->assertRedirect(route('videos.manage'));

        $video = Video::firstOrFail();
        $this->assertSame('submitted', $video->status);
        $this->get(route('watch.show', $video))->assertOk();
        auth()->logout();
        $this->get(route('watch'))->assertDontSee('Agege Easter play');
        $this->get(route('watch.show', $video))->assertNotFound();

        $this->actingAs($this->users['agege'])->post(route('videos.decide', $video), ['decision' => 'publish'])->assertForbidden();
        $this->actingAs($this->users['national'])->post(route('videos.decide', $video), ['decision' => 'publish'])->assertRedirect();
        $this->assertSame('published', $video->fresh()->status);

        auth()->logout();
        $this->get(route('watch'))->assertSee('Agege Easter play');

        $this->actingAs($this->users['mushin'])->post(route('videos.store'), [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Again', 'category' => 'films',
        ])->assertSessionHasErrors('youtube_url');
    }

    public function test_members_cannot_add_videos(): void
    {
        $member = $this->person($this->agege, 'Ngozi', 'Member', password: 'secret-pass-1')->user;
        $this->actingAs($member)->post(route('videos.store'), [
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Mine', 'category' => 'films',
        ])->assertForbidden();
    }

    public function test_draft_productions_and_their_share_cards_stay_private(): void
    {
        $draft = Production::create(['title' => 'Unfinished play', 'kind' => 'stage_play', 'status' => 'draft']);
        $live = Production::create(['title' => 'Valley of Baca', 'kind' => 'film', 'status' => 'published', 'published_at' => now(), 'summary' => 'A GODRAM film.']);

        $this->get(route('showcase'))->assertSee('Valley of Baca')->assertDontSee('Unfinished play');
        $this->get(route('showcase.show', $draft))->assertNotFound();
        $this->get(route('share.card', ['showcase', $draft->slug]))->assertNotFound();

        $card = $this->get(route('share.card', ['showcase', $live->slug]))->assertOk();
        $this->assertSame('image/png', $card->headers->get('Content-Type'));
        $this->get(route('covers.show', ['production', $live->id]))->assertNotFound('No uploaded cover, nothing to serve.');
    }
}
