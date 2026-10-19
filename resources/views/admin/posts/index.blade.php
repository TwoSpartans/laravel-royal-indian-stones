@extends('admin.layout')

@section('pageTitle', 'Blog')
@section('pageDescription', 'Articles published on the site.')
@section('pageActions')
  <a class="admin-primary-btn" href="{{ route('admin.posts.studio.create') }}"><i class="bi bi-plus-lg"></i> New in Studio</a>
@endsection

@section('content')
<form class="journal-filter" method="get" action="{{ route('admin.posts.index') }}">
  <label for="article-search">Title or address</label>
  <input class="form-control" id="article-search" name="q" value="{{ $search }}" placeholder="Search articles">
  <label for="article-category">Category</label>
  <select class="form-select" id="article-category" name="category">
    <option value="">All categories</option>
    @foreach($categories as $category)
      <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
    @endforeach
  </select>
  <button class="admin-primary-btn" type="submit">Find</button>
</form>
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark"><tr><th>Title</th><th>Category</th><th>Date</th><th>Shown</th><th>Comments</th><th></th></tr></thead>
    <tbody>
      @forelse($posts as $post)
        <tr>
          <td>{{ $post->title }}</td>
          <td>{{ $post->postCategory?->name }}</td>
          <td>{{ $post->published_on?->format('j M Y') }}</td>
          <td>{{ $post->is_published ? 'Shown' : 'Hidden' }}</td>
          <td>{{ $post->comments_count }}</td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.posts.studio', $post) }}">Studio</a>
            <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.posts.edit', $post) }}">Classic</a>
            @if($post->is_published)
              <a class="btn btn-sm btn-outline-dark" href="{{ url('/'.$post->slug) }}">View</a>
            @endif
            <form class="d-inline" method="post" action="{{ route('admin.posts.destroy', $post) }}" onsubmit="return confirm('Delete this article?')">
              @csrf
              @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center">No article matched.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
