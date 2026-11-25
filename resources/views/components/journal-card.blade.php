@props(['post', 'wide' => false])

@php
    $imagePath = str_replace('\\', '/', (string) $post->image);
    $image = $imagePath !== '' && ! str_contains($imagePath, '..') && is_file(public_path($imagePath)) ? $imagePath : null;
    $alt = trim((string) $post->featured_image_alt);
    if ($alt === '' || strcasecmp($alt, (string) $post->title) === 0) {
        $alt = '';
    }
    $summary = trim(preg_replace('/\s+/u', ' ', (string) $post->summary) ?? '');
    if ($summary === '') {
        $summary = trim(preg_replace('/\s+/u', ' ', (string) $post->meta_description) ?? '');
    }
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
      <h3 class="journal-card-title"><span class="journal-card-title-text">{{ $post->title }}</span><span class="journal-card-ellipsis" hidden>...</span></h3>
      @if($summary !== '')
        <p class="journal-card-excerpt"><span class="journal-card-excerpt-text">{{ $summary }}</span><span class="journal-card-read"><span class="journal-card-read-ellipsis">...</span><span class="journal-card-read-label"> Read Story</span><span class="journal-arrow" aria-hidden="true">↗</span></span></p>
      @else
        <p class="journal-card-read journal-card-read-solo"><span class="journal-card-read-label">Read Story</span><span class="journal-arrow" aria-hidden="true">↗</span></p>
      @endif
      @if($post->published_on)
        <p class="journal-card-date"><time datetime="{{ $post->published_on->toDateString() }}">{{ $post->published_on->format('j M Y') }}</time></p>
      @endif
    </div>
  </a>
</article>
