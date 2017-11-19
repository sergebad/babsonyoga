<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSupportForWaitlistOrderStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        $order_statuses = [
            [
                'id' => 6,
                'name' => 'Wait-list',
            ],
        ];

        DB::table('order_statuses')->insert($order_statuses);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        DB::table('order_statuses')->where('name', 'Wait-list')->delete();

    }
}
