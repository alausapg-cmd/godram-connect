<?php

namespace App\Http\Controllers;

use App\Models\OrgUnit;
use App\Models\Production;
use App\Models\ProductionImage;
use App\Models\Spotlight;
use App\Services\Access;
use App\Services\AuditLogger;
use App\Services\ImageStore;
use App\Support\SitePicture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** The Creative Showcase: productions, films, posters, awards and major events. */
class ShowcaseController extends Controller
{
    public function __construct(protected Access $access, protected AuditLogger $audit) {}

    public function index(Request $request)
    {
        $kind = $request->input('kind');
        $kind = isset(Production::KINDS[$kind]) ? $kind : null;
        $all = Production::published()->with('orgUnit')
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->orderByDesc('is_featured')->orderByDesc('year')->latest('published_at')->get();

        $lead = $kind ? null : (Spotlight::currentSubject('creative_spotlight') ?? $all->firstWhere('is_featured', true) ?? $all->first());

        return view('showcase.index', [
            'kind' => $kind,
            'lead' => $lead,
            'items' => $lead ? $all->reject(fn ($p) => $p->id === $lead->id)->values() : $all,
            'kinds' => Production::published()->selectRaw('kind, count(*) as n')->groupBy('kind')->pluck('n', 'kind'),
            // The throwback strip leaves out pictures already shown elsewhere: covers, production photos and page headers.
            'archive' => SitePicture::unusedArchive(),
            'canManage' => $this->access->can($request->user(), 'media.manage'),
        ]);
    }

    public function show(Request $request, Production $production)
    {
        abort_unless($production->isVisibleTo($request->user()), 404);
        $production->load(['images', 'videos', 'stories', 'events' => fn ($q) => $q->visibleTo($request->user())->orderByDesc('starts_at'), 'orgUnit']);

        return view('showcase.show', [
            'production' => $production,
            'more' => Production::published()->whereKeyNot($production->id)->inRandomOrder()->limit(3)->get(),
            'canManage' => $this->access->can($request->user(), 'media.manage'),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($this->access->can($request->user(), 'media.manage'), 403);

        return view('showcase.form', ['production' => new Production(['kind' => 'stage_play', 'year' => now()->year]), 'units' => $this->units()]);
    }

    public function store(Request $request, ImageStore $images)
    {
        abort_unless($this->access->can($request->user(), 'media.manage'), 403);
        $production = Production::create($this->validated($request) + ['created_by' => $request->user()->id]);
        $this->saveFiles($request, $production, $images);
        $this->audit->log('production.created', $production, 'Showcase item added: '.$production->title);

        return redirect()->route('showcase.show', $production)->with('status', $production->status === 'published' ? 'Added to the Creative Showcase.' : 'Saved as a draft.');
    }

    public function edit(Request $request, Production $production)
    {
        abort_unless($this->access->can($request->user(), 'media.manage'), 403);

        return view('showcase.form', ['production' => $production->load('images'), 'units' => $this->units()]);
    }

    public function update(Request $request, Production $production, ImageStore $images)
    {
        abort_unless($this->access->can($request->user(), 'media.manage'), 403);
        $production->update($this->validated($request, $production));
        $this->saveFiles($request, $production, $images);
        foreach ($request->input('remove_images', []) as $id) {
            $image = $production->images()->find($id);
            if ($image) {
                if (! SitePicture::is($image->path)) {
                    Storage::disk('local')->delete($image->path);
                }
                $image->delete();
            }
        }
        $this->audit->log('production.updated', $production, 'Showcase item updated: '.$production->title);

        return redirect()->route('showcase.show', $production)->with('status', 'Saved.');
    }

    public function spotlight(Request $request, Production $production)
    {
        abort_unless($this->access->can($request->user(), 'media.manage') && $production->status === 'published', 403);
        Spotlight::create([
            'kind' => 'creative_spotlight', 'subject_type' => 'production', 'subject_id' => $production->id,
            'note' => $request->input('note'), 'starts_on' => today(), 'ends_on' => today()->addDays(13), 'created_by' => $request->user()->id,
        ]);
        $this->audit->log('production.spotlight', $production, 'Creative Spotlight: '.$production->title);

        return back()->with('status', 'This is the Creative Spotlight for the next two weeks.');
    }

    protected function units()
    {
        return OrgUnit::where('type', '!=', OrgUnit::ASSEMBLY)->orderBy('depth')->orderBy('name')->get();
    }

    protected function validated(Request $request, ?Production $production = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(array_keys(Production::KINDS))],
            'year' => ['nullable', 'integer', 'min:1960', 'max:'.(now()->year + 1)],
            'org_unit_id' => ['nullable', 'exists:org_units,id'],
            'summary' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:10000'],
            'credits' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_featured' => ['nullable', 'boolean'],
            'cover' => ['nullable', 'image', 'max:'.config('godram.uploads.image_max_kb')],
            'gallery' => ['array', 'max:12'],
            'gallery.*' => ['image', 'max:'.config('godram.uploads.image_max_kb')],
        ]);

        return collect($data)->except(['cover', 'gallery'])->merge([
            'is_featured' => $request->boolean('is_featured'),
            'published_at' => $data['status'] === 'published' ? ($production?->published_at ?? now()) : $production?->published_at,
        ])->all();
    }

    protected function saveFiles(Request $request, Production $production, ImageStore $images): void
    {
        if ($request->hasFile('cover')) {
            $production->forceFill(['cover_path' => $images->storeImage($request->file('cover'), 'showcase', 1800)])->save();
        }
        $sort = (int) $production->images()->max('sort');
        foreach ($request->file('gallery', []) as $file) {
            ProductionImage::create(['production_id' => $production->id, 'path' => $images->storeImage($file, 'showcase', 1800), 'sort' => ++$sort]);
        }
    }
}
