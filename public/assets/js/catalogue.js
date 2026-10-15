(function () {
  'use strict';
  const form=document.getElementById('product_filters');if(!form)return;
  const results=document.getElementById('product_results'),count=document.getElementById('product_count'),more=document.getElementById('load_more_products'),empty=document.getElementById('catalogue_empty');
  const controls={q:document.getElementById('product_search'),material:document.getElementById('filter_material'),color:document.getElementById('filter_color'),finish:document.getElementById('filter_finish'),application:document.getElementById('filter_application')};
  let products=[],matches=[],limit=24,timer;
  const params=new URLSearchParams(location.search);Object.entries(controls).forEach(([key,el])=>el.value=params.get(key)||'');
  function card(product) {
    const link=document.createElement('a');link.className='catalogue-card';link.href=product.url;link.dataset.track='product_view';link.dataset.product=product.id;
    const img=document.createElement('img');img.src=product.image;img.alt=product.name;img.loading='lazy';img.decoding='async';img.width=product.imageWidth||600;img.height=product.imageHeight||450;if(product.imageSrcset){img.srcset=product.imageSrcset;img.sizes='(max-width: 450px) 100vw, (max-width: 991px) 50vw, 33vw';}link.append(img);
    const body=document.createElement('div');body.className='catalogue-card-body';
    const material=document.createElement('p');material.className='eyebrow';material.textContent=product.category;
    const title=document.createElement('h2');title.textContent=product.name;
    const detail=document.createElement('p');detail.className='catalogue-card-detail';detail.textContent=[product.color,...product.finishes.slice(0,2)].filter(Boolean).join(' · ');
    const cta=document.createElement('span');cta.className='text-link';cta.textContent='View stone →';body.append(material,title,detail,cta);link.append(body);return link;
  }
  function paint() {
    results.replaceChildren(...matches.slice(0,limit).map(card));results.setAttribute('aria-busy','false');count.textContent=`${matches.length} ${matches.length===1?'product':'products'} found · showing ${Math.min(limit,matches.length)}`;empty.hidden=matches.length!==0;more.hidden=matches.length<=limit;
  }
  function update(sendEvent) {
    const filters=Object.fromEntries(Object.entries(controls).map(([k,el])=>[k,el.value]));
    matches=RISCatalogue.filterProducts(products,filters).sort((a,b)=>document.getElementById('product_sort').value==='material' ? a.category.localeCompare(b.category)||a.name.localeCompare(b.name) : a.name.localeCompare(b.name));
    limit=24;paint();const query=new URLSearchParams();Object.entries(filters).forEach(([k,v])=>{if(v)query.set(k,v);});history.replaceState(null,'',location.pathname+(query.size?'?'+query:''));
    if(sendEvent && window.risTrack) window.risTrack('catalogue_search',{result_count:matches.length,material:filters.material,filter_count:Object.values(filters).filter(Boolean).length});
  }
  function options(control, values) {
    const selected=params.get(control.name)||'';
    [...new Set(values)].filter(Boolean).sort((a,b)=>a.localeCompare(b)).forEach(value=>{const option=document.createElement('option');option.value=value;option.textContent=value;control.append(option);});control.value=selected;if(!control.value)control.value='';
  }
  fetch('assets/data/products.json').then(r=>{if(!r.ok)throw new Error('Catalogue unavailable');return r.json();}).then(data=>{
    products=data;options(controls.material,products.map(p=>p.category));options(controls.color,products.flatMap(p=>RISCatalogue.colorTokens(p.color)));options(controls.finish,products.flatMap(p=>p.finishes));options(controls.application,products.flatMap(p=>p.applications));update(false);
  }).catch(()=>{results.setAttribute('aria-busy','false');count.textContent='The catalogue could not be loaded. Please refresh or browse by stone category.';const link=document.createElement('a');link.href='natural-stones-of-india.html';link.textContent='Browse stone categories';results.append(link);});
  form.addEventListener('submit',e=>{e.preventDefault();clearTimeout(timer);update(true);});controls.q.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>update(true),250);});
  Object.values(controls).filter(el=>el!==controls.q).forEach(el=>el.addEventListener('change',()=>update(true)));form.addEventListener('reset',()=>{clearTimeout(timer);setTimeout(()=>update(true),0);});document.getElementById('product_sort').addEventListener('change',()=>update(false));more.addEventListener('click',()=>{limit+=24;paint();});
})();
