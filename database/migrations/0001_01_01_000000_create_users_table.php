<?php

declare(strict_types=1);

use App\Enums\CivilIdTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->index()->comment('Unique identifier for the user');
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 20)->unique();
            $table->string('phone2', 20)->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('civil_id')->nullable();
            $table->enum('civil_id_type', CivilIdTypeEnum::getAllValues())
                ->nullable()->comment('e.g., national_code, passport');
            $table->date('date_of_birth')->nullable();
            $table->string('father_name', 100)->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('education_level', 20)->nullable();
            $table->string('field_of_study')->nullable();
            $table->string('education_status', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
