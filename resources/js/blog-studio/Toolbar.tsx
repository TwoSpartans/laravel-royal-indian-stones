import { useEditorState, type Editor } from '@tiptap/react';
import { NodeSelection } from '@tiptap/pm/state';
import { useRef, useState, type MouseEvent } from 'react';
import { currentBlock } from './editorCommands';
import { Icon } from './Icon';
import type { BlockAction } from './Inspector';

type Props = {
  editor: Editor;
  onInsert: () => void;
  onBlockAction: (action: BlockAction) => void;
};

const SAFE_LINK = /^(https?:\/\/[^\s]+|\/[^\s]*)$/i;

const keepSelection = (event: MouseEvent) => event.preventDefault();

export function Toolbar({ editor, onInsert, onBlockAction }: Props) {
  const state = useEditorState({
    editor,
    selector: ({ editor: instance }) => {
      const block = currentBlock(instance);
      const atom = instance.state.selection instanceof NodeSelection;

      return {
        bold: instance.isActive('bold'),
        italic: instance.isActive('italic'),
        link: instance.isActive('link'),
        h2: instance.isActive('heading', { level: 2 }),
        h3: instance.isActive('heading', { level: 3 }),
        paragraph: instance.isActive('paragraph'),
        atom,
        hasText: !instance.state.selection.empty,
        canUndo: instance.can().undo(),
        canRedo: instance.can().redo(),
        first: (block?.index ?? 0) === 0,
        last: (block?.index ?? 0) >= instance.state.doc.childCount - 1,
      };
    },
  });
  const [linkOpen, setLinkOpen] = useState(false);
  const [href, setHref] = useState('');
  const [linkError, setLinkError] = useState('');
  const linkInput = useRef<HTMLInputElement>(null);

  const openLink = () => {
    setHref(String(editor.getAttributes('link').href ?? ''));
    setLinkError('');
    setLinkOpen(true);
    window.setTimeout(() => linkInput.current?.focus(), 0);
  };

  const applyLink = () => {
    const value = href.trim();
    if (value === '') {
      editor.chain().focus().extendMarkRange('link').unsetLink().run();
      setLinkOpen(false);

      return;
    }
    if (!SAFE_LINK.test(value)) {
      setLinkError('Use a site path such as /products, or a full https:// address.');

      return;
    }
    editor.chain().focus().extendMarkRange('link').setLink({ href: value }).run();
    setLinkOpen(false);
  };

  return (
    <div className="studio-toolbar-wrap">
      <div className="studio-toolbar" role="toolbar" aria-label="Formatting">
        <div className="studio-toolbar__group">
          <button type="button" onMouseDown={keepSelection} disabled={!state.canUndo} onClick={() => editor.chain().focus().undo().run()} aria-label="Undo" title="Undo (Ctrl+Z)">
            <Icon name="undo-2" />
          </button>
          <button type="button" onMouseDown={keepSelection} disabled={!state.canRedo} onClick={() => editor.chain().focus().redo().run()} aria-label="Redo" title="Redo (Ctrl+Shift+Z)">
            <Icon name="redo-2" />
          </button>
        </div>
        <div className="studio-toolbar__group">
          <button type="button" onMouseDown={keepSelection} disabled={state.atom} aria-pressed={state.paragraph} onClick={() => editor.chain().focus().setParagraph().run()}>
            Text
          </button>
          <button type="button" onMouseDown={keepSelection} disabled={state.atom} aria-pressed={state.h2} onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}>
            H2
          </button>
          <button type="button" onMouseDown={keepSelection} disabled={state.atom} aria-pressed={state.h3} onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}>
            H3
          </button>
        </div>
        <div className="studio-toolbar__group">
          <button type="button" onMouseDown={keepSelection} disabled={state.atom} aria-pressed={state.bold} onClick={() => editor.chain().focus().toggleBold().run()} aria-label="Bold" title="Bold (Ctrl+B)">
            <Icon name="bold" />
          </button>
          <button type="button" onMouseDown={keepSelection} disabled={state.atom} aria-pressed={state.italic} onClick={() => editor.chain().focus().toggleItalic().run()} aria-label="Italic" title="Italic (Ctrl+I)">
            <Icon name="italic" />
          </button>
          <button
            type="button"
            onMouseDown={keepSelection}
            disabled={state.atom || (!state.hasText && !state.link)}
            aria-pressed={state.link}
            aria-expanded={linkOpen}
            onClick={openLink}
            aria-label="Link"
            title={state.hasText || state.link ? 'Link' : 'Select text to add a link'}
          >
            <Icon name="link" />
          </button>
        </div>
        <div className="studio-toolbar__group">
          <button type="button" onClick={onInsert}>
            <Icon name="plus" /> Insert
          </button>
        </div>
        <div className="studio-toolbar__group">
          <button type="button" onMouseDown={keepSelection} disabled={state.first} onClick={() => onBlockAction('up')} aria-label="Move block up" title="Move block up">
            <Icon name="arrow-up" />
          </button>
          <button type="button" onMouseDown={keepSelection} disabled={state.last} onClick={() => onBlockAction('down')} aria-label="Move block down" title="Move block down">
            <Icon name="arrow-down" />
          </button>
          <button type="button" onMouseDown={keepSelection} onClick={() => onBlockAction('duplicate')} aria-label="Duplicate block" title="Duplicate block">
            <Icon name="copy" />
          </button>
          <button type="button" onMouseDown={keepSelection} onClick={() => onBlockAction('delete')} aria-label="Delete block" title="Delete block">
            <Icon name="trash-2" />
          </button>
        </div>
      </div>
      {linkOpen ? (
        <form
          className="studio-link-form"
          onSubmit={(event) => {
            event.preventDefault();
            applyLink();
          }}
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              event.preventDefault();
              event.stopPropagation();
              setLinkOpen(false);
              editor.commands.focus();
            }
          }}
        >
          <label htmlFor="studio-link-href" className="sr-only">Link address</label>
          <input
            id="studio-link-href"
            ref={linkInput}
            value={href}
            onChange={(event) => setHref(event.target.value)}
            placeholder="/products or https://…"
            inputMode="url"
            spellCheck={false}
            aria-invalid={linkError !== ''}
            aria-describedby={linkError !== '' ? 'studio-link-error' : undefined}
          />
          <button type="submit" className="studio-btn studio-btn-primary">Apply</button>
          {state.link ? (
            <button
              type="button"
              className="studio-btn"
              onClick={() => {
                editor.chain().focus().extendMarkRange('link').unsetLink().run();
                setLinkOpen(false);
              }}
            >
              Remove link
            </button>
          ) : null}
          <button type="button" className="studio-btn" onClick={() => { setLinkOpen(false); editor.commands.focus(); }}>Cancel</button>
          {linkError !== '' ? <p id="studio-link-error" className="studio-error">{linkError}</p> : null}
        </form>
      ) : null}
    </div>
  );
}
