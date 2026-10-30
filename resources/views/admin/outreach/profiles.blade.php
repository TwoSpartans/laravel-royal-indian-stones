@extends('admin.layout')

@section('pageTitle', 'Outreach')
@section('pageDescription', 'Places buyers already look, kept complete and consistent with this site.')

@section('content')
@include('admin.outreach.tabs', ['section' => 'profiles'])
<p class="outreach-note">Google Business Profile, the company LinkedIn and Pinterest, and stone or export directories belong here. Record what is missing, then check the profile again.</p>
<form method="post" action="{{ $editing ? route('admin.outreach.profiles.update', $editing) : route('admin.outreach.profiles.store') }}" class="card shadow-sm mb-4">
  <div class="card-body row g-3">
    @csrf
    @if($editing) @method('PUT') @endif
    <div class="col-md-4">
      <label class="form-label" for="profile-name">Name</label>
      <input class="form-control" id="profile-name" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="160">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="profile-kind">Kind</label>
      <select class="form-select" id="profile-kind" name="kind" required>
        @foreach($kinds as $kind)
          <option value="{{ $kind->value }}" @selected(old('kind', $editing?->kind->value ?? 'company_profile') === $kind->value)>{{ $kind->label() }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="last_checked_on">Last checked</label>
      <input class="form-control" id="last_checked_on" name="last_checked_on" type="date" value="{{ old('last_checked_on', $editing?->last_checked_on?->toDateString()) }}">
    </div>
    <div class="col-12">
      <label class="form-label" for="profile-url">Address</label>
      <input class="form-control" id="profile-url" name="url" type="url" value="{{ old('url', $editing?->url) }}" required maxlength="255" placeholder="https://">
    </div>
    <div class="col-12">
      <label class="form-label" for="gap">What is missing</label>
      <textarea class="form-control" id="gap" name="gap" rows="2" maxlength="2000">{{ old('gap', $editing?->gap) }}</textarea>
    </div>
    @if($errors->any())
      <div class="col-12"><div class="alert alert-danger mb-0">{{ $errors->first() }}</div></div>
    @endif
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-dark text-warning" type="submit">{{ $editing ? 'Update profile' : 'Save profile' }}</button>
      @if($editing)
        <a class="btn btn-outline-dark" href="{{ route('admin.outreach.profiles.index') }}">Cancel</a>
      @endif
    </div>
  </div>
</form>
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark"><tr><th>Name</th><th>Kind</th><th>Last checked</th><th>What is missing</th><th></th></tr></thead>
    <tbody>
      @forelse($profiles as $profile)
        <tr>
          <td><a href="{{ $profile->url }}" rel="noopener noreferrer" target="_blank">{{ $profile->name }}</a></td>
          <td>{{ $profile->kind->label() }}</td>
          <td>{{ $profile->last_checked_on?->toFormattedDateString() ?? '—' }}</td>
          <td>{{ $profile->gap }}</td>
          <td>
            <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.outreach.profiles.index', ['edit' => $profile]) }}">Edit</a>
            <form class="d-inline" method="post" action="{{ route('admin.outreach.profiles.destroy', $profile) }}" onsubmit="return confirm('Delete this profile?')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted">No profiles yet. Add Google Business Profile first, using the same Jodhpur address as this site.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
