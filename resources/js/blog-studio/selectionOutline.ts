import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';

export const SelectionOutline = Extension.create({
  name: 'selectionOutline',
  addProseMirrorPlugins() {
    return [
      new Plugin({
        key: new PluginKey('selectionOutline'),
        props: {
          decorations(state) {
            const decorations: Decoration[] = [];
            const { $from } = state.selection;
            const selected = $from.depth >= 1 ? $from.before(1) : null;

            state.doc.forEach((node, offset) => {
              if (offset === selected) {
                decorations.push(Decoration.node(offset, offset + node.nodeSize, { class: 'is-current-block' }));
              }
              const insertAt = offset + node.nodeSize;
              decorations.push(Decoration.widget(insertAt, () => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'studio-insert-gap';
                button.textContent = '+ Insert paragraph';
                button.setAttribute('aria-label', 'Insert a paragraph after this block');
                button.addEventListener('mousedown', (event) => {
                  event.preventDefault();
                });
                button.addEventListener('click', () => {
                  document.dispatchEvent(new CustomEvent('studio-insert-at', { detail: insertAt }));
                });

                return button;
              }, { side: -1, ignoreSelection: true }));
            });

            return DecorationSet.create(state.doc, decorations);
          },
        },
      }),
    ];
  },
});
