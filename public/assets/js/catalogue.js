(function () {
  'use strict';
  const form = document.getElementById('product_filters');
  if (!form) return;
  const results = document.getElementById('product_results');
  const count = document.getElementById('product_count');
  const more = document.getElementById('load_more_products');
  const empty = document.getElementById('catalogue_empty');
  const controls = {
    q: document.getElementById('product_search'),
    material: document.getElementById('filter_material'),
    color: document.getElementById('filter_color'),
    finish: document.getElementById('filter_finish'),
    application: document.getElementById('filter_application'),
  };
  let products = [];
  let matches = [];
  let limit = 24;
  let timer;
  const params = new URLSearchParams(location.search);
  Object.entries(controls).forEach(([key, el]) => { el.value = params.get(key) || ''; });

  function card(product) {
    const link = document.createElement('a');
    link.className = 'catalogue-card';
    link.href = product.url;
    link.dataset.track = 'product_view';
    link.dataset.product = product.id;
    const picture = document.createElement('picture');
    const sizes = '(max-width: 575px) calc(100vw - 40px), (max-width: 991px) calc(50vw - 24px), calc(33vw - 24px)';
    if (product.avifSrcset) {
      const source = document.createElement('source');
      source.type = 'image/avif';
      source.srcset = product.avifSrcset;
      source.sizes = sizes;
      picture.append(source);
    }
    if (product.webpSrcset) {
      const source = document.createElement('source');
      source.type = 'image/webp';
      source.srcset = product.webpSrcset;
      source.sizes = sizes;
      picture.append(source);
    }
    const img = document.createElement('img');
    img.src = product.image;
    img.alt = product.name;
    img.loading = 'lazy';
    img.decoding = 'async';
    img.width = product.imageWidth || 600;
    img.height = product.imageHeight || 450;
    picture.append(img);
    link.append(picture);
    const body = document.createElement('div');
    body.className = 'catalogue-card-body';
    const title = document.createElement('h2');
    title.textContent = product.name;
    const material = document.createElement('p');
    material.className = 'eyebrow';
    material.textContent = product.category;
    const detail = document.createElement('p');
    detail.className = 'catalogue-card-detail';
    detail.textContent = [product.color, ...product.finishes.slice(0, 2)].filter(Boolean).join(' · ');
    body.append(title, material, detail);
    link.append(body);
    return link;
  }

  function paint() {
    const shown = Math.min(limit, matches.length);
    results.replaceChildren(...matches.slice(0, limit).map(card));
    results.setAttribute('aria-busy', 'false');
    count.textContent = matches.length === 0 ? 'Showing 0 of 0' : `Showing 1–${shown} of ${matches.length}`;
    empty.hidden = matches.length !== 0;
    more.hidden = matches.length <= limit;
  }

  function update(sendEvent) {
    const filters = Object.fromEntries(Object.entries(controls).map(([key, el]) => [key, el.value]));
    matches = RISCatalogue.filterProducts(products, filters).sort((a, b) => document.getElementById('product_sort').value === 'material'
      ? a.category.localeCompare(b.category) || a.name.localeCompare(b.name)
      : a.name.localeCompare(b.name));
    limit = 24;
    paint();
    const query = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => { if (value) query.set(key, value); });
    history.replaceState(null, '', location.pathname + (query.size ? '?' + query : ''));
    if (sendEvent && window.risTrack) window.risTrack('catalogue_search', { result_count: matches.length, material: filters.material, filter_count: Object.values(filters).filter(Boolean).length });
  }

  function options(control, values, listId) {
    const selected = params.get(control.name) || '';
    const counts = new Map();
    values.filter(Boolean).forEach((value) => counts.set(value, (counts.get(value) || 0) + 1));
    const unique = [...counts.keys()].sort((a, b) => a.localeCompare(b));
    unique.forEach((value) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      control.append(option);
    });
    control.value = selected;
    if (!control.value) control.value = '';
    const list = document.getElementById(listId);
    if (!list) return;
    const addChoice = (parent, value, label, total) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.value = value;
      const name = document.createElement('span');
      name.textContent = label;
      button.append(name);
      if (total !== null) {
        const countLabel = document.createElement('span');
        countLabel.textContent = String(total);
        button.append(countLabel);
      }
      parent.append(button);
      return button;
    };
    addChoice(list, '', 'All', null);
    unique.forEach((value) => addChoice(list, value, value, counts.get(value)));
    const chips = listId === 'material_list' ? document.getElementById('material_chips') : null;
    if (chips) {
      addChoice(chips, '', 'All', null);
      unique.forEach((value) => addChoice(chips, value, value, null));
    }
    const mark = () => {
      [list, chips].filter(Boolean).forEach((parent) => {
        parent.querySelectorAll('button').forEach((button) => button.classList.toggle('is-active', button.dataset.value === control.value));
      });
    };
    const choose = (event) => {
      const button = event.target.closest('button');
      if (!button || button.id === 'finish_more') return;
      control.value = button.dataset.value !== '' && button.dataset.value === control.value ? '' : button.dataset.value;
      control.dispatchEvent(new Event('change'));
    };
    list.addEventListener('click', choose);
    if (chips) chips.addEventListener('click', choose);
    const selectedChip = listId === 'material_list' ? document.getElementById('material_selected') : null;
    if (selectedChip) {
      selectedChip.addEventListener('click', () => {
        control.value = '';
        control.dispatchEvent(new Event('change'));
      });
    }
    const paintSelection = () => {
      mark();
      if (!selectedChip) return;
      selectedChip.hidden = control.value === '';
      selectedChip.textContent = control.value === '' ? '' : control.value + ' ×';
    };
    if (listId === 'finish_list') {
      const ranked = [...list.querySelectorAll('button')].filter((button) => button.dataset.value !== '')
        .sort((a, b) => Number(b.querySelector('span:last-child').textContent) - Number(a.querySelector('span:last-child').textContent) || a.dataset.value.localeCompare(b.dataset.value));
      const top = new Set(ranked.slice(0, 5).map((button) => button.dataset.value));
      const moreButton = document.getElementById('finish_more');
      const finishFinder = document.getElementById('finish_finder');
      const applyFinishWindow = () => {
        const term = (finishFinder?.value || '').trim().toLowerCase();
        const expanded = list.dataset.expanded === 'true';
        ranked.forEach((button) => {
          const value = button.dataset.value;
          const matches = term === '' || value.toLowerCase().includes(term);
          const pinned = value === control.value;
          button.hidden = !(matches && (term !== '' || expanded || top.has(value) || pinned));
        });
        const hiddenCount = ranked.filter((button) => !top.has(button.dataset.value) && button.dataset.value !== control.value).length;
        if (moreButton) {
          moreButton.hidden = term !== '' || hiddenCount === 0;
          moreButton.textContent = expanded ? 'Show less' : `+${hiddenCount} more`;
        }
      };
      moreButton?.addEventListener('click', () => {
        list.dataset.expanded = list.dataset.expanded === 'true' ? 'false' : 'true';
        applyFinishWindow();
      });
      finishFinder?.addEventListener('input', applyFinishWindow);
      finishFinder?.addEventListener('keydown', (event) => { if (event.key === 'Enter') event.preventDefault(); });
      control.addEventListener('change', applyFinishWindow);
      if (control.value) document.getElementById('filter-finish')?.setAttribute('open', '');
      applyFinishWindow();
    }
    control.addEventListener('change', paintSelection);
    paintSelection();
  }

  fetch('assets/data/products.json').then((response) => {
    if (!response.ok) throw new Error('Catalogue unavailable');
    return response.json();
  }).then((data) => {
    products = data;
    options(controls.material, products.map((product) => product.category), 'material_list');
    options(controls.color, products.flatMap((product) => RISCatalogue.colorTokens(product.color)), 'color_list');
    options(controls.finish, products.flatMap((product) => product.finishes), 'finish_list');
    options(controls.application, products.flatMap((product) => product.applications), 'application_list');
    update(false);
  }).catch(() => {
    results.setAttribute('aria-busy', 'false');
    count.textContent = 'The catalogue could not be loaded. Please refresh or browse by stone category.';
    const link = document.createElement('a');
    link.href = 'natural-stones-of-india.html';
    link.textContent = 'Browse stone categories';
    results.append(link);
  });

  const finder = document.getElementById('material_finder');
  if (finder) {
    finder.addEventListener('input', () => {
      const term = finder.value.trim().toLowerCase();
      document.querySelectorAll('#material_list button').forEach((button) => {
        button.hidden = term !== '' && button.dataset.value !== '' && !button.dataset.value.toLowerCase().includes(term);
      });
    });
  }

  form.addEventListener('submit', (event) => { event.preventDefault(); clearTimeout(timer); update(true); });
  controls.q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => update(true), 250); });
  Object.values(controls).filter((el) => el !== controls.q).forEach((el) => el.addEventListener('change', () => update(true)));
  document.getElementById('clear_filters')?.addEventListener('click', (event) => {
    event.preventDefault();
    clearTimeout(timer);
    Object.values(controls).forEach((el) => { el.value = ''; });
    if (finder) finder.value = '';
    const finishFinder = document.getElementById('finish_finder');
    const finishList = document.getElementById('finish_list');
    if (finishFinder) finishFinder.value = '';
    if (finishList) finishList.dataset.expanded = 'false';
    Object.values(controls).forEach((el) => el.dispatchEvent(new Event('change')));
    finder?.dispatchEvent(new Event('input'));
    finishFinder?.dispatchEvent(new Event('input'));
    history.replaceState(null, '', location.pathname);
    setDrawer(false);
  });
  document.getElementById('product_sort').addEventListener('change', () => update(false));
  more.addEventListener('click', () => { limit += 24; paint(); });

  const sidebar = document.getElementById('filters-sidebar');
  const filterToggle = document.getElementById('filter-toggle-button');
  const backdrop = document.getElementById('filter-backdrop');
  const desktopFilters = () => window.matchMedia('(min-width: 992px)').matches;
  function setDrawer(open) {
    if (!sidebar || desktopFilters()) {
      sidebar?.classList.remove('is-open');
      if (backdrop) backdrop.hidden = true;
      document.body.classList.remove('shop-filters-lock');
      filterToggle?.setAttribute('aria-expanded', 'false');
      return;
    }
    sidebar.classList.toggle('is-open', open);
    if (backdrop) backdrop.hidden = !open;
    document.body.classList.toggle('shop-filters-lock', open);
    filterToggle?.setAttribute('aria-expanded', String(open));
  }
  filterToggle?.addEventListener('click', () => setDrawer(!sidebar.classList.contains('is-open')));
  backdrop?.addEventListener('click', () => setDrawer(false));
  document.querySelector('.shop-filters-close')?.addEventListener('click', () => setDrawer(false));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setDrawer(false); });
  window.addEventListener('resize', () => { if (desktopFilters()) setDrawer(false); });
})();
