{{-- $order, $user --}}
<x-mail.layout
    locale="ar"
    title="تم إلغاء الطلب — حرفتي"
    preheader="تم إلغاء الطلب {{ $order->reference }} من قِبل المشتري.">

    <x-mail.eyebrow tone="danger">تم إلغاء الطلب</x-mail.eyebrow>

    <x-mail.heading>قام المشتري بإلغاء الطلب {{ $order->reference }}.</x-mail.heading>

    <x-mail.text>
        مرحباً {{ $user->first_name }}، قام المشتري
        {{ $order->buyer->first_name }} {{ $order->buyer->last_name }}
        بإلغاء طلبه. تم استعادة المخزون تلقائياً.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="الطلب" :value="$order->reference" />
        <x-mail.row label="المشتري" :value="$order->buyer->first_name.' '.$order->buyer->last_name" />
        <x-mail.row label="المبلغ" :value="number_format($order->total_amount, 2).' د.م'" />
    </x-mail.panel>

    <x-mail.note tone="danger" title="سبب الإلغاء">
        {{ $order->cancellation_reason }}
    </x-mail.note>

    <x-mail.note>
        تم تحديث مخزون المنتجات المتأثرة تلقائياً.
        لا توجد إجراءات إضافية مطلوبة منك.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/seller/orders'">
        عرض طلباتي
    </x-mail.button>

    <x-slot:footer>
        <a href="{{ config('app.url') }}/seller/orders" style="color:{{ config('mail_theme.soft') }};">طلباتي</a>
    </x-slot:footer>
</x-mail.layout>
