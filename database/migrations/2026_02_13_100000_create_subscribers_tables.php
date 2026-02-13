<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->enum('status', ['pending', 'active', 'unsubscribed'])->default('pending');
            $table->string('source_page')->nullable()->comment('Page where user subscribed');
            $table->string('interest_country')->nullable()->comment('Country interest if provided');
            $table->string('confirmation_token')->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribe_token')->nullable()->unique();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('source_page');
            $table->index('interest_country');
            $table->index('confirmed_at');
        });

        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('content_html');
            $table->text('content_text')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'cancelled'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('After this date, newsletter will not be sent');
            $table->string('template')->default('promotion')->comment('Template type: welcome, promotion, review');
            $table->json('recipient_filter')->nullable()->comment('Filter criteria: all, active, country, etc.');
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('batch_size')->default(50)->comment('Emails per batch for warm-up');
            $table->unsignedInteger('batch_delay_seconds')->default(60)->comment('Delay between batches');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_at');
        });

        Schema::create('newsletter_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscriber_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'sent', 'failed', 'opened'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->unique(['newsletter_id', 'subscriber_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_logs');
        Schema::dropIfExists('newsletters');
        Schema::dropIfExists('subscribers');
    }
};
