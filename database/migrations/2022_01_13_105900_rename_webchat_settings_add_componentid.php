<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameWebchatSettingsAddComponentid extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::rename('webchat_settings', 'component_settings');

        Schema::table('component_settings', function (Blueprint $table) {
            $table->string('component_id')->default('platform.core.webchat');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::table('component_settings', function (Blueprint $table) {
            $table->removeColumn('component_id');
        });

        Schema::rename('component_settings', 'webchat_settings');
    }
}
