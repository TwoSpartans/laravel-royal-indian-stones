export type BlockSnapshot = {
  blockId: string | null;
  type: string;
};

export type ProductChoice = {
  productId: number;
  variantId: number | null;
  visible: boolean;
  health: string;
};

export type ProductTargetResult =
  | { ok: true }
  | { ok: false; reason: 'cancelled' | 'missing' | 'not-product' | 'unavailable' };

/**
 * Decides whether a catalog choice may be written, and only onto the block
 * that opened the picker. Selection changes, moves, and other product blocks
 * are ignored because the decision is keyed by block id.
 */
export function applyProductToTarget(
  blocks: BlockSnapshot[],
  targetBlockId: string | null,
  choice: ProductChoice | null,
): ProductTargetResult {
  if (choice === null || targetBlockId === null || targetBlockId === '') {
    return { ok: false, reason: 'cancelled' };
  }

  if (!choice.visible || choice.health === 'blocked' || choice.productId <= 0) {
    return { ok: false, reason: 'unavailable' };
  }

  const target = blocks.find((block) => block.blockId === targetBlockId);
  if (!target) {
    return { ok: false, reason: 'missing' };
  }

  if (target.type !== 'productSpotlight') {
    return { ok: false, reason: 'not-product' };
  }

  return { ok: true };
}

export function productTargetMessage(reason: Exclude<ProductTargetResult, { ok: true }>['reason']): string {
  switch (reason) {
    case 'cancelled':
      return 'Product choice cancelled. Nothing was changed.';
    case 'missing':
      return 'That product block was removed. Nothing else was changed.';
    case 'not-product':
      return 'That block is no longer a product spotlight. Nothing else was changed.';
    case 'unavailable':
      return 'That product is not available for a spotlight.';
  }
}
