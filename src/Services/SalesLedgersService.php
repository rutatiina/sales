<?php

namespace Rutatiina\Sales\Services;

use Rutatiina\Sales\Models\SalesItem;
use Rutatiina\Sales\Models\SalesItemTax;
use Rutatiina\Sales\Models\SalesLedger;

class SalesLedgersService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function store($data)
    {
        foreach ($data['ledgers'] as &$ledger)
        {
            $ledger['sales_id'] = $data['id'];
            SalesLedger::create($ledger);
        }
        unset($ledger);

    }

}
