@extends('portal.layout')
@section('title', 'People')
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">Directory</p>
    <h1>People</h1>
  </div>
  @if (auth()->user()->portal_role?->canAdminister())
    <a class="btn" href="{{ route('portal.people.create') }}">Invite someone</a>
  @endif
</header>
<form class="filters" method="get">
  <div class="field"><label for="q">Search</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email, code, or role"></div>
  <div class="field"><label for="department_id">Department</label>
    <select id="department_id" name="department_id"><option value="">All</option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected(($filters['department_id'] ?? '') == $department->id)>{{ $department->name }}</option>@endforeach</select>
  </div>
  <div class="field"><label for="work_location_id">Location</label>
    <select id="work_location_id" name="work_location_id"><option value="">All</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected(($filters['work_location_id'] ?? '') == $location->id)>{{ $location->name }}</option>@endforeach</select>
  </div>
  <div class="field"><label for="employment_status">Status</label>
    <select id="employment_status" name="employment_status"><option value="">All</option>@foreach (\App\Enums\EmploymentStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['employment_status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
  </div>
  <button type="submit">Filter</button>
</form>
<div class="table-wrap">
  @if ($people->isEmpty())
    <div class="empty"><strong>No matching people</strong><p>Invite a colleague, or clear the filters.</p></div>
  @else
    <table>
      <thead><tr><th>Name</th><th>Role</th><th>Place</th><th>Manager</th><th>Status</th></tr></thead>
      <tbody>
        @foreach ($people as $person)
          <tr>
            <td data-label="Name"><a href="{{ route('portal.people.show', $person) }}">{{ $person->name }}</a><br><span class="muted">{{ $person->profile?->employee_code }} · {{ $person->profile?->job_title }}</span></td>
            <td data-label="Role">{{ $person->portal_role?->label() }}</td>
            <td data-label="Place">{{ $person->profile?->location?->name }}<br><span class="muted">{{ $person->profile?->department?->name }}</span></td>
            <td data-label="Manager">{{ $person->profile?->manager?->name ?? '—' }}</td>
            <td data-label="Status"><span class="badge">{{ $person->profile?->employment_status?->label() }}</span>@if($person->deactivated_at)<br><span class="badge badge-bad">Sign-in revoked</span>@endif</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    {{ $people->links() }}
  @endif
</div>
@endsection
