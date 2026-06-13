<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('relay_status', function (Blueprint $table) {
            $table->id();
            $table->boolean('r1')->default(false);
            $table->boolean('r2')->default(false);
            $table->boolean('r3')->default(false);
            $table->boolean('r4')->default(false);
            $table->timestamps();
        });

        // Insert default data
        DB::table('relay_status')->insert([
            'r1' => false,
            'r2' => false,
            'r3' => false,
            'r4' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relay_status');
    }
};
