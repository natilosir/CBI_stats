<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbi_cells', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('row_id');
            $table->unsignedSmallInteger('column_index')->comment('اندیس ستون');
            $table->string('column_name')->nullable()->comment('نام ستون (اختیاری)');
            $table->string('hash')->nullable()->index()->comment('ارجاع به دیکشنری برای مقدار سلول');
            $table->unsignedSmallInteger('row_span')->default(1);
            $table->unsignedSmallInteger('col_span')->default(1);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_merged')->default(false);

            $table->foreign('row_id')->references('id')->on('cbi_rows')->onDelete('cascade');

            $table->foreign('hash')->references('key')->on('cbi_dictionary')->onDelete('set null');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbi_cells');
    }
};