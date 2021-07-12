<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColsMarkedAtToRakanFiles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumns(config('rakan.default.table_name'), [
            'is_default',
            'marked_at',
        ])) {
            return;
        }

        Schema::table(config('rakan.default.table_name'), function (Blueprint $table) {
            $table->unsignedTinyInteger('is_default')->default(0);
            $table->dateTime('marked_at')->nullable();
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
            'is_default',
            'marked_at',
        ])) {
            return;
        }

        Schema::table(config('rakan.default.table_name'), function (Blueprint $table) {
            $table->dropColumn([
                'is_default',
                'marked_at',
            ]);
        });
    }
}
