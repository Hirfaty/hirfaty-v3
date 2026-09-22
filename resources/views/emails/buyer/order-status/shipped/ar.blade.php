{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    /*
     * رقم تتبع مشترك يعني عادة طرداً واحداً فعلياً: مكتب حرفتي يجمع الطلبية
     * من كل ورشة لمستها ثم يسلّمها للناقل كعمل واحد (انظر
     * Admin\OrderController). مُدرَجة هنا احتياطاً إن حمل سجل المجموعة أكثر
     * من رقم.
     */
    $trackingNumbers = $orders->pluck('tracking_number')->filter()->unique()->values();
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => 'تم التأكيد', 'body' => 'من طرف '.($many ? 'الورشات' : 'الورشة').'.'],
        ['title' => 'خرج للتوصيل', 'body' => ''],
        ['title' => 'قيد التوصيل', 'body' => 'إلى العنوان المحدد.'],
    ];
@endphp

<x-mail.layout
    locale="ar"
    title="تم شحن الطلب — حرفتي"
    preheader="طلبك {{ $reference }} خرج للتوصيل.">

    <x-mail.eyebrow>في طريقه إليك</x-mail.eyebrow>

    <x-mail.heading>طلبك في الطريق إليك.</x-mail.heading>

    <x-mail.text>
        @if ($many)
            مرحباً {{ $user->first_name }}، طلبك {{ $reference }} خرج للتوصيل
            — قامت كل ورشة من الورشات الـ{{ $orders->count() }} المعنية
            بتحضير حصتها.
        @else
            مرحباً {{ $user->first_name }}، طلبك {{ $reference }} من
            {{ $first->store->store_name }} خرج للتوصيل.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="رقم الطلب" :value="$reference" />
        @if ($trackingNumbers->count() === 1)
            <x-mail.row label="رقم التتبع" :value="$trackingNumbers->first()" />
        @endif
        <x-mail.row label="عنوان التوصيل" :value="$first->shipping_address" />
    </x-mail.panel>

    {{-- كتلة لكل ورشة، ليبقى الطرد المُجمَّع مقروءاً كعملية شراء واحدة. --}}
    @if ($many)
        @foreach ($orders as $order)
            <x-mail.items
                :store="$order->store->store_name"
                :reference="$order->reference.($order->tracking_number && $trackingNumbers->count() > 1 ? ' · '.$order->tracking_number : '')"
                :items="$order->items"
                :labels="['product' => 'المنتج', 'qty' => 'الكمية', 'price' => 'السعر']" />
        @endforeach
    @endif

    <x-mail.note title="التوصيل">
        يستغرق التوصيل عادة من 3 إلى 7 أيام في جميع أنحاء المغرب.
        احتفظ بهاتفك قريباً — يتصل بك الموصّل قبل تسليم الطرد.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/orders'">
        تتبع طلبي
    </x-mail.button>

    <x-mail.divider />

    <x-mail.steps title="أين وصل طلبك" :steps="$steps" />

    <x-slot:footer>
        هل لديك سؤال؟ تواصل معنا من صفحة طلباتك.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">طلباتي</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/privacy" style="color:{{ config('mail_theme.soft') }};">سياسة الخصوصية</a>
    </x-slot:footer>
</x-mail.layout>
