@extends('layouts.site')

@section('content')
<main id="main">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <h2>Project Gallery</h2>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          <li>Project Gallery</li>
        </ol>
      </div>
    </div>
  </section>
  <div class="section-title">
    <h1>Project Gallery</h1>
    <p>Royal Indian Exports has successfully delivered a number of projects on time across different countries over the last two decades. Our projects include exclusive residences, hotels, resorts, landscaping elements, building renovation, and unique stone crafts.</p>
  </div>
  <section id="portfolio" class="portfolio">
    <div class="container">
      <div class="row portfolio-container">
        @foreach($projects as $project)
          <div class="col-lg-4 col-md-6 portfolio-item" id="project-{{ $project->position + 1 }}">
            <a href="{{ asset($project->lightbox ?: $project->image) }}" class="portfolio-lightbox" aria-label="View {{ $project->title }}">
              <x-picture src="{{ $project->image }}" class="img-fluid" alt="{{ $project->alt }}" :width="$project->width ? (string) $project->width : null" :height="$project->height ? (string) $project->height : null" loading="lazy" sizes="(max-width: 767px) calc(100vw - 32px), (max-width: 991px) calc(50vw - 16px), calc(33vw - 20px)" />
            </a>
            <div class="portfolio-info">
              <h4>{{ $project->title }}</h4>
              <a href="{{ asset($project->lightbox ?: $project->image) }}" data-gallery="portfolioGallery" class="portfolio-lightbox preview-link" title="{{ $project->title }}"><i class="bx bx-plus"></i></a>
              <a class="details-link" href="{{ route('contact', ['project' => $project->title]) }}" data-track="quote_click" aria-label="Discuss {{ $project->title }}"><i class="bx bx-link"></i></a>
            </div>
            @if($project->caption)
              <p class="project-caption">{{ $project->caption }} <a href="{{ route('contact', ['project' => $project->title]) }}" data-track="quote_click">Discuss a similar project →</a></p>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  </section>
</main>
@endsection
