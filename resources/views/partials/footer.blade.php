<footer id="footer">
  <div class="footer-top">
    <div class="container">
      <div class="row">
        <div class="col-lg-5 col-md-6 footer-contact">
          <h4>ROYAL INDIAN EXPORTS</h4>
          <p>
            Rajpurohit Bhawan <br>
            Plot-17, Golf Course Road, Air Force<br>
            Jodhpur-342011, Rajasthan, India <br><br>
            <strong>Phone:</strong> <a href="tel:+918769873114" data-track="phone_click">+91 876 987 3114</a><br>
            <strong>Email:</strong> <a href="mailto:info@royalindianstones.com" rel="nofollow" class="footer-email">info@royalindianstones.com</a><br>
            <strong>Email:</strong> <a href="mailto:royalindianstones@gmail.com" rel="nofollow" class="footer-email">royalindianstones@gmail.com</a>
          </p>
        </div>
        <div class="col-lg-2 col-md-6 footer-links">
          <h4>Useful Links</h4>
          <ul>
            <li><i class="bx bx-chevron-right" aria-hidden="true"></i> <a href="{{ url('/') }}">Home</a></li>
            <li><i class="bx bx-chevron-right" aria-hidden="true"></i> <a href="{{ url('/faq') }}">FAQ</a></li>
            <li><i class="bx bx-chevron-right" aria-hidden="true"></i> <a href="{{ url('/sitemap') }}">Sitemap</a></li>
            <li><i class="bx bx-chevron-right" aria-hidden="true"></i> <a href="{{ url('/disclaimer') }}">Disclaimer</a></li>
            <li><i class="bx bx-chevron-right" aria-hidden="true"></i> <a href="{{ url('/privacy-policy') }}">Privacy Policy</a></li>
          </ul>
        </div>
        <div class="col-lg-2 col-md-6 footer-links">
          <h4>Useful Informations</h4>
          <ul>
            <li><i class="bx bx-chevron-right" aria-hidden="true"></i> <a href="{{ url('/natural-stone-care') }}">Natural Stone Care</a></li>
            <li><i class="bx bx-chevron-right" aria-hidden="true"></i> <a href="{{ url('/general-instruction') }}">General Instruction</a></li>
            <li><i class="bx bx-chevron-right" aria-hidden="true"></i> <a href="{{ url('/packaging-type') }}">Packaging Type</a></li>
            <li><i class="bx bx-chevron-right" aria-hidden="true"></i> <a href="{{ url('/quality-control') }}">Quality Control</a></li>
            <li><i class="bx bx-chevron-right" aria-hidden="true"></i> <a href="{{ url('/project-gallery') }}">Project Gallery</a></li>
          </ul>
        </div>
        <div class="col-lg-3 col-md-6 footer-newsletter">
          <h4>Join Our Newsletter</h4>
          <p>We don’t do mail to spam and your mail id is very confidential.</p>
          <form id="newsletter_form" action="{{ route('newsletter.store') }}" method="post">
            @csrf
            <label class="visually-hidden" for="email_address">Email address</label>
            <input type="email" name="email_address" id="email_address" placeholder="Email Address" autocomplete="email" maxlength="254" required>
            <input type="submit" value="Subscribe" id="email_submit">
            <div class="form-trap" aria-hidden="true"><label for="newsletter_website">Leave this empty</label><input id="newsletter_website" name="website" tabindex="-1" autocomplete="off"></div>
          </form>
          <div id="email_address_status" class="email-address-status" role="status" aria-live="polite"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="container d-md-flex py-4">
    <div class="me-md-auto text-center text-md-start">
      <div class="copyright">&copy; Copyright <strong><span><a href="{{ url('/') }}">Royal Indian Stones</a></span></strong> All Rights Reserved</div>
    </div>
    <div class="social-links text-center text-md-right pt-3 pt-md-0">
      <a href="https://www.facebook.com/RoyalIndianStones" target="_blank" rel="noopener noreferrer" class="facebook" aria-label="Facebook"><i class="bx bxl-facebook" aria-hidden="true"></i></a>
      <a href="https://twitter.com/Indian_Stones" target="_blank" rel="noopener noreferrer" class="twitter" aria-label="Twitter"><i class="bx bxl-twitter" aria-hidden="true"></i></a>
      <a href="https://www.instagram.com/indianstoneexports" target="_blank" rel="noopener noreferrer" class="instagram" aria-label="Instagram"><i class="bx bxl-instagram" aria-hidden="true"></i></a>
      <a href="https://www.linkedin.com/company/royal-indian-stones" target="_blank" rel="noopener noreferrer" class="linkedin" aria-label="LinkedIn"><i class="bx bxl-linkedin" aria-hidden="true"></i></a>
      <a href="https://in.pinterest.com/royalindianstones" target="_blank" rel="noopener noreferrer" aria-label="Pinterest"><i class="bi bi-pinterest" aria-hidden="true"></i></a>
      <a href="https://www.youtube.com/@royalindianstones" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><i class="bx bxl-youtube" aria-hidden="true"></i></a>
    </div>
  </div>
</footer>
