<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Access;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request, Access $access, AuditLogger $audit)
    {
        abort_unless($access->can($request->user(), 'audit.view'), 403);

        return view('admin.audit', [
            'entries' => AuditLog::with('user')
                ->when($request->input('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->when($request->input('q'), fn ($q, $t) => $q->where('summary', 'like', "%$t%"))
                ->latest('id')->paginate(50)->withQueryString(),
            'broken' => $request->boolean('verify') ? $audit->firstBrokenEntry() : false,
            'verified' => $request->boolean('verify'),
        ]);
    }
}
