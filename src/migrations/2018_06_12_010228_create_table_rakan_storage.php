<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableRakanStorage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rakan_storage', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('target_id', false, true)->comment('用户ID');
            $table->bigInteger('usage', false, true)->default(0)->comment('使用量 默认:0');
            $table->bigInteger('space', false, true)->default(10737418240)->comment('存储空间 默认:10737418240');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rakan_storage');
    }
}
