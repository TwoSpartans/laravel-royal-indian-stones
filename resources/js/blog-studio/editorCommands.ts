import type { Editor, JSONContent } from '@tiptap/react';
import type { Node as ProseMirrorNode } from '@tiptap/pm/model';
import { NodeSelection, Selection, TextSelection } from '@tiptap/pm/state';
import type { OutlineItem } from './types';

export type TopLevelBlock = {
  index: number;
  start: number;
  node: ProseMirrorNode;
};

export function blockStart(doc: ProseMirrorNode, index: number): number {
  let position = 0;
  for (let i = 0; i < index && i < doc.childCount; i += 1) {
    position += doc.child(i).nodeSize;
  }

  return position;
}

export function blockAt(doc: ProseMirrorNode, index: number): TopLevelBlock | null {
  if (index < 0 || index >= doc.childCount) {
    return null;
  }

  return { index, start: blockStart(doc, index), node: doc.child(index) };
}

export function currentBlock(editor: Editor): TopLevelBlock | null {
  const { doc, selection } = editor.state;
  if (doc.childCount === 0) {
    return null;
  }
  const $from = doc.resolve(Math.min(selection.from, doc.content.size));

  return blockAt(doc, Math.min($from.index(0), doc.childCount - 1));
}

export function isEmptyParagraph(node: ProseMirrorNode): boolean {
  return node.type.name === 'paragraph' && node.content.size === 0;
}

export function isDocumentEmpty(editor: Editor): boolean {
  const { doc } = editor.state;

  return doc.childCount === 0 || (doc.childCount === 1 && isEmptyParagraph(doc.child(0)));
}

function selectBlock(editor: Editor, start: number, node: ProseMirrorNode, textRange?: { from: number; to: number }) {
  const { tr } = editor.state;
  if (node.isAtom) {
    tr.setSelection(NodeSelection.create(tr.doc, start));
  } else if (textRange) {
    tr.setSelection(TextSelection.create(tr.doc, start + textRange.from, start + textRange.to));
  } else {
    tr.setSelection(TextSelection.create(tr.doc, start + 1));
  }
  editor.view.dispatch(tr.scrollIntoView());
  editor.view.focus();
}

export function selectBlockAt(editor: Editor, index: number): void {
  const block = blockAt(editor.state.doc, index);
  if (!block) {
    return;
  }
  selectBlock(editor, block.start, block.node, block.node.isAtom ? undefined : { from: 1, to: 1 });
}

export function selectBlockById(editor: Editor, blockId: string): boolean {
  const matches: Array<{ start: number; node: ProseMirrorNode }> = [];
  editor.state.doc.forEach((node, offset) => {
    if (matches.length === 0 && node.attrs.blockId === blockId) {
      matches.push({ start: offset, node });
    }
  });
  const found = matches[0];
  if (!found) {
    return false;
  }
  selectBlock(editor, found.start, found.node, found.node.isAtom ? undefined : { from: 1, to: 1 });

  return true;
}

/**
 * Moves the block holding the selection one step up or down with a single
 * transaction, so undo restores it and the caret stays where it was.
 */
export function moveBlock(editor: Editor, direction: -1 | 1): boolean {
  const block = currentBlock(editor);
  if (!block) {
    return false;
  }
  const { state } = editor;
  const targetIndex = block.index + direction;
  if (targetIndex < 0 || targetIndex >= state.doc.childCount) {
    return false;
  }

  const { selection } = state;
  const wasNodeSelection = selection instanceof NodeSelection;
  const relativeFrom = selection.from - block.start;
  const relativeTo = selection.to - block.start;
  const target = state.doc.child(targetIndex);
  const insertAt = direction === -1 ? blockStart(state.doc, targetIndex) : block.start + target.nodeSize;

  const tr = state.tr.delete(block.start, block.start + block.node.nodeSize).insert(insertAt, block.node);
  if (wasNodeSelection || block.node.isAtom) {
    tr.setSelection(NodeSelection.create(tr.doc, insertAt));
  } else {
    tr.setSelection(TextSelection.create(tr.doc, insertAt + relativeFrom, insertAt + relativeTo));
  }
  editor.view.dispatch(tr.scrollIntoView());
  editor.view.focus();

  return true;
}

