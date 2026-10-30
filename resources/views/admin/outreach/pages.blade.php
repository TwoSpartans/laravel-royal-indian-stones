@extends('admin.layout')

@section('pageTitle', 'Outreach')
@section('pageDescription', 'Pages that still need a title, description, or photograph before search can use them as saved.')

@section('content')
@include('admin.outreach.tabs', ['section' => 'pages'])
<p class="outreach-note">These saved titles and descriptions are not the ones search is using. Open the editor and rewrite the page in the words a buyer would type. <a href="https://search.google.com/search-console" rel="noopener noreferrer" target="_blank">Search Console</a> shows which queries and countries already bring visitors.</p>
<nav class="outreach-tabs" aria-label="Page gaps">
  <a href="{{ route('admin.outreach.index') }}" @if($gap === '') aria-current="page" @endif>All {{ $total }}</a>
  @foreach($gaps as $key => $label)
    @if(($counts[$key] ?? 0) > 0)
      <a href="{{ route('admin.outreach.index', ['gap' => $key]) }}" @if($gap === $key) aria-current="page" @endif>{{ $label }} {{ $counts[$key] }}</a>
    @endif
  @endforeach
</nav>
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark"><tr><th>Page</th><th>Kind</th><th>What to fix</th><th></th></tr></thead>
    <tbody>
      @forelse($pages as $page)
        <tr>
          <td>
            {{ $page['name'] }}
            @if($page['public_url'])
              <div><a href="{{ $page['public_url'] }}" rel="noopener noreferrer" target="_blank">View</a></div>
            @endif
          </td>
          <td>{{ $page['kind'] }}</td>
          <td>
            <ul class="outreach-reasons">
              @foreach($page['reasons'] as $reason)
                <li>{{ $reason['text'] }}</li>
              @endforeach
            </ul>
          </td>
          <td><a class="btn btn-sm btn-outline-dark" href="{{ $page['edit_url'] }}">Edit</a></td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="text-center text-muted">
            @if($gap !== '')
              Nothing is left in this group.
            @else
              Every public page has a title, description, and photograph. Check Search Console for a query that still needs a clearer page.
            @endif
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
