{{-- $user --}}
<x-mail.layout
    locale="ar"
    title="تم تفعيل الحساب المهني — حرفتي"
    preheader="يستفيد حسابك الآن من أسعار الجملة.">

    <x-mail.eyebrow>تمت الموافقة على الطلب</x-mail.eyebrow>

    <x-mail.heading>أصبح حسابك حساباً مهنياً.</x-mail.heading>

    <x-mail.text>
        مرحباً {{ $user->first_name }}، تمت مراجعة طلبك والموافقة عليه من طرف فريقنا.
        يستفيد حسابك الآن من أسعار الجملة على حرفتي.
    </x-mail.text>

    <x-mail.note title="ما الذي يتغير">
        تظهر الأسعار المهنية تلقائياً على المنتجات المعنية.
        بعض المنتجات لها حد أدنى للطلب — وهو مذكور في صفحة كل منتج.
    </x-mail.note>

    <x-mail.button :url="route('products.index', ['locale' => 'ar'])">
        اكتشف أسعار الجملة
    </x-mail.button>

    <x-slot:footer>
        تصلك هذه الرسالة بعد طلبك حساباً مهنياً على حرفتي.<br>
        <a href="{{ route('products.index', ['locale' => 'ar']) }}" style="color:{{ config('mail_theme.soft') }};">السوق</a>
        &nbsp;·&nbsp;
        <a href="{{ config('app.url') }}/orders" style="color:{{ config('mail_theme.soft') }};">طلباتي</a>
    </x-slot:footer>
</x-mail.layout>
