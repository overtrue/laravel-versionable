<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('versions', function (Blueprint $table) {
            $uuid = config('versionable.uuid');
            $uuidVersionId = config('versionable.uuid_version_id', false);

            $uuid ? $table->uuid('id')->primary() : $table->bigIncrements('id');
            $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'));

            $uuid || $uuidVersionId ? $table->uuidMorphs('versionable') : $table->morphs('versionable');

            $table->json('contents')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('versions');
    }
};
