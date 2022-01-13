<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumns('messages', ['intents', 'conversation', 'scene'])) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn(['intents', 'conversation', 'scene']);
            });
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->string('scenario_id')->nullable();
            $table->string('scenario_name')->nullable();
            $table->string('conversation_id')->nullable();
            $table->string('conversation_name')->nullable();
            $table->string('scene_id')->nullable();
            $table->string('scene_name')->nullable();
            $table->string('turn_id')->nullable();
            $table->string('turn_name')->nullable();
            $table->string('intent_id')->nullable();
            $table->string('intent_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'scenario_id',
                'scenario_name',
                'conversation_id',
                'conversation_name',
                'scene_id',
                'scene_name',
                'turn_id',
                'turn_name',
                'intent_id',
                'intent_name'
            ]);

            $table->string('intents')->nullable();
            $table->string('conversation')->nullable();
            $table->string('scene')->nullable();
        });
    }
}
