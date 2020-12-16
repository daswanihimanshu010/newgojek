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

	$app->get('/transport/services', 'V2\Transport\User\RideController@services');

	$app->post('/transport/estimate', 'V2\Transport\User\RideController@estimate');

	$app->post('/transport/send/request', 'V2\Transport\User\RideController@create_ride');

	$app->get('/transport/check/request', 'V2\Transport\User\RideController@status');

	$app->post('/transport/cancel/request', 'V2\Transport\User\RideController@cancel_ride');

	$app->post('/transport/extend/trip', 'V2\Transport\User\RideController@extend_trip');

	$app->post('/transport/rate', 'V2\Transport\User\RideController@rate'); 

    $app->post('/transport/payment', 'V2\Transport\User\RideController@payment');

    $app->post('/transport/update/payment', 'V2\Transport\User\RideController@update_payment_method');

    // $app->get('/trips', 'V2\Transport\User\HomeController@trips');
	// $app->get('/trips/{id}', 'V2\Transport\User\HomeController@gettripdetails');
	$app->get('/trips-history/transport', 'V2\Transport\User\HomeController@trips');
	$app->get('/trips-history/transport/{id}', 'V2\Transport\User\HomeController@gettripdetails');
	$app->get('/upcoming/trips/transport', 'V2\Transport\User\HomeController@upcoming_trips');
	$app->get('/upcoming/trips/transport/{id}', 'V2\Transport\User\HomeController@getupcomingtrips');
	// $app->get('/upcoming/trips', 'V2\Transport\User\HomeController@upcoming_trips');
	// $app->get('/upcoming/trips/{id}', 'V2\Transport\User\HomeController@getupcomingtrips');
	$app->post('/ride/dispute', 'V2\Transport\User\HomeController@ride_request_dispute');
	$app->post('/ride/lostitem', 'V2\Transport\User\HomeController@ride_lost_item');
	$app->get('/ride/dispute', 'V2\Transport\User\HomeController@getUserdisputedetails');
	$app->get('/ride/dispute/{id}', 'V2\Transport\User\HomeController@get_ride_request_dispute');
	$app->get('/ride/lostitem/{id}', 'V2\Transport\User\HomeController@get_ride_lost_item');
	$app->get('/ride/packages', 'V2\Transport\User\HomeController@getPackagesList');
});