@component('emails.layout', ['title' => 'تم تفعيل الحساب المهني — حرفتي', 'lang' => 'ar', 'dir' => 'rtl'])
    <span class="badge badge-ok">✓ تمت الموافقة على الطلب</span>

    <p class="eyebrow">مرحباً بك ضمن المحترفين</p>

    <h1 class="headline">
        أصبح حسابك<br>
        <em>حساباً مهنياً</em>.
    </h1>

    <p class="body-text">
        مرحباً {{ $user->first_name }}، تمت مراجعة طلبك والموافقة عليه من طرف فريقنا.
        يستفيد حسابك الآن من أسعار الجملة على حرفتي.
    </p>

    <div class="tip-box">
        <p>
            <strong>ما الذي يتغير:</strong> تظهر الأسعار المهنية تلقائياً على المنتجات المعنية.
            بعض المنتجات لها حد أدنى للطلب — وهو مذكور في صفحة كل منتج.
        </p>
    </div>

    <div class="cta-wrap">
        <a href="{{ config('app.url') }}/products" class="cta-btn">
            اكتشف أسعار الجملة ←
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
