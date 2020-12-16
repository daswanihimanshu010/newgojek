<?php

$router->group(['middleware' => 'auth:admin'], function ($app) {

    // vehile

    $app->get('/vehicle', 'V2\Transport\Admin\VehicleController@index');
    
    $app->get('/getvehicletype', 'V2\Transport\Admin\VehicleController@getvehicletype');

    $app->post('/vehicle', 'V2\Transport\Admin\VehicleController@store');

    $app->get('/vehicle/{id}', 'V2\Transport\Admin\VehicleController@show');

    $app->patch('/vehicle/{id}', 'V2\Transport\Admin\VehicleController@update');

    $app->delete('/vehicle/{id}', 'V2\Transport\Admin\VehicleController@destroy');

    $app->get('/transport/price/get/{id}', 'V2\Transport\Admin\VehicleController@gettaxiprice');

    $app->get('/vehicle/{id}/updateStatus', 'V2\Transport\Admin\VehicleController@updateStatus');

    $app->get('/comission/{country_id}/{city_id}/{admin_service_id}', 'V2\Transport\Admin\VehicleController@getComission');
    
    $app->get('/gettaxiprice/{id}', 'V2\Transport\Admin\VehicleController@gettaxiprice');

    $app->post('/transport/track/request', 'V2\Transport\User\RideController@track_location');
    

    $app->post('/rideprice', 'V2\Transport\Admin\VehicleController@rideprice');
    $app->post('/rentalprice', 'V2\Transport\Admin\VehicleController@rentalRidePrice');
    $app->post('/outstationprice', 'V2\Transport\Admin\VehicleController@outstationRidePrice');

    $app->get('/rideprice/{ride_delivery_vehicle_id}/{city_id}', 'V2\Transport\Admin\VehicleController@getRidePrice');
    $app->get('/rentalrideprice/{ride_delivery_vehicle_id}/{city_id}', 'V2\Transport\Admin\VehicleController@getRentalRidePrice');

    $app->post('/comission', 'V2\Transport\Admin\VehicleController@comission');

    // Lost Item
    $app->get('/lostitem', 'V2\Transport\Admin\LostItemController@index');

    $app->post('/lostitem', 'V2\Transport\Admin\LostItemController@store');

    $app->get('/lostitem/{id}', 'V2\Transport\Admin\LostItemController@show');

    $app->patch('/lostitem/{id}', 'V2\Transport\Admin\LostItemController@update');


    $app->get('usersearch', 'V2\Transport\User\RideController@search_user');

    $app->get('userprovider', 'V2\Transport\User\RideController@search_provider');

    $app->post('ridesearch', 'V2\Transport\User\RideController@searchRideLostitem');

    $app->post('disputeridesearch', 'V2\Transport\User\RideController@searchRideDispute');


       // Ride Request Dispute
       $app->get('/requestdispute', 'V2\Transport\Admin\RideRequestDisputeController@index');

       $app->post('/requestdispute', 'V2\Transport\Admin\RideRequestDisputeController@store');
   
       $app->get('/requestdispute/{id}', 'V2\Transport\Admin\RideRequestDisputeController@show');
   
       $app->patch('/requestdispute/{id}', 'V2\Transport\Admin\RideRequestDisputeController@update');
   
       $app->get('disputelist', 'V2\Transport\Admin\RideRequestDisputeController@dispute_list');
        
       // request history
       $app->get('/requesthistory', 'V2\Transport\User\RideController@requestHistory');
       $app->get('/requestschedulehistory', 'V2\Transport\User\RideController@requestscheduleHistory');
       $app->get('/requesthistory/{id}', 'V2\Transport\User\RideController@requestHistoryDetails');
       $app->get('/requestStatementhistory', 'V2\Transport\User\RideController@requestStatementHistory');

       // vehicle type
        $app->get('/vehicletype', 'V2\Transport\Admin\VehicleTypeController@index');

        $app->post('/vehicletype', 'V2\Transport\Admin\VehicleTypeController@store');

        $app->get('/vehicletype/{id}', 'V2\Transport\Admin\VehicleTypeController@show');

        $app->patch('/vehicletype/{id}', 'V2\Transport\Admin\VehicleTypeController@update');

        $app->delete('/vehicletype/{id}', 'V2\Transport\Admin\VehicleTypeController@destroy');

        $app->get('/vehicletype/{id}/updateStatus', 'V2\Transport\Admin\VehicleTypeController@updateStatus');
        $app->get('/transportdocuments/{id}', 'V2\Transport\Admin\VehicleTypeController@webproviderservice');

        // statement
        $app->get('/statement', 'V2\Transport\User\RideController@statement');

        // Dashboard

        $app->get('transportdashboard/{id}', 'V2\Transport\Admin\RideRequestDisputeController@dashboarddata');

        // Packages
        $app->get('/packages', 'V2\Transport\Admin\RidePackagesController@index');
        $app->get('/packagesList', 'V2\Transport\Admin\RidePackagesController@packagesList');
        $app->post('/packages', 'V2\Transport\Admin\RidePackagesController@store');
        $app->get('/packages/{id}', 'V2\Transport\Admin\RidePackagesController@show');
        $app->patch('/packages/{id}', 'V2\Transport\Admin\RidePackagesController@update');
        $app->delete('/packages/{id}', 'V2\Transport\Admin\RidePackagesController@destroy');
        $app->get('/packages/{id}/update-status', 'V2\Transport\Admin\RidePackagesController@updateStatus');

});
