<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('email_id');
            $table->text('content_id')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->timestamps();

            $table->foreign('email_id')->references('id')->on('emails')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attachments');
    }
};
