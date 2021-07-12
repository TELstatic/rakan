<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColReadonlyToRakanFiles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumns(config('rakan.default.table_name'), [
            'readonly',
        ])) {
            return;
        }

        Schema::table(config('rakan.default.table_name'), function (Blueprint $table) {
            $table->unsignedTinyInteger('readonly')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasColumns(config('rakan.default.table_name'), [
            'readonly',
        ])) {
            return;
        }

        Schema::table(config('rakan.default.table_name'), function (Blueprint $table) {
            $table->dropColumn([
                'readonly',
            ]);
        });
    }
}
