<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->latest()->paginate(15);

        return view('account.notifications', ['notifications' => $notifications]);
    }

    /** Marks one notification as read and follows its link when it has one. */
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $route = $notification->data['route'] ?? null;
        if ($request->boolean('open') && $route && Route::has(app()->getLocale().'.'.$route)) {
            try {
                return redirect()->to(route($route, $notification->data['params'] ?? []));
            } catch (\Throwable) {
                // The linked record may no longer exist; fall through.
            }
        }

        return back()->with('success', __('account.notifications.marked'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', __('account.notifications.marked'));
    }
}
