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

$router->post('/login', 'V2\Common\User\UserAuthController@login');
$router->post('/verify', 'V2\Common\User\UserAuthController@verify');

$router->post('/signup', 'V2\Common\User\UserAuthController@signup');

$router->post('/refresh', 'V2\Common\User\UserAuthController@refresh');
$router->post('/forgot/otp', 'V2\Common\User\UserAuthController@forgotPasswordOTP');
$router->post('/reset/otp', 'V2\Common\User\UserAuthController@resetPasswordOTP');
$router->get('/logout', 'V2\Common\User\UserAuthController@logout');
$router->post('countries', 'V2\Common\User\HomeController@countries');

$router->group(['middleware' => 'auth:user'], function($app) {

    $app->get('cities', 'V2\Common\User\HomeController@cities');
    $app->get('promocodes', 'V2\Common\User\HomeController@promocode');

    $app->get('/reasons', 'V2\Common\User\HomeController@reasons');

    $app->get('/ongoing', 'V2\Common\User\HomeController@ongoing_services');

	$app->get('/users', function() {
        return response()->json([
            'message' => \Auth::guard('user')->user(),
        ]);
    });

    $app->post('/logout', 'V2\Common\User\UserAuthController@logout');

    $app->get('/chat', 'V2\Common\User\HomeController@get_chat');

    $app->get('/menus', 'V2\Common\User\HomeController@index');
    $app->post('/address/add', 'V2\Common\User\HomeController@addmanageaddress');
    $app->patch('/address/update', 'V2\Common\User\HomeController@updatemanageaddress');
    $app->get('/address', 'V2\Common\User\HomeController@listmanageaddress');
    $app->delete('/address/{id}', 'V2\Common\User\HomeController@deletemanageaddress');

	$app->get('/profile', 'V2\Common\User\HomeController@show_profile');
    $app->post('/profile', 'V2\Common\User\HomeController@update_profile');
    $app->post('password', 'V2\Common\User\HomeController@password_update');
    $app->post('card', 'V2\Common\User\HomeController@addcard');
    $app->get('card', 'V2\Common\User\HomeController@carddetail');
    $app->get('walletlist', 'V2\Common\User\HomeController@userlist');
    $app->delete('card/{id}', 'V2\Common\User\HomeController@deleteCard');
    $app->post('/add/money', 'V2\Common\PaymentController@add_money');
    $app->get('/payment/response', 'V2\Common\User\PaymentController@response');
    $app->get('/payment/failure', 'V2\Common\User\PaymentController@failure');
    $app->get('/wallet', 'V2\Common\User\HomeController@walletlist');
    $app->get('/orderstatus', 'V2\Common\User\HomeController@order_status');
    $app->post('/updatelanguage', 'V2\Common\User\HomeController@updatelanguage');
    $app->get('/service/{id}', 'V2\Common\User\HomeController@service');
    $app->get('/service_city_price/{id}', 'V2\Common\User\HomeController@service_city_price');
    $app->get('/notification', 'V2\Common\User\HomeController@notification');
    $app->get('/promocode/{service}', 'V2\Common\User\HomeController@listpromocode');
    $app->post('/city', 'V2\Common\User\HomeController@city');
    $app->post('/defaultcard', 'V2\Common\User\HomeController@defaultcard');
  

});

$router->post('/account/kit', 'V2\Common\User\SocialLoginController@account_kit');