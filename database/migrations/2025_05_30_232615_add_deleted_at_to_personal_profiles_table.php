<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('personal_profiles', function (Blueprint $table) {
            $table->softDeletes(); // ajoute la colonne deleted_at
        });
    }

    public function down()
    {
        Schema::table('personal_profiles', function (Blueprint $table) {
            $table->dropSoftDeletes(); // supprime la colonne deleted_at
        });
    }

};
