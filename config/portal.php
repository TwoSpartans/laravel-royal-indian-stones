<?php

return [

    /*
    | Employer bootstrap. The password is never given a default.
    */
    'employer_name' => env('PORTAL_EMPLOYER_NAME', 'Employer'),
    'employer_email' => env('PORTAL_EMPLOYER_EMAIL'),
    'employer_password' => env('PORTAL_EMPLOYER_PASSWORD'),

    /*
    | Explicit proxy addresses for staff IP checks. "*" is ignored.
    | Leave empty to use only the direct connection address.
    */
    'trusted_proxies' => env('PORTAL_TRUSTED_PROXIES', ''),

    'invitation_days' => 7,

    'notice_version' => '2026-10-23',

    'session_lifetime_minutes' => (int) env('SESSION_LIFETIME', 120),

    'device_cookie' => 'ris_device',

    'retention' => [
        'access_events_months' => 24,
        'audit_logs_months' => 84,
        'attendance_months' => 84,
        'payslips_months' => 96,
        'identity_documents_months_after_offboarding' => 84,
        'receipts_months' => 84,
    ],

    /*
    | Statutory and payout features stay off until a qualified reviewer
    | and a real payment provider are both in place.
    */
    'payroll' => [
        'calculations_enabled' => (bool) env('PORTAL_PAYROLL_CALCULATIONS', false),
        'professional_review_confirmed' => (bool) env('PORTAL_PAYROLL_REVIEW_CONFIRMED', false),
        'payment_provider' => env('PORTAL_PAYMENT_PROVIDER'),
        'statutory' => [
            'provident_fund' => 'Disabled. Employee and employer PF rates need a payroll professional before use.',
            'esi' => 'Disabled. ESI wages and contribution rates are not calculated.',
            'professional_tax' => 'Disabled. State professional tax, including Rajasthan, is not calculated.',
            'tax_deducted_at_source' => 'Disabled. Income-tax deduction is not calculated or filed from this portal.',
        ],
    ],

    'onboarding_items' => [
        'Appointment letter filed',
        'Identity document received',
        'Emergency contact confirmed',
        'Yard or office safety briefing',
        'Shift and weekly off explained',
        'Bank details received for payroll records',
    ],

    'offboarding_items' => [
        'Company access and devices revoked',
        'Final attendance and leave checked',
        'Expense claims cleared or rejected',
        'Tools, samples, and documents returned',
        'Experience or relieving letter prepared',
    ],

    'task_categories' => [
        'sales_inquiry' => 'Sales inquiry',
        'stone_sourcing' => 'Stone sourcing',
        'processing' => 'Processing',
        'quality_inspection' => 'Quality inspection',
        'packaging' => 'Packaging',
        'dispatch' => 'Dispatch',
        'export_documentation' => 'Export documentation',
    ],

    'expense_categories' => [
        'yard_supplies' => 'Yard supplies',
        'sample_freight' => 'Sample freight',
        'travel' => 'Travel',
        'buyer_visit' => 'Buyer visit',
        'export_paperwork' => 'Export paperwork',
        'other' => 'Other',
    ],

];
