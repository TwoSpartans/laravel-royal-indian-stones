import { useCallback, useEffect, useRef, useState } from 'react';
import { saveDraft, type SaveResult } from './api';
import type { MetaState } from './Inspector';
import type { ArticleMetadata, Bootstrap, RequestError, SaveState } from './types';

export type ArticleSnapshot = {
  metadata: ArticleMetadata;
  meta: MetaState;
  content: Record<string, unknown>;
};

export type RecoveryCopy = {
  baseVersion: number;
  changedAt: string;
  snapshot: ArticleSnapshot;
};

type Options = {
  bootstrap: Bootstrap;
  autosave: boolean;
  getSnapshot: () => ArticleSnapshot | null;
  onSaved: (result: SaveResult) => void;
  onValidationError: (errors: Record<string, string[]>, intent: 'autosave' | 'manual') => void;
};

const AUTOSAVE_DELAY = 1500;
const RECOVERY_DELAY = 400;
const RETRY_DELAYS = [3000, 10000, 30000];

function recoveryKey(blogId: number): string {
  return `blog-studio:recovery:${blogId}`;
}

export function readRecovery(blogId: number): RecoveryCopy | null {
  try {
    const raw = window.localStorage.getItem(recoveryKey(blogId));
    if (!raw) {
      return null;
    }
    const parsed = JSON.parse(raw) as RecoveryCopy;

    return parsed && typeof parsed.baseVersion === 'number' && parsed.snapshot?.meta && parsed.snapshot?.content ? parsed : null;
  } catch {
    return null;
  }
}

export function clearRecovery(blogId: number): void {
  try {
    window.localStorage.removeItem(recoveryKey(blogId));
  } catch {
    // Storage can be unavailable in private windows; recovery is best effort.
  }
}

function writeRecovery(blogId: number, copy: RecoveryCopy): void {
  try {
    window.localStorage.setItem(recoveryKey(blogId), JSON.stringify(copy));
  } catch {
    // Quota or privacy mode: the server save path still works.
  }
}

/**
 * Serialises every save so only one request is in flight, coalesces edits made
 * while a save runs into one follow-up save, and keeps a local copy until the
 * server confirms the latest change.
 */
