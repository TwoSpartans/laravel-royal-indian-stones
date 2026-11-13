import { useEffect, useRef } from 'react';

type Props = {
  url: string;
  onClose: () => void;
};

/**
 * Shows the authenticated public preview in this page. A new browser tab is
 * not used, because many browsers block that after the save request.
 */
export function PreviewOverlay({ url, onClose }: Props) {
  const ref = useRef<HTMLDialogElement>(null);
  const onCloseRef = useRef(onClose);
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
        <p>The public article, including live catalog price and currency.</p>
        <button type="button" className="studio-btn" onClick={() => onCloseRef.current()}>
          Close preview
        </button>
      </div>
      <iframe className="studio-preview-dialog__frame" title="Article preview" src={url} />
    </dialog>
  );
}
