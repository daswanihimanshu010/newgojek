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

$router->get('/', function () use ($router) {
	//return $router->app->version();
    return view('index');
}); 

$router->post('verify', 'LicenseController@verify');


$router->get('cmspage/{type}', 'V2\Common\CommonController@cmspagetype');

$router->group(['prefix' => 'api/v2'], function ($app) {

	$app->post('user/appsettings', 'V2\Common\CommonController@base');

	$app->post('provider/appsettings', 'V2\Common\CommonController@base');

	$app->get('countries', 'V2\Common\CommonController@countries_list');

	$app->get('states/{id}', 'V2\Common\CommonController@states_list');

	$app->get('cities/{id}', 'V2\Common\CommonController@cities_list');

	$app->post('/{provider}/social/login', 'V2\Common\SocialLoginController@handleSocialLogin');

	$app->post('/chat', 'V1\Common\CommonController@chat');

});

$router->get('/send/{type}/push', 'V2\Common\SocialLoginController@push');

/*$router->get('v2/docs', ['as' => 'swagger-lume.docs', 'middleware' => config('swagger-lume.routes.middleware.docs', []), 'uses' => 'V2\Common\SwaggerController@docs']);

$router->get('/api/v2/documentation', ['as' => 'swagger-lume.api', 'middleware' => config('swagger-lume.routes.middleware.api', []), 'uses' => 'V2\Common\SwaggerController@api']);*/