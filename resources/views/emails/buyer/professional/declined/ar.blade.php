@component('emails.layout', ['title' => 'طلب حسابك المهني — حرفتي', 'lang' => 'ar', 'dir' => 'rtl'])
    <span class="badge badge-info">تمت مراجعة الطلب</span>

    <p class="eyebrow">طلب الحساب المهني</p>

    <h1 class="headline">
        لم نتمكن من قبوله<br>
        <em>في الوقت الحالي</em>.
    </h1>

    <p class="body-text">
        مرحباً {{ $user->first_name }}، راجع فريقنا طلبك للحصول على حساب مهني،
        ولم نتمكن من قبوله بصيغته الحالية.
    </p>

    <div class="tip-box">
        <p>
            <strong>السبب:</strong> {{ $reason }}
        </p>
    </div>

    <p class="body-text">
        يبقى حسابك نشطاً بالكامل ويمكنك مواصلة الطلب كالمعتاد.
        ويمكنك تقديم طلب جديد بمجرد تغير الوضع.
    </p>

    <div class="cta-wrap">
        <a href="{{ config('app.url') }}/products" class="cta-btn">
            العودة إلى السوق ←
        </a>
    </div>

    @slot('footer')
        <p>تصلك هذه الرسالة بعد طلبك حساباً مهنياً على حرفتي.</p>
        <p>
            © {{ date('Y') }} حرفتي — منصة الحرفيين المغاربة.<br>
            <a href="{{ config('app.url') }}/products">السوق</a>
            &nbsp;·&nbsp;
            <a href="{{ config('app.url') }}/orders">طلباتي</a>
        </p>
    @endslot
@endcomponent