export function duplicateBlock(editor: Editor): boolean {
  const block = currentBlock(editor);
  if (!block) {
    return false;
  }
  const insertAt = block.start + block.node.nodeSize;
  const attrs = { ...block.node.attrs, blockId: null };
  const copy = block.node.isAtom
    ? block.node.type.create(attrs)
    : block.node.type.create(attrs, block.node.content);
  const tr = editor.state.tr.insert(insertAt, copy);
  if (block.node.isAtom) {
    tr.setSelection(NodeSelection.create(tr.doc, insertAt));
  } else {
    tr.setSelection(TextSelection.create(tr.doc, insertAt + block.node.nodeSize - 1));
  }
  editor.view.dispatch(tr.scrollIntoView());
  editor.view.focus();

  return true;
}

export function deleteBlock(editor: Editor): boolean {
  const block = currentBlock(editor);
  if (!block) {
    return false;
  }
  const { state } = editor;
  const tr = state.tr;
  if (state.doc.childCount === 1) {
    tr.replaceWith(0, state.doc.content.size, state.schema.nodes.paragraph.create());
  } else {
    tr.delete(block.start, block.start + block.node.nodeSize);
  }
  tr.setSelection(Selection.near(tr.doc.resolve(Math.min(block.start, tr.doc.content.size))));
  editor.view.dispatch(tr.scrollIntoView());
  editor.view.focus();

  return true;
}

/**
 * Inserts a block after the block holding the caret. An empty paragraph at
 * the caret is replaced instead of leaving a blank line behind. When the
 * writer has not placed the caret yet, the block goes to the end.
 */
export function insertBlock(
  editor: Editor,
  content: JSONContent,
  options: { placeAtEnd: boolean; replace?: TopLevelBlock | null },
): void {
  const { doc } = editor.state;
  const block = options.replace ?? (options.placeAtEnd ? null : currentBlock(editor));
  let from: number;
  let to: number;

  if (options.replace || (block && isEmptyParagraph(block.node))) {
    from = block!.start;
    to = block!.start + block!.node.nodeSize;
  } else if (options.placeAtEnd || !block) {
    const last = doc.childCount > 0 ? doc.child(doc.childCount - 1) : null;
    if (last && isEmptyParagraph(last)) {
      to = doc.content.size;
      from = to - last.nodeSize;
    } else {
      from = doc.content.size;
      to = from;
    }
  } else {
    from = block.start + block.node.nodeSize;
    to = from;
  }

  editor.chain().insertContentAt({ from, to }, content, { updateSelection: false }).run();

  const inserted = editor.state.doc.nodeAt(from);
  if (!inserted) {
    editor.commands.focus('end');

    return;
  }
  const textLength = inserted.isTextblock ? inserted.textContent.length : 0;
  selectBlock(editor, from, inserted, inserted.isAtom ? undefined : { from: 1, to: 1 + textLength });
}

export function updateBlockAttrs(editor: Editor, start: number, attrs: Record<string, unknown>): void {
  const node = editor.state.doc.nodeAt(start);
  if (!node) {
    return;
  }
  const tr = editor.state.tr.setNodeMarkup(start, undefined, { ...node.attrs, ...attrs });
  if (editor.state.selection instanceof NodeSelection && editor.state.selection.from === start) {
    tr.setSelection(NodeSelection.create(tr.doc, start));
  }
  editor.view.dispatch(tr);
}

