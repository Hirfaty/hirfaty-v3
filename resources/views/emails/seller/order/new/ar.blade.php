{{-- $order, $user --}}
@php
    /*
     * تفصيل ما يحمله الطلب فعلاً بدل رقم صافٍ واحد. لا يظهر سطر التوصيل إلا
     * إذا كان هذا الطلب هو الذي حمل رسوم توصيل السلة (انظر CheckoutService).
     */
    $lines = [
        ['label' => 'إجمالي الطلب', 'amount' => (float) $order->total_amount],
        ['label' => 'عمولة حرفتي (20%)', 'amount' => -(float) $order->platform_fee],
    ];

    if ((float) $order->delivery_fee > 0) {
        $lines[] = ['label' => 'التوصيل', 'amount' => (float) $order->delivery_fee];
    }
@endphp

<x-mail.layout
    locale="ar"
    title="طلب جديد — حرفتي"
    preheader="الطلب {{ $order->reference }} في انتظار التحضير.">

    <x-mail.eyebrow>طلب جديد · {{ $order->store->store_name }}</x-mail.eyebrow>

    <x-mail.heading>لديك طلب جديد.</x-mail.heading>

    <x-mail.text>
        مرحباً {{ $user->first_name }}، تلقيت طلباً جديداً.
        حضّر القطع وأكّد الطلب — ونحن نتكفّل بعد ذلك بتوصيله إلى المشتري.
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="الطلب" :value="$order->reference" />
        <x-mail.row label="المشتري" :value="$order->buyer->first_name.' '.$order->buyer->last_name" />
        <x-mail.row label="التاريخ" :value="$order->created_at->format('d/m/Y H:i')" />
        <x-mail.row label="التوصيل إلى" :value="$order->shipping_address" />
    </x-mail.panel>

    <x-mail.items
        :items="$order->items"
        :labels="['product' => 'المنتج', 'qty' => 'الكمية', 'price' => 'السعر']" />

    <x-mail.totals
        :lines="$lines"
        :total="['label' => 'أرباحك من هذا الطلب', 'amount' => (float) $order->seller_earnings]" />

    <x-mail.note>
        أكّد هذا الطلب في أقرب وقت حتى نتمكّن من استلامه من الورشة.
        تصبح أرباحك متاحة بمجرد توصيل الطلب.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/seller/orders/'.$order->reference">
        عرض وتأكيد الطلب
    </x-mail.button>

    <x-slot:footer>
        تلقيت هذا البريد لأنك بائع على حرفتي.<br>
        <a href="{{ config('app.url') }}/seller/orders" style="color:{{ config('mail_theme.soft') }};">طلباتي</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/seller/dashboard" style="color:{{ config('mail_theme.soft') }};">لوحة التحكم</a>
    </x-slot:footer>
</x-mail.layout>
