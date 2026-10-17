@extends('admin.layout')

@section('pageTitle', 'Blog')
@section('pageDescription', 'Articles published on the site.')
@section('pageActions')
  <a class="admin-primary-btn" href="{{ route('admin.posts.create') }}"><i class="bi bi-plus-lg"></i> Add article</a>
@endsection

@section('content')
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark"><tr><th>Title</th><th>Category</th><th>Date</th><th></th></tr></thead>
    <tbody>
      @forelse($posts as $post)
        <tr>
          <td><a href="{{ url('/'.$post->slug) }}">{{ $post->title }}</a></td>
          <td>{{ $post->postCategory?->name }}</td>
          <td>{{ $post->published_on?->format('d-m-Y') }}</td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.posts.edit', $post) }}">Edit</a>
            <form class="d-inline" method="post" action="{{ route('admin.posts.destroy', $post) }}" onsubmit="return confirm('Delete this article?')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-center text-muted">No articles yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
