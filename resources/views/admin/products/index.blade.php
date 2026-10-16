@extends('admin.layout')

@section('pageTitle', 'Products')
@section('pageDescription', 'Stones shown in the catalogue.')
@section('pageActions')
  <a class="admin-primary-btn" href="{{ route('admin.products.create') }}"><i class="bi bi-plus-lg"></i> Add product</a>
@endsection

@section('content')
<form class="product-editor-filter" method="get" action="{{ route('admin.products.index') }}">
  <label for="product-search">Name</label>
  <input class="form-control" id="product-search" name="q" value="{{ $search }}" placeholder="Search by name">
  <label for="product-material">Material</label>
  <select class="form-select" id="product-material" name="category">
    <option value="">All materials</option>
    @foreach($categories as $category)
      <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
    @endforeach
  </select>
  <button class="admin-primary-btn" type="submit">Find</button>
</form>
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark"><tr><th>Name</th><th>Material</th><th>Shown</th><th></th></tr></thead>
    <tbody>
      @forelse($products as $product)
        <tr>
          <td>{{ $product->name }}</td>
          <td>{{ $product->category?->name }}</td>
          <td>{{ $product->is_published ? 'Yes' : 'No' }}</td>
          <td><a class="btn btn-sm btn-outline-dark" href="{{ route('admin.products.edit', $product) }}">Edit</a></td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="text-center">No stone matched.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
