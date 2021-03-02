<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('accessToken','Api\ZohoApiController@generateAccessToken');
Route::get('createSaleOrder','Api\ZohoApiController@createSaleOrder');
Route::get('createInvoice','Api\ZohoApiController@createInvoice');
Route::get('createCustomer','Api\ZohoApiController@createCustomer');
Route::get('Customers','Api\ZohoApiController@getAllOrganizationCustomers');
Route::get('create','Api\ZohoApiController@runAllApisCycle');
Route::get('getAdmins','Api\ZohoApiController@getAdmins');
Route::get('build','Api\ZohoApiController@buildEmailsArrayAsString');

