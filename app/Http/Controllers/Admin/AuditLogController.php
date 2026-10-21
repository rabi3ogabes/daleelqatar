<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()->with('user:id,name,email')
            ->when(trim((string) $request->query('action')), fn ($q, $a) => $q->where('action', 'like', addcslashes($a, '%_\\').'%'))
            ->when($request->integer('user'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('subject'), fn ($q, $s) => $q->where('auditable_type', $s))
            ->when($request->date('from'), fn ($q, $d) => $q->where('created_at', '>=', $d->startOfDay()))
            ->when($request->date('to'), fn ($q, $d) => $q->where('created_at', '<=', $d->endOfDay()))
            ->latest('created_at')->latest('id')->paginate(30)->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->selectRaw("split_part(action, '.', 1) as prefix")->distinct()->orderBy('prefix')->pluck('prefix'),
            'subjects' => AuditLog::query()->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
        ]);
    }

    public function show(AuditLog $log): View
    {
        $log->load('user:id,name,email');
        $keys = array_values(array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))));

        return view('admin.audit.show', ['log' => $log, 'keys' => $keys]);
    }
}
