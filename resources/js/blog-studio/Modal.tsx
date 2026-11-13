import { useEffect, useRef, type ReactNode } from 'react';
import { Icon } from './Icon';

type Props = {
  title: string;
  labelId: string;
  onClose: () => void;
  children: ReactNode;
  wide?: boolean;
};

/**
 * A native modal dialog: the browser traps focus and closes it on Escape, and
 * focus returns to whatever opened it.
 */
export function Modal({ title, labelId, onClose, children, wide = false }: Props) {
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
      className={`studio-dialog ${wide ? 'is-wide' : ''}`}
      aria-labelledby={labelId}
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
      onClick={(event) => {
        if (event.target === ref.current) {
          onCloseRef.current();
        }
      }}
    >
      <div className="studio-dialog__inner">
        <div className="studio-dialog__head">
          <h2 id={labelId}>{title}</h2>
          <button type="button" className="studio-icon-btn" aria-label="Close" onClick={() => onCloseRef.current()}>
            <Icon name="x" />
          </button>
        </div>
        {children}
      </div>
    </dialog>
  );
}
