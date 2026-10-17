@extends('layouts.site')

@section('content')
<main id="main">
  <section class="catalogue-intro">
    <div class="container">
      <h1>Sitemap</h1>
      <p>Every public page, grouped so you can find a stone or a company page.</p>
    </div>
  </section>
  <section class="sitemap-page">
    <div class="container">
      <nav class="sitemap-jump" aria-label="Sitemap sections">
        <a href="#sitemap-pages">Pages <span>{{ $pageCount }}</span></a>
        <a href="#sitemap-blog">Blog <span>{{ $posts->count() }}</span></a>
        @foreach($categories as $category)
          <a href="#sitemap-{{ $category->slug }}">{{ $category->name }} <span>{{ $productsByCategory->get($category->id, collect())->count() }}</span></a>
        @endforeach
      </nav>

      <section id="sitemap-pages" class="sitemap-block">
        <h2>Pages <span class="sitemap-count">{{ $pageCount }}</span></h2>
        <div class="sitemap-group">
          <h3>Start here</h3>
          <ul class="sitemap-links">
            @foreach($featuredPages as $page)
              <li><a href="{{ $page['url'] }}">{{ $page['label'] }}</a></li>
            @endforeach
          </ul>
        </div>
        @foreach($pageGroups as $label => $links)
          <div class="sitemap-group">
            <h3>{{ $label }}</h3>
            <ul class="sitemap-links">
              @foreach($links as $page)
                <li><a href="{{ $page['url'] }}">{{ $page['label'] }}</a></li>
              @endforeach
            </ul>
          </div>
        @endforeach
      </section>

      <section id="sitemap-blog" class="sitemap-block">
        <h2>Blog <span class="sitemap-count">{{ $posts->count() }}</span></h2>
        <ul class="sitemap-links sitemap-posts">
          @foreach($posts as $post)
            <li>
              @if($post->published_on)
                <time datetime="{{ $post->published_on->toDateString() }}">{{ $post->published_on->format('j M Y') }}</time>
              @endif
              <a href="{{ url('/'.$post->slug) }}">{{ $post->title }}</a>
            </li>
          @endforeach
        </ul>
      </section>

      @foreach($categories as $category)
        @php $stones = $productsByCategory->get($category->id, collect()); @endphp
        <section id="sitemap-{{ $category->slug }}" class="sitemap-block">
          <h2>
            <a href="{{ url('/'.$category->slug) }}">{{ $category->name }}</a>
            <span class="sitemap-count">{{ $stones->count() }}</span>
          </h2>
          @if($stones->isEmpty())
            <p class="sitemap-empty">This category has a page, and no individual stones yet.</p>
          @else
            <ul class="sitemap-links">
              @foreach($stones as $product)
                <li><a href="{{ url('/'.$product->slug) }}">{{ $product->name }}</a></li>
              @endforeach
            </ul>
          @endif
        </section>
      @endforeach
    </div>
  </section>
</main>
@endsection
