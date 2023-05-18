<?php

namespace Rutatiina\Sales\Services;

use Rutatiina\Tax\Models\Tax;
use Rutatiina\Sales\Models\Sales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Rutatiina\Sales\Models\SalesSetting;
use Rutatiina\GoodsReceived\Services\GoodsReceivedInventoryService;
use Rutatiina\GoodsDelivered\Services\GoodsDeliveredInventoryService;
use Rutatiina\FinancialAccounting\Services\AccountBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceUpdateService;

class SalesService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function settings()
    {
        return SalesSetting::firstOrCreate(
            ['tenant_id' => session('tenant_id')],
            [
                'tenant_id' => session('tenant_id'),
                'document_name' => 'Sales receipt',
                'document_type' => 'receipt',
                'minimum_number_length' => 5,
                'number_prefix' => '',
                'debit_financial_account_code' => 110100, //Cash and Cash Equivalents
                'credit_financial_account_code' => 410100, //Sales Revenue
            ]
        );
    }

    public static function nextNumber()
    {
        $count = Sales::count();
        $settings = self::settings();

        return $settings->number_prefix . (str_pad(($count + 1), $settings->minimum_number_length, "0", STR_PAD_LEFT)) . $settings->number_postfix;
    }

    public static function edit($id)
    {
        $taxes = Tax::all()->keyBy('code');

        $txn = Sales::findOrFail($id);
        $txn->load('contact', 'items.taxes');
        $txn->setAppends(['taxes']);

        $contact = $txn->contact;

        $attributes = $txn->toArray();

        //print_r($attributes); exit;

        $attributes['_method'] = 'PATCH';

        $attributes['contact']['currency'] = ($contact) ? $attributes['contact']['currency_and_exchange_rate']: [];
        $attributes['contact']['currencies'] = ($contact) ? $attributes['contact']['currencies_and_exchange_rates'] : [];

        $attributes['taxes'] = json_decode('{}');

        foreach ($attributes['items'] as $key => $item)
        {
            $selectedItem = [
                'id' => $item['item_id'],
                'name' => $item['name'],
                'description' => $item['description'],
                'rate' => $item['rate'],
                'tax_method' => 'inclusive',
                'account_type' => null,
            ];

            $attributes['items'][$key]['selectedItem'] = $selectedItem; #required
            $attributes['items'][$key]['selectedTaxes'] = []; #required
            $attributes['items'][$key]['displayTotal'] = 0; #required

            foreach ($item['taxes'] as $itemTax)
            {
                $attributes['items'][$key]['selectedTaxes'][] = $taxes[$itemTax['tax_code']];
            }

            $attributes['items'][$key]['rate'] = floatval($item['rate']);
            $attributes['items'][$key]['quantity'] = floatval($item['quantity']);
            $attributes['items'][$key]['total'] = floatval($item['total']);
            $attributes['items'][$key]['displayTotal'] = $item['total']; #required
        };

        return $attributes;
    }

    public static function store($requestInstance)
    {
        $data = SalesValidateService::run($requestInstance);
        //print_r($data); exit;
        if ($data === false)
        {
            self::$errors = SalesValidateService::$errors;
            return false;
        }

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = new Sales;
            $Txn->tenant_id = $data['tenant_id'];
            $Txn->created_by = Auth::id();
            $Txn->document_name = $data['document_name'];
            $Txn->number = $data['number'];
            $Txn->date = $data['date'];
            $Txn->debit_financial_account_code = $data['debit_financial_account_code'];
            $Txn->contact_id = $data['contact_id'];
            $Txn->contact_name = $data['contact_name'];
            $Txn->contact_address = $data['contact_address'];
            // $Txn->reference = $data['reference'];
            $Txn->base_currency = $data['base_currency'];
            $Txn->quote_currency = $data['quote_currency'];
            $Txn->exchange_rate = $data['exchange_rate'];
            $Txn->taxable_amount = $data['taxable_amount'];
            $Txn->total = $data['total'];
            $Txn->branch_id = $data['branch_id'];
            $Txn->store_id = $data['store_id'];
            // $Txn->due_date = $data['due_date'];
            $Txn->contact_notes = $data['contact_notes'];
            // $Txn->terms_and_conditions = $data['terms_and_conditions'];
            $Txn->status = $data['status'];

            $Txn->save();

            $data['id'] = $Txn->id;

            //print_r($data['items']); exit;

            //Save the items >> $data['items']
            SalesItemService::store($data);

            //Save the ledgers >> $data['ledgers']; and update the balances
            SalesLedgerService::store($data);

            //check status and update financial account and contact balances accordingly
            //update the status of the txn
            if (SalesApprovalService::run($data))
            {
                $Txn->status = $data['status'];
                $Txn->balances_where_updated = 1;
                $Txn->save();
            }

            if (GoodsDeliveredInventoryService::update($data))
            {
                //do nothing 
            }
            else
            {
                DB::connection('tenant')->rollBack();
            }

            DB::connection('tenant')->commit();

            return $Txn;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to save Sales to database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to save Sales to database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to save Sales to database. Please contact Admin';
            }

            return false;
        }
        //*/

    }

    public static function update($requestInstance)
    {
        $data = SalesValidateService::run($requestInstance);
        //print_r($data); exit;
        if ($data === false)
        {
            self::$errors = SalesValidateService::$errors;
            return false;
        }

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $originalTxn = Sales::with('items', 'ledgers')->findOrFail($data['id']);

            $originalTxnArray = $originalTxn->toArray();

            $Txn = $originalTxn->duplicate();

            //reverse the inventory entries
            GoodsDeliveredInventoryService::reverse($originalTxnArray);

            //Delete affected relations
            $Txn->ledgers()->delete();
            $Txn->items()->delete();
            $Txn->item_taxes()->delete();
            $Txn->comments()->delete();

            //reverse the account balances
            AccountBalanceUpdateService::doubleEntry($originalTxnArray, true);

            //reverse the contact balances
            ContactBalanceUpdateService::doubleEntry($originalTxnArray, true);

            $Txn->tenant_id = $data['tenant_id'];
            $Txn->created_by = Auth::id();
            $Txn->document_name = $data['document_name'];
            // $Txn->number = $data['number'];
            $Txn->date = $data['date'];
            $Txn->debit_financial_account_code = $data['debit_financial_account_code'];
            $Txn->contact_id = $data['contact_id'];
            $Txn->contact_name = $data['contact_name'];
            $Txn->contact_address = $data['contact_address'];
            // $Txn->reference = $data['reference'];
            $Txn->base_currency = $data['base_currency'];
            $Txn->quote_currency = $data['quote_currency'];
            $Txn->exchange_rate = $data['exchange_rate'];
            $Txn->taxable_amount = $data['taxable_amount'];
            $Txn->total = $data['total'];
            $Txn->branch_id = $data['branch_id'];
            $Txn->store_id = $data['store_id'];
            // $Txn->due_date = $data['due_date'];
            $Txn->contact_notes = $data['contact_notes'];
            // $Txn->terms_and_conditions = $data['terms_and_conditions'];
            $Txn->status = $data['status'];

            $Txn->save();

            $data['id'] = $Txn->id;

            //print_r($data['items']); exit;

            //Save the items >> $data['items']
            SalesItemService::store($data);

            //Save the ledgers >> $data['ledgers']; and update the balances
            SalesLedgerService::store($data);

            //check status and update financial account and contact balances accordingly
            //update the status of the txn
            if (SalesApprovalService::run($data))
            {
                $Txn->status = $data['status'];
                $Txn->balances_where_updated = 1;
                $Txn->save();
            }

            $originalTxn->update(['status' => 'edited']);

            DB::connection('tenant')->commit();

            return $Txn;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to update sales in database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to update sales in database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to update sales in database. Please contact Admin';
            }

            return false;
        }

    }

    public static function destroy($id)
    {
        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = Sales::with('items', 'ledgers')->findOrFail($id);

            $txnArray = $Txn->toArray();

            //reverse the inventory entries
            GoodsDeliveredInventoryService::reverse($txnArray);

            //reverse the account balances
            AccountBalanceUpdateService::doubleEntry($txnArray, true);

            //reverse the contact balances
            ContactBalanceUpdateService::doubleEntry($txnArray, true);

            //Delete affected relations
            $Txn->ledgers()->delete();
            $Txn->items()->delete();
            $Txn->item_taxes()->delete();
            $Txn->comments()->delete();

            $Txn->delete();

            DB::connection('tenant')->commit();

            return true;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to delete sale from database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to delete sale from database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to delete sale from database. Please contact Admin';
            }

            return false;
        }
    }

    public static function destroyMany($ids)
    {
        foreach($ids as $id)
        {
            if(!self::destroy($id)) return false;
        }
        return true;
    }

    public static function copy($id)
    {
        $taxes = Tax::all()->keyBy('code');

        $txn = Sales::findOrFail($id);
        $txn->load('contact', 'items.taxes');
        $txn->setAppends(['taxes']);

        $attributes = $txn->toArray();

        #reset some values
        $attributes['number'] = self::nextNumber();
        $attributes['date'] = date('Y-m-d');
        $attributes['due_date'] = '';
        $attributes['expiry_date'] = '';
        #reset some values

        $attributes['contact']['currency'] = $attributes['contact']['currency_and_exchange_rate'];
        $attributes['contact']['currencies'] = $attributes['contact']['currencies_and_exchange_rates'];

        $attributes['taxes'] = json_decode('{}');

        foreach ($attributes['items'] as &$item)
        {
            $selectedItem = [
                'id' => $item['item_id'],
                'name' => $item['name'],
                'description' => $item['description'],
                'rate' => $item['rate'],
                'tax_method' => 'inclusive',
                'account_type' => null,
            ];

            $item['selectedItem'] = $selectedItem; #required
            $item['selectedTaxes'] = []; #required
            $item['displayTotal'] = 0; #required
            $item['rate'] = floatval($item['rate']);
            $item['quantity'] = floatval($item['quantity']);
            $item['total'] = floatval($item['total']);
            $item['displayTotal'] = $item['total']; #required

            foreach ($item['taxes'] as $itemTax)
            {
                $item['selectedTaxes'][] = $taxes[$itemTax['tax_code']];
            }
        };
        unset($item);

        return $attributes;
    }

    public static function approve($id)
    {
        $Txn = Sales::with(['ledgers'])->findOrFail($id);

        if (!in_array($Txn->status, config('financial-accounting.approvable_status')))
        {
            self::$errors[] = $Txn->status . ' sales cannot be approved';
            return false;
        }

        $data = $Txn->toArray();

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $data['status'] = 'approved';
            $approval = SalesApprovalService::run($data);

            //update the status of the txn
            if ($approval)
            {
                $Txn->status = 'approved';
                $Txn->balances_where_updated = 1;
                $Txn->save();
            }

            DB::connection('tenant')->commit();

            return true;

        }
        catch (\Exception $e)
        {
            DB::connection('tenant')->rollBack();
            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'DB Error: Failed to approve sale.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to approve sale. Please contact Admin';
            }

            return false;
        }
    }

}
