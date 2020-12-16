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
$router->group(['middleware' => 'auth:user'], function($app) {

	//For category service
	$app->get('/store/list/{id}', 'V2\Order\User\HomeController@store_list');
	$app->get('/store/cusines/{id}', 'V2\Order\User\HomeController@cusine_list');
	$app->get('/store/details/{id}', 'V2\Order\User\HomeController@store_details');
	//address
	$app->post('/store/address/add', 'V2\Common\User\HomeController@addmanageaddress');
	$app->get('/store/useraddress', 'V2\Order\User\HomeController@useraddress');
	$app->delete('/store/address/{id}', 'V2\Common\User\HomeController@deletemanageaddress');
	$app->get('/store/address/{id}', 'V2\Common\User\HomeController@editmanageaddress');
	//addons
	$app->get('/store/show-addons/{id}', 'V2\Order\User\HomeController@show_addons');
	$app->post('/store/addcart', 'V2\Order\User\HomeController@addcart');
	$app->post('/store/removecart', 'V2\Order\User\HomeController@removecart');
	$app->get('/store/cartlist', 'V2\Order\User\HomeController@viewcart');
	$app->get('/store/promocodelist', 'V2\Order\User\HomeController@promocodelist');

	$app->post('/store/checkout', 'V2\Order\User\HomeController@checkout');
	$app->get('/store/check/request', 'V2\Order\User\HomeController@status');

	$app->get('/store/order/{id}', 'V2\Order\User\HomeController@orderdetails');
	$app->post('/store/order/rating', 'V2\Order\User\HomeController@orderdetailsRating');

	$app->get('/trips-history/order', 'V2\Order\User\HomeController@tripsList');
	$app->get('/trips-history/order/{id}', 'V2\Order\User\HomeController@getOrderHistorydetails');
	$app->get('/upcoming/trips/order', 'V2\Order\User\HomeController@tripsUpcomingList');
	$app->get('/upcoming/trips/order/{id}', 'V2\Order\User\HomeController@orderdetails');

	$app->get('/dispute/order', 'V2\Order\Provider\OrderController@getUserdisputedetails');
	$app->get('/order/search/{id}', 'V2\Order\User\HomeController@search');
	$app->get('/getUserdisputedetails', 'V2\Order\Provider\OrderController@getdisputedetails'); 
	$app->post('/order_request_dispute', 'V2\Order\User\HomeController@order_request_dispute');
	$app->get('/get_order_request_dispute/{id}', 'V2\Order\User\HomeController@get_order_request_dispute');
});