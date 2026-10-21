@php
    $email = setting('contact.email');
    $phone = setting('contact.phone');
    $whatsapp = setting('contact.whatsapp');
@endphp
<x-layouts.app>
    <x-page-hero :title="__('contact.title')" :subtitle="__('contact.subtitle')" icon="message-square" :breadcrumbs="[['label' => __('nav.contact')]]" />
    <div class="container-app grid gap-8 py-12 lg:grid-cols-12">
        <form method="POST" action="{{ route('contact.store') }}" class="card card-pad space-y-5 lg:col-span-8">
            @csrf
            <x-flash />
            <div class="grid gap-5 md:grid-cols-2">
                <x-form.input name="name" :label="__('fields.name')" :value="auth()->user()?->name" required maxlength="120" autocomplete="name" />
                <x-form.input name="email" type="email" dir="ltr" :label="__('fields.email')" :value="auth()->user()?->email" required autocomplete="email" />
                <x-form.input name="phone" type="tel" dir="ltr" :label="__('fields.phone').' ('.__('common.optional').')'" autocomplete="tel" />
                <x-form.input name="subject" :label="__('fields.subject')" required maxlength="200" />
            </div>
            <x-form.textarea name="message" :label="__('fields.message')" rows="6" required maxlength="5000" />
            <div class="hidden" aria-hidden="true"><input type="text" name="company_website" tabindex="-1" autocomplete="off"></div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-muted-strong">{{ __('contact.response_time') }}</p>
                <button class="btn btn-primary btn-lg"><x-icon name="send" class="size-5" />{{ __('contact.send') }}</button>
            </div>
        </form>
        <aside class="space-y-4 lg:col-span-4">
            @if($email)
                <a href="mailto:{{ $email }}" class="card card-hover flex items-center gap-4 p-5"><span class="flex size-12 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon name="mail" /></span><span><span class="block text-xs text-muted-strong">{{ __('contact.email_us') }}</span><span class="font-semibold text-navy-900" dir="ltr">{{ $email }}</span></span></a>
            @endif
            @if($phone)
                <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="card card-hover flex items-center gap-4 p-5"><span class="flex size-12 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon name="phone" /></span><span><span class="block text-xs text-muted-strong">{{ __('contact.call_us') }}</span><span class="font-semibold text-navy-900" dir="ltr">{{ $phone }}</span></span></a>
            @endif
            @if($whatsapp)
                <a href="https://wa.me/{{ preg_replace('/\D/', '', $whatsapp) }}" target="_blank" rel="noopener noreferrer" class="card card-hover flex items-center gap-4 p-5"><span class="flex size-12 items-center justify-center rounded-xl bg-[#128C4A] text-white"><x-icon name="whatsapp" /></span><span><span class="block text-xs text-muted-strong">WhatsApp</span><span class="font-semibold text-navy-900" dir="ltr">{{ $whatsapp }}</span></span></a>
            @endif
            <div class="card flex items-center gap-4 p-5"><span class="flex size-12 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon name="map-pin" /></span><span><span class="block text-xs text-muted-strong">{{ __('contact.visit_us') }}</span><span class="font-semibold text-navy-900">{{ setting_t('contact.address', 'Doha, Qatar') }}</span></span></div>
            <a href="{{ route('faq') }}" class="flex items-center justify-between rounded-2xl bg-gold-50 p-5 text-sm font-medium text-gold-800 ring-1 ring-gold-100 hover:bg-gold-100">{{ __('pages.faq_title') }}<x-icon name="arrow-right" class="size-4" /></a>
        </aside>
    </div>
</x-layouts.app>
