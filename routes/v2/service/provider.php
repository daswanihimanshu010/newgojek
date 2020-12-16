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
	$app->get('/providerservice/categories', 'V2\Service\Provider\HomeController@categories');
	$app->post('/providerservice/subcategories', 'V2\Service\Provider\HomeController@subcategories');
	$app->post('/providerservice/service', 'V2\Service\Provider\HomeController@service');
	$app->get('/totalservices', 'V2\Service\Provider\HomeController@totalservices');
	$app->get('/check/serve/request', 'V2\Service\Provider\ServeController@index');
	$app->post('/update/serve/request', 'V2\Service\Provider\ServeController@updateServe');
	$app->patch('/update/serve/request', 'V2\Service\Provider\ServeController@updateServe');
	$app->post('/cancel/serve/request', 'V2\Service\Provider\ServeController@cancelServe');
	$app->post('/rate/serve', 'V2\Service\Provider\ServeController@rate');
	$app->get('/history/service', 'V2\Service\Provider\ServeController@historyList');
	$app->get('/history/service/{id}', 'V2\Service\Provider\ServeController@getServiceHistorydetails');
	$app->get('/history-dispute/service/{id}', 'V2\Service\Provider\ServeController@getServiceRequestDispute');
	$app->post('/history-dispute/service/{id}', 'V2\Service\Provider\ServeController@saveServiceRequestDispute');
	// $app->post('/service_request_dispute/{id}', 'V2\Service\Provider\ServeController@saveServiceRequestDispute');
	// $app->get('/get_service_request_dispute/{id}', 'V2\Service\Provider\ServeController@getServiceRequestDispute');
	$app->get('/getdisputedetails', 'V2\Service\Provider\ServeController@getdisputedetails');
	$app->get('/dispute/service', 'V2\Service\Provider\ServeController@getdisputedetails');
	
	
});