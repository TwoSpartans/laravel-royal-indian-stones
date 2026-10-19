(function () {
  var root = document.getElementById('blog-studio');
  var bootNode = document.getElementById('blog-studio-bootstrap');
  if (!root || !bootNode) return;

  var boot = JSON.parse(bootNode.textContent);
  var post = boot.post;
  var state = {
    mode: 'write',
    rail: 'insert',
    tab: 'content',
    document: boot.document,
    selected: 0,
    quality: boot.quality,
    lockVersion: post.lockVersion,
    status: post.status,
    hasDraft: post.hasDraft,
    isLive: post.isLive,
    query: '',
    notice: '',
    preview: '',
    dialog: null,
    focus: false,
    drawer: '',
    products: [],
    saving: false,
    queued: false,
    recovery: null,
  };
  var meta = {
    title: post.title || '',
    slug: post.slug || '',
    excerpt: post.excerpt || '',
    metaDescription: post.metaDescription || '',
    tags: (post.tags || []).join(', '),
    categoryId: post.categoryId || '',
    featuredImageAlt: post.featuredImageAlt || '',
    image: post.image || '',
    imagePath: post.imagePath || '',
  };
  var key = 'blog-studio-' + post.id;
  var timer = null;
  var token = document.querySelector('meta[name="csrf-token"]').content;
  var saveWaiters = [];
  var history = [];
  var future = [];

  function blocks() {
    return state.document.content || [];
  }

  function textOf(node) {
    return ((node && node.content) || []).map(function (part) { return part.text || ''; }).join('');
  }

  function textNode(value, marks) {
    var node = { type: 'text', text: value };
    if (marks && marks.length) node.marks = marks;
    return node;
  }

  function contentToHtml(content) {
    return (content || []).map(function (part) {
      if (part.type === 'hardBreak') return '<br>';
      var html = escapeHtml(part.text || '');
      (part.marks || []).forEach(function (mark) {
        if (mark.type === 'bold') html = '<strong>' + html + '</strong>';
        if (mark.type === 'italic') html = '<em>' + html + '</em>';
        if (mark.type === 'link' && mark.attrs) html = '<a href="' + escapeHtml(mark.attrs.href || '') + '">' + html + '</a>';
      });
      return html;
    }).join('');
  }

  function contentFromHtml(editor) {
    var out = [];
    function walk(node, marks) {
      if (node.nodeType === 3) {
        if (node.textContent) out.push(textNode(node.textContent, marks));
        return;
      }
      if (node.nodeName === 'BR') {
        out.push({ type: 'hardBreak' });
        return;
      }
      var next = marks.slice();
      if (node.nodeName === 'STRONG' || node.nodeName === 'B') next.push({ type: 'bold' });
      if (node.nodeName === 'EM' || node.nodeName === 'I') next.push({ type: 'italic' });
      if (node.nodeName === 'A') next.push({ type: 'link', attrs: { href: node.getAttribute('href') || '' } });
      Array.prototype.forEach.call(node.childNodes, function (child) { walk(child, next); });
    }
    Array.prototype.forEach.call(editor.childNodes, function (child) { walk(child, []); });
    return out;
  }

  function flushEditors() {
    root.querySelectorAll('[data-text]').forEach(function (editor) {
      var section = editor.closest('[data-select]');
      if (!section) return;
      var block = blocks()[Number(section.getAttribute('data-select'))];
      if (!block) return;
      var raw = editor.textContent || '';
      block.content = raw.trim().charAt(0) === '/' ? [] : contentFromHtml(editor);
    });
    root.querySelectorAll('[data-field]').forEach(function (field) {
      meta[field.getAttribute('data-field')] = field.value;
    });
  }

  function growTitle(field) {
    if (!field) return;
    field.style.height = 'auto';
    field.style.height = field.scrollHeight + 'px';
  }

  function blank(type) {
    if (type === 'heading') return { type: 'heading', attrs: { level: 2 }, content: [textNode('Section heading')] };
    if (type === 'image') return { type: 'image', attrs: { mediaId: null, alt: '', decorative: false, caption: '', credit: '' } };
    if (type === 'gallery') return { type: 'gallery', attrs: { mode: 'grid', items: [] } };
    if (type === 'productSpotlight') return { type: 'productSpotlight', attrs: { productId: null, presentation: { layout: 'image-left', ctaLabel: 'View stone' }, editorial: { eyebrow: 'Stone', summary: '' } } };
    if (type === 'workshopStory') return { type: 'workshopStory', attrs: { title: '', processStage: 'selection', body: '', mediaIds: [], caption: '' } };
    if (type === 'cta') return { type: 'cta', attrs: { title: '', body: '', buttonText: '', buttonUrl: '' } };
    return { type: 'paragraph', content: [] };
  }

  function remember() {
    history.push(JSON.stringify({ document: state.document, meta: meta, selected: state.selected }));
    if (history.length > 40) history.shift();
    future = [];
  }

  function restore(raw) {
    var saved = JSON.parse(raw);
    state.document = saved.document;
    meta = saved.meta;
    state.selected = saved.selected;
    render();
    schedule();
  }

  function undo() {
    if (!history.length) return;
    future.push(JSON.stringify({ document: state.document, meta: meta, selected: state.selected }));
    restore(history.pop());
  }

  function redo() {
    if (!future.length) return;
    history.push(JSON.stringify({ document: state.document, meta: meta, selected: state.selected }));
    var next = future.pop();
    var saved = JSON.parse(next);
    state.document = saved.document;
    meta = saved.meta;
    state.selected = saved.selected;
    render();
    schedule();
  }

  function insert(type) {
    remember();
    var next = blank(type);
    var index = state.selected == null ? blocks().length : state.selected + 1;
    blocks().splice(index, 0, next);
    state.selected = index;
    state.tab = 'content';
    render();
    schedule();
  }

  function move(direction) {
    var index = state.selected;
    var target = index + direction;
    if (index == null || target < 0 || target >= blocks().length) return;
    remember();
    var copy = blocks()[index];
    blocks().splice(index, 1);
    blocks().splice(target, 0, copy);
    state.selected = target;
    render();
    schedule();
  }

  function duplicate() {
    if (state.selected == null) return;
    remember();
    var copy = JSON.parse(JSON.stringify(blocks()[state.selected]));
    if (copy.attrs) delete copy.attrs.blockId;
    blocks().splice(state.selected + 1, 0, copy);
    state.selected += 1;
    render();
    schedule();
  }

  function removeBlock() {
    if (state.selected == null) return;
    remember();
    blocks().splice(state.selected, 1);
    if (!blocks().length) blocks().push(blank('paragraph'));
    state.selected = Math.max(0, state.selected - 1);
    render();
    schedule();
  }

  function applyTemplate(template, mode) {
    remember();
    var validated = JSON.parse(JSON.stringify(template.document));
    if (mode === 'append') {
      state.document.content = blocks().concat(validated.content);
    } else {
      state.document = validated;
      state.selected = 0;
    }
    state.dialog = null;
    render();
    schedule();
  }

  function schedule() {
    state.notice = 'Unsaved';
    try {
      localStorage.setItem(key, JSON.stringify({ document: state.document, meta: meta, baseVersion: state.lockVersion }));
    } catch (error) {}
    window.clearTimeout(timer);
    timer = window.setTimeout(save, 1500);
    var status = root.querySelector('[data-status]');
    if (status) status.textContent = 'Unsaved';
  }

  function payload() {
    return {
      baseVersion: state.lockVersion,
      document: state.document,
      metadata: {
        title: meta.title,
        slug: meta.slug,
        excerpt: meta.excerpt,
        metaDescription: meta.metaDescription,
        tags: meta.tags.split(',').map(function (tag) { return tag.trim(); }).filter(Boolean),
        featuredImageAlt: meta.featuredImageAlt,
        categoryId: meta.categoryId || null,
      },
    };
  }

  function save() {
    if (post.contentSource !== 'studio') return Promise.resolve();
    flushEditors();
    if (state.saving) {
      state.queued = true;
      return new Promise(function (resolve) { saveWaiters.push(resolve); });
    }
    state.saving = true;
    paintStatus('Saving');
    return fetch(boot.urls.save, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify(payload()),
    }).then(function (response) {
      return response.json().then(function (body) { return { ok: response.ok, status: response.status, body: body }; });
    }).then(function (result) {
      state.saving = false;
      if (result.status === 409) {
        state.notice = 'This article changed in another tab. Reload before you keep writing.';
        state.serverLock = result.body.lockVersion;
        saveWaiters.splice(0).forEach(function (resolve) { resolve(result); });
        render();
        return;
      }
      if (!result.ok) {
        state.notice = firstError(result.body) || 'The article could not be saved.';
        saveWaiters.splice(0).forEach(function (resolve) { resolve(result); });
        render();
        return;
      }
      keepPreviews(result.body.document);
      state.lockVersion = result.body.lockVersion;
      state.document = result.body.document;
      state.quality = result.body.quality;
      state.hasDraft = result.body.hasDraft;
      state.isLive = result.body.isLive;
      state.status = result.body.status;
      state.notice = 'Saved';
      try { localStorage.removeItem(key); } catch (error) {}
      paintStatus(state.notice);
      paintQuality();
      saveWaiters.splice(0).forEach(function (resolve) { resolve(result); });
      if (state.queued) {
        state.queued = false;
        return save();
      }
      return result;
    }).catch(function () {
      state.saving = false;
      state.notice = 'The article could not be saved.';
      saveWaiters.splice(0).forEach(function (resolve) { resolve(null); });
      render();
    });
  }

  function firstError(body) {
    var errors = body && body.errors ? body.errors : null;
    if (!errors) return body && body.message;
    var keyName = Object.keys(errors)[0];
    var value = errors[keyName];
    return Array.isArray(value) ? value[0] : value;
  }

  function paintStatus(text) {
    var status = root.querySelector('[data-status]');
    if (!status) return;
    status.textContent = text;
    status.className = 'studio-save ' + saveClass();
  }

  function paintQuality() {
    var bar = root.querySelector('.studio-readiness');
    if (!bar) return;
    var sections = ['SEO', 'Accessibility', 'Product references', 'Media', 'Broken links', 'Publish'];
    bar.innerHTML = sections.map(function (section) {
      var items = groupChecks(section);
      var blocked = items.filter(function (item) { return item.state === 'blocked'; }).length;
      return '<button type="button" data-mode="optimize">' + section + ' <small>' + (blocked ? blocked + ' to review' : 'Clear') + '</small></button>';
    }).join('');
  }

  function keepPreviews(next) {
    var previous = {};
    blocks().forEach(function (block) {
      if (block.attrs && block.attrs.blockId) previous[block.attrs.blockId] = block.attrs;
    });
    ((next && next.content) || []).forEach(function (block) {
      var old = block.attrs && previous[block.attrs.blockId];
      if (!old) return;
      ['url', 'productName', 'productImage'].forEach(function (name) {
        if (old[name]) block.attrs[name] = old[name];
      });
      (block.attrs.items || []).forEach(function (item, index) {
        if (old.items && old.items[index] && old.items[index].url) item.url = old.items[index].url;
      });
    });
  }

  function upload(file, role) {
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 6 * 1024 * 1024) {
      state.notice = 'Use a JPG, PNG, or WebP image up to 6 MB.';
      render();
      return;
    }
    var data = new FormData();
    data.append('file', file);
    data.append('role', role === 'featured' ? 'featured' : 'block');
    fetch(boot.urls.media, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: data })
      .then(function (response) { return response.json().then(function (body) { return { ok: response.ok, body: body }; }); })
      .then(function (result) {
        if (!result.ok) {
          state.notice = firstError(result.body) || 'The photograph could not be saved.';
          render();
          return;
        }
        if (role === 'featured') {
          meta.image = result.body.url;
          meta.imagePath = result.body.path;
        } else if (role === 'gallery') {
          var gallery = blocks()[state.selected];
          gallery.attrs.items = gallery.attrs.items || [];
          if (gallery.attrs.items.length >= 12) {
            state.notice = 'A gallery holds 12 photographs.';
            render();
            return;
          }
          gallery.attrs.items.push({ mediaId: result.body.id, alt: '', decorative: false, url: result.body.url });
        } else if (role === 'workshop') {
          blocks()[state.selected].attrs.mediaIds.push(result.body.id);
        } else {
          var image = blocks()[state.selected];
          image.attrs.mediaId = result.body.id;
          image.attrs.url = result.body.url;
        }
        state.dialog = null;
        render();
        schedule();
      });
  }

  function searchProducts(query) {
    fetch(boot.urls.products + '?q=' + encodeURIComponent(query), { headers: { 'Accept': 'application/json' } })
      .then(function (response) { return response.json(); })
      .then(function (body) {
        state.products = body.products || [];
        var list = root.querySelector('[data-product-results]');
        if (!list) return;
        list.innerHTML = state.products.map(function (product) {
          return '<button type="button" data-pick="' + product.id + '">' + escapeHtml(product.name) + (product.category ? ' · ' + escapeHtml(product.category) : '') + '</button>';
        }).join('') || '<p class="studio-hint">No published stone matched.</p>';
      });
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (character) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
    });
  }

  function groupChecks(section) {
    return (state.quality.items || []).filter(function (item) { return item.section === section; });
  }

  function saveClass() {
    var text = (state.notice || '').toLowerCase();
    if (text.indexOf('could not') !== -1 || text.indexOf('another tab') !== -1) return 'is-error';
    if (text.indexOf('unsaved') !== -1 || text.indexOf('saving') !== -1) return 'is-warning';
    return 'is-success';
  }

  function bannerClass() {
    var text = (state.notice || '').toLowerCase();
    if (text.indexOf('could not') !== -1 || text.indexOf('another tab') !== -1 || text.indexOf('resolve') !== -1) return 'is-error';
    if (text.indexOf('unsaved') !== -1) return 'is-warning';
    if (text.indexOf('saved') !== -1 || text.indexOf('published') !== -1 || text.indexOf('scheduled') !== -1) return 'is-success';
    return 'is-info';
  }

  function render() {
    if (post.contentSource !== 'studio') {
      root.innerHTML = '<main class="studio-convert"><p><a class="studio-link" href="' + boot.urls.back + '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to articles</a></p><h1>' + escapeHtml(post.title || 'This article') + '</h1><p>Studio will turn the saved article into blocks. The original text stays stored, so the classic version remains.</p>' + (post.isLive ? '<p>Readers will see the Studio version as soon as conversion finishes.</p>' : '') + (post.hasStudioVersion ? '<label><input type="radio" name="studio-source" value="fresh" checked> Build a new Studio version</label><label><input type="radio" name="studio-source" value="existing"> Go back to the earlier Studio version</label>' : '') + '<label><input type="checkbox" data-confirm-convert> ' + (post.isLive ? 'I understand readers will see the Studio version immediately.' : 'I understand this article will open in Blog Studio.') + '</label><p class="studio-hint" data-convert-error></p><button class="studio-publish" type="button" data-convert disabled>Convert to Blog Studio</button><p><a href="' + boot.urls.classic + '">Keep using the Classic editor</a></p></main>';
      return;
    }
    var selected = blocks()[state.selected] || null;
    var sections = ['SEO', 'Accessibility', 'Product references', 'Media', 'Broken links', 'Publish'];
    root.innerHTML = ''
      + '<div class="studio-shell' + (state.focus ? ' is-focus' : '') + '">'
      + '<header class="studio-top"><div class="studio-identity"><a class="studio-icon" href="' + boot.urls.back + '" aria-label="Back to articles"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a> <strong>Blog Studio</strong></div>'
      + '<div class="studio-modes"><button type="button" data-mode="write"' + pressed(state.mode === 'write') + '>Write</button><button type="button" data-mode="design"' + pressed(state.mode === 'design') + '>Design</button><button type="button" data-mode="optimize"' + pressed(state.mode === 'optimize') + '>Optimize</button></div>'
      + '<div class="studio-actions"><button class="studio-icon" type="button" data-drawer="rail" aria-label="Blocks" aria-expanded="' + (state.drawer === 'rail' ? 'true' : 'false') + '"><i class="fa-solid fa-plus" aria-hidden="true"></i></button><button class="studio-icon" type="button" data-drawer="inspector" aria-label="Settings" aria-expanded="' + (state.drawer === 'inspector' ? 'true' : 'false') + '"><i class="fa-solid fa-sliders" aria-hidden="true"></i></button><button class="studio-icon" type="button" data-undo aria-label="Undo"' + (history.length ? '' : ' disabled') + '><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button><button class="studio-icon" type="button" data-redo aria-label="Redo"' + (future.length ? '' : ' disabled') + '><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>' + statusChip() + '<span class="studio-save ' + saveClass() + '" data-status>' + escapeHtml(state.notice || 'Saved') + '</span><button type="button" data-preview>Preview</button><button type="button" data-schedule>Schedule</button><button type="button" data-save>' + (state.isLive ? 'Save draft' : 'Save') + '</button><button class="studio-publish" type="button" data-publish>' + (state.isLive ? 'Republish' : 'Publish') + '</button><button class="studio-icon" type="button" data-focus aria-label="' + (state.focus ? 'Show panels' : 'Focus on the article') + '"><i class="fa-solid ' + (state.focus ? 'fa-compress' : 'fa-expand') + '" aria-hidden="true"></i></button><details class="studio-more"><summary>More</summary><div class="studio-more-menu"><button type="button" data-save>Save now</button>' + (post.publicUrl ? '<button type="button" data-copy-link>Copy public link</button>' : '') + '<a href="' + boot.urls.classic + '">Open the classic editor</a></div></details></div></header>'
      + '<nav class="studio-path"><button type="button" data-rail="templates"' + on(state.rail === 'templates') + '>Template</button><button type="button" data-mode="write">Build</button><button type="button" data-mode="design">Design</button><button type="button" data-mode="optimize">Optimize</button><button type="button" data-publish>Publish</button></nav>'
      + (state.notice ? '<p class="studio-banner ' + bannerClass() + '">' + escapeHtml(state.notice) + (state.serverLock ? ' <button type="button" data-reload>Load the latest version</button> <button type="button" data-overwrite>Keep my version</button>' : '') + ' <button class="studio-icon" type="button" data-dismiss aria-label="Dismiss"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></p>' : '')
      + (state.recovery ? '<p class="studio-banner is-warning">A copy from this browser was not saved. <button type="button" data-restore>Restore</button> <button type="button" data-discard>Discard</button></p>' : '')
      + (state.hasDraft ? '<p class="studio-banner is-info">Readers still see the published article until you republish.</p>' : '')
      + '<div class="studio-readiness">' + sections.map(function (section) {
        var items = groupChecks(section);
        var blocked = items.filter(function (item) { return item.state === 'blocked'; }).length;
        var attention = items.filter(function (item) { return item.state === 'attention'; }).length;
        var label = items.length === 0 ? 'No checks yet' : (blocked ? blocked + ' to review' : attention ? attention + ' to review' : 'Clear');
        return '<button type="button" data-check-group="' + section + '"' + (items.length === 0 ? ' disabled' : '') + '>' + section + ' <small>' + label + '</small></button>';
      }).join('') + '</div>'
      + '<div class="studio-body' + (state.drawer === 'rail' ? ' is-rail' : '') + (state.drawer === 'inspector' ? ' is-inspector' : '') + '"><button class="studio-backdrop" type="button" data-drawer="" aria-label="Close panel"></button><aside class="studio-rail">' + rail(selected) + '</aside><main class="studio-canvas"><article class="studio-article rich-content">' + canvas(selected) + '</article></main><aside class="studio-inspector">' + inspector(selected) + '</aside></div></div>'
      + overlay();
    growTitle(root.querySelector('.studio-title'));
  }

  function insertButtons() {
    return boot.blocks.filter(function (block) {
      return (block.name + ' ' + block.description).toLowerCase().includes(state.query.toLowerCase());
    }).map(function (block) {
      return '<button class="studio-block-button" type="button" data-insert="' + block.type + '"><i class="' + block.icon + '" aria-hidden="true"></i><span><strong>' + block.name + '</strong><small>' + block.description + '</small></span></button>';
    }).join('');
  }

  function on(active) {
    return active ? ' class="is-on"' : '';
  }

  function pressed(active) {
    return (active ? ' class="is-on"' : '') + ' aria-pressed="' + (active ? 'true' : 'false') + '"';
  }

  function statusChip() {
    if (state.isLive && post.publicUrl) return '<a class="studio-status" href="' + post.publicUrl + '" target="_blank" rel="noopener">Live</a>';
    if (state.status === 'scheduled') return '<span class="studio-status">Scheduled</span><button type="button" data-publish>Change</button><button type="button" data-cancel-schedule>Cancel</button>';
    return '<span class="studio-status">' + escapeHtml(state.status || 'Draft') + '</span>';
  }

  function rail() {
    var tabs = '<div class="studio-tabs"><button type="button" data-rail="insert"' + on(state.rail === 'insert') + '>Insert</button><button type="button" data-rail="layers"' + on(state.rail === 'layers') + '>Layers</button><button type="button" data-rail="templates"' + on(state.rail === 'templates') + '>Templates</button></div>';
    if (state.rail === 'layers') {
      return tabs + blocks().map(function (block, index) {
        var name = (boot.blocks.find(function (item) { return item.type === block.type; }) || {}).name || block.type;
        return '<div class="studio-layer-row' + (index === state.selected ? ' is-current' : '') + '"><button class="studio-layer" type="button" data-select="' + index + '">' + (index + 1) + '. ' + name + '</button><button type="button" data-layer-move="-1" data-index="' + index + '" aria-label="Move up"' + (index === 0 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button><button type="button" data-layer-move="1" data-index="' + index + '" aria-label="Move down"' + (index === blocks().length - 1 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button><button type="button" data-layer-copy data-index="' + index + '" aria-label="Duplicate"><i class="fa-solid fa-clone" aria-hidden="true"></i></button><button type="button" data-layer-delete data-index="' + index + '" aria-label="Delete"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>';
      }).join('');
    }
    if (state.rail === 'templates') {
      return tabs + boot.templates.map(function (template) {
        var sections = templateSections(template);
        return '<button class="studio-block-button" type="button" data-template="' + template.id + '"><i class="fa-solid fa-file-lines" aria-hidden="true"></i><span><strong>' + escapeHtml(template.name) + '</strong><small>' + escapeHtml(template.description) + '</small>' + (sections ? '<small class="studio-template-sections">' + escapeHtml(sections) + '</small>' : '') + '</span></button>';
      }).join('');
    }
    var buttons = insertButtons();
    return tabs + '<input data-search placeholder="Search blocks" value="' + escapeHtml(state.query) + '"><p class="studio-hint">' + (state.selected == null ? 'Enter adds the first match at the end.' : 'Enter adds the first match after the selected block.') + '</p><div data-insert-list>' + buttons + '</div>';
  }

  function canvas(selected) {
    var hero = meta.image
      ? '<figure class="studio-hero"><img src="' + escapeHtml(meta.image) + '" alt="' + escapeHtml(meta.featuredImageAlt) + '"></figure>'
      : '<button class="studio-hero-empty" type="button" data-focus-hero><i class="fa-regular fa-image" aria-hidden="true"></i> Add the featured photograph</button>';
    var title = '<textarea class="studio-title" data-field="title" rows="1" maxlength="255" aria-label="Title">' + escapeHtml(meta.title) + '</textarea>';
    var excerptOutside = meta.excerpt.length < boot.excerpt.min || meta.excerpt.length > boot.excerpt.max;
    var excerpt = '<label class="studio-excerpt-label">Excerpt<textarea class="studio-excerpt" data-field="excerpt" maxlength="200" aria-label="Excerpt"' + (excerptOutside ? ' aria-invalid="true"' : '') + '>' + escapeHtml(meta.excerpt) + '</textarea><span class="studio-hint' + (excerptOutside ? ' is-error' : '') + '">' + (excerptOutside ? 'Use between ' + boot.excerpt.min + ' and ' + boot.excerpt.max + ' characters. ' : '') + meta.excerpt.length + ' / ' + boot.excerpt.max + '</span></label>';
    var body = blocks().map(function (block, index) {
      return '<section class="studio-block' + (index === state.selected ? ' is-selected' : '') + '" data-select="' + index + '">' + blockView(block) + '</section>';
    }).join('');
    var empty = blocks().length === 1 && blocks()[0].type === 'paragraph' && textOf(blocks()[0]) === '';
    var offers = empty ? '<div>' + boot.blocks.filter(function (block) { return block.type !== 'paragraph' && block.type !== 'heading'; }).map(function (block) {
      return '<button type="button" data-insert="' + block.type + '">' + block.name + '</button>';
    }).join('') + '</div>' : '';
    var blocked = (state.quality.items || []).filter(function (item) { return item.state === 'blocked'; }).length;
    var attention = (state.quality.items || []).filter(function (item) { return item.state === 'attention'; }).length;
    var dock = '<button class="studio-dock" type="button" data-mode="optimize">' + (blocked ? blocked + ' checks block publishing' : attention ? attention + ' to review' : 'All checks pass') + '</button>';
    var atStart = state.selected === 0;
    var atEnd = state.selected == null || state.selected >= blocks().length - 1;
    return hero + title + excerpt + dock + '<div class="studio-toolbar" role="toolbar" aria-label="Formatting"><button type="button" data-undo aria-label="Undo"' + (history.length ? '' : ' disabled') + '><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button><button type="button" data-redo aria-label="Redo"' + (future.length ? '' : ' disabled') + '><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button><button type="button" data-format="bold" aria-label="Bold"><i class="fa-solid fa-bold" aria-hidden="true"></i></button><button type="button" data-format="italic" aria-label="Italic"><i class="fa-solid fa-italic" aria-hidden="true"></i></button><button type="button" data-format="link" aria-label="Link"><i class="fa-solid fa-link" aria-hidden="true"></i></button><button type="button" data-format="p">Text</button><button type="button" data-format="h2">H2</button><button type="button" data-format="h3">H3</button><button type="button" data-open-insert>Insert</button><button type="button" data-move="-1" aria-label="Move block up"' + (atStart ? ' disabled' : '') + '><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button><button type="button" data-move="1" aria-label="Move block down"' + (atEnd ? ' disabled' : '') + '><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button><button type="button" data-duplicate aria-label="Duplicate block"><i class="fa-solid fa-clone" aria-hidden="true"></i></button><button class="is-danger" type="button" data-delete aria-label="Delete block"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>' + body + offers;
  }

  function blockView(block) {
    if (block.type === 'paragraph' || block.type === 'heading') {
      return '<div class="studio-text" contenteditable="true" data-text data-placeholder="Write, or type / to insert a block.">' + contentToHtml(block.content) + '</div>';
    }
    if (block.type === 'image') {
      return '<p class="studio-atom-label">Image</p>' + (block.attrs.url || block.attrs.mediaId ? '<figure class="studio-atom-image"><img alt="' + escapeHtml(block.attrs.alt || '') + '" src="' + escapeHtml(block.attrs.url || '') + '"><figcaption class="' + (block.attrs.alt ? '' : 'is-empty') + '">' + escapeHtml(block.attrs.caption || block.attrs.alt || 'Add alt text in the inspector') + '</figcaption></figure><button class="studio-upload" type="button" data-upload="block"><i class="fa-solid fa-upload" aria-hidden="true"></i> Replace photograph</button>' : '<button class="studio-upload is-primary" type="button" data-upload="block"><i class="fa-solid fa-upload" aria-hidden="true"></i> Add a photograph</button>');
    }
    if (block.type === 'gallery') {
      var photos = (block.attrs.items || []).map(function (item) { return item.url ? '<img src="' + escapeHtml(item.url) + '" alt="">' : '<span>Photo</span>'; }).join('');
      var addPhoto = '<button class="studio-upload' + (photos ? '' : ' is-primary') + '" type="button" data-upload="gallery"><i class="fa-solid fa-upload" aria-hidden="true"></i> Add photograph</button>';
      return '<p class="studio-atom-label">Gallery</p><div class="studio-atom-gallery' + (block.attrs.mode === 'carousel' ? ' is-carousel' : '') + (photos ? '' : ' is-empty-well') + '">' + (photos || addPhoto) + '</div>' + (photos ? addPhoto : '');
    }
    if (block.type === 'productSpotlight') return '<p class="studio-atom-label">Product Spotlight</p><aside class="studio-atom-product is-' + escapeHtml((block.attrs.presentation && block.attrs.presentation.layout) || 'image-left') + '"><div class="studio-atom-photo">' + (block.attrs.productImage ? '<img src="' + escapeHtml(block.attrs.productImage) + '" alt="">' : '') + '</div><div><p class="studio-eyebrow">' + escapeHtml((block.attrs.editorial && block.attrs.editorial.eyebrow) || 'Stone') + '</p><strong>' + escapeHtml(block.attrs.productName || 'Choose a stone') + '</strong><p>' + escapeHtml((block.attrs.editorial && block.attrs.editorial.summary) || 'Add a short summary') + '</p></div></aside>';
    if (block.type === 'workshopStory') return '<p class="studio-atom-label">Quarry story</p><section class="studio-atom-workshop"><p class="studio-eyebrow">' + escapeHtml(block.attrs.processStage || 'selection') + '</p><strong>' + escapeHtml(block.attrs.title || 'Add a title') + '</strong><p>' + escapeHtml(block.attrs.body || 'Add the story') + '</p></section><button class="studio-upload" type="button" data-upload="workshop"><i class="fa-solid fa-upload" aria-hidden="true"></i> Add process photograph</button>';
    return '<p class="studio-atom-label">Call to action</p><aside class="studio-atom-cta"><strong>' + escapeHtml(block.attrs.title || 'Add a heading') + '</strong><p>' + escapeHtml(block.attrs.body || 'Add a supporting line') + '</p><span>' + escapeHtml(block.attrs.buttonText || 'Button') + '</span></aside>';
  }

  function inspector(selected) {
    var tabs = '<div class="studio-tabs"><button type="button" data-tab="content"' + on(state.tab === 'content') + '>Content</button><button type="button" data-tab="style"' + on(state.tab === 'style') + '>Style</button><button type="button" data-tab="advanced"' + on(state.tab === 'advanced') + '>Advanced</button><button type="button" data-tab="checks"' + on(state.tab === 'checks') + '>Checks</button></div>';
    if (state.tab === 'checks' || state.mode === 'optimize') {
      return tabs + (state.quality.items || []).map(function (item) {
        return '<button class="studio-check is-' + item.state + '" type="button" data-jump="' + (item.blockIndex == null ? '' : item.blockIndex) + '" data-target="' + escapeHtml(item.target || '') + '"><strong>' + item.title + '</strong><small>' + item.explanation + '</small></button>';
      }).join('');
    }
    if (state.tab === 'style' || state.mode === 'design') {
      var frame = (selected && selected.attrs && selected.attrs.frame) || {};
      return tabs + Object.keys(boot.frames).filter(function (name) { return name !== 'visibility'; }).map(function (name) {
        return '<label>' + name + '<select data-frame="' + name + '">' + boot.frames[name].map(function (option) {
          return '<option' + (frame[name] === option ? ' selected' : '') + '>' + option + '</option>';
        }).join('') + '</select></label>';
      }).join('') + (selected && selected.type === 'productSpotlight' ? '<label>Layout<select data-layout><option>image-left</option><option>image-right</option><option>stacked</option></select></label>' : '');
    }
    if (state.tab === 'advanced') {
      var visibility = (selected && selected.attrs && selected.attrs.frame && selected.attrs.frame.visibility) || 'all';
      return tabs + '<label>Visible on<select data-frame="visibility">' + boot.frames.visibility.map(function (option) {
        return '<option' + (visibility === option ? ' selected' : '') + '>' + option + '</option>';
      }).join('') + '</select></label>';
    }
    return tabs + '<label>URL slug<input data-field="slug" value="' + escapeHtml(meta.slug) + '"></label>'
      + '<label>SEO title<input data-field="title" value="' + escapeHtml(meta.title) + '"></label>'
      + '<p class="studio-hint">' + meta.title.length + ' / 60</p>'
      + '<label>Meta description<textarea data-field="metaDescription">' + escapeHtml(meta.metaDescription) + '</textarea></label>'
      + '<label>Tags<input data-field="tags" value="' + escapeHtml(meta.tags) + '" placeholder="Separate tags with commas"></label>'
      + '<label>Category<select data-field="categoryId"><option value="">None</option>' + boot.categories.map(function (category) {
        return '<option value="' + category.id + '"' + (String(meta.categoryId) === String(category.id) ? ' selected' : '') + '>' + escapeHtml(category.name) + '</option>';
      }).join('') + '</select></label>'
      + '<label>Featured image alt<input data-field="featuredImageAlt" maxlength="140" value="' + escapeHtml(meta.featuredImageAlt) + '"></label><button type="button" data-upload="featured"><i class="fa-solid fa-upload" aria-hidden="true"></i> ' + (meta.image ? 'Replace image' : 'Upload image') + '</button>' + (meta.image ? '<button type="button" data-remove-hero>Remove</button>' : '')
      + blockFields(selected);
  }

  function blockFields(selected) {
    if (!selected) return '';
    if (selected.type === 'image') return '<label>Alt text<input data-attr="alt" value="' + escapeHtml(selected.attrs.alt || '') + '"></label><label><input type="checkbox" data-attr="decorative"' + (selected.attrs.decorative ? ' checked' : '') + '> Decorative</label><label>Caption<input data-attr="caption" value="' + escapeHtml(selected.attrs.caption || '') + '"></label>';
    if (selected.type === 'productSpotlight') return '<button type="button" data-products>Choose a stone</button><label>Eyebrow<input data-editorial="eyebrow" value="' + escapeHtml(selected.attrs.editorial.eyebrow || '') + '"></label><label>Summary<textarea data-editorial="summary">' + escapeHtml(selected.attrs.editorial.summary || '') + '</textarea></label>';
    if (selected.type === 'workshopStory') return '<label>Title<input data-attr="title" value="' + escapeHtml(selected.attrs.title || '') + '"></label><label>Stage<select data-attr="processStage">' + boot.stages.map(function (stage) { return '<option' + (selected.attrs.processStage === stage ? ' selected' : '') + '>' + stage + '</option>'; }).join('') + '</select></label><label>Story<textarea data-attr="body">' + escapeHtml(selected.attrs.body || '') + '</textarea></label><button type="button" data-upload="workshop">Add process photograph</button>';
    if (selected.type === 'cta') return '<label>Heading<input data-attr="title" value="' + escapeHtml(selected.attrs.title || '') + '"></label><label>Support<textarea data-attr="body">' + escapeHtml(selected.attrs.body || '') + '</textarea></label><label>Button<input data-attr="buttonText" value="' + escapeHtml(selected.attrs.buttonText || '') + '"></label><label>Link<input data-attr="buttonUrl" value="' + escapeHtml(selected.attrs.buttonUrl || '') + '"></label>';
    if (selected.type === 'gallery') return '<label>Mode<select data-attr="mode"><option' + (selected.attrs.mode === 'grid' ? ' selected' : '') + '>grid</option><option' + (selected.attrs.mode === 'carousel' ? ' selected' : '') + '>carousel</option></select></label>' + (selected.attrs.items || []).map(function (item, index) { return '<label>Alt text ' + (index + 1) + '<input data-gallery-alt="' + index + '" value="' + escapeHtml(item.alt || '') + '"></label>'; }).join('');
    if (selected.type === 'heading') return '<label>Level<select data-level><option value="2"' + ((selected.attrs.level || 2) === 2 ? ' selected' : '') + '>H2</option><option value="3"' + (selected.attrs.level === 3 ? ' selected' : '') + '>H3</option></select></label>';
    return '';
  }

  function blockTitle(block) {
    if (block.content && block.content[0] && block.content[0].text) return block.content[0].text;
    if (block.attrs && block.attrs.title) return block.attrs.title;
    return '';
  }

  function templateSections(template) {
    return ((template.document || {}).content || []).filter(function (block) {
      return block.type === 'heading';
    }).map(blockTitle).filter(Boolean).slice(0, 3).join(' · ');
  }

  function templateOutline(template) {
    return ((template.document || {}).content || []).map(function (block) {
      var match = boot.blocks.find(function (item) { return item.type === block.type; });
      var label = match ? match.name : block.type;
      var title = blockTitle(block);
      if (block.type === 'paragraph') return '';
      if (block.type === 'heading' && title) return '<li><strong>' + escapeHtml(title) + '</strong></li>';
      if (title) return '<li><strong>' + escapeHtml(label) + '</strong><small>' + escapeHtml(title) + '</small></li>';
      return '<li>' + escapeHtml(label) + '</li>';
    }).join('');
  }

  function modal(title, body, actions) {
    return '<div class="studio-dialog" role="dialog" aria-modal="true" aria-label="' + escapeHtml(title) + '"><form class="studio-modal"><div class="studio-modal-head"><h2>' + escapeHtml(title) + '</h2><button class="studio-icon" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>' + body + (actions ? '<div class="studio-modal-actions">' + actions + '</div>' : '') + '</form></div>';
  }

  function overlay() {
    if (state.preview) return '<div class="studio-overlay" role="dialog" aria-modal="true" aria-label="Preview"><div class="studio-card studio-preview-card"><div class="studio-modal-head"><h2>Preview</h2><button class="studio-icon" type="button" data-close aria-label="Close preview"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div><div class="studio-preview-body">' + state.preview + '</div></div></div>';
    if (state.dialog === 'products') return modal('Choose a stone', '<input data-product-search placeholder="Search the catalogue"><div class="studio-modal-list" data-product-results></div>', '');
    if (state.dialog === 'publish') {
      var blocked = (state.quality.items || []).filter(function (item) { return item.state === 'blocked'; });
      var summary = '<p>' + escapeHtml(meta.slug ? '/blogs/' + meta.slug : '') + '</p><p>' + blocked.length + ' blocking checks. ' + blocks().filter(function (block) { return block.type === 'productSpotlight' && block.attrs.productId; }).length + ' stones linked.</p><div class="studio-modal-list">' + blocked.map(function (item) { return '<button type="button" data-jump="' + (item.blockIndex == null ? '' : item.blockIndex) + '" data-target="' + escapeHtml(item.target || '') + '">' + escapeHtml(item.title) + '</button>'; }).join('') + '</div>';
      return modal(state.isLive ? 'Republish' : 'Publish', summary, '<button class="studio-publish" type="button" data-confirm-publish ' + (blocked.length ? 'disabled' : '') + '>' + (state.isLive ? 'Republish' : 'Publish now') + '</button>');
    }
    if (state.dialog === 'schedule') return modal('Schedule', '<label>Publish at<input type="datetime-local" data-when></label>', '<button class="studio-publish" type="button" data-confirm-schedule>Schedule</button>');
    if (state.dialog === 'template') {
      return modal(state.pendingTemplate.name, '<p>' + escapeHtml(state.pendingTemplate.description) + '</p><ol class="studio-template-outline">' + templateOutline(state.pendingTemplate) + '</ol>', '<button type="button" data-template-apply="append">Append</button><button class="studio-publish" type="button" data-template-apply="replace">Replace article</button>');
    }
    if (state.linkForm) return modal('Link', '<input data-link-href value="' + escapeHtml(state.linkForm.href) + '"><p class="studio-hint">' + escapeHtml(state.linkForm.error || 'Use a site path or an https:// address.') + '</p>', '<button class="studio-publish" type="button" data-apply-link>Apply</button><button type="button" data-remove-link>Remove link</button>');
    return '';
  }

  root.addEventListener('mousedown', function (event) {
    if (event.target.closest('.studio-toolbar button')) event.preventDefault();
  });

  root.addEventListener('keydown', function (event) {
    if (event.target.classList && event.target.classList.contains('studio-title') && event.key === 'Enter') {
      event.preventDefault();
      var excerpt = root.querySelector('.studio-excerpt');
      if (excerpt) excerpt.focus();
    }
    if (event.target.hasAttribute && event.target.hasAttribute('data-search') && event.key === 'Enter') {
      event.preventDefault();
      var first = root.querySelector('[data-insert-list] [data-insert]');
      if (first) insert(first.getAttribute('data-insert'));
    }
  });

  root.addEventListener('click', function (event) {
    var target = event.target.closest('button');
    if (!target) {
      var section = event.target.closest('[data-select]');
      if (section) {
        state.selected = Number(section.getAttribute('data-select'));
        if (event.target.closest('[data-text]')) {
          root.querySelectorAll('.studio-block').forEach(function (block, index) {
            block.classList.toggle('is-selected', index === state.selected);
          });
          return;
        }
        render();
      }
      return;
    }
    if (target.hasAttribute('data-mode')) {
      state.mode = target.getAttribute('data-mode');
      state.tab = state.mode === 'design' ? 'style' : (state.mode === 'optimize' ? 'checks' : 'content');
      if (state.mode === 'write') state.rail = 'insert';
      if (state.mode === 'optimize') state.rail = 'layers';
      state.drawer = target.classList.contains('studio-dock') && window.matchMedia('(max-width: 1024px)').matches ? 'inspector' : '';
      render();
    }
    if (target.hasAttribute('data-drawer')) {
      var drawer = target.getAttribute('data-drawer');
      state.drawer = state.drawer === drawer ? '' : drawer;
      render();
    }
    if (target.hasAttribute('data-focus')) {
      state.focus = !state.focus;
      state.drawer = '';
      render();
    }
    if (target.hasAttribute('data-undo')) undo();
    if (target.hasAttribute('data-redo')) redo();
    if (target.hasAttribute('data-reload')) window.location.reload();
    if (target.hasAttribute('data-overwrite') && state.serverLock) {
      state.lockVersion = state.serverLock;
      state.serverLock = null;
      state.notice = '';
      save();
    }
    if (target.hasAttribute('data-copy-link') && post.publicUrl) {
      navigator.clipboard.writeText(post.publicUrl);
      state.notice = 'Public link copied.';
      render();
    }
    if (target.hasAttribute('data-cancel-schedule')) {
      if (!window.confirm('Cancel this schedule and return the article to draft?')) return;
      fetch(boot.urls.cancelSchedule, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token } })
        .then(function (response) { return response.json(); })
        .then(function (body) { state.status = body.status; state.notice = 'Schedule cancelled.'; render(); });
    }
    if (target.hasAttribute('data-check-group')) {
      var group = target.getAttribute('data-check-group');
      var items = groupChecks(group);
      var worst = items.filter(function (item) { return item.state === 'blocked'; })[0] || items.filter(function (item) { return item.state === 'attention'; })[0] || items[0];
      state.mode = 'optimize';
      state.tab = 'checks';
      state.rail = 'layers';
      if (worst && worst.blockIndex != null) state.selected = worst.blockIndex;
      render();
    }
    if (target.hasAttribute('data-layer-move') || target.hasAttribute('data-layer-copy') || target.hasAttribute('data-layer-delete')) {
      state.selected = Number(target.getAttribute('data-index'));
      if (target.hasAttribute('data-layer-move')) move(Number(target.getAttribute('data-layer-move')));
      if (target.hasAttribute('data-layer-copy')) duplicate();
      if (target.hasAttribute('data-layer-delete')) removeBlock();
    }
    if (target.hasAttribute('data-focus-hero')) {
      state.mode = 'write';
      state.tab = 'content';
      render();
      var alt = root.querySelector('[data-field="featuredImageAlt"]');
      if (alt) alt.focus();
    }
    if (target.hasAttribute('data-remove-hero')) {
      meta.image = '';
      meta.imagePath = '';
      meta.featuredImageAlt = '';
      render();
      schedule();
    }
    if (target.hasAttribute('data-template-apply')) {
      applyTemplate(state.pendingTemplate, target.getAttribute('data-template-apply'));
      state.pendingTemplate = null;
    }
    if (target.hasAttribute('data-dismiss')) {
      state.notice = '';
      render();
    }
    if (target.hasAttribute('data-rail')) { state.rail = target.getAttribute('data-rail'); render(); }
    if (target.hasAttribute('data-tab')) { state.tab = target.getAttribute('data-tab'); render(); }
    if (target.hasAttribute('data-insert')) {
      if (window.matchMedia('(max-width: 1024px)').matches) state.drawer = '';
      insert(target.getAttribute('data-insert'));
    }
    if (target.hasAttribute('data-open-insert')) {
      state.mode = 'write';
      state.rail = 'insert';
      state.drawer = window.matchMedia('(max-width: 1024px)').matches ? 'rail' : '';
      render();
      var search = root.querySelector('[data-search]');
      if (search) search.focus();
    }
    if (target.hasAttribute('data-select')) {
      var nextIndex = Number(target.getAttribute('data-select'));
      if (target.hasAttribute('data-text') || target.closest('[data-text]')) {
        state.selected = nextIndex;
        return;
      }
      state.selected = nextIndex;
      render();
    }
    if (target.hasAttribute('data-move')) move(Number(target.getAttribute('data-move')));
    if (target.hasAttribute('data-duplicate')) duplicate();
    if (target.hasAttribute('data-delete')) removeBlock();
    if (target.hasAttribute('data-template')) {
      var template = boot.templates.find(function (item) { return item.id === target.getAttribute('data-template'); });
      var started = blocks().some(function (block) { return textOf(block) !== '' || block.type !== 'paragraph'; });
      if (!started) applyTemplate(template, 'replace');
      else { state.pendingTemplate = template; state.dialog = 'template'; render(); }
    }
    if (target.hasAttribute('data-upload')) {
      var input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/jpeg,image/png,image/webp';
      input.onchange = function () { upload(input.files[0], target.getAttribute('data-upload')); };
      input.click();
    }
    if (target.hasAttribute('data-products')) { state.dialog = 'products'; render(); searchProducts(''); }
    if (target.hasAttribute('data-pick')) {
      var product = state.products.find(function (item) { return String(item.id) === target.getAttribute('data-pick'); });
      blocks()[state.selected].attrs.productId = product.id;
      blocks()[state.selected].attrs.productName = product.name;
      blocks()[state.selected].attrs.productImage = product.image || '';
      state.dialog = null;
      render();
      schedule();
    }
    if (target.hasAttribute('data-save')) save();
    if (target.hasAttribute('data-preview')) {
      flushEditors();
      var pending = state.saving ? new Promise(function (resolve) { saveWaiters.push(resolve); }) : Promise.resolve();
      pending.then(function () {
        return fetch(boot.urls.preview, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify(payload()) });
      }).then(function (response) { return response.json().then(function (body) { return { ok: response.ok, body: body }; }); }).then(function (result) {
        if (!result.ok) {
          state.notice = firstError(result.body) || 'Preview could not be opened.';
          render();
          return;
        }
        state.preview = result.body.html || '';
        if (result.body.saved) {
          keepPreviews(result.body.saved.document);
          state.lockVersion = result.body.saved.lockVersion;
          state.document = result.body.saved.document;
          state.quality = result.body.saved.quality;
          state.notice = 'Saved';
        }
        render();
      });
    }
    if (target.hasAttribute('data-publish')) { state.dialog = 'publish'; render(); }
    if (target.hasAttribute('data-schedule')) { state.dialog = 'schedule'; render(); }
    if (target.hasAttribute('data-confirm-publish')) {
      flushEditors();
      save().then(function () {
        return fetch(boot.urls.publish, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token } });
      })
        .then(function (response) { return response.json().then(function (body) { return { ok: response.ok, body: body }; }); })
        .then(function (result) {
          if (!result.ok) { state.notice = firstError(result.body); state.dialog = null; render(); return; }
          state.isLive = true;
          state.hasDraft = false;
          state.status = 'published';
          state.dialog = null;
          state.notice = 'Published';
          render();
        });
    }
    if (target.hasAttribute('data-confirm-schedule')) {
      var when = root.querySelector('[data-when]').value;
      fetch(boot.urls.schedule, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ scheduledFor: when }) })
        .then(function (response) { return response.json().then(function (body) { return { ok: response.ok, body: body }; }); })
        .then(function (result) {
          state.dialog = null;
          state.notice = result.ok ? 'Scheduled' : (firstError(result.body) || 'Choose a future time.');
          if (result.ok) state.status = result.body.status;
          render();
        });
    }
    if (target.hasAttribute('data-convert')) {
      var source = root.querySelector('input[name="studio-source"]:checked');
      target.disabled = true;
      fetch(boot.urls.convert, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ useExisting: !!(source && source.value === 'existing') }) })
        .then(function (response) { return response.json().then(function (body) { return { ok: response.ok, body: body }; }); })
        .then(function (result) {
          if (!result.ok) {
            var line = root.querySelector('[data-convert-error]');
            if (line) line.textContent = firstError(result.body) || 'The article could not be converted.';
            target.disabled = false;
            return;
          }
          window.location.href = (result.body && result.body.url) || window.location.href;
        });
    }
    if (target.hasAttribute('data-jump')) {
      var jump = target.getAttribute('data-jump');
      var focusField = target.getAttribute('data-target');
      if (jump !== '') state.selected = Number(jump);
      state.dialog = null;
      state.mode = 'write';
      state.tab = 'content';
      render();
      var focus = focusField === 'title' ? root.querySelector('.studio-title') : (focusField === 'excerpt' ? root.querySelector('.studio-excerpt') : (focusField ? root.querySelector('[data-field="' + focusField + '"]') : null));
      if (focus) focus.focus();
    }
    if (target.hasAttribute('data-apply-link')) {
      var value = (root.querySelector('[data-link-href]') || {}).value || '';
      value = value.trim();
      var safe = value === '' || value.charAt(0) === '/' || value.indexOf('https://') === 0 || value.indexOf('http://') === 0;
      if (!safe || value.indexOf('//') === 0) {
        state.linkForm.error = 'Use a site path such as /products, or a full http:// or https:// address.';
        render();
        return;
      }
      var editor = root.querySelector('.studio-block.is-selected [data-text]');
      if (editor) {
        editor.focus();
        if (value === '') document.execCommand('unlink');
        else document.execCommand('createLink', false, value);
        blocks()[state.selected].content = contentFromHtml(editor);
      }
      state.linkForm = null;
      render();
      schedule();
    }
    if (target.hasAttribute('data-remove-link')) {
      var selectedEditor = root.querySelector('.studio-block.is-selected [data-text]');
      if (selectedEditor) {
        selectedEditor.focus();
        document.execCommand('unlink');
        blocks()[state.selected].content = contentFromHtml(selectedEditor);
      }
      state.linkForm = null;
      render();
      schedule();
    }
    if (target.hasAttribute('data-close')) { state.dialog = null; state.preview = ''; state.linkForm = null; state.pendingTemplate = null; render(); }
    if (target.hasAttribute('data-restore') && state.recovery) {
      state.document = state.recovery.document;
      meta = state.recovery.meta;
      state.recovery = null;
      render();
      schedule();
    }
    if (target.hasAttribute('data-discard')) {
      state.recovery = null;
      try { localStorage.removeItem(key); } catch (error) {}
      render();
    }
    if (target.hasAttribute('data-format')) format(target.getAttribute('data-format'));
  });

  root.addEventListener('input', function (event) {
    var field = event.target.getAttribute('data-field');
    if (field) {
      meta[field] = event.target.value;
      if (field === 'title') {
        event.target.value = event.target.value.replace(/[\r\n]+/g, ' ');
        meta.title = event.target.value;
        root.querySelectorAll('[data-field="title"]').forEach(function (input) {
          if (input !== event.target) input.value = event.target.value;
        });
        growTitle(root.querySelector('.studio-title'));
      }
      if (field === 'excerpt') {
        var counter = root.querySelector('.studio-excerpt + .studio-hint');
        if (counter) {
          var outside = event.target.value.length < boot.excerpt.min || event.target.value.length > boot.excerpt.max;
          counter.textContent = event.target.value.length + ' / ' + boot.excerpt.max;
          counter.classList.toggle('is-error', outside);
        }
      }
      schedule();
      return;
    }
    if (event.target.hasAttribute('data-search')) {
      state.query = event.target.value;
      var list = root.querySelector('[data-insert-list]');
      if (list) list.innerHTML = insertButtons();
      return;
    }
    if (event.target.hasAttribute('data-text')) {
      var section = event.target.closest('[data-select]');
      var block = section ? blocks()[Number(section.getAttribute('data-select'))] : blocks()[state.selected];
      if (!block) return;
      state.selected = section ? Number(section.getAttribute('data-select')) : state.selected;
      var raw = event.target.textContent || '';
      if (raw.trim().charAt(0) === '/') showSlash(raw.trim().slice(1), event.target);
      else hideSlash();
      block.content = raw.trim().charAt(0) === '/' ? [] : contentFromHtml(event.target);
      schedule();
      return;
    }
    if (event.target.hasAttribute('data-attr') && blocks()[state.selected]) {
      var name = event.target.getAttribute('data-attr');
      blocks()[state.selected].attrs[name] = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
      schedule();
    }
    if (event.target.hasAttribute('data-editorial') && blocks()[state.selected]) {
      blocks()[state.selected].attrs.editorial[event.target.getAttribute('data-editorial')] = event.target.value;
      schedule();
    }
    if (event.target.hasAttribute('data-gallery-alt') && blocks()[state.selected]) {
      blocks()[state.selected].attrs.items[Number(event.target.getAttribute('data-gallery-alt'))].alt = event.target.value;
      schedule();
    }
    if (event.target.hasAttribute('data-frame') && blocks()[state.selected]) {
      blocks()[state.selected].attrs.frame = blocks()[state.selected].attrs.frame || {};
      blocks()[state.selected].attrs.frame[event.target.getAttribute('data-frame')] = event.target.value;
      schedule();
    }
    if (event.target.hasAttribute('data-level') && blocks()[state.selected]) {
      blocks()[state.selected].attrs.level = Number(event.target.value);
      schedule();
    }
    if (event.target.hasAttribute('data-layout') && blocks()[state.selected]) {
      blocks()[state.selected].attrs.presentation.layout = event.target.value;
      schedule();
    }
    if (event.target.hasAttribute('data-product-search')) searchProducts(event.target.value);
    if (event.target.hasAttribute('data-confirm-convert')) {
      var convertButton = root.querySelector('[data-convert]');
      if (convertButton) convertButton.disabled = !event.target.checked;
    }
  });

  function format(kind) {
    var block = blocks()[state.selected];
    var editor = root.querySelector('.studio-block.is-selected [data-text]');
    if (!block) return;
    if ((kind === 'bold' || kind === 'italic' || kind === 'link') && editor) {
      editor.focus();
      if (kind === 'link') {
        var current = '';
        var anchor = window.getSelection() && window.getSelection().anchorNode && window.getSelection().anchorNode.parentElement;
        if (anchor && anchor.closest) current = (anchor.closest('a') || {}).href || '';
        state.linkForm = { href: current, error: '' };
        render();
        return;
      } else {
        document.execCommand(kind);
      }
      block.content = contentFromHtml(editor);
      schedule();
      return;
    }
    if (kind === 'h2' || kind === 'h3') {
      block.type = 'heading';
      block.attrs = Object.assign({}, block.attrs, { level: kind === 'h3' ? 3 : 2 });
    }
    if (kind === 'p') block.type = 'paragraph';
    render();
    schedule();
  }

  function hideSlash() {
    var menu = document.querySelector('.studio-slash');
    if (menu) menu.remove();
  }

  function showSlash(query, anchor) {
    var menu = document.querySelector('.studio-slash');
    if (!menu) {
      menu = document.createElement('div');
      menu.className = 'studio-slash';
      menu.setAttribute('role', 'listbox');
      menu.setAttribute('aria-label', 'Insert a block');
      document.body.appendChild(menu);
    }
    if (anchor) {
      var rect = anchor.getBoundingClientRect();
      menu.style.left = Math.max(12, rect.left) + 'px';
      menu.style.top = (rect.bottom + 8) + 'px';
      menu.style.right = 'auto';
      menu.style.bottom = 'auto';
    }
    var items = boot.blocks.filter(function (block) {
      return (block.name + ' ' + block.type).toLowerCase().indexOf(query.toLowerCase()) !== -1;
    });
    menu.innerHTML = (items.length ? items.map(function (block, index) {
      return '<button type="button" data-slash="' + block.type + '"' + (index === 0 ? ' class="is-active"' : '') + '><i class="' + block.icon + '" aria-hidden="true"></i><span><strong>' + block.name + '</strong><small>' + block.description + '</small></span></button>';
    }).join('') : '<p>No block matches. Press Escape to keep typing.</p>') + '<p class="studio-slash-keys">↑↓ to choose · Enter to insert · Esc to close</p>';
  }

  document.addEventListener('keydown', function (event) {
    var menu = document.querySelector('.studio-slash');
    if (!menu) return;
    var buttons = Array.prototype.slice.call(menu.querySelectorAll('[data-slash]'));
    var current = menu.querySelector('.is-active');
    var index = buttons.indexOf(current);
    if (event.key === 'Escape') {
      hideSlash();
      return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (!buttons.length) return;
      if (current) current.classList.remove('is-active');
      var next = buttons[(index + (event.key === 'ArrowDown' ? 1 : buttons.length - 1)) % buttons.length];
      next.classList.add('is-active');
    }
    if (event.key === 'Enter' && current) {
      event.preventDefault();
      if (blocks()[state.selected]) blocks()[state.selected].content = [];
      insert(current.getAttribute('data-slash'));
      hideSlash();
    }
  });

  document.addEventListener('click', function (event) {
    var choice = event.target.closest('[data-slash]');
    if (!choice) return;
    if (blocks()[state.selected]) blocks()[state.selected].content = [];
    insert(choice.getAttribute('data-slash'));
    hideSlash();
  });

  try {
    var stored = JSON.parse(localStorage.getItem(key) || 'null');
    if (stored && stored.baseVersion === post.lockVersion) state.recovery = stored;
  } catch (error) {}

  render();
  growTitle(root.querySelector('.studio-title'));
})();
