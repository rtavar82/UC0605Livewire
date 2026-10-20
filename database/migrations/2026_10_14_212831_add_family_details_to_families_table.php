<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->dropUnique(['name']);
            $table->dropUnique(['slug']);
            $table->dropColumn(['name', 'slug', 'description', 'image']);
            $table->dropSoftDeletes();
        });
    }
};
