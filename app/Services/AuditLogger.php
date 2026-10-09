<?php

namespace App\Services;

use App\Jobs\RouteAuditEntry;
use App\Models\AuditLog;
use App\Models\OrgUnit;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public function log(string $action, ?Model $subject = null, ?string $summary = null, array $data = [], ?OrgUnit $unit = null, ?int $userId = null): AuditLog
    {
        $entry = DB::transaction(function () use ($action, $subject, $summary, $data, $unit, $userId) {
            $previous = AuditLog::query()->orderByDesc('id')->lockForUpdate()->first();

            $entry = new AuditLog([
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'org_unit_id' => $unit?->id,
                'summary' => $summary,
                'data' => $data ?: null,
                'ip_address' => request()?->ip(),
                'user_agent' => substr((string) request()?->userAgent(), 0, 250) ?: null,
                'prev_hash' => $previous?->hash,
            ]);
            $entry->created_at = now()->startOfSecond();
            $entry->hash = $this->hashFor($entry);
            $entry->save();

            return $entry;
        });

        if (Notify::routes($action)) {
            $this->notifyAfterCommit($entry->id);
        }

        return $entry;
    }

    /** Hands the entry to the notifier once the change is saved. A notification problem never undoes the change itself. */
    protected function notifyAfterCommit(int $id): void
    {
        DB::afterCommit(function () use ($id) {
            try {
                app(Dispatcher::class)->dispatch((new RouteAuditEntry($id))->onConnection(config('godram.notify.queue')));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function hashFor(AuditLog $entry): string
    {
        $payload = json_encode([
            $entry->user_id,
            $entry->action,
            $entry->subject_type,
            $entry->subject_id,
            $entry->org_unit_id,
            $entry->summary,
            $entry->data,
            $entry->ip_address,
            $entry->created_at?->format('Y-m-d H:i:s'),
            $entry->prev_hash,
        ]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    /**
     * Walks the chain and returns the id of the first broken entry, or null
     * when every entry matches its stored hash and links to the one before.
     */
    public function firstBrokenEntry(): ?int
    {
        $previousHash = null;
        $broken = null;

        AuditLog::query()->orderBy('id')->chunk(500, function ($entries) use (&$previousHash, &$broken) {
            foreach ($entries as $entry) {
                if ($entry->prev_hash !== $previousHash || ! hash_equals($entry->hash, $this->hashFor($entry))) {
                    $broken = $entry->id;

                    return false;
                }
                $previousHash = $entry->hash;
            }
        });

        return $broken;
    }
}
