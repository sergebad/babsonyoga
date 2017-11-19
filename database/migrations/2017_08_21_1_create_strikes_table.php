<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;

class CreateStrikesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('strikes', function ($t) {
            $t->increments('id');
            $t->string('attendee_identifier');
            $t->string('description');
            $t->datetime('expires_on')->default(\Carbon\Carbon::now()->addMonths(3));
            $t->foreign('attendee_identifier')->references('email')->on('attendees')->onDelete('cascade');
            $t->nullableTimestamps();
        });
        
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tables = [
            'strikes'
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach($tables as $table) {
            Schema::drop($table);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    }
}