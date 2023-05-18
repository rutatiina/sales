<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RgSalesAddNumberColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::connection('tenant')->hasColumn('rg_sales', 'number'))
        {
            //do nothing
        }
        else
        {
            Schema::connection('tenant')->table('rg_sales', function (Blueprint $table) {
                $table->string('number', 250)->nullable()->default(0)->after('document_name');
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
        Schema::connection('tenant')->table('rg_sales', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
}
