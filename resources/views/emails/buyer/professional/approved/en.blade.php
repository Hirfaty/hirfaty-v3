@component('emails.layout', ['title' => 'Professional account active — Hirfaty', 'lang' => 'en', 'dir' => 'ltr'])
    <span class="badge badge-ok">✓ Request approved</span>

    <p class="eyebrow">Welcome to the trade</p>

    <h1 class="headline">
        Your account is now<br>
        <em>professional</em>.
    </h1>

    <p class="body-text">
        Hello {{ $user->first_name }}, your request has been reviewed and approved by our team.
        Your account now gets wholesale pricing across Hirfaty.
    </p>

    <div class="tip-box">
        <p>
            <strong>What changes:</strong> professional prices now show automatically on the
            products that have them. Some items carry a minimum order quantity — it's shown
            on each product page.
        </p>
    </div>

    <div class="cta-wrap">
        <a href="{{ config('app.url') }}/products" class="cta-btn">
            Browse trade pricing →
        </a>
    </div>

    @slot('footer')
        <p>You're receiving this email because you requested a professional account on Hirfaty.</p>
        <p>
            © {{ date('Y') }} Hirfaty — The home of Moroccan artisans.<br>
            <a href="{{ config('app.url') }}/products">The market</a>
            &nbsp;·&nbsp;
            <a href="{{ config('app.url') }}/orders">My orders</a>
        </p>
    @endslot
@endcomponent
