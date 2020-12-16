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

$router->post('/login', 'V2\Common\Provider\ProviderAuthController@login');
$router->post('/verify', 'V2\Common\Provider\ProviderAuthController@verify');

$router->post('/signup', 'V2\Common\Provider\ProviderAuthController@signup');

$router->post('/refresh', 'V2\Common\Provider\ProviderAuthController@refresh');
$router->post('/forgot/otp', 'V2\Common\Provider\ProviderAuthController@forgotPasswordOTP');
$router->post('/reset/otp', 'V2\Common\Provider\ProviderAuthController@resetPasswordOTP');


$router->post('countries', 'V2\Common\Provider\HomeController@countries');

$router->post('cities/{id}', 'V2\Common\Provider\HomeController@cities');

$router->group(['middleware' => 'auth:provider'], function($app) {

    $app->post('/logout', 'V2\Common\Provider\ProviderAuthController@logout');

    $app->get('/chat', 'V2\Common\Provider\HomeController@get_chat');

    $app->post('/chat', 'V2\Common\Provider\HomeController@chat');

    $app->get('/check/request', 'V2\Common\Provider\HomeController@index');

    $app->post('/accept/request', 'V2\Common\Provider\HomeController@accept_request');

    // $app->get('/check/serve/request', 'V2\Service\Provider\ServeController@index');

    $app->post('/cancel/request', 'V2\Common\Provider\HomeController@cancel_request');

    $app->post('/listdocuments', 'V2\Common\Provider\ProviderAuthController@listdocuments');

    $app->post('/documents', 'V2\Common\Provider\ProviderAuthController@document_store');
    
    $app->get('/profile', 'V2\Common\Provider\HomeController@show_profile');
    $app->post('/profile', 'V2\Common\Provider\HomeController@update_profile');
    $app->post('/password', 'V2\Common\Provider\HomeController@password_update');

    $app->post('/card', 'V2\Common\Provider\HomeController@addcard');
    $app->get('card', 'V2\Common\Provider\HomeController@carddetail');
    $app->get('list', 'V2\Common\Provider\HomeController@providerlist');
    $app->delete('card/{id}', 'V2\Common\Provider\HomeController@deleteCard');
    $app->post('/add/money', 'V2\Common\PaymentController@add_money');
    $app->get('/payment/response', 'V2\Common\Provider\PaymentController@response');
    $app->get('/payment/failure', 'V2\Common\Provider\PaymentController@failure');
    $app->get('/wallet', 'V2\Common\Provider\HomeController@walletlist');
    $app->get('services/list', 'V2\Common\Provider\HomeController@provider_services');
    

    $app->post('/vehicle', 'V2\Common\Provider\HomeController@add_vehicle');
    $app->delete('providerdocument/{id}', 'V2\Common\Provider\HomeController@deleteproviderdocument');
    $app->post('/service', 'V2\Common\Provider\HomeController@add_service');
    $app->get('/vehicle', 'V2\Common\Provider\HomeController@vehicle_list');
    $app->get('/orderstatus', 'V2\Common\Provider\HomeController@order_status');
    $app->post('/vechile/add', 'V2\Common\Provider\HomeController@addvechile');
    $app->post('/vechile/addservice', 'V2\Common\Provider\HomeController@addproviderservice');
    $app->post('/vechile/editservice', 'V2\Common\Provider\HomeController@editproviderservice');
    $app->post('/vehicle/edit', 'V2\Common\Provider\HomeController@editvechile');
    $app->get('/reasons', 'V2\Common\Provider\HomeController@reasons');
    $app->post('/updatelanguage', 'V2\Common\Provider\HomeController@updatelanguage');
    $app->get('/adminservices', 'V2\Common\Provider\HomeController@adminservices');
    $app->get('/notification', 'V2\Common\Provider\HomeController@notification');
    $app->get('/bankdetails/template', 'V2\Common\Provider\HomeController@template');
    $app->post('/addbankdetails', 'V2\Common\Provider\HomeController@addbankdetails');
    $app->post('/editbankdetails', 'V2\Common\Provider\HomeController@editbankdetails');
    $app->post('/referemail', 'V2\Common\Provider\HomeController@refer_email');
    $app->post('/defaultcard', 'V2\Common\Provider\HomeController@defaultcard');
    $app->get('/onlinestatus/{id}', 'V2\Common\Provider\HomeController@onlinestatus');
    $app->post('/updatelocation', 'V2\Common\Provider\HomeController@updatelocation');
    $app->get('/earnings/{id}', 'V2\Common\Provider\HomeController@totalEarnings');
    $app->get('/providers', function() {
        return response()->json([
            'message' => \Auth::guard('provider')->user(), 
        ]);
    });
	
});



$router->post('/clear', 'V2\Common\Provider\HomeController@clear');