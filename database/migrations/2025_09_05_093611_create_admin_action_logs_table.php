<?php

declare(strict_types=1);

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
        Schema::create('admin_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')
                ->constrained('staff')
                ->restrictOnDelete()
                ->comment('Reference to the staff member who performed the action');
            $table->string('action_type', 50)
                ->comment('Type of action: create, update, delete, view, etc.');
            $table->string('resource_type', 255)
                ->nullable()
                ->comment('Full class name of the affected resource');
            $table->unsignedBigInteger('resource_id')
                ->nullable()
                ->comment('ID of the affected resource');
            $table->string('route_name', 255)
                ->comment('Laravel route name that was accessed');
            $table->string('http_method', 10)
                ->comment('HTTP method: GET, POST, PUT, DELETE');
            $table->jsonb('request_data')
                ->nullable()
                ->comment('Request payload data');
            $table->smallInteger('response_status')
                ->comment('HTTP response status code');
            $table->ipAddress('ip_address')
                ->comment('IP address of the admin');
            $table->text('user_agent')
                ->nullable()
                ->comment('Browser user agent string');
            $table->string('session_id', 255)
                ->nullable()
                ->comment('Session identifier');
            $table->enum('risk_level', ['low', 'medium', 'high'])
                ->default('low')
                ->comment('Assessed risk level of the action');
            $table->jsonb('metadata')
                ->nullable()
                ->comment('Additional contextual information');
            $table->timestamps();

            // Indexes for performance
            $table->index(['admin_id', 'created_at']);
            $table->index(['action_type', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index(['risk_level', 'created_at']);
            $table->index('route_name');
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_action_logs');
    }
};
