import type { ReactNode } from 'react';
import { publicArticlePath } from './api';
import { Icon } from './Icon';
import { useMediaUrl, useProductName } from './mediaCache';
import type { Bootstrap, QualityItem, QualityReport } from './types';

export type InspectorTab = 'content' | 'style' | 'advanced' | 'checks';

export type MetaState = {
  title: string;
  slug: string;
  excerpt: string;
  metaTitle: string;
  metaDescription: string;
  tagsText: string;
  categoryIds: number[];
  featuredMediaId: number | null;
  featuredUrl: string | null;
  featuredAlt: string;
  removeFeatured: boolean;
};

export type SelectedBlock = {
  index: number;
  start: number;
  type: string;
  attrs: Record<string, unknown>;
  isAtom: boolean;
};

export type BlockAction = 'up' | 'down' | 'duplicate' | 'delete';

type Props = {
  bootstrap: Bootstrap;
  tab: InspectorTab;
  onTab: (tab: InspectorTab) => void;
  meta: MetaState;
  onMeta: (patch: Partial<MetaState>) => void;
  selected: SelectedBlock | null;
  blockCount: number;
  quality: QualityReport;
  qualityStale: boolean;
  uploading: 'featured' | 'block' | null;
  onUpdateBlock: (attrs: Record<string, unknown>) => void;
  onUploadBlock: (file: File) => void;
  onUploadFeatured: (file: File) => void;
  onRemoveFeatured: () => void;
  onPickProduct: () => void;
  onSetTextBlock: (level: 2 | 3 | null) => void;
  onBlockAction: (action: BlockAction) => void;
  onJump: (item: QualityItem) => void;
};

const ACCEPT = 'image/jpeg,image/png,image/webp';

const BLOCK_NAMES: Record<string, string> = {
  paragraph: 'Paragraph',
  heading: 'Heading',
  image: 'Image',
  splitSection: 'Text and image',
  productSpotlight: 'Product Spotlight',
  workshopStory: 'Workshop Story',
  cta: 'Call to Action',
  gallery: 'Gallery',
};

function Field({ label, hint, children, id }: { label: string; hint?: ReactNode; children: ReactNode; id?: string }) {
  return (
    <div className="studio-field" id={id ? `${id}-field` : undefined}>
      <label htmlFor={id}>{label}</label>
      {children}
      {hint ? <p className="studio-hint">{hint}</p> : null}
    </div>
  );
}

function Counter({ value, max }: { value: string; max: number }) {
  const length = [...value].length;

  return <span className={length > max ? 'is-over' : ''}>{length} / {max}</span>;
}

function FileButton({ label, busy, onFile, id }: { label: string; busy: boolean; onFile: (file: File) => void; id: string }) {
  return (
    <label className={`studio-btn studio-file ${busy ? 'is-busy' : ''}`} htmlFor={id}>
      <Icon name="upload" />
      {busy ? 'Uploading…' : label}
      <input
        id={id}
        type="file"
        accept={ACCEPT}
        className="sr-only"
        disabled={busy}
        onChange={(event) => {
          const file = event.target.files?.[0];
          event.target.value = '';
          if (file) {
            onFile(file);
          }
        }}
      />
    </label>
  );
}

export function Inspector(props: Props) {
  const tabs: Array<{ id: InspectorTab; label: string }> = [
    { id: 'content', label: 'Content' },
    { id: 'style', label: 'Style' },
    { id: 'advanced', label: 'Advanced' },
    { id: 'checks', label: props.quality.blocked > 0 ? `Checks (${props.quality.blocked})` : 'Checks' },
  ];

  return (
    <div className="studio-panel">
      <div className="studio-tabs studio-tabs--inspector" role="tablist" aria-label="Inspector">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            id={`inspector-tab-${tab.id}`}
            type="button"
            role="tab"
            aria-selected={props.tab === tab.id}
            aria-controls={`inspector-panel-${tab.id}`}
            className={`${props.tab === tab.id ? 'is-active' : ''} ${tab.id === 'checks' && props.quality.blocked > 0 ? 'is-attention' : ''}`}
            onClick={() => props.onTab(tab.id)}
          >
            {tab.id === 'checks' && props.quality.blocked > 0 ? <Icon name="triangle-alert" size={16} /> : null}
            {tab.label}
          </button>
        ))}
      </div>
      <div
        className="studio-rail-body"
        role="tabpanel"
        id={`inspector-panel-${props.tab}`}
        aria-labelledby={`inspector-tab-${props.tab}`}
      >
        {props.tab === 'content' ? (
          <>
            <ArticleSettings {...props} />
            <BlockSettings {...props} />
          </>
        ) : null}
        {props.tab === 'style' ? <StyleSettings {...props} /> : null}
        {props.tab === 'advanced' ? <AdvancedSettings {...props} /> : null}
        {props.tab === 'checks' ? <ChecksPanel {...props} /> : null}
      </div>
    </div>
  );
}

