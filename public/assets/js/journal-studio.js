(function () {
  var form = document.querySelector('[data-journal-studio]');
  if (!form) return;

  var writing = form.querySelector('[data-writing]');
  var body = form.querySelector('[data-body]');
  var title = form.querySelector('[data-title]');
  var slug = form.querySelector('[data-slug]');
  var date = form.querySelector('[data-date]');
  var description = form.querySelector('[data-description]');
  var summary = form.querySelector('#summary');
  var visibility = form.querySelector('[data-visibility]');
  var saveState = form.querySelector('[data-save-state]');
  var saveButton = form.querySelector('[data-save]');
  var barName = form.querySelector('[data-bar-name]');
  var barVisibility = form.querySelector('[data-bar-visibility]');
  var coverInput = document.getElementById('article-cover');
  var coverFrame = form.querySelector('[data-cover-frame]');
  var coverImage = form.querySelector('[data-cover-image]');
  var removeCover = form.querySelector('[data-remove-cover]');
  var inlineInput = document.getElementById('inline-images');
  var uploadErrors = form.querySelector('[data-upload-errors]');
  var summaryCount = form.querySelector('[data-summary-count]');
  var seoTitle = form.querySelector('[data-seo-title]');
  var seoUrl = form.querySelector('[data-seo-url]');
  var seoDescription = form.querySelector('[data-seo-description]');
  var pendingFiles = [];
  var dirty = false;
  var submitting = false;
  var savedSelection = null;
  var maxBytes = 5120 * 1024;

  function markDirty() {
    if (submitting) return;
    dirty = true;
    if (saveState && saveState.textContent !== 'Check the form') saveState.textContent = 'Unsaved';
  }

  function syncBody() {
    if (writing && body) body.value = writing.innerHTML;
  }

  function rememberSelection() {
    var selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) return;
    var range = selection.getRangeAt(0);
    if (writing && writing.contains(range.commonAncestorContainer)) savedSelection = range;
  }

  function restoreSelection() {
    if (!savedSelection) return;
    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(savedSelection);
  }

  function focusWriting() {
    writing.focus();
    restoreSelection();
  }

  function format(command, value) {
    focusWriting();
    document.execCommand('defaultParagraphSeparator', false, 'p');
    document.execCommand(command, false, value || null);
    syncBody();
    markDirty();
  }

  function safeLink(value) {
    var href = value.trim();
    if (href.indexOf('//') === 0) return '';
    if (href.charAt(0) === '/' || href.indexOf('https://') === 0) return href;
    return '';
  }

  function addLink() {
    var current = '';
    var selection = window.getSelection();
    if (selection && selection.anchorNode) {
      var anchor = selection.anchorNode.parentElement ? selection.anchorNode.parentElement.closest('a') : null;
      if (anchor) current = anchor.getAttribute('href') || '';
    }
    var entered = window.prompt('Use a site path such as /products, or a full https:// address.', current || 'https://');
    if (entered === null) return;
    if (entered.trim() === '') {
      format('unlink');
      return;
    }
    var href = safeLink(entered);
    if (href === '') {
      window.alert('Use a site path such as /products, or a full https:// address.');
      return;
    }
    format('createLink', href);
  }

  function showUploadError(message) {
    if (!uploadErrors) return;
    uploadErrors.textContent = message;
  }

  function acceptedFile(file) {
    var type = (file.type || '').toLowerCase();
    if (['image/jpeg', 'image/png', 'image/webp'].indexOf(type) === -1) {
      return 'Each picture must be a JPG, PNG, or WebP file.';
    }
    if (file.size > maxBytes) {
      return 'Each picture must be 5 MB or smaller.';
    }
    return '';
  }

  function insertImage(file) {
    var problem = acceptedFile(file);
    if (problem !== '') {
      showUploadError(problem);
      return;
    }
    showUploadError('');
    var index = pendingFiles.length;
    pendingFiles.push(file);
    focusWriting();
    var image = document.createElement('img');
    image.src = URL.createObjectURL(file);
    image.alt = '';
    image.setAttribute('data-pending', String(index));
    var selection = window.getSelection();
    if (selection && selection.rangeCount > 0 && writing.contains(selection.getRangeAt(0).commonAncestorContainer)) {
      var range = selection.getRangeAt(0);
      range.deleteContents();
      range.insertNode(image);
      range.setStartAfter(image);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
    } else {
      writing.appendChild(image);
    }
    syncBody();
    markDirty();
  }

  function packInlineImages() {
    if (!inlineInput) return;
    var images = writing.querySelectorAll('img[data-pending]');
    var transfer = new DataTransfer();
    Array.prototype.forEach.call(images, function (image, index) {
      var file = pendingFiles[Number(image.getAttribute('data-pending'))];
      if (!file) {
        image.removeAttribute('data-pending');
        return;
      }
      image.setAttribute('data-pending', String(index));
      transfer.items.add(file);
    });
    inlineInput.files = transfer.files;
  }

  function slugFrom(value) {
    return value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }

  function refreshSnippet() {
    var name = title ? title.value.trim() : '';
    var address = slug && slug.value.trim() !== '' ? slug.value.trim() : slugFrom(name);
    if (barName) barName.textContent = name || 'New article';
    if (seoTitle) seoTitle.textContent = name || 'Article title';
    if (seoUrl) seoUrl.textContent = window.location.origin + '/' + (address || 'article');
    var text = description && description.value.trim() !== '' ? description.value.trim() : (summary ? summary.value.trim() : '');
    if (seoDescription) seoDescription.textContent = text || 'The article description appears here.';
    if (summaryCount && summary) summaryCount.textContent = String(summary.value.length);
    if (barVisibility && visibility) barVisibility.textContent = visibility.value === '1' ? 'Shown' : 'Hidden';
  }

  function coverSource() {
    if (removeCover && removeCover.checked && (!coverInput || coverInput.files.length === 0)) return '';
    if (coverInput && coverInput.files.length > 0) return URL.createObjectURL(coverInput.files[0]);
    if (coverImage && !coverImage.hidden && coverImage.getAttribute('src')) return coverImage.getAttribute('src');
    return '';
  }

  function previewPanel() {
    return document.querySelector('[data-preview]');
  }

  function openPreview() {
    var preview = previewPanel();
    var previewFrame = preview ? preview.querySelector('[data-preview-frame]') : null;
    if (!preview || !previewFrame) return;
    syncBody();
    previewFrame.innerHTML = '';
    var source = coverSource();
    if (source !== '') {
      var image = document.createElement('img');
      image.src = source;
      image.alt = '';
      previewFrame.appendChild(image);
    }
    var heading = document.createElement('h1');
    heading.textContent = title && title.value.trim() !== '' ? title.value.trim() : 'New article';
    previewFrame.appendChild(heading);
    if (date && date.value) {
      var time = document.createElement('p');
      time.textContent = date.value;
      previewFrame.appendChild(time);
    }
    var copy = document.createElement('div');
    copy.innerHTML = writing.innerHTML;
    previewFrame.appendChild(copy);
    preview.hidden = false;
    document.body.classList.add('journal-preview-open');
  }

  function closePreview() {
    var preview = previewPanel();
    if (!preview) return;
    preview.hidden = true;
    document.body.classList.remove('journal-preview-open');
  }

  document.execCommand('defaultParagraphSeparator', false, 'p');
  syncBody();
  refreshSnippet();

  form.addEventListener('input', function () {
    syncBody();
    refreshSnippet();
    markDirty();
  });

  form.addEventListener('change', function () {
    refreshSnippet();
    markDirty();
  });

  writing.addEventListener('keyup', rememberSelection);
  writing.addEventListener('mouseup', rememberSelection);

  form.querySelectorAll('[data-command]').forEach(function (button) {
    button.addEventListener('mousedown', function (event) {
      event.preventDefault();
    });
    button.addEventListener('click', function () {
      var command = button.getAttribute('data-command');
      if (command === 'paragraph') format('formatBlock', 'p');
      if (command === 'h2') format('formatBlock', 'h2');
      if (command === 'h3') format('formatBlock', 'h3');
      if (command === 'bold') format('bold');
      if (command === 'italic') format('italic');
      if (command === 'list') format('insertUnorderedList');
      if (command === 'quote') format('formatBlock', 'blockquote');
      if (command === 'link') addLink();
      if (command === 'image') inlineInput.click();
    });
  });

  inlineInput.addEventListener('change', function () {
    Array.prototype.forEach.call(inlineInput.files, insertImage);
    inlineInput.value = '';
  });

  if (coverInput) {
    coverInput.addEventListener('change', function () {
      var file = coverInput.files[0];
      if (!file) return;
      var problem = acceptedFile(file);
      if (problem !== '') {
        coverInput.value = '';
        showUploadError(problem);
        return;
      }
      showUploadError('');
      if (removeCover) removeCover.checked = false;
      coverImage.src = URL.createObjectURL(file);
      coverImage.hidden = false;
      coverFrame.hidden = false;
      markDirty();
    });
  }

  if (removeCover) {
    removeCover.addEventListener('change', function () {
      var removing = removeCover.checked && (!coverInput || coverInput.files.length === 0);
      coverFrame.hidden = removing;
    });
  }

  form.addEventListener('submit', function () {
    submitting = true;
    syncBody();
    packInlineImages();
    if (saveState) saveState.textContent = 'Saving';
    if (saveButton) saveButton.disabled = true;
  });

  window.addEventListener('beforeunload', function (event) {
    if (!dirty || submitting) return;
    event.preventDefault();
    event.returnValue = '';
  });

  form.querySelector('[data-open-preview]').addEventListener('click', openPreview);
  var preview = previewPanel();
  if (preview) {
    var previewFrame = preview.querySelector('[data-preview-frame]');
    var previewDesktop = preview.querySelector('[data-preview-desktop]');
    var previewMobile = preview.querySelector('[data-preview-mobile]');
    preview.querySelector('[data-preview-close]').addEventListener('click', closePreview);
    previewDesktop.addEventListener('click', function () {
      previewFrame.classList.add('is-desktop');
      previewFrame.classList.remove('is-mobile');
      previewDesktop.setAttribute('aria-pressed', 'true');
      previewMobile.setAttribute('aria-pressed', 'false');
    });
    previewMobile.addEventListener('click', function () {
      previewFrame.classList.remove('is-desktop');
      previewFrame.classList.add('is-mobile');
      previewDesktop.setAttribute('aria-pressed', 'false');
      previewMobile.setAttribute('aria-pressed', 'true');
    });
  }

  var sections = form.querySelectorAll('[data-section]');
  var links = form.querySelectorAll('[data-section-link]');
  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var id = entry.target.getAttribute('data-section');
        links.forEach(function (link) {
          link.classList.toggle('is-current', link.getAttribute('data-section-link') === id);
        });
      });
    }, { rootMargin: '-40% 0px -50% 0px' });
    sections.forEach(function (section) { observer.observe(section); });
  }

  var invalid = form.querySelector('[aria-invalid="true"]');
  if (invalid) {
    if (invalid === writing) writing.focus();
    else invalid.focus();
  }
})();
