@extends('admin.layout')

@section('content')
<h2 class="text-uppercase mb-4">Edit {{ $product->name }}</h2>
<form method="post" action="{{ route('admin.products.update', $product) }}" class="card shadow-sm"><div class="card-body">
  @csrf @method('PUT')
  <div class="mb-3"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="{{ old('name', $product->name) }}" required></div>
  <div class="mb-3"><label class="form-label" for="summary">Summary</label><textarea class="form-control" id="summary" name="summary" rows="2" maxlength="180">{{ old('summary', $product->summary) }}</textarea></div>
  <div class="mb-3"><label class="form-label" for="color">Colour</label><input class="form-control" id="color" name="color" value="{{ old('color', $product->color) }}"></div>
  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" @checked(old('is_published', $product->is_published))>
    <label class="form-check-label" for="is_published">Show this stone on the site</label>
  </div>
  <h3 class="h5">Specifications</h3>
  @foreach($product->specs as $index => $spec)
    <div class="row g-2 mb-2">
      <div class="col-md-4"><input class="form-control" name="specs[{{ $index }}][label]" value="{{ $spec->label }}" placeholder="Label"></div>
      <div class="col-md-8"><input class="form-control" name="specs[{{ $index }}][value]" value="{{ $spec->value }}" placeholder="Value"></div>
    </div>
  @endforeach
  @for($extra = $product->specs->count(); $extra < $product->specs->count() + 2; $extra++)
    <div class="row g-2 mb-2">
      <div class="col-md-4"><input class="form-control" name="specs[{{ $extra }}][label]" placeholder="New label"></div>
      <div class="col-md-8"><input class="form-control" name="specs[{{ $extra }}][value]" placeholder="New value"></div>
    </div>
  @endfor
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <button class="btn btn-dark text-warning" type="submit">Save</button>
</div></form>
@endsection
