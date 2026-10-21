<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(20);
        // Opening the page marks the visible notifications as read.
        $user->unreadNotifications()->whereIn('id', $notifications->pluck('id'))->update(['read_at' => now()]);

        return view('admin.notifications', ['notifications' => $notifications]);
    }
}
