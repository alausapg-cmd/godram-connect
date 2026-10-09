<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Services\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** Works out who should hear about an audited action and sends them a notice. */
class RouteAuditEntry implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $auditLogId) {}

    public function handle(Notify $notify): void
    {
        if ($entry = AuditLog::find($this->auditLogId)) {
            $notify->fromAudit($entry);
        }
    }
}
