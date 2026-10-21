<x-layouts.dashboard area="admin" :title="__('admin.settings.title')" :subtitle="__('admin.settings.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.settings.title')], ['label' => __('admin.settings.groups.'.$group)]]">
    <div class="grid gap-6 xl:grid-cols-[14rem_1fr_20rem]">
        <nav class="flex gap-1 overflow-x-auto scrollbar-none xl:flex-col" aria-label="{{ __('admin.settings.title') }}">
            @foreach($groups as $g)
                <a href="{{ route('admin.settings.edit', $g) }}" @class(['flex shrink-0 items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-sm font-medium transition', 'bg-navy-900 text-white' => $g === $group, 'text-navy-700 hover:bg-white' => $g !== $group]) @if($g === $group) aria-current="page" @endif>
                    <x-icon :name="['brand' => 'sparkles', 'home' => 'house', 'contact' => 'phone', 'moderation' => 'shield-check', 'uploads' => 'upload', 'notifications' => 'bell', 'seo' => 'search'][$g] ?? 'settings'" class="size-4 {{ $g === $group ? 'text-gold-300' : 'text-muted' }}" />
                    {{ __('admin.settings.groups.'.$g) }}
                </a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('admin.settings.update', $group) }}" enctype="multipart/form-data" class="min-w-0">
            @csrf @method('PUT')
            <x-dash.panel :title="__('admin.settings.groups.'.$group)" :subtitle="__('admin.settings.group_hints.'.$group)">
                <div class="divide-y divide-navy-50">
                    @foreach($fields as $f)
                        @php
                            $label = __('admin.settings.keys.'.str_replace('.', '_', $f['key']));
                            $hintKey = 'admin.settings.hints.'.str_replace('.', '_', $f['key']);
                            $hint = \Illuminate\Support\Facades\Lang::has($hintKey) ? __($hintKey) : null;
                            $name = 's['.$f['input'].']';
                            $cur = $f['current'];
                        @endphp
                        <div class="py-4 first:pt-0 last:pb-0">
                            @switch($f['type'])
                                @case('boolean')
                                    <x-form.toggle :name="$name" :label="$label" :checked="(bool) $cur" :hint="$hint" :id="'s_'.$f['input']" />
                                    @break
                                @case('text_i18n')
                                @case('textarea_i18n')
                                    <fieldset>
                                        <legend class="label">{{ $label }}</legend>
                                        <div class="grid gap-3 md:grid-cols-2">
                                            @foreach(['ar' => 'rtl', 'en' => 'ltr'] as $lang => $dir)
                                                @if($f['type'] === 'textarea_i18n')
                                                    <textarea name="{{ $name }}[{{ $lang }}]" dir="{{ $dir }}" lang="{{ $lang }}" rows="3" class="textarea min-h-20" aria-label="{{ $label }} — {{ __('common.'.($lang === 'ar' ? 'arabic' : 'english')) }}">{{ old('s.'.$f['input'].'.'.$lang, is_array($cur) ? ($cur[$lang] ?? '') : '') }}</textarea>
                                                @else
                                                    <input name="{{ $name }}[{{ $lang }}]" dir="{{ $dir }}" lang="{{ $lang }}" class="input" value="{{ old('s.'.$f['input'].'.'.$lang, is_array($cur) ? ($cur[$lang] ?? '') : '') }}" aria-label="{{ $label }} — {{ __('common.'.($lang === 'ar' ? 'arabic' : 'english')) }}">
                                                @endif
                                            @endforeach
                                        </div>
                                        @if($hint)<p class="hint">{{ $hint }}</p>@endif
                                        @error('s.'.$f['input'].'.*')<p class="error">{{ $message }}</p>@enderror
                                    </fieldset>
                                    @break
                                @case('image')
                                    <x-form.image :name="'file_'.$f['input']" :label="$label" :current="media_url($cur)" shape="square" :hint="$hint" />
                                    @if($cur)<x-form.checkbox :name="'remove_'.$f['input']" :label="__('admin.remove_image')" class="mt-2" />@endif
                                    @break
                                @default
                                    <x-form.input :name="$name" :id="'s_'.$f['input']" :type="['integer' => 'number', 'email' => 'email', 'url' => 'url'][$f['type']] ?? 'text'" :dir="in_array($f['type'], ['email', 'url', 'integer'], true) ? 'ltr' : null" :label="$label" :value="$cur" :hint="$hint" />
                                    @error('s.'.$f['input'])<p class="error">{{ $message }}</p>@enderror
                            @endswitch
                        </div>
                    @endforeach
                </div>
                <div class="mt-6 flex justify-end border-t border-navy-50 pt-5">
                    <button class="btn btn-primary"><x-icon name="check" class="size-4" />{{ __('common.save_changes') }}</button>
                </div>
            </x-dash.panel>
        </form>

        <aside>
            <x-dash.panel :title="__('admin.settings.health')" :subtitle="__('admin.settings.health_hint')">
                <dl class="space-y-3 text-sm">
                    @foreach([
                        'php' => $health['php'], 'laravel' => $health['laravel'], 'environment' => $health['environment'],
                        'database' => $health['database'], 'queue' => $health['queue'], 'cache' => $health['cache'],
                        'media_disk' => $health['media_disk'], 'mail' => $health['mail'],
                    ] as $k => $v)
                        <div class="flex items-start justify-between gap-3"><dt class="text-muted-strong">{{ __('admin.settings.health_keys.'.$k) }}</dt><dd class="text-end font-medium break-all text-navy-900" dir="ltr">{{ $v }}</dd></div>
                    @endforeach
                    @foreach(['storage_writable' => $health['storage_writable'], 'disk_ok' => $health['disk_ok'], 'debug_off' => ! $health['debug']] as $k => $ok)
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted-strong">{{ __('admin.settings.health_keys.'.$k) }}</dt>
                            <dd><span class="badge {{ $ok ? 'badge-success' : 'badge-danger' }}"><x-icon :name="$ok ? 'check' : 'triangle-alert'" class="size-3" />{{ $ok ? __('admin.settings.ok') : __('admin.settings.attention') }}</span></dd></div>
                    @endforeach
                    @foreach(['pending_jobs' => $health['pending_jobs'], 'failed_jobs' => $health['failed_jobs']] as $k => $v)
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted-strong">{{ __('admin.settings.health_keys.'.$k) }}</dt>
                            <dd><span @class(['badge', 'badge-neutral' => ! ($k === 'failed_jobs' && $v > 0), 'badge-danger' => $k === 'failed_jobs' && $v > 0])>{{ $v ?? '—' }}</span></dd></div>
                    @endforeach
                </dl>
            </x-dash.panel>
        </aside>
    </div>
</x-layouts.dashboard>
