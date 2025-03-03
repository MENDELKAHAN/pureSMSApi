<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('recipient');
            $table->string('sender')->nullable();
            $table->text('content');
            $table->string('status')->default('pending');
            $table->integer('error_code')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_logs');
    }
};
