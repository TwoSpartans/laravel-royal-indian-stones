@extends('admin.layout')

@section('pageTitle', 'Outreach')
@section('pageDescription', 'Buyer phrases, each pointed at one page on this site.')

@section('content')
@include('admin.outreach.tabs', ['section' => 'topics'])
<p class="outreach-note">Write the phrase a buyer types, the country, and the one page that should answer it. Take the phrase from <a href="https://search.google.com/search-console" rel="noopener noreferrer" target="_blank">Search Console</a> when this site already appears for it.</p>
<form method="post" action="{{ $editing ? route('admin.outreach.topics.update', $editing) : route('admin.outreach.topics.store') }}" class="card shadow-sm mb-4">
  <div class="card-body row g-3">
    @csrf
    @if($editing) @method('PUT') @endif
    <div class="col-md-6">
      <label class="form-label" for="phrase">Phrase</label>
      <input class="form-control" id="phrase" name="phrase" value="{{ old('phrase', $editing?->phrase) }}" required maxlength="160">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="intent">Intent</label>
      <select class="form-select" id="intent" name="intent" required>
        @foreach($intents as $intent)
          <option value="{{ $intent->value }}" @selected(old('intent', $editing?->intent->value ?? 'buy') === $intent->value)>{{ $intent->label() }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="country">Country</label>
      <input class="form-control" id="country" name="country" value="{{ old('country', $editing?->country) }}" maxlength="80" placeholder="India">
    </div>
    <div class="col-12">
      <label class="form-label" for="target_url">Page on this site</label>
      <input class="form-control" id="target_url" name="target_url" value="{{ old('target_url', $editing?->target_url) }}" placeholder="/sandstone-mint" maxlength="255">
    </div>
    <div class="col-12">
      <label class="form-label" for="topic-note">Note</label>
      <textarea class="form-control" id="topic-note" name="note" rows="2" maxlength="2000">{{ old('note', $editing?->note) }}</textarea>
    </div>
    @if($errors->any())
      <div class="col-12"><div class="alert alert-danger mb-0">{{ $errors->first() }}</div></div>
    @endif
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-dark text-warning" type="submit">{{ $editing ? 'Update topic' : 'Save topic' }}</button>
      @if($editing)
        <a class="btn btn-outline-dark" href="{{ route('admin.outreach.topics.index') }}">Cancel</a>
      @endif
    </div>
  </div>
</form>
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark"><tr><th>Phrase</th><th>Intent</th><th>Page</th><th>Country</th><th>Note</th><th></th></tr></thead>
    <tbody>
      @forelse($topics as $topic)
        <tr>
          <td>{{ $topic->phrase }}</td>
          <td>{{ $topic->intent->label() }}</td>
          <td>
            @if($topic->target_url)
              <a href="{{ str_starts_with($topic->target_url, '/') ? url($topic->target_url) : $topic->target_url }}" rel="noopener noreferrer" target="_blank">{{ $topic->target_url }}</a>
            @else
              —
            @endif
          </td>
          <td>{{ $topic->country }}</td>
          <td>{{ $topic->note }}</td>
          <td>
            <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.outreach.topics.index', ['edit' => $topic]) }}">Edit</a>
            <form class="d-inline" method="post" action="{{ route('admin.outreach.topics.destroy', $topic) }}" onsubmit="return confirm('Delete this topic?')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center text-muted">No topics yet. Add a phrase this site should answer, and the page that answers it.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
