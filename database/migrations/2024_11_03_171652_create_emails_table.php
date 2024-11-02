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
            $table->foreignId('conversation_id')->constrained('conversations');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('message_id')->unique();
            $table->string('in_reply_to')->nullable();
            $table->longText('references')->nullable();
            $table->string('from');
            $table->text('to');
            $table->text('cc')->nullable();
            $table->text('subject');
            $table->longText('body');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('emails');
    }
};
