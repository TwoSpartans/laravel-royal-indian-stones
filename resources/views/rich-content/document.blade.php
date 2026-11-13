@php
  $inline = app(\App\Services\RichContent\RichContentRenderer::class);
@endphp
@foreach($blocks as $block)
  @continue(! is_array($block))
  @php
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
    $frame = $frames->classes($attrs['frame'] ?? null);
  @endphp
  @switch($block['type'] ?? '')
    @case('paragraph')
      <p class="{{ $frame }}">{!! $inline->inline($block['content'] ?? []) !!}</p>
      @break
    @case('heading')
      @if((int) ($attrs['level'] ?? 2) === 3)
        <h3 class="{{ $frame }}">{!! $inline->inline($block['content'] ?? []) !!}</h3>
      @else
        <h2 class="{{ $frame }}">{!! $inline->inline($block['content'] ?? []) !!}</h2>
      @endif
      @break
    @case('image')
      @php $photo = $media->get((int) ($attrs['mediaId'] ?? 0)); @endphp
      @if($photo)
        <figure class="{{ $frame }}">
          <x-picture src="{{ $photo->path }}" alt="{{ $attrs['decorative'] ?? false ? '' : ($attrs['alt'] ?? $photo->alt) }}" loading="lazy" sizes="(max-width: 767px) calc(100vw - 32px), min(960px, 100vw)" />
          @if(filled($attrs['caption'] ?? null))<figcaption>{{ $attrs['caption'] }}</figcaption>@endif
          @if(filled($attrs['credit'] ?? null))<figcaption>{{ $attrs['credit'] }}</figcaption>@endif
        </figure>
      @else
        <figure class="{{ $frame }}"><p>This photograph is unavailable.</p></figure>
      @endif
      @break
    @case('gallery')
      <div class="rich-gallery {{ $frame }} {{ ($attrs['mode'] ?? 'grid') === 'carousel' ? 'is-carousel' : 'is-grid' }}">
        @if(($attrs['items'] ?? []) === [])
          <p>Add photographs in the Block settings.</p>
        @endif
        @foreach($attrs['items'] ?? [] as $item)
          @php $photo = is_array($item) ? $media->get((int) ($item['mediaId'] ?? 0)) : null; @endphp
          @if($photo)
            <x-picture src="{{ $photo->path }}" alt="{{ ($item['decorative'] ?? false) ? '' : ($item['alt'] ?? '') }}" loading="lazy" sizes="(max-width: 767px) calc(100vw - 32px), calc(50vw - 24px)" />
          @else
            <span class="rich-gallery-empty"></span>
          @endif
        @endforeach
      </div>
      @break
    @case('productSpotlight')
      @php $stone = $products->get((int) ($attrs['productId'] ?? 0)); @endphp
      @if($stone && $stone->is_published)
        <aside class="rich-stone {{ $frame }} is-{{ $attrs['presentation']['layout'] ?? 'image-left' }}">
          @if($stone->catalogueImage())
            <x-picture src="{{ $stone->catalogueImage()->path }}" alt="{{ $stone->catalogueImage()->alt ?: $stone->name }}" loading="lazy" sizes="(max-width: 767px) calc(100vw - 32px), 40vw" />
          @endif
          <div>
            <p class="eyebrow">{{ $attrs['editorial']['eyebrow'] ?? $stone->category?->name }}</p>
            <h2>{{ $stone->name }}</h2>
            @if(($attrs['presentation']['showPrice'] ?? true))<p>Price on request.</p>@endif
            @if(filled($attrs['editorial']['summary'] ?? null))<p>{{ $attrs['editorial']['summary'] }}</p>@endif
            <a href="{{ url('/'.$stone->slug) }}">{{ $attrs['presentation']['ctaLabel'] ?? 'View stone' }}</a>
          </div>
        </aside>
      @else
        <aside class="rich-stone is-unavailable {{ $frame }}">
          <p>{{ $stone ? 'This stone is not available to view.' : 'This stone could not be found.' }}</p>
        </aside>
      @endif
      @break
    @case('workshopStory')
      <section class="rich-quarry {{ $frame }}">
        @if(filled($attrs['processStage'] ?? null))<p class="eyebrow">{{ ucfirst($attrs['processStage']) }}</p>@endif
        @if(filled($attrs['title'] ?? null))<h2>{{ $attrs['title'] }}</h2>@endif
        @if(filled($attrs['body'] ?? null))<p>{{ $attrs['body'] }}</p>@endif
        @foreach($attrs['mediaIds'] ?? [] as $id)
          @php $photo = $media->get((int) $id); @endphp
          @if($photo)<x-picture src="{{ $photo->path }}" alt="{{ $photo->alt }}" loading="lazy" sizes="(max-width: 767px) calc(100vw - 32px), min(960px, 100vw)" />@endif
        @endforeach
      </section>
      @break
    @case('cta')
      <aside class="rich-cta {{ $frame }}">
        @if(filled($attrs['title'] ?? null))<h2>{{ $attrs['title'] }}</h2>@endif
        @if(filled($attrs['body'] ?? null))<p>{{ $attrs['body'] }}</p>@endif
        @if(filled($attrs['buttonText'] ?? null) && filled($attrs['buttonUrl'] ?? null))
          <a class="ris-button" href="{{ $attrs['buttonUrl'] }}">{{ $attrs['buttonText'] }}</a>
        @endif
      </aside>
      @break
  @endswitch
@endforeach
