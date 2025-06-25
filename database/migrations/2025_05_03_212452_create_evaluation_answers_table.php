<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_session_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('dynamic_question_template_id')
                  ->constrained('dynamic_question_templates')
                  ->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->boolean('is_skipped')->default(false);
            $table->timestamps();

            // Index
            $table->index('evaluation_session_id');
            $table->index('dynamic_question_template_id');
            $table->unsignedInteger('order')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('evaluation_answers');
    }
};