<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\AuditLogger;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MessageController extends Controller
{
    public const STATUSES = ['new', 'read', 'replied', 'archived'];

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : null;
        $messages = ContactMessage::query()
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'archived'))
            ->when(trim((string) $request->query('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('subject', 'ilike', '%'.addcslashes($t, '%_\\').'%')->orWhere('email', 'ilike', '%'.addcslashes($t, '%_\\').'%')->orWhere('name', 'ilike', '%'.addcslashes($t, '%_\\').'%')))
            ->latest()->paginate(20)->withQueryString();
        $counts = ContactMessage::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('admin.messages.index', ['messages' => $messages, 'status' => $status, 'counts' => $counts]);
    }

    public function show(ContactMessage $message): View
    {
        if ($message->status === 'new') {
            $message->forceFill(['status' => 'read', 'handled_by' => auth()->id(), 'handled_at' => now()])->save();
            AdminNavigation::flush();
        }

        return view('admin.messages.show', ['message' => $message->load('user:id,name,email', 'handler:id,name')]);
    }

    public function update(Request $request, ContactMessage $message, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $old = $message->status;
        $message->forceFill($data + ['handled_by' => $request->user()->id, 'handled_at' => now()])->save();
        $audit->log('message.updated', $message, ['status' => $old], ['status' => $data['status']]);
        AdminNavigation::flush();

        return back()->with('success', __('common.updated'));
    }

    public function destroy(ContactMessage $message, AuditLogger $audit): RedirectResponse
    {
        $audit->log('message.deleted', $message, ['subject' => $message->subject]);
        $message->delete();
        AdminNavigation::flush();

        return redirect()->route('admin.messages.index')->with('success', __('common.deleted'));
    }
}
