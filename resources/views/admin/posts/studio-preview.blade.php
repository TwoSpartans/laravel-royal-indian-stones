<article class="studio-storefront">
  @if($post->image)
    <img src="{{ asset($post->image) }}" alt="{{ $post->featured_image_alt ?: $post->title }}">
  @endif
  <h1>{{ $post->title }}</h1>
  @if($post->published_on)
    <p>{{ $post->published_on->format('j F Y') }}</p>
  @endif
  <div>{!! $html !!}</div>
</article>
