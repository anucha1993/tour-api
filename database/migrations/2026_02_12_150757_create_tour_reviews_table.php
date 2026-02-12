<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_reviews', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable()->comment('Booking/Order reference');

            // Reviewer info
            $table->string('reviewer_name', 100);
            $table->string('reviewer_avatar_url')->nullable();

            // Overall rating (required)
            $table->tinyInteger('rating')->unsigned()->comment('1-5 overall');

            // Category ratings (optional, stored as JSON)
            $table->json('category_ratings')->nullable()->comment('guide,food,hotel,value,program_accuracy,would_return');

            // Tags (multi-select)
            $table->json('tags')->nullable()->comment('["#japan","#family"]');

            // Comment
            $table->text('comment')->nullable();

            // Review source
            $table->enum('review_source', ['self', 'assisted', 'internal'])->default('self');

            // Assisted review fields
            $table->boolean('approved_by_customer')->default(false);
            $table->string('approval_screenshot_url')->nullable();
            $table->foreignId('assisted_by_admin_id')->nullable()->constrained('users')->nullOnDelete();

            // Moderation
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Admin reply
            $table->text('admin_reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();

            // Incentive
            $table->string('incentive_type')->nullable()->comment('discount|coupon|points');
            $table->string('incentive_value')->nullable();
            $table->boolean('incentive_claimed')->default(false);

            // Display
            $table->boolean('is_featured')->default(false);
            $table->integer('helpful_count')->default(0);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['tour_id', 'status']);
            $table->index(['user_id', 'tour_id']);
            $table->index(['status', 'created_at']);
            $table->index('review_source');
            $table->index('rating');
        });

        // Review tags master table
        Schema::create('review_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->string('icon')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_tags');
        Schema::dropIfExists('tour_reviews');
    }
};
