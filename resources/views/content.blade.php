@extends('layouts.site')

@section('content')
<main id="main">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <h2>{{ $page->title }}</h2>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          <li>{{ $page->title }}</li>
        </ol>
      </div>
    </div>
  </section>
  {!! $page->body !!}
</main>
@endsection
