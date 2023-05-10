<?php
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web', 'auth', 'tenant', 'service.accounting']], function() {

	Route::prefix('sales')->group(function () {

        Route::post('routes', 'Rutatiina\Sales\Http\Controllers\SalesController@routes')->name('sales.routes');
        //Route::get('summary', 'Rutatiina\Sales\Http\Controllers\SalesController@summary');
        Route::post('export-to-excel', 'Rutatiina\Sales\Http\Controllers\SalesController@exportToExcel');
        Route::post('{id}/approve', 'Rutatiina\Sales\Http\Controllers\SalesController@approve');
        //Route::post('contact-sales', 'Rutatiina\Sales\Http\Controllers\Sales\ReceiptController@sales');
        Route::get('{id}/copy', 'Rutatiina\Sales\Http\Controllers\SalesController@copy');
        Route::delete('delete', 'Rutatiina\Sales\Http\Controllers\SalesController@delete')->name('sales.delete');
        Route::delete('cancel', 'Rutatiina\Sales\Http\Controllers\SalesController@cancel')->name('sales.cancel');

    });

    Route::resource('sales/settings', 'Rutatiina\Sales\Http\Controllers\SalesSettingsController');
    Route::resource('sales', 'Rutatiina\Sales\Http\Controllers\SalesController');

});