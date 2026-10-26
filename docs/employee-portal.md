# Royal Indian Stones staff portal

The public catalogue and the content studio stay where they are. Staff work lives at `/portal`, on the same Laravel application, database, and Hostinger deploy. Portal pages send `X-Robots-Tag: noindex` and a `noindex, nofollow` meta tag. They are not in the sitemap. `robots.txt` is unchanged, matching the existing studio approach.

## How it fits the current stack

- Laravel 13, PHP 8.3, Blade, the existing Bootstrap icons, and a dedicated stylesheet at `public/assets/css/portal.css`. No new Composer or npm packages.
- MySQL in the application environment. Tests use the existing SQLite memory database.
- Database sessions, already configured. Sign-in regenerates the session. Cookies stay HTTP-only.
- The content studio remains `/admin` and still requires `is_site_admin`. A staff account does not enter the studio unless that flag is set.
- Mail uses the existing mailer. With `MAIL_MAILER=log`, invitation and decision messages are written to the log. Production needs a real mailer before staff can rely on email.
- Private files (identity documents, receipts, task files) go to `storage/app/portal`, which is not published by `storage:link` and is gitignored. The deploy script’s database dump does not copy this directory. Back it up with the database, and keep `APP_KEY` with the backup or the encrypted fields cannot be read.
- The scheduler runs `portal:prune-retention` daily at 02:30 IST. The server needs `php artisan schedule:run` every minute. Until that cron exists, run the command by hand.

## Accounts and permissions

Manager and HR are the same level. The employer is the company admin. An employee is the person those accounts manage.

| Action | Employer (admin) | Manager / HR | Employee |
| --- | --- | --- | --- |
| Deactivate a manager or HR account | Yes | No | No |
| Invite people and open the full roster | Yes | Yes | Own record only |
| Salary, identity numbers, workforce export | Yes | Yes | Own published payslip and own documents |
| Edit or delete a saved task | Yes | No | No |
| Assign a task | Anyone | Self or an employee | Self only |
| Record own attendance and leave | No | Yes | Yes |
| Approve leave, expenses, corrections, and overtime | Yes | Yes | No |
| Office networks, devices, and access review | Yes | Yes | No |

The employer uses an authenticator after the password, except the website admin while single-admin mode is on. An account stored as Admin / HR also uses an authenticator. A manager and an employee use a password. Deactivated people, and people who have not accepted an invitation, get the same sign-in error as a wrong password.

A registered browser cookie and an approved IP are signals. Neither proves the person is physically on site. Remote or field work is labelled “approved remote or field work”, not “approved office network”.

## Network classification

At sign-in, authenticator checks, password reset, invitation acceptance, and attendance punches the portal stores the account, session id, time, user agent, and the client address it decided to trust.

`PORTAL_TRUSTED_PROXIES` is a comma-separated list of proxy IPs or CIDR ranges. It does not accept `*`. If the direct connection is not on that list, `X-Forwarded-For` is stored for audit and ignored. `TRUSTED_PROXIES` remains the separate setting used for HTTPS behind the host. Set both to the real proxy when the origin is not reachable directly. Leave the portal list empty until that proxy address is known.

Results shown to HR:

- approved office network
- outside approved network
- unable to verify
- approved remote or field work

HR can record a review. The original result is not rewritten.

## Attendance

Clock in, clock out, and breaks are explicit. Signing in never creates a punch. Late means the first clock-in is after the shift start plus the grace period, in the location timezone. The application timezone is already `Asia/Kolkata`. Each punch also stores the location timezone used at that moment. Weekly offs and holidays are not absences. A missed in or out can be corrected; a manager or HR approval replaces that day’s punches and keeps the correction flag.

## Payroll boundary

Salary components and payslip summaries are encrypted with `APP_KEY`. Preparing a month stores attendance, overtime, and approved adjustments. While `PORTAL_PAYROLL_CALCULATIONS` and `PORTAL_PAYROLL_REVIEW_CONFIRMED` are false, the payslip status is `review_required`, the net is empty, and the page says calculations are withheld. There is no status called sent. Expense reimbursement can be marked “arranged outside this portal”, with a note that it is not a bank confirmation. Salary payout is refused until a real provider exists.

Provident fund, ESI, professional tax, and income-tax deduction are described in `config/portal.php` and are not calculated.

## What must be decided before it is turned on

- A mail provider, so invitations and decisions leave the log.
- The Hostinger or CDN proxy addresses, before forwarded IPs are trusted.
- A payroll professional, before calculations or any statutory rule is enabled.
- A payment provider with a confirmed payout response, before any salary or reimbursement can be called paid. RazorpayX or a bank feed is not connected.
- FIDO2 passkeys. The portal uses authenticator codes for privileged roles and an HTTP-only device secret approved by HR. A passkey needs a WebAuthn package and should be added only after that dependency is approved.
- A desktop agent, only if MAC addresses are still required. The browser cannot read a MAC address. An agent would need a managed install, a named operating system, an employee notice, a private API, and a retention period. The portal does not wait on that.
- Cron for `schedule:run`, and a backup that includes `storage/app/portal` plus `APP_KEY`.
- Leave year, holiday dates, and retention. The defaults are a calendar year, the 2026 holidays seeded as editable dates, 24 months for access events, and 7 years for audit, attendance, and identity files. The prune command deletes only expired access events. It reports the others and does not delete them.

## Page map

- `/portal/login`, `/portal/forgot-password`, `/portal/reset-password/{token}`, `/portal/invitation/{token}`, `/portal/privacy`
- `/portal/mfa/setup`, `/portal/mfa/challenge`, `/portal/notice`
- `/portal` dashboard
- `/portal/people`, create, show, edit
- `/portal/attendance`
- `/portal/leave`
- `/portal/tasks` and task detail
- `/portal/expenses` and claim detail
- `/portal/payroll` and payslip detail
- `/portal/reports`
- `/portal/workplace` for networks, devices, remote work, holidays, leave types, and access review
- `/portal/account`, `/portal/notifications`

The public footer and the studio navigation link to the staff sign-in page.

## Setup

```bash
php artisan migrate
php artisan db:seed --class=PortalFoundationSeeder
```

Set these in `.env`, then run `php artisan portal:bootstrap-employer`. Add `--with-site-admin` only if this person should also open the content studio.

```
PORTAL_EMPLOYER_NAME=
PORTAL_EMPLOYER_EMAIL=
PORTAL_EMPLOYER_PASSWORD=
PORTAL_TRUSTED_PROXIES=
PORTAL_PAYROLL_CALCULATIONS=false
PORTAL_PAYROLL_REVIEW_CONFIRMED=false
PORTAL_PAYMENT_PROVIDER=
```

The password must be at least 8 characters. The command does not print it. The employer then signs in at `/portal/login` and enrolls an authenticator. Invite everyone else from People. They set a password from the one-time link.

On production, set `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true` in `.env`, then rebuild the config cache. The current `.env` value is left as-is so existing studio sessions are not invalidated by this change.

## Tests

`php artisan test --compact` covers the portal together with the existing site. The portal tests check permissions, invitations, office-network classification, forwarded headers, attendance corrections, leave and expense approval, and the payroll guard.
