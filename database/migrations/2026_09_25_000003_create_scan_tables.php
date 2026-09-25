<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('trigger', 16); // manual | scheduled | onboarding
            $table->string('status', 16)->default('queued'); // queued | running | done | failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('totals')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('threshold_days')->default(90);
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('zoom_seats_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 16)->default('plan_usage'); // plan_usage | user_summary
            $table->json('plan_names')->nullable();
            $table->unsignedInteger('purchased_seats')->nullable();
            $table->unsignedInteger('used_seats')->nullable();
            $table->unsignedInteger('unassigned_seats')->nullable();
            $table->unsignedInteger('pending_seats')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });

        Schema::create('zoom_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('member_key'); // zoom user id, or "email:x" for pending users
            $table->string('zoom_user_id')->nullable();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status', 16); // active | inactive | pending
            $table->unsignedSmallInteger('type'); // 1 basic, 2 licensed, 4 unassigned, other raw
            $table->string('dept')->nullable();
            $table->json('group_ids')->nullable();
            $table->string('role_id')->nullable();
            $table->string('role_name')->nullable();
            $table->timestamp('created_at_zoom')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_hosted_window', 16)->nullable(); // 0-30 | 30-60 | ... | none | unknown
            $table->json('meetings_by_window')->nullable();
            $table->unsignedInteger('upcoming_meetings_count')->nullable();
            $table->boolean('is_room')->default(false);
            $table->boolean('has_phone')->default(false);
            $table->boolean('has_bundled_license')->default(false);
            $table->boolean('bundle_known')->default(false);
            $table->boolean('has_webinar_addon')->default(false);
            $table->boolean('has_large_meeting_addon')->default(false);
            $table->json('add_ons')->nullable();
            $table->string('bucket', 32)->default('healthy');
            $table->json('protected_reasons')->nullable();
            $table->boolean('eligible_for_downgrade')->default(false);
            $table->timestamp('excluded_until')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'member_key']);
            $table->index(['organization_id', 'bucket']);
            $table->index(['organization_id', 'email']);
        });

        Schema::create('exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // email | domain | group
            $table->string('value');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'type', 'value']);
        });

        Schema::create('license_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zoom_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_email')->nullable();
            $table->string('member_name')->nullable();
            $table->string('action', 16); // downgrade | restore
            $table->unsignedSmallInteger('from_type')->nullable();
            $table->unsignedSmallInteger('to_type')->nullable();
            $table->string('source', 16); // manual | bulk | rule
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('queued'); // queued | done | failed | skipped
            $table->text('reason')->nullable();
            $table->string('zoom_tracking_id')->nullable();
            $table->string('batch_id')->nullable()->index();
            $table->boolean('dry_run')->default(false);
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('downgrade_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zoom_member_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('scheduled_for');
            $table->timestamp('kept_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('executed_action_id')->nullable()->constrained('license_actions')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downgrade_notices');
        Schema::dropIfExists('license_actions');
        Schema::dropIfExists('exclusions');
        Schema::dropIfExists('zoom_members');
        Schema::dropIfExists('zoom_seats_snapshots');
        Schema::dropIfExists('scans');
    }
};
