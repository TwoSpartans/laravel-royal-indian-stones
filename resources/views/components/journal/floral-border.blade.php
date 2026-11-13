@props([
  'id' => 'journal-floral-border',
])

<svg {{ $attributes->merge(['class' => 'jn:block jn:h-6 jn:w-full jn:opacity-65']) }} aria-hidden="true" focusable="false">
  <defs>
    <pattern id="{{ $id }}" x="13.7" width="48" height="24" patternUnits="userSpaceOnUse">
      <image href="{{ asset('assets/img/journal/handoff/floral-motif.svg') }}" y="1.34" width="21.3255" height="21.3255" />
    </pattern>
  </defs>
  <rect width="100%" height="100%" fill="url(#{{ $id }})" />
</svg>
