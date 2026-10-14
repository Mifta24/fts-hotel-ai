<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // earn, reverse, adjust
            $table->integer('points'); // signed: a reversal or a correction takes points away
            $table->nullableMorphs('source'); // what earned them, e.g. a booking
            $table->string('note')->nullable();
            $table->string('actor_name')->nullable(); // the staff member behind a manual adjustment
            $table->timestamps();

            // One earning and one reversal per source, however often the award job runs.
            $table->unique(['source_type', 'source_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_points');
    }
};
