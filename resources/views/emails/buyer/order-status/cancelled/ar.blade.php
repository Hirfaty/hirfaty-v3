{{-- $orders (Collection<Order>), $reference, $user --}}
@php
    $first = $orders->first();
    $many = $orders->count() > 1;
@endphp

<x-mail.layout
    locale="ar"
    title="تم إلغاء الطلب — حرفتي"
    preheader="تم إلغاء طلبك {{ $reference }}.">

    <x-mail.eyebrow tone="danger">نعتذر عن ذلك</x-mail.eyebrow>

    <x-mail.heading>تم إلغاء طلبك.</x-mail.heading>

    <x-mail.text>
        {{-- لا نذكر من قام بالإلغاء عمداً: نفس هذا البريد يُرسل أيضاً حين
             يلغي المشتري طلبه بنفسه من صفحة طلباته
             (Buyer\OrderController::cancel). --}}
        @if ($many)
            مرحباً {{ $user->first_name }}، تم إلغاء طلبك {{ $reference }} الذي يشمل
            {{ $orders->count() }} ورشات.
        @else
            مرحباً {{ $user->first_name }}، تم إلغاء طلبك {{ $reference }} من
            {{ $first->store->store_name }}.
        @endif
        @if ($first->isPaidByCard())
            سيُرَدّ إليك المبلغ المدفوع بالبطاقة على البطاقة نفسها.
        @else
            لم يُخصم منك أي مبلغ — الدفع يتم عند الاستلام.
        @endif
    </x-mail.text>

    <x-mail.panel>
        <x-mail.row label="رقم الطلب" :value="$reference" />
    </x-mail.panel>

    {{-- كتلة لكل ورشة معنية، ليبقى الإلغاء الذي يمس عدة بائعين مقروءاً كعملية
         شراء واحدة. --}}
    @if ($many)
        @foreach ($orders as $order)
            <x-mail.items
                :store="$order->store->store_name"
                :reference="$order->reference"
                :items="$order->items"
                :labels="['product' => 'المنتج', 'qty' => 'الكمية', 'price' => 'السعر']" />
        @endforeach
    @endif

    @if ($first->cancellation_reason)
        <x-mail.note tone="danger" title="السبب المذكور">
            {{ $first->cancellation_reason }}
        </x-mail.note>
    @endif

    <x-mail.button :url="route('products.index', ['locale' => 'ar'])">
        متابعة التسوق
    </x-mail.button>

    <x-slot:footer>
        لديك سؤال حول هذا الإلغاء؟ تواصل معنا من صفحة طلباتك.<br>
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">طلباتي</a>
        &nbsp;·&nbsp;
        <a href="{{ route('privacy', ['locale' => 'ar']) }}" style="color:{{ config('mail_theme.soft') }};">سياسة الخصوصية</a>
    </x-slot:footer>
</x-mail.layout>
