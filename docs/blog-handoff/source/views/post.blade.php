@extends('layouts.site')

@section('content')
<main id="main">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <h2>{{ $post->title }}</h2>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          <li><a href="{{ url('/blog') }}">Blog</a></li>
          <li>{{ $post->title }}</li>
        </ol>
      </div>
    </div>
  </section>
  <div class="page-body">
  <section id="blog" class="blog">
    <div class="container">
      <article class="entry entry-single">
        @if($post->image)
          <div class="entry-img"><x-picture src="{{ $post->image }}" alt="{{ $post->title }}" loading="eager" sizes="(max-width: 991px) calc(100vw - 32px), min(1200px, 100vw)" /></div>
        @endif
        <h1 class="entry-title">{{ $post->title }}</h1>
        @if($post->published_on)
          <div class="entry-meta"><time datetime="{{ $post->published_on->toDateString() }}">{{ $post->published_on->format('j F Y') }}</time></div>
        @endif
        @php
          $studioBlocks = is_array($post->body_document['content'] ?? null) ? $post->body_document['content'] : [];
          $studioIsPlainText = $post->isStudio()
            && $studioBlocks !== []
            && collect($studioBlocks)->every(fn (mixed $block): bool => is_array($block) && in_array($block['type'] ?? '', ['paragraph', 'heading'], true));
          $showStudio = $post->isStudio() && is_array($post->body_document) && ! ($studioIsPlainText && str_contains((string) $post->body, '<img'));
        @endphp
        <div @class(['entry-content', 'rich-content' => $showStudio])>
          @if($showStudio)
            {!! app(\App\Services\RichContent\RichContentRenderer::class)->render($post) !!}
          @else
            {!! $post->body !!}
          @endif
        </div>
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
<script type="application/ld+json">{!! json_encode($articleSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
