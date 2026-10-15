@extends('admin.layout')

@section('content')
<h2 class="text-uppercase text-center mb-4">Products</h2>
<div class="table-responsive shadow-sm rounded">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark"><tr><th>Name</th><th>Material</th><th>Shown</th><th></th></tr></thead>
    <tbody>
      @foreach($products as $product)
        <tr>
          <td>{{ $product->name }}</td>
          <td>{{ $product->category?->name }}</td>
          <td>{{ $product->is_published ? 'Yes' : 'No' }}</td>
          <td><a class="btn btn-sm btn-outline-dark" href="{{ route('admin.products.edit', $product) }}">Edit</a></td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
