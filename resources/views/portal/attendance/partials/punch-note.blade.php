@php
  $kind = $punch->punch_type === 'in' ? 'In' : ($punch->punch_type === 'out' ? 'Out' : str_replace('_', ' ', $punch->punch_type));
  $tone = $punch->punch_type === 'in' ? 'good' : 'warn';
@endphp
<article class="punch-note">
  <p class="punch-line">
    <span class="badge badge-{{ $tone }}">{{ $kind }}</span>
    <strong>{{ $punch->user?->name ?? ($personName ?? '') }}</strong>
    <span class="muted">{{ \App\Support\PortalTime::format($punch->punched_at, $punch->user, $punch->timezone) }}</span>
    @if ($punch->is_correction)<span class="badge">Correction</span>@endif
    @if ($punch->superseded_at)<span class="badge">Replaced</span>@endif
  </p>
  @include('portal.attendance.partials.shift-notes', ['punch' => $punch])
</article>
