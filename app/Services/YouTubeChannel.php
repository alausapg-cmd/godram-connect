<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reads the GODRAM TV channel's public feed (the latest 15 uploads; no API key
 * needed) and adds new videos to the Watch centre. Videos already there, even
 * if archived or recategorised by the media team, are left alone.
 */
class YouTubeChannel
{
    public function __construct(protected AuditLogger $audit) {}

    public static function uploadsPlaylistId(?string $channelId = null): ?string
    {
        $channelId ??= config('godram.youtube.channel_id');

        return $channelId && str_starts_with($channelId, 'UC') ? 'UU'.substr($channelId, 2) : null;
    }

    /** @return array<int, array{id: string, title: string, description: string, published: Carbon}> */
    public function latest(?string $channelId = null): array
    {
        $channelId ??= config('godram.youtube.channel_id');
        if (! $channelId) {
            return [];
        }
        $response = Http::timeout(15)->get('https://www.youtube.com/feeds/videos.xml', ['channel_id' => $channelId]);
        if (! $response->ok()) {
            return [];
        }

        return $this->parse($response->body());
    }

    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, options: LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if (! $feed) {
            return [];
        }

        $items = [];
        foreach ($feed->entry as $entry) {
            $yt = $entry->children('http://www.youtube.com/xml/schemas/2015');
            $media = $entry->children('http://search.yahoo.com/mrss/');
            $id = (string) $yt->videoId;
            if (! preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
                continue;
            }
            $items[] = [
                'id' => $id,
                'title' => Str::limit(trim((string) $entry->title), 150, ''),
                'description' => trim((string) ($media->group->description ?? '')),
                'published' => Carbon::parse((string) $entry->published),
            ];
        }

        return $items;
    }

    /** Adds uploads not yet in the Watch centre. Returns how many were added. */
    public function import(?array $items = null): int
    {
        $items ??= $this->latest();
        $known = Video::whereIn('youtube_id', array_column($items, 'id'))->pluck('youtube_id')->all();
        $added = 0;
        foreach ($items as $item) {
            if (in_array($item['id'], $known, true) || $item['title'] === '') {
                continue;
            }
            $video = Video::create([
                'youtube_id' => $item['id'],
                'title' => $item['title'],
                'description' => Str::limit($item['description'], 3000, ''),
                'category' => config('godram.youtube.import_category', 'drama_performances'),
                'status' => 'published',
                'published_at' => $item['published'],
            ]);
            $this->audit->log('video.imported', $video, 'Imported from GODRAM TV: '.$video->title);
            $added++;
        }

        return $added;
    }
}
