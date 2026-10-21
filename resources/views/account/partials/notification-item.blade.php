@php
    $data = $notification->data;
    $unread = $notification->read_at === null;
    $hasLink = ! empty($data['route']) && \Illuminate\Support\Facades\Route::has(app()->getLocale().'.'.$data['route']);
    $icon = \App\Support\Icons::has($data['icon'] ?? '') ? $data['icon'] : 'bell';
@endphp
<li @class(['flex items-start gap-3 rounded-xl p-3 transition', 'bg-gold-50/60' => $unread])>
    <span @class(['mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg', 'bg-navy-900 text-gold-300' => $unread, 'bg-navy-50 text-muted' => ! $unread])><x-icon :name="$icon" class="size-4.5" /></span>
    <div class="min-w-0 flex-1">
        <p class="flex items-center gap-2 text-sm font-semibold text-navy-900">{{ $data['title'] ?? '' }}@if($unread)<span class="size-1.5 rounded-full bg-gold-500"></span><span class="sr-only">{{ __('notifications.unread') }}</span>@endif</p>
        <p class="mt-0.5 text-sm text-muted-strong">{{ $data['body'] ?? '' }}</p>
        <p class="mt-1 text-xs text-muted">{{ $notification->created_at->locale(app()->getLocale())->diffForHumans() }}</p>
    </div>
    @if($hasLink || $unread)
        <form method="POST" action="{{ route('account.notifications.read', $notification->id) }}" class="shrink-0">
            @csrf
            @if($hasLink)
                <input type="hidden" name="open" value="1">
                <button type="submit" class="btn btn-ghost btn-sm" data-no-busy>{{ __('account.notifications.open') }}<x-icon name="chevron-right" class="size-3.5" /></button>
            @else
                <button type="submit" class="btn btn-ghost btn-sm" data-no-busy title="{{ __('account.notifications.mark_read') }}"><x-icon name="check" class="size-4" /><span class="sr-only">{{ __('account.notifications.mark_read') }}</span></button>
            @endif
        </form>
    @endif
</li>