function ArticleSettings({ bootstrap, meta, onMeta, uploading, onUploadFeatured, onRemoveFeatured }: Props) {
  return (
    <>
      <p className="studio-hint">The title and excerpt are edited at the top of the page.</p>
      <Field label="URL slug" id="article-slug" hint={publicArticlePath(bootstrap.blog.publicUrl, meta.slug)}>
        <input
          id="article-slug"
          value={meta.slug}
          maxLength={255}
          onChange={(event) => onMeta({ slug: event.target.value })}
          spellCheck={false}
        />
      </Field>
      <Field label="SEO title" id="article-meta-title" hint={<Counter value={meta.metaTitle} max={60} />}>
        <input
          id="article-meta-title"
          value={meta.metaTitle}
          maxLength={60}
          placeholder={meta.title || 'Defaults to the article title'}
          onChange={(event) => onMeta({ metaTitle: event.target.value })}
        />
      </Field>
      <Field label="Meta description" id="article-meta-description" hint={<Counter value={meta.metaDescription} max={160} />}>
        <textarea
          id="article-meta-description"
          rows={3}
          value={meta.metaDescription}
          maxLength={160}
          placeholder="Defaults to the excerpt"
          onChange={(event) => onMeta({ metaDescription: event.target.value })}
        />
      </Field>
      <Field label="Tags" id="article-tags" hint="Separate tags with commas.">
        <input id="article-tags" value={meta.tagsText} onChange={(event) => onMeta({ tagsText: event.target.value })} />
      </Field>
      <fieldset className="studio-field" id="article-categories">
        <legend>Categories</legend>
        {bootstrap.categories.length === 0 ? <p className="studio-hint">No blog categories exist yet.</p> : null}
        {bootstrap.categories.map((category) => (
          <label key={category.id} className="studio-check">
            <input
              type="checkbox"
              checked={meta.categoryIds.includes(category.id)}
              onChange={(event) => onMeta({
                categoryIds: event.target.checked
                  ? [...meta.categoryIds, category.id]
                  : meta.categoryIds.filter((id) => id !== category.id),
              })}
            />
            {category.name}
            {!category.isActive ? <span className="studio-hint"> (hidden category)</span> : null}
          </label>
        ))}
      </fieldset>
      <div className="studio-field" id="article-featured">
        <p className="studio-field__label">Featured image</p>
        {meta.featuredUrl ? (
          <img src={meta.featuredUrl} alt="" className="studio-thumb" />
        ) : (
          <p className="studio-hint">Shown at the top of the article and in listings. JPG, PNG or WEBP up to 6MB.</p>
        )}
        <div className="studio-row">
          <FileButton
            id="article-featured-file"
            label={meta.featuredUrl ? 'Replace image' : 'Upload image'}
            busy={uploading === 'featured'}
            onFile={onUploadFeatured}
          />
          {meta.featuredUrl ? (
            <button type="button" className="studio-btn" onClick={onRemoveFeatured}>Remove</button>
          ) : null}
        </div>
      </div>
      {meta.featuredUrl ? (
        <Field label="Featured image alt text" id="article-featured-alt" hint={<Counter value={meta.featuredAlt} max={140} />}>
          <input
            id="article-featured-alt"
            value={meta.featuredAlt}
            maxLength={140}
            onChange={(event) => onMeta({ featuredAlt: event.target.value })}
          />
        </Field>
      ) : null}
    </>
  );
}

