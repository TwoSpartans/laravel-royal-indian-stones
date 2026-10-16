@extends('admin.layout')

@section('pageTitle', 'Outreach')
@section('pageDescription', 'Directories and sites where the catalogue is listed.')

@section('content')
<form method="post" action="{{ route('admin.outreach.store') }}" class="card shadow-sm mb-4"><div class="card-body row g-3">
  @csrf
  <div class="col-md-3"><label class="form-label" for="type">Type</label>
    <select class="form-select" id="type" name="type" required>
      @foreach($types as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-3"><label class="form-label" for="site_name">Site name</label><input class="form-control" id="site_name" name="site_name" required></div>
  <div class="col-md-3"><label class="form-label" for="url">URL</label><input class="form-control" id="url" name="url" type="url"></div>
  <div class="col-md-3"><label class="form-label" for="status">Status</label><input class="form-control" id="status" name="status" value="noted" required></div>
  <div class="col-12"><label class="form-label" for="note">Note</label><textarea class="form-control" id="note" name="note" rows="2"></textarea></div>
  @if($errors->any())<div class="col-12"><div class="alert alert-danger mb-0">{{ $errors->first() }}</div></div>@endif
  <div class="col-12"><button class="btn btn-dark text-warning" type="submit">Save note</button></div>
</div></form>
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark"><tr><th>Type</th><th>Site</th><th>Status</th><th>Note</th><th></th></tr></thead>
    <tbody>
      @forelse($submissions as $submission)
        <tr>
          <td>{{ $types[$submission->type] ?? $submission->type }}</td>
          <td>@if($submission->url)<a href="{{ $submission->url }}">{{ $submission->site_name }}</a>@else{{ $submission->site_name }}@endif</td>
          <td>{{ $submission->status }}</td>
          <td>{{ $submission->note }}</td>
          <td>
            <form method="post" action="{{ route('admin.outreach.destroy', $submission) }}" onsubmit="return confirm('Delete this note?')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted">No outreach notes yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
