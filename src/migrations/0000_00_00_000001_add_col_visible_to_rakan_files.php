<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColVisibleToRakanFiles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(config('rakan.default.table_name'), function (Blueprint $table) {
            $table->integer('visible', false, true)->default(1)->comment('权限 0 私有 1 公共读 2 公共读写');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(config('rakan.default.table_name'), function (Blueprint $table) {
            $table->dropColumn('visible');
        });
    }
}
