<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRedisConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Create Redis config table
        Schema::create(
            'redis_config',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('host');
                $t->integer('port')->nullable();
                $t->longText('password')->nullable();
                $t->integer('database_index')->nullable();
                $t->integer('default_ttl');
                $t->text('options')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //Drop Redis config table if exists
        Schema::dropIfExists('redis_config');
    }
}
