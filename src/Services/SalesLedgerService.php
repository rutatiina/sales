<?php

namespace Rutatiina\Sales\Services;

use Rutatiina\Sales\Models\SaleItem;
use Rutatiina\Sales\Models\SaleItemTax;
use Rutatiina\Sales\Models\SaleLedger;

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
            $ledger['invoice_id'] = $data['id'];
            SaleLedger::create($ledger);
        }
        unset($ledger);

    }

}
