<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type');       // cpu | memory | disk | service
            $table->string('identifier')->nullable(); // اسم الخدمة أو mount القرص
            $table->string('status')->default('open'); // open | resolved
            $table->float('threshold_value');
            $table->float('peak_value');
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['metric_type', 'identifier', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
