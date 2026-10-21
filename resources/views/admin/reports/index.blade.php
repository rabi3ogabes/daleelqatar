<x-layouts.dashboard area="admin" :title="__('admin.reports.title')" :subtitle="__('admin.reports.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.reports.title')]]">
    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach(['listings' => __('admin.reports.listing_reports'), 'reviews' => __('admin.reports.review_reports')] as $key => $label)
            <a href="{{ route('admin.reports.index', ['tab' => $key, 'status' => $status?->value ?? 'all']) }}" @class(['btn', 'btn-primary' => $tab === $key, 'btn-outline' => $tab !== $key])>{{ $label }} @if($openCounts[$key])<span class="rounded-full bg-gold-400 px-1.5 text-[0.65rem] font-bold text-navy-950">{{ $openCounts[$key] }}</span>@endif</a>
        @endforeach
        <span class="mx-2 h-6 w-px bg-navy-100"></span>
        @foreach(\App\Enums\ReportStatus::cases() as $s)
            <a href="{{ route('admin.reports.index', ['tab' => $tab, 'status' => $s->value]) }}" @class(['btn btn-sm', 'btn-primary' => $status === $s, 'btn-ghost' => $status !== $s])>{{ $s->label() }}</a>
        @endforeach
        <a href="{{ route('admin.reports.index', ['tab' => $tab, 'status' => 'all']) }}" @class(['btn btn-sm', 'btn-primary' => $status === null, 'btn-ghost' => $status !== null])>{{ __('common.all') }}</a>
    </div>

    @php $items = $tab === 'reviews' ? $reviewReports : $listingReports; @endphp
    <div class="space-y-3">
        @forelse($items as $report)
            <article class="card flex flex-col gap-4 p-5 lg:flex-row lg:items-start">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-danger"><x-icon name="flag" class="size-3" />{{ $report->reason->label() }}</span>
                        <x-status-badge :status="$report->status" />
                        <span class="text-xs text-muted">{{ $report->created_at?->diffForHumans() }}</span>
                    </div>
                    @if($tab === 'reviews')
                        <p class="mt-2 text-sm text-navy-900">“{{ \Illuminate\Support\Str::limit($report->review?->body, 220) }}”</p>
                        <p class="mt-1 text-xs text-muted-strong">{{ $report->review?->user?->name }} · {{ $report->review?->business?->t('name') }}</p>
                    @else
                        <p class="mt-2 font-semibold text-navy-900"><a href="{{ $report->business ? route('admin.businesses.show', $report->business->id) : '#' }}" class="hover:text-gold-800">{{ $report->business?->t('name') }}</a></p>
                    @endif
                    @if($report->details)<p class="mt-2 rounded-lg bg-navy-50 px-3 py-2 text-sm text-charcoal" dir="auto">{{ $report->details }}</p>@endif
                    <p class="mt-2 text-xs text-muted-strong">{{ __('admin.reports.reporter') }}: {{ $report->user?->name ?? ($tab === 'listings' ? ($report->reporter_email ?? __('admin.reports.anonymous')) : __('admin.reports.anonymous')) }}</p>
                    @if($report->resolution_note)<p class="mt-1 text-xs text-muted">{{ __('admin.reports.resolution') }}: {{ $report->resolution_note }} — {{ $report->handler?->name }}</p>@endif
                </div>
                <form method="POST" action="{{ $tab === 'reviews' ? route('admin.reports.review.resolve', $report->id) : route('admin.reports.listing.resolve', $report->id) }}" class="flex w-full flex-col gap-2 lg:w-72">
                    @csrf
                    <input type="text" name="resolution_note" maxlength="1000" class="input py-2 text-xs" placeholder="{{ __('admin.reports.note_placeholder') }}" aria-label="{{ __('admin.reports.resolution') }}">
                    <div class="grid grid-cols-2 gap-2">
                        @if($report->status !== \App\Enums\ReportStatus::Open)
                            <button name="status" value="open" class="btn btn-outline btn-sm col-span-2">{{ __('admin.reports.reopen') }}</button>
                        @else
                            <button name="status" value="resolved" class="btn btn-primary btn-sm">{{ __('admin.reports.resolve') }}</button>
                            <button name="status" value="dismissed" class="btn btn-outline btn-sm">{{ __('admin.reports.dismiss') }}</button>
                        @endif
                    </div>
                    @if($tab === 'reviews' && $report->review)
                        <a href="{{ route('admin.reviews.index', ['status' => 'all', 'reported' => 1]) }}" class="btn btn-ghost btn-sm">{{ __('admin.reports.moderate_review') }}</a>
                    @endif
                </form>
            </article>
        @empty
            <x-empty-state icon="flag" :title="__('admin.reports.empty')" />
        @endforelse
    </div>
    {{ $items->links() }}
</x-layouts.dashboard>
