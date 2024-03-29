<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RgSalesSettingsAddDefaultDateMethodColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::connection('tenant')->hasColumn('rg_sales_settings', 'default_date_method'))
        {
            Schema::connection('tenant')->table('rg_sales_settings', function (Blueprint $table) {
                $table->string('default_date_method')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('tenant')->table('rg_sales_settings', function (Blueprint $table) {
            $table->dropColumn('default_date_method');
        });
    }
}
