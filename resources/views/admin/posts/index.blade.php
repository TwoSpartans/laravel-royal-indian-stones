@extends('admin.layout')

@section('documentTitle', 'Blog')

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,560&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="home-studio blog-catalogue">
  <nav class="home-path" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}">Admin</a>
    <span aria-hidden="true">/</span>
    <span>Content</span>
    <span aria-hidden="true">/</span>
    <span aria-current="page">Blog</span>
  </nav>

  <div class="home-studio-top">
    <div>
      <p class="home-kicker">Royal Indian Stones / Content manager</p>
      <h1>Blog</h1>
      <p class="home-lead">Articles on the public journal. Studio is the editor. Classic keeps the older form.</p>
    </div>
    <div class="home-studio-actions">
      <a class="home-btn home-btn-primary" href="{{ route('admin.posts.studio.create') }}">
        <i class="bi bi-plus-lg" aria-hidden="true"></i>
        New in Studio
      </a>
    </div>
  </div>

  <form class="product-catalogue-filter" method="get" action="{{ route('admin.posts.index') }}">
    <div>
      <label for="article-search">Title</label>
      <input class="form-control" id="article-search" name="q" value="{{ $search }}" placeholder="Search blogs...">
    </div>
    <div>
      <label for="article-category">Category</label>
      <select class="form-select" id="article-category" name="category">
        <option value="">All categories</option>
        @foreach($categories as $category)
          <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label for="article-status">Status</label>
      <select class="form-select" id="article-status" name="status">
        <option value="">All</option>
        @foreach(['draft' => 'Draft', 'scheduled' => 'Scheduled', 'schedule_failed' => 'Schedule failed', 'published' => 'Published'] as $value => $label)
          <option value="{{ $value }}" @selected(($status ?? '') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <button class="home-btn home-btn-primary" type="submit">Apply</button>
  </form>

  <p class="blog-catalogue-count">
    @if($posts->count() === 1)
      1 article
    @else
      {{ $posts->count() }} articles
    @endif
  </p>

  <div class="home-stone-table-wrap">
    <table class="home-stone-table blog-catalogue-table">
      <thead>
        <tr>
          <th>Article</th>
          <th>Category</th>
          <th>Date</th>
          <th>Shown</th>
          <th>Comments</th>
          <th><span class="visually-hidden">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        @forelse($posts as $post)
          @php
            $cover = is_string($post->image) && $post->image !== '' && is_file(public_path($post->image)) ? $post->image : null;
            $shown = $post->is_published;
            $scheduled = $post->status === 'scheduled';
          @endphp
          <tr data-blog-row data-href="{{ route('admin.posts.studio', $post) }}" tabindex="0">
            <td>
              <a class="product-catalogue-stone" href="{{ route('admin.posts.studio', $post) }}">
                @if($cover)
                  <img src="{{ asset($cover) }}" alt="">
                @else
                  <span class="product-catalogue-missing">No photo</span>
                @endif
                <span>{{ $post->title }}</span>
              </a>
            </td>
            <td>{{ $post->postCategory?->name ?: 'None' }}</td>
            <td>{{ $post->published_on?->format('j M Y') ?: 'No date' }}</td>
            <td>
              @if($scheduled)
                <span class="product-visibility">Scheduled</span>
              @else
                <span class="product-visibility {{ $shown ? 'is-shown' : 'is-hidden' }}">{{ $shown ? 'Shown' : 'Hidden' }}</span>
              @endif
            </td>
            <td>{{ $post->comments_count }}</td>
            <td>
              <div class="blog-row-actions">
                <a href="{{ route('admin.posts.studio', $post) }}">Open Studio</a>
                <a href="{{ route('admin.posts.edit', $post) }}">Classic</a>
                @if($shown)
                  <a href="{{ url('/'.$post->slug) }}" target="_blank" rel="noopener noreferrer">View</a>
                @endif
                <form method="post" action="{{ route('admin.posts.destroy', $post) }}" onsubmit="return confirm('Delete this article?')">
                  @csrf
                  @method('DELETE')
                  <button class="is-danger" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="product-catalogue-empty">No article matched.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  document.querySelectorAll('[data-blog-row]').forEach(function (row) {
    row.addEventListener('click', function (event) {
      if (event.target.closest('a, button')) {
        return;
      }
      window.location.assign(row.dataset.href);
    });
    row.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter' || event.target.closest('a, button')) {
        return;
      }
      window.location.assign(row.dataset.href);
    });
  });
})();
</script>
@endpush
