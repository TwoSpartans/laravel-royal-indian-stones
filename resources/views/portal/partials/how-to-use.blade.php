@php
  $helpUser = auth()->user();
  $helpRole = $helpUser?->portal_role;
  $helpEmployer = $helpRole?->isCompanyAdmin() === true;
  $helpOversight = $helpRole?->canAdminister() === true;

  $pageHelp = match (true) {
      request()->routeIs('portal.home') => $helpEmployer
          ? 'This is the yard summary: who is in, who is away, and what is waiting. Your own shift is not listed here.'
          : ($helpOversight
              ? 'This is today’s roster and the queue of decisions. Record your own shift from Attendance.'
              : 'This is your day. Open Attendance for the shift and Leave when you need time away.'),
      request()->routeIs('portal.attendance.*') => $helpEmployer
          ? 'Review the roster, then approve corrections and overtime. This account is not on the list.'
          : ($helpOversight
              ? 'See who is in, then approve or reject a correction or overtime request. You cannot decide your own.'
              : 'Record the start of the shift, breaks, and the end of the shift. A missed punch can be sent for approval.'),
      request()->routeIs('portal.leave.*') => $helpEmployer
          ? 'Approve or reject requests from the team. This account does not apply.'
          : ($helpOversight
              ? 'Approve or reject a request, or submit your own. Weekly offs and holidays are not counted.'
              : 'Check the balance, then submit the dates. Weekly offs and holidays are not counted.'),
      request()->routeIs('portal.tasks.*') => $helpOversight
          ? 'Create work for sales, sourcing, processing, inspection, packing, dispatch, or export, and follow it until it is done.'
          : 'Open a task assigned to you, update the status, and add a note or file.',
      request()->routeIs('portal.expenses.*') => $helpOversight
          ? 'Review a claim and its receipt. Marking it arranged outside this portal is not a bank confirmation.'
          : 'Submit a claim with the receipt. It waits for a manager.',
      request()->routeIs('portal.people.*') => 'Invite a person as Employee or Manager / HR, and tick the sections an employee may open.',
      request()->routeIs('portal.reports.*') => 'Choose a report and a date range, then export. An attendance file starts with one line per person and shows settled hours. The employer stays off the roster.',
      request()->routeIs('portal.payroll.*') => 'Prepare records for review. Salary calculations stay off until a reviewer confirms them, and nothing here is marked paid.',
      request()->routeIs('portal.workplace.*') => 'Save an office network, approve a browser, set holidays and the leave balance, and review sign-ins from outside the network. A matching address does not prove someone is on site.',
      request()->routeIs('portal.account') => 'Update the password for this account. Signing in still does not mark anyone present.',
      request()->routeIs('portal.notifications') => 'Decisions and requests land here. Open one to mark it read.',
      request()->routeIs('portal.login') => 'Sign in with the work email and password you were given. There is no public registration.',
      request()->routeIs('portal.password.*') => 'Use the work email. The reset link expires, and a deactivated account cannot set a new password.',
      request()->routeIs('portal.invitation.*') => 'Set a password of at least 8 characters. The link works once.',
      request()->routeIs('portal.mfa.*') => 'The employer and an HR account confirm an authenticator code after the password. A manager signs in with the password. The employer who also runs the website skips this when single-admin mode is on.',
      request()->routeIs('portal.notice', 'portal.privacy') => 'Read the notice, then accept it. Attendance and the other work sections stay closed until you do.',
      default => 'Use the menu for attendance, leave, tasks, and expenses. On a phone, use the bar at the bottom.',
  };
@endphp
<button type="button" class="help-open" data-help-open aria-haspopup="dialog" aria-controls="how-to-use">How to use</button>
<dialog class="help-dialog" id="how-to-use" aria-labelledby="how-to-use-title">
  <div class="help-dialog-head">
    <h2 id="how-to-use-title">How to use</h2>
    <button type="button" class="help-close" data-help-close aria-label="Close">×</button>
  </div>
  <div class="help-dialog-body">
    <p class="eyebrow">This page</p>
    <p>{{ $pageHelp }}</p>
    @if ($helpUser === null)
      <h3>Who does what</h3>
      <ul>
        <li>The employer is the company admin. That account manages the company and does not record attendance or leave.</li>
        <li>Manager and HR are the same level. They look after employees.</li>
        <li>An employee sees only the sections opened for that account.</li>
        <li>Signing in never marks anyone present.</li>
      </ul>
    @elseif ($helpEmployer)
      <h3>Your role</h3>
      <p>You are the employer. You run the roster, the workplace, and the website studio.</p>
      <ul>
        <li>Invite people from People. A manager and an HR account manage the full roster in the same way.</li>
        <li>For an employee, tick only the sections they should open.</li>
        <li>This account stays off attendance and leave. The people you manage record those.</li>
        <li>Content studio, in the header, opens the website editor with this same sign-in.</li>
        <li>Payroll figures stay withheld until a reviewer turns calculations on. A salary is never marked paid here.</li>
      </ul>
    @elseif ($helpOversight)
      <h3>Your role</h3>
      <p>Manager and HR are the same level.</p>
      <ul>
        <li>Open People, Reports, Payroll, and Workplace for the whole roster.</li>
        <li>Tick the sections each employee may open. They cannot reach the others.</li>
        <li>Approve leave, expenses, corrections, and overtime. You cannot approve your own request.</li>
        <li>Record your own attendance and leave. The employer does not.</li>
        <li>Only the employer can deactivate a manager or an HR account.</li>
      </ul>
    @else
      <h3>Your role</h3>
      <p>You are an employee. The menu is the work opened for you.</p>
      <ul>
        <li>If a section is missing, it was not opened on your account.</li>
        <li>Attendance is separate from sign-in. Record the shift there.</li>
        <li>Leave shows the balance for this year. Weekly offs and holidays are not counted.</li>
        <li>A correction, overtime request, or expense waits for a manager.</li>
      </ul>
    @endif
  </div>
  <div class="help-dialog-foot">
    <button type="button" class="btn-secondary" data-help-close>Close</button>
  </div>
</dialog>
<script>
(() => {
  const dialog = document.getElementById('how-to-use');
  if (!dialog || dialog.dataset.ready === '1') {
    return;
  }
  dialog.dataset.ready = '1';
  document.querySelectorAll('[data-help-open]').forEach((button) => {
    button.addEventListener('click', () => dialog.showModal());
  });
  dialog.querySelectorAll('[data-help-close]').forEach((button) => {
    button.addEventListener('click', () => dialog.close());
  });
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) {
      dialog.close();
    }
  });
})();
</script>