function BlockSettings(props: Props) {
  const { selected, bootstrap, onUpdateBlock: update, uploading } = props;

  if (!selected) {
    return <p className="studio-empty">Place the cursor in a block, or pick one from the outline, to edit its settings here.</p>;
  }

  const attrs = selected.attrs;
  const presentation = (attrs.presentation ?? {}) as Record<string, unknown>;
  const editorial = (attrs.editorial ?? {}) as Record<string, unknown>;

  return (
    <>
      <h2 className="studio-subhead">{BLOCK_NAMES[selected.type] ?? selected.type}</h2>

      {selected.type === 'paragraph' || selected.type === 'heading' ? (
        <fieldset className="studio-field">
          <legend>Text style</legend>
          <div className="studio-segmented">
            <button type="button" aria-pressed={selected.type === 'paragraph'} onClick={() => props.onSetTextBlock(null)}>Paragraph</button>
            <button
              type="button"
              aria-pressed={selected.type === 'heading' && Number(attrs.level) === 2}
              onClick={() => props.onSetTextBlock(2)}
            >
              Heading 2
            </button>
            <button
              type="button"
              aria-pressed={selected.type === 'heading' && Number(attrs.level) === 3}
              onClick={() => props.onSetTextBlock(3)}
            >
              Heading 3
            </button>
          </div>
        </fieldset>
      ) : null}

      {selected.type === 'image' ? (
        <>
          <ImagePreview mediaId={Number(attrs.mediaId ?? 0)} previewUrl={typeof attrs.previewUrl === 'string' ? attrs.previewUrl : ''} />
          <FileButton
            id="block-image-file"
            label={Number(attrs.mediaId ?? 0) > 0 ? 'Replace image' : 'Upload image'}
            busy={uploading === 'block'}
            onFile={props.onUploadBlock}
          />
          <label className="studio-check">
            <input
              type="checkbox"
              checked={Boolean(attrs.decorative)}
              onChange={(event) => update({ decorative: event.target.checked, alt: event.target.checked ? '' : attrs.alt })}
            />
            Decorative (screen readers skip it)
          </label>
          {!attrs.decorative ? (
            <Field label="Alt text" id="block-image-alt" hint="Describe what the photo shows for readers who cannot see it.">
              <input id="block-image-alt" value={String(attrs.alt ?? '')} maxLength={140} onChange={(event) => update({ alt: event.target.value })} />
            </Field>
          ) : null}
          <Field label="Caption" id="block-image-caption">
            <input id="block-image-caption" value={String(attrs.caption ?? '')} maxLength={300} onChange={(event) => update({ caption: event.target.value })} />
          </Field>
          <Field label="Credit" id="block-image-credit">
            <input id="block-image-credit" value={String(attrs.credit ?? '')} maxLength={140} onChange={(event) => update({ credit: event.target.value })} />
          </Field>
          <Field label="Width" id="block-image-layout">
            <select
              id="block-image-layout"
              value={String(attrs.layout ?? 'wide')}
              onChange={(event) => {
                const layout = event.target.value;
                const width = layout === 'inline' ? 'text' : layout;
                update({ layout, frame: { ...frameOf(attrs, props.bootstrap), width } });
              }}
            >
              <option value="inline">Text width</option>
              <option value="wide">Wide</option>
              <option value="full">Full width</option>
            </select>
          </Field>
          <Field label="Alignment" id="block-image-alignment">
            <select
              id="block-image-alignment"
              value={String(attrs.alignment ?? 'center')}
              onChange={(event) => update({ alignment: event.target.value, frame: { ...frameOf(attrs, props.bootstrap), align: event.target.value } })}
            >
              <option value="left">Left</option>
              <option value="center">Center</option>
              <option value="right">Right</option>
            </select>
          </Field>
        </>
      ) : null}

      {selected.type === 'splitSection' ? (
        <>
          <Field label="Heading" id="block-split-heading">
            <input id="block-split-heading" value={String(attrs.heading ?? '')} maxLength={160} onChange={(event) => update({ heading: event.target.value })} />
          </Field>
          <Field label="Paragraphs" id="block-split-body" hint={<Counter value={String(attrs.body ?? '')} max={4000} />}>
            <textarea
              id="block-split-body"
              rows={6}
              value={String(attrs.body ?? '')}
              maxLength={4000}
              placeholder="Leave a blank line between paragraphs."
              onChange={(event) => update({ body: event.target.value })}
            />
          </Field>
          <ImagePreview mediaId={Number(attrs.mediaId ?? 0)} previewUrl={typeof attrs.previewUrl === 'string' ? attrs.previewUrl : ''} />
          <FileButton
            id="block-split-file"
            label={Number(attrs.mediaId ?? 0) > 0 ? 'Replace image' : 'Upload image'}
            busy={uploading === 'block'}
            onFile={props.onUploadBlock}
          />
          <label className="studio-check">
            <input
              type="checkbox"
              checked={Boolean(attrs.decorative)}
              onChange={(event) => update({ decorative: event.target.checked, alt: event.target.checked ? '' : attrs.alt })}
            />
            Decorative (screen readers skip it)
          </label>
          {!attrs.decorative ? (
            <Field label="Alt text" id="block-split-alt" hint="Describe what the photo shows for readers who cannot see it.">
              <input id="block-split-alt" value={String(attrs.alt ?? '')} maxLength={140} onChange={(event) => update({ alt: event.target.value })} />
            </Field>
          ) : null}
          <Field label="Caption" id="block-split-caption">
            <input id="block-split-caption" value={String(attrs.caption ?? '')} maxLength={300} onChange={(event) => update({ caption: event.target.value })} />
          </Field>
          <Field label="Photograph side" id="block-split-layout">
            <select
              id="block-split-layout"
              value={String(attrs.layout ?? 'image-right') === 'image-left' ? 'image-left' : 'image-right'}
              onChange={(event) => update({ layout: event.target.value })}
            >
              <option value="image-right">Image on the right</option>
              <option value="image-left">Image on the left</option>
            </select>
          </Field>
        </>
      ) : null}

      {selected.type === 'productSpotlight' ? (
        <>
          <ProductSummary productId={Number(attrs.productId ?? 0)} variantId={Number(attrs.variantId ?? 0)} />
          <button type="button" className="studio-btn" onClick={props.onPickProduct}>
            {Number(attrs.productId ?? 0) > 0 ? 'Change product' : 'Choose product'}
          </button>
          <Field label="Eyebrow" id="block-spotlight-eyebrow">
            <input
              id="block-spotlight-eyebrow"
              value={String(editorial.eyebrow ?? '')}
              maxLength={80}
              onChange={(event) => update({ editorial: { ...editorial, eyebrow: event.target.value } })}
            />
          </Field>
          <Field label="Why it belongs in this story" id="block-spotlight-summary" hint={<Counter value={String(editorial.summary ?? '')} max={400} />}>
            <textarea
              id="block-spotlight-summary"
              rows={4}
              value={String(editorial.summary ?? '')}
              maxLength={400}
              onChange={(event) => update({ editorial: { ...editorial, summary: event.target.value } })}
            />
          </Field>
          <Field label="Button label" id="block-spotlight-cta">
            <input
              id="block-spotlight-cta"
              value={String(presentation.ctaLabel ?? '')}
              maxLength={40}
              placeholder="View Product"
              onChange={(event) => update({ presentation: { ...presentation, ctaLabel: event.target.value } })}
            />
          </Field>
          <Field label="Layout" id="block-spotlight-layout">
            <select
              id="block-spotlight-layout"
              value={String(presentation.layout ?? 'image-left')}
              onChange={(event) => update({ presentation: { ...presentation, layout: event.target.value } })}
            >
              <option value="image-left">Image left</option>
              <option value="image-right">Image right</option>
              <option value="stacked">Stacked</option>
            </select>
          </Field>
          <label className="studio-check">
            <input
              type="checkbox"
              checked={Boolean(presentation.showPrice ?? true)}
              onChange={(event) => update({ presentation: { ...presentation, showPrice: event.target.checked } })}
            />
            Show the live price
          </label>
          <label className="studio-check">
            <input
              type="checkbox"
              checked={Boolean(presentation.showAvailability ?? true)}
              onChange={(event) => update({ presentation: { ...presentation, showAvailability: event.target.checked } })}
            />
            Show availability
          </label>
        </>
      ) : null}

      {selected.type === 'workshopStory' ? (
        <>
          <Field label="Title" id="block-workshop-title">
            <input id="block-workshop-title" value={String(attrs.title ?? '')} maxLength={160} onChange={(event) => update({ title: event.target.value })} />
          </Field>
          <Field label="Process stage" id="block-workshop-stage">
            <select id="block-workshop-stage" value={String(attrs.processStage ?? '')} onChange={(event) => update({ processStage: event.target.value })}>
              <option value="">No stage</option>
              {bootstrap.processStages.map((stage) => (
                <option key={stage} value={stage}>{stage[0].toUpperCase() + stage.slice(1)}</option>
              ))}
            </select>
          </Field>
          <Field label="Story" id="block-workshop-body" hint={<Counter value={String(attrs.body ?? '')} max={4000} />}>
            <textarea id="block-workshop-body" rows={6} value={String(attrs.body ?? '')} maxLength={4000} onChange={(event) => update({ body: event.target.value })} />
          </Field>
          <WorkshopPhotos
            mediaIds={Array.isArray(attrs.mediaIds) ? (attrs.mediaIds as unknown[]).map(Number).filter((id) => id > 0) : []}
            onRemove={(id) => update({ mediaIds: ((attrs.mediaIds as unknown[]) ?? []).map(Number).filter((item) => item !== id) })}
          />
          <FileButton id="block-workshop-file" label="Add workshop photo" busy={uploading === 'block'} onFile={props.onUploadBlock} />
          <Field label="Caption" id="block-workshop-caption">
            <input id="block-workshop-caption" value={String(attrs.caption ?? '')} maxLength={300} onChange={(event) => update({ caption: event.target.value })} />
          </Field>
          <Field label="Credit" id="block-workshop-credit">
            <input id="block-workshop-credit" value={String(attrs.credit ?? '')} maxLength={140} onChange={(event) => update({ credit: event.target.value })} />
          </Field>
        </>
      ) : null}

      {selected.type === 'cta' ? (
        <>
          <Field label="Heading" id="block-cta-title">
            <input id="block-cta-title" value={String(attrs.title ?? '')} maxLength={120} onChange={(event) => update({ title: event.target.value })} />
          </Field>
          <Field label="Supporting line" id="block-cta-body">
            <textarea id="block-cta-body" rows={3} value={String(attrs.body ?? '')} maxLength={400} onChange={(event) => update({ body: event.target.value })} />
          </Field>
          <Field label="Button label" id="block-cta-button">
            <input id="block-cta-button" value={String(attrs.buttonText ?? '')} maxLength={40} onChange={(event) => update({ buttonText: event.target.value })} />
          </Field>
          <Field label="Button link" id="block-cta-url" hint="A site path such as /products, or a full https:// address.">
            <input
              id="block-cta-url"
              value={String(attrs.buttonUrl ?? '')}
              inputMode="url"
              spellCheck={false}
              onChange={(event) => update({ buttonUrl: event.target.value })}
            />
          </Field>
        </>
      ) : null}

      {selected.type === 'gallery' ? <GallerySettings {...props} /> : null}

      <fieldset className="studio-field">
        <legend>Block</legend>
        <div className="studio-row">
          <button type="button" className="studio-btn" disabled={selected.index === 0} onClick={() => props.onBlockAction('up')}>
            <Icon name="arrow-up" size={16} /> Move up
          </button>
          <button
            type="button"
            className="studio-btn"
            disabled={selected.index >= props.blockCount - 1}
            onClick={() => props.onBlockAction('down')}
          >
            <Icon name="arrow-down" size={16} /> Move down
          </button>
          <button type="button" className="studio-btn" onClick={() => props.onBlockAction('duplicate')}>
            <Icon name="copy" size={16} /> Duplicate
          </button>
          <button type="button" className="studio-btn is-danger" onClick={() => props.onBlockAction('delete')}>
            <Icon name="trash-2" size={16} /> Delete
          </button>
        </div>
        <p className="studio-hint">Undo with Ctrl+Z (⌘Z on Mac).</p>
      </fieldset>
    </>
  );
}

