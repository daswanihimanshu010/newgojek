<?php

namespace App\Http\Controllers\V1\Transport\User;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\SendPushNotification;
use App\Models\Transport\RideDeliveryVehicle;
use App\Models\Common\RequestFilter;
use App\Models\Transport\RideRequest;
use App\Models\Common\UserRequest;
use App\Models\Transport\RideType;
use App\Models\Common\Provider;
use App\Models\Common\Country;
use App\Models\Common\Rating;
use App\Services\V1\ServiceTypes;
use App\Models\Common\Setting;
use App\Models\Common\Reason;
use App\Models\Common\State;
use App\Models\Common\User;
use App\Models\Common\Menu;
use App\Models\Common\Card;
use App\Models\Transport\RideCityPrice;
use App\Models\Transport\RidePeakPrice;
use App\Models\Common\PeakHour;
use App\Models\Common\AdminService;
use App\Models\Transport\RideLostItem;
use App\Models\Transport\RideRequestDispute;
use App\Models\Transport\RideRequestPayment;
use App\Models\Common\ProviderService;
use App\Models\Common\CompanyCountry;
use App\Models\Common\Promocode;
use App\Services\PaymentGateway;
use App\Models\Common\PaymentLog;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\V1\Transport\Provider\TripController;
use App\Http\Controllers\V1\Common\Provider\HomeController;
use Carbon\Carbon;
use Auth;
use DB;

