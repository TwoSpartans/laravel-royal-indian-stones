import { useEffect, useRef, useState } from 'react';

type Props = {
  url: string;
  onClose: () => void;
};

type PreviewWidth = 'phone' | 'laptop' | 'wide';

const WIDTHS: { id: PreviewWidth; label: string }[] = [
  { id: 'phone', label: 'Phone' },
  { id: 'laptop', label: 'Laptop' },
  { id: 'wide', label: 'Wide' },
];

/**
 * Shows the authenticated public preview in this page. A new browser tab is
 * not used, because many browsers block that after the save request.
 */
export function PreviewOverlay({ url, onClose }: Props) {
  const ref = useRef<HTMLDialogElement>(null);
  const onCloseRef = useRef(onClose);
  const [width, setWidth] = useState<PreviewWidth>('wide');
  onCloseRef.current = onClose;

  useEffect(() => {
    const dialog = ref.current;
    const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (dialog && !dialog.open) {
      dialog.showModal();
    }

    return () => {
      dialog?.close();
      opener?.focus();
    };
  }, []);

  return (
    <dialog
      ref={ref}
      className="studio-preview-dialog"
      aria-labelledby="studio-preview-title"
      onCancel={(event) => {
        event.preventDefault();
        onCloseRef.current();
      }}
      onKeyDown={(event) => {
        if (event.key === 'Escape') {
          event.preventDefault();
          onCloseRef.current();
        }
      }}
    >
      <div className="studio-preview-dialog__bar">
        <h2 id="studio-preview-title">Preview</h2>
        <div className="studio-preview-widths" role="group" aria-label="Preview width">
          {WIDTHS.map((choice) => (
            <button
              key={choice.id}
              type="button"
              className="studio-btn"
              aria-pressed={width === choice.id}
              onClick={() => setWidth(choice.id)}
            >
              {choice.label}
            </button>
          ))}
        </div>
        <button type="button" className="studio-btn studio-preview-dialog__close" onClick={() => onCloseRef.current()}>
          Close preview
        </button>
      </div>
      <div className="studio-preview-stage">
        <iframe className={`studio-preview-dialog__frame is-${width}`} title="Article preview" src={url} />
      </div>
    </dialog>
  );
}
