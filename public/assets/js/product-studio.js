(function () {
  var form = document.querySelector('[data-product-studio]');
  if (!form) return;

  var dirty = false;
  var submitting = false;
  var saveState = form.querySelector('[data-save-state]');
  var saveButton = form.querySelector('[data-save]');
  var orderStatus = form.querySelector('[data-order-status]');
  var photoList = form.querySelector('[data-photo-list]');
  var specList = form.querySelector('[data-spec-list]');
  var specTemplate = document.getElementById('spec-row-template');
  var nextSpecIndex = specList ? specList.querySelectorAll('.product-editor-spec').length : 0;
  var photoInput = document.getElementById('new-photos');
  var dropZone = form.querySelector('[data-drop-zone]');
  var uploadErrors = form.querySelector('[data-upload-errors]');
  var photoPreview = form.querySelector('[data-photo-preview]');
  var overlay = document.querySelector('[data-photo-overlay]');
  var overlayImage = overlay ? overlay.querySelector('[data-overlay-image]') : null;
  var overlayAlt = overlay ? overlay.querySelector('[data-overlay-alt]') : null;
  var preview = document.querySelector('[data-preview]');
  var previewFrame = preview ? preview.querySelector('[data-preview-frame]') : null;
  var activeCard = null;
  var dragPhoto = null;
  var dragSpec = null;
  var filteringFiles = false;
  var maxBytes = 5120 * 1024;
  var slugPattern = /^[A-Za-z0-9_-]+$/;
  var sections = ['basics', 'photos', 'specifications', 'story', 'search'];
  var currentSection = form.dataset.openSection || 'basics';
  var saveModes = {
    fresh: { icon: 'bi-circle', label: 'Not saved', className: 'is-fresh' },
    saved: { icon: 'bi-check-circle', label: 'All changes saved', className: 'is-saved' },
    dirty: { icon: 'bi-exclamation-circle', label: 'Unsaved changes', className: 'is-dirty' },
    error: { icon: 'bi-exclamation-circle', label: 'Check the highlighted fields', className: 'is-error' },
    saving: { icon: 'bi-arrow-repeat', label: 'Saving', className: 'is-saving' }
  };
  var fieldTabs = {
    name: 'basics',
    slug: 'basics',
    category_id: 'basics',
    color: 'basics',
    finishes_text: 'basics',
    applications_text: 'basics',
    photos: 'photos',
    specifications: 'specifications',
    summary: 'story',
    detail: 'story',
    application: 'story',
    meta_title: 'search',
    meta_description: 'search'
  };

  function setMode(mode) {
    var choice = saveModes[mode];
    var icon = form.querySelector('[data-save-icon]');
    var label = form.querySelector('[data-save-label]');
    if (!saveState || !choice) return;
    saveState.classList.remove('is-fresh', 'is-saved', 'is-dirty', 'is-error', 'is-saving');
    saveState.classList.add(choice.className);
    if (label) label.textContent = choice.label;
    if (icon) icon.className = 'bi ' + choice.icon;
  }

  function showSection(key, updateHash) {
    if (sections.indexOf(key) === -1) return;
    currentSection = key;
    document.documentElement.dataset.productSection = key;
    form.querySelectorAll('[data-product-panel]').forEach(function (panel) {
      var on = panel.dataset.productPanel === key;
      panel.classList.toggle('is-open', on);
    });
    var selectedTab = null;
    form.querySelectorAll('[data-section-tab]').forEach(function (tab) {
      var selected = tab.dataset.sectionTab === key;
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      tab.tabIndex = selected ? 0 : -1;
      if (selected) selectedTab = tab;
    });
    var crumb = form.querySelector('[data-crumb]');
    if (crumb && selectedTab) crumb.textContent = selectedTab.dataset.label || '';
    if (updateHash) {
      var url = new URL(window.location.href);
      url.hash = key;
      history.replaceState(null, '', url);
    }
  }

  function markDirty() {
    if (submitting) return;
    dirty = true;
    setMode('dirty');
    var tab = form.querySelector('[data-section-tab="' + currentSection + '"]');
    if (tab) tab.dataset.dirty = '1';
  }

  function announce(message) {
    if (!orderStatus) return;
    orderStatus.textContent = '';
    window.setTimeout(function () {
      orderStatus.textContent = message;
    }, 20);
  }

  function cards() {
    return photoList ? Array.from(photoList.querySelectorAll('[data-photo-card]')) : [];
  }

  function activeCards() {
    return cards().filter(function (card) {
      return !card.classList.contains('is-removed');
    });
  }

  function renumberPhotos() {
    activeCards().forEach(function (card, index) {
      var position = card.querySelector('[data-photo-position]');
      var badge = card.querySelector('[data-cover-badge]');
      var makeCoverButton = card.querySelector('[data-make-cover]');
      if (position) position.value = String(index);
      if (badge) badge.hidden = index !== 0;
      if (makeCoverButton) makeCoverButton.hidden = index === 0;
    });
  }

  function moveCard(card, direction) {
    if (!photoList || !card) return;
    var siblings = activeCards();
    var index = siblings.indexOf(card);
    var target = siblings[index + direction];
    if (!target) return;
    if (direction < 0) {
      photoList.insertBefore(card, target);
    } else {
      photoList.insertBefore(target, card);
    }
    renumberPhotos();
    markDirty();
    announce(direction < 0 ? 'Photo moved earlier.' : 'Photo moved later.');
  }

  function makeCover(card) {
    if (!photoList || !card || card.classList.contains('is-removed')) return;
    photoList.prepend(card);
    renumberPhotos();
    markDirty();
    announce('This photo is now the cover.');
  }

  function setRemoved(card, removed) {
    var box = card.querySelector('[data-remove-image]');
    var label = card.querySelector('[data-remove-label]');
    card.classList.toggle('is-removed', removed);
    if (box) box.checked = removed;
    if (label) label.textContent = removed ? 'Will be removed' : 'Remove';
    renumberPhotos();
    markDirty();
  }

  function openOverlay(card) {
    if (!overlay || !card) return;
    activeCard = card;
    var image = card.querySelector('img');
    var alt = card.querySelector('[data-photo-alt]');
    if (overlayImage && image) {
      overlayImage.src = image.currentSrc || image.src;
    }
    if (overlayAlt && alt) overlayAlt.value = alt.value;
    overlay.hidden = false;
    overlay.querySelector('[data-overlay-close]')?.focus();
  }

  function closeOverlay() {
    if (!overlay) return;
    overlay.hidden = true;
    var opener = activeCard?.querySelector('[data-open-photo]');
    activeCard = null;
    opener?.focus();
  }

  function syncOverlayAlt() {
    if (!activeCard || !overlayAlt) return;
    var alt = activeCard.querySelector('[data-photo-alt]');
    if (alt) alt.value = overlayAlt.value;
    markDirty();
  }

  function acceptFile(file) {
    var allowed = ['image/jpeg', 'image/png', 'image/webp'];
    var name = file.name.toLowerCase();
    var extensionOk = name.endsWith('.jpg') || name.endsWith('.jpeg') || name.endsWith('.png') || name.endsWith('.webp');
    if (allowed.indexOf(file.type) === -1 && !extensionOk) {
      return 'Use a JPG, PNG, or WebP file.';
    }
    if (file.size > maxBytes) {
      return 'Each photo must be 5 MB or smaller.';
    }
    return '';
  }

  function applyFiles(fileList, merge) {
    if (!photoInput || typeof DataTransfer === 'undefined' || filteringFiles) return;
    var incoming = Array.from(fileList || []);
    var rejected = [];
    var accepted = merge ? Array.from(photoInput.files || []) : [];
    incoming.forEach(function (file) {
      var problem = acceptFile(file);
      if (problem) {
        rejected.push(file.name + ': ' + problem);
        return;
      }
      var duplicate = accepted.some(function (current) {
        return current.name === file.name && current.size === file.size && current.lastModified === file.lastModified;
      });
      if (duplicate) {
        if (merge) rejected.push(file.name + ': this file is already selected.');
        return;
      }
      accepted.push(file);
    });
    var transfer = new DataTransfer();
    accepted.forEach(function (file) {
      transfer.items.add(file);
    });
    filteringFiles = true;
    photoInput.files = transfer.files;
    filteringFiles = false;
    if (uploadErrors) {
      uploadErrors.replaceChildren();
      rejected.forEach(function (message) {
        var item = document.createElement('li');
        item.textContent = message;
        uploadErrors.append(item);
      });
    }
    renderNewPhotos();
    if (accepted.length) markDirty();
  }

  function renderNewPhotos() {
    if (!photoPreview || !photoInput) return;
    photoPreview.replaceChildren();
    Array.from(photoInput.files || []).forEach(function (file) {
      var figure = document.createElement('figure');
      var image = document.createElement('img');
      var caption = document.createElement('figcaption');
      image.alt = '';
      image.src = URL.createObjectURL(file);
      caption.textContent = file.name;
      figure.append(image, caption);
      photoPreview.append(figure);
    });
    refreshStatus();
  }

  function specRows() {
    return specList ? Array.from(specList.querySelectorAll('.product-editor-spec')) : [];
  }

  function renumberSpecs() {
    specRows().forEach(function (row, index) {
      var label = row.querySelector('[data-spec-label]');
      var value = row.querySelector('[data-spec-value]');
      if (label) label.name = 'specs[' + index + '][label]';
      if (value) value.name = 'specs[' + index + '][value]';
    });
    nextSpecIndex = specRows().length;
  }

  function addSpecRow(label, value) {
    if (!specList || !specTemplate) return null;
    var html = specTemplate.innerHTML.replaceAll('__INDEX__', String(nextSpecIndex));
    nextSpecIndex += 1;
    specList.insertAdjacentHTML('beforeend', html);
    var row = specList.lastElementChild;
    if (label) row.querySelector('[data-spec-label]').value = label;
    if (value) row.querySelector('[data-spec-value]').value = value;
    return row;
  }

  function moveSpec(row, direction) {
    if (!specList || !row) return;
    var rows = specRows();
    var index = rows.indexOf(row);
    var target = rows[index + direction];
    if (!target) return;
    if (direction < 0) specList.insertBefore(row, target);
    else specList.insertBefore(target, row);
    renumberSpecs();
    markDirty();
    refreshStatus();
  }

  function specTemplates() {
    try {
      return JSON.parse(form.querySelector('[data-spec-templates]')?.textContent || '{}');
    } catch (error) {
      return {};
    }
  }

  function selectedMaterialName() {
    var select = form.querySelector('#category_id');
    var option = select && select.selectedOptions && select.selectedOptions[0];
    return option && option.value ? option.textContent.trim() : '';
  }

  function updateMaterialTemplate() {
    var button = form.querySelector('[data-apply-material]');
    if (!button) return;
    var name = selectedMaterialName();
    var labels = name ? specTemplates()[name] : null;
    if (Array.isArray(labels)) {
      button.disabled = false;
      button.textContent = 'Add ' + name + ' labels';
      return;
    }
    button.disabled = true;
    button.textContent = name ? 'No suggested labels for ' + name : 'Select a material first';
  }

  function applySpecLabels(labels) {
    var existing = specRows().map(function (row) {
      return (row.querySelector('[data-spec-label]')?.value || '').trim().toLowerCase();
    });
    var added = 0;
    labels.forEach(function (label) {
      if (existing.indexOf(String(label).toLowerCase()) !== -1) return;
      var empty = specRows().find(function (row) {
        return !row.querySelector('[data-spec-label]')?.value.trim() && !row.querySelector('[data-spec-value]')?.value.trim();
      });
      if (empty) {
        empty.querySelector('[data-spec-label]').value = label;
      } else {
        addSpecRow(label, '');
      }
      existing.push(String(label).toLowerCase());
      added += 1;
    });
    if (added) {
      markDirty();
      refreshStatus();
    }
    announce(added ? 'Added ' + added + ' specification labels. Existing values were kept.' : 'Those labels are already in the list.');
  }

  function renderSpecPreview() {
    var target = form.querySelector('[data-spec-preview]');
    if (!target) return;
    target.replaceChildren();
    var table = document.createElement('table');
    var count = 0;
    specRows().forEach(function (row) {
      var label = row.querySelector('[data-spec-label]')?.value.trim();
      var value = row.querySelector('[data-spec-value]')?.value.trim();
      if (!label || !value) return;
      count += 1;
      var line = document.createElement('tr');
      var headingCell = document.createElement('th');
      var valueCell = document.createElement('td');
      headingCell.textContent = label;
      valueCell.textContent = value;
      line.append(headingCell, valueCell);
      table.append(line);
    });
    if (!count) {
      var empty = document.createElement('p');
      empty.className = 'product-spec-preview-empty';
      empty.textContent = 'Complete rows appear here, in this order.';
      target.append(empty);
      return;
    }
    target.append(table);
  }

  function paragraphs(text) {
    return String(text || '').split(/\n{2,}/).map(function (part) {
      return part.trim();
    }).filter(Boolean);
  }

  function appendParagraphs(parent, text) {
    paragraphs(text).forEach(function (paragraph) {
      var node = document.createElement('p');
      paragraph.split('\n').forEach(function (line, index) {
        if (index > 0) node.append(document.createElement('br'));
        node.append(document.createTextNode(line));
      });
      parent.append(node);
    });
  }

  function renderStory() {
    var target = form.querySelector('[data-story-preview]');
    if (!target) return;
    target.replaceChildren();
    var summary = document.createElement('p');
    summary.className = 'product-editor-preview-summary';
    summary.textContent = form.querySelector('#summary')?.value || 'Summary preview';
    target.append(summary);
    var detailTitle = document.createElement('h3');
    detailTitle.textContent = 'Product Detail';
    target.append(detailTitle);
    appendParagraphs(target, form.querySelector('#detail')?.value);
    var applicationTitle = document.createElement('h3');
    applicationTitle.textContent = 'Application Areas';
    target.append(applicationTitle);
    appendParagraphs(target, form.querySelector('#application')?.value);
    var count = form.querySelector('[data-summary-count]');
    if (count) count.textContent = String((form.querySelector('#summary')?.value || '').length);
  }

  function renderSeo() {
    var titleValue = form.querySelector('#meta_title')?.value.trim() || '';
    var descriptionValue = form.querySelector('#meta_description')?.value.trim() || '';
    var title = titleValue || form.querySelector('#name')?.value.trim() || 'Page title';
    var slug = form.querySelector('#slug')?.value.trim();
    var description = descriptionValue || form.querySelector('#summary')?.value.trim() || 'Page description';
    var titleNode = form.querySelector('[data-seo-title]');
    var urlNode = form.querySelector('[data-seo-url]');
    var descriptionNode = form.querySelector('[data-seo-description]');
    if (titleNode) titleNode.textContent = title;
    if (urlNode) urlNode.textContent = window.location.origin + '/' + (slug || 'stone-name');
    if (descriptionNode) descriptionNode.textContent = description;
    var titleCount = form.querySelector('[data-title-count]');
    var descriptionCount = form.querySelector('[data-description-count]');
    if (titleCount) titleCount.textContent = titleGuidance(titleValue.length, 50, 60, 'page title');
    if (descriptionCount) descriptionCount.textContent = titleGuidance(descriptionValue.length, 120, 160, 'description');
    var slugField = form.querySelector('#slug');
    var slugHint = form.querySelector('[data-slug-hint]');
    if (slugField) {
      var invalid = slugField.value.trim() !== '' && !slugPattern.test(slugField.value.trim());
      slugField.classList.toggle('is-invalid', invalid);
      if (slugHint) slugHint.textContent = invalid
        ? 'Use only letters, numbers, hyphens, or underscores.'
        : 'Use letters, numbers, hyphens, or underscores.';
    }
  }

  function titleGuidance(length, shortLimit, longLimit, label) {
    if (length === 0) return 'The ' + label + ' is empty. This is optional, and a search snippet may use other text.';
    if (length < shortLimit) return length + ' characters. A little short for a typical ' + label + '.';
    if (length > longLimit) return length + ' characters. Longer than a typical ' + label + '.';
    return length + ' characters.';
  }

  function hasCompleteSpec() {
    return specRows().some(function (row) {
      return row.querySelector('[data-spec-label]')?.value.trim() && row.querySelector('[data-spec-value]')?.value.trim();
    });
  }

  function setMark(name, state, text) {
    document.querySelectorAll('[data-check="' + name + '"], [data-section-state="' + name + '"]').forEach(function (node) {
      node.dataset.state = state;
      node.textContent = text;
    });
  }

  function refreshStatus() {
    var name = form.querySelector('#name')?.value.trim() || '';
    var material = form.querySelector('#category_id')?.value || '';
    var photos = activeCards().length + (photoInput && photoInput.files ? photoInput.files.length : 0);
    var missingAlt = activeCards().some(function (card) {
      return !card.querySelector('[data-photo-alt]')?.value.trim();
    });
    var summary = form.querySelector('#summary')?.value.trim() || '';
    var title = form.querySelector('#meta_title')?.value.trim() || '';
    var description = form.querySelector('#meta_description')?.value.trim() || '';
    var basicsError = form.querySelector('.product-editor-errors') && (!name || !material);

    setMark('name', name ? 'complete' : 'required', name ? 'Complete' : 'Required');
    setMark('category', material ? 'complete' : 'required', material ? 'Complete' : 'Required');
    setMark('photos', photos ? 'complete' : 'recommended', photos ? 'Complete' : 'Recommended');
    setMark('alts', photos && !missingAlt ? 'complete' : 'recommended', photos && !missingAlt ? 'Complete' : 'Recommended');
    setMark('specs', hasCompleteSpec() ? 'complete' : 'recommended', hasCompleteSpec() ? 'Complete' : 'Recommended');
    setMark('summary', summary ? 'complete' : 'recommended', summary ? 'Complete' : 'Recommended');
    setMark('title', title ? 'complete' : 'recommended', title ? 'Complete' : 'Recommended');
    setMark('description', description ? 'complete' : 'recommended', description ? 'Complete' : 'Recommended');
    setMark('basics', basicsError ? 'error' : (name && material ? 'complete' : 'required'), basicsError ? 'Error' : (name && material ? 'Complete' : 'Required'));
    setMark('story', summary ? 'complete' : 'recommended', summary ? 'Complete' : 'Recommended');
    setMark('search', title && description ? 'complete' : 'recommended', title && description ? 'Complete' : 'Recommended');

    var shown = !!form.querySelector('[data-published]')?.checked;
    form.querySelectorAll('[data-bar-name]').forEach(function (node) {
      node.textContent = name || 'New stone';
    });
    form.querySelectorAll('[data-bar-visibility]').forEach(function (node) {
      node.textContent = shown ? 'Shown' : 'Hidden';
      node.classList.toggle('is-shown', shown);
      node.classList.toggle('is-hidden', !shown);
    });
    var materialSelect = form.querySelector('#category_id');
    var materialLabel = form.querySelector('[data-stone-material]');
    if (materialLabel && materialSelect) {
      var option = materialSelect.selectedOptions && materialSelect.selectedOptions[0];
      materialLabel.textContent = option && option.value ? option.textContent : 'No material yet';
    }
    var cover = form.querySelector('[data-stone-cover]');
    var coverEmpty = form.querySelector('[data-stone-cover-empty]');
    var coverSource = activeCards()[0]?.querySelector('img') || form.querySelector('[data-photo-preview] img');
    if (cover) {
      if (coverSource) {
        cover.src = coverSource.currentSrc || coverSource.src;
        cover.hidden = false;
      } else {
        cover.removeAttribute('src');
        cover.hidden = true;
      }
    }
    if (coverEmpty) coverEmpty.hidden = !!coverSource;
    updateMaterialTemplate();
    renderSpecPreview();
    renderStory();
    renderSeo();
  }

  function renderPreview() {
    if (!previewFrame) return;
    previewFrame.replaceChildren();
    var gallery = document.createElement('div');
    gallery.className = 'product-preview-gallery';
    activeCards().forEach(function (card) {
      var source = card.querySelector('img');
      if (!source) return;
      var image = document.createElement('img');
      image.src = source.currentSrc || source.src;
      image.alt = card.querySelector('[data-photo-alt]')?.value || '';
      gallery.append(image);
    });
    Array.from(photoInput?.files || []).forEach(function (file) {
      var image = document.createElement('img');
      image.src = URL.createObjectURL(file);
      image.alt = '';
      gallery.append(image);
    });
    previewFrame.append(gallery);

    var heading = document.createElement('h2');
    heading.textContent = form.querySelector('#name')?.value.trim() || 'Product name';
    previewFrame.append(heading);

    var basics = document.createElement('p');
    var colour = form.querySelector('#color')?.value.trim();
    var finishes = form.querySelector('#finishes_text')?.value.trim();
    basics.textContent = [colour, finishes].filter(Boolean).join(' · ');
    previewFrame.append(basics);

    var table = document.createElement('table');
    specRows().forEach(function (row) {
      var label = row.querySelector('[data-spec-label]')?.value.trim();
      var value = row.querySelector('[data-spec-value]')?.value.trim();
      if (!label || !value) return;
      var line = document.createElement('tr');
      var headingCell = document.createElement('th');
      var valueCell = document.createElement('td');
      headingCell.textContent = label;
      valueCell.textContent = value;
      line.append(headingCell, valueCell);
      table.append(line);
    });
    previewFrame.append(table);

    var detailHeading = document.createElement('h3');
    detailHeading.textContent = 'Product Detail';
    previewFrame.append(detailHeading);
    appendParagraphs(previewFrame, form.querySelector('#detail')?.value);

    var applicationHeading = document.createElement('h3');
    applicationHeading.textContent = 'Application Areas';
    previewFrame.append(applicationHeading);
    appendParagraphs(previewFrame, form.querySelector('#application')?.value);
  }

  form.addEventListener('input', function (event) {
    if (event.target === form.querySelector('#name') && form.querySelector('[data-slug-follows-name]') && form.querySelector('#slug')?.dataset.touched !== '1') {
      form.querySelector('#slug').value = event.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
    markDirty();
    refreshStatus();
  });

  form.addEventListener('change', function () {
    markDirty();
    refreshStatus();
  });

  form.querySelector('#slug')?.addEventListener('input', function (event) {
    event.target.dataset.touched = '1';
  });

  form.addEventListener('submit', function (event) {
    if (submitting) {
      event.preventDefault();
      return;
    }
    var invalid = form.querySelector(':invalid');
    if (invalid) {
      event.preventDefault();
      var panel = invalid.closest('[data-product-panel]');
      if (panel) showSection(panel.dataset.productPanel, false);
      form.querySelectorAll('input, select, textarea').forEach(function (field) {
        if (field.willValidate && !field.checkValidity()) {
          field.setAttribute('aria-invalid', 'true');
        }
      });
      openIssues();
      return;
    }
    renumberPhotos();
    renumberSpecs();
    submitting = true;
    dirty = false;
    if (saveButton) saveButton.disabled = true;
    setMode('saving');
  });

  window.addEventListener('beforeunload', function (event) {
    if (!dirty || submitting) return;
    event.preventDefault();
    event.returnValue = '';
  });

  photoList?.addEventListener('click', function (event) {
    var card = event.target.closest('[data-photo-card]');
    if (!card) return;
    if (event.target.closest('[data-open-photo]')) openOverlay(card);
    if (event.target.closest('[data-make-cover]')) makeCover(card);
    if (event.target.closest('[data-move-up]')) moveCard(card, -1);
    if (event.target.closest('[data-move-down]')) moveCard(card, 1);
  });

  photoList?.addEventListener('change', function (event) {
    var box = event.target.closest('[data-remove-image]');
    if (!box) return;
    var card = box.closest('[data-photo-card]');
    if (box.checked && !window.confirm('Remove this photo when you save?')) {
      box.checked = false;
      return;
    }
    setRemoved(card, box.checked);
  });

  photoList?.addEventListener('dragstart', function (event) {
    var handle = event.target.closest('[data-drag-handle]');
    dragPhoto = handle ? handle.closest('[data-photo-card]') : null;
    if (!dragPhoto) return;
    dragPhoto.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
  });

  photoList?.addEventListener('dragover', function (event) {
    if (!dragPhoto) return;
    var card = event.target.closest('[data-photo-card]');
    if (!card || card === dragPhoto) return;
    event.preventDefault();
    var bounds = card.getBoundingClientRect();
    var after = event.clientY > bounds.top + bounds.height / 2;
    photoList.insertBefore(dragPhoto, after ? card.nextElementSibling : card);
  });

  photoList?.addEventListener('drop', function (event) {
    if (!dragPhoto) return;
    event.preventDefault();
    dragPhoto.classList.remove('is-dragging');
    dragPhoto = null;
    renumberPhotos();
    markDirty();
    announce('Photo order updated. Save to keep it.');
  });

  photoList?.addEventListener('dragend', function () {
    dragPhoto?.classList.remove('is-dragging');
    dragPhoto = null;
    renumberPhotos();
  });

  form.querySelector('[data-pick-photos]')?.addEventListener('click', function () {
    photoInput?.click();
  });

  photoInput?.addEventListener('change', function () {
    if (filteringFiles) return;
    applyFiles(photoInput.files, false);
  });

  dropZone?.addEventListener('dragover', function (event) {
    event.preventDefault();
    dropZone.classList.add('is-dropping');
  });

  dropZone?.addEventListener('dragleave', function () {
    dropZone.classList.remove('is-dropping');
  });

  dropZone?.addEventListener('drop', function (event) {
    event.preventDefault();
    dropZone.classList.remove('is-dropping');
    applyFiles(event.dataTransfer?.files, true);
  });

  overlay?.addEventListener('click', function (event) {
    if (event.target === overlay || event.target.closest('[data-overlay-close]')) closeOverlay();
    if (event.target.closest('[data-overlay-cover]')) makeCover(activeCard);
    if (event.target.closest('[data-overlay-up]')) moveCard(activeCard, -1);
    if (event.target.closest('[data-overlay-down]')) moveCard(activeCard, 1);
    if (event.target.closest('[data-overlay-remove]')) {
      if (activeCard && window.confirm('Remove this photo when you save?')) {
        setRemoved(activeCard, true);
        closeOverlay();
      }
    }
  });

  overlayAlt?.addEventListener('input', syncOverlayAlt);

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    if (issueModal && !issueModal.hidden) {
      closeIssues();
      return;
    }
    if (overlay && !overlay.hidden) closeOverlay();
  });

  form.querySelector('[data-add-spec]')?.addEventListener('click', function () {
    addSpecRow('', '');
    markDirty();
  });

  specList?.addEventListener('click', function (event) {
    var row = event.target.closest('.product-editor-spec');
    if (!row) return;
    if (event.target.closest('[data-spec-up]')) moveSpec(row, -1);
    if (event.target.closest('[data-spec-down]')) moveSpec(row, 1);
    if (event.target.closest('[data-duplicate-spec]')) {
      addSpecRow(row.querySelector('[data-spec-label]')?.value || '', row.querySelector('[data-spec-value]')?.value || '');
      markDirty();
      refreshStatus();
    }
    if (event.target.closest('[data-remove-spec]')) {
      var label = row.querySelector('[data-spec-label]')?.value.trim();
      var value = row.querySelector('[data-spec-value]')?.value.trim();
      if ((label || value) && !window.confirm('Remove this specification row?')) return;
      row.remove();
      renumberSpecs();
      markDirty();
      refreshStatus();
    }
  });

  specList?.addEventListener('dragstart', function (event) {
    var handle = event.target.closest('[data-spec-handle]');
    dragSpec = handle ? handle.closest('.product-editor-spec') : null;
    if (!dragSpec) return;
    dragSpec.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
  });

  specList?.addEventListener('dragover', function (event) {
    if (!dragSpec) return;
    var row = event.target.closest('.product-editor-spec');
    if (!row || row === dragSpec) return;
    event.preventDefault();
    var bounds = row.getBoundingClientRect();
    var after = event.clientY > bounds.top + bounds.height / 2;
    specList.insertBefore(dragSpec, after ? row.nextElementSibling : row);
  });

  specList?.addEventListener('drop', function (event) {
    if (!dragSpec) return;
    event.preventDefault();
    dragSpec.classList.remove('is-dragging');
    dragSpec = null;
    renumberSpecs();
    markDirty();
    refreshStatus();
  });

  specList?.addEventListener('dragend', function () {
    dragSpec?.classList.remove('is-dragging');
    dragSpec = null;
    renumberSpecs();
  });

  form.querySelector('[data-apply-material]')?.addEventListener('click', function () {
    var labels = specTemplates()[selectedMaterialName()];
    if (!Array.isArray(labels)) return;
    applySpecLabels(labels);
  });

  form.querySelector('[data-apply-template]')?.addEventListener('click', function () {
    var selected = form.querySelector('[data-spec-template]')?.value;
    if (!selected) {
      announce('Choose a material template first.');
      return;
    }
    applySpecLabels(specTemplates()[selected] || []);
  });

  form.querySelectorAll('[data-section-tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      showSection(tab.dataset.sectionTab, true);
    });
  });

  form.querySelector('[data-section-nav]')?.addEventListener('keydown', function (event) {
    var tabs = Array.prototype.slice.call(form.querySelectorAll('[data-section-tab]'));
    var index = tabs.findIndex(function (tab) { return tab.dataset.sectionTab === currentSection; });
    var next = index;
    if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
    else if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = tabs.length - 1;
    else return;
    event.preventDefault();
    showSection(tabs[next].dataset.sectionTab, true);
    tabs[next].focus();
  });

  var issueModal = document.querySelector('[data-issue-modal]');

  function openIssues() {
    if (!issueModal) return;
    refreshStatus();
    issueModal.hidden = false;
    var clientNote = issueModal.querySelector('[data-issue-client]');
    if (clientNote) clientNote.hidden = !!issueModal.querySelector('[data-issue-problem]');
    issueModal.querySelector('[data-issue-close]')?.focus();
  }

  function closeIssues() {
    if (!issueModal) return;
    issueModal.hidden = true;
  }

  document.querySelectorAll('[data-goto]').forEach(function (button) {
    button.addEventListener('click', function () {
      var key = button.dataset.goto;
      closeIssues();
      showSection(fieldTabs[key] || 'basics', true);
      var target = document.getElementById(key);
      if (target?.matches('input, textarea, select')) target.focus();
    });
  });

  issueModal?.addEventListener('click', function (event) {
    if (event.target === issueModal || event.target.closest('[data-issue-close]')) closeIssues();
  });

  form.querySelector('[data-open-preview]')?.addEventListener('click', function () {
    renderPreview();
    if (!preview) return;
    preview.hidden = false;
    preview.querySelector('[data-preview-close]')?.focus();
  });

  preview?.querySelector('[data-preview-close]')?.addEventListener('click', function () {
    preview.hidden = true;
    form.querySelector('[data-open-preview]')?.focus();
  });

  preview?.querySelector('[data-preview-desktop]')?.addEventListener('click', function (event) {
    previewFrame?.classList.add('is-desktop');
    previewFrame?.classList.remove('is-mobile');
    event.currentTarget.setAttribute('aria-pressed', 'true');
    preview.querySelector('[data-preview-mobile]')?.setAttribute('aria-pressed', 'false');
  });

  preview?.querySelector('[data-preview-mobile]')?.addEventListener('click', function (event) {
    previewFrame?.classList.add('is-mobile');
    previewFrame?.classList.remove('is-desktop');
    event.currentTarget.setAttribute('aria-pressed', 'true');
    preview.querySelector('[data-preview-desktop]')?.setAttribute('aria-pressed', 'false');
  });

  document.querySelector('[data-duplicate-form]')?.addEventListener('submit', function (event) {
    if (!window.confirm('Create a hidden copy of this stone? The original will not change.')) {
      event.preventDefault();
      return;
    }
    submitting = true;
    dirty = false;
  });

  window.addEventListener('hashchange', function () {
    var next = location.hash.replace(/^#/, '');
    if (sections.indexOf(next) !== -1) showSection(next, false);
  });

  renumberPhotos();
  if (form.dataset.lock !== '1') {
    var hash = location.hash.replace(/^#/, '');
    if (sections.indexOf(hash) !== -1) currentSection = hash;
  }
  showSection(currentSection, false);
  refreshStatus();
  if (form.dataset.saveMode === 'error') setMode('error');
  if (issueModal && !issueModal.hidden) {
    openIssues();
  }
})();
