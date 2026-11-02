@extends('admin.layout')

@section('documentTitle', 'Sitemap history')
@section('pageTitle', 'Sitemap history')
@section('pageDescription', 'Recent sitemap rebuilds.')

@section('content')
<p><a href="{{ route('admin.sitemap.index') }}">Back to sitemap</a></p>
<div class="table-responsive">
  <table class="admin-table">
    <thead>
      <tr>
        <th>When</th>
        <th>Group</th>
        <th>Trigger</th>
        <th>Result</th>
        <th>URLs</th>
        <th>Note</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($runs as $run)
        <tr>
          <td>{{ $run->finished_at?->timezone('Asia/Kolkata')->format('d-m-Y H:i') }}</td>
          <td>{{ $run->sitemap_group ?? 'All' }}</td>
          <td>{{ $run->trigger }}</td>
          <td>{{ $run->status === 'success' ? 'Success' : 'Failed' }}</td>
          <td>{{ $run->url_count }}</td>
          <td>{{ $run->message }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="admin-empty">No sitemap updates yet.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
