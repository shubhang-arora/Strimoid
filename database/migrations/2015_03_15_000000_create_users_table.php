<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');

            // Credentials
            $table->string('name')->unique()->collation = 'utf8_unicode_ci';
            $table->string('email')->unique()->collation = 'utf8_unicode_ci';
            $table->string('password', 60);
            $table->rememberToken();

            // Some internal info
            $table->enum('type', ['user', 'banned', 'admin']);
            $table->string('last_ip', 45);
            $table->boolean('is_activated')->default(false);
            $table->string('activation_token', 16);
            $table->integer('total_points')->default(0);

            // User profile
            $table->string('avatar', 16)->nullable();
            $table->smallInteger('age')->unsigned();
            $table->enum('sex', ['unknown', 'male', 'female'])->default('unknown');
            $table->string('location')->nullable();
            $table->text('description', 255)->nullable();

            // Dates
            $table->timestamps();
            $table->softDeletes();
            $table->timestamp('last_login');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('users');
    }
}
