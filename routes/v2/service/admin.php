<?php

$router->group(['middleware' => 'auth:admin'], function ($app) {
    $app->group(['prefix'=>'service'], function($app){
        // SERVICE MAIN CATEGORIES
        $app->get('/categories', 'V2\Service\Admin\ServiceCategoryController@index');
        $app->post('/categories', 'V2\Service\Admin\ServiceCategoryController@store');
        $app->get('/categories/{id}', 'V2\Service\Admin\ServiceCategoryController@show');
        $app->patch('/categories/{id}', 'V2\Service\Admin\ServiceCategoryController@update');
        $app->delete('/categories/{id}', 'V2\Service\Admin\ServiceCategoryController@destroy');
        $app->get('/categories/{id}/updateStatus', 'V2\Service\Admin\ServiceCategoryController@updateStatus');

        // SERVICE SUB CATEGORIES
        $app->get('/categories-list', 'V2\Service\Admin\ServiceSubCategoryController@categoriesList');
        $app->get('/subcategories', 'V2\Service\Admin\ServiceSubCategoryController@index');
        $app->post('/subcategories', 'V2\Service\Admin\ServiceSubCategoryController@store');
        $app->get('/subcategories/{id}', 'V2\Service\Admin\ServiceSubCategoryController@show');
        $app->patch('/subcategories/{id}', 'V2\Service\Admin\ServiceSubCategoryController@update');
        $app->delete('/subcategories/{id}', 'V2\Service\Admin\ServiceSubCategoryController@destroy');

        $app->get('/subcategories/{id}/updateStatus', 'V2\Service\Admin\ServiceSubCategoryController@updateStatus');

        // SERVICES
        $app->get('/subcategories-list/{categoryId}', 'V2\Service\Admin\ServicesController@subcategoriesList');
        $app->get('/listing', 'V2\Service\Admin\ServicesController@index');
        $app->post('/listing', 'V2\Service\Admin\ServicesController@store');
        $app->get('/listing/{id}', 'V2\Service\Admin\ServicesController@show');
        $app->patch('/listing/{id}', 'V2\Service\Admin\ServicesController@update');
        $app->delete('/listing/{id}', 'V2\Service\Admin\ServicesController@destroy');

        $app->get('/listing/{id}/updateStatus', 'V2\Service\Admin\ServicesController@updateStatus');

        $app->get('/get-service-price/{id}', 'V2\Service\Admin\ServicesController@getServicePriceCities'); 
        $app->post('/pricings', 'V2\Service\Admin\ServicesController@servicePricePost');
        $app->get('/pricing/{service_id}/{city_id}', 'V2\Service\Admin\ServicesController@getServicePrice');
        // Dispute
        $app->post('dispute-service-search', 'V2\Service\User\ServiceController@searchServiceDispute');
        $app->get('/requestdispute', 'V2\Service\Admin\RequestDisputeController@index');
        $app->post('/requestdispute', 'V2\Service\Admin\RequestDisputeController@store');
        $app->get('/requestdispute/{id}', 'V2\Service\Admin\RequestDisputeController@show');
        $app->patch('/requestdispute/{id}', 'V2\Service\Admin\RequestDisputeController@update');
        $app->get('disputelist', 'V2\Service\Admin\RequestDisputeController@dispute_list');
        //request history
        $app->get('/requesthistory', 'V2\Service\User\ServiceController@requestHistory');
        $app->get('/requestschedulehistory', 'V2\Service\User\ServiceController@requestScheduleHistory');
        $app->get('/requesthistory/{id}', 'V2\Service\User\ServiceController@requestHistoryDetails');
        $app->get('/servicedocuments/{id}', 'V2\Service\User\ServiceController@webproviderservice');

        $app->get('/Servicedashboard/{id}', 'V2\Service\Admin\ServicesController@dashboarddata');
        $app->get('/requestStatementhistory', 'V2\Service\User\ServiceController@requestStatementHistory');
    
    });
    $app->get('user-search', 'V2\Common\User\HomeController@search_user');
    $app->get('provider-search', 'V2\Common\Provider\HomeController@search_provider');
    
});
