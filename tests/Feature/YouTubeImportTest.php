<?php

namespace Tests\Feature;

use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class YouTubeImportTest extends TestCase
{
    use BuildsMinistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
        Http::fake(['www.youtube.com/feeds/*' => Http::response(file_get_contents(base_path('tests/Fixtures/youtube-feed.xml')))]);
    }

    public function test_new_channel_uploads_are_added_once(): void
    {
        Video::create(['youtube_id' => 'BBBBBBBBBB2', 'title' => 'Renamed by the media team', 'category' => 'behind_the_scenes', 'status' => 'archived']);

        $this->artisan('godram:sync-youtube')->expectsOutputToContain('Added 1 new GODRAM TV video')->assertSuccessful();
        $this->artisan('godram:sync-youtube')->expectsOutputToContain('No new GODRAM TV videos')->assertSuccessful();

        $this->assertSame(2, Video::count());
        $imported = Video::firstWhere('youtube_id', 'AAAAAAAAAA1');
        $this->assertSame('published', $imported->status);
        $this->assertSame('2026-10-01', $imported->published_at->toDateString());
        $this->assertSame('archived', Video::firstWhere('youtube_id', 'BBBBBBBBBB2')->status, 'Decisions by the media team are kept.');
        $this->get(route('watch'))->assertSee('The Prodigal Returns');
    }

    public function test_watch_page_plays_the_channel_when_the_library_is_empty(): void
    {
        $this->get(route('watch'))->assertOk()->assertSee('videoseries?list=UURJnL2sj9MASfswKg9Sle1A', false);
    }
}
