const DEFAULT_FRAME = {
  width: 'wide',
  align: 'left',
  ratio: '4:3',
  scale: 'editorial',
  spacing: 'standard',
  background: 'none',
  radius: 'soft',
  visibility: 'all',
} as const;

const ALLOWED = {
  width: ['text', 'wide', 'full'],
  align: ['left', 'center', 'right'],
  ratio: ['4:3', '1:1', '16:9', '3:2'],
  scale: ['editorial', 'standard', 'compact'],
  spacing: ['compact', 'standard', 'relaxed'],
  background: ['none', 'ivory', 'forest', 'sand', 'gold'],
  radius: ['none', 'soft', 'round'],
  visibility: ['all', 'desktop', 'tablet', 'mobile'],
} as const;

type FrameKey = keyof typeof DEFAULT_FRAME;

/**
 * The same class list BlockFrame::classes() writes on the public article.
 * A missing or unknown value uses the public default.
 */
export function frameClasses(frame: unknown): string {
  const incoming = frame !== null && typeof frame === 'object' ? frame as Record<string, unknown> : {};
  const pick = (key: FrameKey): string => {
    const value = String(incoming[key] ?? DEFAULT_FRAME[key]);

    return (ALLOWED[key] as readonly string[]).includes(value) ? value : DEFAULT_FRAME[key];
  };

  return [
    'rich-frame',
    `rich-frame-width-${pick('width')}`,
    `rich-frame-align-${pick('align')}`,
    `rich-frame-scale-${pick('scale')}`,
    `rich-frame-space-${pick('spacing')}`,
    `rich-frame-bg-${pick('background')}`,
    `rich-frame-radius-${pick('radius')}`,
    `rich-frame-ratio-${pick('ratio').replace(':', 'x')}`,
    `rich-frame-show-${pick('visibility')}`,
  ].join(' ');
}
