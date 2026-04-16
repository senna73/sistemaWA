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
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            
            $table->string('name');

            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('total_added', 15, 2)->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);

            $table->timestamps();
        });

        DB::table('cash_accounts')->insertOrIgnore([
            [
                'id' => 1,
                'name' => 'Conta Principal',
                'balance' => 0,
                'total_added' => 0,
                'total_spent' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_accounts');
    }
};
