<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\StaffAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\s\-()]{7,32}$/'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $message = new ContactMessage($data + ['locale' => app()->getLocale()]);
        $message->user_id = auth('sanctum')->id();
        $message->ip_hash = ip_hash();
        $message->status = 'new';
        $message->save();

        if (setting('notifications.notify_admins_contact', true)) {
            Notification::send(User::permission('messages.manage')->get(), new StaffAlert('contact',
                __('notifications.staff_contact_body', ['name' => $message->name, 'subject' => $message->subject]), 'admin.messages.show', ['message' => $message->id]));
        }

        return response()->json(['message' => __('contact.sent')], 201);
    }
}
