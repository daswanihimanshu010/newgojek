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
	$app->get('/service_category', 'V2\Service\User\HomeController@service_category');
	$app->get('/service_sub_category/{id}', 'V2\Service\User\HomeController@service_sub_category');
    $app->get('/services/{id}/{ids}', 'V2\Service\User\HomeController@service');
    $app->get('/service_city_price/{id}', 'V2\Service\User\HomeController@service_city_price');

	$app->get('/list', 'V2\Service\User\ServiceController@providerServiceList');
	$app->get('/review/{id}', 'V2\Service\User\ServiceController@review');
	$app->get('/servicelist/{id}', 'V2\Service\User\ServiceController@service');
	$app->post('/cancelrequest/{id}', 'V2\Service\User\ServiceController@cancel_request');

	$app->post('service/send/request', 'V2\Service\User\ServiceController@create_service');
	$app->get('/service/check/request', 'V2\Service\User\ServiceController@status');
	$app->post('/service/cancel/request', 'V2\Service\User\ServiceController@cancel_service');
	$app->post('/service/rate', 'V2\Service\User\ServiceController@rate'); 
    $app->post('/service/payment', 'V2\Service\User\ServiceController@payment');

    $app->post('/service/update/payment', 'V2\Service\User\ServiceController@update_payment_method');


	$app->get('/promocode', 'V2\Service\User\ServiceController@promocode');
	$app->post('/update/service/{id}', 'V2\Service\User\ServiceController@update_service');

	//History details
	$app->get('/trips-history/service', 'V2\Service\User\HomeController@trips');
	$app->get('/trips-history/service/{id}', 'V2\Service\User\HomeController@gettripdetails');
	$app->get('/upcoming/trips/service', 'V2\Service\User\HomeController@upcoming_trips');
	$app->get('/upcoming/trips/service/{id}', 'V2\Service\User\HomeController@getupcomingtrips');
	$app->get('/getdisputedetails', 'V2\Service\User\HomeController@getdisputedetails');
	$app->get('/dispute/service', 'V2\Service\User\HomeController@getUserdisputedetails');
	$app->get('/get_service_request_dispute/{id}', 'V2\Service\User\HomeController@get_service_request_dispute');
	$app->post('/service_request_dispute/{id}', 'V2\Service\User\HomeController@service_request_dispute');
});