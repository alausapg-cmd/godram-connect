<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/** Looks up a video's title through YouTube's public oEmbed endpoint. Fails quietly. */
class YouTubeMeta
{
    /** @return array{title?: string, author?: string} */
    public function lookup(string $youtubeId): array
    {
        try {
            $response = Http::timeout(4)->get('https://www.youtube.com/oembed', [
                'url' => 'https://www.youtube.com/watch?v='.$youtubeId,
                'format' => 'json',
            ]);
            if ($response->ok()) {
                return array_filter([
                    'title' => $response->json('title'),
                    'author' => $response->json('author_name'),
                ]);
            }
        } catch (\Throwable) {
            // Offline or blocked: the person types the title instead.
        }

        return [];
    }
}
