@props(['post', 'wide' => false])

@php
    $imagePath = str_replace('\\', '/', (string) $post->image);
    $image = $imagePath !== '' && ! str_contains($imagePath, '..') && is_file(public_path($imagePath)) ? $imagePath : null;
    $alt = trim((string) $post->featured_image_alt);
    if ($alt === '' || strcasecmp($alt, (string) $post->title) === 0) {
        $alt = '';
    }
    $summary = trim((string) $post->summary);
    $sizes = $wide
        ? '(max-width: 767px) calc(100vw - 2rem), 46vw'
        : '(max-width: 767px) calc(100vw - 2rem), (max-width: 1099px) 46vw, 30vw';
@endphp

<article @class(['journal-card', 'journal-card-wide' => $wide])>
  <a class="journal-card-link" href="{{ url('/'.$post->slug) }}">
    <div class="journal-card-media">
      @if($image)
        <x-picture src="{{ $image }}" alt="{{ $alt }}" loading="lazy" sizes="{{ $sizes }}" />
      @endif
    </div>
    <div class="journal-card-body">
      @if($post->postCategory)
        <p class="journal-card-kicker">{{ $post->postCategory->name }}</p>
      @endif
      <h3>{{ $post->title }}</h3>
      @if($summary !== '')
        <p class="journal-card-excerpt">{{ $summary }}</p>
      @endif
      <p class="journal-card-meta"><span>{{ $post->readingMinutes() }} min read</span></p>
      <p class="journal-card-more">Read story <span aria-hidden="true">↗</span></p>
    </div>
  </a>
</article>
