{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => 'تم التأكيد', 'body' => 'من طرف '.($many ? 'الورشات' : 'الورشة').'.'],
        ['title' => 'خرج للتوصيل', 'body' => ''],
        ['title' => 'تم التوصيل', 'body' => 'إلى العنوان المحدد.'],
    ];

    /*
     * تُضاف التقييمات من صفحة الطلب نفسه (buyer.orders.reviews.store): الطلب
     * من ورشة واحدة يحيل مباشرة إليها، أما الطلب المجمّع فيحيل إلى قائمة
     * الطلبات.
     */
    $reviewUrl = config('app.url').'/orders'.($many ? '' : '/'.$first->reference);
@endphp

<x-mail.layout
    locale="ar"
    title="تم توصيل الطلب — حرفتي"
    preheader="طلبك {{ $reference }} وصل بسلام.">

    <x-mail.eyebrow>لقد وصل</x-mail.eyebrow>

    <x-mail.heading>طلبك وصل بسلام.</x-mail.heading>

    <x-mail.text>
        @if ($many)
            مرحباً {{ $user->first_name }}، تم توصيل طلبك {{ $reference }} الذي جمع
            {{ $orders->count() }} ورشات. نتمنى أن ينال إعجابك بقدر ما استمتعنا
            بإيصاله إليك.
        @else
            مرحباً {{ $user->first_name }}، تم توصيل طلبك {{ $reference }} من
            {{ $first->store->store_name }}. نتمنى أن ينال إعجابك بقدر ما استمتعنا
            بإيصاله إليك.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="رقم الطلب" :value="$reference" />
        <x-mail.row label="تاريخ التوصيل" :value="optional($first->delivered_at)->format('d/m/Y')" />
    </x-mail.panel>

    {{-- كتلة لكل ورشة، لتبقى الطلبية المُسلَّمة من عدة بائعين مقروءة كعملية
         شراء واحدة. --}}
    @foreach ($orders as $order)
        <x-mail.items
            :store="$order->store->store_name"
            :reference="$order->reference"
            :items="$order->items"
            :labels="['product' => 'المنتج', 'qty' => 'الكمية', 'price' => 'السعر']" />
    @endforeach

    <x-mail.button :url="$reviewUrl">
        أضف تقييمك
    </x-mail.button>

    <x-mail.divider />

    <x-mail.steps title="أين وصل طلبك" :steps="$steps" />

    <x-slot:footer>
        هل واجهت مشكلة في هذا الطلب؟ تواصل معنا من صفحة طلباتك.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">طلباتي</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/privacy" style="color:{{ config('mail_theme.soft') }};">سياسة الخصوصية</a>
    </x-slot:footer>
</x-mail.layout>
