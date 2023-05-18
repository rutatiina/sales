<?php

namespace Rutatiina\Sales\Seeders;

use Illuminate\Database\Seeder;
use Rutatiina\Sales\Models\Sales;
use Rutatiina\Tenant\Models\Tenant;
use Rutatiina\Sales\Models\SalesSetting;
use Rutatiina\Tenant\Scopes\TenantIdScope;

//php artisan db:seed --class=\\Rutatiina\\Sales\\Seeders\\DocumentNumbersSeeder
//php artisan db:seed --class=\Rutatiina\Sales\Seeders\DocumentNumbersSeeder

class DocumentNumbersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        
        //this seeder created the document numbers for sales records.
        //the sales module did not have numbers before 18 May 2023


        //get all tenants
        foreach (Tenant::all() as $tenant)
        {
            $this->command->line('- Update sales records for :: #'.$tenant->id.' > '.$tenant->name);
            $settings = SalesSetting::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'document_name' => 'Sales receipt',
                    'document_type' => 'receipt',
                    'minimum_number_length' => 5,
                    'number_prefix' => '',
                    'debit_financial_account_code' => 110100, //Cash and Cash Equivalents
                    'credit_financial_account_code' => 410100, //Sales Revenue
                ]
            );

            $sales = Sales::withoutGlobalScopes([TenantIdScope::class])->where('tenant_id', $tenant->id)->orderBy('id', 'asc')->get();

            $count = 0;
            
            foreach ($sales as $txn) 
            {
                $count++;

                $number = $settings->number_prefix . (str_pad(($count), $settings->minimum_number_length, "0", STR_PAD_LEFT)) . $settings->number_postfix;
                $txn->number = $number;
                $txn->save();

                $this->command->line('  - txn #'.$txn->id.' > '.$settings->minimum_number_length.' updated');

                
            }
            $this->command->line('  - '.$count.' txns updated');
        }
        
    }
}
