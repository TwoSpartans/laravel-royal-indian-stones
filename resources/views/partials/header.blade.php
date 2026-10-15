<a class="skip-link" href="#main">Skip to content</a>
<div id="topbar" class="d-flex align-items-center">
  <div class="container topbar-inner">
    <span>Natural stone from India · Export enquiries welcome</span>
    <div><a href="tel:+918769873114" data-track="phone_click">+91 876 987 3114</a><a href="mailto:info@royalindianstones.com">info@royalindianstones.com</a></div>
  </div>
</div>
<header id="header" class="d-flex align-items-center">
  <div class="container d-flex justify-content-between align-items-center">
    <a class="logo" href="{{ url('/') }}" aria-label="Royal Indian Stones home"><img src="{{ asset('assets/img/royal-indian-stones-logo.png') }}" alt="Royal Indian Stones" width="350" height="63"></a>
    <nav id="navbar" class="navbar" aria-label="Main navigation">
      <ul>
        <li><a href="{{ url('/') }}" @class(['active' => ($active ?? '') === 'home']) @if(($active ?? '') === 'home') aria-current="page" @endif>Home</a></li>
        <li class="dropdown"><a href="{{ url('/products') }}" @class(['active' => ($active ?? '') === 'products']) @if(($active ?? '') === 'products') aria-current="page" @endif><span>Our Stones</span><i class="bi bi-chevron-down" aria-hidden="true"></i></a>
          <ul>
            <li><a href="{{ url('/products') }}">Search all products</a></li>
            <li><a href="{{ url('/sandstone') }}">Sandstone</a></li>
            <li><a href="{{ url('/limestone') }}">Limestone</a></li>
            <li><a href="{{ url('/marble') }}">Marble</a></li>
            <li><a href="{{ url('/granite') }}">Granite</a></li>
            <li><a href="{{ url('/slate') }}">Slate</a></li>
            <li><a href="{{ url('/quartzite') }}">Quartzite</a></li>
            <li><a href="{{ url('/stone-pebbles') }}">Pebbles</a></li>
            <li><a href="{{ url('/stone-cobbles') }}">Cobbles</a></li>
            <li><a href="{{ url('/stone-chips') }}">Stone chips</a></li>
            <li><a href="{{ url('/stone-basalt') }}">Basalt</a></li>
            <li><a href="{{ url('/stone-soap') }}">Soapstone</a></li>
            <li><a href="{{ url('/stone-travertine') }}">Travertine</a></li>
            <li><a href="{{ url('/stone-articles') }}">Stone articles</a></li>
          </ul>
        </li>
        <li><a href="{{ url('/project-gallery') }}" @class(['active' => ($active ?? '') === 'projects']) @if(($active ?? '') === 'projects') aria-current="page" @endif>Projects</a></li>
        <li><a href="{{ url('/aboutus') }}" @class(['active' => ($active ?? '') === 'about']) @if(($active ?? '') === 'about') aria-current="page" @endif>About Us</a></li>
        <li class="dropdown"><a href="{{ url('/general-instruction') }}" @class(['active' => ($active ?? '') === 'resources']) @if(($active ?? '') === 'resources') aria-current="page" @endif><span>Resources</span><i class="bi bi-chevron-down" aria-hidden="true"></i></a>
          <ul>
            <li><a href="{{ url('/quality-control') }}">Quality control</a></li>
            <li><a href="{{ url('/packaging-type') }}">Packaging</a></li>
            <li><a href="{{ url('/natural-stone-care') }}">Stone care</a></li>
            <li><a href="{{ url('/general-instruction') }}">Installation guidance</a></li>
            <li><a href="{{ url('/faq') }}">FAQ</a></li>
            <li><a href="{{ url('/blog') }}">Journal</a></li>
          </ul>
        </li>
        <li><a href="{{ route('contact') }}" @class(['active' => ($active ?? '') === 'contact']) @if(($active ?? '') === 'contact') aria-current="page" @endif>Contact</a></li>
        <li><a class="nav-quote" href="{{ route('contact') }}" data-track="quote_click">Request a Quote</a></li>
      </ul>
      <button class="mobile-nav-toggle bi bi-list" type="button" aria-label="Open navigation" aria-controls="navbar" aria-expanded="false"></button>
    </nav>
  </div>
</header>
