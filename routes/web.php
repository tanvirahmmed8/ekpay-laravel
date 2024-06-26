<?php

use Illuminate\Support\Facades\Route;



Route::get('pay', 'EkpayPaymentController@pay');
Route::get('success', 'EkpayPaymentController@success');
Route::get('fail', 'EkpayPaymentController@fail');
Route::get('cancel', 'EkpayPaymentController@cancel');

Route::post('ipn', 'EkpayPaymentController@ipn');


