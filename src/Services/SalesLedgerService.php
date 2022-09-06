<?php

namespace Rutatiina\Sales\Services;

use Rutatiina\Sales\Models\SalesItem;
use Rutatiina\Sales\Models\SalesLedger;
use Rutatiina\Sales\Models\SalesItemTax;
use Rutatiina\FinancialAccounting\Models\FinancialAccountLedger;

class SalesLedgerService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function store($data)
    {
        //print_r($data['ledgers']); exit;

        //Save the items >> $data['items']
        foreach ($data['ledgers'] as &$ledger)
        {
            $ledger['model_id'] = $data['id'];
            FinancialAccountLedger::create($ledger);
        }
        unset($ledger);

    }

}
