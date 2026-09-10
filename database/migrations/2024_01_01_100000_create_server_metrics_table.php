<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('cpu_percent');
            $table->unsignedTinyInteger('memory_percent');
            $table->unsignedTinyInteger('disk_percent'); // أعلى نسبة استخدام بين كل الـ partitions
            $table->float('load_1min', 5, 2)->default(0);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
