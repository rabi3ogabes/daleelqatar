{{-- Session flash messages rendered as accessible alerts --}}
@foreach(['success' => 'circle-check', 'error' => 'circle-alert', 'warning' => 'triangle-alert', 'info' => 'info'] as $type => $icon)
    @if(session($type))
        @php $tone = ['success' => 'border-success/20 bg-success-soft text-success', 'error' => 'border-danger/20 bg-danger-soft text-danger', 'warning' => 'border-warning/20 bg-warning-soft text-warning', 'info' => 'border-info/20 bg-info-soft text-info'][$type]; @endphp
        <div x-data="{ show: true }" x-show="show" x-transition role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->class("mb-5 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm $tone") }}>
            <x-icon :name="$icon" class="mt-0.5 size-5" />
            <p class="flex-1 font-medium">{{ session($type) }}</p>
            <button type="button" @click="show = false" class="opacity-60 hover:opacity-100" aria-label="{{ __('common.close') }}"><x-icon name="x" class="size-4" /></button>
        </div>
    @endif
@endforeach
@if(session('status') && ! session('success'))
    <div role="status" {{ $attributes->class('mb-5 flex items-start gap-3 rounded-xl border border-success/20 bg-success-soft px-4 py-3 text-sm font-medium text-success') }}>
        <x-icon name="circle-check" class="mt-0.5 size-5" />{{ session('status') }}
    </div>
@endif
