@extends('layouts.site')

@section('content')
<main id="main" class="about-page">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <h2>About Us</h2>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          <li>About Us</li>
        </ol>
      </div>
    </div>
  </section>
  <section class="catalogue-intro" aria-labelledby="about-title">
    <div class="container">
      <p class="eyebrow">About us</p>
      <h1 id="about-title">A Jodhpur stone house for buildings and landscapes.</h1>
      <p class="about-lead">We supply Indian sandstone, limestone, marble, and granite, cut to the size and finish the project needs.</p>
      <div class="button-row">
        <a class="ris-button" href="{{ url('/products') }}">Explore the collection</a>
        <a class="ris-button ris-button-outline" href="{{ route('contact') }}" data-track="quote_click">Request a Quote</a>
      </div>
    </div>
  </section>
  <section class="about-story" aria-labelledby="about-story-title">
    <div class="container about-story-grid">
      <figure>
        <x-picture src="assets/img/about.jpg" alt="Sample slabs of Indian granite, marble, and sandstone on a showroom wall" width="1025" height="768" sizes="(max-width: 991px) calc(100vw - 32px), min(720px, 50vw)" />
        <figcaption>Sample stones, shown before an order is cut.</figcaption>
      </figure>
      <div class="about-story-copy">
        <p class="eyebrow">Who we are</p>
        <h2 id="about-story-title">Royal Indian Stones</h2>
        <p>We work from Jodhpur and supply natural stone for paving, cladding, flooring, and custom articles. Each order is matched for colour and finish, then checked and packed for shipment.</p>
        <p>Architects, builders, landscapers, and wholesalers stay with the same team from selection through delivery.</p>
      </div>
    </div>
  </section>
  <section class="home-process" aria-labelledby="about-work-title">
    <div class="container">
      <p class="eyebrow">How we work</p>
      <h2 id="about-work-title">From the quarry to your site</h2>
      <div class="process-grid about-work-grid">
        <a href="{{ url('/quality-control') }}" class="process-card" data-track="trust_click"><span class="step-number">01</span><h3>Quality checks</h3><p>See how stone dimensions, finishes, and your specification are checked during production.</p><span class="text-link">Our quality process →</span></a>
        <a href="{{ url('/project-gallery') }}" class="process-card" data-track="trust_click"><span class="step-number">02</span><h3>Projects already supplied</h3><p>Look at cladding, paving, and feature work already delivered.</p><span class="text-link">View the gallery →</span></a>
        <a href="{{ url('/packaging-type') }}" class="process-card" data-track="trust_click"><span class="step-number">03</span><h3>How stone is packed</h3><p>Explore packing for tiles, slabs, blocks, cobbles, and pebbles.</p><span class="text-link">View packaging →</span></a>
        <a href="{{ url('/directors-message') }}" class="process-card" data-track="trust_click"><span class="step-number">04</span><h3>A note from the company</h3><p>Read a short note from the people who run Royal Indian Stones.</p><span class="text-link">Read the note →</span></a>
      </div>
    </div>
  </section>
  <section class="enquiry-band">
    <div class="container">
      <div>
        <p class="eyebrow">Start an enquiry</p>
        <h2>Tell us the stone, the size, and the destination.</h2>
        <p>We will reply with a quotation for that order.</p>
      </div>
      <a class="ris-button" href="{{ route('contact') }}" data-track="quote_click">Request a Quote →</a>
    </div>
  </section>
</main>
@endsection
