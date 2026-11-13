import { useEffect, useState, type ReactNode } from 'react';

export type MediaPreview = {
  id: number;
  url: string;
  width?: number | null;
  height?: number | null;
};

type MediaCacheValue = {
  urls: Record<number, string>;
  remember: (media: MediaPreview) => void;
};

let urls: Record<number, string> = {};
const listeners = new Set<() => void>();

function emit(): void {
  listeners.forEach((listener) => listener());
}

function seed(items: MediaPreview[]): void {
  let changed = false;
  items.forEach((item) => {
    if (item.id > 0 && item.url !== '' && urls[item.id] !== item.url) {
      urls = { ...urls, [item.id]: item.url };
      changed = true;
    }
  });
  if (changed) {
    emit();
  }
}

export function rememberMedia(media: MediaPreview): void {
  if (media.id <= 0 || media.url === '') {
    return;
  }
  if (urls[media.id] === media.url) {
    return;
  }
  urls = { ...urls, [media.id]: media.url };
  emit();
}

export function getMediaUrl(mediaId: number): string | null {
  return mediaId > 0 ? (urls[mediaId] ?? null) : null;
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

export function MediaCacheProvider({
  initial,
  products = [],
  children,
}: {
  initial: MediaPreview[];
  products?: Array<{ id: number; name: string }>;
  children: ReactNode;
}) {
  const [ready] = useState(() => {
    seed(initial);
    products.forEach((product) => rememberProductName(product.id, product.name));

    return true;
  });

  if (!ready) {
    return null;
  }

  return children;
}

export function useMediaCache(): MediaCacheValue {
  const [snapshot, setSnapshot] = useState(urls);

  useEffect(() => subscribe(() => setSnapshot(urls)), []);

  return {
    urls: snapshot,
    remember: rememberMedia,
  };
}

let productNames: Record<number, string> = {};
const productListeners = new Set<() => void>();

export function rememberProductName(id: number, name: string): void {
  if (id <= 0 || name === '' || productNames[id] === name) {
    return;
  }
  productNames = { ...productNames, [id]: name };
  productListeners.forEach((listener) => listener());
}

export type ProductCard = {
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

let productCards: Record<number, ProductCard> = {};

export function rememberProductCards(cards: ProductCard[]): void {
  let changed = false;
  cards.forEach((card) => {
    if (card.id <= 0) {
      return;
    }
    productCards = { ...productCards, [card.id]: card };
    changed = true;
    if (card.name !== '') {
      rememberProductName(card.id, card.name);
    }
  });
  if (changed) {
    productListeners.forEach((listener) => listener());
  }
}

export function getProductCard(productId: number): ProductCard | null {
  return productId > 0 ? (productCards[productId] ?? null) : null;
}

export function useProductCard(productId: number): ProductCard | null {
  const [card, setCard] = useState(() => getProductCard(productId));

  useEffect(() => {
    const read = () => setCard(getProductCard(productId));
    read();
    productListeners.add(read);

    return () => {
      productListeners.delete(read);
    };
  }, [productId]);

  return card;
}

export function useProductName(productId: number): string | null {
  const [name, setName] = useState(() => productNames[productId] ?? null);

  useEffect(() => {
    const read = () => setName(productNames[productId] ?? null);
    read();
    productListeners.add(read);

    return () => {
      productListeners.delete(read);
    };
  }, [productId]);

  return name;
}

export function useMediaUrl(mediaId: number): string | null {
  const [url, setUrl] = useState(() => getMediaUrl(mediaId));

  useEffect(() => {
    setUrl(getMediaUrl(mediaId));

    return subscribe(() => setUrl(getMediaUrl(mediaId)));
  }, [mediaId]);

  return url;
}
