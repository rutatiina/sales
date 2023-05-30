<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RgSalesSettingsAddDefaultContactIdColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::connection('tenant')->hasColumn('rg_sales_settings', 'default_contact_id'))
        {
            Schema::connection('tenant')->table('rg_sales_settings', function (Blueprint $table) {
                $table->unsignedBigInteger('default_contact_id')->nullable();
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
            $table->dropColumn('default_contact_id');
        });
    }
}
