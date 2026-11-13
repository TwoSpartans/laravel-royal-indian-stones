@props([
    'src',
    'alt' => '',
    'width' => null,
    'height' => null,
    'avifSrcset' => null,
    'webpSrcset' => null,
    'sizes' => null,
    'loading' => 'lazy',
    'fetchpriority' => null,
])

<picture>
    @if (filled($avifSrcset))
        <source type="image/avif" srcset="{{ $avifSrcset }}" @if (filled($sizes)) sizes="{{ $sizes }}" @endif>
    @endif
    @if (filled($webpSrcset))
        <source type="image/webp" srcset="{{ $webpSrcset }}" @if (filled($sizes)) sizes="{{ $sizes }}" @endif>
    @endif
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        @if ($width) width="{{ $width }}" @endif
        @if ($height) height="{{ $height }}" @endif
        @if (filled($sizes)) sizes="{{ $sizes }}" @endif
        loading="{{ $loading }}"
        decoding="async"
        @if ($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
        {{ $attributes }}
    >
</picture>