class RideController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/user/transport/services",
     *     operationId="apiv1.user.transport.services",
     *     tags={"Transport Services"},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Ride Type ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="latitude",
     *         in="query",
     *         description="Current latitude of the user",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="longitude",
     *         in="query",
     *         description="Current longitude of the user",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns available services, providers and promocodes",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Returns unauthorized response",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *     ),
     *     security={ {"bearer": {}} },
     * )
     */
    public function services(Request $request)
    {
        $this->validate($request, [
            'type' => 'required|numeric|exists:transport.ride_types,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $transport= new \stdClass;

        //$menu = Menu::where('menu_type_id', $request->type)->first();

        $admin_service = AdminService::where('admin_service_name','TRANSPORT')->where('company_id', Auth::guard('user')->user()->company_id)->first();

        $settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));

        $siteConfig = $settings->site;

        $transportConfig = $settings->transport;

        $distance = isset($transportConfig->provider_search_radius) ? $transportConfig->provider_search_radius : 100;

        $ride_delivery_vehicles = [];

        $admin_id=$admin_service->id;

        $callback = function ($q) use ($admin_id) {
            $q->where('admin_service_id',$admin_id);
        };

        $data = Provider::with(['service' => $callback, 'service.ride_vehicle'])->whereHas('service', function($query) use ($admin_service){
                        $query->where('admin_service_id',$admin_service->id);
                    })
            ->select('*', DB::Raw("(6371 * acos( cos( radians('$request->latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$request->longitude') ) + sin( radians('$request->latitude') ) * sin( radians(latitude) ) ) ) AS distance"))
            ->where('status', 'approved')
            ->where('is_online',1)
            ->where('is_assigned',0)
            ->where('company_id', Auth::guard('user')->user()->company_id)
            ->whereRaw("(6371 * acos( cos( radians('$request->latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$request->longitude') ) + sin( radians('$request->latitude') ) * sin( radians(latitude) ) ) ) <= $distance")             
            ->orderBy('distance','asc')
            ->where('city_id', Auth::guard('user')->user()->city_id)
            ->where('wallet_balance' ,'>=',$siteConfig->provider_negative_balance)
            ->get();


        if(count($data) > 0) {
            foreach ($data as $datum) {
                if($datum->service != null)
                $ride_delivery_vehicles[] = $datum->service->ride_delivery_id;
            }
        }

        $service = null;
        $providers = [];
        $nearestProvider = [];        

        foreach($data as $datum) {
            if($service == null) {
                if($datum->service != null) {
                    $nearestProvider[] = [ 'service_id' => $datum->service->ride_delivery_id, 'latitude' => $datum->latitude, 'longitude' => $datum->longitude ];
                    $service = $datum->service->ride_delivery_id;
                    $ride_delivery_vehicles[] = $service;
                }
            }
            $service_id = $datum->service->ride_delivery_id;
            //unset($datum->service);

            $provider = new \stdClass();
            foreach (json_decode($datum) as $l => $val) {
                $provider->$l = $val;
            }
            $provider->service_id = $service_id;
            $providers[] = $provider;
        }

        $sources = [];
        $destinations = [];
        foreach ($nearestProvider as $near) {
            $sources[] = $near['latitude'].','.$near['longitude'];
            $destinations[] = $request->latitude.','.$request->longitude;
        }


        $estimations = [];
        $etaFromLatLong = file_get_contents('https://maps.googleapis.com/maps/api/distancematrix/json?origins='.implode('|', $sources).'&destinations='.implode('|', $destinations).'&sensor=false&key='.$siteConfig->server_key); 
        $output = json_decode($etaFromLatLong);
        if($output->status=="OK") {
            foreach ($output->rows as $key => $row) {
                $estimations[$nearestProvider[$key]['service_id']] = $row->elements[0]->duration->text;
            }
        }
        
        $service_list = RideDeliveryVehicle::with(['priceDetails' => function($q) {
            $q->where('city_id', Auth::guard('user')->user()->city_id);
        }])->whereHas('priceDetails', function($q) {
            $q->where('city_id', Auth::guard('user')->user()->city_id);
        })->where('ride_type_id', $request->type)->whereIn('id', $ride_delivery_vehicles)->where('company_id', Auth::guard('user')->user()->company_id)->where('status', 1)->get();


        $service_types = [];
        $service_id_list = [];

        if(count($service_list) > 0) {
            foreach ($service_list as $k => $services) {
                $service = new \stdClass();
                foreach (json_decode($services)as $j => $s) {
                    $service->$j = $s;
                }
                $service->estimated_time = isset($estimations[ $services->id ]) ? $estimations[ $services->id ] : '0 Min';
                $service_types[] = $service;
                $service_id_list[] = $service->id; 
            }
        }

        $ride_delivery_vehicles = RideDeliveryVehicle::with(['priceDetails' => function($q) {
            $q->where('city_id', Auth::guard('user')->user()->city_id);
        }])->whereHas('priceDetails', function($q) {
            $q->where('city_id', Auth::guard('user')->user()->city_id);
        })->where('ride_type_id', $request->type)->where('company_id', Auth::guard('user')->user()->company_id)->where('status', 1)->whereNotIn('id', $service_id_list)->select('*', \DB::raw('"..." AS "estimated_time"'))->get()->toArray();

        $transport->services = array_merge($service_types,$ride_delivery_vehicles);

        $transport->providers = $providers;

        $transport->promocodes = Promocode::where('company_id', Auth::guard('user')->user()->company_id)->where('service', 'TRANSPORT')
                    ->where('expiration','>=',date("Y-m-d H:i"))
                    ->whereDoesntHave('promousage', function($query) {
                        $query->where('user_id',Auth::guard('user')->user()->id);
                    })
                    ->get();

        return Helper::getResponse(['data' => $transport]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/user/promocode/Service",
     *     operationId="apiv1.user.promocode.service",
     *     tags={"Transport Promolist"},
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         description="Admin service ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="latitude",
     *         in="path",
     *         description="Current latitude of the user",
     *         required=true,
     *         @OA\Schema(type="double")
     *     ),
     *     @OA\Parameter(
     *         name="longitude",
     *         in="path",
     *         description="Current longitude of the user",
     *         required=true,
     *         @OA\Schema(type="double")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns available services, providers and promocodes",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *     ),
     *     security={ {"bearer": {}} },
     * )
     */
    public function promolist(Request $request)
    {
        $promolist = $this->UserAPI->list_promocode($request);

        return Helper::getResponse(['data' => $promolist]);
    }

    public function cards(Request $request)
    {
        $cards = (new Resource\CardResource)->index();

        return Helper::getResponse(['data' => $cards]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/user/transport/estimate",
     *     operationId="apiv1.user.transport.estimate",
     *     tags={"Transport Estimate"},
     *     @OA\Parameter(
     *         name="s_latitude",
     *         in="path",
     *         description="Admin service ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="s_longitude",
     *         in="path",
     *         description="Current latitude of the user",
     *         required=true,
     *         @OA\Schema(type="double")
     *     ),
     *     @OA\Parameter(
     *         name="longitude",
     *         in="path",
     *         description="Current longitude of the user",
     *         required=true,
     *         @OA\Schema(type="double")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns available services, providers and promocodes",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Error: Unprocessable entity. When required parameters were not supplied.",
     *     ),
     *     security={ {"bearer": {}} },
     * )
     */
    public function estimate(Request $request)
    {
        $settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));

        $siteConfig = $settings->site;

        $transportConfig = $settings->transport;

        $request->request->add(['server_key' => $siteConfig->server_key]);

        $request->request->add(['city_id' => Auth::guard('user')->user()->city_id]);

        $fare = $this->estimated_fare($request)->getData();
        
        $service = RideDeliveryVehicle::find($request->service_type);

        if($request->has('current_longitude') && $request->has('current_latitude'))
        {
            User::where('id',Auth::user()->id)->update([
                'latitude' => $request->current_latitude,
                'longitude' => $request->current_longitude
            ]);
        }

        $promocodes = Promocode::where('company_id', Auth::guard('user')->user()->company_id)->where('service', 'TRANSPORT')
                    ->where('expiration','>=',date("Y-m-d H:i"))
                    ->whereDoesntHave('promousage', function($query) {
                                $query->where('user_id',Auth::guard('user')->user()->id);
                            })
                    ->get();

        return Helper::getResponse(['data' => ['fare' => $fare, 'service' => $service, 'promocodes' => $promocodes, 'unit' => $transportConfig->unit_measurement, 'currency' => Auth::guard('user')->user()->currency_symbol ]]);
    }

    public function create_ride(Request $request)
    {

        $setting = Setting::where('company_id', Auth::guard('user')->user()->company_id)->first();

        $settings = json_decode(json_encode($setting->settings_data));

        $siteConfig = $settings->site;

        $transportConfig = $settings->transport;
        
        if(isset($transportConfig->destination)) {
            if($transportConfig->destination == 0) {
                $this->validate($request, [
                    's_latitude' => 'required|numeric',
                    's_longitude' => 'required|numeric',
                    'ride_type_id' => 'required',
                    'someone_email'     => 'required_if:someone,1',
                    'someone_mobile'     => 'required_if:someone,1'
                ]);
            } else {
                $this->validate($request, [
                    's_latitude' => 'required|numeric',
                    's_longitude' => 'required|numeric',
                    'ride_type_id' => 'required',
                    'someone_email'     => 'required_if:someone,1',
                    'someone_mobile'     => 'required_if:someone,1',
                    'd_latitude' => 'required|numeric',
                    'd_longitude' => 'required|numeric'
                ]);
            }
        }

        $ride_city_price = RideCityPrice::where('city_id',Auth::guard('user')->user()->city_id)->where('ride_delivery_vehicle_id', $request->service_type)->first();

        if($ride_city_price == null) {
            return Helper::getResponse(['status' => 400, 'message' => trans('user.ride.service_not_available_location'), 'error' => trans('user.ride.service_not_available_location')]);
        }

 
        $ActiveRequests = RideRequest::PendingRequest(Auth::guard('user')->user()->id)->count();

        if($ActiveRequests > 0) {
            return Helper::getResponse(['status' => 422, 'message' => trans('api.ride.request_inprogress')]);
        }

        $admin_service_id = AdminService::where('admin_service_name', 'TRANSPORT')->where('company_id', Auth::guard('user')->user()->company_id)->first();
        
        $timezone =  (Auth::guard('user')->user()->state_id) ? State::find(Auth::guard('user')->user()->state_id)->timezone : '';

        $country =  CompanyCountry::where('country_id', Auth::guard('user')->user()->country_id)->first();

        $currency =  ($country != null) ? $country->currency : '' ;

        if($request->has('schedule_date') && $request->has('schedule_time')){

            $schedule_date = (Carbon::createFromFormat('Y-m-d H:i:s', (Carbon::parse($request->schedule_date. ' ' .$request->schedule_time)->format('Y-m-d H:i:s')), $timezone))->setTimezone('UTC'); 


            $beforeschedule_time = (new Carbon($schedule_date))->subHour(1);
            $afterschedule_time = (new Carbon($schedule_date))->addHour(1);


            $CheckScheduling = RideRequest::where('status','SCHEDULED')
                            ->where('user_id', Auth::guard('user')->user()->id)
                            ->whereBetween('schedule_at',[$beforeschedule_time,$afterschedule_time])
                            ->count();


            if($CheckScheduling > 0){
                return Helper::getResponse(['status' => 422, 'message' => trans('api.ride.request_already_scheduled')]);
            }

        }

        $distance = $transportConfig->provider_search_radius ? $transportConfig->provider_search_radius : 100;

        $latitude = $request->s_latitude;
        $longitude = $request->s_longitude;
        $service_type = $request->service_type;


        $child_seat = $request->child_seat != null  ? $request->child_seat : 0 ;
        $wheel_chair = $request->wheel_chair != null ? $request->wheel_chair : 0 ;

        $admin_id=$admin_service_id->id;

        $callback = function ($q) use ($admin_id, $service_type) {
            $q->where('admin_service_id',$admin_id);
            $q->where('ride_delivery_id',$service_type);
        };

        $Providers = Provider::with(['service' => $callback])
            ->select(DB::Raw("(6371 * acos( cos( radians('$latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(latitude) ) ) ) AS distance"),'id')
            ->where('status', 'approved')
            ->where('is_online', 1)
            ->where('is_assigned', 0)
            ->where('company_id', Auth::guard('user')->user()->company_id)
            ->whereRaw("(6371 * acos( cos( radians('$latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(latitude) ) ) ) <= $distance")
            ->whereHas('service', function($query) use ($admin_id, $service_type){          
                        $query->where('admin_service_id', $admin_id);
                        $query->where('ride_delivery_id',$service_type);
                    })
            ->whereHas('service.vehicle', function($query) use ($child_seat, $wheel_chair){    
                    if($child_seat != 0) {
                        $query->where('child_seat', $child_seat);
                    }
                    if($wheel_chair != 0) {
                        $query->where('wheel_chair',$wheel_chair);
                    }
                    })
            ->orderBy('distance','asc')
            ->get();

        if(count($Providers) == 0) {
            return Helper::getResponse(['status' => 422, 'message' => trans('api.ride.no_providers_found')]);
        }     

        try {
            $details = "https://maps.googleapis.com/maps/api/directions/json?origin=".$request->s_latitude.",".$request->s_longitude."&destination=".$request->d_latitude.",".$request->d_longitude."&mode=driving&key=".$siteConfig->server_key;

            $json = Helper::curl($details);

            $details = json_decode($json, TRUE);

            $route_key = (count($details['routes']) > 0) ? $details['routes'][0]['overview_polyline']['points'] : '';

            $rideRequest = new RideRequest;
            $rideRequest->company_id = Auth::guard('user')->user()->company_id;
            $rideRequest->admin_service_id = $admin_id;
            $rideRequest->booking_id = Helper::generate_booking_id('TRNX');

            $rideRequest->user_id = Auth::guard('user')->user()->id;

            $rideRequest->provider_service_id = $request->service_type;
            $rideRequest->ride_type_id = $request->ride_type_id;
            $rideRequest->distance = (count($details['routes']) > 0) ? ($details['routes'][0]['legs'][0]['distance']['value'] / 1000) : 0;

            //$rideRequest->rental_hours = $request->rental_hours;
            $rideRequest->payment_mode = $request->payment_mode;
            $rideRequest->promocode_id = $request->promocode_id ? : 0;
            
            $rideRequest->status = 'SEARCHING';

            $rideRequest->timezone = $timezone;
            $rideRequest->currency = $currency;

            if($transportConfig->manual_request == "1") {
                $rideRequest->request_type = "MANUAL";
            }

            $rideRequest->country_id = Auth::guard('user')->user()->country_id;
            $rideRequest->city_id = Auth::guard('user')->user()->city_id;

            $rideRequest->s_address = $request->s_address ? $request->s_address : "";
            $rideRequest->d_address = $request->d_address ? $request->d_address  : "";

            $rideRequest->s_latitude = $request->s_latitude;
            $rideRequest->s_longitude = $request->s_longitude;

            $rideRequest->d_latitude = $request->d_latitude;
            $rideRequest->d_longitude = $request->d_longitude;
            $rideRequest->ride_delivery_id = $service_type;
            if($request->has('someone') && $request->someone==1){
                $rideRequest->someone=$request->someone;
                $rideRequest->someone_mobile=$request->someone_mobile;
                $rideRequest->someone_email=$request->someone_email;
                 try{
                      if( !empty($settings->send_email) && $settings->send_email == 1) {
                         Mail::send('mails/someone', ['settings' => $settings,'user'=>Auth::guard('user')->user()], function ($mail) use ($settings,$request) {
                            $mail->from($settings->site->mail_from_address, $settings->site->mail_from_name);
                            $mail->to($request->someone_email, Auth::guard('user')->user()->first_name.' '.Auth::guard('user')->user()->last_name)->subject('Notification');
                          });
                       }  

                   }catch (\Throwable $e) { 
                       throw new \Exception($e->getMessage());
                    }   
             }
            $rideRequest->track_distance = 1;
            $rideRequest->track_latitude = $request->s_latitude;
            $rideRequest->track_longitude = $request->s_longitude;

            if($request->d_latitude == null && $request->d_longitude == null) {
                $rideRequest->is_drop_location = 0;
            }

            $rideRequest->destination_log = json_encode([['latitude' => $rideRequest->d_latitude, 'longitude' => $request->d_longitude, 'address' => $request->d_address]]);
            $rideRequest->unit = isset($siteConfig->distance) ? $siteConfig->distance : 'Kms';

            if(Auth::guard('user')->user()->wallet_balance > 0){
                $rideRequest->use_wallet = $request->use_wallet ? : 0;
            }

            $rideRequest->is_track = "YES";

            $rideRequest->otp = mt_rand(1000 , 9999);

            $rideRequest->assigned_at = Carbon::now();
            $rideRequest->route_key = $route_key;

            if($Providers->count() <= (isset($transportConfig->surge_trigger) ? $transportConfig->surge_trigger : 0) && $Providers->count() > 0){
                $rideRequest->surge = 1;
            }

            if($request->has('schedule_date') && $request->has('schedule_time')){
                $rideRequest->status = 'SCHEDULED';
                $rideRequest->schedule_at = (Carbon::createFromFormat('Y-m-d H:i:s', (Carbon::parse($request->schedule_date. ' ' .$request->schedule_time)->format('Y-m-d H:i:s')), $timezone))->setTimezone('UTC');
                $rideRequest->is_scheduled = 'YES';
            }

            if($rideRequest->status != 'SCHEDULED') {
                if($transportConfig->manual_request == 0 && $transportConfig->broadcast_request == 0) {
                    //Log::info('New Request id : '. $rideRequest->id .' Assigned to provider : '. $rideRequest->provider_id);
                    //(new SendPushNotification)->IncomingRequest($Providers[0]->id, 'transport');
                }
            }   

            $rideRequest->save();

            /*if($transportConfig->manual_request == 1) {

                $admins = Admin::select('id')->get();

                foreach ($admins as $admin_id) {
                    $admin = Admin::find($admin_id->id);
                    //$admin->notify(new WebPush("Notifications", trans('api.push.incoming_request'), route('admin.dispatcher.index') ));
                }

            }*/
           

            // update payment mode
            User::where('id',Auth::guard('user')->user()->id)->update(['payment_mode' => $request->payment_mode]);

            if($request->has('card_id')){

                Card::where('user_id',Auth::guard('user')->user()->id)->update(['is_default' => 0]);
                Card::where('card_id',$request->card_id)->update(['is_default' => 1]);
            }

            $rideRequest = RideRequest::with('ride', 'ride_type')->where('id', $rideRequest->id)->first();

            //Add the Log File for ride
            $user_request = new UserRequest();
            $user_request->request_id = $rideRequest->id;
            $user_request->user_id = $rideRequest->user_id;
            $user_request->provider_id = $rideRequest->provider_id;
            $user_request->admin_service_id =$admin_service_id->id;
            $user_request->status = $rideRequest->status;
            $user_request->request_data = json_encode($rideRequest);
            $user_request->company_id = Auth::guard('user')->user()->company_id; 
            $user_request->schedule_at = $rideRequest->schedule_at; 
            $user_request->save();

            if($rideRequest->status != 'SCHEDULED') {
                if($transportConfig->manual_request == 0){
                    $first_iteration = true;
                    foreach ($Providers as $key => $Provider) {

                        if($transportConfig->broadcast_request == 1){
                           (new SendPushNotification)->IncomingRequest($Provider->id, 'transport_incoming_request', 'Taxi Incoming Request'); 
                        }

                        /*$unwantedRequests = RequestFilter::select('id')->whereHas('accepted_request')->where('provider_id', $Provider->id)->get();

                        foreach ($unwantedRequests as $unwantedRequest) {
                            $unwantedRequest->delete();
                        }*/

                        $existingRequest =  RequestFilter::where('provider_id', $Provider->id)->first();
                        if($existingRequest == null) {
                            $Filter = new RequestFilter;
                            // Send push notifications to the first provider
                            // incoming request push to provider
                            $Filter->admin_service_id = $admin_service_id->id;
                            $Filter->request_id = $user_request->id;
                            $Filter->provider_id = $Provider->id; 

                            if($transportConfig->broadcast_request == 0 && $first_iteration == false ) {
                                $Filter->assigned = 1;
                            }

                            $Filter->company_id = Auth::guard('user')->user()->company_id; 
                            $Filter->save();
                        }
                        $first_iteration = false;
                    }
                }

                //Send message to socket
                $requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.Auth::guard('user')->user()->company_id, 'id' => $rideRequest->id, 'city' => ($setting->demo_mode == 0) ? $rideRequest->city_id : 0, 'user' => $rideRequest->user_id ];
                app('redis')->publish('newRequest', json_encode( $requestData ));

            }

            return Helper::getResponse([ 'data' => [
                        'message' => ($rideRequest->status == 'SCHEDULED') ? 'Schedule request created!' : 'New request created!',
                        'request'=> $rideRequest,
                    ]]);

        } catch (Exception $e) {  
            return Helper::getResponse(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    public function status(Request $request)
    {

        try{

            $settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));

            $siteConfig = $settings->site;

            $transportConfig = $settings->transport;

            $check_status = ['CANCELLED', 'SCHEDULED'];

            $admin_service = AdminService::where('admin_service_name','TRANSPORT')->where('company_id', Auth::guard('user')->user()->company_id)->first();

            $rideRequest = RideRequest::RideRequestStatusCheck(Auth::guard('user')->user()->id, $check_status, $admin_service->id)
                                        ->get()
                                        ->toArray();

            $start_time = (Carbon::now())->toDateTimeString();
            $end_time = (Carbon::now())->toDateTimeString();

            $peak_percentage = 1+(0/100)."X";
            $peak = 0;

            $start_time_check = PeakHour::where('start_time', '<=', $start_time)->where('end_time', '>=', $start_time)->where('company_id', '>=', Auth::guard('user')->user()->company_id)->first();

            if( count($rideRequest) > 0 && $start_time_check){

                $Peakcharges = RidePeakPrice::where('ride_city_price_id', $rideRequest[0]['city_id'])->where('ride_delivery_id', $rideRequest[0]['ride_delivery_id'])->where('peak_hour_id',$start_time_check->id)->first();

                if($Peakcharges){
                    $peak = 1;
                }

            }
                                       

            $search_status = ['SEARCHING','SCHEDULED'];
            $rideRequestFilter = RideRequest::RideRequestAssignProvider(Auth::guard('user')->user()->id,$search_status)->get(); 

            if(!empty($rideRequest)){
                $rideRequest[0]['ride_otp'] = (int) $transportConfig->ride_otp ? $transportConfig->ride_otp : 0 ;
                $rideRequest[0]['peak'] = $peak ;

                $rideRequest[0]['reasons']=Reason::where('type','USER')->get();
            }

            $Timeout = $transportConfig->provider_select_timeout ? $transportConfig->provider_select_timeout : 60 ;
            $response_time = $Timeout;

            if(!empty($rideRequestFilter)){
                for ($i=0; $i < sizeof($rideRequestFilter); $i++) {
                    $ExpiredTime = $Timeout - (time() - strtotime($rideRequestFilter[$i]->assigned_at));
                    if($rideRequestFilter[$i]->status == 'SEARCHING' && $ExpiredTime < 0) {
                        $Providertrip = new HomeController();
                        $Providertrip->assign_next_provider($rideRequestFilter[$i]->id, $admin_service );
                        $response_time = $Timeout - (time() - strtotime($rideRequestFilter[$i]->assigned_at));
                    }else if($rideRequestFilter[$i]->status == 'SEARCHING' && $ExpiredTime > 0){
                        break;
                    }
                }

            }

            if(empty($rideRequest)) {

                $cancelled_request = RideRequest::where('ride_requests.user_id', Auth::guard('user')->user()->id)
                    ->where('ride_requests.user_rated',0)
                    ->where('ride_requests.status', ['CANCELLED'])->orderby('updated_at', 'desc')
                    ->where('updated_at','>=',\Carbon\Carbon::now()->subSeconds(5))
                    ->first();
                
            }

            return Helper::getResponse(['data' => [
                'response_time' => $response_time, 
                'data' => $rideRequest, 
                'sos' => isset($siteConfig->sos_number) ? $siteConfig->sos_number : '911' , 
                'emergency' => isset($siteConfig->contact_number) ? $siteConfig->contact_number : [['number' => '911']]  ]]);

        } catch (Exception $e) {
            return Helper::getResponse(['status' => 500, 'message' => trans('api.something_went_wrong'), 'error' => $e->getMessage() ]);
        }
    }

    public function track_location(Request $request) {


        $guard = Helper::getGuard();

        $settings = json_decode(json_encode(Setting::where('company_id', Auth::guard(strtolower($guard))->user()->id)->first()->settings_data));

        $siteConfig = $settings->site;

        $transportConfig = $settings->transport;


        $ride = RideRequest::with(['user' => function($query) { $query->select('id','first_name','last_name'); }, 'provider' => function($query) { $query->select('id','first_name','last_name', 'picture', 'rating'); }, 'provider.provider_vehicle', 'provider.provider_vehicle.provider_service.ride_vehicle' ])
        ->where('ride_requests.id', $request->id)
        ->where('ride_requests.status', 'PICKEDUP')
        ->first();

        if($ride != null) {
            $s_latitude = $ride->s_latitude;
            $s_longitude = $ride->s_longitude;
            $d_latitude = $ride->d_latitude;
            $d_longitude = $ride->d_longitude;
            $user = $ride->user;
            $provider = new \stdClass;
            $provider->first_name = $ride->provider->first_name;
            $provider->last_name = $ride->provider->last_name;
            $provider->rating = $ride->provider->rating;
            $provider->picture = $ride->provider->picture;

            $vehicle = new \stdClass;

            if(count($ride->provider->provider_vehicle) > 0) {
                $vehicle->model = $ride->provider->provider_vehicle[0]->vehicle_model;
                $vehicle->vehicle_no = $ride->provider->provider_vehicle[0]->vehicle_no;

                if($ride->provider->provider_vehicle[0]->provider_service != null) {
                    if($ride->provider->provider_vehicle[0]->provider_service->ride_vehicle != null) {
                         $vehicle->type = $ride->provider->provider_vehicle[0]->provider_service->ride_vehicle->vehicle_name;
                         $vehicle->picture = $ride->provider->provider_vehicle[0]->provider_service->ride_vehicle->vehicle_image;
                         $vehicle->marker = $ride->provider->provider_vehicle[0]->provider_service->ride_vehicle->vehicle_marker;
                    }
                }


            }

            $provider->vehicle = $vehicle;

            $apiurl = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=".$s_latitude.",".$s_longitude."&destinations=".$d_latitude.",".$d_longitude."&mode=driving&sensor=false&units=imperial&key=".$siteConfig->server_key;

            $client = new \GuzzleHttp\Client;
            $location = $client->get($apiurl);           
            $location = json_decode($location->getBody(),true);

            if(!empty($location['rows'][0]['elements'][0]['status']) && $location['rows'][0]['elements'][0]['status']=='OK'){

                $meters = $location['rows'][0]['elements'][0]['distance']['value'];
                $source = $s_latitude . ',' . $s_longitude;
                $destination = $d_latitude . ',' . $d_longitude;
                $minutes = $location['rows'][0]['elements'][0]['duration']['value'];

            }

            return response()->json([ 'user' => $user, 'provider' => $provider, 'meters' => $meters, 'source' => $source, 'destination' => $destination, 'minutes' => $minutes, 'marker' => isset($vehicle->marker) ? $vehicle->marker : '' ]);
        }
        

        return response()->json([ 'status' => 'Data not available' ], 201);
    }

    public function cancel_ride(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric|exists:transport.ride_requests,id,user_id,'.Auth::guard('user')->user()->id,
        ]);

        try{

            $rideRequest = RideRequest::findOrFail($request->id);

            $setting = Setting::where('company_id', $rideRequest->company_id)->first();

            if($rideRequest->status == 'CANCELLED')
            {
                return Helper::getResponse(['status' => 404, 'message' => trans('api.ride.already_cancelled')]);
            }

            if(in_array($rideRequest->status, ['SEARCHING','STARTED','ARRIVED','SCHEDULED'])) {

                if($rideRequest->status != 'SEARCHING'){
                    $this->validate($request, [
                        'cancel_reason'=> 'max:255',
                    ]);
                }

                $rideRequest->status = 'CANCELLED';

                if($request->cancel_reason=='ot')
                    $rideRequest->cancel_reason = $request->cancel_reason_opt;
                else
                    $rideRequest->cancel_reason = $request->cancel_reason;

                $rideRequest->cancelled_by = 'USER';
                $rideRequest->save();

                $admin_service = AdminService::where('admin_service_name','TRANSPORT')->where('company_id', Auth::guard('user')->user()->company_id)->first();

                $user_request = UserRequest::where('admin_service_id', $admin_service->id )->where('request_id',$rideRequest->id)->first();

                RequestFilter::where('admin_service_id', $admin_service->id )->where('request_id', $user_request->id)->delete();

                if($rideRequest->status != 'SCHEDULED'){

                    if($rideRequest->provider_id != null){
                        //ProviderService::where('provider_id',$rideRequest->provider_id)->update(['status' => 'ACTIVE']);
                        Provider::where('id', $rideRequest->provider_id)->update(['is_assigned' => 0]);
                    }
                }

                // Send Push Notification to User
                (new SendPushNotification)->UserCancellRide($rideRequest, 'transport');

                $user_request->delete();

                //Send message to socket
                $requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.Auth::guard('user')->user()->company_id, 'id' => $rideRequest->id, 'city' => ($setting->demo_mode == 0) ? $rideRequest->city_id : 0, 'user' => $rideRequest->user_id ];
                app('redis')->publish('newRequest', json_encode( $requestData ));

                return Helper::getResponse(['message' => trans('api.ride.ride_cancelled')]);

            } else {
                return Helper::getResponse(['status' => 403, 'message' => trans('api.ride.already_onride')]);
            }
        }

        catch (ModelNotFoundException $e) {
            return Helper::getResponse(['status' => 500, 'error' => $e->getMessage()]);
        }
    }



    public function extend_trip(Request $request) {
        $this->validate($request, [
            'id' => 'required|numeric|exists:transport.ride_requests,id,user_id,'.Auth::guard('user')->user()->id,
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'address' => 'required',
        ]);

        try{

            $rideRequest = RideRequest::findOrFail($request->id);

            $setting = Setting::where('company_id', $rideRequest->company_id)->first();

            $details = "https://maps.googleapis.com/maps/api/directions/json?origin=".$rideRequest->s_latitude.",".$rideRequest->s_longitude."&destination=".$request->latitude.",".$request->longitude."&mode=driving&key=".config('constants.map_key');

            $json = Helper::curl($details);

            $details = json_decode($json, TRUE);

            $route_key = (count($details['routes']) > 0) ? $details['routes'][0]['overview_polyline']['points'] : '';

            $destination_log = json_decode($rideRequest->destination_log);
            $destination_log[] = ['latitude' => $request->latitude, 'longitude' => $request->longitude, 'address' => $request->address];

            $rideRequest->d_latitude = $request->latitude;
            $rideRequest->d_longitude = $request->longitude;
            $rideRequest->d_address = $request->address;
            $rideRequest->route_key = $route_key;
            $rideRequest->destination_log = json_encode($destination_log);

            $rideRequest->save();

            $message = trans('api.destination_changed');

            (new SendPushNotification)->sendPushToProvider($rideRequest->provider_id, 'transport', $message);

            (new SendPushNotification)->sendPushToUser($rideRequest->user_id, 'transport', $message); 

            //Send message to socket
            $requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.Auth::guard('user')->user()->company_id, 'id' => $rideRequest->id, 'city' => ($setting->demo_mode == 0) ? $rideRequest->city_id : 0, 'user' => $rideRequest->user_id ];
            app('redis')->publish('newRequest', json_encode( $requestData ));

            return Helper::getResponse(['message' => 'Destination location has been changed', 'data' => $rideRequest]);

        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 500, 'message' => trans('api.something_went_wrong'), 'error' => $e->getMessage() ]);
        }
    }

    public function update_payment_method(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric|exists:transport.ride_requests,id,user_id,'.Auth::guard('user')->user()->id,
            'payment_mode' => 'required',
        ]);

        try{

            if($request->has('card_id')){
                Card::where('user_id',Auth::guard('user')->user()->id)->update(['is_default' => 0]);
                Card::where('card_id',$request->card_id)->update(['is_default' => 1]);
            }

            $rideRequest = RideRequest::findOrFail($request->id);
            $rideRequest->payment_mode = $request->payment_mode;
            $rideRequest->save();


            $payment = RideRequestPayment::where('ride_request_id', $rideRequest->id)->first();

            if($payment != null) {
                $payment->payment_mode = $request->payment_mode;
                $payment->save();
            }

            $admin_service = AdminService::where('admin_service_name', 'TRANSPORT')->where('company_id', Auth::guard('user')->user()->company_id)->first();

            $user_request = UserRequest::where('request_id', $request->id)->where('admin_service_id', $admin_service->id )->first();
            $user_request->request_data = json_encode($rideRequest);
            $user_request->save();

            (new SendPushNotification)->updateProviderStatus($user_request->provider_id, 'provider', trans('api.ride.payment_updated'), 'Payment Mode Changed', [] ); 

            return Helper::getResponse(['message' => trans('api.ride.payment_updated')]);
        }

        catch (ModelNotFoundException $e) {
            return Helper::getResponse(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    public function estimated_fare(Request $request){

        $this->validate($request,[
                's_latitude' => 'required|numeric',
                's_longitude' => 'numeric',
                'd_latitude' => 'required|numeric',
                'd_longitude' => 'numeric',
                'service_type' => 'required|numeric|exists:transport.ride_delivery_vehicles,id',
            ]);

        try{       
            $response = new ServiceTypes();

            $request->request->add(['company_id' => Auth::guard('user')->user()->company_id]);

            $responsedata=$response->calculateFare($request->all(), 1);

            if(!empty($responsedata['errors'])){
                throw new \Exception($responsedata['errors']);
            }
            else{
                return response()->json( $responsedata['data'] );
            }

        } catch(Exception $e) {
            return response()->json( $e->getMessage() );
        }
    }

    public function search_user(Request $request){

		$results=array();

		$term =  $request->input('stext');  

		$queries = User::where('first_name', 'LIKE', $term.'%')->where('company_id', Auth::user()->company_id)->take(5)->get();

		foreach ($queries as $query)
		{
			$results[]=$query;
		}    

		return response()->json(array('success' => true, 'data'=>$results));

    }
    
    public function search_provider(Request $request){

		$results=array();

		$term =  $request->input('stext');  

		$queries = Provider::where('first_name', 'LIKE', $term.'%')->take(5)->get();

		foreach ($queries as $query)
		{
			$results[]=$query;
		}    

		return response()->json(array('success' => true, 'data'=>$results));

    }
    
    public function searchRideLostitem(Request $request)
    {

		$results=array();

		$term =  $request->input('stext');

		if($request->input('sflag')==1){
			
			$queries = RideRequest::where('provider_id', $request->id)->orderby('id', 'desc')->take(10)->get();
		}
		else{

			$queries = RideRequest::where('user_id', $request->id)->orderby('id', 'desc')->take(10)->get();
		}

		foreach ($queries as $query)
		{
            $LostItem = RideLostItem::where('ride_request_id',$query->id)->first();
            if(!$LostItem)
			$results[]=$query;
		}

		return response()->json(array('success' => true, 'data'=>$results));

	}
    
    public function searchRideDispute(Request $request)
    {

		$results=array();

		$term =  $request->input('stext');

		if($request->input('sflag')==1){
			
			$queries = RideRequest::where('provider_id', $request->id)->orderby('id', 'desc')->take(10)->get();
		}
		else{

			$queries = RideRequest::where('user_id', $request->id)->orderby('id', 'desc')->take(10)->get();
		}

		foreach ($queries as $query)
		{
            $RideRequestDispute = RideRequestDispute::where('ride_request_id',$query->id)->first();
            if(!$RideRequestDispute)
			$results[]=$query;
		}

		return response()->json(array('success' => true, 'data'=>$results));

    }
    
    public function requestHistory(Request $request)
	{
		try {
            $history_status = array('CANCELLED','COMPLETED');
            $datum = RideRequest::where('company_id',  Auth::user()->company_id)
                     ->with('user', 'provider','payment');

            if(Auth::user()->hasRole('FLEET')) {
                $datum->where('admin_id', Auth::user()->id);  
            }
            if($request->has('search_text') && $request->search_text != null) {
                $datum->Search($request->search_text);
            }
    
            if($request->has('order_by')) {
                $datum->orderby($request->order_by, $request->order_direction);
            }
            $data = $datum->whereIn('status',$history_status)->paginate(10);
            return Helper::getResponse(['data' => $data]);

        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }
    public function requestscheduleHistory(Request $request)
	{
		try {
            $scheduled_status = array('SCHEDULED');
            $datum = RideRequest::where('company_id',  Auth::user()->company_id)
                     ->whereIn('status',$scheduled_status)
                     ->with('user', 'provider');

            if(Auth::user()->hasRole('FLEET')) {
                $datum->where('admin_id', Auth::user()->id);  
            }
            if($request->has('search_text') && $request->search_text != null) {
                $datum->Search($request->search_text);
            }
    
            if($request->has('order_by')) {
                $datum->orderby($request->order_by, $request->order_direction);
            }
    
            $data = $datum->paginate(10);
    
            return Helper::getResponse(['data' => $data]);

        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
	}

    public function requestStatementHistory(Request $request)
	{
		try {
            $history_status = array('CANCELLED','COMPLETED');
            $rides = RideRequest::where('company_id',  Auth::user()->company_id)
                     ->with('user', 'provider');
            if($request->has('country_id')) {
                $rides->where('country_id',$request->country_id);
            }
            if(Auth::user()->hasRole('FLEET')) {
                $rides->where('admin_id', Auth::user()->id);  
            }
            if($request->has('search_text') && $request->search_text != null) {
                $rides->Search($request->search_text);
            }
    
            if($request->has('order_by')) {
                $rides->orderby($request->order_by, $request->order_direction);
            }
            $type = isset($_GET['type'])?$_GET['type']:'';
            if($type == 'today'){
				$rides->where('created_at', '>=', Carbon::today());
			}elseif($type == 'monthly'){
				$rides->where('created_at', '>=', Carbon::now()->month);
			}elseif($type == 'yearly'){
				$rides->where('created_at', '>=', Carbon::now()->year);
			}elseif ($type == 'range') {   
                if($request->has('from') &&$request->has('to')) {             
                    if($request->from == $request->to) {
                        $rides->whereDate('created_at', date('Y-m-d', strtotime($request->from)));
                    } else {
                        $rides->whereBetween('created_at',[Carbon::createFromFormat('Y-m-d', $request->from),Carbon::createFromFormat('Y-m-d', $request->to)]);
                    }
                }
			}else{
                // dd(5);
            }
            $cancelrides = $rides;
            $orderCounts = $rides->count();
            $dataval = $rides->whereIn('status',$history_status)->paginate(10);
            $cancelledQuery = $cancelrides->where('status','CANCELLED')->count();
            $total_earnings = 0;
            foreach($dataval as $ride){
                $ride->status = $ride->status == 1?'Enabled' : 'Disable';
                $rideid  = $ride->id;
                $earnings = RideRequestPayment::select('total')->where('ride_request_id',$rideid)->where('company_id',  Auth::user()->company_id)->first();
                if($earnings != null){
                    $ride->earnings = $earnings->total;
                    $total_earnings = $total_earnings + $earnings->total;
                }else{
                    $ride->earnings = 0;
                }
            }
            $data['rides'] = $dataval;
            $data['total_rides'] = $orderCounts;
            $data['revenue_value'] = $total_earnings;
            $data['cancelled_rides'] = $cancelledQuery;
            return Helper::getResponse(['data' => $data]);

        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function requestHistoryDetails($id)
	{
		try {
			$data = RideRequest::with('user', 'provider','rating','payment')->findOrFail($id);

            return Helper::getResponse(['data' => $data]);

        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }
    

    public function statement_provider(Request $request)
    {

        try{

		$datum = Provider::where('company_id', Auth::user()->company_id);

        if($request->has('search_text') && $request->search_text != null) {
                $datum->Search($request->search_text);
            }

            if($request->has('order_by')) {
                $datum->orderby($request->order_by, $request->order_direction);
            }

         $Providers = $datum->paginate(10);

		foreach($Providers as $index => $Provider){

			$Rides = RideRequest::where('provider_id',$Provider->id)
						->where('status','<>','CANCELLED')
						->get()->pluck('id');

			$Providers[$index]->rides_count = $Rides->count();

			$Providers[$index]->payment = RideRequestPayment::whereIn('ride_request_id', $Rides)
							->select(\DB::raw(
							   'SUM(ROUND(provider_pay)) as overall'
							))->get();
		}

            return Helper::getResponse(['data' => $Providers]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

   public function statement_user(Request $request)
   {

	try{

		$datum = User::where('company_id', Auth::user()->company_id);

        if($request->has('search_text') && $request->search_text != null) {
            $datum->Search($request->search_text);
        }

        if($request->has('order_by')) {
            $datum->orderby($request->order_by, $request->order_direction);
        }

            $Users = $datum->paginate(10);

            foreach($Users as $index => $User){

                $Rides = RideRequest::where('user_id',$User->id)
                            ->where('status','<>','CANCELLED')
                            ->get()->pluck('id');

                $Users[$index]->rides_count = $Rides->count();

                $Users[$index]->payment = RideRequestPayment::whereIn('ride_request_id', $Rides)
                                ->select(\DB::raw(
                                'SUM(ROUND(total)) as overall' 
                                ))->get();
            }			

            return Helper::getResponse(['data' => $Users]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function rate(Request $request) {

      $this->validate($request, [
              'id' => 'required|numeric|exists:transport.ride_requests,id,user_id,'.Auth::guard('user')->user()->id,
              'rating' => 'required|integer|in:1,2,3,4,5',
              'comment' => 'max:255',
              'admin_service_id' => 'required|integer',
          ],['comment.max'=>'character limit should not exceed 255']);

      try{


        $admin_service = AdminService::where('admin_service_name', 'TRANSPORT')->where('company_id', Auth::guard('user')->user()->company_id)->first();
  
        $rideRequest = RideRequest::findOrFail($request->id);

        if ($rideRequest->paid == '0') {

          return Helper::getResponse(['status' => 422, 'message' => trans('api.user.not_paid'), 'error' => trans('api.user.not_paid')  ]);
        }

        $ratingRequest = Rating::where('request_id', $rideRequest->id)
                     ->where('admin_service_id', $admin_service->id )->first();          
          
          if($ratingRequest == null) {
                $request->request->add(['company_id' => $rideRequest->company_id ]);
                $request->request->add(['provider_id' => $rideRequest->provider_id ]);
                $request->request->add(['user_id' => $rideRequest->user_id ]);
                $request->request->add(['request_id' => $rideRequest->id ]);
                (new \App\Http\Controllers\V1\Common\CommonController)->rating($request);
          } else {
              $rideRequest->rating->update([
                    'user_rating' => $request->rating,
                    'user_comment' => $request->comment,
                  ]);
          }

          $rideRequest->user_rated = 1;
          $rideRequest->save();

          $average = Rating::where('provider_id', $rideRequest->provider_id)->avg('user_rating');

          Provider::where('id',$rideRequest->provider_id)->update(['rating' => $average]);

          // Send Push Notification to Provider 

          return Helper::getResponse(['message' => trans('api.ride.provider_rated') ]);

      } catch (Exception $e) {
          return Helper::getResponse(['status' => 500, 'message' => trans('api.ride.request_completed'), 'error' => $e->getMessage() ]);
      }
    }


    public function payment(Request $request) {

        $this->validate($request, [
            'id' => 'required|numeric|exists:transport.ride_requests,id',
        ]);
       
       try {

            $tip_amount = 0;

            $UserRequest = \App\Models\Transport\RideRequest::find($request->id);
            $payment = \App\Models\Transport\RideRequestPayment::where('ride_request_id', $request->id)->first();

            $user = User::find($UserRequest->user_id);

            $setting = Setting::where('company_id', $UserRequest->company_id)->first();

            $settings = json_decode(json_encode($setting->settings_data));
            $siteConfig = $settings->site;
            $transportConfig = $settings->transport;
            $paymentConfig = json_decode( json_encode( $settings->payment ) , true);

            $cardObject = array_values(array_filter( $paymentConfig, function ($e) { return $e['name'] == 'card'; }));
            $card = 0;

            $stripe_secret_key = "";
            $stripe_publishable_key = "";
            $stripe_currency = "";

            if(count($cardObject) > 0) { 
                $card = $cardObject[0]['status'];

                $stripeSecretObject = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'stripe_secret_key'; }));
                $stripePublishableObject = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'stripe_publishable_key'; }));
                $stripeCurrencyObject = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'stripe_currency'; }));

                if(count($stripeSecretObject) > 0) {
                    $stripe_secret_key = $stripeSecretObject[0]['value'];
                }

                if(count($stripePublishableObject) > 0) {
                    $stripe_publishable_key = $stripePublishableObject[0]['value'];
                }

                if(count($stripeCurrencyObject) > 0) {
                    $stripe_currency = $stripeCurrencyObject[0]['value'];
                }
            }

            $random = $transportConfig->booking_prefix.mt_rand(100000, 999999);
            

            if (isset($request->tips) && !empty($request->tips)) {
                $tip_amount = round($request->tips, 2);
            }

            $totalAmount = $payment->payable + $tip_amount;


            $paymentMode = $request->has('payment_mode') ? strtoupper($request->payment_mode) : $UserRequest->payment_mode;
            

            if($paymentMode != 'CASH') {

                if ($totalAmount == 0) {

                    $UserRequest->payment_mode = $paymentMode;
                    $payment->card = $payment->payable;
                    $payment->payable = 0;
                    $payment->tips = $tip_amount;
                    $payment->provider_pay = $payment->provider_pay + $tip_amount;
                    $payment->save();

                    $UserRequest->paid = 1;
                    $UserRequest->status = 'COMPLETED';
                    $UserRequest->save();

                    //for create the transaction
                    (new TripController)->callTransaction($request->id);

                    $requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.$UserRequest->company_id, 'id' => $UserRequest->id, 'city' => ($setting->demo_mode == 0) ? $UserRequest->city_id : 0, 'user' => $UserRequest->user_id ];
                    app('redis')->publish('newRequest', json_encode( $requestData ));

                    return Helper::getResponse(['message' => trans('api.paid')]);

                } else {

                    $log = new PaymentLog();
                    $log->company_id = $user->company_id;
                    $log->admin_service = 'TRANSPORT';
                    $log->user_type = 'user';
                    $log->transaction_code = $random;
                    $log->amount = $totalAmount;
                    $log->transaction_id = $UserRequest->id;
                    $log->payment_mode = $paymentMode;
                    $log->user_id = $UserRequest->user_id;
                    $log->save();
                    switch ($paymentMode) {
                        case 'CARD':

                        if($request->has('card_id')) {
                            Card::where('card_id', $request->card_id)->update(['is_default' => 1]);
                        }

                        $card = Card::where('user_id', $UserRequest->user_id)->where('is_default', 1)->first();

                        if($card == null)  $card = Card::where('user_id', $UserRequest->user_id)->first();

                        $gateway = new PaymentGateway('stripe');

                        $response = $gateway->process([
                            'order' => $random,
                            "amount" => $totalAmount,
                            "currency" => $stripe_currency,
                            "customer" => $user->stripe_cust_id,
                            "card" => $card->card_id,
                            "description" => "Payment Charge for " . $user->email,
                            "receipt_email" => $user->email,
                        ]);

                        break;
                    }
                    if($response->status == "SUCCESS") {

                        $payment->payment_id = $response->payment_id;
                        $payment->payment_mode = $paymentMode;
                        $payment->card = $payment->payable;
                        $payment->payable = 0;
                        $payment->tips = $tip_amount;
                        $payment->total = $totalAmount;
                        $payment->provider_pay = $payment->provider_pay + $tip_amount;
                        $payment->save();

                        $UserRequest->paid = 1;
                        $UserRequest->status = 'COMPLETED';
                        $UserRequest->save();
                        //for create the transaction
                        (new TripController)->callTransaction($request->id);

                        $requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.$UserRequest->company_id, 'id' => $UserRequest->id, 'city' => ($setting->demo_mode == 0) ? $UserRequest->city_id : 0, 'user' => $UserRequest->user_id ];
                        app('redis')->publish('newRequest', json_encode( $requestData ));

                        return Helper::getResponse(['message' => trans('api.paid')]);

                    } else {
                        return Helper::getResponse(['message' => trans('Transaction Failed')]);
                    }
                }

            } else {
                $UserRequest->paid = 1;
                $UserRequest->status = 'COMPLETED';
                $UserRequest->save();
                //for create the transaction
                (new TripController)->callTransaction($request->id);

                $requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.$UserRequest->company_id, 'id' => $UserRequest->id, 'city' => ($setting->demo_mode == 0) ? $UserRequest->city_id : 0, 'user' => $UserRequest->user_id ];
                app('redis')->publish('newRequest', json_encode( $requestData ));

                return Helper::getResponse(['message' => trans('api.paid')]);
            }

        } catch (\Throwable $e) {
             return Helper::getResponse(['status' => 500, 'message' => trans('api.ride.request_not_completed'), 'error' => $e->getMessage() ]);
        }
    }
}