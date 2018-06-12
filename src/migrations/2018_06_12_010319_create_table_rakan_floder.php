<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableRakanFloder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rakan_floder', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('target_id', false, true)->comment('用户ID');
            $table->integer('pid')->default(0)->comment('父级ID');
            $table->string('path')->comment('目录路径');
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
        Schema::dropIfExists('rakan_floder');
    }
}
