<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePersonalPointStatisticsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('personal_point_statistics', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('staff_sn')->comment('员工编号');
            $table->char('staff_name', 10)->comment('员工姓名');
            $table->unsignedTinyInteger('brand_id')->comment('品牌ID');
            $table->char('brand_name', 10)->comment('品牌名称');
            $table->unsignedSmallInteger('department_id')->comment('部门ID');
            $table->char('department_name', 100)->comment('部门名称');
            $table->char('shop_sn', 10)->comment('店铺代码');
            $table->char('shop_name', 50)->comment('店铺名称');
            $table->mediumInteger('point_a')->comment('A分');
            $table->mediumInteger('point_b_monthly')->comment('当月B分');
            $table->mediumInteger('point_b_total')->comment('累计B分');
            $table->dateTime('calculated_at')->comment('结算时间');
            $table->text('source_b_monthly')->comment('当月各来源B分');
            $table->text('source_b_total')->comment('累计各来源B分');
            $table->mediumInteger('point_a_total')->comment('累计A分');
            $table->text('source_a_monthly')->comment('当月各来源A分');
            $table->text('source_a_total')->comment('累计各来源A分');
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
        Schema::dropIfExists('personal_point_statistics');
    }
}
