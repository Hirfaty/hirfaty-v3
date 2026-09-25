{{--
    Search and link-preview tags, built by App\Services\SeoService.
    Server-rendered on purpose — see that class for why they are not left to
    React, and why they carry no data-inertia attribute.
--}}
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
@if ($seo['indexable'])
<link rel="canonical" href="{{ $seo['canonical'] }}">
@endif

<meta property="og:site_name" content="{{ $seo['site_name'] }}">
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:locale" content="{{ $seo['locale'] }}">
@if ($seo['image'])
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:alt" content="{{ $seo['image_alt'] }}">
@endif
@foreach ($seo['meta'] as $property => $content)
<meta property="{{ $property }}" content="{{ $content }}">
@endforeach

<meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
@if ($seo['image'])
<meta name="twitter:image" content="{{ $seo['image'] }}">
@endif

@foreach ($seo['json_ld'] as $schema)
{{-- JSON_HEX_TAG turns < and > into < / >, so a product
     description containing "</script>" cannot break out of the tag. --}}
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
