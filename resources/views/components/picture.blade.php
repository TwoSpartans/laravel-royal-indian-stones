@if($fallback !== '')
<picture>
@if($avif)<source type="image/avif" srcset="{{ $avif }}" @if($sizes) sizes="{{ $sizes }}" @endif>@endif
@if($webp)<source type="image/webp" srcset="{{ $webp }}" @if($sizes) sizes="{{ $sizes }}" @endif>@endif
<img src="{{ $fallback }}" alt="{{ $alt }}" @if($width) width="{{ $width }}" @endif @if($height) height="{{ $height }}" @endif @if($class !== '') class="{{ $class }}" @endif @if($loading) loading="{{ $loading }}" @endif decoding="{{ $decoding }}" @if($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif>
</picture>
@endif
