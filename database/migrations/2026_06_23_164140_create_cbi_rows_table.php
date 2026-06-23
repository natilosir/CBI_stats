<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbi_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sheet_id');
            $table->unsignedInteger('row_index')->comment('اندیس ردیف در شیت');
            $table->unsignedSmallInteger('cell_count')->default(0);

            $table->foreign('sheet_id')->references('id')->on('cbi_sheets')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbi_rows');
    }
};