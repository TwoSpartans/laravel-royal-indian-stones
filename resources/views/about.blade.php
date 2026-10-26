@extends('layouts.site')

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

  <section class="about-hero" aria-labelledby="about-title">
    <div class="container about-hero-grid">
      <div class="about-hero-copy">
        <p class="eyebrow">About us</p>
        <h1 id="about-title">A Jodhpur stone house for buildings and landscapes.</h1>
        <p class="about-lead">We supply Indian sandstone, limestone, marble, granite, slate, and pebbles, cut to the size and finish the project needs, then checked and packed for shipment.</p>
        <div class="button-row">
          <a class="ris-button" href="{{ url('/products') }}">Explore the collection</a>
          <a class="ris-button ris-button-outline" href="{{ route('contact') }}" data-track="quote_click">Request a Quote</a>
        </div>
      </div>
      <figure class="about-hero-figure">
        <x-picture src="assets/img/projects/jodhpur-pink-sandstone-wall-cladding-building-construction-project.jpg" alt="Carved Jodhpur pink sandstone facade with a circular stone screen" width="800" height="600" sizes="(max-width: 991px) calc(100vw - 32px), min(680px, 46vw)" fetchpriority="high" />
        <figcaption>Jodhpur pink sandstone, carved for a building facade.</figcaption>
      </figure>
    </div>
  </section>

  <section class="about-facts" aria-label="Where we work">
    <div class="container">
      <dl class="about-facts-list">
        <div>
          <dt>Jodhpur</dt>
          <dd>Rajasthan, India</dd>
        </div>
        <div>
          <dt>Workshop</dt>
          <dd>Rajpurohit Bhawan, Golf Course Road</dd>
        </div>
        <div>
          <dt>Work</dt>
          <dd>Supplier, manufacturer, exporter</dd>
        </div>
        <div>
          <dt>Projects</dt>
          <dd>Two decades of work across countries</dd>
        </div>
      </dl>
    </div>
  </section>

  <section class="about-story" aria-labelledby="about-story-title">
    <div class="container about-story-grid">
      <figure>
        <x-picture src="assets/img/about.jpg" alt="Sample slabs of Indian granite, marble, and sandstone on a showroom wall" width="1025" height="768" loading="lazy" sizes="(max-width: 991px) calc(100vw - 32px), min(720px, 50vw)" />
        <figcaption>Sample stones, shown before an order is cut.</figcaption>
      </figure>
      <div class="about-story-copy">
        <p class="eyebrow">Who we are</p>
        <h2 id="about-story-title">Royal Indian Stones</h2>
        <p>We work from Jodhpur and supply natural stone for paving, cladding, flooring, and custom articles. The range runs from blocks, slabs, and tiles to cobbles, kerbs, pebbles, monuments, and landscaping pieces.</p>
        <blockquote class="about-pull">
          <p>Each order is matched for colour and finish, then checked and packed for shipment.</p>
        </blockquote>
        <p>Architects, builders, landscapers, and wholesalers stay with the same team from selection through delivery.</p>
        <p class="about-identity">Supplier · Manufacturer · Exporter</p>
        <a class="text-link" href="{{ url('/directors-message') }}">Read a note from the company →</a>
      </div>
    </div>
  </section>

  <section class="about-principles" aria-labelledby="about-principles-title">
    <div class="container">
      <div class="about-section-head">
        <p class="eyebrow">The work</p>
        <h2 id="about-principles-title">How the stone is handled</h2>
        <p>From the first sample to the crate that leaves Jodhpur, the order stays with one workshop.</p>
      </div>
      <div class="about-principle-grid">
        <article class="about-principle">
          <span class="step-number">01</span>
          <h3>Colour matched first</h3>
          <p>The colour and finish are agreed before the order is cut.</p>
        </article>
        <article class="about-principle">
          <span class="step-number">02</span>
          <h3>Cut to the project</h3>
          <p>Sizes follow the drawing, from tiles and slabs to custom articles.</p>
        </article>
        <article class="about-principle">
          <span class="step-number">03</span>
          <h3>Buildings and landscapes</h3>
          <p>Paving, cladding, flooring, and the articles a site still needs.</p>
        </article>
        <a class="about-principle" href="{{ url('/quality-control') }}" data-track="trust_click">
          <span class="step-number">04</span>
          <h3>Quality checks</h3>
          <p>Dimensions, finishes, and your specification are checked during production.</p>
          <span class="text-link">Our quality process →</span>
        </a>
        <a class="about-principle" href="{{ url('/packaging-type') }}" data-track="trust_click">
          <span class="step-number">05</span>
          <h3>Packed for the journey</h3>
          <p>Tiles, slabs, blocks, cobbles, and pebbles each have their own packing.</p>
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

  <section class="about-history" aria-labelledby="about-history-title">
    <div class="container">
      <div class="about-section-head">
        <p class="eyebrow">The path</p>
        <h2 id="about-history-title">From the quarry face to a finished building</h2>
      </div>
      <ol class="about-history-list">
        <li>
          <span class="about-history-mark">01</span>
          <h3>A stone house in Jodhpur</h3>
          <p>Royal Indian Stones works from Jodhpur, in the sandstone country of Rajasthan.</p>
        </li>
        <li>
          <span class="about-history-mark">02</span>
          <h3>Cut, finished, and packed</h3>
          <p>Production covers blocks, slabs, tiles, and the landscaping articles a project asks for.</p>
        </li>
        <li>
          <span class="about-history-mark">03</span>
          <h3>Delivered across countries</h3>
          <p>Residences, hotels, resorts, and landscaping work have left the workshop for more than two decades.</p>
        </li>
      </ol>
    </div>
  </section>

  <section class="about-feature" aria-labelledby="about-feature-title">
    <div class="container">
      <figure class="about-feature-frame">
        <x-picture src="assets/img/projects/jaisalmer-yellow-limestone-bush-hammer-finish-wall-cladding-project.jpg" alt="Jaisalmer yellow limestone around an arched entrance" width="800" height="600" loading="lazy" sizes="(max-width: 767px) calc(100vw - 32px), min(1440px, 92vw)" />
        <div class="about-feature-shade" aria-hidden="true"></div>
        <figcaption class="about-feature-caption">
          <div>
            <p class="eyebrow" id="about-feature-title">Stone in place</p>
            <p>Jaisalmer yellow limestone around an entrance.</p>
          </div>
          <a href="{{ url('/project-gallery') }}" data-track="trust_click">View the gallery →</a>
        </figcaption>
      </figure>
    </div>
  </section>

  <section class="about-steps" aria-labelledby="about-steps-title">
    <div class="container">
      <div class="about-section-head">
        <p class="eyebrow">The order</p>
        <h2 id="about-steps-title">Five steps from selection to dispatch</h2>
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

  <section class="about-after" aria-labelledby="about-after-title">
    <div class="container about-after-grid">
      <div>
        <p class="eyebrow">After delivery</p>
        <h2 id="about-after-title">After the crate leaves</h2>
        <p>Care and installation still matter once the stone is on site. These notes cover cleaning, sealing, and the handling we recommend.</p>
      </div>
      <div class="about-after-links">
        <a href="{{ url('/natural-stone-care') }}">Natural stone care <span aria-hidden="true">→</span></a>
        <a href="{{ url('/general-instruction') }}">General instruction <span aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

  <section class="about-close" aria-labelledby="about-close-title">
    <div class="container about-close-grid">
      <div>
        <p class="eyebrow">The workshop</p>
        <h2 id="about-close-title">Tell us the stone, the size, and the destination.</h2>
        <p>We will reply with a quotation for that order.</p>
        <address>
          Royal Indian Exports<br>
          Rajpurohit Bhawan, Plot-17, Golf Course Road<br>
          Air Force, Jodhpur-342011, Rajasthan, India<br>
          <a href="tel:+918769873114" data-track="phone_click">+91 876 987 3114</a>
        </address>
      </div>
      <a class="ris-button" href="{{ route('contact') }}" data-track="quote_click">Request a Quote →</a>
    </div>
  </section>
</main>
@endsection
