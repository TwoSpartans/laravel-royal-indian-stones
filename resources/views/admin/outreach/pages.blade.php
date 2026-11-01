@extends('admin.layout')

@section('pageTitle', 'Outreach')
@section('pageDescription', 'One row is one warning. Compare what is saved with what visitors see.')

@section('content')
@include('admin.outreach.tabs', ['section' => 'pages'])

<div class="outreach-filter">
  <div class="outreach-filter-line">
    <span class="outreach-filter-label" id="outreach-show-label">Show</span>
    <nav class="outreach-tabs" aria-labelledby="outreach-show-label">
      <a href="{{ route('admin.outreach.index', array_filter(['gap' => $gap ?: null])) }}" @if($show === 'open') aria-current="page" @endif>Open {{ $openCount }}</a>
      <a href="{{ route('admin.outreach.index', array_filter(['show' => 'ignored', 'gap' => $gap ?: null])) }}" @if($show === 'ignored') aria-current="page" @endif>Ignored {{ $ignoredCount }}</a>
    </nav>
  </div>
  <div class="outreach-filter-line">
    <span class="outreach-filter-label" id="outreach-problem-label">Problem</span>
    <nav class="outreach-tabs" aria-labelledby="outreach-problem-label">
      <a href="{{ route('admin.outreach.index', array_filter(['show' => $show === 'ignored' ? 'ignored' : null])) }}" @if($gap === '') aria-current="page" @endif>All {{ $show === 'ignored' ? $ignoredCount : $openCount }}</a>
      @foreach($gaps as $key => $label)
        @if(($counts[$key] ?? 0) > 0)
          <a href="{{ route('admin.outreach.index', array_filter(['show' => $show === 'ignored' ? 'ignored' : null, 'gap' => $key])) }}" @if($gap === $key) aria-current="page" @endif>{{ $label }} {{ $counts[$key] }}</a>
        @endif
      @endforeach
    </nav>
  </div>
</div>
<p class="outreach-rule">{{ $rule }}</p>

<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-striped align-middle outreach-warnings">
    <thead class="table-dark">
      <tr>
        <th>Page</th>
        <th>Problem</th>
        <th>Saved</th>
        <th>Visitors see</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse($warnings as $warning)
        <tr>
          <td>
            <a class="outreach-page-name" href="{{ $warning['edit_url'] }}">{{ $warning['name'] }}</a>
            <p class="outreach-page-meta">
              {{ $warning['kind'] }}
              @if($warning['public_url'])
                · <a href="{{ $warning['public_url'] }}" rel="noopener noreferrer" target="_blank">View</a>
              @endif
            </p>
          </td>
          <td><span class="outreach-problem">{{ $gaps[$warning['gap']] }}</span></td>
          <td>
            <p class="outreach-copy">{{ $warning['saved'] }}</p>
            @if($warning['measure'] !== '')
              <p class="outreach-measure">{{ $warning['measure'] }}</p>
            @endif
          </td>
          <td><p class="outreach-copy">{{ $warning['used'] }}</p></td>
          <td>
            <div class="outreach-actions">
              <a class="btn btn-sm btn-outline-dark" href="{{ $warning['edit_url'] }}">Edit</a>
              @if($show === 'ignored')
                <form method="post" action="{{ route('admin.outreach.warnings.restore') }}">
                  @csrf
                  @method('DELETE')
                  <input type="hidden" name="subject_type" value="{{ $warning['subject_type'] }}">
                  <input type="hidden" name="subject_id" value="{{ $warning['subject_id'] }}">
                  <input type="hidden" name="gap" value="{{ $warning['gap'] }}">
                  <input type="hidden" name="show" value="ignored">
                  <input type="hidden" name="return_gap" value="{{ $gap }}">
                  <button class="btn btn-sm btn-outline-secondary" type="submit" aria-label="Restore this warning for {{ $warning['name'] }}">Restore</button>
                </form>
              @else
                <form method="post" action="{{ route('admin.outreach.warnings.ignore') }}">
                  @csrf
                  <input type="hidden" name="subject_type" value="{{ $warning['subject_type'] }}">
                  <input type="hidden" name="subject_id" value="{{ $warning['subject_id'] }}">
                  <input type="hidden" name="gap" value="{{ $warning['gap'] }}">
                  <input type="hidden" name="show" value="open">
                  <input type="hidden" name="return_gap" value="{{ $gap }}">
                  <button class="btn btn-sm btn-outline-secondary" type="submit" aria-label="Ignore this warning for {{ $warning['name'] }}">Ignore</button>
                </form>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="text-center text-muted">
            @if($gap !== '')
              No warnings in this problem.
            @elseif($show === 'ignored')
              No warnings are ignored.
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
