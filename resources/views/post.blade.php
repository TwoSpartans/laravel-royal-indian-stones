@extends('layouts.site')

@section('content')
<main id="main">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <h2>Blogs</h2>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          <li><a href="{{ url('/blog') }}">Blogs</a></li>
        </ol>
      </div>
    </div>
  </section>
  <div class="page-body">
  <section id="blog" class="blog">
    <div class="container">
      <article class="entry entry-single">
        <h1 class="entry-title">{{ $post->title }}</h1>
        <div class="entry-meta">
          <span>By Royal Indian Stones</span>
          @if($post->published_on)
            <span aria-hidden="true"> · </span>
            <time datetime="{{ $post->published_on->toDateString() }}">{{ $post->published_on->format('j F Y') }}</time>
          @endif
        </div>
        @if($post->image)
          <div class="entry-img"><x-picture src="{{ $post->image }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="eager" sizes="(max-width: 1440px) calc(100vw - 24px), 1416px" /></div>
        @endif
        @php
          $showStudio = $post->isStudio()
            && is_array($post->body_document)
            && app(\App\Services\RichContent\QualityChecker::class)->hasVisibleContent($post->body_document);
        @endphp
        <div @class(['entry-content', 'rich-content' => $showStudio])>
          @if($showStudio)
            {!! app(\App\Services\RichContent\RichContentRenderer::class)->render($post) !!}
          @else
            {!! $post->body !!}
          @endif
        </div>
        @if(is_array($post->tags) && $post->tags !== [])
          <p class="entry-tags">
            @foreach($post->tags as $tag)
              <span>#{{ $tag }}</span>
            @endforeach
          </p>
        @endif
        <aside class="entry-stories">
          <section aria-labelledby="recent-posts-title">
            <h2 id="recent-posts-title">Recent posts</h2>
            @if(($recent ?? collect())->isEmpty())
              <p class="entry-stories-empty">No recent posts yet.</p>
            @else
              <ul class="entry-story-list">
                @foreach($recent as $recentPost)
                  <li>
                    <a class="entry-story" href="{{ url('/'.$recentPost->slug) }}">
                      @php
                        $recentImage = str_replace('\\', '/', (string) $recentPost->image);
                        $recentImage = $recentImage !== '' && ! str_contains($recentImage, '..') && is_file(public_path($recentImage)) ? $recentImage : null;
                      @endphp
                      @if($recentImage)
                        <img src="{{ asset($recentImage) }}" alt="" width="112" height="84" loading="lazy" decoding="async">
                      @else
                        <span class="entry-story-mark" aria-hidden="true"></span>
                      @endif
                      <span class="entry-story-copy">
                        <span class="entry-story-title">{{ $recentPost->title }}</span>
                        @if($recentPost->published_on)
                          <time datetime="{{ $recentPost->published_on->toDateString() }}">{{ $recentPost->published_on->format('j M Y') }}</time>
                        @endif
                      </span>
                    </a>
                  </li>
                @endforeach
              </ul>
            @endif
          </section>
          <section aria-labelledby="related-posts-title">
            <h2 id="related-posts-title">Related blogs</h2>
            @if(($related ?? collect())->isEmpty())
              <p class="entry-stories-empty">No related blogs found.</p>
            @else
              <ul class="entry-story-list">
                @foreach($related as $relatedPost)
                  <li>
                    <a class="entry-story" href="{{ url('/'.$relatedPost->slug) }}">
                      @php
                        $relatedImage = str_replace('\\', '/', (string) $relatedPost->image);
                        $relatedImage = $relatedImage !== '' && ! str_contains($relatedImage, '..') && is_file(public_path($relatedImage)) ? $relatedImage : null;
                      @endphp
                      @if($relatedImage)
                        <img src="{{ asset($relatedImage) }}" alt="" width="112" height="84" loading="lazy" decoding="async">
                      @else
                        <span class="entry-story-mark" aria-hidden="true"></span>
                      @endif
                      <span class="entry-story-copy">
                        <span class="entry-story-title">{{ $relatedPost->title }}</span>
                        @if($relatedPost->published_on)
                          <time datetime="{{ $relatedPost->published_on->toDateString() }}">{{ $relatedPost->published_on->format('j M Y') }}</time>
                        @endif
                      </span>
                    </a>
                  </li>
                @endforeach
              </ul>
            @endif
          </section>
        </aside>
        @unless($preview ?? false)
        @if($post->comments->isNotEmpty())
          <h2>Comments</h2>
          @foreach($post->comments as $comment)
            <p><strong>{{ $comment->name }}</strong><br>{{ $comment->body }}</p>
          @endforeach
        @endif
        <h2>Leave a comment</h2>
        <form method="post" action="{{ route('comments.store', $post->slug) }}" class="quote-form">
          @csrf
          <div class="form-grid">
            <div><label for="comment_name">Name</label><input id="comment_name" name="name" required maxlength="120" value="{{ old('name') }}"></div>
            <div><label for="comment_email">Email</label><input id="comment_email" name="email" type="email" required maxlength="254" value="{{ old('email') }}"></div>
            <div class="full-width"><label for="comment_body">Comment</label><textarea id="comment_body" name="body" rows="4" required minlength="5" maxlength="2000">{{ old('body') }}</textarea></div>
          </div>
          @if($errors->any())<p class="form-status is-error">{{ $errors->first() }}</p>@endif
          @if(session('comment_status'))<p class="form-status is-success">{{ session('comment_status') }}</p>@endif
          <button class="ris-button" type="submit">Submit comment</button>
        </form>
        @endunless
      </article>
    </div>
  </section>
  </div>
</main>
@endsection

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@if(!empty($articleSchema))
<script type="application/ld+json">{!! json_encode($articleSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endif
@endpush
