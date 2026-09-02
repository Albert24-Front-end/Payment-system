<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('name');
            $table->string('success_url');
            $table->string('fail_url');
            $table->string('webhook_url');
            $table->string('secret_key')->unique();
            $table->timestamps();

            $table->softDeletes(); // делаем пометку - удалено, добавляется поле deleted_at

            $table->unique(['user_id', 'name']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('terminals');
    }
};
