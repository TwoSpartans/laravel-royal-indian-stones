import { Node, mergeAttributes } from '@tiptap/core';
import { NodeViewWrapper, ReactNodeViewRenderer } from '@tiptap/react';
import type { ReactNodeViewProps } from '@tiptap/react';
import type { ReactNode } from 'react';
import { frameClasses } from './frameClasses';
import { useMediaUrl, useProductCard, useProductName } from './mediaCache';

function BlockChrome({
  selected,
  label,
  frame,
  children,
}: {
  selected: boolean;
  label: string;
  frame: unknown;
  children: ReactNode;
}) {
  return (
    <NodeViewWrapper
      className={`studio-node ${frameClasses(frame)} ${selected ? 'is-selected' : ''}`}
      data-type={label}
    >
      <p className="studio-node__label">{label}</p>
      {children}
    </NodeViewWrapper>
  );
}

function splitParagraphs(body: string): string[] {
  return body
    .split(/\n\s*\n/)
    .map((paragraph) => paragraph.trim())
    .filter((paragraph) => paragraph !== '');
}

function SplitView({ node, selected }: ReactNodeViewProps) {
  const heading = String(node.attrs.heading ?? '');
  const paragraphs = splitParagraphs(String(node.attrs.body ?? ''));
  const imageLeft = String(node.attrs.layout ?? 'image-right') === 'image-left';
  const mediaId = Number(node.attrs.mediaId ?? 0);
  const alt = String(node.attrs.alt ?? '');
  const decorative = Boolean(node.attrs.decorative);
  const caption = String(node.attrs.caption ?? '');
  const previewUrl = typeof node.attrs.previewUrl === 'string' ? node.attrs.previewUrl : '';
  const url = useMediaUrl(mediaId) ?? (previewUrl !== '' ? previewUrl : null);

  return (
    <BlockChrome selected={selected} label="Text and image" frame={node.attrs.frame}>
      <section className={`rich-split ${imageLeft ? 'is-image-left' : 'is-image-right'}`}>
        <div className="rich-split__copy">
          {heading !== '' ? <h3>{heading}</h3> : null}
          {paragraphs.map((paragraph, index) => (
            <p key={index}>{paragraph}</p>
          ))}
          {heading === '' && paragraphs.length === 0 ? (
            <p className="studio-node__empty">Add a heading and paragraphs in the Block settings.</p>
          ) : null}
        </div>
        <figure className="rich-split__media">
          {url ? (
            <img src={url} alt={decorative ? '' : alt} />
          ) : (
            <p className="studio-node__empty">
              {mediaId > 0 ? 'The image is saved, but its preview URL is missing. Upload it again.' : 'Upload an image in the Block settings.'}
            </p>
          )}
          {caption !== '' ? <figcaption>{caption}</figcaption> : null}
          {url && !decorative && alt === '' ? (
            <figcaption className="studio-node__empty">Add alt text, or mark the image decorative.</figcaption>
          ) : null}
        </figure>
      </section>
    </BlockChrome>
  );
}

function ImageView({ node, selected }: ReactNodeViewProps) {
  const mediaId = Number(node.attrs.mediaId ?? 0);
  const alt = String(node.attrs.alt ?? '');
  const decorative = Boolean(node.attrs.decorative);
  const caption = String(node.attrs.caption ?? '');
  const previewUrl = typeof node.attrs.previewUrl === 'string' ? node.attrs.previewUrl : '';
  const url = useMediaUrl(mediaId) ?? (previewUrl !== '' ? previewUrl : null);

  return (
    <BlockChrome selected={selected} label="Image" frame={node.attrs.frame}>
      {url ? (
        <figure>
          <img src={url} alt={decorative ? '' : alt} />
          {caption !== '' ? <figcaption>{caption}</figcaption> : null}
          {!decorative && alt === '' ? (
            <figcaption className="studio-node__empty">Add alt text, or mark the image decorative.</figcaption>
          ) : null}
        </figure>
      ) : (
        <p className="studio-node__empty">
          {mediaId > 0 ? 'The image is saved, but its preview URL is missing. Upload it again.' : 'Upload an image in the Block settings.'}
        </p>
      )}
    </BlockChrome>
  );
}

