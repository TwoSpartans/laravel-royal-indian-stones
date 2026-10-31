@extends('layouts.site')

@push('head')
@php
    $aboutPage = [
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        'url' => url('/aboutus'),
        'name' => 'About Royal Indian Stones',
        'description' => 'Royal Indian Stones is part of the Royal Indian Exports Group, a certified manufacturer, exporter, and factory owner of natural stone.',
        'mainEntity' => [
            '@type' => 'Organization',
            'name' => 'Royal Indian Stones',
            'url' => url('/'),
            'parentOrganization' => [
                '@type' => 'Organization',
                'name' => 'Royal Indian Exports',
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($aboutPage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
<main id="main" class="about-page">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <p class="about-crumb">About Us</p>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          <li>About Us</li>
        </ol>
      </div>
    </div>
  </section>

  <section class="about-story" aria-labelledby="about-story-title">
    <div class="container about-story-grid">
      <figure>
        <x-picture src="assets/img/about.jpg" alt="Sample slabs of Indian granite, marble, and sandstone on a showroom wall" width="1025" height="768" sizes="(max-width: 991px) calc(100vw - 32px), min(720px, 50vw)" fetchpriority="high" />
        <figcaption>Sample stones, shown before an order is cut.</figcaption>
      </figure>
      <div class="about-story-copy">
        <p class="eyebrow">Who we are</p>
        <h1 id="about-story-title">Royal Indian Stones</h1>
        <p>Royal Indian Stones is part of the Royal Indian Exports Group. We are a certified manufacturer and exporter, and the stone is cut in our own factory. We also buy, import, and wholesale it for clients in India and overseas.</p>
        <blockquote class="about-pull">
          <p>Each order is made to the sizes and finish the project needs, at a competitive price and on a short lead time.</p>
        </blockquote>
        <p>Wholesalers, architects, builders, and landscapers use the stone for paving, cladding, flooring, and large sites. They stay because the quality, the price, and the service hold from one order to the next.</p>
        <p class="about-identity">Manufacturer · Exporter · Factory</p>
        <a class="text-link" href="{{ url('/directors-message') }}">Read a note from the company →</a>
      </div>
    </div>
  </section>

  <section class="about-facts" aria-label="The company">
    <div class="container">
      <dl class="about-facts-list">
        <div>
          <dt>Group</dt>
          <dd>Royal Indian Exports</dd>
        </div>
        <div>
          <dt>Workshop</dt>
          <dd>Rajpurohit Bhawan, Golf Course Road</dd>
        </div>
        <div>
          <dt>Work</dt>
          <dd>Manufacture, wholesale, and export</dd>
        </div>
        <div>
          <dt>Supply</dt>
          <dd>Quarries across India</dd>
        </div>
      </dl>
    </div>
  </section>

  <section class="about-principles" aria-labelledby="about-principles-title">
    <div class="container">
      <div class="about-section-head">
        <p class="eyebrow">The company</p>
        <h2 id="about-principles-title">What the work rests on</h2>
        <p>Stone chosen by hand, quarries we already know, and a team that finishes the order.</p>
      </div>
      <div class="about-principle-grid">
        <a class="about-principle" href="{{ url('/quality-control') }}" data-track="trust_click">
          <span class="step-number">01</span>
          <h3>Quality checks</h3>
          <p>The quality team selects each stone so the colour and finish stay consistent. On a long project, testers stay with the work until it matches the specification.</p>
          <span class="text-link">Our quality process →</span>
        </a>
        <article class="about-principle">
          <span class="step-number">02</span>
          <h3>Clients who return</h3>
          <p>Wholesalers, architects, builders, and landscapers, in India and overseas, are why the company has kept growing. Those relationships are meant to last.</p>
        </article>
        <article class="about-principle">
          <span class="step-number">03</span>
          <h3>Quarries we know</h3>
          <p>Suppliers across India keep material on a planned schedule. Popular stones stay in hand, and production follows the order.</p>
        </article>
        <article class="about-principle">
          <span class="step-number">04</span>
          <h3>A workshop team</h3>
          <p>A large team handles processing, dressing, inspection, and shipment. The group has learned how to keep that team working as the orders have grown.</p>
        </article>
        <a class="about-principle" href="{{ url('/packaging-type') }}" data-track="trust_click">
          <span class="step-number">05</span>
          <h3>Packed for the journey</h3>
          <p>Tiles, slabs, blocks, cobbles, and pebbles each leave in their own packing.</p>
          <span class="text-link">View packaging →</span>
        </a>
        <a class="about-principle" href="{{ url('/project-gallery') }}" data-track="trust_click">
          <span class="step-number">06</span>
          <h3>Seen on real buildings</h3>
          <p>Cladding, paving, and feature work already delivered.</p>
          <span class="text-link">View the gallery →</span>
        </a>
      </div>
    </div>
  </section>

  <section class="about-steps" aria-labelledby="about-steps-title">
    <div class="container">
      <div class="about-section-head">
        <p class="eyebrow">The order</p>
        <h2 id="about-steps-title">Five steps from selection to dispatch</h2>
        <p>Processing, dressing, inspection, and shipment stay in the same workshop.</p>
      </div>
      <ol class="about-steps-list">
        <li class="about-step">
          <span class="step-number">01</span>
          <h3>Selection</h3>
          <p>Stone, colour, and finish are chosen for the project.</p>
        </li>
        <li class="about-step">
          <span class="step-number">02</span>
          <h3>Cutting</h3>
          <p>The order is cut to the size and surface the drawing asks for.</p>
        </li>
        <li class="about-step">
          <span class="step-number">03</span>
          <h3>Checks</h3>
          <p>Dimensions and finish are checked against the specification.</p>
          <a class="text-link" href="{{ url('/quality-control') }}" data-track="trust_click">Our quality process →</a>
        </li>
        <li class="about-step">
          <span class="step-number">04</span>
          <h3>Packing</h3>
          <p>Crates and bundles are prepared for tiles, slabs, blocks, cobbles, and pebbles.</p>
          <a class="text-link" href="{{ url('/packaging-type') }}" data-track="trust_click">View packaging →</a>
        </li>
        <li class="about-step">
          <span class="step-number">05</span>
          <h3>Dispatch</h3>
          <p>The destination is named on the enquiry, and the stone leaves for that site.</p>
        </li>
      </ol>
    </div>
  </section>

  <section class="about-close" aria-labelledby="about-close-title">
    <div class="container about-close-grid">
      <div>
        <p class="eyebrow">The range</p>
        <h2 id="about-close-title">Quality stone at a competitive price</h2>
        <p>A wide range of Indian natural stone, drawn from quarries across the country, for buyers at home and overseas.</p>
      </div>
      <a class="ris-button" href="{{ url('/contact') }}" data-track="quote_click">Request a quote</a>
    </div>
  </section>
</main>
@endsection
