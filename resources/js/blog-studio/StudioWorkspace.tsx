import Placeholder from '@tiptap/extension-placeholder';
import { EditorContent, useEditor, type Editor, type JSONContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import type { Node as ProseMirrorNode } from '@tiptap/pm/model';
import { NodeSelection } from '@tiptap/pm/state';
import { lazy, Suspense, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { cancelSchedule, fetchProductCards, publishArticle, requestPreview, scheduleArticle, submitArticle, uploadMedia } from './api';
import {
  blockAt,
  blockSnapshots,
  currentBlock,
  deleteBlock,
  duplicateBlock,
  insertBlock,
  isDocumentEmpty,
  moveBlock,
  outlineFor,
  productIdsIn,
  selectBlockAt,
  selectBlockById,
  updateBlockAttrs,
  updateProductSpotlight,
} from './editorCommands';
import { assignMissingBlockIds, BlockId } from './blockIds';
import { ProductSpotlight, SplitSection, StudioCta, StudioGallery, StudioImage, WorkshopStory } from './extensions';
import { useMediaQuery } from './hooks';
import { blockIcon, Icon, type IconName } from './Icon';
import { Inspector, type BlockAction, type InspectorTab, type MetaState, type SelectedBlock } from './Inspector';
import { getProductCard, rememberMedia, rememberProductCards, rememberProductName } from './mediaCache';
import { Modal } from './Modal';
import { PreviewOverlay } from './PreviewOverlay';
import { applyProductToTarget, productTargetMessage, type ProductChoice } from './productPickerTarget';
import type { PublishAction } from './PublishDialog';
import { filterBlocks, SlashMenu, type SlashState } from './SlashMenu';
import { SelectionOutline } from './selectionOutline';
import { Toolbar } from './Toolbar';
import type {
  ArticleMetadata,
  BlockDefinition,
  Bootstrap,
  OutlineItem,
  ProductSearchItem,
  PublishingStatus,
  QualityItem,
  QualityReport,
  RequestError,
  SaveState,
  StudioBlog,
} from './types';
import { clearRecovery, readRecovery, useSaveController, type ArticleSnapshot, type RecoveryCopy } from './useSaveController';

const ProductPicker = lazy(() => import('./ProductPicker'));
const PublishDialog = lazy(() => import('./PublishDialog'));

const ACCEPTED_IMAGES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_IMAGE_BYTES = 6 * 1024 * 1024;
const SAFE_URI = /^(https?:\/\/|\/)/i;

const ERROR_FIELDS: Array<{ prefix: string; id: string; tab?: InspectorTab }> = [
  { prefix: 'metadata.title', id: 'studio-title' },
  { prefix: 'metadata.excerpt', id: 'studio-excerpt' },
  { prefix: 'metadata.slug', id: 'article-slug', tab: 'advanced' },
  { prefix: 'metadata.metaTitle', id: 'article-meta-title', tab: 'advanced' },
  { prefix: 'metadata.metaDescription', id: 'article-meta-description', tab: 'advanced' },
  { prefix: 'metadata.featuredImageAlt', id: 'article-featured-alt', tab: 'advanced' },
  { prefix: 'metadata.featuredMediaId', id: 'article-featured', tab: 'advanced' },
  { prefix: 'metadata.tags', id: 'article-tags', tab: 'advanced' },
  { prefix: 'metadata.categoryIds', id: 'article-categories', tab: 'advanced' },
];

type Notice = { tone: 'info' | 'success' | 'error'; text: string; link?: { href: string; label: string } };

type OutlineState = { items: OutlineItem[]; products: number; empty: boolean; count: number };

function initialMeta(blog: StudioBlog): MetaState {
  return {
    title: blog.title,
    slug: blog.slug,
    excerpt: blog.excerpt,
    metaTitle: blog.metaTitle,
    metaDescription: blog.metaDescription,
    tagsText: (blog.tags ?? []).join(', '),
    categoryIds: blog.categoryIds,
    featuredMediaId: blog.featuredMediaId,
    featuredUrl: blog.featuredImageUrl,
    featuredAlt: blog.featuredImageAlt,
    removeFeatured: false,
  };
}

function metadataFrom(meta: MetaState): ArticleMetadata {
  return {
    title: meta.title.trim(),
    slug: meta.slug.trim(),
    excerpt: meta.excerpt,
    metaTitle: meta.metaTitle,
    metaDescription: meta.metaDescription,
    featuredMediaId: meta.featuredMediaId,
    removeFeaturedImage: meta.removeFeatured,
    featuredImageAlt: meta.featuredAlt,
    tags: meta.tagsText.split(',').map((tag) => tag.trim()).filter(Boolean),
    categoryIds: meta.categoryIds,
  };
}

function documentForSave(json: JSONContent): Record<string, unknown> {
  const content = (json.content ?? []).map((node) => {
    if (!node.attrs || (node.type !== 'image' && node.type !== 'splitSection')) {
      return node;
    }
    const { previewUrl: _previewUrl, ...attrs } = node.attrs;

    return { ...node, attrs };
  });

  return { ...json, content, schemaVersion: 1 };
}

function contentFor(type: string): JSONContent {
  if (type === 'heading') {
    return { type: 'heading', attrs: { level: 2 }, content: [{ type: 'text', text: 'Section heading' }] };
  }
  if (type === 'gallery') {
    return { type: 'gallery', attrs: { nodeVersion: 2, mode: 'grid', items: [] } };
  }
  if (type === 'splitSection') {
    return {
      type: 'splitSection',
      attrs: {
        nodeVersion: 1,
        heading: 'Section heading',
        body: '',
        mediaId: null,
        alt: '',
        decorative: false,
        caption: null,
        layout: 'image-right',
      },
    };
  }

  return { type };
}

function excerptLength(value: string): number {
  return [...value.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim()].length;
}

function checkImage(file: File): string | null {
  if (!ACCEPTED_IMAGES.includes(file.type)) {
    return 'Uploads must be a JPG, PNG, or WEBP image.';
  }
  if (file.size > MAX_IMAGE_BYTES) {
    return 'Images must be 6MB or smaller.';
  }

  return null;
}

function statusFrom(blog: StudioBlog): PublishingStatus {
  return {
    status: blog.status,
    isLive: blog.isLive,
    hasDraftRevision: blog.hasDraftRevision,
    publishedAt: blog.publishedAt,
    publishedAtInput: blog.publishedAtInput,
    publishedAtLabel: blog.publishedAtLabel,
    version: blog.lockVersion,
  };
}

export function StudioWorkspace({ bootstrap }: { bootstrap: Bootstrap }) {
  const compact = useMediaQuery('(max-width: 1024px)');
  const reducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)');
  const { permissions, timezone } = bootstrap;
  const canPublish = permissions.publish;
  const canSubmit = permissions.submitForReview && !canPublish;

  const [drawer, setDrawer] = useState<'rail' | 'inspector' | null>(null);
  const [railTab, setRailTab] = useState<'insert' | 'layers' | 'templates'>('insert');
  const [mode, setMode] = useState<'write' | 'design' | 'optimize'>('write');
  const [inspectorTab, setInspectorTab] = useState<InspectorTab>('content');
  const [focusMode, setFocusMode] = useState(false);
  const [history, setHistory] = useState({ undo: false, redo: false });
  const [pendingTemplate, setPendingTemplate] = useState<Bootstrap['templates'][number] | null>(null);
  const [meta, setMeta] = useState<MetaState>(() => initialMeta(bootstrap.blog));
  const [status, setStatus] = useState<PublishingStatus>(() => statusFrom(bootstrap.blog));
  const [publicUrl, setPublicUrl] = useState(bootstrap.blog.publicUrl);
  const [quality, setQuality] = useState<QualityReport>(bootstrap.quality);
  const [qualityStale, setQualityStale] = useState(false);
  const [outline, setOutline] = useState<OutlineState>({ items: [], products: 0, empty: false, count: 1 });
  const [selected, setSelected] = useState<SelectedBlock | null>(null);
  const [slash, setSlash] = useState<SlashState | null>(null);
  const [slashIndex, setSlashIndex] = useState(0);
  const [blockFilter, setBlockFilter] = useState('');
  const [pickerOpen, setPickerOpen] = useState(false);
  const [pickerProductId, setPickerProductId] = useState<number | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [publishOpen, setPublishOpen] = useState(false);
  const [publishBusy, setPublishBusy] = useState<PublishAction | null>(null);
  const [publishError, setPublishError] = useState('');
  const [publishProblems, setPublishProblems] = useState<string[]>([]);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [liveNoticeOpen, setLiveNoticeOpen] = useState(true);
  const [hasDraft, setHasDraft] = useState(bootstrap.blog.hasDraftRevision);
  const [uploading, setUploading] = useState<'featured' | 'block' | null>(null);
  const [recovery, setRecovery] = useState<RecoveryCopy | null>(() => readRecovery(bootstrap.blog.id));
  const [errorTarget, setErrorTarget] = useState<{ id: string; tab?: InspectorTab } | 'content' | null>(null);

  const metaRef = useRef(meta);
  const editorRef = useRef<Editor | null>(null);
  const selectedRef = useRef<SelectedBlock | null>(null);
  const pickerTargetId = useRef<string | null>(null);
  const selectedKey = useRef('');
  const slashDismissed = useRef('');
  const touched = useRef(false);
  const slugTouched = useRef(false);
  const outlineTimer = useRef<number | null>(null);
  const articleRef = useRef<HTMLDivElement>(null);
  const titleRef = useRef<HTMLTextAreaElement>(null);
  const drawerOpener = useRef<HTMLElement | null>(null);
  const blockSearchRef = useRef<HTMLInputElement>(null);

  const isPublished = status.status === 'published';

  const getSnapshot = (): ArticleSnapshot | null => {
    const instance = editorRef.current;
    if (!instance) {
      return null;
    }

    return {
      metadata: metadataFrom(metaRef.current),
      meta: metaRef.current,
      content: documentForSave(instance.getJSON()),
    };
  };

  const focusTarget = (target: { id: string; tab?: InspectorTab } | 'content') => {
    if (target === 'content') {
      editorRef.current?.commands.focus();

      return;
    }
    if (target.tab) {
      setInspectorTab(target.tab);
      if (compact) {
        setDrawer('inspector');
      }
    } else if (compact) {
      setDrawer(null);
    }
    window.setTimeout(() => {
      const element = document.getElementById(target.id);
      const field = element?.matches('input, textarea, select, button')
        ? element
        : element?.querySelector<HTMLElement>('input, textarea, select, button');
      element?.scrollIntoView({ block: 'center', behavior: reducedMotion ? 'auto' : 'smooth' });
      field?.focus({ preventScroll: true });
    }, 60);
  };

  const save = useSaveController({
    bootstrap,
    autosave: true,
    getSnapshot,
    onSaved: (result) => {
      setQuality(result.quality);
      setQualityStale(false);
      setPublicUrl(result.publicUrl);
      setErrorTarget(null);
      setHasDraft(Boolean(result.hasDraftRevision));
      setStatus((current) => ({
        ...current,
        status: result.status,
        isLive: result.isLive,
        hasDraftRevision: Boolean(result.hasDraftRevision),
        version: result.version,
      }));
      if (!slugTouched.current && result.slug !== metaRef.current.slug) {
        metaRef.current = { ...metaRef.current, slug: result.slug };
        setMeta(metaRef.current);
      }
    },
    onValidationError: (errors, intent) => {
      const key = Object.keys(errors)[0] ?? '';
      const field = ERROR_FIELDS.find((item) => key.startsWith(item.prefix));
      const target = field ? { id: field.id, tab: field.tab } : key === 'content' ? ('content' as const) : null;
      setErrorTarget(target);
      if (target && intent === 'manual') {
        focusTarget(target);
      }
    },
  });

  const updateMeta = (patch: Partial<MetaState>) => {
    if ('slug' in patch) {
      slugTouched.current = true;
    }
    metaRef.current = { ...metaRef.current, ...patch };
    setMeta(metaRef.current);
    setQualityStale(true);
    save.markChanged();
  };

  const refreshOutline = (instance: Editor) => {
    const next: OutlineState = {
      items: outlineFor(instance),
      products: productIdsIn(instance).length,
      empty: isDocumentEmpty(instance),
      count: instance.state.doc.childCount,
    };
    setOutline((current) => (JSON.stringify(current) === JSON.stringify(next) ? current : next));
  };

  const scheduleOutline = (instance: Editor) => {
    if (outlineTimer.current !== null) {
      window.clearTimeout(outlineTimer.current);
    }
    outlineTimer.current = window.setTimeout(() => refreshOutline(instance), 250);
  };

  const syncSelection = (instance: Editor) => {
    const block = currentBlock(instance);
    const key = block ? `${block.index}|${block.start}|${block.node.type.name}|${JSON.stringify(block.node.attrs)}` : '';
    if (key === selectedKey.current) {
      return;
    }
    const previous = selectedRef.current;
    selectedKey.current = key;
    const next: SelectedBlock | null = block
      ? {
        index: block.index,
        start: block.start,
        type: block.node.type.name,
        attrs: block.node.attrs as Record<string, unknown>,
        isAtom: block.node.isAtom,
      }
      : null;
    selectedRef.current = next;
    setSelected(next);

    const blockChanged = previous?.index !== next?.index || previous?.type !== next?.type;
    const newAtom = Boolean(next?.isAtom) && instance.state.selection instanceof NodeSelection && blockChanged;
    if (blockChanged) {
      document.getElementById('studio-inspector')?.scrollTo({ top: 0 });
    }
    if (newAtom) {
      setInspectorTab('content');
    }
    setHistory({ undo: instance.can().undo(), redo: instance.can().redo() });
  };

  const syncSlash = (instance: Editor) => {
    const { selection } = instance.state;
    const block = currentBlock(instance);
    let next: SlashState | null = null;

    if (block && selection.empty && block.node.type.name === 'paragraph') {
      const text = block.node.textContent;
      const atEnd = selection.from === block.start + block.node.nodeSize - 1;
      const key = `${block.start}:${text}`;
      if (atEnd && text.startsWith('/') && text.length <= 24 && !/\s/.test(text) && slashDismissed.current !== key) {
        const coords = instance.view.coordsAtPos(selection.from);
        const host = articleRef.current?.getBoundingClientRect();
        const width = host?.width ?? 320;
        next = {
          text,
          query: text.slice(1),
          index: block.index,
          start: block.start,
          top: coords.bottom - (host?.top ?? 0) + 6,
          left: Math.max(0, Math.min(coords.left - (host?.left ?? 0), width - 300)),
        };
      }
    }

    setSlash((current) => {
      if (current === null && next === null) {
        return current;
      }
      if (current && next && current.text === next.text && current.start === next.start && current.top === next.top) {
        return current;
      }

      return next;
    });
  };

  const slashItems = slash ? filterBlocks(bootstrap.blocks, slash.query) : [];

  useEffect(() => {
    setSlashIndex(0);
  }, [slash?.query]);

  const afterInsert = (type: string) => {
    const definition = bootstrap.blocks.find((block) => block.type === type);
    if (!definition || ['paragraph', 'heading'].includes(type)) {
      if (compact) {
        setDrawer(null);
      }

      return;
    }
    setInspectorTab('content');
    if (compact) {
      setDrawer('inspector');
    }
    if (type === 'productSpotlight') {
      openProductPicker();
    }
  };

  const openProductPicker = () => {
    const instance = editorRef.current;
    const block = instance ? currentBlock(instance) : null;
    const blockId = block?.node.attrs.blockId;
    if (!block || block.node.type.name !== 'productSpotlight' || typeof blockId !== 'string' || blockId === '') {
      setNotice({ tone: 'error', text: 'Select a product block before choosing a product.' });

      return;
    }
    pickerTargetId.current = blockId;
    setPickerProductId(Number(block.node.attrs.productId ?? 0) || null);
    setPickerOpen(true);
  };

  const closeProductPicker = () => {
    pickerTargetId.current = null;
    setPickerProductId(null);
    setPickerOpen(false);
  };

  const confirmProduct = (product: ProductSearchItem, variantId: number | null) => {
    const instance = editorRef.current;
    const targetId = pickerTargetId.current;
    const choice: ProductChoice = {
      productId: product.id,
      variantId,
      visible: product.visible,
      health: product.health,
    };
    const decision = applyProductToTarget(instance ? blockSnapshots(instance) : [], targetId, choice);
    if (!decision.ok) {
      setNotice({ tone: 'error', text: productTargetMessage(decision.reason) });
      if (decision.reason !== 'unavailable') {
        closeProductPicker();
      }

      return;
    }

    const applied = updateProductSpotlight(instance!, targetId!, {
      productId: product.id,
      variantId,
    });
    if (applied !== 'updated') {
      setNotice({ tone: 'error', text: productTargetMessage(applied === 'missing' ? 'missing' : 'not-product') });
      closeProductPicker();

      return;
    }

    rememberProductName(product.id, product.name);
    closeProductPicker();
  };

  const chooseSlash = (block: BlockDefinition) => {
    const instance = editorRef.current;
    const current = slash;
    if (!instance || !current) {
      return;
    }
    const target = blockAt(instance.state.doc, current.index);
    if (!target || target.start !== current.start) {
      setSlash(null);

      return;
    }
    setSlash(null);
    insertBlock(instance, contentFor(block.type), { placeAtEnd: false, replace: target });
    afterInsert(block.type);
  };

  const insertFromRail = (type: string) => {
    const instance = editorRef.current;
    if (!instance) {
      return;
    }
    insertBlock(instance, contentFor(type), { placeAtEnd: !touched.current });
    touched.current = true;
    afterInsert(type);
  };

  const onEditorKeyDown = (event: KeyboardEvent): boolean => {
    if (!slash) {
      return false;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      const length = Math.max(slashItems.length, 1);
      setSlashIndex((index) => (index + (event.key === 'ArrowDown' ? 1 : -1) + length) % length);

      return true;
    }
    if (event.key === 'Enter' || event.key === 'Tab') {
      const choice = slashItems[slashIndex];
      if (!choice) {
        setSlash(null);

        return false;
      }
      chooseSlash(choice);

      return true;
    }
    if (event.key === 'Escape') {
      slashDismissed.current = `${slash.start}:${slash.text}`;
      setSlash(null);

      return true;
    }

    return false;
  };

  const handlers = useRef<{
    keyDown: (event: KeyboardEvent) => boolean;
    create: (instance: Editor) => void;
    update: (instance: Editor) => void;
    selection: (instance: Editor) => void;
  }>({
    keyDown: () => false,
    create: () => {},
    update: () => {},
    selection: () => {},
  });
  handlers.current = {
    keyDown: onEditorKeyDown,
    create: (instance) => {
      editorRef.current = instance;
      assignMissingBlockIds(instance);
      refreshOutline(instance);
      syncSelection(instance);
    },
    update: (instance) => {
      save.markChanged();
      setQualityStale(true);
      scheduleOutline(instance);
      syncSelection(instance);
      syncSlash(instance);
    },
    selection: (instance) => {
      syncSelection(instance);
      syncSlash(instance);
    },
  };

  const [editorOptions] = useState(() => ({
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3] },
        bulletList: false,
        orderedList: false,
        listItem: false,
        listKeymap: false,
        codeBlock: false,
        code: false,
        blockquote: false,
        horizontalRule: false,
        strike: false,
        underline: false,
        link: {
          openOnClick: false,
          autolink: true,
          defaultProtocol: 'https',
          isAllowedUri: (url: string) => SAFE_URI.test(url),
          shouldAutoLink: (url: string) => !url.includes('@'),
        },
      }),
      Placeholder.configure({
        placeholder: ({ node }) => (node.type.name === 'heading' ? 'Heading' : 'Write, or type / to insert a block…'),
      }),
      BlockId,
      SelectionOutline,
      StudioImage,
      SplitSection,
      ProductSpotlight,
      WorkshopStory,
      StudioCta,
      StudioGallery,
    ],
    immediatelyRender: false,
    shouldRerenderOnTransaction: false,
    content: bootstrap.document as JSONContent,
    editorProps: {
      attributes: {
        class: 'studio-prose',
        'aria-label': 'Article body',
        'aria-multiline': 'true',
        role: 'textbox',
      },
      handleKeyDown: (_view: unknown, event: KeyboardEvent) => handlers.current.keyDown(event),
    },
    onCreate: ({ editor: instance }: { editor: Editor }) => handlers.current.create(instance),
    onUpdate: ({ editor: instance, transaction }: { editor: Editor; transaction: { getMeta: (key: string) => unknown } }) => {
      if (transaction.getMeta('blockIdHydration')) {
        return;
      }
      handlers.current.update(instance);
    },
    onSelectionUpdate: ({ editor: instance }: { editor: Editor }) => handlers.current.selection(instance),
    onFocus: () => {
      touched.current = true;
    },
  }));

  const editor = useEditor(editorOptions);

  useEffect(() => {
    editorRef.current = editor;
  }, [editor]);

  useEffect(() => {
    const onInsert = (event: Event) => {
      const position = (event as CustomEvent<number>).detail;
      const instance = editorRef.current;
      if (!instance || typeof position !== 'number') {
        return;
      }
      instance.chain().focus().insertContentAt(position, { type: 'paragraph' }).run();
    };
    document.addEventListener('studio-insert-at', onInsert);

    return () => document.removeEventListener('studio-insert-at', onInsert);
  }, []);

  useEffect(() => {
    if (!editor) {
      return;
    }
    const missing = productIdsIn(editor).filter((id) => getProductCard(id) === null);
    if (missing.length === 0) {
      return;
    }
    let cancelled = false;
    fetchProductCards(bootstrap, missing)
      .then((result) => {
        if (!cancelled) {
          rememberProductCards(result.data);
        }
      })
      .catch(() => {});

    return () => {
      cancelled = true;
    };
  }, [editor, outline, bootstrap]);

  const activeSlashType = slash ? (slashItems[slashIndex]?.type ?? null) : null;

  useEffect(() => {
    if (!editor) {
      return;
    }
    const dom = editor.view.dom;
    if (activeSlashType) {
      dom.setAttribute('aria-controls', 'studio-slash-list');
      dom.setAttribute('aria-activedescendant', `studio-slash-${activeSlashType}`);
    } else {
      dom.removeAttribute('aria-controls');
      dom.removeAttribute('aria-activedescendant');
    }
  }, [editor, activeSlashType]);

  useEffect(() => () => {
    if (outlineTimer.current !== null) {
      window.clearTimeout(outlineTimer.current);
    }
  }, []);

  useLayoutEffect(() => {
    const element = titleRef.current;
    const fit = () => {
      if (!element) {
        return;
      }
      element.style.height = 'auto';
      element.style.height = `${element.scrollHeight}px`;
    };
    fit();
    let cancelled = false;
    void document.fonts?.ready.then(() => {
      if (!cancelled) {
        fit();
      }
    });

    return () => {
      cancelled = true;
    };
  }, [meta.title, compact, focusMode]);

  useEffect(() => {
    if (!compact) {
      setDrawer(null);
    }
  }, [compact]);

  useEffect(() => {
    if (!drawer) {
      return;
    }
    const panel = document.getElementById(drawer === 'rail' ? 'studio-rail' : 'studio-inspector');
    panel?.querySelector<HTMLElement>('input, button, select, textarea, a[href]')?.focus();
  }, [drawer]);

  const openDrawer = (which: 'rail' | 'inspector') => {
    drawerOpener.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    setDrawer(which);
  };

  const closeDrawer = () => {
    setDrawer(null);
    drawerOpener.current?.focus();
    drawerOpener.current = null;
  };

  const drawerRef = useRef(drawer);
  drawerRef.current = drawer;
  const saveNowRef = useRef(save.saveNow);
  saveNowRef.current = save.saveNow;

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        void saveNowRef.current();
      }
      if (event.key === 'Escape' && drawerRef.current && !event.defaultPrevented) {
        setDrawer(null);
        drawerOpener.current?.focus();
      }
    };
    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const runBlockAction = (action: BlockAction) => {
    const instance = editorRef.current;
    if (!instance) {
      return;
    }
    if (action === 'up') {
      moveBlock(instance, -1);
    } else if (action === 'down') {
      moveBlock(instance, 1);
    } else if (action === 'duplicate') {
      duplicateBlock(instance);
    } else {
      deleteBlock(instance);
    }
  };

  const setTextBlock = (level: 2 | 3 | null) => {
    const instance = editorRef.current;
    if (!instance) {
      return;
    }
    if (level === null) {
      instance.chain().focus().setParagraph().run();
    } else {
      instance.chain().focus().setHeading({ level }).run();
    }
  };

  const updateSelectedBlock = (attrs: Record<string, unknown>) => {
    const instance = editorRef.current;
    const target = selectedRef.current;
    if (instance && target) {
      updateBlockAttrs(instance, target.start, attrs);
    }
  };

  const findBlockStart = (instance: Editor, matches: (node: ProseMirrorNode) => boolean): number | null => {
    let found: number | null = null;
    instance.state.doc.forEach((node, offset) => {
      if (found === null && matches(node)) {
        found = offset;
      }
    });

    return found;
  };

  const uploadForBlock = async (file: File) => {
    const instance = editorRef.current;
    const target = selectedRef.current;
    if (!instance || !target) {
      return;
    }
    const problem = checkImage(file);
    if (problem) {
      setNotice({ tone: 'error', text: problem });

      return;
    }
    const original = instance.state.doc.nodeAt(target.start);
    if (!original || !['image', 'splitSection', 'workshopStory', 'gallery'].includes(original.type.name)) {
      return;
    }

    setUploading('block');
    const usesPreview = original.type.name === 'image' || original.type.name === 'splitSection';
    const localUrl = usesPreview ? URL.createObjectURL(file) : null;
    if (localUrl) {
      updateBlockAttrs(instance, target.start, { previewUrl: localUrl });
    }

    try {
      const uploaded = await uploadMedia(bootstrap, file);
      rememberMedia({ id: uploaded.id, url: uploaded.url, width: uploaded.width, height: uploaded.height });
      const start = localUrl
        ? findBlockStart(instance, (node) => (
          (node.type.name === 'image' || node.type.name === 'splitSection') && node.attrs.previewUrl === localUrl
        ))
        : findBlockStart(instance, (node) => node === original)
          ?? (instance.state.doc.nodeAt(target.start)?.type.name === 'workshopStory' ? target.start : null);

      if (start === null) {
        setNotice({ tone: 'error', text: 'The block changed while the photo uploaded. Select it and upload again.' });

        return;
      }
      const node = instance.state.doc.nodeAt(start);
      if (node?.type.name === 'image' || node?.type.name === 'splitSection') {
        updateBlockAttrs(instance, start, { mediaId: uploaded.id, previewUrl: null });
      } else if (node?.type.name === 'gallery') {
        const items = Array.isArray(node.attrs.items) ? (node.attrs.items as Array<Record<string, unknown>>) : [];
        updateBlockAttrs(instance, start, {
          items: [...items, { mediaId: uploaded.id, alt: '', caption: null, decorative: false }],
        });
      } else if (node) {
        const ids = Array.isArray(node.attrs.mediaIds) ? (node.attrs.mediaIds as unknown[]).map(Number) : [];
        updateBlockAttrs(instance, start, { mediaIds: [...ids, uploaded.id] });
      }
    } catch (cause) {
      if (localUrl) {
        const start = findBlockStart(instance, (node) => (
          (node.type.name === 'image' || node.type.name === 'splitSection') && node.attrs.previewUrl === localUrl
        ));
        if (start !== null) {
          updateBlockAttrs(instance, start, { previewUrl: null });
        }
      }
      setNotice({ tone: 'error', text: (cause as RequestError).message || 'The image could not be uploaded.' });
    } finally {
      if (localUrl) {
        URL.revokeObjectURL(localUrl);
      }
      setUploading(null);
    }
  };

  const uploadFeatured = async (file: File) => {
    const problem = checkImage(file);
    if (problem) {
      setNotice({ tone: 'error', text: problem });

      return;
    }
    setUploading('featured');
    try {
      const uploaded = await uploadMedia(bootstrap, file);
      rememberMedia({ id: uploaded.id, url: uploaded.url, width: uploaded.width, height: uploaded.height });
      updateMeta({ featuredMediaId: uploaded.id, featuredUrl: uploaded.url, removeFeatured: false });
    } catch (cause) {
      setNotice({ tone: 'error', text: (cause as RequestError).message || 'The image could not be uploaded.' });
    } finally {
      setUploading(null);
    }
  };

  const jump = (item: QualityItem) => {
    setPublishOpen(false);
    const instance = editorRef.current;
    if (item.id === 'title') {
      focusTarget({ id: 'studio-title' });
    } else if (item.id === 'excerpt') {
      focusTarget({ id: 'studio-excerpt' });
    } else if (item.id === 'featured-alt') {
      focusTarget({ id: 'article-featured-alt', tab: 'advanced' });
    } else if (item.id === 'body') {
      if (compact) {
        setDrawer(null);
      }
      instance?.commands.focus('end');
    } else if (instance && item.blockId && selectBlockById(instance, item.blockId)) {
      setInspectorTab('content');
      if (compact) {
        setDrawer('inspector');
      }
    } else if (instance && item.blockIndex !== null) {
      selectBlockAt(instance, item.blockIndex);
      setInspectorTab('content');
      if (compact) {
        setDrawer('inspector');
      }
    } else if (instance && item.id === 'headings') {
      let index: number | null = null;
      instance.state.doc.forEach((node, _offset, position) => {
        if (index === null && node.type.name === 'heading') {
          index = position;
        }
      });
      if (index !== null) {
        selectBlockAt(instance, index);
      }
    }
  };

  const applyStatus = (result: PublishingStatus) => {
    setHasDraft(result.hasDraftRevision);
    setStatus({
      status: result.status,
      isLive: result.isLive,
      hasDraftRevision: result.hasDraftRevision,
      publishedAt: result.publishedAt,
      publishedAtInput: result.publishedAtInput,
      publishedAtLabel: result.publishedAtLabel,
      version: result.version,
    });
    save.setVersion(result.version);
  };

  const runPublishing = async <T extends PublishingStatus>(action: PublishAction, request: () => Promise<T>): Promise<T | null> => {
    setPublishBusy(action);
    setPublishError('');
    setPublishProblems([]);

    const saved = await save.ensureSaved();
    if (!saved) {
      setPublishBusy(null);
      setPublishError('Your latest changes are not saved yet, so nothing changed. Check the message at the top of the page.');

      return null;
    }

    try {
      const result = await request();
      applyStatus(result);

      return result;
    } catch (cause) {
      const error = cause as RequestError;
      setPublishError(error.message || 'The request failed.');
      setPublishProblems(error.payload?.problems ?? []);
      if (error.payload?.quality) {
        setQuality(error.payload.quality);
      }

      return null;
    } finally {
      setPublishBusy(null);
    }
  };

  const publishNow = async () => {
    const result = await runPublishing('publish', () => publishArticle(bootstrap));
    if (result) {
      setPublishOpen(false);
      setPublicUrl(result.url);
      setNotice({ tone: 'success', text: 'Published. The article is live.', link: { href: result.url, label: 'View article' } });
    }
  };

  const schedule = async (publishAt: string) => {
    const result = await runPublishing('schedule', () => scheduleArticle(bootstrap, publishAt));
    if (result) {
      setPublishOpen(false);
      setNotice({ tone: 'success', text: `Scheduled for ${result.publishedAtLabel} ${timezone.label}.` });
    }
  };

  const cancelScheduled = async () => {
    if (!window.confirm('Cancel the schedule? The article goes back to draft and will not publish on its own.')) {
      return;
    }
    const result = await runPublishing('cancel', () => cancelSchedule(bootstrap));
    if (result) {
      setPublishOpen(false);
      setNotice({ tone: 'info', text: 'Schedule cancelled. The article is a draft again.' });
    } else if (!publishOpen) {
      setNotice({ tone: 'error', text: 'The schedule could not be cancelled. Try again.' });
    }
  };

  const submitForReview = async () => {
    const result = await runPublishing('submit', () => submitArticle(bootstrap));
    if (result) {
      setPublishOpen(false);
      setNotice({ tone: 'success', text: 'Submitted for review. A Super Admin will publish or schedule it.' });
    }
  };

  const chooseMode = (next: 'write' | 'design' | 'optimize') => {
    setMode(next);
    if (next === 'write') {
      setInspectorTab('content');
      setRailTab('insert');
    } else if (next === 'design') {
      setInspectorTab('style');
    } else {
      setInspectorTab('checks');
    }
  };

  const applyTemplate = (template: Bootstrap['templates'][number], how: 'append' | 'replace') => {
    const instance = editorRef.current;
    if (!instance) {
      return;
    }
    const nodes = template.document.content;
    if (how === 'replace') {
      instance.chain().focus().setContent({ type: 'doc', content: nodes }).run();
    } else {
      instance.chain().focus().insertContentAt(instance.state.doc.content.size, nodes).run();
    }
    touched.current = true;
    setPendingTemplate(null);
  };

  const requestTemplate = (template: Bootstrap['templates'][number]) => {
    const instance = editorRef.current;
    if (!instance || isDocumentEmpty(instance)) {
      applyTemplate(template, 'replace');

      return;
    }
    setPendingTemplate(template);
  };

  const preview = async () => {
    const saved = await save.ensureSaved();
    if (!saved) {
      setNotice({ tone: 'error', text: 'The article could not be saved, so a preview would be out of date.' });

      return;
    }
    try {
      const result = await requestPreview(bootstrap);
      setPreviewUrl(result.url);
    } catch (cause) {
      setNotice({ tone: 'error', text: (cause as RequestError).message || 'The preview could not be opened.' });
    }
  };

  const restoreRecovery = () => {
    const instance = editorRef.current;
    if (!instance || !recovery) {
      return;
    }
    metaRef.current = recovery.snapshot.meta;
    setMeta(recovery.snapshot.meta);
    instance.commands.setContent(recovery.snapshot.content as JSONContent, { emitUpdate: true });
    save.markChanged();
    refreshOutline(instance);
    setRecovery(null);
  };

  const discardRecovery = () => {
    clearRecovery(bootstrap.blog.id);
    setRecovery(null);
  };

  const filteredBlocks = filterBlocks(bootstrap.blocks, blockFilter);
  const excerptCount = excerptLength(meta.excerpt);
  const excerptRules = bootstrap.excerptRules;
  const excerptTone = excerptCount === 0 ? 'neutral' : excerptCount < excerptRules.min ? 'attention' : excerptCount > excerptRules.max ? 'error' : 'ready';
  const excerptMessage = excerptCount === 0
    ? `${excerptRules.messages.empty} Required to submit, schedule or publish.`
    : excerptCount < excerptRules.min
      ? excerptRules.messages.short.replace(':count', String(excerptCount))
      : excerptCount > excerptRules.max
        ? excerptRules.messages.long.replace(':count', String(excerptCount))
        : 'Ready for listings and search snippets.';

  if (!editor) {
    return <div className="studio-loading" role="status">Loading Blog Studio…</div>;
  }

  return (
    <div className={`studio-shell ${focusMode ? 'is-focus' : ''}`}>
      <header className="studio-topbar">
        <div className="studio-topbar__lead">
          <a href={bootstrap.routes.back} className="studio-icon-btn" aria-label="Back to posts" title="Back to posts">
            <Icon name="arrow-left" />
          </a>
          <p className="studio-identity">Blog Studio</p>
          <div className="studio-modes" role="group" aria-label="Studio mode">
            <button type="button" aria-pressed={mode === 'write'} className={mode === 'write' ? 'is-active' : ''} onClick={() => chooseMode('write')}>Write</button>
            <button type="button" aria-pressed={mode === 'design'} className={mode === 'design' ? 'is-active' : ''} onClick={() => chooseMode('design')}>Design</button>
            <button type="button" aria-pressed={mode === 'optimize'} className={mode === 'optimize' ? 'is-active' : ''} onClick={() => chooseMode('optimize')}>Optimize</button>
          </div>
          {compact && !focusMode ? (
            <button
              type="button"
              className="studio-btn"
              aria-expanded={drawer === 'rail'}
              aria-controls="studio-rail"
              onClick={() => (drawer === 'rail' ? closeDrawer() : openDrawer('rail'))}
            >
              <Icon name="plus" /> Blocks
            </button>
          ) : null}
          <StatusChip
            status={status}
            timezoneLabel={timezone.label}
            publicUrl={publicUrl}
            canSchedule={permissions.schedule}
            onChange={() => setPublishOpen(true)}
            onCancel={() => void cancelScheduled()}
          />
          <SaveIndicator state={save.state} savedAt={save.savedAt} isPublished={isPublished} hasDraft={hasDraft} />
        </div>
        <div className="studio-topbar__actions">
          <button type="button" className="studio-icon-btn" aria-label="Undo" title="Undo" disabled={!history.undo} onClick={() => editor.chain().focus().undo().run()}>
            <Icon name="undo-2" />
          </button>
          <button type="button" className="studio-icon-btn" aria-label="Redo" title="Redo" disabled={!history.redo} onClick={() => editor.chain().focus().redo().run()}>
            <Icon name="redo-2" />
          </button>
          <button type="button" className="studio-btn" onClick={() => void preview()}>Preview</button>
          {permissions.schedule ? (
            <button type="button" className="studio-btn" onClick={() => setPublishOpen(true)}>Schedule</button>
          ) : null}
          {isPublished ? (
            <button type="button" className="studio-btn" disabled={save.state === 'saving'} onClick={() => void save.saveNow()}>
              Save draft
            </button>
          ) : (
            <button type="button" className="studio-btn" disabled={save.state === 'saving'} onClick={() => void save.saveNow()}>
              Save
            </button>
          )}
          {isPublished && canPublish ? (
            <button type="button" className="studio-btn studio-btn-primary" onClick={() => setPublishOpen(true)}>
              Republish
            </button>
          ) : null}
          {!isPublished && canPublish ? (
            <button type="button" className="studio-btn studio-btn-primary" onClick={() => setPublishOpen(true)}>
              {status.status === 'scheduled' ? 'Schedule…' : 'Publish…'}
            </button>
          ) : null}
          {canSubmit ? (
            <button type="button" className="studio-btn studio-btn-primary" onClick={() => setPublishOpen(true)}>
              {status.status === 'pending_review' ? 'In review' : 'Submit for review…'}
            </button>
          ) : null}
          {compact && !focusMode ? (
            <button
              type="button"
              className="studio-btn"
              aria-expanded={drawer === 'inspector'}
              aria-controls="studio-inspector"
              onClick={() => (drawer === 'inspector' ? closeDrawer() : openDrawer('inspector'))}
            >
              <Icon name="sliders-horizontal" /> Settings
            </button>
          ) : null}
          <details className="studio-more">
            <summary className="studio-icon-btn" aria-label="More" title="More">
              <Icon name="ellipsis" />
            </summary>
            <div className="studio-more__menu">
              <button type="button" disabled={save.state === 'saving'} onClick={() => void save.saveNow()}>Save now</button>
              {publicUrl ? (
                <button
                  type="button"
                  onClick={() => {
                    void navigator.clipboard.writeText(publicUrl);
                    setNotice({ tone: 'success', text: 'Public link copied.' });
                  }}
                >
                  Copy public link
                </button>
              ) : null}
              <button type="button" aria-pressed={focusMode} onClick={() => setFocusMode((value) => !value)}>
                {focusMode ? 'Show side panels' : 'Focus mode'}
              </button>
              {permissions.convert ? <a href={bootstrap.routes.classic}>Open the classic editor</a> : null}
            </div>
          </details>
        </div>
      </header>

      <div className="studio-banners">
        {save.state === 'conflict' ? (
          <div className="studio-banner is-error" role="alert">
            <p>{save.message} Your version is kept on this device.</p>
            <div className="studio-row">
              <button type="button" className="studio-btn" onClick={() => window.location.reload()}>Load the latest version</button>
              {save.conflictVersion !== null ? (
                <button
                  type="button"
                  className="studio-btn"
                  onClick={() => {
                    if (window.confirm('Replace the other changes with your version? Their edits will be lost.')) {
                      void save.overwriteConflict();
                    }
                  }}
                >
                  Keep my version
                </button>
              ) : null}
            </div>
          </div>
        ) : null}
        {save.state === 'offline' ? (
          <div className="studio-banner is-warning" role="status">
            <p>You are offline. Your changes are kept on this device and save when the connection returns.</p>
            <button type="button" className="studio-btn" onClick={() => void save.saveNow()}>Retry now</button>
          </div>
        ) : null}
        {save.state === 'failed' ? (
          <div className="studio-banner is-error" role="alert">
            <p>Save failed: {save.message}</p>
            <div className="studio-row">
              {errorTarget ? (
                <button type="button" className="studio-btn" onClick={() => focusTarget(errorTarget)}>Show me</button>
              ) : null}
              <button type="button" className="studio-btn" onClick={() => void save.saveNow()}>Try again</button>
            </div>
          </div>
        ) : null}
        {recovery ? (
          <div className="studio-banner is-warning" role="status">
            <p>
              Unsaved changes from {new Date(recovery.changedAt).toLocaleString()} were found on this device.
              {recovery.baseVersion !== bootstrap.blog.lockVersion
                ? ' The article has been saved elsewhere since then; restoring replaces that version when you save.'
                : ''}
            </p>
            <div className="studio-row">
              <button type="button" className="studio-btn studio-btn-primary" onClick={restoreRecovery}>Restore my changes</button>
              <button type="button" className="studio-btn" onClick={discardRecovery}>Discard them</button>
            </div>
          </div>
        ) : null}
        {isPublished && liveNoticeOpen ? (
          <div className="studio-banner is-info" role="status">
            <Icon name="info" />
            <p>
              You are editing a draft of a live article. Autosave, preview and checks use that draft. Readers keep seeing
              the published article until you republish it.
            </p>
            <button type="button" className="studio-icon-btn" aria-label="Dismiss" onClick={() => setLiveNoticeOpen(false)}>
              <Icon name="x" />
            </button>
          </div>
        ) : null}
        {notice ? (
          <div className={`studio-banner is-${notice.tone === 'error' ? 'error' : notice.tone === 'success' ? 'success' : 'info'}`} role={notice.tone === 'error' ? 'alert' : 'status'}>
            <p>
              {notice.text}
              {notice.link ? (
                <>
                  {' '}
                  <a href={notice.link.href} target="_blank" rel="noopener noreferrer" className="studio-link">{notice.link.label}</a>
                </>
              ) : null}
            </p>
            <button type="button" className="studio-icon-btn" aria-label="Dismiss" onClick={() => setNotice(null)}>
              <Icon name="x" />
            </button>
          </div>
        ) : null}
      </div>

      <nav className="studio-workflow" aria-label="Article workflow">
        <button type="button" onClick={() => { chooseMode('write'); setRailTab('templates'); }}>Template</button>
        <button type="button" onClick={() => chooseMode('write')}>Build</button>
        <button type="button" onClick={() => chooseMode('design')}>Design</button>
        <button type="button" onClick={() => chooseMode('optimize')}>Optimize</button>
        <button type="button" onClick={() => setPublishOpen(true)}>Publish</button>
      </nav>
      <ReadinessBar
        items={quality.items}
        onOpen={(item) => {
          chooseMode('optimize');
          jump(item);
        }}
      />

      <div className="studio-workspace">
        {compact && drawer ? <div className="studio-backdrop" aria-hidden="true" onClick={closeDrawer} /> : null}

        <aside
          id="studio-rail"
          className={`studio-rail ${drawer === 'rail' ? 'is-open' : ''}`}
          aria-label="Insert blocks and outline"
          inert={compact && drawer !== 'rail' ? true : undefined}
        >
          <div className="studio-panel">
            <div className="studio-tabs studio-tabs--rail" role="tablist" aria-label="Blocks">
              <button type="button" role="tab" aria-selected={railTab === 'insert'} className={railTab === 'insert' ? 'is-active' : ''} onClick={() => setRailTab('insert')}>
                Insert
              </button>
              <button type="button" role="tab" aria-selected={railTab === 'layers'} className={railTab === 'layers' ? 'is-active' : ''} onClick={() => setRailTab('layers')}>
                Layers
              </button>
              <button type="button" role="tab" aria-selected={railTab === 'templates'} className={railTab === 'templates' ? 'is-active' : ''} onClick={() => setRailTab('templates')}>
                Templates
              </button>
              {compact ? (
                <button type="button" className="studio-icon-btn studio-drawer-close" aria-label="Close blocks" onClick={closeDrawer}>
                  <Icon name="x" />
                </button>
              ) : null}
            </div>
            {railTab === 'insert' ? (
              <div className="studio-rail-body">
                <label className="sr-only" htmlFor="block-search">Search blocks</label>
                <input
                  id="block-search"
                  ref={blockSearchRef}
                  type="search"
                  value={blockFilter}
                  onChange={(event) => setBlockFilter(event.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter' && filteredBlocks[0]) {
                      event.preventDefault();
                      insertFromRail(filteredBlocks[0].type);
                    }
                  }}
                  placeholder="Search blocks"
                />
                <p className="studio-hint">
                  {touched.current ? 'Inserts after the block with the cursor.' : 'Inserts at the end of the article.'} In the text, type / for the same list.
                </p>
                {filteredBlocks.length === 0 ? <p className="studio-empty">No block matches “{blockFilter}”.</p> : null}
                {filteredBlocks.map((block) => (
                  <button key={block.type} type="button" className="studio-block-btn" onClick={() => insertFromRail(block.type)}>
                    <Icon name={blockIcon(block.type)} />
                    <span>
                      <strong>{block.name}</strong>
                      <small>{block.description}</small>
                    </span>
                  </button>
                ))}
              </div>
            ) : railTab === 'layers' ? (
              <div className="studio-rail-body">
                {outline.items.length === 0 ? (
                  <p className="studio-empty">Layers fill in as you write. Headings and blocks appear here so you can jump to them.</p>
                ) : (
                  <ol className="studio-outline">
                    {outline.items.map((item) => {
                      const block = bootstrap.blocks.find((entry) => entry.type === item.type);
                      const typeName = block?.name ?? item.type;
                      const preview = item.label === typeName ? '' : item.label;
                      const visible = preview !== '' ? preview : typeName;

                      return (
                        <li key={`${item.type}-${item.index}`}>
                          <div className={`studio-layer-row ${selected?.index === item.index ? 'is-current' : ''}`}>
                            <button
                              type="button"
                              className={`studio-layer is-${item.type}`}
                              title={`${typeName}: ${item.label}`}
                              onClick={() => {
                                selectBlockAt(editor, item.index);
                                if (compact) {
                                  closeDrawer();
                                }
                              }}
                            >
                              {item.type === 'heading' ? (
                                <span className="studio-layer__mark">H{item.level ?? 2}</span>
                              ) : (
                                <Icon name={blockIcon(item.type)} size={16} />
                              )}
                              <span className="studio-layer__copy">
                                <span className="sr-only">{typeName}: </span>
                                <span className="studio-layer__label">{visible}</span>
                              </span>
                              {item.warning ? (
                                <span className="studio-layer__warn" title="Needs attention">
                                  <Icon name="triangle-alert" size={16} />
                                  <span className="sr-only"> needs attention</span>
                                </span>
                              ) : null}
                            </button>
                            <details className="studio-layer-menu">
                              <summary aria-label={`Actions for ${typeName}`} title="Layer actions">
                                <Icon name="ellipsis" size={16} />
                              </summary>
                              <div className="studio-layer-menu__list">
                                <button type="button" disabled={item.index === 0} onClick={() => { selectBlockAt(editor, item.index); moveBlock(editor, -1); }}>
                                  <Icon name="arrow-up" size={16} /> Move up
                                </button>
                                <button type="button" disabled={item.index >= outline.count - 1} onClick={() => { selectBlockAt(editor, item.index); moveBlock(editor, 1); }}>
                                  <Icon name="arrow-down" size={16} /> Move down
                                </button>
                                <button type="button" onClick={() => { selectBlockAt(editor, item.index); duplicateBlock(editor); }}>
                                  <Icon name="copy" size={16} /> Duplicate
                                </button>
                                <button type="button" className="is-danger" onClick={() => { selectBlockAt(editor, item.index); deleteBlock(editor); }}>
                                  <Icon name="trash-2" size={16} /> Delete
                                </button>
                              </div>
                            </details>
                          </div>
                          {item.children.length > 0 ? (
                            <ol className="studio-outline is-nested">
                              {item.children.map((child, childIndex) => (
                                <li key={`${item.index}-${childIndex}`}>
                                  <span className={`studio-layer studio-layer--child ${child.warning ? 'is-warning' : ''}`}>
                                    <Icon name="image" size={16} />
                                    <span className="studio-layer__label">{child.label}</span>
                                    {child.warning ? (
                                      <span className="studio-layer__warn">
                                        <Icon name="triangle-alert" size={16} />
                                        <span className="sr-only"> needs attention</span>
                                      </span>
                                    ) : null}
                                  </span>
                                </li>
                              ))}
                            </ol>
                          ) : null}
                        </li>
                      );
                    })}
                  </ol>
                )}
              </div>
            ) : (
              <div className="studio-rail-body">
                <p className="studio-hint">Templates insert real article blocks. An empty article is replaced. A started article asks before anything is overwritten.</p>
                {bootstrap.templates.map((template) => (
                  <button key={template.id} type="button" className="studio-block-btn" onClick={() => requestTemplate(template)}>
                    <Icon name="file-text" />
                    <span>
                      <strong>{template.name}</strong>
                      <small>{template.description}</small>
                    </span>
                  </button>
                ))}
              </div>
            )}
          </div>
        </aside>

        <main className="studio-canvas" id="studio-canvas">
          <div className="studio-article" ref={articleRef}>
            {meta.featuredUrl ? (
              <img src={meta.featuredUrl} alt={meta.featuredAlt} className="studio-hero" />
            ) : (
              <button type="button" className="studio-hero-empty" onClick={() => focusTarget({ id: 'article-featured', tab: 'advanced' })}>
                <Icon name="image" /> Add a featured image
              </button>
            )}
            <label className="sr-only" htmlFor="studio-title">Article title</label>
            <textarea
              id="studio-title"
              ref={titleRef}
              rows={1}
              className="studio-title font-brand"
              placeholder="Untitled article"
              value={meta.title}
              maxLength={255}
              onChange={(event) => updateMeta({ title: event.target.value.replace(/\n/g, ' ') })}
              onKeyDown={(event) => {
                if (event.key === 'Enter') {
                  event.preventDefault();
                  document.getElementById('studio-excerpt')?.focus();
                }
              }}
            />
            <label className="sr-only" htmlFor="studio-excerpt">Excerpt</label>
            <textarea
              id="studio-excerpt"
              className="studio-excerpt"
              rows={3}
              value={meta.excerpt}
              aria-describedby="studio-excerpt-count"
              aria-invalid={excerptTone === 'error' ? true : undefined}
              onChange={(event) => updateMeta({ excerpt: event.target.value })}
              placeholder={`Excerpt: ${excerptRules.min}–${excerptRules.max} characters that sum up the story for listings and search.`}
            />
            <p id="studio-excerpt-count" className={`studio-count is-${excerptTone}`}>
              <span>{excerptCount} / {excerptRules.max}</span> {excerptMessage}
            </p>

            <Toolbar
              editor={editor}
              onInsert={() => {
                setRailTab('insert');
                if (compact) {
                  openDrawer('rail');
                } else {
                  blockSearchRef.current?.focus();
                }
              }}
              onBlockAction={runBlockAction}
            />

            <EditorContent editor={editor} />

            {slash ? (
              <SlashMenu slash={slash} items={slashItems} activeIndex={slashIndex} onChoose={chooseSlash} onHover={setSlashIndex} />
            ) : null}

            {outline.empty ? (
              <div className="studio-empty-state">
                <p>Start with the story, or add a block:</p>
                <div className="studio-row">
                  {bootstrap.blocks.filter((block) => block.type !== 'paragraph').map((block) => (
                    <button key={block.type} type="button" className="studio-btn" onClick={() => insertFromRail(block.type)}>
                      <Icon name={blockIcon(block.type)} /> {block.name}
                    </button>
                  ))}
                </div>
              </div>
            ) : null}

            <div className="studio-quality-dock">
              <button
                type="button"
                className={quality.blocked > 0 ? 'is-blocked' : quality.attention > 0 ? 'is-attention' : 'is-ready'}
                onClick={() => {
                  setInspectorTab('checks');
                  if (compact) {
                    openDrawer('inspector');
                  }
                }}
              >
                {quality.blocked > 0
                  ? `${quality.blocked} blocking ${quality.blocked === 1 ? 'check' : 'checks'}`
                  : quality.attention > 0
                    ? `${quality.attention} to review`
                    : 'All checks pass'}
              </button>
              <span>{outline.products} {outline.products === 1 ? 'product' : 'products'} linked</span>
              {qualityStale ? <span>Checks refresh after the next save</span> : null}
            </div>
          </div>
        </main>

        <aside
          id="studio-inspector"
          className={`studio-inspector ${drawer === 'inspector' ? 'is-open' : ''}`}
          aria-label="Settings"
          inert={compact && drawer !== 'inspector' ? true : undefined}
        >
          {compact ? (
            <button type="button" className="studio-icon-btn studio-drawer-close" aria-label="Close settings" onClick={closeDrawer}>
              <Icon name="x" />
            </button>
          ) : null}
          <Inspector
            bootstrap={bootstrap}
            tab={inspectorTab}
            onTab={setInspectorTab}
            meta={meta}
            onMeta={updateMeta}
            selected={selected}
            blockCount={outline.count}
            quality={quality}
            qualityStale={qualityStale}
            uploading={uploading}
            onUpdateBlock={updateSelectedBlock}
            onUploadBlock={(file) => void uploadForBlock(file)}
            onUploadFeatured={(file) => void uploadFeatured(file)}
            onRemoveFeatured={() => updateMeta({ featuredMediaId: null, featuredUrl: null, removeFeatured: true })}
            onPickProduct={openProductPicker}
            onSetTextBlock={setTextBlock}
            onBlockAction={runBlockAction}
            onJump={jump}
          />
        </aside>
      </div>

      <Suspense fallback={null}>
        {pickerOpen ? (
          <ProductPicker
            bootstrap={bootstrap}
            currentProductId={pickerProductId}
            onClose={closeProductPicker}
            onChoose={confirmProduct}
          />
        ) : null}
        {pendingTemplate ? (
          <Modal title={pendingTemplate.name} labelId="template-choice-title" onClose={() => setPendingTemplate(null)}>
            <p>This article already has content. Append adds the template after it. Replace removes the current blocks in one undoable step.</p>
            <div className="studio-row">
              <button type="button" className="studio-btn" onClick={() => applyTemplate(pendingTemplate, 'append')}>Append</button>
              <button type="button" className="studio-btn studio-btn-primary" onClick={() => applyTemplate(pendingTemplate, 'replace')}>Replace article</button>
            </div>
          </Modal>
        ) : null}
        {previewUrl ? <PreviewOverlay url={previewUrl} onClose={() => setPreviewUrl(null)} /> : null}
        {publishOpen ? (
          <PublishDialog
            bootstrap={bootstrap}
            status={status}
            quality={quality}
            slug={meta.slug}
            productCount={outline.products}
            busy={publishBusy}
            error={publishError}
            problems={publishProblems}
            onPublishNow={() => void publishNow()}
            onSchedule={(publishAt) => void schedule(publishAt)}
            onCancelSchedule={() => void cancelScheduled()}
            onSubmit={() => void submitForReview()}
            onJump={jump}
            onClose={() => {
              setPublishOpen(false);
              setPublishError('');
              setPublishProblems([]);
            }}
          />
        ) : null}
      </Suspense>
    </div>
  );
}

