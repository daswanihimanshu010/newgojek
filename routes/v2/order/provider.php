<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/


$router->group(['middleware' => 'auth:provider'], function($app) {
	
	$app->get('/shoptype', 'V2\Order\Provider\HomeController@shoptype'); 

	$app->get('/check/order/request', 'V2\Order\Provider\OrderController@index');
	$app->post('/update/order/request', 'V2\Order\Provider\OrderController@updateOrderStaus');
	$app->patch('/update/order/request', 'V2\Order\Provider\OrderController@updateOrderStaus');
	$app->post('/cancel/order/request', 'V2\Order\Provider\OrderController@cancelOrdered');
	$app->post('/rate/order', 'V2\Order\Provider\OrderController@rate');
	$app->get('/history/order', 'V2\Order\Provider\OrderController@historyList');
	$app->get('/history/order/{id}', 'V2\Order\Provider\OrderController@getServiceHistorydetails');
	$app->get('/history-dispute/order/{id}', 'V2\Order\Provider\OrderController@getServiceRequestDispute');
	$app->post('/history-dispute/order', 'V2\Order\Provider\OrderController@saveServiceRequestDispute');
	$app->get('/getdisputedetails', 'V2\Order\Provider\OrderController@getdisputedetails');
	$app->get('/dispute/order', 'V2\Order\Provider\OrderController@getdisputedetails');
});