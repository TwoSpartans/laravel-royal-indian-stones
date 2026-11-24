import { MediaCacheProvider } from './mediaCache';
import { StudioWorkspace } from './StudioWorkspace';
import type { Bootstrap } from './types';

export function BlogStudioApp({ bootstrap }: { bootstrap: Bootstrap }) {
  return (
    <MediaCacheProvider initial={bootstrap.media ?? []} products={bootstrap.products ?? []}>
      <StudioWorkspace bootstrap={bootstrap} />
    </MediaCacheProvider>
  );
}
