<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbi_dictionary', function (Blueprint $table) {
            $table->id();
            $table->char('key',11)->unique()->comment('کلید منحصربه‌فرد برای ارجاع در سایر جداول');
            $table->text('value')->nullable()->comment('مقدار متنی');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbi_dictionary');
    }
};