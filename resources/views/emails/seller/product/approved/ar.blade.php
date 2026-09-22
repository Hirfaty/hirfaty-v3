{{-- $product, $user --}}
@php
    // `translated_name`, not `name`: Category::$casts makes `name` a per-locale
    // array, so echoing it raised "htmlspecialchars(): array given" and threw
    // the queued mail job — the seller never received this at all.
    $category = $product->category?->translated_name ?? 'بدون فئة';
    $price = $product->sale_price ?? $product->base_price;
@endphp

<x-mail.layout
    locale="ar"
    title="منتجك أصبح متاحاً الآن — حرفتي"
    preheader="{{ $product->name }} أصبح مرئياً لجميع المشترين.">

    <x-mail.eyebrow>تمت الموافقة على المنتج</x-mail.eyebrow>

    <x-mail.heading>منتجك أصبح متاحاً للعموم.</x-mail.heading>

    <x-mail.text>
        مرحباً {{ $user->first_name }}، تمت مراجعة منتجك والموافقة عليه من قِبل فريقنا.
        أصبح الآن مرئياً لجميع المشترين على حرفتي.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="المنتج" :value="$product->name" />
        <x-mail.row label="الفئة" :value="$category" />
        <x-mail.row label="المتجر" :value="$product->store?->store_name" />
        <x-mail.row label="السعر" :value="number_format($price, 2).' د.م'" />
    </x-mail.panel>

    <x-mail.button :url="config('app.url').'/products/'.$product->slug">
        مشاهدة منتجي على الموقع
    </x-mail.button>

    <x-mail.divider />

    <x-mail.note title="نصيحة">
        شارك رابط منتجك على وسائل التواصل الاجتماعي لزيادة وضوحك. كلما زادت
        المشاهدات، ارتفع ترتيب منتجك في نتائج البحث على حرفتي.
    </x-mail.note>

    <x-slot:footer>
        تلقيت هذا البريد الإلكتروني لأنك بائع على حرفتي.<br>
        <a href="{{ config('app.url') }}/seller/products" style="color:{{ config('mail_theme.soft') }};">منتجاتي</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/seller/dashboard" style="color:{{ config('mail_theme.soft') }};">لوحة التحكم</a>
    </x-slot:footer>
</x-mail.layout>
