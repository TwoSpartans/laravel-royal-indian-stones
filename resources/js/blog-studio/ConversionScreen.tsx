import { useState } from 'react';
import { convertArticle } from './api';
import { Icon } from './Icon';
import type { Bootstrap, RequestError } from './types';

export function ConversionScreen({ bootstrap }: { bootstrap: Bootstrap }) {
  const conversion = bootstrap.conversion;
  const [source, setSource] = useState<'classic' | 'previous'>('classic');
  const [confirmed, setConfirmed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const convert = async () => {
    setBusy(true);
    setError('');
    try {
      const result = await convertArticle(bootstrap, 'studio', source === 'previous');
      window.location.assign(result.redirect);
    } catch (cause) {
      setError((cause as RequestError).message || 'The article could not be converted.');
      setBusy(false);
    }
  };

  return (
    <div className="studio-convert">
      <a href={bootstrap.routes.back} className="studio-link">
        <Icon name="arrow-left" /> Back to posts
      </a>
      <main className="studio-convert__card" aria-labelledby="studio-convert-title">
        <p className="studio-eyebrow">Classic editor article</p>
        <h1 id="studio-convert-title" className="font-brand">{bootstrap.blog.title || 'Untitled article'}</h1>
        <p>
          This article is written in the Classic editor, which is what readers see today. Blog Studio can build a
          Studio version from it. The Classic body is kept unchanged, so you can switch back from the Classic editor
          at any time.
        </p>

        {conversion ? (
          <section className="studio-stack" aria-labelledby="studio-convert-summary">
            <h2 id="studio-convert-summary" className="studio-subhead">What the conversion will do</h2>
            <p>
              The Studio version will have {conversion.summary.blocks} {conversion.summary.blocks === 1 ? 'block' : 'blocks'},
              built from the Classic rows.
            </p>
            {conversion.summary.notes.length > 0 ? (
              <ul className="studio-notes">
                {conversion.summary.notes.map((note) => <li key={note}>{note}</li>)}
              </ul>
            ) : null}
          </section>
        ) : null}

        {conversion?.hasPreviousStudioVersion ? (
          <fieldset className="studio-stack">
            <legend className="studio-subhead">This article also has an earlier Studio version</legend>
            <label className="studio-check">
              <input type="radio" name="convert-source" checked={source === 'classic'} onChange={() => setSource('classic')} />
              Build a new Studio version from the current Classic body (replaces the earlier Studio version)
            </label>
            <label className="studio-check">
              <input type="radio" name="convert-source" checked={source === 'previous'} onChange={() => setSource('previous')} />
              Go back to the earlier Studio version
            </label>
          </fieldset>
        ) : null}

        {bootstrap.permissions.convert ? (
          <>
            <label className="studio-check">
              <input type="checkbox" checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} />
              {bootstrap.blog.isLive
                ? 'I understand readers will see the Studio version as soon as I convert this live article.'
                : 'I understand the article will be edited in Blog Studio from now on.'}
            </label>
            {error !== '' ? <p className="studio-error" role="alert">{error}</p> : null}
            <div className="studio-row">
              <button type="button" className="studio-btn studio-btn-primary" disabled={!confirmed || busy} onClick={() => void convert()}>
                {busy ? 'Converting…' : 'Convert to Blog Studio'}
              </button>
              <a href={bootstrap.routes.classic} className="studio-btn">Keep using the Classic editor</a>
            </div>
          </>
        ) : (
          <p className="studio-hint">You can view this article in the Classic editor, but you cannot convert it.</p>
        )}
      </main>
    </div>
  );
}
