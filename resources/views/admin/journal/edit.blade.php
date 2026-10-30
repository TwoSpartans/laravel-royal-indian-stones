@extends('admin.layout')

@php
    $selectedEdit = array_values((array) old('edit_posts', $journal->edit_post_ids ?? []));
    $selectedCloser = array_values((array) old('closer_posts', $journal->closer_post_ids ?? []));
    $sections = [
        'cover' => ['label' => 'Cover', 'mark' => '01'],
        'featured' => ['label' => 'Featured story', 'mark' => '02'],
    ];
    $errorTabs = [
        'eyebrow' => 'cover',
        'heading_lead' => 'cover',
        'heading_emphasis' => 'cover',
        'introduction' => 'cover',
        'caption' => 'cover',
        'cover_image_alt' => 'cover',
        'cover' => 'cover',
        'remove_cover' => 'cover',
        'edit_kicker' => 'featured',
        'edit_heading' => 'featured',
        'edit_posts' => 'featured',
        'closer_kicker' => 'cover',
        'closer_heading' => 'cover',
        'closer_posts' => 'cover',
        'maker_post_id' => 'cover',
        'maker_eyebrow' => 'cover',
        'maker_heading' => 'cover',
        'maker_body' => 'cover',
        'maker_label' => 'cover',
        'maker_path' => 'cover',
        'maker_image_alt' => 'cover',
        'maker_image' => 'cover',
        'remove_maker_image' => 'cover',
    ];
    $openSection = 'cover';
    foreach ($errors->keys() as $key) {
        $root = explode('.', (string) $key)[0];
        if (isset($errorTabs[$root])) {
            $openSection = $errorTabs[$root];
            break;
        }
    }
    $saved = ! $errors->any() && session('status') === 'Journal page saved.';
    $saveMode = $errors->any() ? 'error' : ($saved ? 'saved' : 'loaded');
    $saveLabel = match ($saveMode) {
        'error' => 'Check the highlighted fields',
        'saved' => 'Journal page saved',
        default => 'Existing content loaded',
    };
    $saveIcon = match ($saveMode) {
        'error' => 'bi-exclamation-circle',
        'saved' => 'bi-check-circle',
        default => 'bi-circle-fill',
    };
    $draftEyebrow = (string) old('eyebrow', $journal->eyebrow);
    $draftLead = (string) old('heading_lead', $journal->heading_lead);
    $draftEmphasis = (string) old('heading_emphasis', $journal->heading_emphasis);
    $draftIntro = (string) old('introduction', $journal->introduction);
    $previewFeature = $preview['featured'];
    $previewStories = $preview['stories'];
    $previewMode = $errors->any()
        ? 'Showing the draft from the rejected save. The public page is unchanged.'
        : 'Showing the saved journal.';
    $journalTitle = $openSection === 'featured' ? 'Journal page / Featured story' : 'Journal page / Cover';
    $journalLead = 'The introduction and the featured story are what the public journal shows. Both save together.';
    $coverFile = is_string($journal->cover_image) && $journal->cover_image !== ''
        ? basename(str_replace('\\', '/', $journal->cover_image))
        : null;
    $featuredValue = (string) ($selectedEdit[0] ?? '');
    $featuredPost = $featuredValue !== '' ? $posts->firstWhere('id', (int) $featuredValue) : null;
    $invalid = fn (string $name): string => $errors->has($name) ? ' is-invalid' : '';
@endphp

