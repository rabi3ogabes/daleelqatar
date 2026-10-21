<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (array) $this->data;
        $route = $data['route'] ?? null;
        $locale = app()->getLocale();
        $url = null;
        if ($route && Route::has($locale.'.'.$route)) {
            try {
                $url = route($locale.'.'.$route, $data['params'] ?? []);
            } catch (\Throwable) {
                $url = null;
            }
        }

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? null,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'icon' => $data['icon'] ?? null,
            'route' => $route,
            'params' => $data['params'] ?? [],
            'web_url' => $url,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
