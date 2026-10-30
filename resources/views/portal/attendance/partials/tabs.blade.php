<nav class="attendance-tabs" aria-label="Attendance sections">
  <a href="{{ route('portal.attendance.index', ['date' => $date ?? now()->toDateString()]) }}" @if(request()->routeIs('portal.attendance.index')) aria-current="page" @endif>Daily overview</a>
  <a href="{{ route('portal.attendance.report', array_filter(['user_id' => $selected?->id ?? ($person->id ?? null), 'range' => $range ?? '30'])) }}" @if(request()->routeIs('portal.attendance.report')) aria-current="page" @endif>Employee report</a>
  <a href="{{ route('portal.attendance.compare', ['range' => $range ?? '30']) }}" @if(request()->routeIs('portal.attendance.compare')) aria-current="page" @endif>Team comparison</a>
  <a href="{{ route('portal.attendance.exceptions') }}" @if(request()->routeIs('portal.attendance.exceptions')) aria-current="page" @endif>Exceptions</a>
</nav>
