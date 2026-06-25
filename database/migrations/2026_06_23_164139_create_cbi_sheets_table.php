<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbi_sheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->string('hash')->index()->comment('ارجاع به کلید دیکشنری برای نام شیت');
            $table->unsignedSmallInteger('index')->comment('شماره یا ترتیب شیت');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('total_columns')->default(0);

            $table->foreign('report_id')->references('id')->on('cbi_reports')->onDelete('cascade');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbi_sheets');
    }
};