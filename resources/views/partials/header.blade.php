<a class="skip-link" href="#main">Skip to content</a>
<section id="topbar" class="d-flex align-items-center">
  <div class="container d-flex justify-content-center justify-content-md-between">
    <div class="contact-info d-flex align-items-center">
      <i class="bi bi-envelope d-flex align-items-center"><a href="mailto:info@royalindianstones.com">info@royalindianstones.com</a></i>
    </div>
    <div class="social-links d-none d-md-flex align-items-center">
      <a href="https://www.facebook.com/RoyalIndianStones" target="_blank" rel="noopener noreferrer" class="facebook" title="Facebook" aria-label="Facebook"><i class="bi bi-facebook" aria-hidden="true"></i></a>
      <a href="https://twitter.com/Indian_Stones" target="_blank" rel="noopener noreferrer" class="twitter" title="Twitter" aria-label="Twitter"><i class="bi bi-twitter" aria-hidden="true"></i></a>
      <a href="https://www.instagram.com/indianstoneexports" target="_blank" rel="noopener noreferrer" class="instagram" title="Instagram" aria-label="Instagram"><i class="bi bi-instagram" aria-hidden="true"></i></a>
      <a href="https://www.linkedin.com/company/royal-indian-stones" target="_blank" rel="noopener noreferrer" class="linkedin" title="Linkedin" aria-label="LinkedIn"><i class="bi bi-linkedin" aria-hidden="true"></i></a>
      <a href="https://in.pinterest.com/royalindianstones" target="_blank" rel="noopener noreferrer" class="pinterest" title="Pinterest" aria-label="Pinterest"><i class="bi bi-pinterest" aria-hidden="true"></i></a>
      <a href="https://www.youtube.com/@royalindianstones" target="_blank" rel="noopener noreferrer" class="youtube" title="Youtube" aria-label="YouTube"><i class="bi bi-youtube" aria-hidden="true"></i></a>
    </div>
  </div>
</section>
<header id="header" class="d-flex align-items-center">
  <div class="container d-flex justify-content-between align-items-center">
    <a class="logo" href="{{ url('/') }}">Royal Indian Stones</a>
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
            <li><a href="{{ url('/blog') }}">Blog</a></li>
          </ul>
        </li>
        <li><a href="{{ route('contact') }}" @class(['nav-quote', 'active' => ($active ?? '') === 'contact']) @if(($active ?? '') === 'contact') aria-current="page" @endif data-track="quote_click">Request a Quote</a></li>
      </ul>
      <a class="header-search" href="{{ route('catalogue') }}" aria-label="Search products"><i class="bi bi-search" aria-hidden="true"></i></a>
      <button class="mobile-nav-toggle bi bi-list" type="button" aria-label="Open navigation" aria-controls="navbar" aria-expanded="false"></button>
    </nav>
  </div>
</header>
