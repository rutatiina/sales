<?php

Route::group(['middleware' => ['web', 'auth', 'tenant', 'service.accounting']], function() {

	Route::prefix('sales')->group(function () {

        //Route::get('summary', 'Rutatiina\Sales\Http\Controllers\SalesController@summary');
        Route::post('export-to-excel', 'Rutatiina\Sales\Http\Controllers\SalesController@exportToExcel');
        Route::post('{id}/approve', 'Rutatiina\Sales\Http\Controllers\SalesController@approve');
        //Route::post('contact-invoices', 'Rutatiina\Sales\Http\Controllers\Sales\ReceiptController@invoices');
        Route::get('{id}/copy', 'Rutatiina\Sales\Http\Controllers\SalesController@copy');

    });

    Route::resource('sales/settings', 'Rutatiina\Sales\Http\Controllers\SalesSettingsController');
    Route::resource('sales', 'Rutatiina\Sales\Http\Controllers\SalesController');

});