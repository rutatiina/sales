<?php

namespace Rutatiina\Sales\Services;

use Illuminate\Support\Facades\Validator;
use Rutatiina\Contact\Models\Contact;
use Rutatiina\Sales\Models\SalesSetting;
use Rutatiina\Item\Models\Item;
use Illuminate\Support\Facades\Auth;
class SalesValidateService
{
    public static $errors = [];

    public static function run($requestInstance)
    {
        //$request = request(); //used for the flash when validation fails
        $user = auth()->user();


        // >> data validation >>------------------------------------------------------------

        //validate the data
        $customMessages = [
            //'total.in' => "Item total is invalid:\nItem total = item rate x item quantity",
            'debit_financial_account_code.required' => "The payment receipt account field is required.",
            'items.*.taxes.*.code.required' => "Tax code is required",
            'items.*.taxes.*.total.required' => "Tax total is required",
            //'items.*.taxes.*.exclusive.required' => "Tax exclusive amount is required",
        ];

        $rules = [
            'contact_id' => 'nullable|numeric',
            'date' => 'required|date',
            'base_currency' => 'required',
            'debit_financial_account_code' => 'required',
            'salesperson_contact_id' => 'numeric|nullable',
            'memo' => 'string|nullable',

            'items' => 'required|array',
            'items.*.name' => 'required_without:item_id',
            'items.*.rate' => 'required|numeric',
            'items.*.quantity' => 'required|numeric|gt:0',
            //'items.*.total' => 'required|numeric|in:' . $itemTotal, //todo custom validator to check this
            'items.*.units' => 'numeric|nullable',
            'items.*.taxes' => 'array|nullable',

            'items.*.taxes.*.code' => 'required',
            'items.*.taxes.*.total' => 'required|numeric',
            //'items.*.taxes.*.exclusive' => 'required|numeric',
        ];

        $validator = Validator::make($requestInstance->all(), $rules, $customMessages);

        if ($validator->fails())
        {
            self::$errors = $validator->errors()->all();
            return false;
        }

        // << data validation <<------------------------------------------------------------

        SalesService::settings();

        $settings = SalesSetting::has('financial_account_to_debit')
            ->has('financial_account_to_credit')
            ->with(['financial_account_to_debit', 'financial_account_to_credit'])
            ->firstOrFail();
        //Log::info($this->settings);

        $financialAccountToCredit = $settings->financial_account_to_credit->code;


        $contact = Contact::find($requestInstance->contact_id);
        $tenant = Auth::user()->tenant;


        $data['id'] = $requestInstance->input('id', null); //for updating the id will always be posted
        $data['user_id'] = $user->id;
        $data['tenant_id'] = $user->tenant->id;
        $data['created_by'] = $user->name;
        $data['app'] = 'web';
        $data['document_name'] = $settings->document_name;
        // $data['number_prefix'] = $settings->number_prefix;
        $data['number'] = $requestInstance->input('number');
        // $data['number_length'] = $settings->minimum_number_length;
        // $data['number_postfix'] = $settings->number_postfix;
        $data['date'] = $requestInstance->input('date');
        $data['debit_financial_account_code'] = $requestInstance->debit_financial_account_code;
        $data['contact_id'] = $requestInstance->contact_id;
        $data['contact_name'] = optional($contact)->name;
        $data['contact_address'] = trim(optional($contact)->shipping_address_street1 . ' ' . optional($contact)->shipping_address_street2);
        $data['reference'] = $requestInstance->input('reference', null);
        $data['base_currency'] =  $tenant->base_currency; //$requestInstance->input('base_currency');
        $data['quote_currency'] =  $tenant->base_currency; //$requestInstance->input('quote_currency', $data['base_currency']);
        $data['exchange_rate'] = 1; //$requestInstance->input('exchange_rate', 1);
        $data['salesperson_contact_id'] = $requestInstance->input('salesperson_contact_id', null);
        $data['branch_id'] = $requestInstance->input('branch_id', null);
        $data['store_id'] = $requestInstance->input('store_id', null);
        $data['due_date'] = $requestInstance->input('due_date', null);
        $data['terms_and_conditions'] = $requestInstance->input('terms_and_conditions', null);
        $data['contact_notes'] = $requestInstance->input('contact_notes', null);
        $data['status'] = strtolower($requestInstance->input('status', null));
        $data['balances_where_updated'] = 0;


        //set the transaction total to zero
        $txnTotal = 0;
        $taxableAmount = 0;

        //Formulate the DB ready items array
        $data['items'] = [];
        foreach ($requestInstance->items as $key => $item)
        {
            $itemTaxes = $requestInstance->input('items.'.$key.'.taxes', []);

            $txnTotal           += ($item['rate']*$item['quantity']);
            $taxableAmount      += ($item['rate']*$item['quantity']);
            $itemTaxableAmount   = ($item['rate']*$item['quantity']); //calculate the item taxable amount

            foreach ($itemTaxes as $itemTax)
            {
                $txnTotal           += $itemTax['exclusive'];
                $taxableAmount      -= $itemTax['inclusive'];
                $itemTaxableAmount  -= $itemTax['inclusive']; //calculate the item taxable amount more by removing the inclusive amount
            }

            //get the item
            $itemModel = Item::find($item['item_id']);

            if (optional($itemModel)->selling_financial_account_code)
            {
                $financialAccountToCredit = $itemModel->selling_financial_account_code;
            }


            //use item selling_financial_account_code if available and default if not
            $financialAccountToCredit = (optional($itemModel)->selling_financial_account_code) ? $itemModel->selling_financial_account_code : $settings->financial_account_to_credit->code;

            $data['items'][] = [
                'tenant_id' => $data['tenant_id'],
                'created_by' => $data['created_by'],
                'contact_id' => $item['contact_id'],
                'item_id' => optional($itemModel)->id, //$item['item_id'], use internal ID to verify data so that from here one the item_id value is LEGIT
                'credit_financial_account_code' => $financialAccountToCredit,
                'name' => $item['name'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'rate' => $item['rate'],
                'total' => $item['total'],
                'taxable_amount' => $itemTaxableAmount,
                'units' => $requestInstance->input('items.'.$key.'.units', null),
                'batch' => $requestInstance->input('items.'.$key.'.batch', null), //this is used by the invetory module
                // 'expiry' => $requestInstance->input('items.'.$key.'.expiry', null),
                'inventory_tracking' => ($itemModel->inventory_tracking ?? 0),
                'taxes' => $itemTaxes,
            ];
        }

        $data['taxable_amount'] = $taxableAmount;
        $data['total'] = $txnTotal;

        //print_r($data); exit;

        return $data;

    }

}
