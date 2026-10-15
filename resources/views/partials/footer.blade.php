<footer id="footer">
  <div class="footer-top"><div class="container"><div class="row gy-4">
    <div class="col-lg-4 col-md-6 footer-contact">
      <h3>Royal Indian Stones</h3>
      <p>Part of Royal Indian Exports.<br>Natural stone for architecture, interiors and landscaping.</p>
      <p>Rajpurohit Bhawan, Plot-17<br>Golf Course Road, Air Force<br>Jodhpur-342011, Rajasthan, India</p>
      <p><a href="tel:+918769873114" data-track="phone_click">+91 876 987 3114</a><br><a href="mailto:info@royalindianstones.com">info@royalindianstones.com</a></p>
      <div class="footer-social">
        <a href="https://www.facebook.com/RoyalIndianStones" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="bx bxl-facebook" aria-hidden="true"></i></a>
        <a href="https://twitter.com/Indian_Stones" target="_blank" rel="noopener noreferrer" aria-label="Twitter"><i class="bx bxl-twitter" aria-hidden="true"></i></a>
        <a href="https://www.instagram.com/indianstoneexports" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="bx bxl-instagram" aria-hidden="true"></i></a>
        <a href="https://www.linkedin.com/company/royal-indian-stones" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="bx bxl-linkedin" aria-hidden="true"></i></a>
        <a href="https://in.pinterest.com/royalindianstones" target="_blank" rel="noopener noreferrer" aria-label="Pinterest"><i class="bi bi-pinterest" aria-hidden="true"></i></a>
        <a href="https://www.youtube.com/@royalindianstones" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><i class="bx bxl-youtube" aria-hidden="true"></i></a>
      </div>
    </div>
    <div class="col-lg-2 col-md-6 footer-links"><h4>Explore</h4><ul>
      <li><a href="{{ url('/products') }}">Find your stone</a></li>
      <li><a href="{{ url('/natural-stones-of-india') }}">Stone categories</a></li>
      <li><a href="{{ url('/stone-articles') }}">Stone articles</a></li>
      <li><a href="{{ url('/project-gallery') }}">Project gallery</a></li>
      <li><a href="{{ url('/aboutus') }}">About us</a></li>
      <li><a href="{{ route('contact') }}">Request a quote</a></li>
    </ul></div>
    <div class="col-lg-2 col-md-6 footer-links"><h4>Resources</h4><ul>
      <li><a href="{{ url('/quality-control') }}">Quality control</a></li>
      <li><a href="{{ url('/packaging-type') }}">Packaging</a></li>
      <li><a href="{{ url('/natural-stone-care') }}">Stone care</a></li>
      <li><a href="{{ url('/general-instruction') }}">Installation</a></li>
      <li><a href="{{ url('/faq') }}">FAQ</a></li>
      <li><a href="{{ url('/blog') }}">Journal</a></li>
    </ul></div>
    <div class="col-lg-4 col-md-6 footer-newsletter"><h4>Stone updates, by email</h4>
      <p>Subscribe to our newsletter for product updates.</p>
      <form id="newsletter_form" class="newsletter-form" action="{{ route('newsletter.store') }}" method="post">
        @csrf
        <label for="email_address">Email address</label>
        <div class="newsletter-fields"><input type="email" name="email_address" id="email_address" autocomplete="email" maxlength="254" required><button type="submit" id="email_submit">Subscribe</button></div>
        <div class="form-trap" aria-hidden="true"><label for="newsletter_website">Leave this empty</label><input id="newsletter_website" name="website" tabindex="-1" autocomplete="off"></div>
        <p id="email_address_status" class="form-status" role="status" aria-live="polite"></p>
      </form>
      <p class="newsletter-privacy">Read our <a href="{{ url('/privacy-policy') }}">privacy policy</a>.</p>
    </div>
  </div></div></div>
  <div class="container footer-bottom"><p>© Royal Indian Stones. All rights reserved.</p><div><a href="{{ url('/sitemap') }}">Sitemap</a><a href="{{ url('/privacy-policy') }}">Privacy</a><a href="{{ url('/disclaimer') }}">Disclaimer</a></div></div>
</footer>
