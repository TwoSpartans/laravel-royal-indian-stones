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
    content: ['eyebrow', 'title', 'subtitle', 'body', 'button_label'],
    image: ['photo', 'restore'],
    metadata: ['link', 'alt']
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
      if (input.type === 'hidden' || input.name === '_token' || input.name === '_method') {
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
      var order = ['content', 'image', 'metadata'];
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
      var othersDirty = Array.prototype.some.call(document.querySelectorAll('.home-editor-form'), function (other) {
        return other !== form && isFormDirty(other);
      });
      if (othersDirty && !window.confirm('Another section still has unsaved changes. Save this section and leave those changes behind?')) {
        event.preventDefault();
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
})();
