<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_login_codes', function (Blueprint $table) {
            $table->id();
            $table->morphs('tenant');
            $table->string('email');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_type', 'tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_login_codes');
    }
};