function ProductView({ node, selected }: ReactNodeViewProps) {
  const productId = Number(node.attrs.productId ?? 0);
  const presentation = (node.attrs.presentation ?? {}) as Record<string, unknown>;
  const layout = String(presentation.layout ?? 'image-left');
  const eyebrow = String(node.attrs.editorial?.eyebrow ?? 'Product Spotlight');
  const summary = String(node.attrs.editorial?.summary ?? '');
  const showPrice = presentation.showPrice !== false;
  const showAvailability = presentation.showAvailability !== false;
  const ctaLabel = String(presentation.ctaLabel ?? 'View Product');
  const name = useProductName(productId);
  const card = useProductCard(productId);
  const stacked = layout === 'stacked';
  const imageRight = layout === 'image-right';

  return (
    <BlockChrome selected={selected} label="Product Spotlight" frame={node.attrs.frame}>
      <aside className={`rich-stone ${stacked ? 'is-stacked' : imageRight ? 'is-image-right' : 'is-image-left'}`}>
        {productId > 0 && card?.imageUrl ? <img src={card.imageUrl} alt="" /> : null}
        <div>
          <p className="eyebrow">{eyebrow}</p>
          {productId > 0 ? (
            <h2>{card?.name || name || `Product #${productId}`}</h2>
          ) : (
            <p className="studio-node__empty">Choose a product in the Block settings.</p>
          )}
          {productId > 0 && card && !card.visible ? (
            <p className="studio-node__empty">{card.reason || 'This product is not currently available.'}</p>
          ) : null}
          {productId > 0 && card?.visible && showPrice && card.price ? <p>{card.price}</p> : null}
          {summary !== '' ? <p>{summary}</p> : null}
          {productId > 0 && card?.visible && showAvailability ? (
            <p>{card.available ? 'Available' : 'Currently unavailable'}</p>
          ) : null}
          {productId > 0 && card?.visible && card.health !== 'blocked' ? (
            <span className="ris-button">{ctaLabel}</span>
          ) : null}
        </div>
      </aside>
    </BlockChrome>
  );
}

function WorkshopView({ node, selected }: ReactNodeViewProps) {
  const title = String(node.attrs.title ?? '');
  const body = String(node.attrs.body ?? '');
  const stage = String(node.attrs.processStage ?? '');
  const mediaIds = Array.isArray(node.attrs.mediaIds)
    ? (node.attrs.mediaIds as unknown[]).map((id) => Number(id)).filter((id) => id > 0)
    : [];

  return (
    <BlockChrome selected={selected} label="Workshop Story" frame={node.attrs.frame}>
      <section className="rich-quarry">
        {stage !== '' ? <p className="eyebrow">{stage.charAt(0).toUpperCase() + stage.slice(1)}</p> : null}
        <h2>{title || 'Untitled workshop story'}</h2>
        <p>{body || 'Add the process story in the Block settings.'}</p>
        {mediaIds.map((id) => (
          <WorkshopPhoto key={id} mediaId={id} />
        ))}
      </section>
    </BlockChrome>
  );
}

function WorkshopPhoto({ mediaId }: { mediaId: number }) {
  const url = useMediaUrl(mediaId);

  if (!url) {
    return null;
  }

  return <img src={url} alt="" />;
}

type GalleryItem = {
  mediaId?: number | null;
  alt?: string;
  caption?: string | null;
  decorative?: boolean;
};

function GalleryView({ node, selected }: ReactNodeViewProps) {
  const mode = String(node.attrs.mode ?? 'grid');
  const items = Array.isArray(node.attrs.items) ? (node.attrs.items as GalleryItem[]) : [];

  return (
    <BlockChrome selected={selected} label="Gallery" frame={node.attrs.frame}>
      <div className={`rich-gallery ${mode === 'carousel' ? 'is-carousel' : 'is-grid'}`}>
        {items.length === 0 ? (
          <p className="studio-node__empty">Add photographs in the Block settings.</p>
        ) : (
          items.map((item, index) => (
            <GalleryPhoto key={`${item.mediaId ?? 'empty'}-${index}`} item={item} />
          ))
        )}
      </div>
    </BlockChrome>
  );
}

function GalleryPhoto({ item }: { item: GalleryItem }) {
  const mediaId = Number(item.mediaId ?? 0);
  const url = useMediaUrl(mediaId);

  if (mediaId <= 0 || !url) {
    return <p className="studio-node__empty">A photograph in this gallery is missing.</p>;
  }

  return (
    <figure>
      <img src={url} alt={item.decorative ? '' : String(item.alt ?? '')} />
      {item.caption ? <figcaption>{item.caption}</figcaption> : null}
    </figure>
  );
}

