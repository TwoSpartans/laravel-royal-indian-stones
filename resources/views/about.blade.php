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
        <h1 id="about-story-title">Royal Indian Exports</h1>
        <p>Royal Indian Stones is part of the Royal Indian Exports Group. We are a certified manufacturer and exporter, and the stone is cut in our own factory.</p>
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
      <div class="about-section-head about-company-head">
        <p class="eyebrow" id="about-principles-title">The company</p>
        <p>Royal Indian Stones, part of the Royal Indian Exports Group, has grown to become India's largest professional certified manufacturer, exporter, importer, buyer, wholesaler, trader, and factory owner of natural stones. We supply high-quality Indian natural stone products, suitable for various applications to our valued clients worldwide at a competitive price and in a short lead time. All of our products are customized to meet the needs and requirements of our clients.</p>
      </div>
      <div class="about-principle-grid about-company-grid">
        <article class="about-principle">
          <h3>Quality &amp; Dedicated Services</h3>
          <p>We are recognized for our innovative natural stone products, high quality, and dedicated service. Our quality control team individually selects all of the stones to ensure consistency and quality. A dedicated team of testers and quality assurance specialists who provide long-term project testing and focus on task completion success.</p>
        </article>
        <article class="about-principle">
          <h3>Satisfied Overseas Clients</h3>
          <p>Our satisfied overseas clients with our quality, price, and service are the driving force behind our continued growth and strength. We supply wholesalers, architects, builders, landscapers, and large-scale projects. Long-term relationships with both domestic and international clients are critical to business success.</p>
        </article>
        <article class="about-principle">
          <h3>Close Ties with Suppliers</h3>
          <p>Because of our strong relationships with Indian suppliers, we can guarantee the best material will be available on carefully planned delivery schedules. By allowing suppliers to access inventory information, we are able to keep sufficient supplies of popular stones on hand and maintain just-in-time production schedules without sacrificing quality standards.</p>
        </article>
        <article class="about-principle">
          <h3>Large Team of Professionals</h3>
          <p>The Royal Indian Exports Group has a large team of professionals who handle the processing, dressing, inspection, and shipment of natural stones. As a company succeeds and grows, we have learned through extensive experience how to effectively manage large teams as manpower increases.</p>
        </article>
      </div>
      <div class="about-company-note">
        <h2>Quality Stones at Competitive Price</h2>
        <p>The company offers a wide variety of stone products to all the customers and supply best stones at the lowest rates to all our national and international buyers. Our extensive knowledge of different quarries of natural stones of the whole of India enables us to supply quality stones at competitive price.</p>
      </div>
    </div>
  </section>
</main>
@endsection
