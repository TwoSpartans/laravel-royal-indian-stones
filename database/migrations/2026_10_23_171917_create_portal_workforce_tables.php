<?php

use Database\Seeders\PortalFoundationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('work_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('kind');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->json('weekly_off');
            $table->timestamps();
        });

        Schema::create('office_networks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_location_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('cidr');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('holiday_on');
            $table->timestamps();
            $table->index('holiday_on');
        });

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_code')->unique();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_title');
            $table->string('employment_status')->default('invited')->index();
            $table->date('joined_on')->nullable();
            $table->date('probation_ends_on')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('identity_document_label')->nullable();
            $table->text('identity_document_number')->nullable();
            $table->date('offboarded_on')->nullable();
            $table->timestamp('data_notice_acknowledged_at')->nullable();
            $table->string('data_notice_version')->nullable();
            $table->timestamps();
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship');
            $table->string('phone');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('title');
            $table->string('path');
            $table->string('original_name');
            $table->date('expires_on')->nullable()->index();
            $table->date('retention_until')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('identity_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('onboarding_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phase');
            $table->string('label');
            $table->boolean('is_complete')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employment_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('summary');
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        Schema::create('portal_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('portal_role');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('registered_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('secret_hash', 64)->unique();
            $table->text('user_agent')->nullable();
            $table->string('status')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('remote_work_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason');
            $table->string('status')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->string('event_type')->index();
            $table->string('attempted_email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('forwarded_for')->nullable();
            $table->boolean('forwarded_header_accepted')->default(false);
            $table->text('user_agent')->nullable();
            $table->string('classification')->index();
            $table->string('classification_label');
            $table->text('classification_reason');
            $table->foreignId('office_network_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('remote_work_authorization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registered_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_status')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->string('timezone');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_decision')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('requested_in')->nullable();
            $table->string('requested_out')->nullable();
            $table->string('reason');
            $table->string('status')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'work_date']);
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('punch_type');
            $table->timestamp('punched_at');
            $table->string('timezone');
            $table->foreignId('access_event_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_correction')->default(false);
            $table->foreignId('correction_id')->nullable()->constrained('attendance_corrections')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'punched_at']);
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->unsignedSmallInteger('minutes');
            $table->string('reason');
            $table->string('status')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_paid')->default(true);
            $table->decimal('annual_allowance', 5, 1)->default(0);
            $table->boolean('needs_hr_approval')->default(false);
            $table->decimal('hr_threshold_days', 4, 1)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated', 5, 1)->default(0);
            $table->decimal('used', 5, 1)->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('days', 4, 1);
            $table->string('reason');
            $table->string('status')->index();
            $table->foreignId('manager_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_decided_at')->nullable();
            $table->foreignId('hr_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
        });

        Schema::create('work_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->index();
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on')->nullable()->index();
            $table->string('status')->index();
            $table->timestamps();
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->timestamps();
        });

        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->unsignedInteger('amount_paise');
            $table->date('incurred_on');
            $table->string('description');
            $table->string('status')->index();
            $table->string('receipt_path');
            $table->string('receipt_name');
            $table->foreignId('manager_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_decided_at')->nullable();
            $table->foreignId('hr_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->string('payment_state')->default('none');
            $table->string('payment_note')->nullable();
            $table->foreignId('payment_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_recorded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('effective_on');
            $table->text('components');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'effective_on']);
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7);
            $table->integer('amount_paise');
            $table->string('reason');
            $table->string('status')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'period']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7);
            $table->string('status');
            $table->text('summary');
            $table->text('disclaimer');
            $table->string('payment_state')->default('none');
            $table->string('payment_note')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'period']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        (new PortalFoundationSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('expense_claims');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('work_tasks');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('access_events');
        Schema::dropIfExists('remote_work_authorizations');
        Schema::dropIfExists('registered_devices');
        Schema::dropIfExists('portal_invitations');
        Schema::dropIfExists('employment_changes');
        Schema::dropIfExists('onboarding_items');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('office_networks');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('work_locations');
        Schema::dropIfExists('departments');
    }
};
