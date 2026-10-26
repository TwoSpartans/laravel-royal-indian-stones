@extends('portal.layout')
@section('title', $person ? 'Edit '.$person->name : 'Invite')
@section('content')
<header class="page-head"><div><p class="eyebrow">Directory</p><h1>{{ $person ? 'Edit employment' : 'Invite a staff member' }}</h1></div></header>
<form method="post" action="{{ $person ? route('portal.people.update', $person) : route('portal.people.store') }}">
  @csrf
  @if ($person) @method('PUT') @endif
  <div class="form-grid">
    <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $person?->name ?? '') }}" required></div>
    <div class="field"><label for="email">Work email</label><input id="email" name="email" type="email" value="{{ old('email', $person?->email ?? '') }}" required @if($person) readonly @endif></div>
    <div class="field"><label for="portal_role">Portal role</label>
      <select id="portal_role" name="portal_role" required>
        @foreach ($roles as $role)
          <option value="{{ $role->value }}" @selected(old('portal_role', $person?->portal_role?->value ?? 'employee') === $role->value)>{{ $role->label() }}</option>
        @endforeach
        @if ($person?->portal_role === \App\Enums\PortalRole::Employer)
          <option value="employer" selected>Employer</option>
        @endif
      </select>
    </div>
    <div class="field"><label for="job_title">Job title</label><input id="job_title" name="job_title" value="{{ old('job_title', $person?->profile?->job_title ?? '') }}" required></div>
    <div class="field"><label for="department_id">Department</label>
      <select id="department_id" name="department_id" required>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $person?->profile?->department_id ?? '') == $department->id)>{{ $department->name }}</option>@endforeach</select>
    </div>
    <div class="field"><label for="work_location_id">Location</label>
      <select id="work_location_id" name="work_location_id" required>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected(old('work_location_id', $person?->profile?->work_location_id ?? '') == $location->id)>{{ $location->name }}</option>@endforeach</select>
    </div>
    <div class="field"><label for="shift_id">Shift</label>
      <select id="shift_id" name="shift_id" required>@foreach ($shifts as $shift)<option value="{{ $shift->id }}" @selected(old('shift_id', $person?->profile?->shift_id ?? '') == $shift->id)>{{ $shift->name }}</option>@endforeach</select>
    </div>
    <div class="field"><label for="manager_id">Manager</label>
      <select id="manager_id" name="manager_id"><option value="">None</option>@foreach ($managers as $manager)<option value="{{ $manager->id }}" @selected(old('manager_id', $person?->profile?->manager_id ?? '') == $manager->id)>{{ $manager->name }}</option>@endforeach</select>
      <p class="help">Required for an employee. Manager and HR are the same level and can open the full roster.</p>
    </div>
    <div class="field"><label for="joined_on">Joining date</label><input id="joined_on" name="joined_on" type="date" value="{{ old('joined_on', $person?->profile?->joined_on?->toDateString()) }}" required></div>
    <div class="field"><label for="probation_ends_on">Probation ends</label><input id="probation_ends_on" name="probation_ends_on" type="date" value="{{ old('probation_ends_on', $person?->profile?->probation_ends_on?->toDateString()) }}"></div>
    <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $person?->profile?->phone ?? '') }}"></div>
    @if ($person)
      <div class="field"><label for="employment_status">Employment status</label>
        <select id="employment_status" name="employment_status" required>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('employment_status', $person?->profile?->employment_status?->value) === $status->value)>{{ $status->label() }}</option>@endforeach</select>
      </div>
      <div class="field span-2"><label for="address">Address</label><textarea id="address" name="address">{{ old('address', $person?->profile?->address) }}</textarea></div>
      <div class="field"><label for="identity_document_label">Identity document</label><input id="identity_document_label" name="identity_document_label" value="{{ old('identity_document_label', $person?->profile?->identity_document_label) }}"></div>
      <div class="field"><label for="identity_document_number">Document number</label><input id="identity_document_number" name="identity_document_number" autocomplete="off" placeholder="Leave blank to keep the saved number"></div>
    @endif
    <fieldset class="span-2">
      <legend>Sections this employee can open</legend>
      <input type="hidden" name="sections_chosen" value="1">
      @php
        $chosen = old('visible_sections', $person?->profile?->visible_sections ?? array_keys(config('portal.employee_sections')));
      @endphp
      @foreach (config('portal.employee_sections') as $key => $label)
        <label class="check" for="section-{{ $key }}">
          <input id="section-{{ $key }}" type="checkbox" name="visible_sections[]" value="{{ $key }}" @checked(in_array($key, $chosen, true))>
          <span>{{ $label }}</span>
        </label>
      @endforeach
      <p class="help">Checked sections appear for an employee. The employer and every Manager / HR account always see every section.</p>
    </fieldset>
  </div>
  <button type="submit">{{ $person ? 'Save record' : 'Create invitation' }}</button>
</form>
@endsection
