<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRakanFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('rakan.default.table_name'), function (Blueprint $table) {
            $table->increments('id');
            $table->integer('target_id', false, true)->comment('模型ID');
            $table->integer('pid', false, true)->default(0)->comment('父目录id');
            $table->string('path', 255)->comment('文件路径');
            $table->string('module', 255)->default('default')->comment('模块');
            $table->string('name')->comment('文件名');
            $table->string('gateway')->default('oss')->comment('文件名');
            $table->string('host')->nullable()->comment('地址');
            $table->string('ext')->nullable()->comment('文件 MineType');
            $table->string('type')->default('folder')->comment('文件类别 文件或目录');
            $table->integer('size', false, true)->default(0)->comment('文件大小');
            $table->integer('width', false, true)->default(0)->comment('文件宽度');
            $table->integer('height', false, true)->default(0)->comment('文件高度');
            $table->integer('sort', false, true)->default(0)->comment('排序');
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
        Schema::dropIfExists(config('rakan.table.name'));
    }
}