function CtaView({ node, selected }: ReactNodeViewProps) {
  const title = String(node.attrs.title ?? '');
  const body = String(node.attrs.body ?? '');
  const buttonText = String(node.attrs.buttonText ?? '');

  return (
    <BlockChrome selected={selected} label="Call to Action" frame={node.attrs.frame}>
      <aside className="rich-cta">
        <h2>{title || 'CTA heading'}</h2>
        {body !== '' ? <p>{body}</p> : null}
        {buttonText !== '' ? <span className="ris-button">{buttonText}</span> : <p className="studio-node__empty">Add a button label and link in the Block settings.</p>}
      </aside>
    </BlockChrome>
  );
}

export const SplitSection = Node.create({
  name: 'splitSection',
  group: 'block',
  atom: true,
  selectable: true,
  addAttributes() {
    return {
      nodeVersion: { default: 1 },
      heading: { default: '' },
      body: { default: '' },
      mediaId: { default: null },
      alt: { default: '' },
      decorative: { default: false },
      caption: { default: null },
      layout: { default: 'image-right' },
      previewUrl: { default: null },
    };
  },
  parseHTML() {
    return [{ tag: 'div[data-studio-split]' }];
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-studio-split': '' })];
  },
  addNodeView() {
    return ReactNodeViewRenderer(SplitView);
  },
});

export const StudioImage = Node.create({
  name: 'image',
  group: 'block',
  atom: true,
  selectable: true,
  addAttributes() {
    return {
      nodeVersion: { default: 1 },
      mediaId: { default: null },
      alt: { default: '' },
      decorative: { default: false },
      caption: { default: null },
      credit: { default: null },
      layout: { default: 'wide' },
      alignment: { default: 'center' },
      previewUrl: { default: null },
    };
  },
  parseHTML() {
    return [{ tag: 'div[data-studio-image]' }];
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-studio-image': '' })];
  },
  addNodeView() {
    return ReactNodeViewRenderer(ImageView);
  },
});

export const ProductSpotlight = Node.create({
  name: 'productSpotlight',
  group: 'block',
  atom: true,
  selectable: true,
  addAttributes() {
    return {
      nodeVersion: { default: 1 },
      productId: { default: null },
      variantId: { default: null },
      mediaId: { default: null },
      presentation: {
        default: {
          layout: 'image-left',
          imageRatio: '4:3',
          showPrice: true,
          showAvailability: true,
          ctaLabel: 'View Product',
        },
      },
      editorial: {
        default: { eyebrow: 'Product Spotlight', summary: '' },
      },
    };
  },
  parseHTML() {
    return [{ tag: 'div[data-product-spotlight]' }];
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-product-spotlight': '' })];
  },
  addNodeView() {
    return ReactNodeViewRenderer(ProductView);
  },
});

export const WorkshopStory = Node.create({
  name: 'workshopStory',
  group: 'block',
  atom: true,
  selectable: true,
  addAttributes() {
    return {
      nodeVersion: { default: 1 },
      title: { default: '' },
      processStage: { default: '' },
      body: { default: '' },
      mediaIds: { default: [] },
      productIds: { default: [] },
      caption: { default: null },
      credit: { default: null },
    };
  },
  parseHTML() {
    return [{ tag: 'div[data-workshop-story]' }];
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-workshop-story': '' })];
  },
  addNodeView() {
    return ReactNodeViewRenderer(WorkshopView);
  },
});

export const StudioCta = Node.create({
  name: 'cta',
  group: 'block',
  atom: true,
  selectable: true,
  addAttributes() {
    return {
      nodeVersion: { default: 1 },
      title: { default: '' },
      body: { default: '' },
      buttonText: { default: '' },
      buttonUrl: { default: null },
    };
  },
  parseHTML() {
    return [{ tag: 'div[data-studio-cta]' }];
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-studio-cta': '' })];
  },
  addNodeView() {
    return ReactNodeViewRenderer(CtaView);
  },
});

export const StudioGallery = Node.create({
  name: 'gallery',
  group: 'block',
  atom: true,
  selectable: true,
  addAttributes() {
    return {
      nodeVersion: { default: 2 },
      mode: { default: 'grid' },
      items: { default: [] },
    };
  },
  parseHTML() {
    return [{ tag: 'div[data-studio-gallery]' }];
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-studio-gallery': '' })];
  },
  addNodeView() {
    return ReactNodeViewRenderer(GalleryView);
  },
});
