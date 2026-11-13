@props([
  'eyebrow',
  'title',
  'id',
])

<div {{ $attributes->merge(['class' => 'jn:flex jn:flex-col jn:gap-4 jn:sm:flex-row jn:sm:items-end jn:sm:justify-between jn:sm:gap-8']) }}>
  <div class="jn:flex jn:max-w-[63.75rem] jn:flex-col jn:gap-2">
    <p class="jn:text-xs jn:font-medium jn:uppercase jn:leading-4 jn:tracking-[0.14em] jn:text-journal-gold-text">{{ $eyebrow }}</p>
    <h2 id="{{ $id }}" class="jn:font-brand jn:text-[2.75rem] jn:font-medium jn:leading-[1.04] jn:text-journal-indigo jn:text-balance jn:min-[375px]:text-[3.25rem] jn:lg:text-[3.125rem]">{{ $title }}</h2>
  </div>

  @isset($action)
    <div class="jn:shrink-0 jn:sm:pb-2">{{ $action }}</div>
  @endisset
</div>
