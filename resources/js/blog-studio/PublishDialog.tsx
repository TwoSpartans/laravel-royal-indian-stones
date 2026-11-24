import { useState } from 'react';
import { publicArticlePath } from './api';
import { Modal } from './Modal';
import type { Bootstrap, PublishingStatus, QualityItem, QualityReport } from './types';

export type PublishAction = 'publish' | 'schedule' | 'cancel' | 'submit';

type Props = {
  bootstrap: Bootstrap;
  status: PublishingStatus;
  quality: QualityReport;
  slug: string;
  productCount: number;
  busy: PublishAction | null;
  error: string;
  problems: string[];
  onPublishNow: () => void;
  onSchedule: (publishAt: string) => void;
  onCancelSchedule: () => void;
  onSubmit: () => void;
  onJump: (item: QualityItem) => void;
  onClose: () => void;
};

export default function PublishDialog(props: Props) {
  const { bootstrap, status, quality, busy } = props;
  const { publish: canPublish, schedule: canSchedule, submitForReview } = bootstrap.permissions;
  const canSubmit = submitForReview && !canPublish;
  const [publishAt, setPublishAt] = useState(status.status === 'scheduled' ? status.publishedAtInput : '');
  const blocked = quality.items.filter((item) => item.state === 'blocked');
  const isBlocked = blocked.length > 0;
  const title = canPublish ? (status.isLive ? 'Live article' : 'Publish or schedule') : 'Submit for review';

  return (
    <Modal title={title} labelId="studio-publish-title" onClose={props.onClose}>
      <div className="studio-stack">
        <dl className="studio-summary">
          <div><dt>Address</dt><dd>{publicArticlePath(bootstrap.blog.publicUrl, props.slug)}</dd></div>
          <div><dt>Products linked</dt><dd>{props.productCount}</dd></div>
          <div><dt>Checks</dt><dd>{quality.ready} ready · {quality.attention} to review · {quality.blocked} blocking</dd></div>
        </dl>

        {isBlocked ? (
          <div className="studio-callout is-blocked">
            <p className="font-semibold">Fix these before {canPublish ? 'publishing or scheduling' : 'submitting'}:</p>
            <ul className="studio-stack is-tight">
              {blocked.map((item) => (
                <li key={item.id}>
                  <button type="button" className="studio-link-btn" onClick={() => props.onJump(item)}>
                    {item.title}: {item.explanation}
                  </button>
                </li>
              ))}
            </ul>
          </div>
        ) : (
          <p className="studio-callout is-ready">Every required check passes.</p>
        )}

        {props.problems.length > 0 ? (
          <ul className="studio-error" role="alert">
            {props.problems.map((problem) => <li key={problem}>{problem}</li>)}
          </ul>
        ) : props.error !== '' ? (
          <p className="studio-error" role="alert">{props.error}</p>
        ) : null}

        {status.status === 'schedule_failed' ? (
          <div className="studio-callout is-blocked">
            <p className="font-semibold">This article missed its schedule and was not published.</p>
            <p>{status.scheduleFailureReason || 'It failed the publishing checks. It will not be retried on its own.'}</p>
            <p>Fix the checks below, then publish it now or choose a new time.</p>
          </div>
        ) : null}

        {canPublish && status.isLive ? (
          <section className="studio-stack" aria-labelledby="republish-heading">
            <h3 id="republish-heading" className="studio-subhead">Republish</h3>
            <p className="studio-hint">
              Live since {status.publishedAtLabel} {bootstrap.timezone.label}. Readers still see that version.
              Republishing replaces it with this draft.
            </p>
            <button
              type="button"
              className="studio-btn studio-btn-primary"
              disabled={isBlocked || busy !== null}
              onClick={props.onPublishNow}
            >
              {busy === 'publish' ? 'Republishing…' : 'Republish'}
            </button>
          </section>
        ) : null}

        {canPublish && !status.isLive ? (
          <section className="studio-stack" aria-labelledby="publish-now-heading">
            <h3 id="publish-now-heading" className="studio-subhead">Publish now</h3>
            <button
              type="button"
              className="studio-btn studio-btn-primary"
              disabled={isBlocked || busy !== null}
              onClick={props.onPublishNow}
            >
              {busy === 'publish' ? 'Publishing…' : 'Publish now'}
            </button>
          </section>
        ) : null}

        {canSchedule && !status.isLive ? (
          <section className="studio-stack" aria-labelledby="schedule-heading">
            <h3 id="schedule-heading" className="studio-subhead">
              {status.status === 'scheduled' ? 'Change the schedule' : 'Schedule'}
            </h3>
            {status.status === 'scheduled' ? (
              <p className="studio-hint">Scheduled for {status.publishedAtLabel} {bootstrap.timezone.label}.</p>
            ) : null}
            <form
              className="studio-row"
              onSubmit={(event) => {
                event.preventDefault();
                props.onSchedule(publishAt);
              }}
            >
              <label className="studio-field">
                <span>Publish on ({bootstrap.timezone.label})</span>
                <input
                  type="datetime-local"
                  required
                  min={bootstrap.timezone.nowInput}
                  value={publishAt}
                  onChange={(event) => setPublishAt(event.target.value)}
                />
              </label>
              <button type="submit" className="studio-btn" disabled={isBlocked || busy !== null || publishAt === ''}>
                {busy === 'schedule' ? 'Scheduling…' : status.status === 'scheduled' ? 'Update schedule' : 'Schedule'}
              </button>
            </form>
            {status.status === 'scheduled' ? (
              <button type="button" className="studio-btn" disabled={busy !== null} onClick={props.onCancelSchedule}>
                {busy === 'cancel' ? 'Cancelling…' : 'Cancel schedule (back to draft)'}
              </button>
            ) : null}
          </section>
        ) : null}

        {canSubmit ? (
          status.status === 'pending_review' ? (
            <p className="studio-hint">This article is waiting for a Super Admin to review it.</p>
          ) : (
            <button
              type="button"
              className="studio-btn studio-btn-primary"
              disabled={isBlocked || busy !== null}
              onClick={props.onSubmit}
            >
              {busy === 'submit' ? 'Submitting…' : 'Submit for review'}
            </button>
          )
        ) : null}
      </div>
    </Modal>
  );
}
