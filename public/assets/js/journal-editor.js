(function () {
  var root = document.querySelector('[data-journal-studio]');
  var form = document.getElementById('journal-settings');
  if (!root || !form) {
    return;
  }

  var allowed = ['cover', 'featured'];
  var sectionAliases = { edit: 'featured', overview: 'cover', closer: 'cover', maker: 'cover' };

  function normalizeSection(key) {
    if (sectionAliases[key]) {
      return sectionAliases[key];
    }
    if (allowed.indexOf(key) === -1) {
      return 'cover';
    }
    return key;
  }
  var panels = Array.prototype.slice.call(root.querySelectorAll('[data-journal-panel]'));
  var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-section-tab]'));
  var fields = Array.prototype.slice.call(root.querySelectorAll('.journal-main input, .journal-main textarea, .journal-main select'));
  var submitting = false;
  var leaving = false;
  var modes = {
    loaded: { icon: 'bi-circle-fill', label: 'Existing content loaded', className: 'is-loaded' },
    saved: { icon: 'bi-check-circle', label: 'Journal page saved', className: 'is-saved' },
    dirty: { icon: 'bi-exclamation-circle', label: 'Unsaved changes', className: 'is-dirty' },
    error: { icon: 'bi-exclamation-circle', label: 'Check the highlighted fields', className: 'is-error' },
    saving: { icon: 'bi-arrow-repeat', label: 'Saving…', className: 'is-saving' }
  };
  var preview = { cards: {}, order: [], fallbackId: null, storyLimit: 3 };
  var payload = document.getElementById('journal-preview-data');
  if (payload) {
    try {
      var parsed = JSON.parse(payload.textContent || '');
      if (parsed && typeof parsed === 'object') {
        preview = parsed;
      }
    } catch (error) {}
  }

  function text(value) {
    return value == null ? '' : String(value);
  }

  function safeUrl(value) {
    var url = text(value);
    if (url.indexOf('https://') === 0 || url.indexOf('http://') === 0 || url.indexOf('/') === 0) {
      return url;
    }
    return '';
  }

  function fieldToken(field) {
    if (field.type === 'file') {
      var file = field.files && field.files[0];
      return file ? file.name + ':' + file.size + ':' + file.lastModified : '';
    }
    if (field.type === 'checkbox' || field.type === 'radio') {
      return field.checked ? '1' : '0';
    }
    return field.value;
  }

  fields.forEach(function (field) {
    field.dataset.journalInitial = fieldToken(field);
  });

  function panelDirty(name) {
    var panel = root.querySelector('[data-journal-panel="' + name + '"]');
    if (!panel) {
      return false;
    }
    return Array.prototype.some.call(panel.querySelectorAll('input, textarea, select'), function (field) {
      return fieldToken(field) !== field.dataset.journalInitial;
    });
  }

  function isDirty() {
    return allowed.some(panelDirty);
  }

  function setMode(mode) {
    var state = root.querySelector('[data-save-state]');
    var icon = root.querySelector('[data-save-icon]');
    var label = root.querySelector('[data-save-label]');
    var choice = modes[mode];
    if (!state || !choice) {
      return;
    }
    state.classList.remove('is-loaded', 'is-saved', 'is-dirty', 'is-error', 'is-saving');
    state.classList.add(choice.className);
    if (label) {
      label.textContent = choice.label;
    }
    if (icon) {
      icon.className = 'bi ' + choice.icon;
    }
  }

  function setPreviewMode(dirty) {
    var node = root.querySelector('[data-preview-mode]');
    if (!node) {
      return;
    }
    if (root.dataset.saveMode === 'error' && !dirty) {
      node.textContent = 'Showing the draft from the rejected save. The public page is unchanged.';
      return;
    }
    node.textContent = dirty
      ? 'Showing this draft. The public page changes only after a confirmed save.'
      : 'Showing the saved journal.';
  }

  function updateDirty() {
    var dirty = false;
    allowed.forEach(function (name) {
      var changed = panelDirty(name);
      var tab = root.querySelector('[data-section-tab="' + name + '"]');
      if (tab) {
        if (changed) {
          tab.dataset.dirty = '1';
        } else {
          delete tab.dataset.dirty;
        }
      }
      if (changed) {
        dirty = true;
      }
    });
    if (submitting) {
      setMode('saving');
    } else if (dirty) {
      setMode('dirty');
    } else {
      setMode(root.dataset.saveMode === 'error' || root.dataset.saveMode === 'saved' ? root.dataset.saveMode : 'loaded');
    }
    setPreviewMode(dirty);
    return dirty;
  }

  function showSection(key, focusTab) {
    key = normalizeSection(key);
    document.documentElement.dataset.journalSection = key;
    panels.forEach(function (panel) {
      panel.classList.toggle('is-open', panel.dataset.journalPanel === key);
    });
    var selectedTab = null;
    tabs.forEach(function (tab) {
      var selected = tab.dataset.sectionTab === key;
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      tab.tabIndex = selected ? 0 : -1;
      if (selected) {
        selectedTab = tab;
      }
    });
    var crumb = root.querySelector('[data-crumb]');
    if (crumb && selectedTab && selectedTab.dataset.label) {
      crumb.textContent = selectedTab.dataset.label;
    }
    var pageTitle = root.querySelector('[data-journal-title]');
    var pageLead = root.querySelector('[data-journal-lead]');
    var previewTitle = root.querySelector('[data-preview-title]');
    var previewBlurb = root.querySelector('[data-preview-blurb]');
    var focusCopy = {
      cover: {
        title: 'Journal page / Cover',
        lead: 'The introduction and the featured story are what the public journal shows. Both save together.'
      },
      featured: {
        title: 'Journal page / Featured story',
        lead: 'The introduction and the featured story are what the public journal shows. Both save together.'
      }
    };
    var focused = focusCopy[key] || focusCopy.cover;
    root.classList.toggle('is-cover-focus', key === 'cover');
    root.classList.toggle('is-featured-focus', key === 'featured');
    if (pageTitle) {
      pageTitle.textContent = focused.title;
    }
    if (pageLead) {
      pageLead.textContent = focused.lead;
    }
    if (previewTitle) {
      previewTitle.textContent = 'Public journal preview';
    }
    if (previewBlurb) {
      previewBlurb.textContent = 'Approximate preview of the /blog landing, not the live page.';
    }
    if (typeof renderEditPreview === 'function') {
      renderEditPreview();
    }
    if (typeof renderCloserPreview === 'function') {
      renderCloserPreview();
    }
    if (focusTab && selectedTab) {
      selectedTab.focus();
    }
    if (window.history && window.history.replaceState && location.hash !== '#' + key) {
      window.history.replaceState(null, '', '#' + key);
    }
  }

  function setNodeText(selector, value) {
    var node = root.querySelector(selector);
    if (node) {
      node.textContent = value;
    }
  }

  function syncCopy() {
    setNodeText('[data-preview-eyebrow]', document.getElementById('eyebrow') ? document.getElementById('eyebrow').value : '');
    setNodeText('[data-preview-lead]', document.getElementById('heading_lead') ? document.getElementById('heading_lead').value : '');
    setNodeText('[data-preview-emphasis]', document.getElementById('heading_emphasis') ? document.getElementById('heading_emphasis').value : '');
    setNodeText('[data-preview-intro]', document.getElementById('introduction') ? document.getElementById('introduction').value : '');
  }

  function card(id) {
    if (id == null || !preview.cards) {
      return null;
    }
    return preview.cards[String(id)] || null;
  }

  function featuredId() {
    var seen = [];
    Array.prototype.forEach.call(form.querySelectorAll('[name="edit_posts[]"]'), function (select) {
      var id = parseInt(select.value, 10);
      if (id > 0 && seen.indexOf(id) === -1) {
        seen.push(id);
      }
    });
    for (var index = 0; index < seen.length; index += 1) {
      if (card(seen[index])) {
        return seen[index];
      }
    }
    return preview.fallbackId == null ? null : preview.fallbackId;
  }

  function photo(parent, item, feature) {
    if (item.image && safeUrl(item.image)) {
      var image = document.createElement('img');
      image.src = safeUrl(item.image);
      image.alt = feature || !item.decorative ? text(item.alt) : '';
      parent.appendChild(image);
      return;
    }
    var note = document.createElement('p');
    note.className = 'journal-preview-nophoto';
    note.textContent = feature ? 'No photograph on this story.' : 'No photograph';
    parent.appendChild(note);
  }

  function storyLink(item, feature) {
    var href = safeUrl(item.url);
    var link = document.createElement(href ? 'a' : 'div');
    if (href) {
      link.href = href;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
    }
    link.className = feature ? 'journal-preview-feature-link' : '';
    var media = document.createElement('div');
    media.className = feature ? 'journal-preview-feature-media' : 'journal-preview-story-media';
    photo(media, item, feature);
    link.appendChild(media);
    var copy = document.createElement(feature ? 'div' : 'div');
    if (feature) {
      copy.className = 'journal-preview-feature-copy';
      var kicker = document.createElement('p');
      kicker.className = 'journal-preview-kicker';
      kicker.appendChild(document.createTextNode('Featured story'));
      if (item.category) {
        var separator = document.createElement('span');
        separator.setAttribute('aria-hidden', 'true');
        separator.textContent = ' / ';
        kicker.appendChild(separator);
        kicker.appendChild(document.createTextNode(text(item.category)));
      }
      copy.appendChild(kicker);
    }
    var title = document.createElement(feature ? 'h3' : 'h4');
    title.className = feature ? 'journal-preview-feature-title' : 'journal-preview-story-title';
    title.textContent = text(item.title);
    var summary = null;
    if (text(item.summary) !== '') {
      summary = document.createElement('p');
      summary.className = 'journal-preview-summary';
      summary.textContent = text(item.summary);
    }
    var meta = document.createElement('p');
    meta.className = 'journal-preview-meta';
    meta.textContent = text(item.minutes) + ' min read';
    if (feature) {
      copy.appendChild(title);
      if (summary) {
        copy.appendChild(summary);
      }
      copy.appendChild(meta);
      var button = document.createElement('span');
      button.className = 'journal-preview-button';
      button.textContent = 'Read the story';
      copy.appendChild(button);
      link.appendChild(copy);
      return link;
    }
    link.appendChild(title);
    if (summary) {
      link.appendChild(summary);
    }
    link.appendChild(meta);
    return link;
  }

  function renderStories() {
    var feature = root.querySelector('[data-preview-feature]');
    var list = root.querySelector('[data-preview-stories]');
    if (!feature || !list) {
      return;
    }
    var chosen = featuredId();
    var featureCard = card(chosen);
    feature.replaceChildren();
    if (!featureCard) {
      var empty = document.createElement('p');
      empty.className = 'journal-preview-nophoto';
      empty.textContent = 'No published story to feature.';
      feature.appendChild(empty);
    } else {
      feature.appendChild(storyLink(featureCard, true));
    }
    list.replaceChildren();
    var shown = 0;
    var limit = preview.storyLimit || 3;
    var order = Array.isArray(preview.order) ? preview.order : [];
    var remaining = 0;
    order.forEach(function (id) {
      if (Number(id) === Number(chosen) || !card(id)) {
        return;
      }
      remaining += 1;
      if (shown >= limit) {
        return;
      }
      var article = document.createElement('article');
      article.className = 'journal-preview-story';
      article.appendChild(storyLink(card(id), false));
      list.appendChild(article);
      shown += 1;
    });
    var more = Math.max(0, remaining - shown);
    var moreNode = root.querySelector('[data-preview-more]');
    if (moreNode) {
      moreNode.hidden = more < 1;
      moreNode.textContent = more === 1
        ? '1 more published story is on the public page.'
        : more + ' more published stories are on the public page.';
    }
    var none = root.querySelector('[data-preview-none]');
    if (none) {
      none.hidden = shown > 0;
    }
    renderFeaturedStatus();
  }

  function renderFeaturedStatus() {
    var node = root.querySelector('[data-featured-resolved]');
    if (!node) {
      return;
    }
    var id = featuredId();
    var item = card(id);
    if (!item) {
      node.textContent = 'No published story is available to feature.';
      return;
    }
    var select = document.getElementById('edit-post-0');
    var explicit = select ? card(parseInt(select.value, 10)) : null;
    if (explicit && explicit.id === item.id) {
      node.textContent = 'The public journal features “' + text(item.title) + '”.';
      return;
    }
    node.textContent = 'The public journal features “' + text(item.title) + '”. A blank or hidden choice uses the latest published story that has a photograph.';
  }

  root.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-section-jump], [data-section-tab]');
    if (!trigger || !root.contains(trigger)) {
      return;
    }
    var key = trigger.dataset.sectionJump || trigger.dataset.sectionTab;
    if (!key) {
      return;
    }
    showSection(key, trigger.hasAttribute('data-section-jump'));
  });

  var nav = root.querySelector('[data-section-nav]');
  if (nav) {
    nav.addEventListener('keydown', function (event) {
      var keys = ['ArrowRight', 'ArrowLeft', 'Home', 'End'];
      if (keys.indexOf(event.key) === -1) {
        return;
      }
      var current = tabs.indexOf(document.activeElement);
      if (current === -1) {
        return;
      }
      event.preventDefault();
      var next = current;
      if (event.key === 'ArrowRight') {
        next = (current + 1) % tabs.length;
      }
      if (event.key === 'ArrowLeft') {
        next = (current - 1 + tabs.length) % tabs.length;
      }
      if (event.key === 'Home') {
        next = 0;
      }
      if (event.key === 'End') {
        next = tabs.length - 1;
      }
      tabs[next].focus();
      showSection(tabs[next].dataset.sectionTab, false);
    });
  }

  root.addEventListener('input', function (event) {
    updateDirty();
    if (!event.target) {
      return;
    }
    if (event.target.name === 'eyebrow' || event.target.name === 'heading_lead' || event.target.name === 'heading_emphasis' || event.target.name === 'introduction') {
      syncCopy();
    }
    if (event.target.name === 'edit_kicker' || event.target.name === 'edit_heading') {
      renderEditPreview();
    }
    if (event.target.name === 'closer_kicker' || event.target.name === 'closer_heading') {
      renderCloserPreview();
    }
    if (event.target.name === 'maker_path' || event.target.name === 'maker_heading' || event.target.name === 'maker_body' || event.target.name === 'maker_eyebrow' || event.target.name === 'maker_label' || event.target.name === 'maker_image_alt') {
      renderMakerPreview();
    }
  });

  root.addEventListener('change', function (event) {
    updateDirty();
    if (event.target && (event.target.name === 'edit_posts[]' || event.target.name === 'closer_posts[]')) {
      renderStories();
      renderSlot(event.target);
      renderEditPreview();
      renderCloserPreview();
      if (event.target.name === 'edit_posts[]') {
        closerSelects().forEach(renderSlot);
      }
    }
    if (event.target && (event.target.name === 'maker_post_id' || event.target.name === 'remove_maker_image')) {
      renderMakerFace();
      paintMakerMode();
      renderMakerPreview();
    }
  });

  form.addEventListener('invalid', function (event) {
    var field = event.target;
    if (!field || !field.closest) {
      return;
    }
    var panel = field.closest('[data-journal-panel]');
    if (panel && panel.dataset.journalPanel) {
      showSection(panel.dataset.journalPanel, false);
    }
    setMode('error');
  }, true);

  form.addEventListener('submit', function (event) {
    var invalid = form.querySelector('input:invalid, textarea:invalid, select:invalid');
    if (invalid) {
      event.preventDefault();
      var panel = invalid.closest('[data-journal-panel]');
      if (panel && panel.dataset.journalPanel) {
        showSection(panel.dataset.journalPanel, false);
      }
      invalid.focus();
      if (typeof invalid.reportValidity === 'function') {
        invalid.reportValidity();
      }
      setMode('error');
      return;
    }
    submitting = true;
    form.setAttribute('aria-busy', 'true');
    setMode('saving');
    var buttons = Array.prototype.slice.call(form.querySelectorAll('button[type="submit"]'));
    document.querySelectorAll('button[type="submit"][form="' + form.id + '"]').forEach(function (button) {
      if (buttons.indexOf(button) === -1) {
        buttons.push(button);
      }
    });
    buttons.forEach(function (button) {
      button.disabled = true;
    });
  });

  document.addEventListener('click', function (event) {
    if (submitting || !isDirty()) {
      return;
    }
    var link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download')) {
      return;
    }
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
      return;
    }
    var url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (error) {
      return;
    }
    if (url.origin !== window.location.origin) {
      return;
    }
    if (url.pathname === window.location.pathname && url.search === window.location.search) {
      return;
    }
    if (!window.confirm('Leave the journal editor? Unsaved changes will be lost.')) {
      event.preventDefault();
      return;
    }
    leaving = true;
  });

  window.addEventListener('beforeunload', function (event) {
    if (submitting || leaving || !isDirty()) {
      return;
    }
    event.preventDefault();
    event.returnValue = '';
  });

  var coverInput = document.getElementById('cover');
  var coverSelected = root.querySelector('[data-cover-selected]');
  var coverSelectedImage = root.querySelector('[data-cover-selected-image]');
  var coverSelectedName = root.querySelector('[data-cover-selected-name]');
  var coverFileError = root.querySelector('[data-cover-file-error]');
  var coverPreviewUrl = '';
  var coverMaxBytes = 5120 * 1024;

  function clearCoverSelection() {
    if (coverPreviewUrl) {
      URL.revokeObjectURL(coverPreviewUrl);
      coverPreviewUrl = '';
    }
    if (coverInput) {
      coverInput.value = '';
    }
    if (coverSelected) {
      coverSelected.hidden = true;
    }
    if (coverSelectedImage) {
      coverSelectedImage.removeAttribute('src');
    }
    if (coverFileError) {
      coverFileError.hidden = true;
      coverFileError.textContent = '';
    }
    updateDirty();
  }

  function showCoverFileError(message) {
    clearCoverSelection();
    if (coverFileError) {
      coverFileError.hidden = false;
      coverFileError.textContent = message;
    }
    if (coverInput) {
      coverInput.setAttribute('aria-invalid', 'true');
      coverInput.setAttribute('aria-describedby', 'cover-client-error');
      coverInput.focus();
    }
  }

  function acceptCoverFile(file) {
    if (!file) {
      clearCoverSelection();
      return;
    }
    var type = file.type || '';
    var name = file.name || '';
    var allowedType = type === 'image/jpeg' || type === 'image/png' || type === 'image/webp';
    var allowedName = /\.(jpe?g|png|webp)$/i.test(name);
    if (!allowedType && !allowedName) {
      showCoverFileError('Use a JPG, PNG, or WebP image.');
      return;
    }
    if (file.size > coverMaxBytes) {
      showCoverFileError('The cover field must not be greater than 5120 kilobytes.');
      return;
    }
    if (coverPreviewUrl) {
      URL.revokeObjectURL(coverPreviewUrl);
    }
    coverPreviewUrl = URL.createObjectURL(file);
    if (coverSelectedImage) {
      coverSelectedImage.src = coverPreviewUrl;
      coverSelectedImage.alt = '';
    }
    if (coverSelectedName) {
      coverSelectedName.textContent = name;
    }
    if (coverSelected) {
      coverSelected.hidden = false;
    }
    if (coverFileError) {
      coverFileError.hidden = true;
      coverFileError.textContent = '';
    }
    if (coverInput) {
      coverInput.removeAttribute('aria-invalid');
    }
    updateDirty();
  }

  function assignCoverFile(file) {
    if (!coverInput || !file) {
      return;
    }
    var transfer = new DataTransfer();
    transfer.items.add(file);
    coverInput.files = transfer.files;
    acceptCoverFile(file);
  }

  if (coverInput) {
    coverInput.addEventListener('change', function () {
      acceptCoverFile(coverInput.files && coverInput.files[0]);
    });
  }

  var coverDrop = root.querySelector('[data-cover-drop]');
  if (coverDrop) {
    ['dragenter', 'dragover'].forEach(function (name) {
      coverDrop.addEventListener(name, function (event) {
        event.preventDefault();
        coverDrop.classList.add('is-drag');
      });
    });
    coverDrop.addEventListener('dragleave', function () {
      coverDrop.classList.remove('is-drag');
    });
    coverDrop.addEventListener('drop', function (event) {
      event.preventDefault();
      coverDrop.classList.remove('is-drag');
      var file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
      if (file) {
        assignCoverFile(file);
      }
    });
  }

  var cancelCover = root.querySelector('[data-cover-cancel]');
  if (cancelCover) {
    cancelCover.addEventListener('click', function () {
      clearCoverSelection();
    });
  }

  var discardCover = root.querySelector('[data-discard-cover]');
  if (discardCover) {
    discardCover.addEventListener('click', function () {
      var panel = root.querySelector('[data-journal-panel="cover"]');
      if (!panel) {
        return;
      }
      panel.querySelectorAll('input, textarea, select').forEach(function (field) {
        if (field.type === 'file') {
          return;
        }
        if (field.type === 'checkbox' || field.type === 'radio') {
          field.checked = field.dataset.journalInitial === '1';
          return;
        }
        if (typeof field.dataset.journalInitial === 'string') {
          field.value = field.dataset.journalInitial;
        }
      });
      clearCoverSelection();
      syncCopy();
      updateDirty();
    });
  }

  root.querySelectorAll('[data-cover-device]').forEach(function (button) {
    button.addEventListener('click', function () {
      var mode = button.dataset.coverDevice;
      var paper = root.querySelector('[data-preview-paper]');
      var column = root.querySelector('.journal-preview-column');
      if (paper) {
        paper.classList.toggle('is-phone', mode === 'mobile');
      }
      if (column) {
        column.classList.toggle('is-phone', mode === 'mobile');
      }
      root.querySelectorAll('[data-cover-device]').forEach(function (item) {
        item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
      });
    });
  });

  function editSelects() {
    return Array.prototype.slice.call(form.querySelectorAll('[name="edit_posts[]"]'));
  }

  function pickerBlock(select) {
    var blocked = {};
    if (!select) {
      return blocked;
    }
    form.querySelectorAll('[name="' + select.name + '"]').forEach(function (other) {
      if (other === select || other.hasAttribute('data-preserved-edit')) {
        return;
      }
      var id = parseInt(other.value, 10);
      if (id > 0) {
        blocked[String(id)] = select.name === 'closer_posts[]'
          ? 'Already chosen in the other closer slot.'
          : 'Already chosen in another slot.';
      }
    });
    if (select.name === 'closer_posts[]') {
      resolvedEditIds().forEach(function (id) {
        blocked[String(id)] = 'Already used in The Edit.';
      });
    }
    return blocked;
  }

  function chosenEditIds(except) {
    var ids = [];
    editSelects().forEach(function (select) {
      if (select === except) {
        return;
      }
      var id = parseInt(select.value, 10);
      if (id > 0 && ids.indexOf(id) === -1) {
        ids.push(id);
      }
    });
    return ids;
  }

  function closerSelects() {
    return Array.prototype.slice.call(form.querySelectorAll('[name="closer_posts[]"]'));
  }

  function resolvedEditIds() {
    var explicit = [];
    editSelects().forEach(function (select) {
      var id = parseInt(select.value, 10);
      if (card(id) && explicit.indexOf(id) === -1) {
        explicit.push(id);
      }
    });
    if (explicit.length > 0) {
      return explicit;
    }
    var latest = [];
    var order = Array.isArray(preview.order) ? preview.order : [];
    order.forEach(function (id) {
      if (latest.length >= 3 || !card(id)) {
        return;
      }
      latest.push(Number(id));
    });
    return latest;
  }

  function renderSlot(select) {
    var slot = select.closest('[data-edit-slot], [data-closer-slot], [data-featured-slot]');
    var face = slot ? slot.querySelector('[data-slot-face]') : null;
    if (!face) {
      return;
    }
    var featuredSlot = Boolean(select.closest('[data-featured-slot]'));
    var closerSlot = Boolean(select.closest('[data-closer-slot]'));
    var id = parseInt(select.value, 10);
    var item = card(id);
    var option = select.options[select.selectedIndex];
    var blockedByEdit = closerSlot && select.value && resolvedEditIds().indexOf(id) !== -1;
    face.replaceChildren();
    if (!select.value) {
      var blank = document.createElement('div');
      var auto = document.createElement('p');
      auto.className = 'journal-slot-auto';
      auto.textContent = featuredSlot ? 'Latest published story' : 'Auto — latest eligible published article';
      var note = document.createElement('p');
      note.className = 'journal-hint';
      if (featuredSlot) {
        var fallback = card(preview.fallbackId);
        note.textContent = fallback
          ? 'Nothing is saved in this field. The public page will feature “' + text(fallback.title) + '”.'
          : 'Nothing is saved in this field. No published story is available.';
      } else {
        note.textContent = closerSlot
          ? 'Fallback only. This blank slot is not saved. Stories already in The Edit are skipped.'
          : 'Fallback only. This blank slot is not saved as a story.';
      }
      blank.appendChild(auto);
      blank.appendChild(note);
      var choose = document.createElement('button');
      choose.type = 'button';
      choose.className = 'home-btn home-btn-ghost';
      choose.dataset.slotChoose = '1';
      choose.textContent = featuredSlot ? 'Choose a story' : 'Choose';
      face.appendChild(blank);
      face.appendChild(choose);
      return;
    }
    var copy = document.createElement('div');
    if (item && item.image && safeUrl(item.image)) {
      var image = document.createElement('img');
      image.src = safeUrl(item.image);
      image.alt = '';
      copy.appendChild(image);
    }
    var state = document.createElement('p');
    state.className = 'journal-slot-state';
    state.textContent = blockedByEdit
      ? 'Already used in The Edit'
      : (item ? (featuredSlot ? 'Featured on the public journal' : 'Chosen') : 'Hidden');
    var title = document.createElement('p');
    title.className = 'journal-slot-title';
    title.textContent = item ? text(item.title) : (option ? option.text : 'Article ' + id);
    var meta = document.createElement('p');
    meta.className = 'journal-hint';
    if (blockedByEdit) {
      meta.textContent = 'Article ' + id + '. This choice stays in the form. The closer-look resolver skips it so the story is not repeated.';
    } else if (item && featuredSlot) {
      meta.textContent = 'Article ' + item.id + '. This is the featured story on the public journal.';
    } else if (item) {
      meta.textContent = 'Article ' + item.id + '. Saved as this article ID.';
    } else {
      meta.textContent = 'Article ' + id + '. Hidden articles are skipped on the public page.';
    }
    copy.appendChild(state);
    copy.appendChild(title);
    copy.appendChild(meta);
    var actions = document.createElement('div');
    actions.className = 'journal-slot-actions';
    var change = document.createElement('button');
    change.type = 'button';
    change.className = 'home-btn home-btn-ghost';
    change.dataset.slotChoose = '1';
    change.textContent = 'Change';
    var clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'home-btn home-btn-ghost';
    clear.dataset.slotClear = '1';
    clear.textContent = featuredSlot ? 'Use latest story' : 'Clear / use latest';
    actions.appendChild(change);
    actions.appendChild(clear);
    face.appendChild(copy);
    face.appendChild(actions);
  }

  function renderSlots() {
    editSelects().forEach(renderSlot);
    closerSelects().forEach(renderSlot);
  }

  function renderCloserPreview() {
    var kicker = document.getElementById('closer_kicker');
    var heading = document.getElementById('closer_heading');
    var notice = root.querySelector('[data-closer-conflict]');
    setNodeText('[data-closer-preview-kicker]', kicker ? kicker.value : '');
    setNodeText('[data-closer-preview-heading]', heading ? heading.value : '');
    var box = root.querySelector('[data-closer-preview-cards]');
    if (!box) {
      return;
    }
    var except = resolvedEditIds();
    var explicit = [];
    var configured = false;
    var blocked = false;
    closerSelects().forEach(function (select) {
      var id = parseInt(select.value, 10);
      if (!(id > 0)) {
        return;
      }
      configured = true;
      if (except.indexOf(id) !== -1) {
        blocked = true;
        return;
      }
      if (card(id) && explicit.indexOf(id) === -1) {
        explicit.push(id);
      }
    });
    if (notice) {
      notice.hidden = !blocked;
    }
    box.replaceChildren();
    var resolved = explicit;
    var fallback = false;
    if (!configured) {
      fallback = true;
      resolved = [];
      var order = Array.isArray(preview.order) ? preview.order : [];
      order.forEach(function (id) {
        if (resolved.length >= 2 || except.indexOf(Number(id)) !== -1 || !card(id)) {
          return;
        }
        resolved.push(Number(id));
      });
    }
    var label = document.createElement('p');
    label.className = 'journal-hint';
    if (fallback) {
      label.textContent = resolved.length
        ? 'Fallback, not a saved choice. These are the next published stories after The Edit.'
        : 'No further published story is available.';
    } else if (resolved.length === 0) {
      label.textContent = 'None of the chosen stories can be shown here. They are already in The Edit or are not public.';
    } else {
      label.textContent = 'Saved choices that are not already in The Edit.';
    }
    box.appendChild(label);
    resolved.forEach(function (id) {
      var item = card(id);
      if (!item) {
        return;
      }
      var article = document.createElement('article');
      article.className = 'journal-preview-story';
      article.appendChild(storyLink(item, false));
      box.appendChild(article);
    });
  }

  function renderEditPreview() {
    var kicker = document.getElementById('edit_kicker');
    var heading = document.getElementById('edit_heading');
    setNodeText('[data-edit-preview-kicker]', kicker ? kicker.value : '');
    setNodeText('[data-edit-preview-heading]', heading ? heading.value : '');
    var box = root.querySelector('[data-edit-preview-cards]');
    if (!box) {
      return;
    }
    box.replaceChildren();
    var chosen = [];
    editSelects().forEach(function (select) {
      var id = parseInt(select.value, 10);
      if (card(id) && chosen.indexOf(id) === -1) {
        chosen.push(id);
      }
    });
    if (chosen.length === 0) {
      var fallback = card(preview.fallbackId);
      var label = document.createElement('p');
      label.className = 'journal-hint';
      label.textContent = fallback
        ? 'Fallback, not a saved choice. These slots are blank or not public, so the journal features the latest published story.'
        : 'No published story is available.';
      box.appendChild(label);
      if (fallback) {
        var article = document.createElement('article');
        article.className = 'journal-preview-story';
        article.appendChild(storyLink(fallback, false));
        box.appendChild(article);
      }
      return;
    }
    chosen.forEach(function (id, index) {
      var item = card(id);
      var article = document.createElement('article');
      article.className = 'journal-preview-story';
      var label = document.createElement('p');
      label.className = 'journal-slot-state';
      label.textContent = index === 0
        ? 'Public featured story'
        : 'Saved choice. The public landing features the first published story in this group.';
      article.appendChild(label);
      article.appendChild(storyLink(item, false));
      box.appendChild(article);
    });
  }

  var picker = root.querySelector('[data-article-picker]');
  var pickerList = picker ? picker.querySelector('[data-picker-list]') : null;
  var pickerSearch = picker ? picker.querySelector('[data-picker-search]') : null;
  var pickerEmpty = picker ? picker.querySelector('[data-picker-empty]') : null;
  var pickerSelect = null;
  var pickerReturn = null;
  var pickerPending = '';
  var pickerVisible = 9;

  function pickerFocusable() {
    if (!picker) {
      return [];
    }
    return Array.prototype.filter.call(picker.querySelectorAll('button, input, select'), function (node) {
      return !node.disabled && !node.hidden;
    });
  }

  function pickerIds() {
    var order = Array.isArray(preview.order) ? preview.order.slice() : [];
    var sort = picker ? picker.querySelector('[data-picker-sort]') : null;
    if (sort && sort.value === 'title') {
      order.sort(function (left, right) {
        return text(card(left) && card(left).title).localeCompare(text(card(right) && card(right).title));
      });
    }
    return order;
  }

  function pickerContext(select) {
    if (!select) {
      return 'Published stories only. The field stores the article ID.';
    }
    if (select.closest('[data-featured-slot]')) {
      return 'Featured story · Published stories only. The public journal shows this article first.';
    }
    if (select.name === 'maker_post_id') {
      return 'Maker feature · Published stories only. Choosing an article does not clear the page path.';
    }
    var slot = select.closest('[data-edit-slot], [data-closer-slot]');
    var index = slot ? Number(slot.dataset.editSlot || slot.dataset.closerSlot) + 1 : 1;
    var group = select.name === 'closer_posts[]' ? 'A closer look' : 'The Edit';
    return group + ' · Story ' + index + ' · Published stories only.';
  }

  function markPickerPending() {
    if (!pickerList) {
      return;
    }
    var visible = false;
    pickerList.querySelectorAll('[data-picker-card]').forEach(function (button) {
      var selected = button.dataset.pickerCard === pickerPending;
      if (selected) {
        visible = true;
      }
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
      button.classList.toggle('is-selected', selected);
    });
    var use = picker ? picker.querySelector('[data-picker-use]') : null;
    if (use) {
      use.disabled = !visible;
    }
  }

  function renderPickerList() {
    if (!pickerList) {
      return;
    }
    var query = pickerSearch ? pickerSearch.value.trim().toLowerCase() : '';
    var used = pickerBlock(pickerSelect);
    var matches = [];
    pickerIds().forEach(function (id) {
      var item = card(id);
      if (!item) {
        return;
      }
      if (query && text(item.title).toLowerCase().indexOf(query) === -1) {
        return;
      }
      matches.push(item);
    });
    pickerList.replaceChildren();
    matches.slice(0, pickerVisible).forEach(function (item) {
      var reason = used[String(item.id)] || '';
      var taken = reason !== '';
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'journal-picker-item';
      button.dataset.pickerCard = String(item.id);
      button.disabled = taken;
      button.setAttribute('aria-disabled', taken ? 'true' : 'false');
      if (taken) {
        button.title = reason;
      }
      if (item.image && safeUrl(item.image)) {
        var image = document.createElement('img');
        image.src = safeUrl(item.image);
        image.alt = '';
        button.appendChild(image);
      } else {
        var missing = document.createElement('span');
        missing.className = 'journal-picker-missing';
        missing.textContent = 'No photograph';
        button.appendChild(missing);
      }
      var copy = document.createElement('span');
      var title = document.createElement('strong');
      title.textContent = text(item.title);
      var meta = document.createElement('small');
      meta.textContent = taken ? reason : (item.image ? 'Published' : 'Published · No photograph');
      copy.appendChild(title);
      copy.appendChild(meta);
      button.appendChild(copy);
      button.addEventListener('click', function () {
        if (taken) {
          return;
        }
        pickerPending = String(item.id);
        markPickerPending();
      });
      button.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || taken) {
          return;
        }
        if (pickerPending === String(item.id)) {
          event.preventDefault();
          applyPicker(pickerPending);
        }
      });
      pickerList.appendChild(button);
    });
    if (matches.length > pickerVisible) {
      var more = document.createElement('button');
      more.type = 'button';
      more.className = 'home-btn home-btn-ghost journal-picker-more';
      more.textContent = 'Show more published stories';
      more.addEventListener('click', function () {
        pickerVisible += 9;
        renderPickerList();
      });
      pickerList.appendChild(more);
    }
    if (pickerEmpty) {
      pickerEmpty.hidden = matches.length > 0;
    }
    markPickerPending();
  }

  function applyPicker(id) {
    if (!pickerSelect) {
      return;
    }
    if (id !== '' && pickerBlock(pickerSelect)[String(id)]) {
      return;
    }
    if (id === '' && pickerSelect.closest('[data-featured-slot]')) {
      editSelects().forEach(function (other) {
        other.value = '';
      });
    }
    pickerSelect.value = id === '' ? '' : String(id);
    pickerSelect.dispatchEvent(new Event('change', { bubbles: true }));
    closePicker();
  }

  function openPicker(select, opener) {
    if (!picker) {
      return;
    }
    pickerSelect = select;
    pickerReturn = opener || null;
    pickerPending = '';
    pickerVisible = 9;
    var current = parseInt(select.value, 10);
    if (card(current) && !pickerBlock(select)[String(current)]) {
      pickerPending = String(current);
    }
    var pendingIndex = pickerIds().indexOf(current);
    if (pickerPending !== '' && pendingIndex >= pickerVisible) {
      pickerVisible = pendingIndex + 1;
    }
    var context = picker.querySelector('[data-picker-context]');
    if (context) {
      context.textContent = pickerContext(select);
    }
    var clearButton = picker.querySelector('[data-picker-clear]');
    if (clearButton) {
      if (select.closest('[data-featured-slot]')) {
        clearButton.textContent = 'Use latest story';
      } else if (select.name === 'maker_post_id') {
        clearButton.textContent = 'Clear article';
      } else {
        clearButton.textContent = 'Clear slot';
      }
    }
    var sort = picker.querySelector('[data-picker-sort]');
    if (sort) {
      sort.value = 'newest';
    }
    if (pickerSearch) {
      pickerSearch.value = '';
    }
    picker.hidden = false;
    renderPickerList();
    if (pickerSearch) {
      pickerSearch.focus();
    }
  }

  function closePicker() {
    if (!picker) {
      return;
    }
    var select = pickerSelect;
    var opener = pickerReturn;
    picker.hidden = true;
    pickerSelect = null;
    pickerReturn = null;
    var next = null;
    if (select && select.name === 'maker_post_id') {
      next = root.querySelector('[data-maker-choose]');
    } else if (select) {
      var slot = select.closest('[data-edit-slot], [data-closer-slot]');
      if (slot) {
        next = slot.querySelector('[data-slot-choose]');
      }
    }
    if ((!next || !next.isConnected) && opener && opener.isConnected) {
      next = opener;
    }
    if (next && next.focus) {
      next.focus();
    }
  }

  function bindSlotClicks(panel) {
    if (!panel) {
      return;
    }
    panel.addEventListener('click', function (event) {
      var choose = event.target.closest('[data-slot-choose]');
      var clear = event.target.closest('[data-slot-clear]');
      var slot = event.target.closest('[data-featured-slot], [data-edit-slot], [data-closer-slot]');
      var select = slot ? slot.querySelector('select') : null;
      if (choose && select) {
        openPicker(select, choose);
      }
      if (clear && select) {
        if (slot.hasAttribute('data-featured-slot')) {
          editSelects().forEach(function (other) {
            other.value = '';
          });
        } else {
          select.value = '';
        }
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  bindSlotClicks(root.querySelector('[data-journal-panel="featured"]'));
  bindSlotClicks(root.querySelector('[data-journal-panel="closer"]'));

  if (pickerSearch) {
    pickerSearch.addEventListener('input', function () {
      pickerVisible = 9;
      renderPickerList();
    });
  }
  if (picker) {
    var closeButton = picker.querySelector('[data-picker-close]');
    var clearButton = picker.querySelector('[data-picker-clear]');
    var useButton = picker.querySelector('[data-picker-use]');
    var sortField = picker.querySelector('[data-picker-sort]');
    if (closeButton) {
      closeButton.addEventListener('click', closePicker);
    }
    if (clearButton) {
      clearButton.addEventListener('click', function () {
        applyPicker('');
      });
    }
    if (useButton) {
      useButton.addEventListener('click', function () {
        if (pickerPending === '') {
          return;
        }
        applyPicker(pickerPending);
      });
    }
    if (sortField) {
      sortField.addEventListener('change', function () {
        pickerVisible = 9;
        if (pickerPending !== '') {
          var pendingIndex = pickerIds().indexOf(parseInt(pickerPending, 10));
          if (pendingIndex >= pickerVisible) {
            pickerVisible = pendingIndex + 1;
          }
        }
        renderPickerList();
      });
    }
    picker.addEventListener('click', function (event) {
      if (event.target === picker) {
        closePicker();
      }
    });
    document.addEventListener('keydown', function (event) {
      if (!picker || picker.hidden) {
        return;
      }
      if (event.key === 'Escape') {
        event.preventDefault();
        closePicker();
        return;
      }
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        var cards = pickerList
          ? Array.prototype.slice.call(pickerList.querySelectorAll('[data-picker-card]:not(:disabled)'))
          : [];
        if (cards.length === 0) {
          return;
        }
        var index = cards.indexOf(document.activeElement);
        if (document.activeElement === pickerSearch && event.key === 'ArrowDown') {
          event.preventDefault();
          cards[0].focus();
          return;
        }
        if (index === -1) {
          return;
        }
        event.preventDefault();
        var columns = 1;
        var top = cards[0].getBoundingClientRect().top;
        cards.forEach(function (cardButton) {
          if (Math.abs(cardButton.getBoundingClientRect().top - top) < 8) {
            columns += 1;
          }
        });
        columns = Math.max(1, columns - 1);
        var step = event.key === 'ArrowDown' || event.key === 'ArrowUp' ? columns : 1;
        var direction = event.key === 'ArrowLeft' || event.key === 'ArrowUp' ? -1 : 1;
        var next = index + (direction * step);
        if (next < 0) {
          next = 0;
        }
        if (next >= cards.length) {
          next = cards.length - 1;
        }
        cards[next].focus();
        return;
      }
      if (event.key !== 'Tab') {
        return;
      }
      var items = pickerFocusable();
      if (items.length === 0) {
        return;
      }
      var first = items[0];
      var last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
  }

  var discardEdit = root.querySelector('[data-discard-edit]');
  if (discardEdit) {
    discardEdit.addEventListener('click', function () {
      var panel = root.querySelector('[data-journal-panel="featured"]');
      if (!panel) {
        return;
      }
      panel.querySelectorAll('input, textarea, select').forEach(function (field) {
        if (field.type === 'file') {
          return;
        }
        if (typeof field.dataset.journalInitial === 'string') {
          field.value = field.dataset.journalInitial;
        }
      });
      renderSlots();
      renderEditPreview();
      renderCloserPreview();
      renderStories();
      updateDirty();
    });
  }

  var discardCloser = root.querySelector('[data-discard-closer]');
  if (discardCloser) {
    discardCloser.addEventListener('click', function () {
      var panel = root.querySelector('[data-journal-panel="closer"]');
      if (!panel) {
        return;
      }
      panel.querySelectorAll('input, textarea, select').forEach(function (field) {
        if (field.type === 'file') {
          return;
        }
        if (typeof field.dataset.journalInitial === 'string') {
          field.value = field.dataset.journalInitial;
        }
      });
      renderSlots();
      renderCloserPreview();
      updateDirty();
    });
  }

  function makerValue(id) {
    var node = document.getElementById(id);
    return node ? node.value.trim() : '';
  }

  function paintMakerMode() {
    var article = parseInt(makerValue('maker_post_id'), 10) > 0;
    root.querySelectorAll('[data-maker-mode]').forEach(function (button) {
      var selected = button.dataset.makerMode === 'article' ? article : !article;
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
  }

  function renderMakerFace() {
    var select = document.getElementById('maker_post_id');
    var face = root.querySelector('[data-maker-face]');
    if (!select || !face) {
      return;
    }
    var id = parseInt(select.value, 10);
    var item = card(id);
    var option = select.options[select.selectedIndex];
    face.replaceChildren();
    var copy = document.createElement('div');
    if (!select.value) {
      var none = document.createElement('p');
      none.className = 'journal-slot-auto';
      none.textContent = 'None';
      var note = document.createElement('p');
      note.className = 'journal-hint';
      note.textContent = 'No article is saved in this field.';
      copy.appendChild(none);
      copy.appendChild(note);
      var choose = document.createElement('button');
      choose.type = 'button';
      choose.className = 'home-btn home-btn-ghost';
      choose.dataset.makerChoose = '1';
      choose.textContent = 'Choose';
      face.appendChild(copy);
      face.appendChild(choose);
      return;
    }
    if (item && item.image && safeUrl(item.image)) {
      var image = document.createElement('img');
      image.src = safeUrl(item.image);
      image.alt = '';
      copy.appendChild(image);
    }
    var state = document.createElement('p');
    state.className = 'journal-slot-state';
    state.textContent = item ? 'Chosen' : 'Not on the public page';
    var title = document.createElement('p');
    title.className = 'journal-slot-title';
    title.textContent = item ? text(item.title) : (option ? option.text : 'Article ' + id);
    var meta = document.createElement('p');
    meta.className = 'journal-hint';
    meta.textContent = item
      ? 'Article ' + item.id + '. This article takes precedence over the page path.'
      : 'Article ' + id + '. A hidden article does not build a feature.';
    copy.appendChild(state);
    copy.appendChild(title);
    copy.appendChild(meta);
    var actions = document.createElement('div');
    actions.className = 'journal-slot-actions';
    var change = document.createElement('button');
    change.type = 'button';
    change.className = 'home-btn home-btn-ghost';
    change.dataset.makerChoose = '1';
    change.textContent = 'Change';
    var clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'home-btn home-btn-ghost';
    clear.dataset.makerClear = '1';
    clear.textContent = 'Clear article';
    actions.appendChild(change);
    actions.appendChild(clear);
    face.appendChild(copy);
    face.appendChild(actions);
  }

  function makerPreviewImage() {
    if (makerPreviewUrl) {
      return makerPreviewUrl;
    }
    var remove = document.getElementById('remove_maker_image');
    if (remove && remove.checked) {
      return '';
    }
    var holder = root.querySelector('[data-maker-preview]');
    return holder && holder.dataset.makerSavedImage ? holder.dataset.makerSavedImage : '';
  }

  function renderMakerPreview() {
    var box = root.querySelector('[data-maker-preview-body]');
    var holder = root.querySelector('[data-maker-preview]');
    if (!box || !holder) {
      return;
    }
    var eyebrowDefault = holder.dataset.makerDefaultEyebrow || '';
    var labelDefault = holder.dataset.makerDefaultLabel || '';
    var id = parseInt(makerValue('maker_post_id'), 10);
    var item = card(id);
    box.replaceChildren();
    function line(className, value) {
      var node = document.createElement('p');
      node.className = className;
      node.textContent = value;
      return node;
    }
    if (id > 0 && !item) {
      box.appendChild(line('journal-slot-auto', 'This feature stays off.'));
      box.appendChild(line('journal-hint', 'The chosen article is not public, so the maker rule does not build a feature.'));
      return;
    }
    if (id > 0 && item) {
      var articleHeading = makerValue('maker_heading') || text(item.title);
      var articleBody = makerValue('maker_body') || text(item.summary);
      box.appendChild(line('journal-hint', 'Article rule. Empty heading and supporting text use the article title and summary.'));
      box.appendChild(line('journal-preview-eyebrow', makerValue('maker_eyebrow') || eyebrowDefault));
      var articleTitle = document.createElement('h3');
      articleTitle.className = 'journal-edit-heading';
      articleTitle.textContent = articleHeading;
      box.appendChild(articleTitle);
      if (articleBody) {
        box.appendChild(line('journal-hint', articleBody));
      }
      box.appendChild(line('journal-slot-state', makerValue('maker_label') || labelDefault));
      var articleImage = makerPreviewImage() || item.image;
      if (articleImage && safeUrl(articleImage)) {
        var articlePhoto = document.createElement('img');
        articlePhoto.src = safeUrl(articleImage);
        articlePhoto.alt = makerValue('maker_image_alt') || articleHeading;
        box.appendChild(articlePhoto);
      }
      box.appendChild(line('journal-hint', 'The public journal does not currently print this block.'));
      return;
    }
    var path = makerValue('maker_path');
    var pageHeading = makerValue('maker_heading');
    if (path === '' || pageHeading === '') {
      box.appendChild(line('journal-slot-auto', 'This feature is not ready.'));
      var detail = 'No article is selected.';
      if (path !== '') {
        detail += ' Saved page path: ' + path + '.';
      }
      detail += ' A page feature also needs a heading. Empty copy and a missing photograph are left blank.';
      box.appendChild(line('journal-hint', detail));
      return;
    }
    box.appendChild(line('journal-hint', 'Page rule. This is used only when the path is a page that already exists.'));
    box.appendChild(line('journal-preview-eyebrow', makerValue('maker_eyebrow') || eyebrowDefault));
    var pageTitle = document.createElement('h3');
    pageTitle.className = 'journal-edit-heading';
    pageTitle.textContent = pageHeading;
    box.appendChild(pageTitle);
    if (makerValue('maker_body') !== '') {
      box.appendChild(line('journal-hint', makerValue('maker_body')));
    }
    box.appendChild(line('journal-slot-state', makerValue('maker_label') || labelDefault));
    box.appendChild(line('journal-hint', 'Linked page: ' + path));
    var pageImage = makerPreviewImage();
    if (pageImage && safeUrl(pageImage)) {
      var pagePhoto = document.createElement('img');
      pagePhoto.src = safeUrl(pageImage);
      pagePhoto.alt = makerValue('maker_image_alt') || pageHeading;
      box.appendChild(pagePhoto);
    }
    box.appendChild(line('journal-hint', 'The public journal does not currently print this block.'));
  }

  var makerPreviewUrl = '';
  var makerInput = document.getElementById('maker_image');

  function clearMakerSelection() {
    if (makerPreviewUrl) {
      URL.revokeObjectURL(makerPreviewUrl);
      makerPreviewUrl = '';
    }
    if (makerInput) {
      makerInput.value = '';
    }
    var selected = root.querySelector('[data-maker-selected]');
    if (selected) {
      selected.hidden = true;
    }
    var image = root.querySelector('[data-maker-selected-image]');
    if (image) {
      image.removeAttribute('src');
    }
    var error = root.querySelector('[data-maker-file-error]');
    if (error) {
      error.hidden = true;
      error.textContent = '';
    }
    updateDirty();
    renderMakerPreview();
  }

  function acceptMakerFile(file) {
    var error = root.querySelector('[data-maker-file-error]');
    if (!file) {
      clearMakerSelection();
      return;
    }
    var allowedType = file.type === 'image/jpeg' || file.type === 'image/png' || file.type === 'image/webp';
    var allowedName = /\.(jpe?g|png|webp)$/i.test(file.name || '');
    if (!allowedType && !allowedName) {
      clearMakerSelection();
      if (error) {
        error.hidden = false;
        error.textContent = 'Use a JPG, PNG, or WebP image.';
      }
      return;
    }
    if (file.size > 5120 * 1024) {
      clearMakerSelection();
      if (error) {
        error.hidden = false;
        error.textContent = 'The maker image field must not be greater than 5120 kilobytes.';
      }
      return;
    }
    if (makerPreviewUrl) {
      URL.revokeObjectURL(makerPreviewUrl);
    }
    makerPreviewUrl = URL.createObjectURL(file);
    var selected = root.querySelector('[data-maker-selected]');
    var image = root.querySelector('[data-maker-selected-image]');
    var name = root.querySelector('[data-maker-selected-name]');
    if (image) {
      image.src = makerPreviewUrl;
      image.alt = '';
    }
    if (name) {
      name.textContent = file.name;
    }
    if (selected) {
      selected.hidden = false;
    }
    if (error) {
      error.hidden = true;
      error.textContent = '';
    }
    updateDirty();
    renderMakerPreview();
  }

  if (makerInput) {
    makerInput.addEventListener('change', function () {
      acceptMakerFile(makerInput.files && makerInput.files[0]);
    });
  }
  var makerDrop = root.querySelector('[data-maker-drop]');
  if (makerDrop) {
    ['dragenter', 'dragover'].forEach(function (name) {
      makerDrop.addEventListener(name, function (event) {
        event.preventDefault();
        makerDrop.classList.add('is-drag');
      });
    });
    makerDrop.addEventListener('dragleave', function () {
      makerDrop.classList.remove('is-drag');
    });
    makerDrop.addEventListener('drop', function (event) {
      event.preventDefault();
      makerDrop.classList.remove('is-drag');
      var file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
      if (!file || !makerInput) {
        return;
      }
      var transfer = new DataTransfer();
      transfer.items.add(file);
      makerInput.files = transfer.files;
      acceptMakerFile(file);
    });
  }
  var makerCancel = root.querySelector('[data-maker-cancel]');
  if (makerCancel) {
    makerCancel.addEventListener('click', clearMakerSelection);
  }

  var makerPanel = root.querySelector('[data-journal-panel="maker"]');
  if (makerPanel) {
    makerPanel.addEventListener('click', function (event) {
      var choose = event.target.closest('[data-maker-choose]');
      var clear = event.target.closest('[data-maker-clear]');
      var select = document.getElementById('maker_post_id');
      if (choose && select) {
        openPicker(select, choose);
      }
      if (clear && select) {
        select.value = '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }
  root.querySelectorAll('[data-maker-mode]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (button.dataset.makerMode === 'page') {
        var path = document.getElementById('maker_path');
        if (path) {
          path.focus();
        }
        return;
      }
      var choose = root.querySelector('[data-maker-choose]');
      if (choose) {
        choose.focus();
      }
    });
  });

  var discardMaker = root.querySelector('[data-discard-maker]');
  if (discardMaker) {
    discardMaker.addEventListener('click', function () {
      if (!makerPanel) {
        return;
      }
      makerPanel.querySelectorAll('input, textarea, select').forEach(function (field) {
        if (field.type === 'file') {
          return;
        }
        if (field.type === 'checkbox' || field.type === 'radio') {
          field.checked = field.dataset.journalInitial === '1';
          return;
        }
        if (typeof field.dataset.journalInitial === 'string') {
          field.value = field.dataset.journalInitial;
        }
      });
      clearMakerSelection();
      renderMakerFace();
      paintMakerMode();
      renderMakerPreview();
      updateDirty();
    });
  }

  var previewDialog = root.querySelector('[data-preview-dialog]');
  var previewReturn = null;

  function previewFocusable() {
    if (!previewDialog) {
      return [];
    }
    return Array.prototype.filter.call(previewDialog.querySelectorAll('button, a'), function (node) {
      return !node.disabled && !node.hidden;
    });
  }

  function closePreview() {
    if (!previewDialog || previewDialog.hidden) {
      return;
    }
    previewDialog.hidden = true;
    if (previewReturn && previewReturn.focus) {
      previewReturn.focus();
    }
    previewReturn = null;
  }

  function openPreview(opener) {
    if (!previewDialog) {
      return;
    }
    previewReturn = opener || null;
    syncCopy();
    renderStories();
    previewDialog.hidden = false;
    var closeButton = previewDialog.querySelector('[data-preview-close]');
    if (closeButton) {
      closeButton.focus();
    }
  }

  root.querySelectorAll('[data-preview-open]').forEach(function (button) {
    button.addEventListener('click', function () {
      openPreview(button);
    });
  });
  if (previewDialog) {
    var previewClose = previewDialog.querySelector('[data-preview-close]');
    if (previewClose) {
      previewClose.addEventListener('click', closePreview);
    }
    previewDialog.addEventListener('click', function (event) {
      if (event.target === previewDialog) {
        closePreview();
      }
    });
    document.addEventListener('keydown', function (event) {
      if (!previewDialog || previewDialog.hidden) {
        return;
      }
      if (picker && !picker.hidden) {
        return;
      }
      if (event.key === 'Escape') {
        event.preventDefault();
        closePreview();
        return;
      }
      if (event.key !== 'Tab') {
        return;
      }
      var items = previewFocusable();
      if (items.length === 0) {
        return;
      }
      var first = items[0];
      var last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
  }

  var initial = normalizeSection(root.dataset.openSection || 'cover');
  if (root.dataset.lock !== '1') {
    var hash = window.location.hash.replace(/^#/, '');
    if (hash) {
      initial = normalizeSection(hash);
    }
  }
  showSection(initial, false);
  renderSlots();
  renderEditPreview();
  renderCloserPreview();
  renderMakerFace();
  paintMakerMode();
  renderMakerPreview();
  updateDirty();

  var errors = root.querySelector('[data-journal-errors]');
  if (errors) {
    errors.focus();
  }
})();