export function blockSnapshots(editor: Editor): Array<{ blockId: string | null; type: string }> {
  const blocks: Array<{ blockId: string | null; type: string }> = [];
  editor.state.doc.forEach((node) => {
    const blockId = node.attrs.blockId;
    blocks.push({
      blockId: typeof blockId === 'string' && blockId !== '' ? blockId : null,
      type: node.type.name,
    });
  });

  return blocks;
}

/**
 * Writes attributes onto the product block with this id, wherever it now sits.
 * Returns missing when the block was deleted, and not-product when that id
 * now belongs to a different block type.
 */
export function updateProductSpotlight(
  editor: Editor,
  blockId: string,
  attrs: Record<string, unknown>,
): 'updated' | 'missing' | 'not-product' {
  let start: number | null = null;
  let type = '';
  editor.state.doc.forEach((node, offset) => {
    if (start === null && node.attrs.blockId === blockId) {
      start = offset;
      type = node.type.name;
    }
  });

  if (start === null) {
    return 'missing';
  }

  if (type !== 'productSpotlight') {
    return 'not-product';
  }

  updateBlockAttrs(editor, start, attrs);

  return 'updated';
}

export function outlineFor(editor: Editor): OutlineItem[] {
  const items: OutlineItem[] = [];
  editor.state.doc.forEach((node, _offset, index) => {
    const text = node.textContent.trim();
    let label = text;
    let warning = false;

    let children: OutlineItem['children'] = [];

    if (node.type.name === 'image') {
      label = String(node.attrs.alt || node.attrs.caption || 'Image');
      warning = Number(node.attrs.mediaId ?? 0) <= 0 || (!node.attrs.decorative && String(node.attrs.alt ?? '').trim() === '');
    } else if (node.type.name === 'productSpotlight') {
      const productId = Number(node.attrs.productId ?? 0);
      label = productId > 0 ? `Product #${productId}` : 'Product Spotlight';
      warning = productId <= 0;
    } else if (node.type.name === 'workshopStory') {
      label = String(node.attrs.title || 'Workshop Story');
      warning = String(node.attrs.body ?? '').trim() === '';
    } else if (node.type.name === 'cta') {
      label = String(node.attrs.title || 'Call to Action');
      warning = String(node.attrs.buttonText ?? '').trim() === '' || String(node.attrs.buttonUrl ?? '').trim() === '';
    } else if (node.type.name === 'gallery') {
      const slides = Array.isArray(node.attrs.items) ? (node.attrs.items as Array<Record<string, unknown>>) : [];
      label = slides.length > 0
        ? `${slides.length} ${slides.length === 1 ? 'photograph' : 'photographs'}`
        : 'Gallery';
      warning = slides.length === 0;
      children = slides.map((slide, slideIndex) => {
        const mediaId = Number(slide.mediaId ?? 0);
        const decorative = Boolean(slide.decorative);
        const alt = String(slide.alt ?? '').trim();
        const caption = String(slide.caption ?? '').trim();

        return {
          label: caption || alt || `Photograph ${slideIndex + 1}`,
          warning: mediaId <= 0 || (!decorative && alt === ''),
        };
      });
      warning = warning || children.some((child) => child.warning);
    } else if (node.type.name === 'heading') {
      label = text || 'Empty heading';
      warning = text === '';
    }

    if (node.type.name === 'paragraph' && text === '') {
      return;
    }

    items.push({
      index,
      type: node.type.name,
      label: label.length > 60 ? `${label.slice(0, 57)}…` : label,
      level: node.type.name === 'heading' ? Number(node.attrs.level ?? 2) : undefined,
      warning,
      children,
    });
  });

  return items;
}

export function productIdsIn(editor: Editor): number[] {
  const ids = new Set<number>();
  editor.state.doc.forEach((node) => {
    if (node.type.name === 'productSpotlight') {
      const id = Number(node.attrs.productId ?? 0);
      if (id > 0) {
        ids.add(id);
      }
    }
  });

  return [...ids];
}