@section('documentTitle', 'Journal page editor')

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,560&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<script>
document.documentElement.classList.add('journal-editor-enhanced');
@if (! $errors->any())
(function () {
  var hash = location.hash.replace(/^#/, '');
  if (hash === 'edit') {
    hash = 'featured';
  }
  if (hash === 'overview' || hash === 'closer' || hash === 'maker') {
    hash = 'cover';
  }
  if (hash === 'cover' || hash === 'featured') {
    document.documentElement.dataset.journalSection = hash;
  }
})();
@endif
</script>
@endpush

@section('content')
<div
  class="home-studio journal-studio{{ $openSection === 'featured' ? ' is-featured-focus' : ' is-cover-focus' }}"
  data-journal-studio
  data-open-section="{{ $openSection }}"
  data-lock="{{ $errors->any() ? '1' : '0' }}"
  data-save-mode="{{ $saveMode }}"
>
  <nav class="home-path" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}">Admin</a>
    <span aria-hidden="true">/</span>
    <span>Content</span>
    <span aria-hidden="true">/</span>
    <span>Journal page</span>
    <span aria-hidden="true">/</span>
    <span data-crumb aria-current="page">{{ $sections[$openSection]['label'] }}</span>
  </nav>

  <div class="home-studio-top">
    <div>
      <p class="home-kicker">Royal Indian Stones / Content manager</p>
      <h1 data-journal-title>{{ $journalTitle }}</h1>
      <p class="home-lead" data-journal-lead>{{ $journalLead }}</p>
    </div>
    <div class="home-studio-actions">
      <p class="home-save-state is-{{ $saveMode }}" data-save-state role="status">
        <i class="bi {{ $saveIcon }}" data-save-icon aria-hidden="true"></i>
        <span data-save-label>{{ $saveLabel }}</span>
      </p>
      <button class="home-btn home-btn-ghost" type="button" data-preview-open>Preview</button>
      <a class="home-btn home-btn-ghost" href="{{ url('/blog') }}" target="_blank" rel="noopener noreferrer">
        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
        View public journal
      </a>
      <button class="home-btn home-btn-primary" type="submit" form="journal-settings">Save Journal Page</button>
    </div>
  </div>

  <div class="home-section-nav" role="tablist" aria-label="Journal page sections" data-section-nav>
    @foreach($sections as $key => $section)
      <button
        type="button"
        class="home-section-tab"
        role="tab"
        id="journal-tab-{{ $key }}"
        data-section-tab="{{ $key }}"
        data-label="{{ $section['label'] }}"
        aria-controls="journal-panel-{{ $key }}"
        aria-selected="{{ $openSection === $key ? 'true' : 'false' }}"
        tabindex="{{ $openSection === $key ? '0' : '-1' }}"
      >
        <span class="home-section-index">{{ $section['mark'] }}</span>
        {{ $section['label'] }}
      </button>
    @endforeach
  </div>

  @if($errors->any())
    <div class="home-alert" role="alert" tabindex="-1" data-journal-errors>
      <strong>Check the highlighted fields.</strong>
      <ul>
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form id="journal-settings" class="journal-studio-grid" method="post" enctype="multipart/form-data" action="{{ route('admin.journal.update') }}">
    @csrf
    @method('PUT')

    <div class="journal-main">
      <section id="journal-panel-cover" class="home-card journal-panel{{ $openSection === 'cover' ? ' is-open' : '' }}" data-journal-panel="cover" role="tabpanel" aria-labelledby="journal-tab-cover">
        <div class="home-card-head">
          <div class="home-card-title">
            <span class="home-index">01</span>
            <div>
              <h2>Cover</h2>
              <p>This wording is the introduction at the top of the public journal. The story photograph comes from the featured article.</p>
            </div>
          </div>
        </div>
        <div class="journal-panel-body">
          <div class="journal-cover-layout">
            <div>
              <h3 class="journal-subhead">Introduction</h3>
              <div class="journal-fields">
                <div class="journal-field">
                  <label class="form-label" for="eyebrow">Eyebrow</label>
                  <input class="form-control{{ $invalid('eyebrow') }}" id="eyebrow" name="eyebrow" value="{{ $draftEyebrow }}" maxlength="80" required @if($errors->has('eyebrow')) aria-invalid="true" aria-describedby="eyebrow-error" @endif>
                  <p class="journal-hint">Small line above the title.</p>
                  @error('eyebrow')
                    <p class="journal-field-error" id="eyebrow-error">{{ $message }}</p>
                  @enderror
                </div>
                <div class="journal-fields journal-fields-split">
                  <div class="journal-field">
                    <label class="form-label" for="heading_lead">Heading</label>
                    <input class="form-control{{ $invalid('heading_lead') }}" id="heading_lead" name="heading_lead" value="{{ $draftLead }}" maxlength="80" required @if($errors->has('heading_lead')) aria-invalid="true" aria-describedby="heading_lead-error" @endif>
                    @error('heading_lead')
                      <p class="journal-field-error" id="heading_lead-error">{{ $message }}</p>
                    @enderror
                  </div>
                  <div class="journal-field">
                    <label class="form-label" for="heading_emphasis">Italic line</label>
                    <input class="form-control{{ $invalid('heading_emphasis') }}" id="heading_emphasis" name="heading_emphasis" value="{{ $draftEmphasis }}" maxlength="80" required @if($errors->has('heading_emphasis')) aria-invalid="true" aria-describedby="heading_emphasis-error" @endif>
                    @error('heading_emphasis')
                      <p class="journal-field-error" id="heading_emphasis-error">{{ $message }}</p>
                    @enderror
                  </div>
                </div>
                <div class="journal-field">
                  <label class="form-label" for="introduction">Introduction</label>
                  <textarea class="form-control{{ $invalid('introduction') }}" id="introduction" name="introduction" rows="5" maxlength="500" required @if($errors->has('introduction')) aria-invalid="true" aria-describedby="introduction-error" @endif>{{ $draftIntro }}</textarea>
                  @error('introduction')
                    <p class="journal-field-error" id="introduction-error">{{ $message }}</p>
                  @enderror
                </div>
              </div>
            </div>
            <div>
              <h3 class="journal-subhead">Share image</h3>
              <p class="journal-hint">Used when the featured story has no photograph. It does not replace that story’s picture on the page.</p>
              @if($journal->cover_image)
                <div class="journal-photo-row">
                  <img class="journal-settings-preview" src="{{ asset($journal->cover_image) }}" alt="">
                  <div>
                    <p class="journal-photo-label">Saved cover photograph</p>
                    @if($coverFile)
                      <p class="journal-hint">{{ $coverFile }}</p>
                    @endif
                    <p class="journal-hint">This file remains until a replacement is saved.</p>
                  </div>
                </div>
                <label class="journal-check" for="remove_cover">
                  <input id="remove_cover" type="checkbox" name="remove_cover" value="1" @checked(old('remove_cover'))>
                  Use the standard cover photograph
                </label>
              @elseif($preview['standard_cover'])
                <div class="journal-photo-row">
                  <img class="journal-settings-preview" src="{{ $preview['standard_cover'] }}" alt="">
                  <div>
                    <p class="journal-photo-label">Standard cover photograph</p>
                    <p class="journal-hint">No replacement is saved.</p>
                  </div>
                </div>
              @endif
              <div class="journal-field">
                <label class="form-label" for="caption">Photograph caption</label>
                <input class="form-control{{ $invalid('caption') }}" id="caption" name="caption" value="{{ old('caption', $journal->caption) }}" maxlength="180" @if($errors->has('caption')) aria-invalid="true" aria-describedby="caption-error" @endif>
                <p class="journal-hint">Saved with the page. The public journal does not print this caption.</p>
                @error('caption')
                  <p class="journal-field-error" id="caption-error">{{ $message }}</p>
                @enderror
              </div>
              <div class="journal-field">
                <label class="form-label" for="cover_image_alt">Photograph description</label>
                <input class="form-control{{ $invalid('cover_image_alt') }}" id="cover_image_alt" name="cover_image_alt" value="{{ old('cover_image_alt', $journal->cover_image_alt) }}" maxlength="180" @if($errors->has('cover_image_alt')) aria-invalid="true" aria-describedby="cover_image_alt-error" @endif>
                @error('cover_image_alt')
                  <p class="journal-field-error" id="cover_image_alt-error">{{ $message }}</p>
                @enderror
              </div>
              <div class="journal-field">
                <label class="form-label" for="cover">Replace the cover photograph</label>
                <div class="journal-drop" data-cover-drop>
                  <input class="journal-drop-input{{ $invalid('cover') }}" id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" @if($errors->has('cover')) aria-invalid="true" aria-describedby="cover-error" @endif>
                  <span class="journal-drop-copy">Choose an image or drop it here</span>
                  <span class="journal-drop-note">Optional replacement</span>
                </div>
                @error('cover')
                  <p class="journal-field-error" id="cover-error">{{ $message }}</p>
                @enderror
                <p class="journal-field-error" id="cover-client-error" data-cover-file-error hidden></p>
                <div class="journal-photo-row" data-cover-selected hidden>
                  <img data-cover-selected-image alt="">
                  <div>
                    <p class="journal-photo-label" data-cover-selected-name></p>
                    <p class="journal-hint">Selected replacement. The saved photograph stays in place until this page is saved.</p>
                    <button class="home-btn home-btn-ghost" type="button" data-cover-cancel>Cancel this replacement</button>
                  </div>
                </div>
                <p class="journal-hint">JPG, PNG, or WebP. A chosen file stays selected while you switch sections and is stored only after a confirmed save.</p>
              </div>
            </div>
          </div>
          <div class="journal-panel-foot">
            <p>Open Preview to read this draft as the public introduction, then the featured story.</p>
            <div class="journal-foot-actions">
              <button class="home-btn home-btn-ghost" type="button" data-discard-cover>Discard</button>
              <button class="home-btn home-btn-primary" type="submit">Save Journal Page</button>
            </div>
          </div>
        </div>
      </section>

      <section id="journal-panel-featured" class="home-card journal-panel{{ $openSection === 'featured' ? ' is-open' : '' }}" data-journal-panel="featured" role="tabpanel" aria-labelledby="journal-tab-featured">
        <div class="home-card-head">
          <div class="home-card-title">
            <span class="home-index">02</span>
            <div>
              <h2>Featured story</h2>
              <p>The large story under the introduction. Leave it blank to feature the latest published story that has a photograph. A hidden article is skipped.</p>
            </div>
          </div>
        </div>
        <div class="journal-panel-body">
          <p class="journal-featured-status" data-featured-resolved>
            @if(is_array($previewFeature))
              The public journal features “{{ $previewFeature['title'] }}”.
            @else
              No published story is available to feature.
            @endif
          </p>
          <article class="journal-slot journal-featured-slot" data-featured-slot data-edit-slot="0">
            <div class="journal-slot-face journal-featured-face" data-slot-face>
              @if(! $featuredPost)
                <div>
                  <p class="journal-slot-auto">Latest published story</p>
                  <p class="journal-hint">Nothing is saved in this field. The public page uses the latest published story that has a photograph.</p>
                </div>
                <button class="home-btn home-btn-ghost" type="button" data-slot-choose>Choose a story</button>
              @else
                <div>
                  <p class="journal-slot-state">{{ $featuredPost->is_published ? 'Chosen' : 'Hidden' }}</p>
                  <p class="journal-slot-title">{{ $featuredPost->title }}</p>
                  <p class="journal-hint">Article {{ $featuredPost->id }}.@if(! $featuredPost->is_published) Hidden articles are skipped on the public page.@else This article is the featured story when it is public.@endif</p>
                </div>
                <div class="journal-slot-actions">
                  <button class="home-btn home-btn-ghost" type="button" data-slot-choose>Change</button>
                  <button class="home-btn home-btn-ghost" type="button" data-slot-clear>Use latest story</button>
                </div>
              @endif
            </div>
            <label class="journal-slot-label" for="edit-post-0">Featured story</label>
            <select class="form-select journal-slot-select{{ $invalid('edit_posts.0') }}" id="edit-post-0" name="edit_posts[]" @if($errors->has('edit_posts.0')) aria-invalid="true" aria-describedby="edit-post-0-error" @endif>
              <option value="">Not chosen</option>
              @foreach($posts as $post)
                <option value="{{ $post->id }}" @selected($featuredValue === (string) $post->id)>{{ $post->title }}{{ $post->is_published ? '' : ' (hidden)' }}</option>
              @endforeach
            </select>
            @error('edit_posts.0')
              <p class="journal-field-error" id="edit-post-0-error">{{ $message }}</p>
            @enderror
          </article>
          <p class="journal-note">Choosing a story stores its article ID. Clearing the field stores a blank, not article 0 and not the word latest. The story list on the public page leaves this featured article out.</p>
          <div hidden>
            <input id="edit_kicker" name="edit_kicker" value="{{ old('edit_kicker', $journal->edit_kicker) }}">
            <input id="edit_heading" name="edit_heading" value="{{ old('edit_heading', $journal->edit_heading) }}">
            @for($slot = 1; $slot < 3; $slot++)
              @php $slotValue = (string) ($selectedEdit[$slot] ?? ''); @endphp
              <select id="edit-post-{{ $slot }}" name="edit_posts[]" data-preserved-edit>
                <option value="">Not chosen</option>
                @foreach($posts as $post)
                  <option value="{{ $post->id }}" @selected($slotValue === (string) $post->id)>{{ $post->title }}{{ $post->is_published ? '' : ' (hidden)' }}</option>
                @endforeach
              </select>
            @endfor
            <input id="closer_kicker" name="closer_kicker" value="{{ old('closer_kicker', $journal->closer_kicker) }}">
            <input id="closer_heading" name="closer_heading" value="{{ old('closer_heading', $journal->closer_heading) }}">
            @for($slot = 0; $slot < 2; $slot++)
              @php $closerValue = (string) ($selectedCloser[$slot] ?? ''); @endphp
              <select id="closer-post-{{ $slot }}" name="closer_posts[]" data-preserved-edit>
                <option value="">Not chosen</option>
                @foreach($posts as $post)
                  <option value="{{ $post->id }}" @selected($closerValue === (string) $post->id)>{{ $post->title }}{{ $post->is_published ? '' : ' (hidden)' }}</option>
                @endforeach
              </select>
            @endfor
            <select id="maker_post_id" name="maker_post_id">
              <option value="">None</option>
              @foreach($posts as $post)
                <option value="{{ $post->id }}" @selected((string) old('maker_post_id', $journal->maker_post_id) === (string) $post->id)>{{ $post->title }}{{ $post->is_published ? '' : ' (hidden)' }}</option>
              @endforeach
            </select>
            <input id="maker_path" name="maker_path" value="{{ old('maker_path', $journal->maker_path) }}" @if($errors->has('maker_path')) aria-invalid="true" @endif>
            <input id="maker_eyebrow" name="maker_eyebrow" value="{{ old('maker_eyebrow', $journal->maker_eyebrow) }}">
            <input id="maker_heading" name="maker_heading" value="{{ old('maker_heading', $journal->maker_heading) }}">
            <textarea id="maker_body" name="maker_body">{{ old('maker_body', $journal->maker_body) }}</textarea>
            <input id="maker_label" name="maker_label" value="{{ old('maker_label', $journal->maker_label) }}">
            <input id="maker_image_alt" name="maker_image_alt" value="{{ old('maker_image_alt', $journal->maker_image_alt) }}">
            <input id="maker_image" name="maker_image" type="file" accept="image/jpeg,image/png,image/webp">
            @if(old('remove_maker_image'))
              <input type="checkbox" name="remove_maker_image" value="1" checked>
            @endif
          </div>
          <div class="journal-panel-foot">
            <p>Preview shows this choice with the cover wording. The public page changes after Save Journal Page.</p>
            <div class="journal-foot-actions">
              <button class="home-btn home-btn-ghost" type="button" data-discard-edit>Discard</button>
              <button class="home-btn home-btn-primary" type="submit">Save Journal Page</button>
            </div>
          </div>
        </div>
      </section>

    </div>
  </form>

  <div class="journal-preview-dialog" data-preview-dialog hidden role="dialog" aria-modal="true" aria-labelledby="journal-preview-title">
    <div class="journal-preview-dialog-panel journal-preview-column">
      <div class="journal-picker-head">
        <div>
          <h2 id="journal-preview-title">Public journal preview</h2>
          <p>Approximate preview of the public /blog landing, not the live page. The introduction follows this draft. The featured story is the chosen public article, or the latest published story that has a photograph.</p>
        </div>
        <button class="home-btn home-btn-ghost" type="button" data-preview-close>Close</button>
      </div>
      <div class="journal-preview-tools">
        <div class="home-mode" role="group" aria-label="Preview width">
          <button type="button" data-cover-device="desktop" aria-pressed="true">Desktop</button>
          <button type="button" data-cover-device="mobile" aria-pressed="false">Mobile</button>
        </div>
        <a class="home-btn home-btn-ghost" href="{{ url('/blog') }}" target="_blank" rel="noopener noreferrer">Open public page</a>
      </div>
      <div class="journal-preview-paper" data-preview-paper>
        <div class="journal-preview-intro">
          <div>
            <p class="journal-preview-eyebrow" data-preview-eyebrow>{{ $draftEyebrow }}</p>
            <p class="journal-preview-heading">
              <span data-preview-lead>{{ $draftLead }}</span>
              <em data-preview-emphasis>{{ $draftEmphasis }}</em>
            </p>
          </div>
          <p class="journal-preview-lede" data-preview-intro>{{ $draftIntro }}</p>
        </div>
        @if($preview['empty'])
          <div class="journal-preview-empty" data-preview-empty>
            <h3>Stories are on the way.</h3>
            <p>There are no published stories yet. The workshop and the stone collection are open while the journal grows.</p>
          </div>
        @else
          <article class="journal-preview-feature" data-preview-feature>
            @if(is_array($previewFeature))
              <a class="journal-preview-feature-link" href="{{ $previewFeature['url'] }}" target="_blank" rel="noopener noreferrer">
                <div class="journal-preview-feature-media">
                  @if($previewFeature['image'])
                    <img src="{{ $previewFeature['image'] }}" alt="{{ $previewFeature['alt'] }}">
                  @else
                    <p class="journal-preview-nophoto">No photograph on this story.</p>
                  @endif
                </div>
                <div class="journal-preview-feature-copy">
                  <p class="journal-preview-kicker">Featured story
                    @if($previewFeature['category'])
                      <span aria-hidden="true"> / </span>{{ $previewFeature['category'] }}
                    @endif
                  </p>
                  <h3 class="journal-preview-feature-title">{{ $previewFeature['title'] }}</h3>
                  @if($previewFeature['summary'] !== '')
                    <p class="journal-preview-summary">{{ $previewFeature['summary'] }}</p>
                  @endif
                  <p class="journal-preview-meta">{{ $previewFeature['minutes'] }} min read</p>
                  <span class="journal-preview-button">Read the story</span>
                </div>
              </a>
            @endif
          </article>
          <section class="journal-preview-latest" aria-labelledby="journal-preview-latest-title">
            <h3 id="journal-preview-latest-title" class="journal-preview-latest-heading">Latest stories</h3>
            <div class="journal-preview-stories" data-preview-stories>
              @foreach($previewStories as $story)
                <article class="journal-preview-story">
                  <a href="{{ $story['url'] }}" target="_blank" rel="noopener noreferrer">
                    <div class="journal-preview-story-media">
                      @if($story['image'])
                        <img src="{{ $story['image'] }}" alt="{{ $story['decorative'] ? '' : $story['alt'] }}">
                      @else
                        <p class="journal-preview-nophoto">No photograph</p>
                      @endif
                    </div>
                    <h4 class="journal-preview-story-title">{{ $story['title'] }}</h4>
                    @if($story['summary'] !== '')
                      <p class="journal-preview-summary">{{ $story['summary'] }}</p>
                    @endif
                    <p class="journal-preview-meta">{{ $story['minutes'] }} min read</p>
                  </a>
                </article>
              @endforeach
            </div>
            <p class="journal-preview-none" data-preview-none @if($previewStories !== []) hidden @endif>No further published stories.</p>
            <p class="journal-preview-more" data-preview-more @if($preview['more'] < 1) hidden @endif>@if($preview['more'] === 1)1 more published story is on the public page.@elseif($preview['more'] > 1){{ $preview['more'] }} more published stories are on the public page.@endif</p>
          </section>
        @endif
      </div>
      <p class="journal-preview-caption">
        This preview shows up to {{ $preview['story_limit'] }} latest stories; the public page lists {{ $preview['page_size'] }} per page.
        The cover photograph is the share image only when the featured story has no photograph.
      </p>
      <p class="journal-preview-mode" data-preview-mode>{{ $previewMode }}</p>
    </div>
  </div>

  <div class="journal-picker" data-article-picker hidden role="dialog" aria-modal="true" aria-labelledby="journal-picker-title" aria-describedby="journal-picker-context">
    <div class="journal-picker-panel">
      <div class="journal-picker-head">
        <div>
          <h2 id="journal-picker-title">Select a published story</h2>
          <p id="journal-picker-context" data-picker-context>Published stories only. The field stores the article ID.</p>
        </div>
        <button class="home-btn home-btn-ghost" type="button" data-picker-close>Close</button>
      </div>
      <div class="journal-picker-tools">
        <label class="journal-field" for="journal-picker-search">Search by title
          <input class="form-control" id="journal-picker-search" type="search" data-picker-search placeholder="Search by article title">
        </label>
        <label class="journal-field" for="journal-picker-state">Publish state
          <select class="form-select" id="journal-picker-state" data-picker-state>
            <option value="published">Published</option>
          </select>
        </label>
        <label class="journal-field" for="journal-picker-sort">Sort
          <select class="form-select" id="journal-picker-sort" data-picker-sort>
            <option value="newest">Newest</option>
            <option value="title">Title</option>
          </select>
        </label>
      </div>
      <div class="journal-picker-list" data-picker-list aria-label="Published stories"></div>
      <p class="journal-hint" data-picker-empty hidden>No published articles match that title.</p>
      <div class="journal-picker-foot">
        <p class="journal-hint">Clear slot leaves the field blank. It does not save article 0 or the word latest.</p>
        <div class="journal-slot-actions">
          <button class="home-btn home-btn-ghost" type="button" data-picker-clear>Clear slot</button>
          <button class="home-btn home-btn-primary" type="button" data-picker-use disabled>Use selected story</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="application/json" id="journal-preview-data">{!! json_encode(['cards' => $preview['cards'], 'order' => $preview['order'], 'fallbackId' => $preview['fallback_id'], 'storyLimit' => $preview['story_limit']], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/journal-editor.js') }}?v=9"></script>
@endpush
