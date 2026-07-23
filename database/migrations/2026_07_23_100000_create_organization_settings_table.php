<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name')->nullable();            // ชื่อบริษัทตามกฎหมาย (schema legalName)
            $table->text('description')->nullable();             // คำอธิบายบริษัท (schema description)
            $table->string('price_range', 20)->nullable();       // ช่วงราคา เช่น ฿฿
            $table->json('area_served')->nullable();             // พื้นที่ให้บริการ เช่น ["Thailand","Japan"]
            $table->json('languages')->nullable();               // ภาษาที่ให้บริการ เช่น ["th","en"]
            $table->string('founding_date', 20)->nullable();     // ปีก่อตั้ง เช่น 2016
            $table->boolean('rating_enabled')->default(false);   // แสดงคะแนนรีวิวรวมใน schema
            $table->decimal('rating_value', 2, 1)->nullable();   // คะแนนรีวิวเฉลี่ย 0.0–5.0
            $table->unsignedInteger('rating_count')->nullable(); // จำนวนรีวิว
            $table->boolean('faq_enabled')->default(true);       // แสดง FAQ (FAQPage schema)
            $table->json('faqs')->nullable();                    // คำถามพบบ่อย [{question, answer}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
