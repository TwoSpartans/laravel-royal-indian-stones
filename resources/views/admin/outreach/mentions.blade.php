@extends('admin.layout')

@section('pageTitle', 'Outreach')
@section('pageDescription', 'Pitches to a person or publication that already has readers.')

@section('content')
@include('admin.outreach.tabs', ['section' => 'mentions'])
<p class="outreach-note">Record one pitch at a time: who you contacted, the page you offered, and the address where it was published.</p>
<form method="post" action="{{ $editing ? route('admin.outreach.mentions.update', $editing) : route('admin.outreach.mentions.store') }}" class="card shadow-sm mb-4">
  <div class="card-body row g-3">
    @csrf
    @if($editing) @method('PUT') @endif
    <div class="col-md-4">
      <label class="form-label" for="publication">Person or publication</label>
      <input class="form-control" id="publication" name="publication" value="{{ old('publication', $editing?->publication) }}" required maxlength="160">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="contact_name">Contact name</label>
      <input class="form-control" id="contact_name" name="contact_name" value="{{ old('contact_name', $editing?->contact_name) }}" maxlength="120">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="status">Status</label>
      <select class="form-select" id="status" name="status" required>
        @foreach($statuses as $status)
          <option value="{{ $status->value }}" @selected(old('status', $editing?->status->value ?? 'to_contact') === $status->value)>{{ $status->label() }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label" for="offered_url">Page offered</label>
      <input class="form-control" id="offered_url" name="offered_url" value="{{ old('offered_url', $editing?->offered_url) }}" placeholder="/sandstone-mint" maxlength="255">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="published_url">Published address</label>
      <input class="form-control" id="published_url" name="published_url" value="{{ old('published_url', $editing?->published_url) }}" placeholder="https://" maxlength="255">
    </div>
    <div class="col-12">
      <label class="form-label" for="note">Note</label>
      <textarea class="form-control" id="note" name="note" rows="2" maxlength="2000">{{ old('note', $editing?->note) }}</textarea>
    </div>
    @if($errors->any())
      <div class="col-12"><div class="alert alert-danger mb-0">{{ $errors->first() }}</div></div>
    @endif
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-dark text-warning" type="submit">{{ $editing ? 'Update mention' : 'Save mention' }}</button>
      @if($editing)
        <a class="btn btn-outline-dark" href="{{ route('admin.outreach.mentions.index') }}">Cancel</a>
      @endif
    </div>
  </div>
</form>
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark"><tr><th>Who</th><th>Page offered</th><th>Status</th><th>Published address</th><th>Note</th><th></th></tr></thead>
    <tbody>
      @forelse($mentions as $mention)
        <tr>
          <td>{{ $mention->publication }}@if($mention->contact_name)<div class="text-muted">{{ $mention->contact_name }}</div>@endif</td>
          <td>
            @if($mention->offered_url)
              <a href="{{ str_starts_with($mention->offered_url, '/') ? url($mention->offered_url) : $mention->offered_url }}" rel="noopener noreferrer" target="_blank">{{ $mention->offered_url }}</a>
            @else
              —
            @endif
          </td>
          <td>{{ $mention->status->label() }}</td>
          <td>
            @if($mention->published_url)
              <a href="{{ $mention->published_url }}" rel="noopener noreferrer" target="_blank">{{ $mention->published_url }}</a>
            @else
              —
            @endif
          </td>
          <td>{{ $mention->note }}</td>
          <td>
            <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.outreach.mentions.index', ['edit' => $mention]) }}">Edit</a>
            <form class="d-inline" method="post" action="{{ route('admin.outreach.mentions.destroy', $mention) }}" onsubmit="return confirm('Delete this mention?')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center text-muted">No mentions yet. Start with one architect, importer, or trade editor.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
