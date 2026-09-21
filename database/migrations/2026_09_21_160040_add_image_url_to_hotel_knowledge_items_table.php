<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_knowledge_items', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_knowledge_items', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
