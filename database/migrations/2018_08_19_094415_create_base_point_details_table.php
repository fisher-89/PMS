<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBasePointDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('base_point_details', function (Blueprint $table) {
            $table->increments('id');
            $table->char('title', 50)->comment('标题');
            $table->unsignedMediumInteger('source_foreign_key')->comment('来源关联id');
            $table->unsignedMediumInteger('staff_sn')->comment('员工编号');
            $table->char('staff_name', 10)->comment('员工姓名');
            $table->char('type', 20)->nullable()->comment('结算分类');
            $table->decimal('point', 8, 2)->default(0)->comment('积分值');
            $table->text('data')->nullable()->comment('基础分详情');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('base_point_details');
    }
}
