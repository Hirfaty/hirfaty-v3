@component('emails.layout', ['title' => 'Your professional account request — Hirfaty', 'lang' => 'en', 'dir' => 'ltr'])
    <span class="badge badge-info">Request reviewed</span>

    <p class="eyebrow">Your professional account request</p>

    <h1 class="headline">
        We can't approve it<br>
        <em>just yet</em>.
    </h1>

    <p class="body-text">
        Hello {{ $user->first_name }}, our team has reviewed your professional account
        request. We weren't able to approve it as it stands.
    </p>

    <div class="tip-box">
        <p>
            <strong>Reason:</strong> {{ $reason }}
        </p>
    </div>

    <p class="body-text">
        Your account stays fully active and you can carry on ordering as normal. You're
        welcome to submit a new request once things change.
    </p>

    <div class="cta-wrap">
        <a href="{{ config('app.url') }}/products" class="cta-btn">
            Back to the market →
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
