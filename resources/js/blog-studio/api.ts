import type {
  ArticleMetadata,
  Bootstrap,
  ProductSearchPage,
  PublishingStatus,
  QualityReport,
  RequestError,
} from './types';

export function publicArticlePath(publicUrl: string, slug: string): string {
  const next = slug.trim() || '…';
  try {
    const url = new URL(publicUrl, window.location.origin);
    const parts = url.pathname.split('/').filter((part) => part !== '');
    if (parts.length === 0) {
      return `/${next}`;
    }
    parts[parts.length - 1] = next;

    return `/${parts.join('/')}`;
  } catch {
    return `/${next}`;
  }
}

export function readBootstrap(): Bootstrap {
  const el = document.getElementById('blog-studio-bootstrap');
  if (!el?.textContent) {
    throw new Error('Blog Studio bootstrap data is missing.');
  }

  return JSON.parse(el.textContent) as Bootstrap;
}

async function request<T>(url: string, csrf: string, options: RequestInit = {}): Promise<T> {
  let response: Response;
  try {
    response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
        ...(options.headers ?? {}),
      },
    });
  } catch (cause) {
    if (cause instanceof DOMException && cause.name === 'AbortError') {
      throw cause;
    }
    const error = new Error('You appear to be offline. Changes are kept on this device.') as RequestError;
    error.offline = true;
    throw error;
  }

  const data = (await response.json().catch(() => ({}))) as T & RequestError['payload'];

  if (!response.ok) {
    const fallback = response.status === 419
      ? 'Your session expired. Reload the page to sign in again; your changes are kept on this device.'
      : response.status >= 500
        ? 'The server could not save right now. Your changes are kept on this device.'
        : 'Request failed.';
    const error = new Error(data?.message || fallback) as RequestError;
    error.status = response.status;
    error.payload = data ?? {};
    throw error;
  }

  return data;
}

export type SavePayload = {
  baseVersion: number;
  schemaVersion: number;
  intent: 'autosave' | 'manual';
  metadata: ArticleMetadata;
  content: Record<string, unknown>;
  clientSavedAt: string;
};

export type SaveResult = {
  version: number;
  savedAt: string;
  slug: string;
  publicUrl: string;
  status: PublishingStatus['status'];
  isLive: boolean;
  hasDraftRevision?: boolean;
  quality: QualityReport;
};

export function saveDraft(bootstrap: Bootstrap, payload: SavePayload): Promise<SaveResult> {
  return request(bootstrap.routes.save, bootstrap.csrf, {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export function requestPreview(bootstrap: Bootstrap): Promise<{ url: string }> {
  return request(bootstrap.routes.preview, bootstrap.csrf, { method: 'POST' });
}

export function publishArticle(bootstrap: Bootstrap): Promise<PublishingStatus & { published: boolean; url: string }> {
  return request(bootstrap.routes.publish, bootstrap.csrf, { method: 'POST' });
}

export function scheduleArticle(bootstrap: Bootstrap, publishAt: string): Promise<PublishingStatus & { scheduled: boolean }> {
  return request(bootstrap.routes.schedule, bootstrap.csrf, {
    method: 'POST',
    body: JSON.stringify({ publishAt }),
  });
}

export function cancelSchedule(bootstrap: Bootstrap): Promise<PublishingStatus & { scheduled: boolean }> {
  return request(bootstrap.routes.cancelSchedule, bootstrap.csrf, { method: 'DELETE' });
}

export function submitArticle(bootstrap: Bootstrap): Promise<PublishingStatus & { submitted: boolean; url: string }> {
  return request(bootstrap.routes.submit, bootstrap.csrf, { method: 'POST' });
}

export function convertArticle(
  bootstrap: Bootstrap,
  to: 'studio' | 'classic',
  restorePreviousStudioVersion: boolean,
): Promise<{ contentSource: string; redirect: string; message: string; notes: string[] }> {
  return request(bootstrap.routes.convert, bootstrap.csrf, {
    method: 'POST',
    body: JSON.stringify({ to, confirm: true, restorePreviousStudioVersion }),
  });
}

export function uploadMedia(
  bootstrap: Bootstrap,
  file: File,
): Promise<{ id: number; url: string; width: number | null; height: number | null }> {
  const body = new FormData();
  body.append('file', file);

  return request(bootstrap.routes.upload, bootstrap.csrf, { method: 'POST', body });
}

export type ProductCardPayload = {
  id: number;
  name: string;
  imageUrl: string | null;
  price: string | null;
  available: boolean;
  visible: boolean;
  health: string;
  reason: string | null;
  ctaLabel: string;
};

export function fetchProductCards(bootstrap: Bootstrap, ids: number[]): Promise<{ data: ProductCardPayload[] }> {
  const url = new URL(bootstrap.routes.productCards, window.location.origin);
  url.searchParams.set('ids', ids.join(','));

  return request(url.toString(), bootstrap.csrf);
}

export function searchProducts(
  bootstrap: Bootstrap,
  q: string,
  page: number,
  signal?: AbortSignal,
): Promise<ProductSearchPage> {
  const url = new URL(bootstrap.routes.products, window.location.origin);
  if (q.trim() !== '') {
    url.searchParams.set('q', q.trim());
  }
  if (page > 1) {
    url.searchParams.set('page', String(page));
  }

  return request<ProductSearchPage>(url.toString(), bootstrap.csrf, { signal });
}
