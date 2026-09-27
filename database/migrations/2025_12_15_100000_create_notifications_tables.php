<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        // Email notification settings per tenant
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            // Invoice notifications
            $table->boolean('send_invoice_on_create')->default(false);
            $table->boolean('send_payment_confirmation')->default(true);
            $table->boolean('send_overdue_reminders')->default(true);
            $table->integer('overdue_reminder_days')->default(7); // Send every X days
            $table->boolean('send_payment_reminders')->default(true);
            $table->integer('payment_reminder_days_before')->default(3);
            
            // Bill notifications
            $table->boolean('send_bill_due_reminders')->default(true);
            $table->integer('bill_reminder_days_before')->default(3);
            
            // Inventory notifications
            $table->boolean('send_low_stock_alerts')->default(true);
            $table->string('low_stock_alert_frequency')->default('daily'); // daily, weekly
            
            // HR notifications
            $table->boolean('send_payroll_notifications')->default(true);
            $table->boolean('send_leave_notifications')->default(true);
            
            // Email settings
            $table->string('email_from_name')->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('email_reply_to')->nullable();
            
            $table->timestamps();
            
            $table->unique('tenant_id');
        });

        // Track sent notifications to avoid duplicates
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('reference_type')->nullable(); // e.g., Invoice, Bill
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('channel')->default('mail'); // mail, database, sms
            $table->string('status')->default('sent'); // sent, failed, pending
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'notification_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('notifications');
    }
};
