<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestionGroupsTable extends Migration
{
    public function up()
    {
        Schema::create('question_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();         // ex: symptomes_sein
            $table->string('label');                // ex: Symptômes du sein
            $table->string('icon')->nullable();     // ex: breast, warning-circle
            $table->string('color')->nullable();    // ex: #FF4081
            $table->text('description')->nullable();// explication pour l’interface
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('question_groups');
    }
}
