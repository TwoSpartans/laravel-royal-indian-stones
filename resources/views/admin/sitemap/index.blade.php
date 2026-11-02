@extends('admin.layout')

@section('documentTitle', 'Sitemap')
@section('pageTitle', 'Sitemap')
@section('pageDescription', 'Public sitemap files follow catalogue, page, and journal changes. Use the buttons only when you want to rebuild or check them now.')

@section('content')
@php
  $statusLabel = match ($status) {
      'error' => 'Error',
      'warning' => 'Warning',
      default => 'Healthy',
  };
  $statusClass = match ($status) {
      'error' => 'is-alert',
      'warning' => 'is-waiting',
      default => 'is-clear',
  };
  $generatedAt = $manifest['last_success_at'] ?? null;
@endphp

<div class="admin-page-actions sitemap-actions">
  <form method="post" action="{{ route('admin.sitemap.regenerate') }}">
    @csrf
    <button class="home-btn home-btn-primary" type="submit">Regenerate all</button>
  </form>
  <form method="post" action="{{ route('admin.sitemap.validate') }}">
    @csrf
    <button class="home-btn home-btn-ghost" type="submit">Run validation</button>
  </form>
  <a class="home-btn home-btn-ghost" href="{{ route('admin.sitemap.exclusions') }}">Excluded URLs</a>
  <a class="home-btn home-btn-ghost" href="{{ route('admin.sitemap.history') }}">Update history</a>
</div>

<div class="admin-stats">
  <div class="admin-stat">
    <span class="admin-stat-icon is-green"><i class="bi bi-check2-circle"></i></span>
    <span class="admin-stat-value"><span class="admin-pill {{ $statusClass }}">{{ $statusLabel }}</span></span>
    <span class="admin-stat-label">Sitemap status</span>
  </div>
  <div class="admin-stat">
    <span class="admin-stat-icon is-blue"><i class="bi bi-link-45deg"></i></span>
    <span class="admin-stat-value">{{ $counts['total'] }}</span>
    <span class="admin-stat-label">Eligible URLs</span>
  </div>
  <div class="admin-stat">
    <span class="admin-stat-icon is-purple"><i class="bi bi-grid"></i></span>
    <span class="admin-stat-value">{{ $counts['categories'] }}</span>
    <span class="admin-stat-label">Categories</span>
  </div>
  <div class="admin-stat">
    <span class="admin-stat-icon is-orange"><i class="bi bi-box-seam"></i></span>
    <span class="admin-stat-value">{{ $counts['products'] }}</span>
    <span class="admin-stat-label">Products</span>
  </div>
  <div class="admin-stat">
    <span class="admin-stat-icon is-blue"><i class="bi bi-image"></i></span>
    <span class="admin-stat-value">{{ $counts['images'] }}</span>
    <span class="admin-stat-label">Image entries</span>
  </div>
  <div class="admin-stat">
    <span class="admin-stat-icon is-green"><i class="bi bi-file-earmark-text"></i></span>
    <span class="admin-stat-value">{{ $counts['pages'] }}</span>
    <span class="admin-stat-label">Pages</span>
  </div>
  <div class="admin-stat">
    <span class="admin-stat-icon is-purple"><i class="bi bi-journal-text"></i></span>
    <span class="admin-stat-value">{{ $counts['journal'] }}</span>
    <span class="admin-stat-label">Journal</span>
  </div>
</div>

<div class="admin-status-card">
  <div>
    <div class="admin-status-line">
      <h2>Last successful update</h2>
      <span class="admin-pill {{ $statusClass }}">{{ $statusLabel }}</span>
    </div>
    <p>
      @if (is_string($generatedAt) && $generatedAt !== '')
        {{ \Illuminate\Support\Carbon::parse($generatedAt)->timezone('Asia/Kolkata')->format('d-m-Y H:i') }} IST
      @else
        Not generated yet.
      @endif
      @if ($exclusionCount > 0)
        {{ $exclusionCount }} excluded {{ $exclusionCount === 1 ? 'address needs' : 'addresses need' }} review.
      @endif
    </p>
    @if (! empty($manifest['last_error']))
      <p>{{ $manifest['last_error'] }}</p>
    @endif
    <p>
      @if (config('sitemap.search_console.enabled'))
        {{ $manifest['search_console']['message'] ?? 'Search Console submission is on. It does not request indexing.' }}
      @else
        Search Console submission is off. Publishing a sitemap does not request indexing.
      @endif
      @if (filled(config('sitemap.asset_url')))
        Image addresses use the configured image host. Verify that host in Search Console when it is a different domain.
      @endif
    </p>
  </div>
  <a href="{{ route('sitemap.xml') }}" target="_blank" rel="noopener">View sitemap index</a>
</div>

<section class="admin-panel">
  <div class="admin-panel-head">
    <h2>Sitemap files</h2>
  </div>
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>File</th>
          <th>URLs</th>
          <th>Last modified</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($files as $file)
          <tr>
            <td><a href="{{ $file['url'] }}" target="_blank" rel="noopener">{{ $file['name'] }}</a></td>
            <td>{{ $file['urls'] ?? '—' }}</td>
            <td>
              @if (is_string($file['lastmod']) && $file['lastmod'] !== '')
                {{ \Illuminate\Support\Carbon::parse($file['lastmod'])->timezone('Asia/Kolkata')->format('d-m-Y H:i') }}
              @else
                —
              @endif
            </td>
            <td>
              @if (! empty($file['regenerate']))
                <form method="post" action="{{ route('admin.sitemap.regenerate-group') }}">
                  @csrf
                  <input type="hidden" name="group" value="{{ $file['group'] }}">
                  <button class="home-btn home-btn-ghost" type="submit">Regenerate</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel-head">
    <h2>Validation</h2>
  </div>
  @php
    $errors = $manifest['validation']['errors'] ?? [];
    $warnings = $manifest['validation']['warnings'] ?? [];
  @endphp
  @if ($errors === [] && $warnings === [])
    <p class="admin-empty">No validation errors are stored. Run validation to check the current files.</p>
  @else
    <ul class="sitemap-issues">
      @foreach ($errors as $error)
        <li>{{ $error }}</li>
      @endforeach
      @foreach ($warnings as $warning)
        <li>{{ $warning }}</li>
      @endforeach
    </ul>
  @endif
</section>

<section class="admin-panel">
  <div class="admin-panel-head">
    <h2>Recent updates</h2>
    <a href="{{ route('admin.sitemap.history') }}">View all</a>
  </div>
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>When</th>
          <th>Group</th>
          <th>Trigger</th>
          <th>Result</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($runs as $run)
          <tr>
            <td>{{ $run->finished_at?->timezone('Asia/Kolkata')->format('d-m-Y H:i') }}</td>
            <td>{{ $run->sitemap_group ?? 'All' }}</td>
            <td>{{ $run->trigger }}</td>
            <td>{{ $run->status === 'success' ? 'Success' : 'Failed' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="admin-empty">No sitemap updates yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
