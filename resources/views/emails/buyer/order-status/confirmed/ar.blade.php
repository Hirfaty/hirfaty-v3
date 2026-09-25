{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    $first = $orders->first();
    $many = $orders->count() > 1;

    $steps = [
        ['title' => 'تم التأكيد', 'body' => 'من طرف '.($many ? 'الورشات' : 'الورشة').'.'],
        ['title' => 'ستتلقى بريداً إلكترونياً', 'body' => 'برقم التتبع بمجرد خروج الطلب للتوصيل.'],
        ['title' => 'يُسلَّم طلبك', 'body' => 'إلى العنوان المحدد.'],
    ];
@endphp

<x-mail.layout
    locale="ar"
    title="تم تأكيد الطلب — حرفتي"
    preheader="طلبك {{ $reference }} قيد التحضير.">

    <x-mail.eyebrow>طلبك يتقدّم</x-mail.eyebrow>

    {{-- صيغة عامة بدل فتح الرسالة باسم الورشة: موضوع هذه الرسالة هو الطلب،
         لا هوية البائع. --}}
    <x-mail.heading>طلبك قيد التحضير.</x-mail.heading>

    @if ($many)
        <x-mail.text>
            مرحباً {{ $user->first_name }}، خبر سار: أكّدت الورشات الـ{{ $orders->count() }}
            المعنية بطلبك {{ $reference }} وبدأت في تحضيره.
            ستصلك رسالة بريد إلكتروني برقم التتبع بمجرد خروجه للتوصيل.
        </x-mail.text>
    @else
        <x-mail.text>
            مرحباً {{ $user->first_name }}، خبر سار: أكّدت الورشة طلبك
            {{ $reference }} وبدأت في تحضيره.
            ستصلك رسالة بريد إلكتروني برقم التتبع بمجرد خروجه للتوصيل.
        </x-mail.text>
    @endif

    <x-mail.panel>
        <x-mail.row label="رقم الطلب" :value="$reference" />
        <x-mail.row label="عنوان التوصيل" :value="$first->shipping_address" />
    </x-mail.panel>

    {{-- كتلة لكل ورشة، لتبقى الطلبية المؤكَّدة لدى عدة بائعين مقروءة كعملية
         شراء واحدة. --}}
    @foreach ($orders as $order)
        <x-mail.items
            :store="$order->store->store_name"
            :reference="$order->reference"
            :items="$order->items"
            :labels="['product' => 'المنتج', 'qty' => 'الكمية', 'price' => 'السعر']" />
    @endforeach

    <x-mail.button :url="config('app.url').'/orders'">
        تتبع طلبي
    </x-mail.button>

    <x-mail.divider />

    <x-mail.steps title="أين وصل طلبك" :steps="$steps" />

    <x-slot:footer>
        هل لديك سؤال؟ تواصل معنا من صفحة طلباتك.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">طلباتي</a>
        &nbsp;·&nbsp;
        <a href="{{ route('privacy', ['locale' => 'ar']) }}" style="color:{{ config('mail_theme.soft') }};">سياسة الخصوصية</a>
    </x-slot:footer>
</x-mail.layout>
