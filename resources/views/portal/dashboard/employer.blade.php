@php
  $rosterTotal = max(1, (int) $counts['workforce']);
  $notYetShare = $counts['workforce'] > 0 ? (int) round(($counts['not_in_yet'] / $counts['workforce']) * 100) : 0;
  $segments = [
    ['#278056', $counts['on_time']],
    ['#b57820', $counts['late']],
    ['#3474b9', $counts['not_in_yet']],
    ['#ad4c3b', $counts['absent']],
    ['#93867d', $counts['leave']],
    ['#9c3418', $counts['missed_punch']],
  ];
  $reviewQueue = $approvals['devices'] + $approvals['remote'] + $approvals['reviews'];
  $attendanceQueue = $approvals['corrections'] + $approvals['overtime'];
  $dateLabel = \Illuminate\Support\Carbon::parse($reportDate, 'Asia/Kolkata')->format('D, j M Y');
  $scheduledLabel = $counts['workforce'] === 1 ? 'employee scheduled today' : 'employees scheduled today';
  $attendanceUrl = route('portal.attendance.index', array_filter([
    'date' => $reportDate,
    'location_id' => $locationId ?: null,
  ]));
  $decisionUrl = route('portal.workplace.index');
@endphp
<div class="yard">
<header class="home-heading" id="yard-top">
  <div>
    <p class="eyebrow">Employer overview · Home</p>
    <h1>Today at the yards</h1>
    <p class="muted">Your workplace at a glance — staffing, attention items and operational readiness.</p>
  </div>
  <div class="home-tools">
  <form class="yard-controls" method="get" action="{{ route('portal.home') }}">
    <label class="yard-control">
      <i class="bi bi-calendar3" aria-hidden="true"></i>
      <span>{{ $dateLabel }}</span>
      <input id="home-date" name="date" type="date" value="{{ $reportDate }}" aria-label="Reporting date" onchange="this.form.requestSubmit()">
    </label>
    <label class="yard-control">
      <i class="bi bi-geo-alt" aria-hidden="true"></i>
      <select id="home-location" name="location_id" aria-label="Location" onchange="this.form.requestSubmit()">
        <option value="">All locations</option>
        @foreach ($places as $place)
          <option value="{{ $place->id }}" @selected($locationId === $place->id)>{{ $place->name }}</option>
        @endforeach
      </select>
    </label>
    <noscript><button class="btn-secondary" type="submit">Show</button></noscript>
    <a class="btn-secondary" href="{{ $attendanceUrl }}"><i class="bi bi-bar-chart" aria-hidden="true"></i> View attendance</a>
  </form>
  </div>
</header>

<section class="home-kpis" aria-label="Today's attendance">
  <article class="card kpi"><span class="kpi-icon" aria-hidden="true"><i class="bi bi-people"></i></span><div><strong>{{ $counts['workforce'] }}</strong><span>On the roster</span></div></article>
  <article class="card kpi" data-tone="good"><span class="kpi-icon" aria-hidden="true"><i class="bi bi-check-lg"></i></span><div><strong>{{ $counts['on_time'] }}</strong><span>Present</span></div></article>
  <article class="card kpi" data-tone="info"><span class="kpi-icon" aria-hidden="true"><i class="bi bi-clock"></i></span><div><strong>{{ $counts['not_in_yet'] }}</strong><span>Not in yet</span></div></article>
  <article class="card kpi" data-tone="warn"><span class="kpi-icon" aria-hidden="true"><i class="bi bi-clock-history"></i></span><div><strong>{{ $counts['late'] }}</strong><span>Late</span></div></article>
  <article class="card kpi" data-tone="bad"><span class="kpi-icon" aria-hidden="true"><i class="bi bi-person-x"></i></span><div><strong>{{ $counts['absent'] }}</strong><span>Absent</span></div></article>
  <article class="card kpi"><span class="kpi-icon" aria-hidden="true"><i class="bi bi-calendar3"></i></span><div><strong>{{ $counts['leave'] }}</strong><span>On leave</span></div></article>
</section>