function frameOf(attrs: Record<string, unknown>, bootstrap: Bootstrap): Record<string, string> {
  const incoming = (attrs.frame ?? {}) as Record<string, string>;

  return { ...bootstrap.frame.defaults, ...incoming };
}

function StyleSettings(props: Props) {
  const selected = props.selected;
  if (!selected) {
    return <p className="studio-empty">Select a block to change its presentation. The same settings are used on the public article.</p>;
  }

  const frame = frameOf(selected.attrs, props.bootstrap);
  const presentation = (selected.attrs.presentation ?? {}) as Record<string, unknown>;
  const setFrame = (key: string, value: string) => {
    const next = { ...frame, [key]: value };
    const patch: Record<string, unknown> = { frame: next };
    if (selected.type === 'image' && key === 'width') {
      patch.layout = value === 'text' ? 'inline' : value;
    }
    if (selected.type === 'image' && key === 'align') {
      patch.alignment = value;
    }
    if ((selected.type === 'image' || selected.type === 'productSpotlight') && key === 'ratio') {
      patch.presentation = { ...presentation, imageRatio: value };
    }
    props.onUpdateBlock(patch);
  };

  return (
    <>
      <h2 className="studio-subhead">{BLOCK_NAMES[selected.type] ?? selected.type}</h2>
      <PresetField id="style-width" label="Block width" value={frame.width} options={props.bootstrap.frame.options.width} labels={{ text: 'Text', wide: 'Wide', full: 'Full' }} onChange={(value) => setFrame('width', value)} />
      <PresetField id="style-align" label="Alignment" value={frame.align} options={props.bootstrap.frame.options.align} onChange={(value) => setFrame('align', value)} />
      {selected.type === 'splitSection' ? (
        <Field label="Photograph side" id="style-split-layout">
          <select
            id="style-split-layout"
            value={String(selected.attrs.layout ?? 'image-right') === 'image-left' ? 'image-left' : 'image-right'}
            onChange={(event) => props.onUpdateBlock({ layout: event.target.value })}
          >
            <option value="image-right">Image on the right</option>
            <option value="image-left">Image on the left</option>
          </select>
        </Field>
      ) : null}
      {selected.type === 'productSpotlight' ? (
        <Field label="Product layout" id="style-product-layout">
          <select
            id="style-product-layout"
            value={String(presentation.layout ?? 'image-left')}
            onChange={(event) => props.onUpdateBlock({ presentation: { ...presentation, layout: event.target.value } })}
          >
            <option value="image-left">Image left</option>
            <option value="image-right">Image right</option>
            <option value="stacked">Stacked</option>
          </select>
        </Field>
      ) : null}
      <PresetField id="style-ratio" label="Image ratio" value={String(presentation.imageRatio ?? frame.ratio)} options={props.bootstrap.frame.options.ratio} onChange={(value) => setFrame('ratio', value)} />
      <PresetField id="style-scale" label="Typography scale" value={frame.scale} options={props.bootstrap.frame.options.scale} onChange={(value) => setFrame('scale', value)} />
      <PresetField id="style-spacing" label="Spacing" value={frame.spacing} options={props.bootstrap.frame.options.spacing} onChange={(value) => setFrame('spacing', value)} />
      <PresetField id="style-background" label="Background" value={frame.background} options={props.bootstrap.frame.options.background} onChange={(value) => setFrame('background', value)} />
      <PresetField id="style-radius" label="Corners" value={frame.radius} options={props.bootstrap.frame.options.radius} labels={{ none: 'Square', soft: 'Soft', round: 'Round' }} onChange={(value) => setFrame('radius', value)} />
    </>
  );
}

