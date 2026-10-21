<div class="grid grid-cols-4 gap-2">
    @foreach(['whatsapp', 'facebook'] as $key)
        @php [$href, $label, $icon, $tone] = $links[$key]; @endphp
        <a href="{{ $href }}" target="_blank" rel="noopener noreferrer nofollow" @click="sent()" class="group flex flex-col items-center gap-1.5 rounded-xl p-2 text-center hover:bg-navy-50">
            <span class="flex size-11 items-center justify-center rounded-full {{ $tone }} shadow-soft transition group-hover:scale-105"><x-icon :name="$icon" class="size-5" /></span>
            <span class="text-[0.7rem] font-medium text-navy-900">{{ $label }}</span>
        </a>
    @endforeach
    <button type="button" @click="messenger()" class="group flex flex-col items-center gap-1.5 rounded-xl p-2 text-center hover:bg-navy-50">
        <span class="flex size-11 items-center justify-center rounded-full bg-gradient-to-br from-[#00B2FF] to-[#A033FF] text-white shadow-soft transition group-hover:scale-105"><x-icon name="messenger" class="size-5" /></span>
        <span class="text-[0.7rem] font-medium text-navy-900">Messenger</span>
    </button>
    @php [$href, $label, $icon, $tone] = $links['x']; @endphp
    <a href="{{ $href }}" target="_blank" rel="noopener noreferrer nofollow" @click="sent()" class="group flex flex-col items-center gap-1.5 rounded-xl p-2 text-center hover:bg-navy-50">
        <span class="flex size-11 items-center justify-center rounded-full {{ $tone }} shadow-soft transition group-hover:scale-105"><x-icon :name="$icon" class="size-4.5" /></span>
        <span class="text-[0.7rem] font-medium text-navy-900">X</span>
    </a>
    <button type="button" @click="instagram()" class="group flex flex-col items-center gap-1.5 rounded-xl p-2 text-center hover:bg-navy-50">
        <span class="flex size-11 items-center justify-center rounded-full bg-gradient-to-tr from-[#F58529] via-[#DD2A7B] to-[#8134AF] text-white shadow-soft transition group-hover:scale-105"><x-icon name="instagram" class="size-5" /></span>
        <span class="text-[0.7rem] font-medium text-navy-900">Instagram</span>
    </button>
    @foreach(['telegram', 'linkedin', 'email'] as $key)
        @php [$href, $label, $icon, $tone] = $links[$key]; @endphp
        <a href="{{ $href }}" @if($key !== 'email') target="_blank" rel="noopener noreferrer nofollow" @endif @click="sent()" class="group flex flex-col items-center gap-1.5 rounded-xl p-2 text-center hover:bg-navy-50">
            <span class="flex size-11 items-center justify-center rounded-full {{ $tone }} shadow-soft transition group-hover:scale-105"><x-icon :name="$icon" class="size-5" /></span>
            <span class="text-[0.7rem] font-medium text-navy-900">{{ $label }}</span>
        </a>
    @endforeach
</div>
<div class="mt-2 flex gap-2">
    <button type="button" @click="copy()" class="btn btn-outline btn-sm flex-1"><x-icon name="link" class="size-4" /><span x-text="copiedNow ? @js(__('common.link_copied')) : @js(__('directory.share.copy_link'))"></span></button>
    <button type="button" x-show="canNative" x-cloak @click="native()" class="btn btn-primary btn-sm"><x-icon name="share-2" class="size-4" />{{ __('directory.share.more') }}</button>
</div>
