<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableRakanFile extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rakan_file', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('target_id', false, true)->comment('用户ID');
            $table->string('path')->comment('文件路径');
            $table->string('floder')->comment('目录');
            $table->string('filename')->comment('文件名称');
            $table->string('type')->comment('文件类型');
            $table->integer('size', false, true)->default(0)->comment('文件大小');
            $table->integer('width', false, true)->default(0)->comment('宽度');
            $table->integer('height', false, true)->default(0)->comment('高度');
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
        Schema::dropIfExists('rakan_file');
    }
}
