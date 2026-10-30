(function () {
  var root = document.querySelector('[data-home-studio]');
  var heroForm = document.getElementById('home-panel-hero');
  var dataNode = document.getElementById('home-opening-slides');
  if (!root || !heroForm || !dataNode || root.dataset.ready === '1') {
    return;
  }
  root.dataset.ready = '1';

  var data;
  try {
    data = JSON.parse(dataNode.textContent || '');
  } catch (error) {
    return;
  }
  if (!data || !Array.isArray(data.slides) || data.slides.length === 0) {
    return;
  }

  var slides = data.slides;
  var errors = data.errors && typeof data.errors === 'object' && !Array.isArray(data.errors) ? data.errors : {};
  var objectUrls = {};
  var submitting = false;
  var currentSlot = slides[0].slot;
  var currentTab = 'content';
  var currentSection = root.dataset.openSection || 'hero';
  var textFields = ['eyebrow', 'title', 'subtitle', 'body', 'button_label', 'link', 'alt'];
  var tabFields = {
    content: ['eyebrow', 'title', 'subtitle', 'body', 'button_label', 'link', 'alt'],
    image: ['photo', 'restore']
  };
  var saveIcons = {
    saved: 'bi-check-circle',
    dirty: 'bi-exclamation-circle',
    error: 'bi-exclamation-circle',
    saving: 'bi-arrow-repeat'
  };
  var saveLabels = {
    saved: 'All changes saved',
    dirty: 'Unsaved changes',
    error: 'Check the highlighted fields',
    saving: 'Saving…'
  };
  var discardButton = heroForm.querySelector('[data-discard]');
  var supplierForm = document.getElementById('home-panel-supplier');
  var supplierNode = document.getElementById('home-supplier-line');
  var supplierData = null;
  var supplierFields = ['heading', 'body', 'button_label'];
  if (supplierNode) {
    try {
      supplierData = JSON.parse(supplierNode.textContent || '');
    } catch (error) {
      supplierData = null;
    }
  }
  if (!supplierData || typeof supplierData !== 'object' || Array.isArray(supplierData)) {
    supplierData = null;
  }
  var supplierDiscard = supplierForm ? supplierForm.querySelector('[data-discard]') : null;
  var exploreForm = document.getElementById('home-panel-explore');
  var exploreDrawer = exploreForm ? exploreForm.querySelector('[data-explore-drawer]') : null;
  var exploreDiscard = exploreForm ? exploreForm.querySelector('[data-discard]') : null;
  var exploreReturnFocus = null;
  var exploreSnapshot = null;
  var exploreObjectUrls = {};
  var exploreClosing = false;
  var stonesForm = document.getElementById('home-panel-stones');
  var stonesDrawer = stonesForm ? stonesForm.querySelector('[data-stones-drawer]') : null;
  var stonesReturnFocus = null;
  var stoneSnapshot = null;
  var stonesDiscard = stonesForm ? stonesForm.querySelector('[data-discard]') : null;
  var stonesObjectUrls = {};
  var stonesPage = 1;
  var stonesPageSize = 6;
  var stonesQuery = '';

  function pad(number) {
    return String(number).padStart(2, '0');
  }

  function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(value);
    }
    return String(value).replace(/"/g, '\\"');
  }

  function slidePanel(slot) {
    return root.querySelector('.home-slide-fields[data-slide="' + cssEscape(slot) + '"]');
  }

  function field(slot, name) {
    var panel = slidePanel(slot);
    return panel ? panel.querySelector('[data-field="' + name + '"]') : null;
  }

  function slideBySlot(slot) {
    return slides.find(function (slide) { return slide.slot === slot; }) || null;
  }

  function published(slide, key) {
    var input = field(slide.slot, key);
    var fallback = slide.defaults && typeof slide.defaults[key] === 'string' ? slide.defaults[key] : '';
    if (!input || input.type === 'file' || input.type === 'checkbox') {
      return fallback;
    }
    return input.value.trim() === '' ? fallback : input.value.trim();
  }

  function revoke(slot) {
    if (!objectUrls[slot]) {
      return;
    }
    URL.revokeObjectURL(objectUrls[slot]);
    delete objectUrls[slot];
  }

  function sourceFor(slide) {
    if (objectUrls[slide.slot]) {
      return objectUrls[slide.slot];
    }
    var restore = field(slide.slot, 'restore');
    if (restore && restore.checked) {
      return slide.fallbackImage || '';
    }
    return slide.image || slide.fallbackImage || '';
  }

  function stateLabel(slide) {
    var photo = field(slide.slot, 'photo');
    if (photo && photo.files && photo.files.length > 0) {
      return 'New photograph selected';
    }
    var restore = field(slide.slot, 'restore');
    if (restore && restore.checked) {
      return 'Original photograph';
    }
    return slide.custom ? 'Image replaced' : 'Original photograph';
  }

  function removeSummaryItem(text) {
    if (!text) {
      return;
    }
    var items = root.querySelectorAll('[data-home-errors] li');
    for (var index = 0; index < items.length; index += 1) {
      if (items[index].textContent.trim() === text) {
        items[index].remove();
        return;
      }
    }
  }

  function syncSummary() {
    var summary = root.querySelector('[data-home-errors]');
    var list = summary ? summary.querySelector('ul') : null;
    if (summary && list && list.children.length === 0) {
      summary.hidden = true;
    }
  }

  function forgetError(input) {
    var panel = input.closest('.home-slide-fields');
    var slot = panel ? panel.dataset.slide : '';
    var name = input.dataset.field || '';
    if (!slot || !name || !Array.isArray(errors[slot])) {
      return;
    }
    errors[slot] = errors[slot].filter(function (item) { return item !== name; });
  }

  function clearFieldError(input) {
    input.classList.remove('is-invalid');
    input.removeAttribute('aria-invalid');
    var wrap = input.closest('.home-field') || input.closest('.home-photo');
    var message = wrap ? wrap.querySelector('.home-field-error') : null;
    if (message && !message.hidden) {
      var text = message.textContent.trim();
      if (text !== '') {
        removeSummaryItem(text);
      }
      message.hidden = true;
      message.textContent = '';
    }
    forgetError(input);
    syncSummary();
    markTabErrors();
  }

  function showFileError(panel, message) {
    var node = panel ? panel.querySelector('[data-file-error]') : null;
    if (!node) {
      return;
    }
    var previous = node.hidden ? '' : node.textContent.trim();
    if (!message) {
      node.hidden = true;
      node.textContent = '';
      if (previous) {
        removeSummaryItem(previous);
      }
      syncSummary();
      return;
    }
    if (previous && previous !== message) {
      removeSummaryItem(previous);
      syncSummary();
    }
    node.hidden = false;
    node.textContent = message;
  }

  function fileMessage(file) {
    var type = (file.type || '').toLowerCase();
    var allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    var extensionOk = /\.(jpe?g|png|webp)$/i.test(file.name || '');
    if (type && allowed.indexOf(type) === -1) {
      return 'Use a JPG, PNG, or WebP image.';
    }
    if (!type && !extensionOk) {
      return 'Use a JPG, PNG, or WebP image.';
    }
    if (file.size > 5 * 1024 * 1024) {
      return 'Use an image smaller than 5 MB.';
    }
    return '';
  }

  function updatePips() {
    root.querySelectorAll('[data-preview-dot]').forEach(function (button) {
      var on = button.dataset.previewDot === currentSlot;
      button.classList.toggle('is-active', on);
      if (on) {
        button.setAttribute('aria-current', 'true');
      } else {
        button.removeAttribute('aria-current');
      }
    });
  }

  function fillSizePreviews() {
    var dialog = root.querySelector('[data-preview-sizes-dialog]');
    var hero = root.querySelector('[data-preview-frame] .home-preview-hero');
    if (!dialog || !hero) {
      return;
    }
    dialog.querySelectorAll('[data-size-slot]').forEach(function (slot) {
      slot.replaceChildren();
      var shell = document.createElement('div');
      shell.className = 'home-preview-frame' + (slot.dataset.sizeSlot === 'mobile' ? ' is-mobile' : '');
      shell.appendChild(hero.cloneNode(true));
      slot.appendChild(shell);
    });
  }

  function openSizePreviews() {
    var dialog = root.querySelector('[data-preview-sizes-dialog]');
    if (!dialog) {
      return;
    }
    fillSizePreviews();
    if (!dialog.open) {
      dialog.showModal();
    }
  }

  function closeSizePreviews() {
    var dialog = root.querySelector('[data-preview-sizes-dialog]');
    if (dialog && dialog.open) {
      dialog.close();
    }
  }

  function renderPreview(slide, src, alt) {
    var image = root.querySelector('[data-preview="image"]');
    if (image) {
      if (src) {
        image.src = src;
        image.hidden = false;
      } else {
        image.removeAttribute('src');
        image.hidden = true;
      }
      image.alt = alt;
    }
    var eyebrow = root.querySelector('[data-preview="eyebrow"]');
    if (eyebrow) {
      eyebrow.textContent = published(slide, 'eyebrow');
    }
    var title = root.querySelector('[data-preview="title"]');
    if (title) {
      title.replaceChildren(document.createTextNode(published(slide, 'title')));
      if (slide.secondLine) {
        var second = published(slide, 'subtitle');
        if (second !== '') {
          title.append(document.createElement('br'));
          title.append(document.createTextNode(second));
        }
      }
    }
    var body = root.querySelector('[data-preview="body"]');
    if (body) {
      body.textContent = published(slide, 'body');
    }
    var button = root.querySelector('[data-preview="button"]');
    if (button) {
      button.textContent = published(slide, 'button_label');
    }
    var link = root.querySelector('[data-preview="link"]');
    if (link) {
      link.textContent = published(slide, 'link');
    }
    var count = root.querySelector('[data-preview-count]');
    if (count) {
      count.textContent = pad(slide.index + 1) + ' / ' + pad(slides.length);
    }
    var sizeDialog = root.querySelector('[data-preview-sizes-dialog]');
    if (sizeDialog && sizeDialog.open) {
      fillSizePreviews();
    }
    var heading = root.querySelector('[data-editor-title]');
    if (heading) {
      heading.textContent = 'Edit slide ' + pad(slide.index + 1);
    }
    updatePips();
  }

  function renderSlide(slot) {
    var slide = slideBySlot(slot);
    if (!slide) {
      return;
    }
    var button = root.querySelector('[data-select-slide="' + cssEscape(slot) + '"]');
    var title = button ? button.querySelector('[data-library-title]') : null;
    var state = button ? button.querySelector('[data-library-state]') : null;
    var thumb = button ? button.querySelector('[data-library-thumb]') : null;
    var src = sourceFor(slide);
    if (title) {
      title.textContent = pad(slide.index + 1) + '. ' + published(slide, 'title');
    }
    if (state) {
      state.textContent = stateLabel(slide);
    }
    if (button && src && !thumb) {
      thumb = document.createElement('img');
      thumb.dataset.libraryThumb = '';
      thumb.alt = '';
      thumb.width = 72;
      thumb.height = 52;
      button.insertBefore(thumb, button.firstChild);
      var missing = button.querySelector('[data-library-missing]');
      if (missing) {
        missing.remove();
      }
    }
    if (thumb && src) {
      thumb.src = src;
    }
    var panel = slidePanel(slot);
    var frame = panel ? panel.querySelector('[data-photo-preview]') : null;
    var caption = panel ? panel.querySelector('[data-photo-caption]') : null;
    var missingPhoto = panel ? panel.querySelector('[data-photo-missing]') : null;
    var alt = published(slide, 'alt');
    if (frame) {
      if (src) {
        frame.src = src;
        frame.hidden = false;
      } else {
        frame.removeAttribute('src');
        frame.hidden = true;
      }
      frame.alt = alt;
    }
    if (missingPhoto) {
      missingPhoto.hidden = Boolean(src);
    }
    if (caption) {
      caption.textContent = stateLabel(slide);
    }
    var photo = field(slot, 'photo');
    var chosen = Boolean(photo && photo.files && photo.files.length > 0);
    var name = panel ? panel.querySelector('[data-file-name]') : null;
    var clear = panel ? panel.querySelector('[data-clear-photo]') : null;
    if (name) {
      name.hidden = !chosen;
      name.textContent = chosen ? photo.files[0].name : '';
    }
    if (clear) {
      clear.hidden = !chosen;
    }
    if (slot === currentSlot) {
      renderPreview(slide, src, alt);
    }
  }

  function syncTabControls() {
    root.querySelectorAll('[data-editor-tab]').forEach(function (tab) {
      tab.setAttribute('aria-controls', 'home-' + currentSlot + '-' + tab.dataset.editorTab);
    });
  }

  function markTabErrors() {
    var names = Array.isArray(errors[currentSlot]) ? errors[currentSlot] : [];
    root.querySelectorAll('[data-editor-tab]').forEach(function (tab) {
      var fields = tabFields[tab.dataset.editorTab] || [];
      var on = names.some(function (name) { return fields.indexOf(name) !== -1; });
      tab.classList.toggle('has-error', on);
      var dot = tab.querySelector('.home-tab-dot');
      if (dot) {
        dot.hidden = !on;
      }
    });
  }

  function setTab(name) {
    if (!tabFields[name]) {
      name = 'content';
    }
    currentTab = name;
    root.querySelectorAll('[data-editor-tab]').forEach(function (tab) {
      var on = tab.dataset.editorTab === name;
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.tabIndex = on ? 0 : -1;
    });
    root.querySelectorAll('[data-tab]').forEach(function (panel) {
      panel.classList.toggle('is-active', panel.dataset.tab === name);
    });
    syncTabControls();
    markTabErrors();
  }

  function selectSlide(slot, scroll) {
    if (!slideBySlot(slot)) {
      return;
    }
    currentSlot = slot;
    root.querySelectorAll('.home-slide-fields').forEach(function (panel) {
      panel.classList.toggle('is-active', panel.dataset.slide === slot);
    });
    root.querySelectorAll('[data-select-slide]').forEach(function (button) {
      var on = button.dataset.selectSlide === slot;
      button.classList.toggle('is-active', on);
      button.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    var selected = root.querySelector('[data-select-slide="' + cssEscape(slot) + '"]');
    if (scroll && selected) {
      selected.scrollIntoView({ block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }
    syncTabControls();
    markTabErrors();
    renderSlide(slot);
  }

  function step(delta) {
    var index = slides.findIndex(function (slide) { return slide.slot === currentSlot; });
    var next = slides[(index + delta + slides.length) % slides.length];
    if (next) {
      selectSlide(next.slot, true);
    }
  }

  function showSection(key, updateHash) {
    var known = root.querySelector('[data-section-tab="' + cssEscape(key) + '"]');
    if (!known) {
      return;
    }
    if (key !== currentSection) {
      parkExploreDrawer(false);
    }
    currentSection = key;
    document.documentElement.dataset.homeSection = key;
    root.querySelectorAll('[data-home-panel]').forEach(function (panel) {
      var on = panel.dataset.homePanel === key;
      panel.classList.toggle('is-open', on);
      if (on) {
        panel.setAttribute('data-open', '1');
      } else {
        panel.removeAttribute('data-open');
      }
    });
    root.querySelectorAll('[data-section-tab]').forEach(function (tab) {
      var on = tab.dataset.sectionTab === key;
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.tabIndex = on ? 0 : -1;
    });
    var crumb = root.querySelector('[data-crumb]');
    if (crumb) {
      crumb.textContent = known.dataset.label || '';
    }
    if (updateHash) {
      var url = new URL(window.location.href);
      url.hash = key;
      history.replaceState(null, '', url);
    }
  }

  function setPreviewMode(scope, mode) {
    var frame = scope.querySelector('[data-preview-frame]');
    if (frame) {
      frame.classList.toggle('is-mobile', mode === 'mobile');
    }
    scope.querySelectorAll('[data-preview-mode]').forEach(function (button) {
      button.setAttribute('aria-pressed', button.dataset.previewMode === mode ? 'true' : 'false');
    });
  }

  function isHeroDirty() {
    return slides.some(function (slide) {
      var changed = textFields.some(function (name) {
        var input = field(slide.slot, name);
        if (!input) {
          return false;
        }
        var saved = slide.saved && typeof slide.saved[name] === 'string' ? slide.saved[name].trim() : '';
        return input.value.trim() !== saved;
      });
      if (changed) {
        return true;
      }
      var photo = field(slide.slot, 'photo');
      if (photo && photo.files && photo.files.length > 0) {
        return true;
      }
      var restore = field(slide.slot, 'restore');
      return Boolean(restore && restore.checked);
    });
  }

  function supplierField(name) {
    return supplierForm ? supplierForm.querySelector('[data-field="' + name + '"]') : null;
  }

  function supplierPublished(name) {
    var input = supplierField(name);
    var fallback = supplierData && supplierData.defaults && typeof supplierData.defaults[name] === 'string' ? supplierData.defaults[name] : '';
    if (!input) {
      return fallback;
    }
    return input.value.trim() === '' ? fallback : input.value.trim();
  }

  function isSupplierDirty() {
    if (!supplierForm || !supplierData) {
      return false;
    }
    return supplierFields.some(function (name) {
      var input = supplierField(name);
      if (!input) {
        return false;
      }
      var saved = supplierData.saved && typeof supplierData.saved[name] === 'string' ? supplierData.saved[name].trim() : '';
      return input.value.trim() !== saved;
    });
  }

  function renderSupplier() {
    if (!supplierForm || !supplierData) {
      return;
    }
    supplierFields.forEach(function (name) {
      var node = supplierForm.querySelector('[data-supplier-preview="' + name + '"]');
      if (node) {
        node.textContent = supplierPublished(name);
      }
    });
  }

  function clearSupplierError(input) {
    input.classList.remove('is-invalid');
    input.removeAttribute('aria-invalid');
    var wrap = input.closest('.home-field');
    var message = wrap ? wrap.querySelector('.home-field-error') : null;
    if (message && !message.hidden) {
      var text = message.textContent.trim();
      if (text !== '') {
        removeSummaryItem(text);
      }
      message.hidden = true;
      message.textContent = '';
    }
    syncSummary();
  }

  function discardSupplier() {
    if (!isSupplierDirty()) {
      return;
    }
    if (!window.confirm('Discard unsaved changes to the supplier line?')) {
      return;
    }
    supplierFields.forEach(function (name) {
      var input = supplierField(name);
      if (!input) {
        return;
      }
      input.value = supplierData.saved && typeof supplierData.saved[name] === 'string' ? supplierData.saved[name] : '';
      clearSupplierError(input);
    });
    renderSupplier();
    updateSaveState();
  }

  function isFormDirty(form) {
    if (form === heroForm) {
      return isHeroDirty();
    }
    if (form === supplierForm) {
      return isSupplierDirty();
    }
    return Array.prototype.some.call(form.querySelectorAll('input, textarea, select'), function (input) {
      if (input.type === 'hidden' || input.name === '_token' || input.name === '_method' || input.hasAttribute('data-stone-search')) {
        return false;
      }
      if (input.type === 'file') {
        return Boolean(input.files && input.files.length > 0);
      }
      if (input.type === 'checkbox' || input.type === 'radio') {
        return input.checked !== input.defaultChecked;
      }
      return input.value !== input.defaultValue;
    });
  }

  function anyDirty() {
    return Array.prototype.some.call(document.querySelectorAll('.home-editor-form'), function (form) {
      return isFormDirty(form);
    });
  }

  function setSaveMode(mode) {
    var node = root.querySelector('[data-save-state]');
    var icon = root.querySelector('[data-save-icon]');
    var label = root.querySelector('[data-save-label]');
    if (!node || !icon || !label) {
      return;
    }
    node.classList.toggle('is-dirty', mode === 'dirty');
    node.classList.toggle('is-error', mode === 'error');
    node.classList.toggle('is-saving', mode === 'saving');
    icon.className = 'bi ' + saveIcons[mode];
    label.textContent = saveLabels[mode];
  }

  function updateSaveState() {
    var dirty = anyDirty();
    if (discardButton) {
      discardButton.disabled = submitting || !isHeroDirty();
    }
    if (supplierDiscard) {
      supplierDiscard.disabled = submitting || !isSupplierDirty();
    }
    if (exploreDiscard) {
      exploreDiscard.disabled = submitting || (!isFormDirty(exploreForm) && !exploreHasFileError());
    }
    if (stonesDiscard) {
      stonesDiscard.disabled = submitting || (!isFormDirty(stonesForm) && !stonesHasFileError());
    }
    if (submitting) {
      setSaveMode('saving');
      return;
    }
    if (dirty) {
      setSaveMode('dirty');
      return;
    }
    var summary = root.querySelector('[data-home-errors]');
    if (root.dataset.lock === '1' && summary && !summary.hidden) {
      setSaveMode('error');
      return;
    }
    setSaveMode('saved');
  }

  function onPhoto(input) {
    var panel = input.closest('.home-slide-fields');
    var slot = panel ? panel.dataset.slide : '';
    if (!slot) {
      return;
    }
    var file = input.files && input.files[0];
    revoke(slot);
    if (!file) {
      showFileError(panel, '');
      input.classList.remove('is-invalid');
      input.removeAttribute('aria-invalid');
      renderSlide(slot);
      updateSaveState();
      return;
    }
    var message = fileMessage(file);
    if (message) {
      input.value = '';
      showFileError(panel, message);
      input.classList.add('is-invalid');
      input.setAttribute('aria-invalid', 'true');
      renderSlide(slot);
      updateSaveState();
      return;
    }
    objectUrls[slot] = URL.createObjectURL(file);
    var restore = field(slot, 'restore');
    if (restore) {
      restore.checked = false;
    }
    showFileError(panel, '');
    clearFieldError(input);
    renderSlide(slot);
    updateSaveState();
  }

  function onRestore(input) {
    var panel = input.closest('.home-slide-fields');
    var slot = panel ? panel.dataset.slide : '';
    if (!slot) {
      return;
    }
    if (input.checked) {
      var photo = field(slot, 'photo');
      if (photo) {
        photo.value = '';
        photo.classList.remove('is-invalid');
        photo.removeAttribute('aria-invalid');
      }
      revoke(slot);
      showFileError(panel, '');
    }
    clearFieldError(input);
    renderSlide(slot);
    updateSaveState();
  }

  function discardHero() {
    if (!isHeroDirty()) {
      return;
    }
    if (!window.confirm('Discard unsaved changes to the opening slides?')) {
      return;
    }
    slides.forEach(function (slide) {
      textFields.forEach(function (name) {
        var input = field(slide.slot, name);
        if (!input) {
          return;
        }
        input.value = slide.saved && typeof slide.saved[name] === 'string' ? slide.saved[name] : '';
        clearFieldError(input);
      });
      var photo = field(slide.slot, 'photo');
      if (photo) {
        photo.value = '';
        clearFieldError(photo);
      }
      var restore = field(slide.slot, 'restore');
      if (restore) {
        restore.checked = false;
      }
      revoke(slide.slot);
      showFileError(slidePanel(slide.slot), '');
      errors[slide.slot] = [];
    });
    syncSummary();
    markTabErrors();
    slides.forEach(function (slide) { renderSlide(slide.slot); });
    updateSaveState();
  }

  function buildPips() {
    var wrap = root.querySelector('[data-preview-dots]');
    if (!wrap) {
      return;
    }
    wrap.replaceChildren();
    slides.forEach(function (slide) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'home-pip';
      button.dataset.previewDot = slide.slot;
      button.setAttribute('aria-label', 'Show slide ' + (slide.index + 1));
      button.addEventListener('click', function () { selectSlide(slide.slot, true); });
      wrap.append(button);
    });
  }

  heroForm.addEventListener('input', function (event) {
    var target = event.target;
    if (!target || !target.dataset || !target.dataset.field) {
      return;
    }
    if (target.dataset.field === 'photo' || target.dataset.field === 'restore') {
      return;
    }
    clearFieldError(target);
    var panel = target.closest('.home-slide-fields');
    if (panel) {
      renderSlide(panel.dataset.slide);
    }
    updateSaveState();
  });

  heroForm.addEventListener('change', function (event) {
    var target = event.target;
    if (!target || !target.dataset) {
      return;
    }
    if (target.dataset.field === 'photo') {
      onPhoto(target);
    } else if (target.dataset.field === 'restore') {
      onRestore(target);
    }
  });

  heroForm.addEventListener('click', function (event) {
    var button = event.target.closest('[data-clear-photo]');
    if (!button) {
      return;
    }
    var panel = button.closest('.home-slide-fields');
    var photo = panel ? panel.querySelector('[data-field="photo"]') : null;
    if (!panel || !photo) {
      return;
    }
    photo.value = '';
    revoke(panel.dataset.slide);
    showFileError(panel, '');
    photo.classList.remove('is-invalid');
    photo.removeAttribute('aria-invalid');
    renderSlide(panel.dataset.slide);
    updateSaveState();
  });

  if (discardButton) {
    discardButton.addEventListener('click', discardHero);
  }

  root.querySelectorAll('[data-select-slide]').forEach(function (button) {
    button.addEventListener('click', function () {
      selectSlide(button.dataset.selectSlide, false);
    });
  });

  var list = root.querySelector('[data-slide-list]');
  if (list) {
    list.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
        return;
      }
      event.preventDefault();
      step(event.key === 'ArrowDown' ? 1 : -1);
      var selected = root.querySelector('[data-select-slide="' + cssEscape(currentSlot) + '"]');
      if (selected) {
        selected.focus();
      }
    });
  }

  root.querySelectorAll('[data-editor-tab]').forEach(function (tab) {
    tab.addEventListener('click', function () { setTab(tab.dataset.editorTab); });
  });

  var editorTabs = root.querySelector('[data-editor-tabs]');
  if (editorTabs) {
    editorTabs.addEventListener('keydown', function (event) {
      var order = ['content', 'image'];
      var index = order.indexOf(currentTab);
      var next = index;
      if (event.key === 'ArrowRight') {
        next = (index + 1) % order.length;
      } else if (event.key === 'ArrowLeft') {
        next = (index - 1 + order.length) % order.length;
      } else if (event.key === 'Home') {
        next = 0;
      } else if (event.key === 'End') {
        next = order.length - 1;
      } else {
        return;
      }
      event.preventDefault();
      setTab(order[next]);
      var tab = root.querySelector('[data-editor-tab="' + order[next] + '"]');
      if (tab) {
        tab.focus();
      }
    });
  }

  root.querySelectorAll('[data-section-tab]').forEach(function (tab) {
    tab.addEventListener('click', function () { showSection(tab.dataset.sectionTab, true); });
  });

  var sectionNav = root.querySelector('[data-section-nav]');
  if (sectionNav) {
    sectionNav.addEventListener('keydown', function (event) {
      var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-section-tab]'));
      var index = tabs.findIndex(function (tab) { return tab.dataset.sectionTab === currentSection; });
      var next = index;
      if (event.key === 'ArrowRight') {
        next = (index + 1) % tabs.length;
      } else if (event.key === 'ArrowLeft') {
        next = (index - 1 + tabs.length) % tabs.length;
      } else if (event.key === 'Home') {
        next = 0;
      } else if (event.key === 'End') {
        next = tabs.length - 1;
      } else {
        return;
      }
      event.preventDefault();
      showSection(tabs[next].dataset.sectionTab, true);
      tabs[next].focus();
    });
  }

  root.querySelector('[data-preview-prev]')?.addEventListener('click', function () { step(-1); });
  root.querySelector('[data-preview-next]')?.addEventListener('click', function () { step(1); });
  root.querySelectorAll('[data-preview-mode]').forEach(function (button) {
    button.addEventListener('click', function () {
      setPreviewMode(button.closest('[data-home-panel]') || root, button.dataset.previewMode);
    });
  });
  root.querySelector('[data-preview-sizes]')?.addEventListener('click', openSizePreviews);
  var sizeDialog = root.querySelector('[data-preview-sizes-dialog]');
  if (sizeDialog) {
    sizeDialog.querySelector('[data-preview-sizes-close]')?.addEventListener('click', closeSizePreviews);
    sizeDialog.addEventListener('cancel', function (event) {
      event.preventDefault();
      closeSizePreviews();
    });
  }

  if (supplierForm && supplierData) {
    supplierForm.addEventListener('input', function (event) {
      var target = event.target;
      if (target && target.dataset && target.dataset.field) {
        clearSupplierError(target);
      }
      renderSupplier();
    });
    if (supplierDiscard) {
      supplierDiscard.addEventListener('click', discardSupplier);
    }
    renderSupplier();
  }

  function exploreFields(slot) {
    return exploreForm ? exploreForm.querySelector('[data-explore-slot="' + cssEscape(slot) + '"]') : null;
  }

  function exploreCard(slot) {
    return exploreForm ? exploreForm.querySelector('[data-explore-card="' + cssEscape(slot) + '"]') : null;
  }

  function fileKey(input) {
    if (!input || !input.files || !input.files[0]) {
      return '';
    }
    return input.files[0].name + ':' + input.files[0].size + ':' + input.files[0].lastModified;
  }

  function revokeExplore(slot) {
    if (!exploreObjectUrls[slot]) {
      return;
    }
    URL.revokeObjectURL(exploreObjectUrls[slot]);
    delete exploreObjectUrls[slot];
  }

  function setExploreFiles(input, files) {
    if (!input) {
      return;
    }
    if (!files.length) {
      input.value = '';
      return;
    }
    try {
      var transfer = new DataTransfer();
      files.forEach(function (file) { transfer.items.add(file); });
      input.files = transfer.files;
    } catch (error) {
      input.value = '';
    }
  }

  function exploreHasFileError() {
    if (!exploreForm) {
      return false;
    }
    var nodes = exploreForm.querySelectorAll('[data-file-error]');
    for (var index = 0; index < nodes.length; index += 1) {
      if (!nodes[index].hidden && nodes[index].textContent.trim() !== '') {
        return true;
      }
    }
    return false;
  }

  function snapshotExplore(slot) {
    var panel = exploreFields(slot);
    if (!panel) {
      return null;
    }
    var alt = panel.querySelector('[data-field="alt"]');
    var restore = panel.querySelector('[data-field="restore"]');
    var photo = panel.querySelector('[data-field="photo"]');
    return {
      alt: alt ? alt.value : '',
      restore: Boolean(restore && restore.checked),
      fileKey: fileKey(photo),
      files: photo && photo.files ? Array.prototype.slice.call(photo.files) : []
    };
  }

  function applyExploreSnapshot(slot, snapshot) {
    var panel = exploreFields(slot);
    if (!panel || !snapshot) {
      return;
    }
    var alt = panel.querySelector('[data-field="alt"]');
    if (alt) {
      alt.value = snapshot.alt;
      clearFieldError(alt);
    }
    var restore = panel.querySelector('[data-field="restore"]');
    if (restore) {
      restore.checked = snapshot.restore;
    }
    var photo = panel.querySelector('[data-field="photo"]');
    if (photo) {
      setExploreFiles(photo, snapshot.files);
      photo.classList.remove('is-invalid');
      photo.removeAttribute('aria-invalid');
    }
    revokeExplore(slot);
    if (snapshot.files.length) {
      exploreObjectUrls[slot] = URL.createObjectURL(snapshot.files[0]);
    }
    showFileError(panel, '');
  }

  function exploreDrawerDirty() {
    if (!exploreForm || !exploreSnapshot || !exploreForm.dataset.activeSlot) {
      return false;
    }
    var panel = exploreFields(exploreForm.dataset.activeSlot);
    if (!panel) {
      return false;
    }
    var alt = panel.querySelector('[data-field="alt"]');
    var restore = panel.querySelector('[data-field="restore"]');
    var photo = panel.querySelector('[data-field="photo"]');
    if (alt && alt.value !== exploreSnapshot.alt) {
      return true;
    }
    if (Boolean(restore && restore.checked) !== exploreSnapshot.restore) {
      return true;
    }
    return fileKey(photo) !== exploreSnapshot.fileKey;
  }

  function exploreSource(panel) {
    var slot = panel.dataset.exploreSlot;
    if (exploreObjectUrls[slot]) {
      return exploreObjectUrls[slot];
    }
    var restore = panel.querySelector('[data-field="restore"]');
    if (restore && restore.checked) {
      return panel.dataset.fallback || '';
    }
    return panel.dataset.image || panel.dataset.fallback || '';
  }

  function exploreState(panel) {
    var photo = panel.querySelector('[data-field="photo"]');
    if (photo && photo.files && photo.files.length > 0) {
      return 'New photograph selected';
    }
    var restore = panel.querySelector('[data-field="restore"]');
    if (restore && restore.checked) {
      return 'Catalogue photograph';
    }
    if (panel.dataset.custom === '1') {
      return 'Homepage photograph';
    }
    if (!(panel.dataset.image || panel.dataset.fallback)) {
      return 'No photograph';
    }
    return 'Catalogue photograph';
  }

  function renderExplore(slot) {
    var panel = exploreFields(slot);
    var card = exploreCard(slot);
    if (!panel) {
      return;
    }
    var src = exploreSource(panel);
    var state = exploreState(panel);
    if (card) {
      var thumb = card.querySelector('[data-card-thumb]');
      var missing = card.querySelector('[data-card-missing]');
      if (thumb) {
        if (src) {
          thumb.src = src;
          thumb.hidden = false;
        } else {
          thumb.removeAttribute('src');
          thumb.hidden = true;
        }
      }
      if (missing) {
        missing.hidden = Boolean(src);
      }
      var label = card.querySelector('[data-card-state]');
      if (label) {
        label.textContent = state;
      }
    }
    var frame = panel.querySelector('[data-photo-preview]');
    var frameMissing = panel.querySelector('[data-photo-missing]');
    var caption = panel.querySelector('[data-photo-caption]');
    var alt = panel.querySelector('[data-field="alt"]');
    if (frame) {
      if (src) {
        frame.src = src;
        frame.hidden = false;
      } else {
        frame.removeAttribute('src');
        frame.hidden = true;
      }
      frame.alt = alt && alt.value.trim() !== '' ? alt.value.trim() : (panel.dataset.name || '');
    }
    if (frameMissing) {
      frameMissing.hidden = Boolean(src);
    }
    if (caption) {
      caption.textContent = state;
    }
    var photo = panel.querySelector('[data-field="photo"]');
    var chosen = Boolean(photo && photo.files && photo.files.length > 0);
    var name = panel.querySelector('[data-file-name]');
    var clear = panel.querySelector('[data-clear-photo]');
    var errorNode = panel.querySelector('[data-file-error]');
    var errorOn = Boolean(errorNode && !errorNode.hidden && errorNode.textContent.trim() !== '');
    if (name) {
      name.hidden = !chosen;
      name.textContent = chosen ? photo.files[0].name : '';
    }
    if (clear) {
      clear.hidden = !chosen && !errorOn;
      clear.textContent = chosen ? 'Remove selected photograph' : 'Dismiss';
    }
  }

  function exploreCardDirty(slot) {
    var panel = exploreFields(slot);
    if (!panel) {
      return false;
    }
    if (panel.dataset.mustSubmit === '1') {
      return true;
    }
    var alt = panel.querySelector('[data-field="alt"]');
    if (alt && alt.value !== alt.defaultValue) {
      return true;
    }
    var photo = panel.querySelector('[data-field="photo"]');
    if (photo && photo.files && photo.files.length > 0) {
      return true;
    }
    var restore = panel.querySelector('[data-field="restore"]');
    if (restore && restore.checked !== restore.defaultChecked) {
      return true;
    }
    return false;
  }

  function setExploreCardEnabled(slot, enabled) {
    var panel = exploreFields(slot);
    if (!panel) {
      return;
    }
    panel.querySelectorAll('[data-field]').forEach(function (input) {
      input.disabled = !enabled;
    });
  }

  function releaseCleanExploreCards() {
    if (!exploreForm) {
      return;
    }
    exploreForm.querySelectorAll('[data-explore-slot]').forEach(function (panel) {
      var slot = panel.dataset.exploreSlot;
      var open = Boolean(exploreDrawer && exploreDrawer.open && exploreForm.dataset.activeSlot === slot);
      setExploreCardEnabled(slot, open || exploreCardDirty(slot));
    });
  }

  function prepareExploreSubmit() {
    if (!exploreForm) {
      return;
    }
    ['heading', 'body'].forEach(function (name) {
      var input = exploreForm.querySelector('[data-field="' + name + '"]');
      if (!input || input.closest('[data-explore-slot]')) {
        return;
      }
      var invalid = input.classList.contains('is-invalid') || input.getAttribute('aria-invalid') === 'true';
      input.disabled = !invalid && input.value === input.defaultValue;
    });
    exploreForm.querySelectorAll('[data-explore-slot]').forEach(function (panel) {
      setExploreCardEnabled(panel.dataset.exploreSlot, exploreCardDirty(panel.dataset.exploreSlot));
    });
  }

  function restoreExploreEditing() {
    if (!exploreForm) {
      return;
    }
    ['heading', 'body'].forEach(function (name) {
      var input = exploreForm.querySelector('[data-field="' + name + '"]');
      if (input && !input.closest('[data-explore-slot]')) {
        input.disabled = false;
      }
    });
    releaseCleanExploreCards();
  }

  function renderAllExplore() {
    if (!exploreForm) {
      return;
    }
    exploreForm.querySelectorAll('[data-explore-slot]').forEach(function (panel) {
      renderExplore(panel.dataset.exploreSlot);
    });
  }

  function parkExploreDrawer(restoreFocus) {
    if (!exploreForm || !exploreDrawer || !exploreDrawer.open) {
      return;
    }
    var mount = exploreForm.querySelector('[data-drawer-mount]');
    var store = exploreForm.querySelector('[data-explore-store]');
    if (mount && store) {
      while (mount.firstElementChild) {
        store.appendChild(mount.firstElementChild);
      }
    }
    exploreForm.querySelectorAll('[data-edit-card][aria-expanded="true"]').forEach(function (button) {
      button.setAttribute('aria-expanded', 'false');
    });
    var focus = restoreFocus ? exploreReturnFocus : null;
    exploreForm.dataset.activeSlot = '';
    exploreSnapshot = null;
    exploreClosing = true;
    exploreDrawer.close();
    exploreClosing = false;
    releaseCleanExploreCards();
    if (focus) {
      focus.focus();
    }
  }

  function openExploreDrawer(slot, trigger) {
    var panel = exploreFields(slot);
    var mount = exploreForm ? exploreForm.querySelector('[data-drawer-mount]') : null;
    if (!panel || !mount || !exploreDrawer) {
      return;
    }
    if (exploreDrawer.open && exploreForm.dataset.activeSlot === slot) {
      return;
    }
    if (exploreDrawer.open) {
      parkExploreDrawer(false);
    }
    mount.appendChild(panel);
    setExploreCardEnabled(slot, true);
    var title = exploreForm.querySelector('[data-drawer-title]');
    if (title) {
      title.textContent = panel.dataset.name || 'Category';
    }
    exploreForm.dataset.activeSlot = slot;
    exploreSnapshot = snapshotExplore(slot);
    exploreReturnFocus = trigger || exploreForm.querySelector('[data-edit-card="' + cssEscape(slot) + '"]');
    exploreForm.querySelectorAll('[data-edit-card]').forEach(function (button) {
      button.setAttribute('aria-expanded', button.dataset.editCard === slot ? 'true' : 'false');
    });
    if (!exploreDrawer.open) {
      exploreDrawer.showModal();
    }
    var invalid = panel.querySelector('.is-invalid');
    var focusTarget = invalid || title;
    if (focusTarget) {
      focusTarget.focus();
    }
    renderExplore(slot);
  }

  function requestCloseExploreDrawer() {
    if (!exploreDrawer || !exploreDrawer.open || !exploreForm) {
      return;
    }
    var slot = exploreForm.dataset.activeSlot;
    if (exploreDrawerDirty()) {
      var panel = exploreFields(slot);
      var name = panel && panel.dataset.name ? panel.dataset.name : 'this category';
      if (!window.confirm('Discard unsaved changes to ' + name + '?')) {
        return;
      }
      applyExploreSnapshot(slot, exploreSnapshot);
      renderExplore(slot);
    }
    parkExploreDrawer(true);
    updateSaveState();
  }

  function discardExplore() {
    if (!exploreForm || (!isFormDirty(exploreForm) && !exploreHasFileError())) {
      return;
    }
    if (!window.confirm('Discard unsaved changes to the collection?')) {
      return;
    }
    exploreForm.querySelectorAll('input, textarea').forEach(function (input) {
      if (input.type === 'hidden' || input.name === '_token' || input.name === '_method') {
        return;
      }
      if (input.type === 'file') {
        input.value = '';
      } else if (input.type === 'checkbox' || input.type === 'radio') {
        input.checked = input.defaultChecked;
      } else {
        input.value = input.defaultValue;
      }
      clearFieldError(input);
    });
    Object.keys(exploreObjectUrls).forEach(revokeExplore);
    exploreForm.querySelectorAll('[data-explore-slot]').forEach(function (panel) {
      showFileError(panel, '');
    });
    exploreForm.querySelectorAll('[data-explore-slot]').forEach(function (panel) {
      panel.dataset.mustSubmit = '0';
    });
    exploreSnapshot = null;
    parkExploreDrawer(false);
    releaseCleanExploreCards();
    renderAllExplore();
    updateSaveState();
  }

  function onExplorePhoto(input) {
    var panel = input.closest('[data-explore-slot]');
    if (!panel) {
      return;
    }
    var slot = panel.dataset.exploreSlot;
    var file = input.files && input.files[0];
    revokeExplore(slot);
    if (!file) {
      showFileError(panel, '');
      input.classList.remove('is-invalid');
      input.removeAttribute('aria-invalid');
      renderExplore(slot);
      updateSaveState();
      return;
    }
    var message = fileMessage(file);
    if (message) {
      input.value = '';
      showFileError(panel, message);
      input.classList.add('is-invalid');
      input.setAttribute('aria-invalid', 'true');
      renderExplore(slot);
      updateSaveState();
      return;
    }
    exploreObjectUrls[slot] = URL.createObjectURL(file);
    var restore = panel.querySelector('[data-field="restore"]');
    if (restore) {
      restore.checked = false;
    }
    showFileError(panel, '');
    clearFieldError(input);
    renderExplore(slot);
    updateSaveState();
  }

  function onExploreRestore(input) {
    var panel = input.closest('[data-explore-slot]');
    if (!panel) {
      return;
    }
    if (input.checked) {
      var photo = panel.querySelector('[data-field="photo"]');
      if (photo) {
        photo.value = '';
        photo.classList.remove('is-invalid');
        photo.removeAttribute('aria-invalid');
      }
      revokeExplore(panel.dataset.exploreSlot);
      showFileError(panel, '');
    }
    renderExplore(panel.dataset.exploreSlot);
    updateSaveState();
  }

  function exploreDrawerFileError() {
    if (!exploreForm || !exploreDrawer || !exploreDrawer.open) {
      return null;
    }
    var mount = exploreForm.querySelector('[data-drawer-mount]');
    if (!mount) {
      return null;
    }
    var node = mount.querySelector('[data-file-error]:not([hidden])');
    if (!node || node.textContent.trim() === '') {
      return null;
    }
    return node;
  }

  if (exploreForm && exploreDrawer) {
    exploreForm.addEventListener('click', function (event) {
      var edit = event.target.closest('[data-edit-card]');
      if (edit) {
        openExploreDrawer(edit.dataset.editCard, edit);
        return;
      }
      var clear = event.target.closest('[data-clear-photo]');
      if (!clear || !clear.closest('[data-explore-slot]')) {
        return;
      }
      var panel = clear.closest('[data-explore-slot]');
      var photo = panel.querySelector('[data-field="photo"]');
      if (!photo) {
        return;
      }
      photo.value = '';
      revokeExplore(panel.dataset.exploreSlot);
      showFileError(panel, '');
      photo.classList.remove('is-invalid');
      photo.removeAttribute('aria-invalid');
      renderExplore(panel.dataset.exploreSlot);
      updateSaveState();
    });
    exploreForm.addEventListener('input', function (event) {
      var target = event.target;
      if (target && target.dataset && (target.dataset.field === 'alt' || target.dataset.field === 'heading' || target.dataset.field === 'body')) {
        clearFieldError(target);
      }
      var panel = target && target.closest ? target.closest('[data-explore-slot]') : null;
      if (panel) {
        renderExplore(panel.dataset.exploreSlot);
      }
      updateSaveState();
    });
    exploreForm.addEventListener('change', function (event) {
      var target = event.target;
      if (!target || !target.dataset) {
        return;
      }
      if (target.dataset.field === 'photo') {
        onExplorePhoto(target);
      } else if (target.dataset.field === 'restore') {
        onExploreRestore(target);
      }
    });
    exploreDrawer.addEventListener('cancel', function (event) {
      event.preventDefault();
      requestCloseExploreDrawer();
    });
    exploreDrawer.addEventListener('click', function (event) {
      if (event.target === exploreDrawer) {
        requestCloseExploreDrawer();
      }
    });
    exploreDrawer.querySelector('[data-drawer-close]')?.addEventListener('click', requestCloseExploreDrawer);
    exploreDrawer.querySelector('[data-drawer-cancel]')?.addEventListener('click', requestCloseExploreDrawer);
    if (exploreDiscard) {
      exploreDiscard.addEventListener('click', discardExplore);
    }
  }

  function stonesFields(slot) {
    return stonesForm ? stonesForm.querySelector('[data-stone-slot="' + cssEscape(slot) + '"]') : null;
  }

  function stonesRow(slot) {
    return stonesForm ? stonesForm.querySelector('[data-stone-row="' + cssEscape(slot) + '"]') : null;
  }

  function revokeStone(slot) {
    if (!stonesObjectUrls[slot]) {
      return;
    }
    URL.revokeObjectURL(stonesObjectUrls[slot]);
    delete stonesObjectUrls[slot];
  }

  function setStoneFiles(input, files) {
    if (!input) {
      return;
    }
    if (!files.length) {
      input.value = '';
      return;
    }
    try {
      var transfer = new DataTransfer();
      files.forEach(function (file) { transfer.items.add(file); });
      input.files = transfer.files;
    } catch (error) {
      input.value = '';
    }
  }

  function stonesHasFileError() {
    if (!stonesForm) {
      return false;
    }
    var nodes = stonesForm.querySelectorAll('[data-stone-slot] [data-file-error]');
    for (var index = 0; index < nodes.length; index += 1) {
      if (!nodes[index].hidden && nodes[index].textContent.trim() !== '') {
        return true;
      }
    }
    return false;
  }

  function stoneSource(panel) {
    var slot = panel.dataset.stoneSlot;
    if (stonesObjectUrls[slot]) {
      return stonesObjectUrls[slot];
    }
    var restore = panel.querySelector('[data-field="restore"]');
    if (restore && restore.checked) {
      return panel.dataset.fallback || '';
    }
    return panel.dataset.image || panel.dataset.fallback || '';
  }

  function stoneState(panel) {
    var photo = panel.querySelector('[data-field="photo"]');
    if (photo && photo.files && photo.files.length > 0) {
      return 'New photograph selected';
    }
    var restore = panel.querySelector('[data-field="restore"]');
    if (restore && restore.checked) {
      return 'Original photograph';
    }
    if (panel.dataset.custom === '1') {
      return 'Homepage photograph';
    }
    if (!(panel.dataset.image || panel.dataset.fallback)) {
      return 'No photograph';
    }
    return 'Original photograph';
  }

  function renderStone(slot) {
    var panel = stonesFields(slot);
    var row = stonesRow(slot);
    if (!panel) {
      return;
    }
    var src = stoneSource(panel);
    var state = stoneState(panel);
    var title = panel.querySelector('[data-field="title"]');
    var link = panel.querySelector('[data-field="link"]');
    var alt = panel.querySelector('[data-field="alt"]');
    if (row) {
      var thumb = row.querySelector('[data-stone-thumb]');
      if (thumb) {
        if (src) {
          thumb.src = src;
          thumb.hidden = false;
        } else {
          thumb.removeAttribute('src');
          thumb.hidden = true;
        }
      }
      var name = row.querySelector('[data-stone-name]');
      if (name) {
        var typedName = title ? title.value.trim() : '';
        name.textContent = typedName !== '' ? typedName : (panel.dataset.defaultName || 'Application');
      }
      var page = row.querySelector('[data-stone-destination]');
      if (page) {
        var typedPage = link ? link.value.trim() : '';
        page.textContent = typedPage !== '' ? typedPage : (panel.dataset.defaultUrl || '');
      }
      var photoLabel = row.querySelector('[data-stone-photo]');
      if (photoLabel) {
        photoLabel.textContent = state;
      }
    }
    var frame = panel.querySelector('[data-photo-preview]');
    var frameMissing = panel.querySelector('[data-photo-missing]');
    if (frame) {
      if (src) {
        frame.src = src;
        frame.hidden = false;
      } else {
        frame.removeAttribute('src');
        frame.hidden = true;
      }
      var description = alt && alt.value.trim() !== '' ? alt.value.trim() : (title && title.value.trim() !== '' ? title.value.trim() : panel.dataset.defaultName || '');
      frame.alt = description;
    }
    if (frameMissing) {
      frameMissing.hidden = Boolean(src);
    }
    var caption = panel.querySelector('[data-photo-caption]');
    if (caption) {
      caption.textContent = state;
    }
    var photo = panel.querySelector('[data-field="photo"]');
    var chosen = Boolean(photo && photo.files && photo.files.length > 0);
    var fileName = panel.querySelector('[data-file-name]');
    var clear = panel.querySelector('[data-clear-photo]');
    var errorNode = panel.querySelector('[data-file-error]');
    var errorOn = Boolean(errorNode && !errorNode.hidden && errorNode.textContent.trim() !== '');
    if (fileName) {
      fileName.hidden = !chosen;
      fileName.textContent = chosen ? photo.files[0].name : '';
    }
    if (clear) {
      clear.hidden = !chosen && !errorOn;
      clear.textContent = chosen ? 'Remove selected photograph' : 'Dismiss';
    }
  }

  function renderAllStones() {
    if (!stonesForm) {
      return;
    }
    stonesForm.querySelectorAll('[data-stone-slot]').forEach(function (panel) {
      renderStone(panel.dataset.stoneSlot);
    });
  }

  function stoneCardDirty(slot) {
    var panel = stonesFields(slot);
    if (!panel) {
      return false;
    }
    if (panel.dataset.mustSubmit === '1') {
      return true;
    }
    var names = ['title', 'link', 'alt'];
    for (var index = 0; index < names.length; index += 1) {
      var input = panel.querySelector('[data-field="' + names[index] + '"]');
      if (input && input.value !== input.defaultValue) {
        return true;
      }
    }
    var photo = panel.querySelector('[data-field="photo"]');
    if (photo && photo.files && photo.files.length > 0) {
      return true;
    }
    var restore = panel.querySelector('[data-field="restore"]');
    if (restore && restore.checked !== restore.defaultChecked) {
      return true;
    }
    return false;
  }

  function setStoneCardEnabled(slot, enabled) {
    var panel = stonesFields(slot);
    if (!panel) {
      return;
    }
    panel.querySelectorAll('[data-field]').forEach(function (input) {
      input.disabled = !enabled;
    });
  }

  function releaseCleanStoneCards() {
    if (!stonesForm) {
      return;
    }
    stonesForm.querySelectorAll('[data-stone-slot]').forEach(function (panel) {
      var slot = panel.dataset.stoneSlot;
      var selected = stonesForm.dataset.activeSlot === slot;
      setStoneCardEnabled(slot, selected || stoneCardDirty(slot));
    });
  }

  function stoneFieldShouldSubmit(input) {
    if (!input) {
      return false;
    }
    if (input.classList.contains('is-invalid') || input.getAttribute('aria-invalid') === 'true') {
      return true;
    }
    if (input.type === 'file') {
      return Boolean(input.files && input.files.length > 0);
    }
    if (input.type === 'checkbox' || input.type === 'radio') {
      return input.checked !== input.defaultChecked;
    }

    return input.value !== input.defaultValue;
  }

  function prepareStonesSubmit() {
    if (!stonesForm) {
      return;
    }
    ['heading', 'body'].forEach(function (name) {
      var input = stonesForm.querySelector('[data-field="' + name + '"]');
      if (!input || input.closest('[data-stone-slot]')) {
        return;
      }
      input.disabled = !stoneFieldShouldSubmit(input);
    });
    stonesForm.querySelectorAll('[data-stone-slot]').forEach(function (panel) {
      panel.querySelectorAll('[data-field]').forEach(function (input) {
        input.disabled = !stoneFieldShouldSubmit(input);
      });
    });
  }

  function restoreStonesEditing() {
    if (!stonesForm) {
      return;
    }
    ['heading', 'body'].forEach(function (name) {
      var input = stonesForm.querySelector('[data-field="' + name + '"]');
      if (input && !input.closest('[data-stone-slot]')) {
        input.disabled = false;
      }
    });
    releaseCleanStoneCards();
  }

  function renderStonePosition(slot) {
    if (!stonesForm) {
      return;
    }
    var position = stonesForm.querySelector('[data-stone-position]');
    var rows = stonesForm.querySelectorAll('[data-stone-row]');
    if (!position) {
      return;
    }
    var index = 0;
    rows.forEach(function (row, item) {
      if (row.dataset.stoneRow === slot) {
        index = item + 1;
      }
    });
    position.textContent = pad(index) + ' / ' + pad(rows.length);
  }

  function renderStonePager(matchCount) {
    var pages = Math.max(1, Math.ceil(matchCount / stonesPageSize));
    if (stonesPage > pages) {
      stonesPage = pages;
    }
    if (stonesPage < 1) {
      stonesPage = 1;
    }
    var range = stonesForm.querySelector('[data-stone-range]');
    if (range) {
      if (matchCount === 0) {
        range.textContent = 'Showing 0 entries';
      } else {
        var start = ((stonesPage - 1) * stonesPageSize) + 1;
        var end = Math.min(stonesPage * stonesPageSize, matchCount);
        range.textContent = 'Showing ' + start + '-' + end + ' of ' + matchCount + ' entries';
      }
    }
    var list = stonesForm.querySelector('[data-stone-pages]');
    if (list) {
      list.replaceChildren();
      for (var page = 1; page <= pages; page += 1) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'home-stone-page' + (page === stonesPage ? ' is-current' : '');
        button.textContent = String(page);
        button.dataset.stonePage = String(page);
        button.setAttribute('aria-label', 'Page ' + page);
        if (page === stonesPage) {
          button.setAttribute('aria-current', 'page');
        }
        list.appendChild(button);
      }
    }
    var prev = stonesForm.querySelector('[data-stone-prev]');
    var next = stonesForm.querySelector('[data-stone-next]');
    if (prev) {
      prev.disabled = stonesPage <= 1 || matchCount === 0;
    }
    if (next) {
      next.disabled = stonesPage >= pages || matchCount === 0;
    }
  }

  function applyStoneFilter() {
    if (!stonesForm) {
      return;
    }
    var query = stonesQuery.trim().toLowerCase();
    var matches = [];
    stonesForm.querySelectorAll('[data-stone-row]').forEach(function (row) {
      var panel = stonesFields(row.dataset.stoneRow);
      var title = panel ? panel.querySelector('[data-field="title"]') : null;
      var link = panel ? panel.querySelector('[data-field="link"]') : null;
      var name = title ? title.value.toLowerCase() : '';
      var page = link ? link.value.toLowerCase() : '';
      var matched = query === '' || name.indexOf(query) !== -1 || page.indexOf(query) !== -1;
      if (matched) {
        matches.push(row);
      } else {
        row.hidden = true;
      }
    });
    renderStonePager(matches.length);
    matches.forEach(function (row) {
      row.hidden = false;
    });
    var empty = stonesForm.querySelector('[data-stone-empty]');
    if (empty) {
      empty.hidden = matches.length !== 0;
    }
  }

  function selectStone(slot) {
    var panel = stonesFields(slot);
    var mount = stonesForm ? stonesForm.querySelector('[data-stone-mount]') : null;
    var store = stonesForm ? stonesForm.querySelector('[data-stone-store]') : null;
    if (!panel || !mount || !store) {
      return;
    }
    if (mount.firstElementChild && mount.firstElementChild !== panel) {
      store.appendChild(mount.firstElementChild);
    }
    mount.appendChild(panel);
    stonesForm.dataset.activeSlot = slot;
    stonesForm.querySelectorAll('[data-stone-row]').forEach(function (row) {
      row.classList.toggle('is-selected', row.dataset.stoneRow === slot);
    });
    stonesForm.querySelectorAll('[data-stone-edit]').forEach(function (button) {
      button.setAttribute('aria-expanded', button.dataset.stoneEdit === slot ? 'true' : 'false');
    });
    renderStone(slot);
    releaseCleanStoneCards();
    stoneSnapshot = snapshotStone(slot);
    if (stonesDrawer && !stonesDrawer.open) {
      stonesDrawer.showModal();
    }
    var invalid = panel.querySelector('.is-invalid');
    var title = stonesForm.querySelector('[data-stone-drawer-title]');
    if (title) {
      var name = panel.querySelector('[data-field="title"]');
      title.textContent = name && name.value.trim() !== '' ? name.value.trim() : (panel.dataset.defaultName || 'Edit card');
    }
    if (invalid) {
      invalid.focus();
    } else if (title) {
      title.focus();
    }
  }

  function snapshotStone(slot) {
    var panel = stonesFields(slot);
    if (!panel) {
      return null;
    }
    var title = panel.querySelector('[data-field="title"]');
    var link = panel.querySelector('[data-field="link"]');
    var alt = panel.querySelector('[data-field="alt"]');
    var photo = panel.querySelector('[data-field="photo"]');
    var restore = panel.querySelector('[data-field="restore"]');
    return {
      title: title ? title.value : '',
      link: link ? link.value : '',
      alt: alt ? alt.value : '',
      photo: fileKey(photo),
      restore: restore ? restore.checked : false
    };
  }

  function stoneDrawerDirty() {
    var slot = stonesForm ? stonesForm.dataset.activeSlot : '';
    var now = snapshotStone(slot);
    if (!now || !stoneSnapshot) {
      return false;
    }
    return now.title !== stoneSnapshot.title || now.link !== stoneSnapshot.link || now.alt !== stoneSnapshot.alt || now.photo !== stoneSnapshot.photo || now.restore !== stoneSnapshot.restore;
  }

  function applyStoneSnapshot(slot) {
    var panel = stonesFields(slot);
    if (!panel || !stoneSnapshot) {
      return;
    }
    ['title', 'link', 'alt'].forEach(function (name) {
      var input = panel.querySelector('[data-field="' + name + '"]');
      if (input) {
        input.value = stoneSnapshot[name];
        clearFieldError(input);
      }
    });
    var photo = panel.querySelector('[data-field="photo"]');
    if (photo && fileKey(photo) !== stoneSnapshot.photo) {
      photo.value = '';
      revokeStone(slot);
      showFileError(panel, '');
      photo.classList.remove('is-invalid');
      photo.removeAttribute('aria-invalid');
    }
    var restore = panel.querySelector('[data-field="restore"]');
    if (restore) {
      restore.checked = stoneSnapshot.restore;
    }
    renderStone(slot);
  }

  function requestCloseStoneDrawer() {
    if (!stonesDrawer || !stonesDrawer.open || !stonesForm) {
      return;
    }
    var slot = stonesForm.dataset.activeSlot;
    if (stoneDrawerDirty()) {
      var panel = stonesFields(slot);
      var nameInput = panel ? panel.querySelector('[data-field="title"]') : null;
      var name = nameInput && nameInput.value.trim() !== '' ? nameInput.value.trim() : 'this card';
      if (!window.confirm('Discard unsaved changes to ' + name + '?')) {
        return;
      }
      applyStoneSnapshot(slot);
    }
    parkStoneDrawer(stonesReturnFocus);
    stoneSnapshot = null;
    updateSaveState();
  }

  function parkStoneDrawer(restoreFocus) {
    if (!stonesForm || !stonesDrawer || !stonesDrawer.open) {
      return;
    }
    var mount = stonesForm.querySelector('[data-stone-mount]');
    var store = stonesForm.querySelector('[data-stone-store]');
    if (mount && store) {
      while (mount.firstElementChild) {
        store.appendChild(mount.firstElementChild);
      }
    }
    stonesForm.querySelectorAll('[data-stone-edit][aria-expanded="true"]').forEach(function (button) {
      button.setAttribute('aria-expanded', 'false');
    });
    stonesForm.dataset.activeSlot = '';
    stonesDrawer.close();
    releaseCleanStoneCards();
    if (restoreFocus) {
      restoreFocus.focus();
    }
  }

  function discardStones() {
    if (!stonesForm || (!isFormDirty(stonesForm) && !stonesHasFileError())) {
      return;
    }
    if (!window.confirm('Discard unsaved changes to Natural Indian Stones?')) {
      return;
    }
    stonesForm.querySelectorAll('input, textarea').forEach(function (input) {
      if (input.type === 'hidden' || input.name === '_token' || input.name === '_method') {
        return;
      }
      if (input.type === 'file') {
        input.value = '';
      } else if (input.type === 'checkbox' || input.type === 'radio') {
        input.checked = input.defaultChecked;
      } else {
        input.value = input.defaultValue;
      }
      clearFieldError(input);
    });
    Object.keys(stonesObjectUrls).forEach(revokeStone);
    stonesForm.querySelectorAll('[data-stone-slot]').forEach(function (panel) {
      showFileError(panel, '');
      panel.dataset.mustSubmit = '0';
    });
    renderAllStones();
    applyStoneFilter();
    releaseCleanStoneCards();
    updateSaveState();
  }

  function onStonePhoto(input) {
    var panel = input.closest('[data-stone-slot]');
    if (!panel) {
      return;
    }
    var slot = panel.dataset.stoneSlot;
    var file = input.files && input.files[0];
    revokeStone(slot);
    if (!file) {
      showFileError(panel, '');
      input.classList.remove('is-invalid');
      input.removeAttribute('aria-invalid');
      renderStone(slot);
      updateSaveState();
      return;
    }
    var message = fileMessage(file);
    if (message) {
      input.value = '';
      showFileError(panel, message);
      input.classList.add('is-invalid');
      input.setAttribute('aria-invalid', 'true');
      renderStone(slot);
      updateSaveState();
      return;
    }
    stonesObjectUrls[slot] = URL.createObjectURL(file);
    var restore = panel.querySelector('[data-field="restore"]');
    if (restore) {
      restore.checked = false;
    }
    showFileError(panel, '');
    clearFieldError(input);
    renderStone(slot);
    updateSaveState();
  }

  function stonesMountedFileError() {
    if (!stonesForm) {
      return null;
    }
    var mount = stonesForm.querySelector('[data-stone-mount]');
    if (!mount) {
      return null;
    }
    var node = mount.querySelector('[data-file-error]:not([hidden])');
    if (!node || node.textContent.trim() === '') {
      return null;
    }
    return node;
  }

  if (stonesForm) {
    stonesForm.addEventListener('click', function (event) {
      var pageButton = event.target.closest('[data-stone-page]');
      if (pageButton && pageButton.dataset.stonePage) {
        stonesPage = Number(pageButton.dataset.stonePage) || 1;
        applyStoneFilter();
        return;
      }
      if (event.target.closest('[data-stone-prev]')) {
        stonesPage -= 1;
        applyStoneFilter();
        return;
      }
      if (event.target.closest('[data-stone-next]')) {
        stonesPage += 1;
        applyStoneFilter();
        return;
      }
      var edit = event.target.closest('[data-stone-edit]');
      if (edit) {
        if (stonesDrawer && stonesDrawer.open && stonesForm.dataset.activeSlot && stonesForm.dataset.activeSlot !== edit.dataset.stoneEdit && stoneDrawerDirty()) {
          if (!window.confirm('Discard unsaved changes to this card?')) {
            return;
          }
          applyStoneSnapshot(stonesForm.dataset.activeSlot);
        }
        stonesReturnFocus = edit;
        selectStone(edit.dataset.stoneEdit);
        return;
      }
      var clear = event.target.closest('[data-clear-photo]');
      if (!clear || !clear.closest('[data-stone-slot]')) {
        return;
      }
      var panel = clear.closest('[data-stone-slot]');
      var photo = panel.querySelector('[data-field="photo"]');
      if (!photo) {
        return;
      }
      photo.value = '';
      revokeStone(panel.dataset.stoneSlot);
      showFileError(panel, '');
      photo.classList.remove('is-invalid');
      photo.removeAttribute('aria-invalid');
      renderStone(panel.dataset.stoneSlot);
      updateSaveState();
    });
    stonesForm.addEventListener('input', function (event) {
      var target = event.target;
      if (target && target.dataset && (target.dataset.field === 'title' || target.dataset.field === 'link' || target.dataset.field === 'alt' || target.dataset.field === 'heading' || target.dataset.field === 'body')) {
        clearFieldError(target);
      }
      if (target && target.dataset && (target.dataset.field === 'title' || target.dataset.field === 'link' || target.dataset.field === 'alt')) {
        var panel = target.closest('[data-stone-slot]');
        if (panel) {
          renderStone(panel.dataset.stoneSlot);
          applyStoneFilter();
        }
      }
    });
    stonesForm.addEventListener('change', function (event) {
      var target = event.target;
      if (!target || !target.dataset) {
        return;
      }
      if (target.dataset.field === 'photo') {
        onStonePhoto(target);
      } else if (target.dataset.field === 'restore') {
        var panel = target.closest('[data-stone-slot]');
        if (panel) {
          if (target.checked) {
            var photo = panel.querySelector('[data-field="photo"]');
            if (photo) {
              photo.value = '';
              photo.classList.remove('is-invalid');
              photo.removeAttribute('aria-invalid');
            }
            revokeStone(panel.dataset.stoneSlot);
            showFileError(panel, '');
          }
          renderStone(panel.dataset.stoneSlot);
        }
      }
    });
    var stoneSearch = stonesForm.querySelector('[data-stone-search]');
    if (stoneSearch) {
      stoneSearch.addEventListener('input', function () {
        stonesQuery = stoneSearch.value || '';
        stonesPage = 1;
        applyStoneFilter();
      });
    }
    if (stonesDiscard) {
      stonesDiscard.addEventListener('click', discardStones);
    }
    if (stonesDrawer) {
      stonesDrawer.addEventListener('cancel', function (event) {
        event.preventDefault();
        requestCloseStoneDrawer();
      });
      stonesDrawer.addEventListener('click', function (event) {
        if (event.target === stonesDrawer) {
          requestCloseStoneDrawer();
        }
      });
      stonesDrawer.querySelector('[data-stone-drawer-close]')?.addEventListener('click', requestCloseStoneDrawer);
      stonesDrawer.querySelector('[data-stone-drawer-cancel]')?.addEventListener('click', requestCloseStoneDrawer);
    }
  }

  document.querySelectorAll('.home-editor-form').forEach(function (form) {
    form.addEventListener('input', function () {
      if (form !== heroForm) {
        updateSaveState();
      }
    });
    form.addEventListener('change', function () {
      if (form !== heroForm) {
        updateSaveState();
      }
    });
    form.addEventListener('submit', function (event) {
      if (form === exploreForm) {
        var fileError = exploreDrawerFileError();
        if (fileError) {
          event.preventDefault();
          var invalidPanel = fileError.closest('[data-explore-slot]');
          var invalidPhoto = invalidPanel ? invalidPanel.querySelector('[data-field="photo"]') : null;
          if (invalidPhoto) {
            invalidPhoto.focus();
          }
          return;
        }
        prepareExploreSubmit();
      }
      if (form === stonesForm) {
        var stoneError = stonesMountedFileError();
        if (stoneError) {
          event.preventDefault();
          var invalidStone = stoneError.closest('[data-stone-slot]');
          var invalidStonePhoto = invalidStone ? invalidStone.querySelector('[data-field="photo"]') : null;
          if (invalidStonePhoto) {
            invalidStonePhoto.focus();
          }
          return;
        }
        prepareStonesSubmit();
      }
      var othersDirty = Array.prototype.some.call(document.querySelectorAll('.home-editor-form'), function (other) {
        return other !== form && isFormDirty(other);
      });
      if (othersDirty && !window.confirm('Another section still has unsaved changes. Save this section and leave those changes behind?')) {
        event.preventDefault();
        if (form === exploreForm) {
          restoreExploreEditing();
        }
        if (form === stonesForm) {
          restoreStonesEditing();
        }
        return;
      }
      submitting = true;
      form.setAttribute('aria-busy', 'true');
      var button = event.submitter || form.querySelector('[type="submit"]');
      if (button) {
        button.disabled = true;
        button.textContent = 'Saving…';
      }
      updateSaveState();
    });
  });

  window.addEventListener('beforeunload', function (event) {
    if (submitting || !anyDirty()) {
      return;
    }
    event.preventDefault();
    event.returnValue = '';
  });

  var active = root.querySelector('.home-slide-fields.is-active');
  currentSlot = active && active.dataset.slide ? active.dataset.slide : slides[0].slot;
  var selectedTab = root.querySelector('[data-editor-tab][aria-selected="true"]');
  currentTab = selectedTab && selectedTab.dataset.editorTab ? selectedTab.dataset.editorTab : 'content';
  var hashed = document.documentElement.dataset.homeSection;
  if (root.dataset.lock === '1') {
    hashed = root.dataset.openSection || currentSection;
  }
  showSection(hashed || currentSection, true);
  buildPips();
  setTab(currentTab);
  selectSlide(currentSlot, false);
  slides.forEach(function (slide) {
    if (slide.slot !== currentSlot) {
      renderSlide(slide.slot);
    }
  });
  updateSaveState();
  var summary = root.querySelector('[data-home-errors]');
  if (summary && !summary.hidden) {
    summary.focus();
  }
  if (exploreForm) {
    renderAllExplore();
    releaseCleanExploreCards();
    if (exploreForm.dataset.openSlot) {
      openExploreDrawer(exploreForm.dataset.openSlot);
    }
  }
  if (stonesForm) {
    renderAllStones();
    if (stonesForm.dataset.openSlot) {
      selectStone(stonesForm.dataset.openSlot);
    }
    applyStoneFilter();
    releaseCleanStoneCards();
  }
})();