<div class="home-columns">
  <section class="card home-pad">
    <div class="card-head">
      <h2>Today's workforce</h2>
      <a href="{{ $attendanceUrl }}">Detailed attendance <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <p class="home-statline">
      <strong>{{ $counts['workforce'] }}</strong>
      @if ($counts['workforce'] > 0)<span class="pill">{{ $notYetShare }}% not in yet</span>@endif
      <span class="muted">{{ $scheduledLabel }}</span>
    </p>
    <div class="mix-bar" role="img" aria-label="Attendance mix for the selected date">
      @foreach ($segments as [$color, $count])
        @if ($count > 0)
          <span style="width: {{ round($count / $rosterTotal * 100) }}%; background: {{ $color }}"></span>
        @endif
      @endforeach
    </div>
    <ul class="legend">
      <li><i class="dot" style="background:#278056"></i> Present: {{ $counts['on_time'] }}</li>
      <li><i class="dot" style="background:#3474b9"></i> Not in yet: {{ $counts['not_in_yet'] }}</li>
      <li><i class="dot" style="background:#b57820"></i> Late: {{ $counts['late'] }}</li>
      <li><i class="dot" style="background:#ad4c3b"></i> Absent: {{ $counts['absent'] }}</li>
      <li><i class="dot" style="background:#93867d"></i> On leave: {{ $counts['leave'] }}</li>
    </ul>
    <div class="card-head yard-subhead">
      <h3>Status by workplace</h3>
      <span>{{ $locations->count() }} {{ \Illuminate\Support\Str::plural('location', $locations->count()) }}</span>
    </div>
    @if ($locations->isEmpty())
      <div class="yard-state"><span class="yard-round" aria-hidden="true"><i class="bi bi-geo-alt"></i></span><div><strong>{{ $locationId ? 'No one at this yard' : 'No roster yet' }}</strong><p>{{ $locationId ? 'No active staff are assigned to this location.' : 'Active staff appear here after they accept an invitation.' }}</p></div></div>
    @else
      <div class="table-wrap">
        <table>
          <thead><tr><th>Location</th><th>Roster</th><th>Present</th><th>Not in yet</th><th>Absent</th></tr></thead>
          <tbody>
            @foreach ($locations as $location)
              <tr>
                <td data-label="Location"><span class="yard-place"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $location['name'] }}</span></td>
                <td data-label="Roster">{{ $location['people'] }}</td>
                <td data-label="Present">{{ $location['present'] }}</td>
                <td data-label="Not in yet">@if ($location['not_in_yet'] > 0)<span class="badge badge-info">{{ $location['not_in_yet'] }} not in yet</span>@else 0 @endif</td>
                <td data-label="Absent">{{ $location['absent'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
    <p class="yard-note"><i class="bi bi-shield-check" aria-hidden="true"></i> Not in yet means the shift is still open. Absent means the shift has passed with no punch. Signing in does not record attendance.</p>
  </section>

  <section class="card home-pad yard-attention">
    <div class="card-head">
      <h2>Needs your decision</h2>
      <a href="{{ $decisionUrl }}">Open queue <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <div class="yard-alert">
      <span class="yard-alert-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
      <div>
        <strong>{{ $reviewQueue }}</strong>
        <span>pending review items</span>
        <p>Across access, remote work and network reviews</p>
      </div>
    </div>
    <div class="yard-callout">
      <strong>Prioritize items needing admin action</strong>
      <p>Review a device, a remote-work request, or a sign-in from outside the saved network before acting on it.</p>
    </div>
    <div class="yard-actions">
      <a class="btn" href="{{ $decisionUrl }}">Review decisions <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
      <a class="btn-secondary" href="{{ $decisionUrl }}">View details</a>
    </div>
    @if ($approvals['leave'] + $approvals['expenses'] + $attendanceQueue > 0)
      <ul class="yard-extra">
        @if ($approvals['leave'] > 0)<li><a href="{{ route('portal.leave.index') }}">Leave {{ $approvals['leave'] }}</a></li>@endif
        @if ($approvals['expenses'] > 0)<li><a href="{{ route('portal.expenses.index') }}">Expenses {{ $approvals['expenses'] }}</a></li>@endif
        @if ($attendanceQueue > 0)<li><a href="{{ route('portal.attendance.exceptions') }}">Corrections and overtime {{ $attendanceQueue }}</a></li>@endif
      </ul>
    @endif
    <p class="yard-missed"><span>Missed attendance punches</span> <a href="{{ $attendanceUrl }}"><strong @class(['is-due' => $counts['missed_punch'] > 0])>{{ $counts['missed_punch'] }} recorded</strong></a></p>
  </section>
</div>

<div class="home-pair">
  <section class="card home-pad">
    <div class="card-head"><h2>Payroll readiness</h2><a href="{{ route('portal.payroll.index') }}">Open payroll <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
    @if ($payslipCounts->isEmpty())
      <div class="yard-state"><span class="yard-round" aria-hidden="true"><i class="bi bi-calendar3"></i></span><div><strong>No period prepared</strong><p>Calculations remain off until a reviewer enables them. No payment is marked complete.</p></div></div>
    @else
      <ul class="yard-list">
        @foreach ($payslipCounts as $status => $count)
          <li>{{ $count }} {{ str_replace('_', ' ', $status) }}</li>
        @endforeach
      </ul>
    @endif
  </section>
  <section class="card home-pad">
    <div class="card-head"><h2>Open stone work</h2>@if (auth()->user()->canOpenPortalSection('tasks'))<a href="{{ route('portal.tasks.index') }}">View tasks <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endif</div>
    @forelse ($tasks as $task)
      <a class="yard-task" href="{{ route('portal.tasks.show', $task) }}">
        <strong>{{ $task->title }}</strong>
        <span>{{ str_replace('_', ' ', $task->status) }}@if ($task->due_on) · due {{ $task->due_on->format('d M') }}@endif</span>
        @if ($task->due_on && $task->due_on->lt(now()->startOfDay()))<em>Overdue</em>@endif
      </a>
    @empty
      <div class="yard-state"><span class="yard-round" aria-hidden="true"><i class="bi bi-box"></i></span><div><strong>No open tasks</strong><p>Processing, inspection, packing and dispatch tasks will appear here when available.</p></div></div>
    @endforelse
  </section>
</div>

<div class="home-trio">
  <section class="card home-pad">
    <div class="card-head"><h2>Upcoming leave</h2><a href="{{ route('portal.leave.index') }}">View leave <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
    @forelse ($upcomingLeave as $leave)
      <p class="yard-line">{{ $leave->user?->name }} · {{ $leave->starts_on->format('d M') }}</p>
    @empty
      <div class="yard-empty"><span aria-hidden="true"><i class="bi bi-calendar3"></i></span><div><strong>No approved leave</strong><p>Nothing scheduled in the next 14 days.</p></div></div>
    @endforelse
  </section>
  <section class="card home-pad">
    <div class="card-head"><h2>Documents expiring</h2><a href="{{ route('portal.people.index') }}">View documents <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
    @forelse ($expiringDocuments as $document)
      <p class="yard-line">{{ $document->title }} · {{ $document->user?->name }} · {{ $document->expires_on?->format('d M Y') }}</p>
    @empty
      <div class="yard-empty"><span aria-hidden="true"><i class="bi bi-file-earmark"></i></span><div><strong>No upcoming expirations</strong><p>No documents expire within 30 days.</p></div></div>
    @endforelse
  </section>
  <section class="card home-pad">
    <div class="card-head"><h2>Quick access</h2><span>Frequently used</span></div>
    <div class="yard-chips">
      <a class="btn-secondary" href="{{ $attendanceUrl }}">Attendance <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
      <a class="btn-secondary" href="{{ $decisionUrl }}">Decisions <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
      <a class="btn-secondary" href="{{ route('portal.payroll.index') }}">Payroll <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
      @if (auth()->user()->canOpenPortalSection('tasks'))
        <a class="btn-secondary" href="{{ route('portal.tasks.index') }}">Tasks <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
      @endif
    </div>
  </section>
</div>
<p class="yard-foot"><span>Asia/Kolkata · signing in does not record attendance</span><a href="#yard-top">Back to top</a></p>
</div>
