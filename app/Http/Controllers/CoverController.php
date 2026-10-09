<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Production;
use App\Models\ProductionImage;
use App\Models\Story;
use App\Services\ShareCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Serves uploaded cover pictures and share cards, after checking who may see them. */
class CoverController extends Controller
{
    public function show(Request $request, string $type, int $id)
    {
        $user = $request->user();
        [$path, $visible] = match ($type) {
            'event' => (fn ($m) => [$m->cover_path, $m->isVisibleTo($user)])(Event::findOrFail($id)),
            'story' => (fn ($m) => [$m->cover_path, $m->isVisibleTo($user)])(Story::findOrFail($id)),
            'production' => (fn ($m) => [$m->cover_path, $m->isVisibleTo($user)])(Production::findOrFail($id)),
            'production-image' => (fn ($m) => [$m->path, $m->production->isVisibleTo($user)])(ProductionImage::with('production')->findOrFail($id)),
            default => abort(404),
        };
        abort_unless($visible && $path && ! str_starts_with($path, 'archive:') && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => 'image/webp', 'Cache-Control' => 'public, max-age=604800']);
    }

    /** The picture shown when a link is shared on WhatsApp, Facebook or X. Public items only. */
    public function share(ShareCard $cards, string $type, string $slug)
    {
        $item = match ($type) {
            'event' => Event::where('slug', $slug)->where('visibility', 'public')->whereIn('status', ['published', 'cancelled'])->firstOrFail(),
            'story' => Story::published()->where('slug', $slug)->firstOrFail(),
            'showcase' => Production::published()->where('slug', $slug)->firstOrFail(),
            'video' => \App\Models\Video::published()->where('slug', $slug)->firstOrFail(),
            'course' => \App\Models\Course::published()->where('is_public', true)->where('slug', $slug)->firstOrFail(),
            'announcement' => \App\Models\Announcement::live()->public()->whereKey((int) $slug)->firstOrFail(),
            default => abort(404),
        };

        [$eyebrow, $subtitle, $cover] = match ($type) {
            'event' => [$item->typeLabel().' · '.$item->starts_at->format('D j M Y'), $item->is_online ? 'Online' : $item->location, $item->coverFile()],
            'story' => ['GODRAM Stories · '.$item->typeLabel(), $item->standfirst, $item->coverFile()],
            'showcase' => ['Creative Showcase · '.$item->kindLabel(), $item->summary, $item->coverFile()],
            'video' => ['Watch on GODRAM TV · '.$item->categoryLabel(), null, null],
            'course' => ['GODRAM Virtual Academy · '.$item->kindLabel(), $item->enrol_by ? 'Enrol by '.$item->enrol_by->format('j F Y') : $item->orgUnit?->fullName(), $item->coverFile()],
            'announcement' => ['GODRAM announcement · '.$item->published_at->format('j M Y'), \Illuminate\Support\Str::limit($item->body, 90), $item->image_path ? Storage::disk('local')->path($item->image_path) : null],
        };

        $file = $cards->render($type.':'.$item->id.':'.$item->updated_at?->timestamp, $eyebrow, $item->title, $subtitle, $cover);

        return response()->file($file, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }
}
