{{-- Google Analytics (GA4). Off unless GOOGLE_ANALYTICS_ID is set, and never
     on the staging host (see config/services.php).

     Loaded once, with the full page. Inertia's later visits change the URL
     through the History API rather than reloading, and GA4's enhanced
     measurement ("page changes based on browser history events", on by
     default) records each of those as its own page_view — so nothing needs
     to call gtag on navigation. --}}
@php
    $gaId = config('services.google_analytics.measurement_id');
@endphp
@if (filled($gaId) && request()->getHost() !== config('hirfaty.staging_host'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($gaId) }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($gaId));
    </script>
@endif