export function useSaveController({ bootstrap, autosave, getSnapshot, onSaved, onValidationError }: Options) {
  const blogId = bootstrap.blog.id;
  const [state, setState] = useState<SaveState>('saved');
  const [message, setMessage] = useState('');
  const [savedAt, setSavedAt] = useState<string | null>(bootstrap.blog.savedAt);
  const [conflictVersion, setConflictVersion] = useState<number | null>(null);

  const versionRef = useRef(bootstrap.blog.lockVersion);
  const changeSeq = useRef(0);
  const savedSeq = useRef(0);
  const queue = useRef<Promise<boolean>>(Promise.resolve(true));
  const pending = useRef(0);
  const blocked = useRef(false);
  const autosaveTimer = useRef<number | null>(null);
  const recoveryTimer = useRef<number | null>(null);
  const retryTimer = useRef<number | null>(null);
  const retryAttempt = useRef(0);

  const optionsRef = useRef({ autosave, getSnapshot, onSaved, onValidationError });
  optionsRef.current = { autosave, getSnapshot, onSaved, onValidationError };

  const isDirty = useCallback(() => changeSeq.current !== savedSeq.current, []);

  const clearTimer = (timer: { current: number | null }) => {
    if (timer.current !== null) {
      window.clearTimeout(timer.current);
      timer.current = null;
    }
  };

  const persistRecovery = useCallback(() => {
    clearTimer(recoveryTimer);
    const snapshot = optionsRef.current.getSnapshot();
    if (snapshot && isDirty()) {
      writeRecovery(blogId, {
        baseVersion: versionRef.current,
        changedAt: new Date().toISOString(),
        snapshot,
      });
    }
  }, [blogId, isDirty]);

  const send = useCallback(async (intent: 'autosave' | 'manual'): Promise<boolean> => {
    if (blocked.current) {
      return false;
    }
    if (intent === 'autosave' && !isDirty()) {
      return true;
    }

    const snapshot = optionsRef.current.getSnapshot();
    if (!snapshot) {
      return false;
    }

    const seqAtSend = changeSeq.current;
    setState('saving');

    try {
      const result = await saveDraft(bootstrap, {
        baseVersion: versionRef.current,
        schemaVersion: 1,
        intent,
        metadata: snapshot.metadata,
        content: snapshot.content,
        clientSavedAt: new Date().toISOString(),
      });

      versionRef.current = result.version;
      savedSeq.current = Math.max(savedSeq.current, seqAtSend);
      retryAttempt.current = 0;
      setSavedAt(result.savedAt);
      setMessage('');
      optionsRef.current.onSaved(result);

      if (isDirty()) {
        setState('unsaved');
        persistRecovery();
      } else {
        setState('saved');
        clearRecovery(blogId);
      }

      return true;
    } catch (cause) {
      const error = cause as RequestError;
      persistRecovery();

      if (error.offline) {
        setState('offline');
        setMessage(error.message);
        const delay = RETRY_DELAYS[Math.min(retryAttempt.current, RETRY_DELAYS.length - 1)];
        retryAttempt.current += 1;
        clearTimer(retryTimer);
        retryTimer.current = window.setTimeout(() => {
          void enqueue('autosave');
        }, delay);

        return false;
      }

      if (error.status === 409 && error.payload?.reason === 'live') {
        setState('unsaved');
        setMessage('');

        return false;
      }

      if (error.status === 409) {
        blocked.current = true;
        setState('conflict');
        setMessage(error.payload?.message || error.message);
        setConflictVersion(typeof error.payload?.serverVersion === 'number' ? error.payload.serverVersion : null);

        return false;
      }

      if (error.status === 422 && error.payload?.errors) {
        optionsRef.current.onValidationError(error.payload.errors, intent);
      }

      setState('failed');
      setMessage(error.message || 'Save failed.');

      return false;
    }
  }, [blogId, bootstrap, isDirty, persistRecovery]);

  const enqueue = useCallback((intent: 'autosave' | 'manual'): Promise<boolean> => {
    clearTimer(autosaveTimer);
    pending.current += 1;
    const next = queue.current.then(() => send(intent)).finally(() => {
      pending.current -= 1;
    });
    queue.current = next.catch(() => false);

    return next;
  }, [send]);

  const markChanged = useCallback(() => {
    changeSeq.current += 1;
    if (!blocked.current) {
      setState((current) => (current === 'saving' || current === 'offline' ? current : 'unsaved'));
    }

    clearTimer(recoveryTimer);
    recoveryTimer.current = window.setTimeout(persistRecovery, RECOVERY_DELAY);

    if (optionsRef.current.autosave && !blocked.current) {
      clearTimer(autosaveTimer);
      autosaveTimer.current = window.setTimeout(() => {
        void enqueue('autosave');
      }, AUTOSAVE_DELAY);
    }
  }, [enqueue, persistRecovery]);

  const saveNow = useCallback(() => enqueue('manual'), [enqueue]);

  const ensureSaved = useCallback(async (): Promise<boolean> => {
    if (!isDirty() && pending.current === 0) {
      return !blocked.current;
    }

    return enqueue('manual');
  }, [enqueue, isDirty]);

  const overwriteConflict = useCallback(() => {
    if (conflictVersion === null) {
      return Promise.resolve(false);
    }
    versionRef.current = conflictVersion;
    blocked.current = false;
    setConflictVersion(null);

    return enqueue('manual');
  }, [conflictVersion, enqueue]);

  const setVersion = useCallback((version: number) => {
    versionRef.current = version;
  }, []);

  useEffect(() => {
    const onBeforeUnload = (event: BeforeUnloadEvent) => {
      if (isDirty() || pending.current > 0) {
        persistRecovery();
        event.preventDefault();
        event.returnValue = '';
      }
    };
    const onHidden = () => {
      if (document.visibilityState === 'hidden' && isDirty()) {
        persistRecovery();
        if (optionsRef.current.autosave && !blocked.current) {
          void enqueue('autosave');
        }
      }
    };
    const onOnline = () => {
      if (isDirty() && !blocked.current) {
        clearTimer(retryTimer);
        void enqueue('autosave');
      }
    };

    window.addEventListener('beforeunload', onBeforeUnload);
    document.addEventListener('visibilitychange', onHidden);
    window.addEventListener('online', onOnline);

    return () => {
      window.removeEventListener('beforeunload', onBeforeUnload);
      document.removeEventListener('visibilitychange', onHidden);
      window.removeEventListener('online', onOnline);
    };
  }, [enqueue, isDirty, persistRecovery]);

  useEffect(() => () => {
    clearTimer(autosaveTimer);
    clearTimer(recoveryTimer);
    clearTimer(retryTimer);
  }, []);

  return {
    state,
    message,
    savedAt,
    conflictVersion,
    isDirty,
    markChanged,
    saveNow,
    ensureSaved,
    overwriteConflict,
    setVersion,
  };
}
