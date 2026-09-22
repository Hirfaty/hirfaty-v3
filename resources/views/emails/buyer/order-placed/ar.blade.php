{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    /*
     * عملية شراء واحدة، مهما بلغ عدد الورشات التي تشملها — لذا فإن المجاميع
     * المعروضة هي مجموع كل الطلبات التي قسّمتها CheckoutService.
     */
    $subtotal = $orders->sum(fn ($o) => (float) $o->total_amount);
    $delivery = $orders->sum(fn ($o) => (float) $o->delivery_fee);
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => $many ? 'تحضّر كل ورشة' : 'تحضّر الورشة', 'body' => 'طلبك وتسلّمه إلينا.'],
        ['title' => 'ستتلقى بريداً إلكترونياً', 'body' => 'برقم التتبع بمجرد خروج الطلب للتوصيل.'],
        ['title' => 'يُسلَّم طلبك', 'body' => 'إلى العنوان المحدد.'],
    ];
@endphp

<x-mail.layout
    locale="ar"
    title="تم تأكيد الطلب — حرفتي"
    preheader="تم استلام طلبك {{ $reference }} بنجاح.">

    <x-mail.eyebrow>تم تأكيد الطلب</x-mail.eyebrow>

    <x-mail.heading>طلبك في الطريق إليك.</x-mail.heading>

    <x-mail.text>
        مرحباً {{ $user->first_name }}، تلقينا طلبك بنجاح.
        @if ($many)
            يشمل طلبك {{ $orders->count() }} ورشات مختلفة، لذا تجده أدناه
            مفصّلاً ورشةً بورشة — تحضّر كل ورشة نصيبها ونجمع الطلب كاملاً
            قبل إرساله.
        @else
            ستقوم الورشة بتحضيره وستصلك رسالة بريد إلكتروني فور انطلاقه
            إليك.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="رقم الطلب" :value="$reference" />
        <x-mail.row label="التاريخ" :value="$first->created_at->format('d/m/Y H:i')" />
        <x-mail.row label="عنوان التوصيل" :value="$first->shipping_address" />
        {{-- الدفع عند الاستلام هو الوسيلة الوحيدة المقبولة عند إتمام الطلب
             (CheckoutRequest::PAYMENT_METHODS)، لذا نوضّح للمشتري أنه لم
             يُخصم منه أي مبلغ بعد. --}}
        <x-mail.row label="طريقة الدفع" value="الدفع عند الاستلام" />
    </x-mail.panel>

    {{-- بطاقة واحدة لكل ورشة، حتى يبقى الشراء من عدة بائعين مقروءاً كطلب
         واحد متكامل. --}}
    @foreach ($orders as $order)
        <x-mail.items
            :store="$order->store->store_name"
            :reference="$order->reference"
            :items="$order->items"
            :labels="['product' => 'المنتج', 'qty' => 'الكمية', 'price' => 'السعر']" />
    @endforeach

    <x-mail.totals
        :lines="[
            ['label' => 'المجموع الفرعي', 'amount' => $subtotal],
            ['label' => 'التوصيل', 'amount' => $delivery],
        ]"
        :total="['label' => 'الإجمالي المستحق عند الاستلام', 'amount' => $subtotal + $delivery]" />

    <x-mail.note title="التوصيل">
        سيتم توصيل طلبك إلى العنوان المذكور أعلاه، عادةً خلال 3 إلى 7
        أيام في جميع أنحاء المغرب. ستتلقى رقم التتبع بالبريد الإلكتروني
        بمجرد انطلاق الطرد.
    </x-mail.note>

    <x-mail.button :url="config('app.url').'/orders'">
        تتبع طلباتي
    </x-mail.button>

    <x-mail.divider />

    <x-mail.steps title="ما الذي سيحدث بعد ذلك" :steps="$steps" />

    <x-slot:footer>
        هل لديك سؤال؟ تواصل معنا وسنتابع الأمر مع الورشة.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">طلباتي</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/privacy" style="color:{{ config('mail_theme.soft') }};">سياسة الخصوصية</a>
    </x-slot:footer>
</x-mail.layout>
