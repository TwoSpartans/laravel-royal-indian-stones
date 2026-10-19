@extends('admin.layout')

@section('pageTitle', 'Categories')
@section('pageDescription', 'Groups used by blog articles.')

@section('content')
<form class="journal-filter" method="post" action="{{ route('admin.post-categories.store') }}">
  @csrf
  <label for="category-name">Name</label>
  <input class="form-control" id="category-name" name="name" placeholder="New category" required maxlength="120">
  <button class="admin-primary-btn" type="submit">Add category</button>
</form>
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark"><tr><th>Name</th><th>Articles</th><th></th></tr></thead>
    <tbody>
      @forelse($categories as $category)
        <tr>
          <td>
            <form class="journal-category-edit" method="post" action="{{ route('admin.post-categories.update', $category) }}">
              @csrf
              @method('PUT')
              <input class="form-control" name="name" value="{{ $category->name }}" required maxlength="120" aria-label="Category name">
              <button class="btn btn-sm btn-outline-dark" type="submit">Save</button>
            </form>
          </td>
          <td>{{ $category->posts_count }}</td>
          <td>
            <form method="post" action="{{ route('admin.post-categories.destroy', $category) }}" onsubmit="return confirm('Delete this category? Its articles stay on the blog.')">
              @csrf
              @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="3" class="text-center">No categories yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
