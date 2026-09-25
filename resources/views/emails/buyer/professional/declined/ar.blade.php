{{-- $user, $reason --}}
<x-mail.layout
    locale="ar"
    title="طلب حسابك المهني — حرفتي"
    preheader="راجع فريقنا طلبك للحصول على حساب مهني.">

    <x-mail.eyebrow tone="danger">تمت مراجعة الطلب</x-mail.eyebrow>

    <x-mail.heading>لم نتمكن من قبوله في الوقت الحالي.</x-mail.heading>

    <x-mail.text>
        مرحباً {{ $user->first_name }}، راجع فريقنا طلبك للحصول على حساب مهني،
        ولم نتمكن من قبوله بصيغته الحالية.
    </x-mail.text>

    <x-mail.note tone="danger" title="السبب">
        {{ $reason }}
    </x-mail.note>

    <x-mail.text>
        يبقى حسابك نشطاً بالكامل ويمكنك مواصلة الطلب كالمعتاد.
        ويمكنك تقديم طلب جديد بمجرد تغير الوضع.
    </x-mail.text>

    <x-mail.button :url="route('products.index', ['locale' => 'ar'])">
        العودة إلى السوق
    </x-mail.button>

    <x-slot:footer>
        تصلك هذه الرسالة بعد طلبك حساباً مهنياً على حرفتي.<br>
        <a href="{{ route('products.index', ['locale' => 'ar']) }}" style="color:{{ config('mail_theme.soft') }};">السوق</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">طلباتي</a>
    </x-slot:footer>
</x-mail.layout>
