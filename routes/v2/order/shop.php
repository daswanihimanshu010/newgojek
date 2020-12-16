<?php

$router->post('/login', 'V2\Order\Shop\Auth\AuthController@login');

$router->post('/refresh', 'V2\Order\Shop\Auth\AuthController@refresh');
$router->post('/forgotOtp', 'V2\Order\Shop\Auth\AuthController@forgotPasswordOTP');
$router->post('/resetOtp', 'V2\Order\Shop\Auth\AuthController@resetPasswordOTP');
$router->get('/dispatcher/autosign', 'V2\Order\Shop\Auth\AdminController@StoreAutoAssign');

$router->group(['middleware' => 'auth:shop'], function ($app) {

//Shops Add on
$app->get('/addon/{id}', 'V2\Order\Admin\Resource\ShopsaddonController@index');
$app->post('/addons', 'V2\Order\Admin\Resource\ShopsaddonController@store');
$app->get('/addons/{id}', 'V2\Order\Admin\Resource\ShopsaddonController@show');
$app->patch('/addons/{id}', 'V2\Order\Admin\Resource\ShopsaddonController@update');
$app->delete('/addons/{id}', 'V2\Order\Admin\Resource\ShopsaddonController@destroy'); 
$app->get('/addonslist/{id}', 'V2\Order\Admin\Resource\ShopsaddonController@addonlist');
$app->get('/addon/{id}/updateStatus', 'V2\Order\Admin\Resource\ShopsaddonController@updateStatus'); 

//Shops Category
$app->get('/categoryindex/{id}', 'V2\Order\Admin\Resource\ShopscategoryController@index');
$app->post('/category', 'V2\Order\Admin\Resource\ShopscategoryController@store');
$app->get('/category/{id}', 'V2\Order\Admin\Resource\ShopscategoryController@show');
$app->patch('/category/{id}', 'V2\Order\Admin\Resource\ShopscategoryController@update');
$app->delete('/category/{id}', 'V2\Order\Admin\Resource\ShopscategoryController@destroy');
$app->get('/categorylist/{id}', 'V2\Order\Admin\Resource\ShopscategoryController@categorylist');
$app->get('/category/{id}/updateStatus', 'V2\Order\Admin\Resource\ShopscategoryController@updateStatus');

//Shpos Items

$app->get('/itemsindex/{id}', 'V2\Order\Admin\Resource\ShopsitemsController@index');
$app->post('/items', 'V2\Order\Admin\Resource\ShopsitemsController@store');
$app->get('/items/{id}', 'V2\Order\Admin\Resource\ShopsitemsController@show');
$app->patch('/items/{id}', 'V2\Order\Admin\Resource\ShopsitemsController@update');
$app->delete('/items/{id}', 'V2\Order\Admin\Resource\ShopsitemsController@destroy');
$app->get('/items/{id}/updateStatus', 'V2\Order\Admin\Resource\ShopsitemsController@updateStatus');


// Store Types	
$app->get('/storetypelist', 'V2\Order\Admin\Resource\StoretypeController@storetypelist'); 	

//zone
$app->get('/zonetype/{id}', 'V2\Common\Admin\Resource\ZoneController@cityzonestype');

//cuisine
$app->get('/cuisinelist/{id}', 'V2\Order\Admin\Resource\CuisinesController@cuisinelist');
//shop
$app->get('/shops/{id}', 'V2\Order\Admin\Resource\ShopsController@show');
$app->patch('/shops/{id}', 'V2\Order\Admin\Resource\ShopsController@update');  

//Account setting details
Route::get('password', 'V2\Order\Shop\Auth\AdminController@password');
Route::post('password', 'V2\Order\Shop\Auth\AdminController@password_update');
Route::get('bankdetails/template', 'V2\Common\Provider\HomeController@template');
$app->post('/addbankdetails', 'V2\Common\Provider\HomeController@addbankdetails'); 
$app->post('/editbankdetails', 'V2\Common\Provider\HomeController@editbankdetails');

//Dispatcher Panel
$app->get('/dispatcher/orders', 'V2\Order\Shop\Auth\AdminController@orders');
$app->post('/dispatcher/cancel', 'V2\Order\Shop\Auth\AdminController@cancel_orders');
$app->post('/dispatcher/accept', 'V2\Order\Shop\Auth\AdminController@accept_orders');
$app->post('/dispatcher/pickedup', 'V2\Order\Shop\Auth\AdminController@picked_up');

//Wallet
$app->get('/wallet', 'V2\Order\Shop\Auth\AdminController@wallet');

//logout
$app->post('/logout', 'V2\Order\Shop\Auth\AuthController@logout'); 

//Dashboard
$app->get('total/storeorder', 'V2\Order\Shop\Auth\AdminController@total_orders');


});

