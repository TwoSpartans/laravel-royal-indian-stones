@extends('layouts.site')

@section('content')
<main id="main" class="catalogue-preview">
  <section class="catalogue-intro">
    <div class="container">
      @fragment('preview')
        <div class="catalogue-preview-copy">
          <p class="eyebrow">E-catalogue</p>
          <h1>{{ $catalogue->title }}</h1>
          @if(filled($catalogue->description))
            <p>{{ $catalogue->description }}</p>
          @endif
          <div class="button-row">
            <a class="ris-button" href="{{ $pdfUrl }}" download="{{ $pdfName }}">Download PDF</a>
          </div>
        </div>
      @endfragment
      <div class="button-row catalogue-preview-back">
        <a class="ris-button ris-button-outline" href="{{ route('e-catalogue') }}">Back to e-catalogue</a>
      </div>
    </div>
  </section>
  <section class="catalogue-preview-stage">
    <div class="container">
      @fragment('viewer')
        <iframe class="catalogue-preview-frame" src="{{ $pdfUrl }}" title="Preview of {{ $catalogue->title }}"></iframe>
        <p class="catalogue-preview-note">If the preview does not appear in your browser, use Download PDF.</p>
      @endfragment
    </div>
  </section>
</main>
@endsection
