@extends('admin.layout')

@section('documentTitle', 'Excluded sitemap URLs')
@section('pageTitle', 'Excluded URLs')
@section('pageDescription', 'Addresses left out of the sitemap, and why.')

@section('content')
<p><a href="{{ route('admin.sitemap.index') }}">Back to sitemap</a></p>
<div class="table-responsive">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Group</th>
        <th>Address</th>
        <th>Reason</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($exclusions as $exclusion)
        <tr>
          <td>{{ $exclusion->sitemap_group }}</td>
          <td>
            @if (str_starts_with($exclusion->loc, 'http'))
              <a href="{{ $exclusion->loc }}">{{ $exclusion->loc }}</a>
            @else
              {{ $exclusion->loc }}
            @endif
          </td>
          <td>{{ $exclusion->reason }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="3" class="admin-empty">Nothing is currently excluded.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
