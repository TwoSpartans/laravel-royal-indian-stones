@props([
  'href',
  'current' => false,
])

<a
  href="{{ $href }}"
  @if ($current) aria-current="page" @endif
  {{ $attributes->class([
    'group/topic inline-flex min-h-11 min-w-[5.875rem] flex-col justify-center gap-2 py-2 text-sm font-medium leading-5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-journal-maroon motion-reduce:transition-none xl:min-w-[8.125rem]',
    'text-journal-maroon' => $current,
    'text-journal-muted hover:text-journal-maroon' => ! $current,
  ]) }}
>
  <span>{{ $slot }}</span>
  <span @class([
    'h-px w-[4.75rem] max-w-full',
    'bg-journal-maroon' => $current,
    'bg-transparent group-hover/topic:bg-journal-line' => ! $current,
  ]) aria-hidden="true"></span>
</a>
