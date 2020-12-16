<?php

$router->post('/login', 'V2\Common\Admin\Auth\AdminAuthController@login');

$router->post('/refresh', 'V2\Common\Admin\Auth\AdminAuthController@refresh');

$router->post('/forgotOtp', 'V2\Common\Admin\Auth\AdminAuthController@forgotPasswordOTP');
$router->post('/resetOtp', 'V2\Common\Admin\Auth\AdminAuthController@resetPasswordOTP');

$router->group(['middleware' => 'auth:admin'], function ($app) {

    $app->post('/permission_list', 'V2\Common\Admin\Auth\AdminAuthController@permission_list');

    $app->get('/users', 'V2\Common\Admin\Resource\UserController@index');

    $app->post('/users', 'V2\Common\Admin\Resource\UserController@store');

    $app->get('/users/{id}', 'V2\Common\Admin\Resource\UserController@show');

    $app->patch('/users/{id}', 'V2\Common\Admin\Resource\UserController@update');

    $app->delete('/users/{id}', 'V2\Common\Admin\Resource\UserController@destroy');

    $app->get('/users/{id}/updateStatus', 'V2\Common\Admin\Resource\UserController@updateStatus');

    $app->get('/{type}/logs/{id}', 'V2\Common\CommonController@logdata');

    $app->get('/{type}/wallet/{id}', 'V2\Common\CommonController@walletDetails');

    $app->post('/logout', 'V2\Common\Admin\Auth\AdminAuthController@logout');

    $app->get('/services/main/list', 'V2\Common\CommonController@admin_services');

    $app->get('/services/list/{id}', 'V2\Common\Admin\Resource\ProviderController@provider_services');


    //Document
    $app->get('/document', 'V2\Common\Admin\Resource\DocumentController@index');

    $app->post('/document', 'V2\Common\Admin\Resource\DocumentController@store');

    $app->get('/document/{id}', 'V2\Common\Admin\Resource\DocumentController@show');

    $app->patch('/document/{id}', 'V2\Common\Admin\Resource\DocumentController@update');

    $app->delete('/document/{id}', 'V2\Common\Admin\Resource\DocumentController@destroy');

    $app->get('/document/{id}/updateStatus', 'V2\Common\Admin\Resource\DocumentController@updateStatus');

    //Notification
    $app->get('/notification', 'V2\Common\Admin\Resource\NotificationController@index');

    $app->post('/notification', 'V2\Common\Admin\Resource\NotificationController@store');

    $app->get('/notification/{id}', 'V2\Common\Admin\Resource\NotificationController@show');

    $app->patch('/notification/{id}', 'V2\Common\Admin\Resource\NotificationController@update');

    $app->delete('/notification/{id}', 'V2\Common\Admin\Resource\NotificationController@destroy');


    //Reason
    $app->get('/reason', 'V2\Common\Admin\Resource\ReasonController@index');

    $app->post('/reason', 'V2\Common\Admin\Resource\ReasonController@store');

    $app->get('/reason/{id}', 'V2\Common\Admin\Resource\ReasonController@show');

    $app->patch('/reason/{id}', 'V2\Common\Admin\Resource\ReasonController@update');

    $app->delete('/reason/{id}', 'V2\Common\Admin\Resource\ReasonController@destroy');

    //Fleet
    $app->get('/fleet', 'V2\Common\Admin\Resource\FleetController@index');

    $app->post('/fleet', 'V2\Common\Admin\Resource\FleetController@store');

    $app->get('/fleet/{id}', 'V2\Common\Admin\Resource\FleetController@show');

    $app->patch('/fleet/{id}', 'V2\Common\Admin\Resource\FleetController@update');

    $app->delete('/fleet/{id}', 'V2\Common\Admin\Resource\FleetController@destroy');

    $app->get('/fleet/{id}/updateStatus', 'V2\Common\Admin\Resource\FleetController@updateStatus');
    $app->post('card', 'V2\Common\Admin\Resource\FleetController@addcard');
    $app->get('card', 'V2\Common\Admin\Resource\FleetController@card');
    $app->post('add/money', 'V2\Common\Admin\Resource\FleetController@wallet');
    // $app->get('wallet', 'V2\Common\Admin\Resource\FleetController@wallet');
    $app->get('adminfleet/wallet', 'V2\Common\Admin\Resource\FleetController@wallet');

    //Dispatcher Panel
    $app->get('/dispatcher/trips', 'V2\Common\Admin\Resource\DispatcherController@trips');

    //Dispatcher
    $app->get('/dispatcher', 'V2\Common\Admin\Resource\DispatcherController@index');

    $app->post('/dispatcher', 'V2\Common\Admin\Resource\DispatcherController@store');

    $app->get('/dispatcher/{id}', 'V2\Common\Admin\Resource\DispatcherController@show');

    $app->patch('/dispatcher/{id}', 'V2\Common\Admin\Resource\DispatcherController@update');

    $app->delete('/dispatcher/{id}', 'V2\Common\Admin\Resource\DispatcherController@destroy');

    $app->get('/dispatcher/get/providers', 'V2\Common\Admin\Resource\DispatcherController@providers');

    $app->post('/dispatcher/assign', 'V2\Common\Admin\Resource\DispatcherController@assign');

    $app->post('/dispatcher/ride/request', 'V2\Common\Admin\Resource\DispatcherController@create_ride');

    $app->post('/dispatcher/ride/cancel', 'V2\Common\Admin\Resource\DispatcherController@cancel_ride');

    $app->post('/dispatcher/service/request', 'V2\Common\Admin\Resource\DispatcherController@create_service');

    $app->post('/dispatcher/service/cancel', 'V2\Common\Admin\Resource\DispatcherController@cancel_service');

    $app->get('/fare' , 'V2\Common\Admin\Resource\DispatcherController@fare');

    //Account Manager
    $app->get('/accountmanager', 'V2\Common\Admin\Resource\AccountManagerController@index');

    $app->post('/accountmanager', 'V2\Common\Admin\Resource\AccountManagerController@store');

    $app->get('/accountmanager/{id}', 'V2\Common\Admin\Resource\AccountManagerController@show');

    $app->patch('/accountmanager/{id}', 'V2\Common\Admin\Resource\AccountManagerController@update');

    $app->delete('/accountmanager/{id}', 'V2\Common\Admin\Resource\AccountManagerController@destroy');
    

    //Promocodes
    $app->get('/promocode', 'V2\Common\Admin\Resource\PromocodeController@index');

    $app->post('/promocode', 'V2\Common\Admin\Resource\PromocodeController@store');

    $app->get('/promocode/{id}', 'V2\Common\Admin\Resource\PromocodeController@show');

    $app->patch('/promocode/{id}', 'V2\Common\Admin\Resource\PromocodeController@update');

    $app->delete('/promocode/{id}', 'V2\Common\Admin\Resource\PromocodeController@destroy');

    //Dispute
    $app->get('/dispute_list', 'V2\Common\Admin\Resource\DisputeController@index');

    $app->post('/dispute', 'V2\Common\Admin\Resource\DisputeController@store');

    $app->get('/dispute/{id}', 'V2\Common\Admin\Resource\DisputeController@show');

    $app->patch('/dispute/{id}', 'V2\Common\Admin\Resource\DisputeController@update');

    $app->delete('/dispute/{id}', 'V2\Common\Admin\Resource\DisputeController@destroy');
    //Provider
    $app->get('/provider', 'V2\Common\Admin\Resource\ProviderController@index');

    $app->post('/provider', 'V2\Common\Admin\Resource\ProviderController@store');

    $app->get('/provider/{id}', 'V2\Common\Admin\Resource\ProviderController@show');

    $app->patch('/provider/{id}', 'V2\Common\Admin\Resource\ProviderController@update');

    $app->delete('/provider/{id}', 'V2\Common\Admin\Resource\ProviderController@destroy');

    $app->get('/provider/{id}/updateStatus', 'V2\Common\Admin\Resource\ProviderController@updateStatus');
    $app->get('/provider/approve/{id}', 'V2\Common\Admin\Resource\ProviderController@approveStatus');
    $app->get('/provider/zoneapprove/{id}', 'V2\Common\Admin\Resource\ProviderController@zoneStatus');
   
    //sub admin

    $app->get('/subadminlist/{type}', 'V2\Common\Admin\Resource\AdminController@index');

    $app->post('/subadmin', 'V2\Common\Admin\Resource\AdminController@store');

    $app->get('/subadmin/{id}', 'V2\Common\Admin\Resource\AdminController@show');

    $app->patch('/subadmin/{id}', 'V2\Common\Admin\Resource\AdminController@update');

    $app->delete('/subadmin/{id}', 'V2\Common\Admin\Resource\AdminController@destroy');

    $app->get('/subadmin/{id}/updateStatus', 'V2\Common\Admin\Resource\AdminController@updateStatus');

    

    $app->get('/heatmap', 'V2\Common\Admin\Resource\AdminController@heatmap');


    $app->get('/role_list', 'V2\Common\Admin\Resource\AdminController@role_list');
 
    //cmspages
    $app->get('/cmspage', 'V2\Common\Admin\Resource\CmsController@index');

    $app->post('/cmspage', 'V2\Common\Admin\Resource\CmsController@store');

    $app->get('/cmspage/{id}', 'V2\Common\Admin\Resource\CmsController@show');

    $app->patch('/cmspage/{id}', 'V2\Common\Admin\Resource\CmsController@update');

    $app->delete('/cmspage/{id}', 'V2\Common\Admin\Resource\CmsController@destroy');

    //custom push
    $app->get('/custompush', 'V2\Common\Admin\Resource\CustomPushController@index');

    $app->post('/custompush', 'V2\Common\Admin\Resource\CustomPushController@store');

    $app->get('/custompush/{id}', 'V2\Common\Admin\Resource\CustomPushController@show');

    $app->patch('/custompush/{id}', 'V2\Common\Admin\Resource\CustomPushController@update');

    $app->delete('/custompush/{id}', 'V2\Common\Admin\Resource\CustomPushController@destroy');

    //Provider add vehicle
    $app->get('/ProviderService/{id}', 'V2\Common\Admin\Resource\ProviderController@ProviderService');
    $app->patch('/vehicle_type', 'V2\Common\Admin\Resource\ProviderController@vehicle_type');
    $app->get('/service_on/{id}', 'V2\Common\Admin\Resource\ProviderController@service_on');
    $app->get('/service_off/{id}', 'V2\Common\Admin\Resource\ProviderController@service_off');
    $app->get('/deleteservice/{id}', 'V2\Common\Admin\Resource\ProviderController@deleteservice');
    //Provider view document
    $app->get('/provider/{id}/view_document', 'V2\Common\Admin\Resource\ProviderController@view_document');
    $app->get('/provider/approve_image/{id}', 'V2\Common\Admin\Resource\ProviderController@approve_image');
    $app->get('/provider/approveall/{id}', 'V2\Common\Admin\Resource\ProviderController@approve_all');
    $app->delete('/provider/delete_view_image/{id}', 'V2\Common\Admin\Resource\ProviderController@delete_view_image');
    //CompanyCountry
    $app->get('/providerdocument/{id}', 'V2\Common\Admin\Resource\ProviderController@providerdocument');

    $app->get('/companycountries', 'V2\Common\Admin\Resource\CompanyCountriesController@index');
    $app->post('/companycountries', 'V2\Common\Admin\Resource\CompanyCountriesController@store');

    $app->get('/companycountries/{id}', 'V2\Common\Admin\Resource\CompanyCountriesController@show');

    $app->patch('/companycountries/{id}', 'V2\Common\Admin\Resource\CompanyCountriesController@update');

    $app->delete('/companycountries/{id}', 'V2\Common\Admin\Resource\CompanyCountriesController@destroy');

    $app->get('/companycountries/{id}/updateStatus', 'V2\Common\Admin\Resource\CompanyCountriesController@updateStatus');

    $app->get('/companycountries/{id}/bankform', 'V2\Common\Admin\Resource\CompanyCountriesController@getBankForm');

    $app->post('/bankform', 'V2\Common\Admin\Resource\CompanyCountriesController@storeBankform');

    //country list
    $app->get('/countries', 'V2\Common\Admin\Resource\CompanyCountriesController@countries');
    $app->get('/states/{id}', 'V2\Common\Admin\Resource\CompanyCountriesController@states');
    $app->get('/cities/{id}', 'V2\Common\Admin\Resource\CompanyCountriesController@cities');
    $app->get('/cities-by-country/{id}', 'V2\Common\Admin\Resource\CompanyCountriesController@citiesByCountry');
    $app->get('/company_country_list', 'V2\Common\Admin\Resource\CompanyCountriesController@companyCountries');
    $app->get('/vehicle_type_list', 'V2\Transport\Admin\VehicleController@vehicletype');
    //$app->get('/gettaxiprice/{id}', 'V2\Transport\Admin\VehicleController@gettaxiprice');

    //CompanyCity
    $app->get('/companycityservice', 'V2\Common\Admin\Resource\CompanyCitiesController@index');

    $app->post('/companycityservice', 'V2\Common\Admin\Resource\CompanyCitiesController@store');

    $app->get('/companycityservice/{id}', 'V2\Common\Admin\Resource\CompanyCitiesController@show');

    $app->patch('/companycityservice/{id}', 'V2\Common\Admin\Resource\CompanyCitiesController@update');

    $app->delete('/companycityservice/{id}', 'V2\Common\Admin\Resource\CompanyCitiesController@destroy');
    $app->get('/countrycities/{id}', 'V2\Common\Admin\Resource\CompanyCitiesController@countrycities');
    
    //Account setting details
    $app->get('/profile', 'V2\Common\Admin\Resource\AdminController@show_profile');
    $app->post('/profile', 'V2\Common\Admin\Resource\AdminController@update_profile');

    Route::get('password', 'V2\Common\Admin\Resource\AdminController@password');
    Route::post('password', 'V2\Common\Admin\Resource\AdminController@password_update');

    $app->get('/adminservice', 'V2\Common\Admin\Resource\AdminController@admin_service');
    $app->get('/heatmap', 'V2\Common\Admin\Resource\AdminController@heatmap');
    $app->get('/godsview', 'V2\Common\Admin\Resource\AdminController@godsview');


    //Admin Seeder
    $app->post('/companyuser', 'V2\Common\Admin\Resource\UserController@companyuser');

    $app->get('/settings', 'V2\Common\Admin\Auth\AdminController@index');

    $app->post('/settings', 'V2\Common\Admin\Auth\AdminController@settings_store');

    //Roles   
    $app->get('/roles', 'V2\Common\Admin\Resource\RolesController@index');
    $app->post('/roles', 'V2\Common\Admin\Resource\RolesController@store');
    $app->get('/roles/{id}', 'V2\Common\Admin\Resource\RolesController@show');
    $app->patch('/roles/{id}', 'V2\Common\Admin\Resource\RolesController@update');
    $app->delete('/roles/{id}', 'V2\Common\Admin\Resource\RolesController@destroy');
    $app->get('/permission', 'V2\Common\Admin\Resource\RolesController@permission');
    //peakhours
    $app->get('/peakhour', 'V2\Common\Admin\Resource\PeakHourController@index');

    $app->post('/peakhour', 'V2\Common\Admin\Resource\PeakHourController@store');

    $app->get('/peakhour/{id}', 'V2\Common\Admin\Resource\PeakHourController@show');

    $app->patch('/peakhour/{id}', 'V2\Common\Admin\Resource\PeakHourController@update');

    $app->delete('/peakhour/{id}', 'V2\Common\Admin\Resource\PeakHourController@destroy');


    // ratings
    $app->get('/userreview', 'V2\Common\Admin\Resource\AdminController@userReview');

    $app->get('/providerreview', 'V2\Common\Admin\Resource\AdminController@providerReview');

    //Menu
    $app->get('/menu', 'V2\Common\Admin\Resource\MenuController@index');
    $app->post('/menu', 'V2\Common\Admin\Resource\MenuController@store');
    $app->get('/menu/{id}', 'V2\Common\Admin\Resource\MenuController@show');
    $app->patch('/menu/{id}', 'V2\Common\Admin\Resource\MenuController@update');
    $app->delete('/menu/{id}', 'V2\Common\Admin\Resource\MenuController@destroy');
    $app->patch('/menucity/{id}', 'V2\Common\Admin\Resource\MenuController@menucity');
    $app->get('/ride_type', 'V2\Common\Admin\Resource\MenuController@ride_type');
    $app->get('/service_type', 'V2\Common\Admin\Resource\MenuController@service_type');

    $app->get('/order_type', 'V2\Common\Admin\Resource\MenuController@order_type');
    
    $app->get('/getcity', 'V2\Common\Admin\Resource\MenuController@getcity');
    $app->get('/getCountryCity/{serviceId}/{CountryId}', 'V2\Common\Admin\Resource\MenuController@getCountryCity');
    $app->get('/getmenucity/{id}', 'V2\Common\Admin\Resource\MenuController@getmenucity');
    //payrolls
    $app->get('/zone', 'V2\Common\Admin\Resource\ZoneController@index');

    $app->post('/zone', 'V2\Common\Admin\Resource\ZoneController@store');

    $app->get('/zone/{id}', 'V2\Common\Admin\Resource\ZoneController@show');

    $app->patch('/zone/{id}', 'V2\Common\Admin\Resource\ZoneController@update');

    $app->delete('/zone/{id}', 'V2\Common\Admin\Resource\ZoneController@destroy');
    $app->get('/zones/{id}/updateStatus', 'V2\Common\Admin\Resource\ZoneController@updateStatus');

    $app->get('/payroll-template', 'V2\Common\Admin\Resource\PayrollTemplateController@index');

    $app->post('/payroll-template', 'V2\Common\Admin\Resource\PayrollTemplateController@store');

    $app->get('/payroll-template/{id}', 'V2\Common\Admin\Resource\PayrollTemplateController@show');

    $app->patch('/payroll-template/{id}', 'V2\Common\Admin\Resource\PayrollTemplateController@update');

    $app->delete('/payroll-template/{id}', 'V2\Common\Admin\Resource\PayrollTemplateController@destroy');
    $app->get('/payroll-templates/{id}/updateStatus', 'V2\Common\Admin\Resource\PayrollTemplateController@updateStatus');


    $app->get('/payroll', 'V2\Common\Admin\Resource\PayrollController@index');

    $app->post('/payroll', 'V2\Common\Admin\Resource\PayrollController@store');

    $app->get('/payroll/{id}', 'V2\Common\Admin\Resource\PayrollController@show');

    $app->patch('/payroll/{id}', 'V2\Common\Admin\Resource\PayrollController@update');

    $app->delete('/payroll/{id}', 'V2\Common\Admin\Resource\PayrollController@destroy');

    $app->get('/payrolls/{id}/updateStatus', 'V2\Common\Admin\Resource\PayrollController@updateStatus');
    
    $app->post('/payroll/update-payroll', 'V2\Common\Admin\Resource\PayrollController@updatePayroll');

    $app->get('/zoneprovider/{id}', 'V2\Common\Admin\Resource\PayrollController@zoneprovider');
    $app->get('/payrolls/download/{id}', 'V2\Common\Admin\Resource\PayrollController@PayrollDownload');
    $app->get('/cityzones/{id}', 'V2\Common\Admin\Resource\ZoneController@cityzones');
    $app->get('/zonetype/{id}', 'V2\Common\Admin\Resource\ZoneController@cityzonestype');
    Route::get('bankdetails/template', 'V2\Common\Provider\HomeController@template');
    $app->post('/addbankdetails', 'V2\Common\Provider\HomeController@addbankdetails'); 
    $app->post('/editbankdetails', 'V2\Common\Provider\HomeController@editbankdetails'); 

    $app->get('/provider_total_deatils/{id}', 'V2\Common\Admin\Resource\ProviderController@provider_total_deatils');

     $app->get('/dashboard/{id}', 'V2\Common\Admin\Auth\AdminController@dashboarddata');


     $app->get('/statement/provider', 'V2\Common\Admin\Resource\AllStatementController@statement_provider');
     $app->get('/statement/user', 'V2\Common\Admin\Resource\AllStatementController@statement_user');
     $app->get('/transactions', 'V2\Common\Admin\Resource\AllStatementController@statement_admin');
    
});

$router->get('/payrolls/download/{id}', 'V2\Common\Admin\Resource\PayrollController@PayrollDownload');
$router->get('/searchprovider/{id}', 'V2\Common\Admin\Resource\ProviderController@searchprovider');