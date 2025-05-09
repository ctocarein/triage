<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDynamicQuestionTemplatesTable extends Migration
{
    public function up()
    {
        Schema::create('dynamic_question_templates', function (Blueprint $table) {
            $table->id();

            $table->string('key')->unique();
            $table->string('text');
            $table->enum('type', [
                'boolean', 'text', 'number', 'date',
                'select_one', 'select_multiple',
                'info', 'file','hidden',
                'checkbox', 'radio', 'textarea',
            ]);
            $table->enum('answer_target', [
                'personal_profile', 'medical_profile',
                'evaluation', 'followup', 'tracking', 'survey'
            ])->nullable();

            $table->unsignedBigInteger('question_group_id')->nullable();

            $table->enum('media_type', ['image', 'video', 'audio'])->nullable();
            $table->text('media_url')->nullable();

            $table->json('options')->nullable();
            $table->json('visible_if')->nullable();

            $table->boolean('is_required')->default(false);
            $table->boolean('is_entry_point')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('risk_weight')->default(0);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dynamic_question_templates');
    }
}