function AdvancedSettings(props: Props) {
  const selected = props.selected;
  if (!selected) {
    return <p className="studio-empty">Select a block to choose where it is visible.</p>;
  }
  const frame = frameOf(selected.attrs, props.bootstrap);

  return (
    <PresetField
      id="advanced-visibility"
      label="Show this block on"
      value={frame.visibility}
      options={props.bootstrap.frame.options.visibility}
      labels={{ all: 'Every screen', desktop: 'Desktop only', tablet: 'Tablet only', mobile: 'Mobile only' }}
      onChange={(value) => props.onUpdateBlock({ frame: { ...frame, visibility: value } })}
    />
  );
}

function PresetField({
  id,
  label,
  value,
  options,
  labels = {},
  onChange,
}: {
  id: string;
  label: string;
  value: string;
  options: string[];
  labels?: Record<string, string>;
  onChange: (value: string) => void;
}) {
  return (
    <Field label={label} id={id}>
      <select id={id} value={options.includes(value) ? value : options[0]} onChange={(event) => onChange(event.target.value)}>
        {options.map((option) => (
          <option key={option} value={option}>{labels[option] ?? option}</option>
        ))}
      </select>
    </Field>
  );
}

function GallerySettings({ selected, uploading, onUpdateBlock, onUploadBlock }: Props) {
  if (!selected) {
    return null;
  }
  const items = Array.isArray(selected.attrs.items) ? (selected.attrs.items as Array<Record<string, unknown>>) : [];
  const write = (next: Array<Record<string, unknown>>) => onUpdateBlock({ items: next });

  return (
    <>
      <Field label="Presentation" id="block-gallery-mode">
        <select id="block-gallery-mode" value={String(selected.attrs.mode ?? 'grid')} onChange={(event) => onUpdateBlock({ mode: event.target.value })}>
          <option value="grid">Grid</option>
          <option value="carousel">Carousel</option>
        </select>
      </Field>
      {items.map((item, index) => (
        <fieldset key={`${index}-${String(item.mediaId ?? '')}`} className="studio-field">
          <legend>Photograph {index + 1}</legend>
          <label className="studio-check">
            <input
              type="checkbox"
              checked={Boolean(item.decorative)}
              onChange={(event) => {
                const next = [...items];
                next[index] = { ...item, decorative: event.target.checked, alt: event.target.checked ? '' : item.alt };
                write(next);
              }}
            />
            Decorative
          </label>
          {!item.decorative ? (
            <Field label="Alt text" id={`gallery-alt-${index}`}>
              <input
                id={`gallery-alt-${index}`}
                value={String(item.alt ?? '')}
                maxLength={140}
                onChange={(event) => {
                  const next = [...items];
                  next[index] = { ...item, alt: event.target.value };
                  write(next);
                }}
              />
            </Field>
          ) : null}
          <Field label="Caption" id={`gallery-caption-${index}`}>
            <input
              id={`gallery-caption-${index}`}
              value={String(item.caption ?? '')}
              maxLength={300}
              onChange={(event) => {
                const next = [...items];
                next[index] = { ...item, caption: event.target.value };
                write(next);
              }}
            />
          </Field>
          <button type="button" className="studio-btn is-danger" onClick={() => write(items.filter((_item, itemIndex) => itemIndex !== index))}>
            Remove photograph
          </button>
        </fieldset>
      ))}
      <FileButton id="block-gallery-file" label="Add photograph" busy={uploading === 'block'} onFile={onUploadBlock} />
    </>
  );
}

