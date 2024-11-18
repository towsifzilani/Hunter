<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('uid')->unique();
            $table->string('folder_name')->nullable();
            $table->string('message_id')->unique();
            $table->string('in_reply_to')->nullable();
            $table->longText('references')->nullable();
            $table->string('from');
            $table->text('to');
            $table->text('cc')->nullable();
            $table->text('subject');
            $table->longText('body');
            $table->timestamp('sentDateTime')->nullable();
            $table->timestamp('receivedDateTime')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('emails');
    }
};
