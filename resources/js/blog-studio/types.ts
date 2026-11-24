export type SaveState = 'saved' | 'unsaved' | 'saving' | 'offline' | 'conflict' | 'failed';

export type ContentSource = 'classic' | 'studio';

export type BlogStatus = 'draft' | 'pending_review' | 'scheduled' | 'schedule_failed' | 'published';

export type QualityItem = {
  id: string;
  state: 'ready' | 'attention' | 'blocked';
  section: string;
  title: string;
  explanation: string;
  action: string;
  blockIndex: number | null;
  blockId: string | null;
};

export type QualityReport = {
  ready: number;
  attention: number;
  blocked: number;
  items: QualityItem[];
};

export type BlockDefinition = {
  type: string;
  name: string;
  description: string;
  group: string;
  icon: string;
  nodeVersion: number;
};

export type StudioBlog = {
  id: number;
  title: string;
  slug: string;
  excerpt: string;
  status: BlogStatus;
  contentSource: ContentSource;
  isLive: boolean;
  hasDraftRevision: boolean;
  publishedAt: string | null;
  publishedAtInput: string;
  publishedAtLabel: string;
  scheduleFailureReason: string;
  savedAt: string | null;
  lockVersion: number;
  schemaVersion: number;
  featuredMediaId: number | null;
  featuredImageUrl: string | null;
  featuredImageAlt: string;
  metaTitle: string;
  metaDescription: string;
  tags: string[];
  categoryIds: number[];
  previewUrl: string | null;
  publicUrl: string;
};

export type PublishingStatus = {
  status: BlogStatus;
  isLive: boolean;
  hasDraftRevision: boolean;
  publishedAt: string | null;
  publishedAtInput: string;
  publishedAtLabel: string;
  scheduleFailureReason: string;
  version: number;
};

export type StudioCategory = {
  id: number;
  name: string;
  slug: string;
  isActive: boolean;
};

export type ProductSearchItem = {
  id: number;
  name: string;
  slug: string;
  imageUrl: string | null;
  health: 'ready' | 'attention' | 'blocked' | string;
  reason: string | null;
  visible: boolean;
  variants: Array<{ id: number; label: string; hasPrice: boolean }>;
};

export type ProductSearchPage = {
  data: ProductSearchItem[];
  meta: { current_page: number; last_page: number };
};

export type StudioMedia = {
  id: number;
  url: string;
  width: number | null;
  height: number | null;
};

export type ExcerptRules = {
  min: number;
  max: number;
  messages: { empty: string; short: string; long: string };
};

export type ConversionSummary = {
  summary: { blocks: number; notes: string[] };
  hasPreviousStudioVersion: boolean;
};

export type Bootstrap = {
  blog: StudioBlog;
  conversion: ConversionSummary | null;
  document: Record<string, unknown>;
  quality: QualityReport;
  media?: StudioMedia[];
  products?: Array<{ id: number; name: string }>;
  categories: StudioCategory[];
  blocks: BlockDefinition[];
  templates: Array<{ id: string; name: string; description: string; document: { type: string; schemaVersion: number; content: Array<Record<string, unknown>> } }>;
  frame: {
    defaults: Record<string, string>;
    options: Record<string, string[]>;
  };
  processStages: string[];
  excerptRules: ExcerptRules;
  timezone: { label: string; nowInput: string };
  permissions: {
    publish: boolean;
    schedule: boolean;
    unpublish: boolean;
    submitForReview: boolean;
    convert: boolean;
    exportJson: boolean;
  };
  routes: {
    save: string;
    preview: string;
    publish: string;
    schedule: string;
    cancelSchedule: string;
    submit: string;
    upload: string;
    products: string;
    convert: string;
    back: string;
    classic: string;
    studio: string;
    productCards: string;
  };
  csrf: string;
  brand: { primary: string; accent: string; secondary: string };
};

export type ArticleMetadata = {
  title: string;
  slug: string;
  excerpt: string;
  metaTitle: string;
  metaDescription: string;
  featuredMediaId: number | null;
  removeFeaturedImage: boolean;
  featuredImageAlt: string;
  tags: string[];
  categoryIds: number[];
};

export type OutlineItem = {
  index: number;
  type: string;
  label: string;
  level?: number;
  warning: boolean;
  children: Array<{ label: string; warning: boolean }>;
};

export type RequestError = Error & {
  status?: number;
  offline?: boolean;
  payload?: {
    message?: string;
    conflict?: boolean;
    reason?: string;
    serverVersion?: number;
    errors?: Record<string, string[]>;
    quality?: QualityReport;
    problems?: string[];
  };
};
