<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_pages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->foreignId('vendor_id')
                ->nullable()
                ->constrained('vendors')
                ->restrictOnDelete();
            $table->string('hero_title')->nullable();
            $table->text('hero_description')->nullable();
            $table->string('ims_portal_url', 2048)->nullable();
            $table->string('request_section_title')->nullable();
            $table->text('request_section_explanation')->nullable();
            $table->json('faqs')->nullable();
            $table->string('hero_image_url', 2048)->nullable();
            $table->string('educational_calendar_url', 2048)->nullable();
            $table->timestamps();
        });

        DB::table('organization_pages')->insert([
            'singleton_key'               => 1,
            'vendor_id'                   => null,
            'hero_title'                  => null,
            'hero_description'            => null,
            'ims_portal_url'              => null,
            'request_section_title'       => null,
            'request_section_explanation' => null,
            'faqs'                        => null,
            'hero_image_url'              => null,
            'educational_calendar_url'    => null,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_pages');
    }
};
