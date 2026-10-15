@extends('layouts.site')

@section('content')
<main id="main">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <h2>FAQ</h2>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          <li>FAQ</li>
        </ol>
      </div>
    </div>
  </section>
  <section id="blog" class="blog">
    <div class="container">
      <div class="text-center">
        <h1>FAQ</h1>
      </div>
      <hr>
      <div class="faq-container">
        @foreach($faqs as $faq)
          <h2>{{ $faq->question }}</h2>
          <div>{!! $faq->answer !!}</div>
          @if(!$loop->last)<hr>@endif
        @endforeach
        <p>If you have any questions, write to <a href="mailto:info@royalindianstones.com">info@royalindianstones.com</a> or <a href="{{ route('contact') }}">send a message</a>.</p>
      </div>
    </div>
  </section>
</main>
@endsection