function ReadinessBar({ items, onOpen }: { items: QualityItem[]; onOpen: (item: QualityItem) => void }) {
  const groups: Array<{ label: string; match: (item: QualityItem) => boolean }> = [
    { label: 'SEO', match: (item) => item.id === 'title' || item.id === 'excerpt' },
    { label: 'Accessibility', match: (item) => item.section === 'Accessibility' },
    { label: 'Product references', match: (item) => item.section === 'Commerce' },
    { label: 'Media', match: (item) => item.section === 'Media' },
    { label: 'Broken links', match: (item) => item.section === 'Links' },
    { label: 'Publish blockers', match: (item) => item.state === 'blocked' },
  ];

  return (
    <div className="studio-readiness" aria-label="Publish readiness">
      {groups.map((group) => {
        const matched = items.filter(group.match);
        const worst = matched.some((item) => item.state === 'blocked')
          ? 'blocked'
          : matched.some((item) => item.state === 'attention')
            ? 'attention'
            : 'ready';
        const first = matched.find((item) => item.state === worst) ?? matched[0];
        const problems = matched.filter((item) => item.state !== 'ready').length;

        const icon: IconName = group.label === 'Product references'
          ? 'package'
          : group.label === 'Broken links'
            ? 'link'
            : matched.length === 0
              ? 'clock'
              : worst === 'ready'
                ? 'circle-check'
                : 'triangle-alert';

        return (
          <button
            key={group.label}
            type="button"
            className={`studio-readiness__item is-${worst}`}
            disabled={!first}
            onClick={() => { if (first) { onOpen(first); } }}
          >
            <Icon name={icon} size={16} />
            <span>{group.label}</span>
            <small>{matched.length === 0 ? 'No checks yet' : problems === 0 ? 'Clear' : `${problems} to review`}</small>
          </button>
        );
      })}
    </div>
  );
}

