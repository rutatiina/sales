<?php

namespace Rutatiina\Sales\Http\Controllers;

use Rutatiina\Sales\Services\SalesService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Rutatiina\Sales\Models\Sale;
use Rutatiina\Item\Traits\ItemsSelect2DataTrait;
use Rutatiina\Contact\Traits\ContactTrait;
use Yajra\DataTables\Facades\DataTables;

class SalesController extends Controller
{
    use ContactTrait;
    use ItemsSelect2DataTrait;

    public function __construct()
    {
        // $this->middleware('permission:invoices.view');
        // $this->middleware('permission:invoices.create', ['only' => ['create', 'store']]);
        // $this->middleware('permission:invoices.update', ['only' => ['edit', 'update']]);
        // $this->middleware('permission:invoices.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        //return config('app.providers');

        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $query = Sale::query();

        if ($request->contact)
        {
            $query->where(function ($q) use ($request)
            {
                $q->where('contact_id', $request->contact);
            });
        }

        $txns = $query->latest()->paginate($request->input('per_page', 20));

        return [
            'tableData' => $txns
        ];
    }

    public function create()
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $tenant = Auth::user()->tenant;

        $txnAttributes = (new Sale())->rgGetAttributes();

        $txnAttributes['tenant_id'] = $tenant->id;
        $txnAttributes['created_by'] = Auth::id();
        $txnAttributes['number'] = SalesService::nextNumber();

        $txnAttributes['status'] = 'approved';
        $txnAttributes['contact_id'] = '';
        $txnAttributes['contact'] = json_decode('{"currencies":[]}'); #required
        $txnAttributes['date'] = date('Y-m-d');
        $txnAttributes['base_currency'] = $tenant->base_currency;
        $txnAttributes['quote_currency'] = $tenant->base_currency;
        $txnAttributes['taxes'] = json_decode('{}');
        $txnAttributes['contact_notes'] = null;
        $txnAttributes['terms_and_conditions'] = null;
        $txnAttributes['items'] = [
            [
                'selectedTaxes' => [], #required
                'selectedItem' => json_decode('{}'), #required
                'displayTotal' => 0,
                'name' => '',
                'description' => '',
                'rate' => 0,
                'quantity' => 1,
                'total' => 0,
                'taxes' => [],

                'type' => '',
                'type_id' => '',
                'contact_id' => '',
                'tax_id' => '',
                'units' => '',
                'batch' => '',
                'expiry' => ''
            ]
        ];

        return [
            'pageTitle' => 'Create Sales', #required
            'pageAction' => 'Create', #required
            'txnUrlStore' => '/invoices', #required
            'txnAttributes' => $txnAttributes, #required
        ];
    }

    public function store(Request $request)
    {
        $storeService = SalesService::store($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => SalesService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Sale saved'],
            'number' => 0,
            'callback' => URL::route('sales.show', [$storeService->id], false)
        ];
    }

    public function show($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txn = Sale::findOrFail($id);
        $txn->load('contact', 'items.taxes', 'ledgers');
        $txn->setAppends([
            'taxes',
            'number_string',
            'total_in_words',
            'payment_status',
            'balance',
        ]);

        return $txn->toArray();
    }

    public function edit($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txnAttributes = SalesService::edit($id);

        return [
            'pageTitle' => 'Edit Sale', #required
            'pageAction' => 'Edit', #required
            'txnUrlStore' => '/sales/' . $id, #required
            'txnAttributes' => $txnAttributes, #required
        ];
    }

    public function update(Request $request)
    {
        //print_r($request->all()); exit;

        $storeService = SalesService::update($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => SalesService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Sale updated'],
            'number' => 0,
            'callback' => URL::route('sales.show', [$storeService->id], false)
        ];
    }

    public function destroy($id)
    {
        $destroy = SalesService::destroy($id);

        if ($destroy)
        {
            return [
                'status' => true,
                'messages' => ['Sale deleted'],
                'callback' => URL::route('invoices.index', [], false)
            ];
        }
        else
        {
            return [
                'status' => false,
                'messages' => SalesService::$errors
            ];
        }
    }

    #-----------------------------------------------------------------------------------

    public function approve($id)
    {
        $approve = SalesService::approve($id);

        if ($approve == false)
        {
            return [
                'status' => false,
                'messages' => SalesService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Sale Approved'],
        ];

    }

    public function copy($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txnAttributes = SalesService::copy($id);

        return [
            'pageTitle' => 'Copy Sale', #required
            'pageAction' => 'Copy', #required
            'txnUrlStore' => '/invoices', #required
            'txnAttributes' => $txnAttributes, #required
        ];
    }

    public function exportToExcel(Request $request)
    {

        $txns = collect([]);

        $txns->push([
            'DATE',
            'DOCUMENT#',
            'REFERENCE',
            'CUSTOMER',
            'STATUS',
            'DUE DATE',
            'TOTAL',
            'BALANCE',
            ' ', //Currency
        ]);

        foreach (array_reverse($request->ids) as $id)
        {
            $txn = Transaction::transaction($id);

            $txns->push([
                $txn->date,
                $txn->number,
                $txn->reference,
                $txn->contact_name,
                $txn->status,
                $txn->expiry_date,
                $txn->total,
                'balance' => $txn->balance,
                'base_currency' => $txn->base_currency,
            ]);
        }

        $export = $txns->downloadExcel(
            'maccounts-invoices-export-' . date('Y-m-d-H-m-s') . '.xlsx',
            null,
            false
        );

        //$books->load('author', 'publisher'); //of no use

        return $export;
    }

}