function ImagePreview({ mediaId, previewUrl }: { mediaId: number; previewUrl: string }) {
  const url = useMediaUrl(mediaId) ?? (previewUrl !== '' ? previewUrl : null);

  return url ? <img src={url} alt="" className="studio-thumb" /> : <p className="studio-hint">No image yet. JPG, PNG or WEBP up to 6MB.</p>;
}

function ProductSummary({ productId, variantId }: { productId: number; variantId: number }) {
  const name = useProductName(productId);

  if (productId <= 0) {
    return <p className="studio-hint">No product chosen yet.</p>;
  }

  return (
    <p className="studio-hint">
      <strong>{name ?? `Product #${productId}`}</strong>
      {variantId > 0 ? ` · variant #${variantId}` : ' · default variant'}. Price and stock come from the live catalog.
    </p>
  );
}

function WorkshopPhotos({ mediaIds, onRemove }: { mediaIds: number[]; onRemove: (id: number) => void }) {
  if (mediaIds.length === 0) {
    return <p className="studio-hint">No workshop photos yet.</p>;
  }

  return (
    <ul className="studio-photo-list">
      {mediaIds.map((id, index) => (
        <WorkshopPhoto key={id} id={id} position={index + 1} onRemove={onRemove} />
      ))}
    </ul>
  );
}

