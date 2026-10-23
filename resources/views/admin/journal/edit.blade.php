@extends('admin.layout')

@php
    $selectedEdit = array_values((array) old('edit_posts', $journal->edit_post_ids ?? []));
    $selectedCloser = array_values((array) old('closer_posts', $journal->closer_post_ids ?? []));
@endphp

@section('pageTitle', 'Journal page')
@section('pageDescription', 'Cover, story selection, and the maker feature on the public journal.')

@section('content')
<form class="journal-settings" method="post" enctype="multipart/form-data" action="{{ route('admin.journal.update') }}">
  @csrf
  @method('PUT')

  @if($errors->any())
    <div class="alert alert-danger" role="alert">
      <strong>Check the highlighted fields.</strong>
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <section class="mb-4">
    <h2>Cover</h2>
    <p class="text-muted">This introduces the journal. Article photographs still come from each story.</p>
    <div class="mb-3">
      <label class="form-label" for="eyebrow">Eyebrow</label>
      <input class="form-control" id="eyebrow" name="eyebrow" value="{{ old('eyebrow', $journal->eyebrow) }}" maxlength="80" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="heading_lead">Heading</label>
      <input class="form-control" id="heading_lead" name="heading_lead" value="{{ old('heading_lead', $journal->heading_lead) }}" maxlength="80" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="heading_emphasis">Italic line</label>
      <input class="form-control" id="heading_emphasis" name="heading_emphasis" value="{{ old('heading_emphasis', $journal->heading_emphasis) }}" maxlength="80" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="introduction">Introduction</label>
      <textarea class="form-control" id="introduction" name="introduction" rows="3" maxlength="500" required>{{ old('introduction', $journal->introduction) }}</textarea>
    </div>
    <div class="mb-3">
      <label class="form-label" for="caption">Photograph caption</label>
      <input class="form-control" id="caption" name="caption" value="{{ old('caption', $journal->caption) }}" maxlength="180">
    </div>
    <div class="mb-3">
      <label class="form-label" for="cover_image_alt">Photograph description</label>
      <input class="form-control" id="cover_image_alt" name="cover_image_alt" value="{{ old('cover_image_alt', $journal->cover_image_alt) }}" maxlength="180">
    </div>
    @if($journal->cover_image)
      <p><img class="journal-settings-preview" src="{{ asset($journal->cover_image) }}" alt=""></p>
      <label class="form-check-label mb-3">
        <input type="checkbox" name="remove_cover" value="1" @checked(old('remove_cover'))>
        Use the standard cover photograph
      </label>
    @endif
    <div class="mb-3">
      <label class="form-label" for="cover">Replace the cover photograph</label>
      <input class="form-control" id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp">
    </div>
  </section>

  <section class="mb-4">
    <h2>The Edit</h2>
    <p class="text-muted">Leave the stories blank to use the latest published articles. Hidden articles are skipped on the public page.</p>
    <div class="mb-3">
      <label class="form-label" for="edit_kicker">Label</label>
      <input class="form-control" id="edit_kicker" name="edit_kicker" value="{{ old('edit_kicker', $journal->edit_kicker) }}" maxlength="80" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="edit_heading">Heading</label>
      <input class="form-control" id="edit_heading" name="edit_heading" value="{{ old('edit_heading', $journal->edit_heading) }}" maxlength="160" required>
    </div>
    @for($slot = 0; $slot < 3; $slot++)
      <div class="mb-3">
        <label class="form-label" for="edit-post-{{ $slot }}">Story {{ $slot + 1 }}</label>
        <select class="form-select" id="edit-post-{{ $slot }}" name="edit_posts[]">
          <option value="">Not chosen</option>
          @foreach($posts as $post)
            <option value="{{ $post->id }}" @selected((string) ($selectedEdit[$slot] ?? '') === (string) $post->id)>{{ $post->title }}{{ $post->is_published ? '' : ' (hidden)' }}</option>
          @endforeach
        </select>
      </div>
    @endfor
  </section>

  <section class="mb-4">
    <h2>A closer look</h2>
    <p class="text-muted">These two stories are skipped when they are already in The Edit. Leave them blank to continue with the next published stories.</p>
    <div class="mb-3">
      <label class="form-label" for="closer_kicker">Label</label>
      <input class="form-control" id="closer_kicker" name="closer_kicker" value="{{ old('closer_kicker', $journal->closer_kicker) }}" maxlength="80" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="closer_heading">Heading</label>
      <input class="form-control" id="closer_heading" name="closer_heading" value="{{ old('closer_heading', $journal->closer_heading) }}" maxlength="160" required>
    </div>
    @for($slot = 0; $slot < 2; $slot++)
      <div class="mb-3">
        <label class="form-label" for="closer-post-{{ $slot }}">Story {{ $slot + 1 }}</label>
        <select class="form-select" id="closer-post-{{ $slot }}" name="closer_posts[]">
          <option value="">Not chosen</option>
          @foreach($posts as $post)
            <option value="{{ $post->id }}" @selected((string) ($selectedCloser[$slot] ?? '') === (string) $post->id)>{{ $post->title }}{{ $post->is_published ? '' : ' (hidden)' }}</option>
          @endforeach
        </select>
      </div>
    @endfor
  </section>

  <section class="mb-4">
    <h2>Maker feature</h2>
    <p class="text-muted">Choose a published article, or a heading plus an existing page such as /aboutus. The feature stays off the journal until one of those is ready. A hidden article is not shown.</p>
    <div class="mb-3">
      <label class="form-label" for="maker_post_id">Article</label>
      <select class="form-select" id="maker_post_id" name="maker_post_id">
        <option value="">None</option>
        @foreach($posts as $post)
          <option value="{{ $post->id }}" @selected((string) old('maker_post_id', $journal->maker_post_id) === (string) $post->id)>{{ $post->title }}{{ $post->is_published ? '' : ' (hidden)' }}</option>
        @endforeach
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label" for="maker_path">Or an existing page</label>
      <input class="form-control" id="maker_path" name="maker_path" value="{{ old('maker_path', $journal->maker_path) }}" maxlength="200" placeholder="/aboutus">
    </div>
    <div class="mb-3">
      <label class="form-label" for="maker_eyebrow">Eyebrow</label>
      <input class="form-control" id="maker_eyebrow" name="maker_eyebrow" value="{{ old('maker_eyebrow', $journal->maker_eyebrow) }}" maxlength="80">
    </div>
    <div class="mb-3">
      <label class="form-label" for="maker_heading">Heading</label>
      <input class="form-control" id="maker_heading" name="maker_heading" value="{{ old('maker_heading', $journal->maker_heading) }}" maxlength="160">
    </div>
    <div class="mb-3">
      <label class="form-label" for="maker_body">Supporting text</label>
      <textarea class="form-control" id="maker_body" name="maker_body" rows="3" maxlength="500">{{ old('maker_body', $journal->maker_body) }}</textarea>
    </div>
    <div class="mb-3">
      <label class="form-label" for="maker_label">Button label</label>
      <input class="form-control" id="maker_label" name="maker_label" value="{{ old('maker_label', $journal->maker_label) }}" maxlength="40">
    </div>
    <div class="mb-3">
      <label class="form-label" for="maker_image_alt">Photograph description</label>
      <input class="form-control" id="maker_image_alt" name="maker_image_alt" value="{{ old('maker_image_alt', $journal->maker_image_alt) }}" maxlength="180">
    </div>
    @if($journal->maker_image)
      <p><img class="journal-settings-preview" src="{{ asset($journal->maker_image) }}" alt=""></p>
      <label class="form-check-label mb-3">
        <input type="checkbox" name="remove_maker_image" value="1" @checked(old('remove_maker_image'))>
        Remove this photograph
      </label>
    @endif
    <div class="mb-3">
      <label class="form-label" for="maker_image">Photograph</label>
      <input class="form-control" id="maker_image" name="maker_image" type="file" accept="image/jpeg,image/png,image/webp">
    </div>
  </section>

  <button class="admin-primary-btn" type="submit">Save journal page</button>
</form>
@endsection
