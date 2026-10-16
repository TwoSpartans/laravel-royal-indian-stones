@extends('admin.layout')

@section('pageTitle')
{{ $post->exists ? 'Edit article' : 'Add article' }}
@endsection

@section('content')
<form method="post" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" class="card shadow-sm"><div class="card-body">
  @csrf
  @if($post->exists) @method('PUT') @endif
  <div class="mb-3"><label class="form-label" for="title">Title</label><input class="form-control" id="title" name="title" value="{{ old('title', $post->title) }}" required></div>
  <div class="mb-3"><label class="form-label" for="slug">Slug</label><input class="form-control" id="slug" name="slug" value="{{ old('slug', $post->slug) }}" placeholder="Leave blank to build it from the title"></div>
  <div class="row">
    <div class="col-md-4 mb-3"><label class="form-label" for="published_on">Date</label><input class="form-control" id="published_on" type="date" name="published_on" value="{{ old('published_on', optional($post->published_on)->toDateString()) }}"></div>
    <div class="col-md-8 mb-3"><label class="form-label" for="post_category_id">Category</label>
      <select class="form-select" id="post_category_id" name="post_category_id">
        <option value="">None</option>
        @foreach($categories as $category)
          <option value="{{ $category->id }}" @selected(old('post_category_id', $post->post_category_id) == $category->id)>{{ $category->name }}</option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="mb-3"><label class="form-label" for="image">Image path</label><input class="form-control" id="image" name="image" value="{{ old('image', $post->image) }}" placeholder="assets/blog/example.jpg"></div>
  <div class="mb-3"><label class="form-label" for="summary">Summary</label><textarea class="form-control" id="summary" name="summary" rows="2" maxlength="180">{{ old('summary', $post->summary) }}</textarea></div>
  <div class="mb-3"><label class="form-label" for="body">Body</label><textarea class="form-control" id="body" name="body" rows="12" required>{{ old('body', $post->body) }}</textarea></div>
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <button class="btn btn-dark text-warning" type="submit">Save</button>
</div></form>
@endsection
