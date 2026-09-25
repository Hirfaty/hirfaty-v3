{{-- $user, $url --}}
@php
    // المدة الحقيقية لصلاحية الرابط، بدل قيمة «60 دقيقة» مكتوبة يدوياً تصبح
    // خاطئة بمجرد تغيير الإعدادات.
    $expires = config('auth.passwords.users.expire', 60);
@endphp

<x-mail.layout
    locale="ar"
    title="إعادة تعيين كلمة المرور — حرفتي"
    preheader="ينتهي رابط إعادة التعيين خلال {{ $expires }} دقيقة.">

    <x-mail.eyebrow>أمان الحساب</x-mail.eyebrow>

    <x-mail.heading>إعادة تعيين كلمة المرور.</x-mail.heading>

    <x-mail.text>
        مرحباً {{ $user->first_name }}، تلقينا طلباً لإعادة تعيين كلمة المرور
        المرتبطة بحسابك على حرفتي.
        انقر على الزر أدناه لإنشاء كلمة مرور جديدة.
    </x-mail.text>

    <x-mail.button :url="$url">
        إعادة تعيين كلمة المرور
    </x-mail.button>

    <x-mail.text tight>
        ينتهي هذا الرابط خلال {{ $expires }} دقيقة.
    </x-mail.text>

    <x-mail.note>
        إذا لم تطلب إعادة تعيين كلمة المرور، تجاهل هذا البريد الإلكتروني.
        ستبقى كلمة مرورك دون تغيير وحسابك آمن.
    </x-mail.note>

    <x-mail.fallback-link
        :url="$url"
        label="إذا لم يعمل الزر، انسخ هذا الرابط في متصفحك:" />

    <x-slot:footer>
        <a href="{{ route('privacy', ['locale' => 'ar']) }}" style="color:{{ config('mail_theme.soft') }};">سياسة الخصوصية</a>
        &nbsp;·&nbsp;
        <a href="{{ route('terms', ['locale' => 'ar']) }}" style="color:{{ config('mail_theme.soft') }};">شروط الاستخدام</a>
    </x-slot:footer>
</x-mail.layout>
