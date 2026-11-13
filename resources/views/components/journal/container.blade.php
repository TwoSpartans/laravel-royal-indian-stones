@props([
  'as' => 'div',
])

<{{ $as }} {{ $attributes->merge(['class' => 'jn:mx-auto jn:box-content jn:max-w-[80rem] jn:px-6 jn:sm:px-8 jn:lg:px-10']) }}>
  {{ $slot }}
</{{ $as }}>
