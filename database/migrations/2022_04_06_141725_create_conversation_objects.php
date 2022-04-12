<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationObjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conversation_objects', function (Blueprint $table) {
            $table->uuid('uid')->index();
            $table->string('od_id')->nullable();
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->string('interpreter')->nullable();
            $table->json('conditions')->nullable();
            $table->json('behaviors')->nullable();
            $table->string('type');
            $table->uuid('parent_id')->nullable();
            $table->json('data')->nullable();
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
        Schema::dropIfExists('conversation_objects');
    }
}
