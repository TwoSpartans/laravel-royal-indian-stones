@extends('layouts.site')

@section('content')
<main id="main">
  <section class="catalogue-intro">
    <div class="container">
      <p class="eyebrow">Royal Indian Stones</p>
      <h1>{{ $pageTitle }}</h1>
      <p>This page is being brought into the new site. You can browse the stone collection or send your requirements while it is prepared.</p>
      <p><a class="ris-button" href="{{ url('/products') }}">Browse stones</a> <a class="text-link" href="{{ route('contact') }}">Request a quote</a></p>
    </div>
  </section>
</main>
@endsection
