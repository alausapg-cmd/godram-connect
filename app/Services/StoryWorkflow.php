<?php

namespace App\Services;

use App\Models\Story;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Draft → Submitted → Under review → Approved → Published, with Returned and Archived. */
class StoryWorkflow
{
    public function __construct(protected Access $access, protected AuditLogger $audit) {}

    public function canEdit(?User $user, Story $story): bool
    {
        return $user && $story->author_id === $user->id && $story->isEditable();
    }

    public function canReview(?User $user, Story $story): bool
    {
        return $user && $story->author_id !== $user->id && $this->access->can($user, 'stories.review');
    }

    public function submit(Story $story, User $by): void
    {
        $missing = collect(['title' => 'a title', 'type' => 'the kind of story', 'body' => 'the story itself'])
            ->filter(fn ($label, $field) => blank($story->{$field}));
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['story' => 'Please add '.$missing->values()->join(', ', ' and ').' before sending.']);
        }
        $story->forceFill(['status' => 'submitted', 'submitted_at' => now(), 'reject_reason' => null])->save();
        $this->audit->log('story.submitted', $story, 'Story sent for review: '.$story->title, [], null, $by->id);
    }

    public function decide(Story $story, User $by, string $decision, ?string $note = null): void
    {
        $allowed = [
            'start' => ['submitted'],
            'approve' => ['submitted', 'under_review'],
            'publish' => ['submitted', 'under_review', 'approved'],
            'reject' => ['submitted', 'under_review', 'approved'],
            'archive' => ['published', 'approved'],
            'feature' => ['published'],
        ];
        abort_unless(in_array($story->status, $allowed[$decision] ?? []), 422, 'This story cannot be moved that way from where it is now.');

        $changes = match ($decision) {
            'start' => ['status' => 'under_review', 'reviewed_by' => $by->id],
            'approve' => ['status' => 'approved', 'reviewed_by' => $by->id],
            'publish' => ['status' => 'published', 'reviewed_by' => $by->id, 'published_at' => $story->published_at ?? now()],
            'reject' => ['status' => 'rejected', 'reviewed_by' => $by->id, 'reject_reason' => $note],
            'archive' => ['status' => 'archived', 'is_featured' => false],
            'feature' => ['is_featured' => ! $story->is_featured],
        };
        if ($decision === 'feature' && $changes['is_featured']) {
            Story::where('is_featured', true)->update(['is_featured' => false]);
        }
        $story->forceFill($changes)->save();

        if ($decision !== 'start') {
            $this->audit->log('story.'.$decision, $story, ucfirst($decision).': '.$story->title, array_filter(['note' => $note]), null, $by->id);
        }
    }
}
