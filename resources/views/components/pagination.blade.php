@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('common.pagination') }}" class="mt-10 flex items-center justify-between gap-4">
        <p class="hidden text-sm text-muted-strong sm:block">
            @if(method_exists($paginator, 'total'))
                {{ __('common.showing', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => format_number($paginator->total())]) }}
            @endif
        </p>
        <div class="flex flex-1 items-center justify-between gap-1 sm:flex-none sm:justify-end">
            @if ($paginator->onFirstPage())
                <span class="btn btn-outline btn-sm pointer-events-none opacity-40" aria-disabled="true"><x-icon name="chevron-left" class="size-4" />{{ __('common.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-outline btn-sm"><x-icon name="chevron-left" class="size-4" />{{ __('common.previous') }}</a>
            @endif

            @if(isset($elements))
                <div class="hidden items-center gap-1 md:flex">
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="px-2 text-sm text-muted">{{ $element }}</span>
                        @endif
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="flex size-9 items-center justify-center rounded-lg bg-navy-900 text-sm font-semibold text-white">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" class="flex size-9 items-center justify-center rounded-lg text-sm font-medium text-navy-700 transition hover:bg-gold-50 hover:text-gold-800" aria-label="{{ __('common.page_x_of_y', ['current' => $page, 'last' => method_exists($paginator, 'lastPage') ? $paginator->lastPage() : $page]) }}">{{ $page }}</a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </div>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-outline btn-sm">{{ __('common.next') }}<x-icon name="chevron-right" class="size-4" /></a>
            @else
                <span class="btn btn-outline btn-sm pointer-events-none opacity-40" aria-disabled="true">{{ __('common.next') }}<x-icon name="chevron-right" class="size-4" /></span>
            @endif
        </div>
    </nav>
@endif
