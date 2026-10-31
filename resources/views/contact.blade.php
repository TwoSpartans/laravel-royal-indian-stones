@extends('layouts.site')

@section('content')
<main id="main">
  <section id="contact" class="contact">
    <div class="container contact-layout">
      <aside class="contact-details">
        <h2>Talk to our team</h2>
        <p>Royal Indian Exports<br>Rajpurohit Bhawan, Plot-17<br>Golf Course Road, Air Force<br>Jodhpur-342011, Rajasthan, India</p>
        <p><strong>Call</strong><br><a href="tel:+918769873114" data-track="phone_click">+91 876 987 3114</a></p>
        <p><strong>Email</strong><br><a href="mailto:info@royalindianstones.com">info@royalindianstones.com</a><br><a href="mailto:royalindianstones@gmail.com">royalindianstones@gmail.com</a></p>
        <p class="company-registration">IEC: 1309006687<br>GST: 08ASYPP8166A1Z4</p>
        <a class="text-link" href="{{ url('/products') }}">Browse our stones →</a>
      </aside>
      <form id="contact_form" class="quote-form" action="{{ route('enquiry.store') }}" method="post">
        @csrf
        <h2>Request a quote</h2>
        <p>Fields marked <span aria-hidden="true">*</span> are required. Other details are optional.</p>
        <div id="selected_product" class="selected-product" @if(!$selected) hidden @endif>
          <span>Enquiring about</span>
          <strong id="selected_product_name">{{ $selected?->name }}</strong>
          <a id="selected_product_link" href="{{ $selected ? url('/'.$selected->slug) : url('/products') }}">View product</a>
        </div>
        <div class="form-grid">
          <div><label for="name">Your name *</label><input id="name" name="name" autocomplete="name" required maxlength="120"></div>
          <div><label for="email">Email address *</label><input id="email" name="email" type="email" autocomplete="email" required maxlength="254"></div>
          <div><label for="product">Stone / product</label><input id="product" name="product" maxlength="180" placeholder="Product name, if known" value="{{ $selected?->name }}"></div>
          <div><label for="destination">Destination country</label><input id="destination" name="destination" autocomplete="country-name" maxlength="100"></div>
          <div><label for="quantity">Quantity</label><input id="quantity" name="quantity" maxlength="100" placeholder="For example, 200 m²"></div>
          <div><label for="dimensions">Dimensions / thickness</label><input id="dimensions" name="dimensions" maxlength="120" placeholder="Include your preferred units"></div>
          <div><label for="finish">Preferred finish</label><input id="finish" name="finish" maxlength="120" placeholder="For example, natural or honed"></div>
          <div><label for="subject">Subject *</label><input id="subject" name="subject" required maxlength="200" value="{{ $subject }}"></div>
          <div class="full-width"><label for="message">Your requirements *</label><textarea id="message" name="message" rows="5" required minlength="10" maxlength="8000" placeholder="Tell us about your project, application and any specific requirements."></textarea></div>
        </div>
        <div class="form-trap" aria-hidden="true"><label for="contact_website">Leave this empty</label><input id="contact_website" name="website" tabindex="-1" autocomplete="off"></div>
        <div id="contact_status" class="form-status" role="status" aria-live="polite" tabindex="-1"></div>
        <p class="form-privacy">We use these details to respond to your enquiry. Read our <a href="{{ url('/privacy-policy') }}">privacy policy</a>.</p>
        <button type="submit" class="ris-button">Send Enquiry <span aria-hidden="true">→</span></button>
        <noscript><p>You can submit this form without JavaScript; the next page will show the result.</p></noscript>
      </form>
    </div>
  </section>
</main>
@endsection
