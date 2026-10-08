<?php

namespace App\Http\Controllers;

use App\Models\ActivityReport;
use App\Models\ReportMedia;
use App\Services\Access;
use App\Services\ImageStore;
use App\Services\ReportWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportMediaController extends Controller
{
    public function __construct(protected Access $access, protected ReportWorkflow $workflow) {}

    public function store(Request $request, ActivityReport $report, ImageStore $store)
    {
        abort_unless($this->workflow->canEdit($request->user(), $report), 403);
        $limits = config('godram.uploads');
        $request->validate([
            'files' => ['required', 'array', 'max:'.$limits['max_files_per_report']],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:'.$limits['document_max_kb']],
        ], ['files.*.mimes' => 'Photos (JPG, PNG, WebP) and documents (PDF, Word) only.']);

        if ($report->media()->count() + count($request->file('files')) > $limits['max_files_per_report']) {
            return back()->withErrors(['files' => 'A report can hold up to '.$limits['max_files_per_report'].' files.']);
        }

        foreach ($request->file('files') as $file) {
            $isImage = str_starts_with((string) $file->getMimeType(), 'image/');
            if ($isImage && $file->getSize() > $limits['image_max_kb'] * 1024) {
                return back()->withErrors(['files' => 'Each photo must be smaller than '.($limits['image_max_kb'] / 1024).' MB.']);
            }
            try {
                $path = $isImage
                    ? $store->storeImage($file, 'reports/'.$report->id)
                    : $store->storeDocument($file, 'reports/'.$report->id);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['files' => $e->getMessage()]);
            }
            $report->media()->create([
                'kind' => $isImage ? 'image' : 'document',
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mime' => $isImage ? 'image/webp' : $file->getMimeType(),
                'size' => Storage::disk('local')->size($path),
            ]);
        }

        return back()->with('status', 'Files added.');
    }

    /** Files are private: published highlights are public, everything else needs report access. */
    public function show(Request $request, ReportMedia $media)
    {
        $report = $media->report;
        $public = $report->status === 'published' && $media->isImage();
        $user = $request->user();
        abort_unless($public || ($user && ($this->access->can($user, 'reports.view', $report->orgUnit) || $report->created_by === $user->id)), 403);
        abort_unless(Storage::disk('local')->exists($media->path), 404);

        $headers = ['Cache-Control' => $public ? 'public, max-age=604800' : 'private, max-age=3600'];

        return $media->isImage()
            ? response()->file(Storage::disk('local')->path($media->path), $headers + ['Content-Type' => 'image/webp'])
            : Storage::disk('local')->download($media->path, $media->original_name, $headers);
    }

    public function destroy(Request $request, ReportMedia $media)
    {
        abort_unless($this->workflow->canEdit($request->user(), $media->report), 403);
        Storage::disk('local')->delete($media->path);
        $media->delete();

        return back()->with('status', 'File removed.');
    }
}