function WorkshopPhoto({ id, position, onRemove }: { id: number; position: number; onRemove: (id: number) => void }) {
  const url = useMediaUrl(id);

  return (
    <li>
      {url ? <img src={url} alt="" /> : <span className="studio-hint">Photo {position}</span>}
      <button type="button" className="studio-icon-btn" aria-label={`Remove photo ${position}`} onClick={() => onRemove(id)}>
        <Icon name="x" />
      </button>
    </li>
  );
}

function ChecksPanel({ quality, qualityStale, onJump }: Props) {
  const order = { blocked: 0, attention: 1, ready: 2 } as const;
  const items = [...quality.items].sort((a, b) => order[a.state] - order[b.state]);

  return (
    <>
      <p className="studio-hint" role="status">
        {quality.blocked} blocking · {quality.attention} to review · {quality.ready} ready.
        {qualityStale ? ' Checks refresh after the next save.' : ''}
      </p>
      {items.map((item) => (
        <button key={item.id} type="button" className={`studio-issue is-${item.state}`} onClick={() => onJump(item)}>
          <span className="studio-issue__state">
            {item.state === 'blocked' ? 'Blocking' : item.state === 'attention' ? 'Review' : 'Ready'}
          </span>
          <strong>{item.title}</strong>
          <small>{item.explanation}</small>
        </button>
      ))}
    </>
  );
}
