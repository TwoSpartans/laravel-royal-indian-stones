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
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function markDirty() {
    if (submitting) return;
    dirty = true;
    if (saveState) saveState.textContent = 'Unsaved';
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
      if (position) position.value = String(index);
      if (badge) badge.hidden = index !== 0;
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
    var title = form.querySelector('#meta_title')?.value.trim() || form.querySelector('#name')?.value.trim() || 'Page title';
    var slug = form.querySelector('#slug')?.value.trim();
    var description = form.querySelector('#meta_description')?.value.trim() || form.querySelector('#summary')?.value.trim() || 'Page description';
    var titleNode = form.querySelector('[data-seo-title]');
    var urlNode = form.querySelector('[data-seo-url]');
    var descriptionNode = form.querySelector('[data-seo-description]');
    if (titleNode) titleNode.textContent = title;
    if (urlNode) urlNode.textContent = window.location.origin + '/' + (slug || 'stone-name');
    if (descriptionNode) descriptionNode.textContent = description;
    var titleCount = form.querySelector('[data-title-count]');
    var descriptionCount = form.querySelector('[data-description-count]');
    if (titleCount) titleCount.textContent = titleGuidance(title.length, 50, 60, 'page title');
    if (descriptionCount) descriptionCount.textContent = titleGuidance(description.length, 120, 160, 'description');
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

    var barName = form.querySelector('[data-bar-name]');
    var visibility = form.querySelector('[data-bar-visibility]');
    if (barName) barName.textContent = name || 'New stone';
    if (visibility) visibility.textContent = form.querySelector('[data-published]')?.checked ? 'Shown' : 'Hidden';
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
    renumberPhotos();
    renumberSpecs();
    submitting = true;
    dirty = false;
    if (saveButton) {
      saveButton.disabled = true;
      saveButton.textContent = 'Saving';
    }
    if (saveState) saveState.textContent = 'Saving';
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
    if (event.key === 'Escape' && overlay && !overlay.hidden) closeOverlay();
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
  });

  specList?.addEventListener('dragend', function () {
    dragSpec?.classList.remove('is-dragging');
    dragSpec = null;
    renumberSpecs();
  });

  form.querySelector('[data-apply-template]')?.addEventListener('click', function () {
    var selected = form.querySelector('[data-spec-template]')?.value;
    var templates = {};
    try {
      templates = JSON.parse(form.querySelector('[data-spec-templates]')?.textContent || '{}');
    } catch (error) {
      templates = {};
    }
    var labels = templates[selected] || [];
    var existing = specRows().map(function (row) {
      return (row.querySelector('[data-spec-label]')?.value || '').trim().toLowerCase();
    });
    var added = 0;
    labels.forEach(function (label) {
      if (existing.indexOf(label.toLowerCase()) !== -1) return;
      var empty = specRows().find(function (row) {
        return !row.querySelector('[data-spec-label]')?.value.trim() && !row.querySelector('[data-spec-value]')?.value.trim();
      });
      if (empty) {
        empty.querySelector('[data-spec-label]').value = label;
      } else {
        addSpecRow(label, '');
      }
      existing.push(label.toLowerCase());
      added += 1;
    });
    if (added) {
      markDirty();
      refreshStatus();
    }
    announce(added ? 'Added ' + added + ' specification labels. Existing values were kept.' : 'Those labels are already in the list.');
  });

  document.querySelectorAll('[data-section-link]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      if (reducedMotion) return;
      var section = document.getElementById(link.getAttribute('href').slice(1));
      if (!section) return;
      event.preventDefault();
      section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  document.querySelectorAll('[data-goto]').forEach(function (button) {
    button.addEventListener('click', function () {
      var target = document.getElementById(button.dataset.goto);
      target?.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
      if (target?.matches('input, textarea, select')) target.focus();
    });
  });

  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        document.querySelectorAll('[data-section-link]').forEach(function (link) {
          link.classList.toggle('is-active', link.dataset.sectionLink === entry.target.id);
        });
      });
    }, { rootMargin: '-30% 0px -55% 0px', threshold: 0.1 });
    document.querySelectorAll('[data-section]').forEach(function (section) {
      observer.observe(section);
    });
  }

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

  renumberPhotos();
  refreshStatus();
  if (form.querySelector('.product-editor-errors') && saveState) {
    saveState.textContent = 'Check the form';
  }
  var firstInvalid = form.querySelector('[aria-invalid="true"]');
  if (firstInvalid) {
    firstInvalid.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'center' });
    firstInvalid.focus({ preventScroll: true });
  }
})();
