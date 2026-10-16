@extends('admin.layout')

@section('pageTitle', 'Categories')
@section('pageDescription', 'Groups used by journal articles.')

@section('content')
<form method="post" action="{{ route('admin.post-categories.store') }}" class="row g-2 justify-content-end my-4">
  @csrf
  <div class="col-md-4"><input class="form-control" name="name" placeholder="New category name" required></div>
  <div class="col-auto"><button class="btn btn-dark text-warning" type="submit"><i class="bi bi-plus-square me-1"></i> Add Category</button></div>
</form>
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-hover table-striped align-middle text-center">
    <thead class="table-dark"><tr><th style="width:10%">#</th><th>Category Name</th><th style="width:20%">Edit</th><th style="width:10%">Remove</th></tr></thead>
    <tbody>
      @forelse($categories as $category)
        <tr>
          <td><span class="badge bg-secondary">{{ $loop->iteration }}</span></td>
          <td class="text-start ps-3">
            <form method="post" action="{{ route('admin.post-categories.update', $category) }}" class="d-flex gap-2">
              @csrf @method('PUT')
              <input class="form-control" name="name" value="{{ $category->name }}" required>
              <button class="btn btn-sm btn-outline-dark" type="submit">Save</button>
            </form>
          </td>
          <td>{{ $category->posts_count }} articles</td>
          <td>
            <form method="post" action="{{ route('admin.post-categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-muted">No categories yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
