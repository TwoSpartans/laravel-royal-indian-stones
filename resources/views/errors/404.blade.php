@extends('layouts.site')

@section('content')
<main id="main">
  <section class="catalogue-intro">
    <div class="container">
      <h1>Page not found</h1>
      <p>That page is not in the collection.</p>
      <p><a class="ris-button" href="{{ url('/products') }}">Browse stones</a></p>
    </div>
  </section>
</main>
@endsection
