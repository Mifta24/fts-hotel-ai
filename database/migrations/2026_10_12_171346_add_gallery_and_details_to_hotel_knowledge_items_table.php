<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_knowledge_items', function (Blueprint $table) {
            $table->json('images')->nullable()->after('image_url');
            $table->json('details')->nullable()->after('images');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_knowledge_items', function (Blueprint $table) {
            $table->dropColumn(['images', 'details']);
        });
    }
};
