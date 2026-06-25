<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('financial_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('title');
            $table->string('value')->nullable();
            $table->string('growth')->nullable();
            $table->string('growth_yoy')->nullable();
            $table->string('growth_end')->nullable();
            $table->string('share_current')->nullable();
            $table->string('share_previous')->nullable();
            $table->string('share_growth_end')->nullable();
            $table->tinyInteger('level')->default(0);
            $table->date('month', 6);

            $table->foreign('report_id')
                ->references('id')
                ->on('cbi_reports')
                ->onDelete('cascade');


            $table->index('month');
            $table->index('level');
            $table->index('title');
        });
    }

    public function down()
    {
        Schema::dropIfExists('financial_items');
    }
};