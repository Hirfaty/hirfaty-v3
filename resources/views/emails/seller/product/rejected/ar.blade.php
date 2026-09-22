{{-- $product, $user --}}
@php
    // `translated_name`, not `name`: Category::$casts makes `name` a per-locale
    // array, so echoing it raised "htmlspecialchars(): array given" and threw
    // the queued mail job — the seller never received this at all.
    $category = $product->category?->translated_name ?? 'بدون فئة';

    /*
     * الحرفيون لا ينشئون فِهرسهم ولا يعدّلونه — فريق حرفتي هو من يتولى ذلك —
     * لذا ندعوهم للتواصل معنا بدل تعديل بطاقة لا يملكون صلاحية الوصول إليها.
     */
    $steps = [
        ['title' => 'اقرأ بعناية', 'body' => 'سبب الرفض المذكور أعلاه.'],
        ['title' => 'تواصل مع فريقنا', 'body' => 'بالصور أو السعر أو الوصف بعد تصحيحها.'],
        ['title' => 'نعيد نشر البطاقة', 'body' => 'نيابة عنك بمجرد تسوية هذه النقاط.'],
    ];
@endphp

<x-mail.layout
    locale="ar"
    title="منتجك يحتاج إلى تعديلات — حرفتي"
    preheader="{{ $product->name }} لا يمكن نشره في حالته الحالية.">

    <x-mail.eyebrow tone="danger">التعديلات مطلوبة</x-mail.eyebrow>

    <x-mail.heading>بعض التعديلات ضرورية.</x-mail.heading>

    <x-mail.text>
        مرحباً {{ $user->first_name }}، قام فريقنا بمراجعة منتجك.
        لا يمكن نشره في حالته الحالية. أرسل لنا ما ينقص وسنصحّح البطاقة
        نيابةً عنك.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="المنتج" :value="$product->name" />
        <x-mail.row label="الفئة" :value="$category" />
        <x-mail.row label="المتجر" :value="$product->store?->store_name" />
    </x-mail.panel>

    <x-mail.note tone="danger" title="سبب الرفض">
        {{ $product->rejection_reason }}
    </x-mail.note>

    <x-mail.steps title="كيفية تصحيح ذلك" :steps="$steps" />

    {{-- يشير إلى البطاقة للقراءة فقط: الحرفي لا يستطيع تعديلها بنفسه. --}}
    <x-mail.button tone="danger" :url="config('app.url').'/seller/products/'.$product->id">
        عرض المنتج
    </x-mail.button>

    <x-slot:footer>
        تلقيت هذا البريد الإلكتروني لأنك بائع على حرفتي.<br>
        <a href="{{ config('app.url') }}/seller/products" style="color:{{ config('mail_theme.soft') }};">منتجاتي</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/seller/dashboard" style="color:{{ config('mail_theme.soft') }};">لوحة التحكم</a>
    </x-slot:footer>
</x-mail.layout>
