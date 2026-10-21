{{-- Responsive table wrapper: scrolls horizontally on small screens. --}}
<div {{ $attributes->class('-mx-px overflow-x-auto') }}>
    <table class="table-app min-w-[640px]">
        {{ $slot }}
    </table>
</div>