function StatusChip({
  status,
  timezoneLabel,
  publicUrl,
  canSchedule,
  onChange,
  onCancel,
}: {
  status: PublishingStatus;
  timezoneLabel: string;
  publicUrl: string;
  canSchedule: boolean;
  onChange: () => void;
  onCancel: () => void;
}) {
  if (status.status === 'schedule_failed') {
    return (
      <div className="studio-chip is-schedule_failed">
        <span>Schedule needs attention</span>
        {canSchedule ? (
          <button type="button" className="studio-link-btn" onClick={onChange}>Review</button>
        ) : null}
      </div>
    );
  }
  if (status.status === 'scheduled') {
    return (
      <div className="studio-chip is-scheduled">
        <span>
          <Icon name="clock" size={16} /> Scheduled · {status.publishedAtLabel} {timezoneLabel}
        </span>
        {canSchedule ? (
          <>
            <button type="button" className="studio-link-btn" onClick={onChange}>Change</button>
            <button type="button" className="studio-link-btn" onClick={onCancel}>Cancel</button>
          </>
        ) : null}
      </div>
    );
  }
  if (status.status === 'published' && status.isLive) {
    return (
      <a href={publicUrl} target="_blank" rel="noopener noreferrer" className="studio-chip is-live">
        <span className="studio-live-dot" aria-hidden="true" /> Live <span className="sr-only">(opens the article)</span>
      </a>
    );
  }

  const label = status.status === 'pending_review' ? 'In review' : status.status === 'published' ? 'Published' : 'Draft';

  return <span className={`studio-chip is-${status.status}`}>{label}</span>;
}

function SaveIndicator({ state, savedAt, isPublished, hasDraft }: { state: SaveState; savedAt: string | null; isPublished: boolean; hasDraft: boolean }) {
  const time = savedAt ? new Date(savedAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : null;
  const label = {
    saved: isPublished && hasDraft ? (time ? `Draft saved ${time}` : 'Draft saved') : (time ? `Saved ${time}` : 'Saved'),
    unsaved: 'Unsaved changes',
    saving: 'Saving…',
    offline: 'Offline',
    conflict: 'Conflict',
    failed: 'Save failed',
  }[state];

  return (
    <p className={`studio-save is-${state}`} role="status" aria-live="polite">
      {label}
    </p>
  );
}
