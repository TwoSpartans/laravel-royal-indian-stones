
(function() {
  "use strict";

  /**
   * Easy selector helper function
   */
  const select = (el, all = false) => {
    el = el.trim()
    if (all) {
      return [...document.querySelectorAll(el)]
    } else {
      return document.querySelector(el)
    }
  }

  /**
   * Easy event listener function
   */
  const on = (type, el, listener, all = false) => {
    let selectEl = select(el, all)
    if (selectEl) {
      if (all) {
        selectEl.forEach(e => e.addEventListener(type, listener))
      } else {
        selectEl.addEventListener(type, listener)
      }
    }
  }

  /**
   * Easy on scroll event listener 
   */
  const onscroll = (el, listener) => {
    el.addEventListener('scroll', listener)
  }

  /**
   * Scrolls to an element with header offset
   */
  const scrollto = (el) => {
    let header = select('#header')
    let offset = header.offsetHeight

    if (!header.classList.contains('header-scrolled')) {
      offset -= 16
    }

    let elementPos = select(el).offsetTop
    window.scrollTo({
      top: elementPos - offset,
      behavior: 'smooth'
    })
  }

  /**
   * Header fixed top on scroll
   */
  let selectHeader = select('#header')
  if (selectHeader) {
    let headerOffset = selectHeader.offsetTop
    let nextElement = selectHeader.nextElementSibling
    const headerFixed = () => {
      if ((headerOffset - window.scrollY) <= 0) {
        selectHeader.classList.add('fixed-top')
        nextElement.classList.add('scrolled-offset')
      } else {
        selectHeader.classList.remove('fixed-top')
        nextElement.classList.remove('scrolled-offset')
      }
    }
    window.addEventListener('load', headerFixed)
    window.addEventListener('scroll', headerFixed, { passive: true })
  }

  /**
   * Back to top button
   */
  let backtotop = select('.back-to-top')
  if (backtotop) {
    const toggleBacktotop = () => {
      if (window.scrollY > 300) {
        backtotop.classList.add('active')
      } else {
        backtotop.classList.remove('active')
      }
    }
    window.addEventListener('load', toggleBacktotop)
    window.addEventListener('scroll', toggleBacktotop, { passive: true })
    backtotop.addEventListener('click', (event) => {
      event.preventDefault()
      backtotop.classList.add('is-bouncing')
      window.setTimeout(() => backtotop.classList.remove('is-bouncing'), 700)
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' })
    })
  }

  /**
   * Mobile nav toggle
   */
  on('click', '.mobile-nav-toggle', function(e) {
    select('#navbar').classList.toggle('navbar-mobile')
    this.classList.toggle('bi-list')
    this.classList.toggle('bi-x')
  })

  /**
   * Mobile nav dropdowns activate
   */
  on('click', '.navbar .dropdown > a', function(e) {
    if (select('#navbar').classList.contains('navbar-mobile')) {
      e.preventDefault()
      this.nextElementSibling.classList.toggle('dropdown-active')
    }
  }, true)

  /**
   * Scrool with ofset on links with a class name .scrollto
   */
  on('click', '.scrollto', function(e) {
    if (select(this.hash)) {
      e.preventDefault()

      let navbar = select('#navbar')
      if (navbar.classList.contains('navbar-mobile')) {
        navbar.classList.remove('navbar-mobile')
        let navbarToggle = select('.mobile-nav-toggle')
        navbarToggle.classList.toggle('bi-list')
        navbarToggle.classList.toggle('bi-x')
      }
      scrollto(this.hash)
    }
  }, true)

  /**
   * Scroll with ofset on page load with hash links in the url
   */
  window.addEventListener('load', () => {
    if (window.location.hash) {
      if (select(window.location.hash)) {
        scrollto(window.location.hash)
      }
    }
  });

  /**
   * Hero carousel indicators
   */
  const heroCarouselIndicators = select("#hero-carousel-indicators")
  const heroCarouselItems = select('#heroCarousel .carousel-item', true)
  const heroCarousel = select('#heroCarousel')

  if (heroCarousel && heroCarouselIndicators) {
    heroCarouselItems.forEach((item, index) => {
      const indicator = document.createElement('button')
      indicator.type = 'button'
      indicator.setAttribute('data-bs-target', '#heroCarousel')
      indicator.setAttribute('data-bs-slide-to', String(index))
      indicator.setAttribute('aria-label', 'Show slide ' + (index + 1))
      if (index === 0) {
        indicator.classList.add('active')
        indicator.setAttribute('aria-current', 'true')
      }
      heroCarouselIndicators.append(indicator)
    })

    heroCarousel.addEventListener('slid.bs.carousel', () => {
      heroCarouselIndicators.querySelectorAll('button').forEach((indicator) => {
        if (indicator.classList.contains('active')) {
          indicator.setAttribute('aria-current', 'true')
        } else {
          indicator.removeAttribute('aria-current')
        }
      })
      if (heroCanAdvance()) {
        restartHeroMotion()
      }
    })

    const narrowQuery = window.matchMedia('(max-width: 767px)')
    const reducedQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
    let slideChosen = false
    const heroCarouselInstance = () => bootstrap.Carousel.getOrCreateInstance(heroCarousel)
    const heroCanAdvance = () => false
    const restartHeroMotion = () => {
      heroCarousel.classList.remove('is-advancing')
      void heroCarousel.offsetWidth
      heroCarousel.classList.add('is-advancing')
    }
    const holdHero = () => {
      heroCarousel.classList.add('is-held')
      heroCarouselInstance().pause()
    }
    const releaseHero = () => {
      if (!heroCanAdvance()) {
        holdHero()
        return
      }
      heroCarousel.classList.remove('is-held')
      restartHeroMotion()
      heroCarouselInstance().cycle()
    }

    heroCarousel.addEventListener('mouseenter', holdHero)
    heroCarousel.addEventListener('mouseleave', releaseHero)
    heroCarousel.addEventListener('focusin', holdHero)
    heroCarousel.addEventListener('focusout', (event) => {
      if (!heroCarousel.contains(event.relatedTarget)) {
        releaseHero()
      }
    })
    heroCarousel.addEventListener('click', (event) => {
      if (event.target.closest('[data-bs-slide], [data-bs-slide-to]')) {
        slideChosen = true
        holdHero()
      }
    })
    narrowQuery.addEventListener('change', releaseHero)

    window.addEventListener('DOMContentLoaded', () => {
      if (heroCanAdvance()) {
        restartHeroMotion()
      } else {
        holdHero()
      }
    })
    window.addEventListener('load', () => {
      if (!heroCanAdvance()) {
        holdHero()
      }
    })
  }

  /**
   * Skills animation
   */
  let skilsContent = select('.skills-content');
  if (skilsContent) {
    new Waypoint({
      element: skilsContent,
      offset: '80%',
      handler: function(direction) {
        let progress = select('.progress .progress-bar', true);
        progress.forEach((el) => {
          el.style.width = el.getAttribute('aria-valuenow') + '%'
        });
      }
    })
  }

  /**
   * Porfolio isotope and filter
   */
  window.addEventListener('load', () => {
    let portfolioContainer = select('.portfolio-container');
    if (portfolioContainer) {
      let portfolioIsotope = new Isotope(portfolioContainer, {
        itemSelector: '.portfolio-item',
        layoutMode: 'fitRows'
      });

      let portfolioFilters = select('#portfolio-flters li', true);

      on('click', '#portfolio-flters li', function(e) {
        e.preventDefault();
        portfolioFilters.forEach(function(el) {
          el.classList.remove('filter-active');
        });
        this.classList.add('filter-active');

        portfolioIsotope.arrange({
          filter: this.getAttribute('data-filter')
        });
        portfolioIsotope.on('arrangeComplete', function() {
          AOS.refresh()
        });
      }, true);
    }

  });

  /**
   * Initiate portfolio lightbox 
   */
  const portfolioLightbox = GLightbox({
    selector: '.portfolio-lightbox'
  });

  /**
   * Portfolio details slider
   */
  const productSlider = document.querySelector('.portfolio-details-slider');
  if (productSlider) {
    const slideCount = productSlider.querySelectorAll('.swiper-slide').length;
    new Swiper(productSlider, {
      speed: 400,
      loop: slideCount > 1,
      watchOverflow: true
    });
  }

  /**
   * Animation on scroll
   */
  window.addEventListener('load', () => {
    AOS.init({
      duration: 1000,
      easing: 'ease-in-out',
      once: true,
      mirror: false
    })
  });

})()