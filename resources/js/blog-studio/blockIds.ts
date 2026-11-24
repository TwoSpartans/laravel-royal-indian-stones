import { Extension } from '@tiptap/core';
import type { Editor } from '@tiptap/react';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { frameClasses } from './frameClasses';

const BLOCK_TYPES = ['paragraph', 'heading', 'image', 'splitSection', 'productSpotlight', 'workshopStory', 'cta', 'gallery'];

export function createBlockId(): string {
  return crypto.randomUUID();
}

export const BlockId = Extension.create({
  name: 'blockId',
  addGlobalAttributes() {
    return [
      {
        types: BLOCK_TYPES,
        attributes: {
          blockId: {
            default: null,
            keepOnSplit: false,
            parseHTML: (element: HTMLElement) => element.getAttribute('data-block-id'),
            renderHTML: (attributes: { blockId?: string | null }) => (
              attributes.blockId ? { 'data-block-id': attributes.blockId } : {}
            ),
          },
          frame: {
            default: null,
            renderHTML: (attributes: { frame?: unknown }) => ({
              class: frameClasses(attributes.frame),
            }),
          },
        },
      },
    ];
  },
  addProseMirrorPlugins() {
    return [
      new Plugin({
        key: new PluginKey('blockId'),
        appendTransaction(transactions, _oldState, newState) {
          const tr = newState.tr;
          let changed = false;
          newState.doc.forEach((node, offset) => {
            if (typeof node.attrs.blockId === 'string' && node.attrs.blockId !== '') {
              return;
            }
            const pos = tr.mapping.map(offset);
            tr.setNodeMarkup(pos, undefined, { ...node.attrs, blockId: createBlockId() });
            changed = true;
          });
          if (!changed) {
            return null;
          }
          if (transactions.every((transaction) => transaction.getMeta('blockIdHydration') === true)) {
            tr.setMeta('blockIdHydration', true);
          }

          return tr;
        },
      }),
    ];
  },
});

export function assignMissingBlockIds(editor: Editor): void {
  const { state } = editor;
  let missing = false;
  state.doc.forEach((node) => {
    if (typeof node.attrs.blockId !== 'string' || node.attrs.blockId === '') {
      missing = true;
    }
  });
  if (!missing) {
    return;
  }

  const tr = state.tr.setMeta('blockIdHydration', true);
  editor.view.dispatch(tr);
}
