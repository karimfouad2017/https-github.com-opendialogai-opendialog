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
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('od_id')->nullable();
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->string('interpreter')->nullable();
            $table->json('conditions')->nullable();
            $table->json('behaviors')->nullable();
            $table->string('type');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->uuid('uid');
            $table->foreign('parent_id')->references('id')->on('conversation_objects')->onDelete('cascade');
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
