<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('current_scene', 20)->default('reception')->after('status');
            $table->foreignId('selected_room_type_id')->nullable()->after('current_scene')->constrained('room_types')->nullOnDelete();
            $table->json('reservation_state')->nullable()->after('selected_room_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_room_type_id');
            $table->dropColumn(['current_scene', 'reservation_state']);
        });
    }
};
