<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('terminals');
            $table->string('order_id');
            $table->bigInteger('amount');
            $table->string('description');
            $table->integer('status')->default(0);
            $table->timestamps();

            $table->unique(['terminal_id', 'order_id']); // мерчант создает 1 платеж на 1 заказ
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
