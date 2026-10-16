(function () {
  'use strict';
  function track(name, detail) {
    if (typeof window.gtag === 'function') window.gtag('event', name, detail || {});
  }
  window.risTrack = track;
  document.addEventListener('click', function (event) {
    const target = event.target.closest('[data-track]');
    if (target) track(target.dataset.track, { product_id: target.dataset.product || document.body.dataset.productId || '' });
  });
  const toggle = document.querySelector('.mobile-nav-toggle'), nav = document.getElementById('navbar');
  if (toggle && nav) {
    const update = () => { const open = nav.classList.contains('navbar-mobile'); toggle.setAttribute('aria-expanded', String(open)); toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation'); };
    toggle.addEventListener('click', update);
    document.addEventListener('keydown', e => {if(e.key === 'Escape') {nav.classList.remove('navbar-mobile');toggle.classList.add('bi-list');toggle.classList.remove('bi-x');update();}});
    window.addEventListener('resize', () => {if(window.innerWidth > 1199){nav.classList.remove('navbar-mobile');toggle.classList.add('bi-list');toggle.classList.remove('bi-x');update();}});
  }
  async function postForm(form, status, label) {
    if (form.dataset.submitting === 'true' || !form.reportValidity()) return;
    form.dataset.submitting = 'true';
    const button = form.querySelector('[type="submit"]'), previous = button.innerHTML;
    button.disabled = true; button.textContent = 'Sending…';
    status.textContent = 'Sending your '+label+'…'; status.className = 'form-status';
    form.setAttribute('aria-busy', 'true');
    const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch(form.action, { method:'POST', body:new URLSearchParams(new FormData(form)), headers:{Accept:'application/json'}, signal:controller.signal });
      if(!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Unexpected server response');
      const result = await response.json();
      if(!response.ok || !result.ok) {
        status.textContent = result.message || 'We could not send this request. Please try again.'; status.classList.add('is-error');
        track(label === 'enquiry' ? 'enquiry_error' : 'newsletter_error',{reason:'server',status_code:response.status}); return;
      }
      status.textContent = result.message; status.classList.add('is-success');
      track(label === 'enquiry' ? 'enquiry_success' : 'newsletter_success',{product_id:document.body.dataset.enquiryProduct || ''}); form.reset();
    } catch (error) {
      status.textContent = error.name === 'AbortError' ? 'The request timed out. It may have reached us; please contact us by email before resubmitting.' : 'Unable to connect. Your details are still here. Please try again or email info@royalindianstones.com.'; status.classList.add('is-error');
      track(label === 'enquiry' ? 'enquiry_error' : 'newsletter_error',{reason:error.name === 'AbortError' ? 'timeout' : 'network'});
    } finally {
      clearTimeout(timeout);button.disabled=false;button.innerHTML=previous;form.dataset.submitting='false';form.removeAttribute('aria-busy');
    }
  }
  const contact = document.getElementById('contact_form');
  const homeEnquiry = document.getElementById('home_enquiry_form');
  const bindEnquiry = (form, statusId) => {
    form.addEventListener('submit', e => {e.preventDefault();postForm(form,document.getElementById(statusId),'enquiry');});
    form.addEventListener('input', function started() {track('enquiry_start',{product_id:document.body.dataset.enquiryProduct || ''});form.removeEventListener('input',started);});
  };
  if(contact) {
    bindEnquiry(contact,'contact_status');
    const params = new URLSearchParams(location.search), productId = params.get('product'), project = params.get('project');
    if(project && project.length <= 180) document.getElementById('subject').value='Project enquiry: '+project;
    if(productId && /^[a-z0-9-]+$/i.test(productId)) {
      fetch('assets/data/products.json').then(r=>{if(!r.ok)throw new Error('Catalogue unavailable');return r.json();}).then(products=>{
        const product=products.find(p=>p.id===productId);if(!product)return;
        for(const [id,value] of [['product',product.name],['subject','Quotation: '+product.name]]) {document.getElementById(id).value=value;document.getElementById(id).defaultValue=value;}
        document.getElementById('selected_product_name').textContent=product.name;document.getElementById('selected_product_link').href=product.url;document.getElementById('selected_product').hidden=false;document.body.dataset.enquiryProduct=product.id;
      }).catch(()=>{document.getElementById('contact_status').textContent='Please enter the stone name in your enquiry; product details could not be loaded.';});
    }
  }
  if(homeEnquiry) bindEnquiry(homeEnquiry,'home_enquiry_status');
  const newsletter=document.getElementById('newsletter_form');
  if(newsletter) newsletter.addEventListener('submit', e=>{e.preventDefault();postForm(newsletter,document.getElementById('email_address_status'),'subscription');});
})();
