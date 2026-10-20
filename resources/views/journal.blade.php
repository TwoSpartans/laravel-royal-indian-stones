@extends('layouts.site')

@section('content')
<main id="main">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <h2>Blog</h2>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          <li>Blog</li>
        </ol>
      </div>
    </div>
  </section>
  <section id="blog" class="blog">
    <div class="container">
      <div class="section-title"><h1>Blog</h1></div>
      <div class="row">
        @foreach($posts as $post)
          <div class="col-lg-6">
            <article class="entry">
              @if($post->image)
                <div class="entry-img"><a href="{{ url('/'.$post->slug) }}"><x-picture src="{{ $post->image }}" alt="{{ $post->title }}" loading="lazy" sizes="(max-width: 991px) calc(100vw - 32px), calc(50vw - 24px)" /></a></div>
              @endif
              <h2 class="entry-title"><a href="{{ url('/'.$post->slug) }}">{{ $post->title }}</a></h2>
              @if($post->postCategory)
                <p class="eyebrow">{{ $post->postCategory->name }}</p>
              @endif
              @if($post->published_on)
                <div class="entry-meta"><time datetime="{{ $post->published_on->toDateString() }}">{{ $post->published_on->format('j F Y') }}</time></div>
              @endif
              @if($post->summary)
                <div class="entry-content"><p>{{ $post->summary }}</p><a href="{{ url('/'.$post->slug) }}">Read more</a></div>
              @endif
            </article>
          </div>
        @endforeach
      </div>
    </div>
  </section>
</main>
@endsection
