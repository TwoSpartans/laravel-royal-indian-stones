import { useCallback, useEffect, useRef, useState } from 'react';
import { searchProducts } from './api';
import { Icon } from './Icon';
import { useDebouncedValue } from './hooks';
import { Modal } from './Modal';
import type { Bootstrap, ProductSearchItem, RequestError } from './types';

type Props = {
  bootstrap: Bootstrap;
  currentProductId: number | null;
  onChoose: (product: ProductSearchItem, variantId: number | null) => void;
  onClose: () => void;
};

export default function ProductPicker({ bootstrap, currentProductId, onChoose, onClose }: Props) {
  const [query, setQuery] = useState('');
  const debouncedQuery = useDebouncedValue(query, 300);
  const [items, setItems] = useState<ProductSearchItem[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [pending, setPending] = useState<ProductSearchItem | null>(null);
  const [variantId, setVariantId] = useState<number | null>(null);
  const controller = useRef<AbortController | null>(null);

  const load = useCallback((term: string, nextPage: number) => {
    controller.current?.abort();
    const abort = new AbortController();
    controller.current = abort;
    setLoading(true);
    setError('');

    searchProducts(bootstrap, term, nextPage, abort.signal)
      .then((result) => {
        setItems((current) => (nextPage === 1 ? result.data : [...current, ...result.data]));
        setPage(result.meta.current_page);
        setLastPage(result.meta.last_page);
      })
      .catch((cause: unknown) => {
        if (cause instanceof DOMException && cause.name === 'AbortError') {
          return;
        }
        setError((cause as RequestError).message || 'Products could not be loaded.');
      })
      .finally(() => {
        if (controller.current === abort) {
          setLoading(false);
        }
      });
  }, [bootstrap]);

  useEffect(() => {
    load(debouncedQuery, 1);
  }, [debouncedQuery, load]);

  useEffect(() => () => controller.current?.abort(), []);

  const unavailable = (product: ProductSearchItem) => !product.visible || product.health === 'blocked';

  const choose = (product: ProductSearchItem) => {
    if (unavailable(product)) {
      return;
    }
    if (product.variants.length > 1) {
      setPending(product);
      setVariantId(null);

      return;
    }
    onChoose(product, null);
  };

  const confirmPending = () => {
    if (!pending || unavailable(pending)) {
      return;
    }
    const chosen = pending.variants.find((variant) => variant.id === variantId);
    if (chosen && !chosen.hasPrice) {
      return;
    }
    onChoose(pending, variantId);
  };

  return (
    <Modal title={pending ? `Choose a variant of ${pending.name}` : 'Choose a product'} labelId="studio-product-picker" onClose={onClose} wide>
      {pending ? (
        <div className="studio-stack">
          <p className="studio-hint">
            The spotlight shows the live catalog price in the reader&apos;s currency. Pick a variant only when the article
            is about that exact size or finish.
          </p>
          <fieldset className="studio-stack">
            <legend className="sr-only">Variant</legend>
            <label className="studio-check">
              <input type="radio" name="spotlight-variant" checked={variantId === null} onChange={() => setVariantId(null)} />
              Default (the product&apos;s standard price)
            </label>
            {pending.variants.map((variant) => (
              <label key={variant.id} className="studio-check">
                <input
                  type="radio"
                  name="spotlight-variant"
                  checked={variantId === variant.id}
                  disabled={!variant.hasPrice}
                  onChange={() => setVariantId(variant.id)}
                />
                {variant.label}
                {!variant.hasPrice ? <span className="studio-hint"> · no live price</span> : null}
              </label>
            ))}
          </fieldset>
          <div className="studio-row">
            <button type="button" className="studio-btn studio-btn-primary" onClick={confirmPending}>
              Use this product
            </button>
            <button type="button" className="studio-btn" onClick={() => setPending(null)}>Back to results</button>
          </div>
        </div>
      ) : (
        <div className="studio-stack">
          <label className="studio-field">
            <span>Search the catalog</span>
            <input
              type="search"
              autoFocus
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Name, SKU or material"
              maxLength={120}
            />
          </label>
          <p className="sr-only" role="status" aria-live="polite">
            {loading ? 'Searching…' : `${items.length} products shown.`}
          </p>
          {error !== '' ? <p className="studio-error" role="alert">{error}</p> : null}
          {!loading && error === '' && items.length === 0 ? (
            <p className="studio-empty">No products match “{debouncedQuery}”. Try a shorter name or a SKU.</p>
          ) : null}
          <ul className="studio-product-list">
            {items.map((product) => {
              const unusable = unavailable(product);

              return (
                <li key={product.id}>
                  <button
                    type="button"
                    className={`studio-product ${product.id === currentProductId ? 'is-current' : ''}`}
                    disabled={unusable}
                    aria-describedby={product.reason ? `product-reason-${product.id}` : undefined}
                    onClick={() => choose(product)}
                  >
                    {product.imageUrl ? (
                      <img src={product.imageUrl} alt="" width={56} height={56} loading="lazy" />
                    ) : (
                      <span className="studio-product__placeholder" aria-hidden="true">
                        <Icon name="image" />
                      </span>
                    )}
                    <span className="studio-product__text">
                      <strong>{product.name}</strong>
                      <small className={`is-${product.health}`}>
                        {unusable ? 'Not available for spotlights' : product.health === 'attention' ? 'Usable, needs attention' : 'Ready'}
                        {product.variants.length > 1 ? ` · ${product.variants.length} variants` : ''}
                      </small>
                      {product.reason ? <small id={`product-reason-${product.id}`}>{product.reason}</small> : null}
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
          {loading ? <p className="studio-hint">Loading products…</p> : null}
          {!loading && page < lastPage ? (
            <button type="button" className="studio-btn" onClick={() => load(debouncedQuery, page + 1)}>
              Load more products
            </button>
          ) : null}
        </div>
      )}
    </Modal>
  );
}
