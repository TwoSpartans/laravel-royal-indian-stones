(function(root) {
  'use strict';
  const normalize = value => String(value || '').toLowerCase().replace(/[-_]/g,' ').replace(/\s+/g,' ').trim();
  const colorTokens = value => String(value || '').split(/,|\/|\band\b/i).map(v=>v.trim()).filter(Boolean);
  function filterProducts(products, filters) {
    const terms=normalize(filters.q).split(' ').filter(Boolean);
    return products.filter(p=>{
      const haystack=normalize([p.name,p.category,p.color,p.finishes.join(' '),p.applications.join(' ')].join(' '));
      return terms.every(term=>haystack.includes(term)) && (!filters.material || p.category===filters.material) && (!filters.color || colorTokens(p.color).includes(filters.color)) && (!filters.finish || p.finishes.some(v=>normalize(v)===normalize(filters.finish))) && (!filters.application || p.applications.includes(filters.application));
    });
  }
  root.RISCatalogue={normalize,colorTokens,filterProducts};
})(typeof window==='undefined'?globalThis:window);
