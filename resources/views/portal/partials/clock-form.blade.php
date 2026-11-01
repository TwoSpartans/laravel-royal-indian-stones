@php
  $openPunch = $lastPunch?->punch_type;
  $clockedIn = in_array($openPunch, ['in', 'break_start', 'break_end'], true);
  $type = $clockedIn ? 'out' : 'in';
  $restore = old('punch_type') === $type;
@endphp
<form method="post" action="{{ route('portal.attendance.clock') }}" class="shift-form">
  @csrf
  <input type="hidden" name="punch_type" value="{{ $type }}">
  <h3>{{ $clockedIn ? 'Clock out' : 'Clock in' }}</h3>
  <p class="help">{{ $clockedIn ? 'Fill in each section. The clock stops when you submit.' : 'Fill in each section. The clock starts when you submit.' }}</p>
  @if (in_array($openPunch, ['break_start', 'break_end'], true))
    <p class="help">Breaks are no longer recorded. Clock out when the shift ends.</p>
  @endif
  <div class="field shift-section">
    <label for="working_on">What are you working on</label>
    <textarea id="working_on" name="working_on" required maxlength="2000">{{ $restore ? old('working_on') : '' }}</textarea>
  </div>
  <div class="field shift-section">
    <label for="completed_work">{{ $clockedIn ? 'What have you done' : 'What did you do yesterday' }}</label>
    <textarea id="completed_work" name="completed_work" required maxlength="2000">{{ $restore ? old('completed_work') : '' }}</textarea>
  </div>
  <div class="field shift-section">
    <label for="planned_work">{{ $clockedIn ? 'What will you be doing tomorrow' : 'What will you work on today' }}</label>
    <textarea id="planned_work" name="planned_work" required maxlength="2000">{{ $restore ? old('planned_work') : '' }}</textarea>
  </div>
  <button class="clock-btn" type="submit">{{ $clockedIn ? 'Clock out' : 'Clock in' }}</button>
  <p class="help">Last punch: {{ $lastPunch ? \App\Support\PortalTime::format($lastPunch->punched_at, auth()->user(), $lastPunch->timezone).' · '.str_replace('_', ' ', $lastPunch->punch_type) : 'none yet' }}</p>
</form>
