<?php

namespace App\Http\Controllers\V1\Service\User;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\SendPushNotification;
use App\Models\Common\Provider;
use App\Models\Common\RequestFilter;
use App\Models\Common\ProviderService;
use App\Models\Common\Promocode;
use App\Models\Common\Rating;
use App\Helpers\Helper;
use App\Models\Service\ServiceCityPrice;
use App\Models\Service\Service;
use App\Models\Service\ServiceCategory;
use App\Models\Service\ServiceSubcategory;
use App\Models\Service\ServiceCancelProvider;
use App\Models\Service\ServiceRequestDispute;
use App\Models\Service\ServiceRequestPayment;
use App\Models\Service\ServiceRequest;
use App\Models\Common\Setting;
use App\Models\Common\Country;
use App\Models\Common\CompanyCountry;
use App\Models\Common\State;
use App\Models\Common\User;
use App\Models\Common\Card;
use App\Models\Common\Reason;
use App\Models\Common\AdminService;
use App\Models\Common\UserRequest;
use App\Models\Common\PaymentLog;
use App\Services\PaymentGateway;
use App\Http\Controllers\V1\Service\Provider\ServeController;
use App\Http\Controllers\V1\Common\Provider\HomeController;
use App\Http\Controllers\V1\Common\CommonController;
use App\Models\Common\MenuCity;
use App\Models\Common\Menu;
use App\Models\Common\CompanyCity;
use Carbon\Carbon;
use Auth;
use Admin;
use DB;

class ServiceController extends Controller
{
    public function providerServiceList(Request $request)
    {
        $settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));

        $siteConfig = $settings->site;
        $serviceConfig = $settings->service;

        $distance = $serviceConfig->provider_search_radius ? $serviceConfig->provider_search_radius : 100;
       
        $latitude = $request->lat;
        $longitude = $request->long;
        $service_id = $request->id;
        
        //$timezone =  (Auth::guard('user')->user()->state_id) ? State::find(Auth::guard('user')->user()->state_id)->timezone : '';
        // $currency =  Country::find(Auth::guard('user')->user()->country_id) ? Country::find(Auth::guard('user')->user()->country_id)->country_currency : '' ;

        $admin_service = AdminService::where('admin_service_name','SERVICE')->where('company_id', Auth::guard('user')->user()->company_id)->first();

        $currency = CompanyCountry::where('company_id',Auth::guard('user')->user()->company_id)->where('country_id',Auth::guard('user')->user()->country_id)->first();
        $service_cancel_provider = ServiceCancelProvider::select('id','provider_id')->where('company_id',Auth::guard('user')->user()->company_id)->where('user_id',Auth::guard('user')->user()->id)->pluck('provider_id','provider_id')->toArray();
    
        $admin_id=$admin_service->id;

        $callback = function ($q) use ($admin_id, $service_id) {
            $q->where('admin_service_id',$admin_id);
            $q->where('service_id',$service_id);
        };

  
        $provider_service_init = Provider::with(['service'=> $callback,'service_city'=>function($q) use ($service_id){
            return $q->where('service_id',$service_id);

        },'request_filter'])
        ->select(DB::Raw("(6371 * acos( cos( radians('$latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(latitude) ) ) ) AS distance"),'id','first_name','picture','rating','city_id','latitude','longitude')
        ->where('status', 'approved')
        ->where('is_online',1)->where('is_assigned',0)
        ->where('company_id', Auth::guard('user')->user()->company_id)
        ->where('city_id', Auth::guard('user')->user()->city_id)
        ->whereRaw("(6371 * acos( cos( radians('$latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(latitude) ) ) ) <= $distance")
        ->whereDoesntHave('request_filter')
        ->whereHas('service', function($q) use ($admin_id, $service_id){          
            $q->where('admin_service_id',$admin_id);
            $q->where('service_id',$service_id);
        })
        ->where('wallet_balance' ,'>=',$siteConfig->provider_negative_balance);
        if($request->has('name')){
            $provider_service_init->where('first_name','LIKE', '%' . $request->name . '%');
            //$provider_service_init->orderBy('first_name','asc');
        }
        $provider_service_init->orderBy('distance','asc');
        

        $provider_service_init->whereNotIn('id',$service_cancel_provider);
        $provider_service = $provider_service_init->get();

        if($provider_service){
            $providers = [];            
            if(!empty($provider_service[0]->service)){
                $serviceDetails=Service::with('serviceCategory')->where('id',$service_id)->where('company_id',Auth::guard('user')->user()->company_id)->first();
                foreach($provider_service as $key=> $service){ 
                    unset($service->request_filter);
                    $provider = new \stdClass();                   
                    $provider->distance=$service->distance;
                    $provider->id=$service->id;
                    $provider->first_name=$service->first_name;
                    $provider->picture=$service->picture;
                    $provider->rating=$service->rating;
                    $provider->city_id=$service->city_id;
                    $provider->latitude=$service->latitude;
                    $provider->longitude=$service->longitude;
                    if($service->service_city==null){
                        $provider->fare_type='FIXED';
                        $provider->base_fare='0';
                        $provider->per_miles='0';
                        $provider->per_mins='0';
                        $provider->price_choose='';
                    }
                    else{                       
                        $provider->fare_type=$service->service_city->fare_type;
                        if($serviceDetails->serviceCategory->price_choose=='admin_price'){
                           if(!empty($request->qty))
                               $provider->base_fare=number_format($service->service_city->base_fare*$request->qty,2,'.','');
                           else
                               $provider->base_fare=number_format($service->service_city->base_fare,2,'.','');

                           $provider->per_miles=number_format($service->service_city->per_miles,2,'.','');
                           $provider->per_mins=number_format($service->service_city->per_mins*60,2,'.','');

                       }
                       else{
                           if(!empty($request->qty))
                               $provider->base_fare=number_format($service->service->base_fare*$request->qty,2,'.','');
                           else
                               $provider->base_fare=number_format($service->service->base_fare,2,'.','');

                           $provider->per_miles=number_format($service->service->per_miles,2,'.','');
                           $provider->per_mins=number_format($service->service->per_mins*60,2,'.','');
                       }

                        $provider->price_choose=$serviceDetails->serviceCategory->price_choose;
                    }    
                                            
                    $providers[] = $provider;
                }                

            }       

            return Helper::getResponse(['data' =>['provider_service' => $providers,'currency' => ($currency != null) ? $currency->currency: '']]);

        }
    }

    public function review(Request $request,$id)
    {
        $admin_service = AdminService::where('admin_service_name','SERVICE')->where('company_id', Auth::guard('user')->user()->company_id)->first();
        if($request->has('limit')) {
            $review = Rating::select('id','admin_service_id','user_id','provider_id','provider_rating','provider_comment','user_comment','user_rating','created_at')->where('provider_id',$id)->where(['company_id'=>Auth::guard('user')->user()->company_id])->where('admin_service_id',$admin_service->id)
            ->with([
                    'user' => function($query){  $query->select('id', 'first_name', 'last_name', 'picture' ); },
            ])->take($request->limit)->offset($request->offset)->get();
        }else{
            $review = Rating::select('id','admin_service_id','user_id','provider_id','provider_rating','provider_comment','user_comment','user_rating','created_at')->where('provider_id',$id)->where(['company_id'=>Auth::guard('user')->user()->company_id])->where('admin_service_id',$admin_service->id)
            ->with([
                    'user' => function($query){  $query->select('id', 'first_name', 'last_name', 'picture' ); },
            ])->orderby('id','desc')->get();
        }
        $jsonResponse['total_records'] = count($review);
        $jsonResponse['review'] = $review;
        if($jsonResponse){
            return Helper::getResponse(['data' =>$jsonResponse]);
        }
    }

    public function service(Request $request,$id)
    {
        $service = Service::where('id',$id)->where(['company_id'=>Auth::guard('user')->user()->company_id])->first();
        if($service){
            return Helper::getResponse(['data' =>$service]);
        }
    }
    public function cancel_request(Request $request,$id)
    {
        try{
            //add cancel_request
            $service_cancel_provider = new ServiceCancelProvider;
            $service_cancel_provider->company_id = Auth::guard('user')->user()->company_id;;
            $service_cancel_provider->user_id = Auth::guard('user')->user()->id;;
            $service_cancel_provider->provider_id = $id;
            $service_cancel_provider->service_id = 1;
            $service_cancel_provider->save();
            return Helper::getResponse(['message' => trans('Cancel the Provider request')]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 500, 'message' => trans('api.ride.request_not_completed'), 'error' => $e->getMessage() ]);
        }
    }

    //For Promocode
    public function promocode(Request $request)
    {
        $promocodes = Promocode::where('company_id', Auth::guard('user')->user()->company_id)->where('service', 'SERVICE')
            ->where('expiration','>=',date("Y-m-d H:i"))
            ->whereDoesntHave('promousage', function($query) {
                $query->where('user_id',Auth::guard('user')->user()->id);
            })
            ->get();

        return Helper::getResponse(['data' => $promocodes]);
    }

     //For Promocode
     public function update_service(Request $request,$id)
     {
         $update_service = Service::where('id',$id)->update(['allow_desc' =>'0']);
         return Helper::getResponse(['data' => $update_service]);
     }

    //Create the ride
    public function create_service(Request $request)
    {
        $this->validate($request, [
            'service_id' => 'required|integer|exists:service.services,id',
            's_latitude' => 'required',
            's_longitude' => 'required',
            'payment_mode' => 'required',
            'id' => 'required',
        ]);
        $provider_id = $request->id;
        $provider = Provider::find($provider_id);
        $company_id = Auth::guard('user')->user()->company_id; 
        $admin_service_id = AdminService::where('admin_service_name','SERVICE')->where('company_id', $company_id)->first();

        $FilterCheck = RequestFilter::where(['admin_service_id'=>$admin_service_id->id,'provider_id'=>$provider_id,'company_id'=>$company_id])->first();

        if($FilterCheck != null) {
            return Helper::getResponse(['status' => 422, 'message' => trans('api.ride.request_inprogress')]);
        }

        $ActiveRequests = ServiceRequest::PendingRequest(Auth::guard('user')->user()->id)->count();

        if($ActiveRequests > 0) {
            return Helper::getResponse(['status' => 422, 'message' => trans('api.ride.request_inprogress')]);
        }

        $setting = Setting::where('company_id', Auth::guard('user')->user()->company_id)->first();

        $settings = json_decode(json_encode($setting->settings_data));

        $siteConfig = $settings->site;

        $serviceConfig = $settings->service;
        
        $timezone =  (Auth::guard('user')->user()->state_id) ? State::find(Auth::guard('user')->user()->state_id)->timezone : '';
        // $currency =  Country::find(Auth::guard('user')->user()->country_id) ? Country::find(Auth::guard('user')->user()->country_id)->country_currency : '' ;
        $currency = CompanyCountry::where('company_id',$company_id)->where('country_id',Auth::guard('user')->user()->country_id)->first();
        if($request->has('schedule_date') && $request->has('schedule_time')){
            $schedule_date = (Carbon::createFromFormat('Y-m-d H:i:s', (Carbon::parse($request->schedule_date. ' ' .$request->schedule_time)->format('Y-m-d H:i:s')), $timezone))->setTimezone('UTC');
           
            $beforeschedule_time = (new Carbon($schedule_date))->subHour(1);
            $afterschedule_time = (new Carbon($schedule_date))->addHour(1);

            $CheckScheduling = ServiceRequest::where('status','SCHEDULED')
                            ->where('user_id', Auth::guard('user')->user()->id)
                            ->whereBetween('schedule_at',[$beforeschedule_time,$afterschedule_time])
                            ->count();


            if($CheckScheduling > 0){
                return Helper::getResponse(['status' => 422, 'message' => trans('api.ride.request_already_scheduled')]);
            }

        }
        $distance = $serviceConfig->provider_search_radius ? $serviceConfig->provider_search_radius : 100;
        // $distance = config('constants.provider_search_radius', '10');

        $latitude =$request->s_latitude;
        $longitude = $request->s_longitude;
        $service_id = $request->service_id;


        $Provider = Provider::with('service','rating')
            ->select(DB::Raw("(6371 * acos( cos( radians('$latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(latitude) ) ) ) AS distance"),'id','first_name','picture')
            ->where('id', $provider_id)
            ->orderBy('distance','asc')
            ->first();


        $admin_service_id = AdminService::where('admin_service_name','SERVICE')->where('company_id', Auth::guard('user')->user()->company_id)->first();

        try {
            $details = "https://maps.googleapis.com/maps/api/directions/json?origin=".$request->s_latitude.",".$request->s_longitude."&mode=driving&key=".$siteConfig->browser_key;

            $json = Helper::curl($details);

            $details = json_decode($json, TRUE);

            $route_key = (count($details['routes']) > 0) ? $details['routes'][0]['overview_polyline']['points'] : '';

            $serviceRequest = new ServiceRequest;
            $serviceRequest->company_id = Auth::guard('user')->user()->company_id;
            $prefix = $serviceConfig->booking_prefix;
            $serviceRequest->booking_id = Helper::generate_booking_id($prefix);
            $serviceRequest->admin_service_id =  $admin_service_id->id;
            $serviceRequest->timezone = (Auth::guard('user')->user()->state_id) ? State::find               (Auth::guard('user')->user()->state_id)->timezone : '';
            $serviceRequest->user_id = Auth::guard('user')->user()->id;
            
            //$serviceRequest->provider_service_id = $request->service_id;
            $serviceRequest->service_id = $request->service_id;
            $serviceRequest->provider_id =  $provider_id;
            //$serviceRequest->rental_hours = $request->rental_hours;
            $serviceRequest->payment_mode = $request->payment_mode;
            $serviceRequest->promocode_id = $request->promocode_id ? : 0;
            
            $serviceRequest->status = 'SEARCHING';

            $serviceRequest->timezone = $timezone;
            $serviceRequest->currency = ($currency != null) ? $currency->currency : '' ;

            $serviceRequest->city_id = Auth::guard('user')->user()->city_id;
            $serviceRequest->country_id = Auth::guard('user')->user()->country_id;

            $serviceRequest->s_address = $request->s_address ? $request->s_address : "Address";

            $serviceRequest->s_latitude = $latitude;
            $serviceRequest->s_longitude = $longitude;

            $serviceRequest->track_latitude = $latitude;
            $serviceRequest->track_longitude =  $longitude;

            $serviceRequest->allow_description = $request->allow_description;
            if($request->hasFile('allow_image')) {
                $serviceRequest->allow_image = Helper::upload_file($request->file('allow_image'), 'service/image', null, Auth::guard('user')->user()->company_id);
            }
            $serviceRequest->quantity = $request->quantity;
            $serviceRequest->price = $request->price;

            $serviceRequest->distance = $request->distance ? $request->distance  : 0;
            $serviceRequest->unit = config('constants.distance', 'Kms');

            if(Auth::guard('user')->user()->wallet_balance > 0){
                $serviceRequest->use_wallet = $request->use_wallet ? : 0;
            }

            $serviceRequest->otp = mt_rand(1000 , 9999);

            $serviceRequest->assigned_at = (Carbon::now())->toDateTimeString();
            $serviceRequest->route_key = $route_key;
            $serviceRequest->admin_id = $provider->admin_id;

            /*if($Providers->count() <= config('constants.surge_trigger') && $Providers->count() > 0){
                $serviceRequest->surge = 1;
            }*/

            if($request->has('schedule_date') && $request->has('schedule_time')){
                $serviceRequest->status = 'SCHEDULED';
                $serviceRequest->schedule_at = (Carbon::createFromFormat('Y-m-d H:i:s', (Carbon::parse($request->schedule_date. ' ' .$request->schedule_time)->format('Y-m-d H:i:s')), $timezone))->setTimezone('UTC');
                $serviceRequest->is_scheduled = 'YES';
            }
            if($serviceRequest->status != 'SCHEDULED') {
                if($serviceConfig->manual_request == 0 && $serviceConfig->broadcast_request == 0) {
                    //Log::info('New Request id : '. $rideRequest->id .' Assigned to provider : '. $rideRequest->provider_id);
                    // (new SendPushNotification)->IncomingRequest($Providers[0]->id, 'service');
                }
            }   
            $serviceRequest->save();
            if($serviceConfig->manual_request == 1) {

                // $admins = Admin::select('id')->get();

                // foreach ($admins as $admin_id) {
                //     $admin = Admin::find($admin_id->id);
                //     //$admin->notify(new WebPush("Notifications", trans('api.push.incoming_request'), route('admin.dispatcher.index') ));
                // }

            }
            // update payment mode
            User::where('id',Auth::guard('user')->user()->id)->update(['payment_mode' => $request->payment_mode]);

            if($request->has('card_id')){

                Card::where('user_id',Auth::guard('user')->user()->id)->update(['is_default' => 0]);
                Card::where('card_id',$request->card_id)->update(['is_default' => 1]);
            }

            $serviceRequest = ServiceRequest::with('service','service.serviceCategory','service.servicesubCategory')->where('id', $serviceRequest->id)->first();

            //Add the Log File for ride
            $serviceRequestId = $serviceRequest->id;
            $user_request = new UserRequest();
            $user_request->request_id = $serviceRequest->id;
            $user_request->user_id = $serviceRequest->user_id;
            $user_request->provider_id = $serviceRequest->provider_id;
            $user_request->schedule_at = $serviceRequest->schedule_at;
            $user_request->company_id = Auth::guard('user')->user()->company_id;
            $user_request->admin_service_id =$admin_service_id->id;
            $user_request->status = $serviceRequest->status;
            $user_request->request_data = json_encode($serviceRequest);
            $user_request->save();

            if($serviceRequest->status != 'SCHEDULED') {
                if($serviceConfig->manual_request == 0){
                    (new SendPushNotification)->IncomingRequest($Provider->id, 'service');
                   /* if($serviceConfig->broadcast_request == 1){
                       //(new SendPushNotification)->IncomingRequest($Provider->id, 'service'); 
                    }*/

                    /*$unwantedRequests = RequestFilter::select('id')->whereHas('accepted_request')->where('provider_id', $Provider->id)->get();

                    foreach ($unwantedRequests as $unwantedRequest) {
                        $unwantedRequest->delete();
                    }*/

                    $Filter = new RequestFilter;
                    // Send push notifications to the first provider
                    // incoming request push to provider
                    $Filter->admin_service_id = $admin_service_id->id;
                    $Filter->request_id = $user_request->id;
                    $Filter->provider_id = $provider_id; 
                    $Filter->company_id = Auth::guard('user')->user()->company_id; 
                    $Filter->save(); 
                }
                  //Send message to socket
            $requestData = ['type' => 'SERVICE', 'room' => 'room_'.Auth::guard('user')->user()->company_id, 'id' => $serviceRequest->id, 'city' => ($setting->demo_mode == 0) ? $serviceRequest->city_id : 0, 'user' => $serviceRequest->user_id ];
            app('redis')->publish('newRequest', json_encode( $requestData ));

            }          

            return Helper::getResponse([ 'data' => [
                'message' => ($serviceRequest->status == 'SCHEDULED') ? 'Schedule request created!' : 'New request created!',
                'request_id' => $serviceRequest->id
            ]]);

        } catch (Exception $e) {  
            return Helper::getResponse(['status' => 500, 'message' => trans('api.service.request_not_completed'), 'error' => $e->getMessage() ]);
        }
    }

    //status
    public function status(Request $request)
    {
        try{

            $settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));

            $siteConfig = $settings->site;

            $serviceConfig = $settings->service;

            $check_status = ['CANCELLED', 'SCHEDULED'];
            $serviceRequest = ServiceRequest::ServiceRequestStatusCheck(Auth::guard('user')->user()->id, $check_status)
                              ->get();
                                       
            $search_status = ['SEARCHING','SCHEDULED'];
            $serviceRequestFilter = ServiceRequest::ServiceRequestAssignProvider(Auth::guard('user')->user()->id,$search_status)->get(); 
            $Timeout = $serviceConfig->provider_select_timeout ? $serviceConfig->provider_select_timeout : 60 ;
            $response_time = $Timeout;
            if(!empty($serviceRequest)){
                // $serviceRequest[0]['ride_otp'] = (int) $serviceConfig->serve_otp ? $serviceConfig->serve_otp : 0 ;

                // $serviceRequest[0]['reasons']=Reason::where('type','USER')->get();
                // $categoryId = $serviceRequest[0]['service']['service_category_id'];
                foreach($serviceRequest as $key=>$requestlist){
                    $categoryId = $requestlist->service->service_category_id;
                    $subCategoryId = $requestlist->service->service_subcategory_id;
                    $requestlist->category = ServiceCategory::where('id',$categoryId)->first();
                    $requestlist->subcategory = ServiceSubCategory::where('id',$subCategoryId)->first();
                    $requestlist->reasons =Reason::where('type','USER')->get();
                    $response_time = $Timeout - (time() - strtotime($serviceRequest[$key]->assigned_at));
                }
				
            }

           
            

            /*if(!empty($serviceRequestFilter)){
                for ($i=0; $i < sizeof($serviceRequestFilter); $i++) {
                    $ExpiredTime = $Timeout - (time() - strtotime($serviceRequestFilter[$i]->assigned_at));
                    if($serviceRequestFilter[$i]->status == 'SEARCHING' && $ExpiredTime < 0) {
                        $Providertrip = new HomeController();
                        $Providertrip->assign_next_provider($serviceRequestFilter[$i]->id);
                        $response_time = $Timeout - (time() - strtotime($serviceRequestFilter[$i]->assigned_at));
                    }else if($serviceRequestFilter[$i]->status == 'SEARCHING' && $ExpiredTime > 0){
                        break;
                    }
                }

            }*/
            if(empty($serviceRequest)) {

                $cancelled_request = ServiceRequest::where('service_requests.user_id', Auth::guard('user')->user()->id)
                    ->where('service_requests.user_rated',0)
                    ->where('service_requests.status', ['CANCELLED'])->orderby('updated_at', 'desc')
                    ->where('updated_at','>=',\Carbon\Carbon::now()->subSeconds(5))
                    ->first();
                
            }
            return Helper::getResponse(['data' => [
                'response_time' => $response_time, 
                'data' => $serviceRequest, 
                'sos' => isset($siteConfig->sos_number) ? $siteConfig->sos_number : '911' , 
                'emergency' => isset($siteConfig->contact_number) ? $siteConfig->contact_number : [['number' => '911']]  ]]);

        } catch (Exception $e) {
            return Helper::getResponse(['status' => 500, 'message' => trans('api.something_went_wrong'), 'error' => $e->getMessage() ]);
        }
    }

    public function cancel_service(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|numeric|exists:service.service_requests,id,user_id,'.Auth::guard('user')->user()->id,
        ]);
        try{
            $setting = Setting::where('company_id', Auth::guard('user')->user()->company_id)->first();
            $serviceRequest = ServiceRequest::findOrFail($request->id);
            if($serviceRequest->status == 'CANCELLED')
            {
                return Helper::getResponse(['status' => 404, 'message' => trans('api.service.already_cancelled')]);
            }
            if($serviceRequest->status == 'PICKEDUP' || $serviceRequest->status == 'DROPPED' || $serviceRequest->status == 'COMPLETED'){
                return Helper::getResponse(['status' => 404, 'message' => trans('api.service.request_inprogress')]);
            }else{
                if($serviceRequest->status != 'SEARCHING'){
                    $this->validate($request, [
                        'cancel_reason'=> 'max:255',
                    ]);
                }
                $serviceRequest->status = 'CANCELLED';
                if($request->cancel_reason=='ot'){
                    $serviceRequest->cancel_reason = $request->cancel_reason_opt;
                }else{
                    $serviceRequest->cancel_reason = $request->cancel_reason;
                }
                $serviceRequest->cancelled_by = 'USER';
                $serviceRequest->save();

                $admin_service = AdminService::where('admin_service_name', 'SERVICE')->where('company_id', Auth::guard('user')->user()->company_id)->first();
                $user_request = UserRequest::where('admin_service_id', $admin_service->id)->where('request_id',$serviceRequest->id)->first();
                if($user_request != null){
                    $requestFilter = RequestFilter::where('admin_service_id', $admin_service->id )->where('request_id', $user_request->id)->first();
                    $user_request->delete();
                    if($requestFilter != null){
                        $requestFilter->delete();
                    }
                }
                if($serviceRequest->status != 'SCHEDULED'){
                    if($serviceRequest->provider_id != null){
                        Provider::where('id', $serviceRequest->provider_id)->update(['status' => 'approved', 'is_assigned' => 0]);
                    }
                }
                // Send Push Notification to User
                //(new SendPushNotification)->UserCancellRide($rideRequest, 'service');               
                //Send message to socket
                $requestData = ['type' => 'SERVICE', 'room' => 'room_'.Auth::guard('user')->user()->company_id, 'id' => $serviceRequest->id , 'city' => ($setting->demo_mode == 0) ? $serviceRequest->city_id : 0, 'user' => $serviceRequest->user_id ];
                app('redis')->publish('newRequest', json_encode( $requestData ));
                return Helper::getResponse(['message' => trans('api.service.ride_cancelled')]);                
            }
        }catch (ModelNotFoundException $e) {
            return Helper::getResponse(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    public function update_payment_method(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|exists:service.service_requests',
            'payment_mode' => 'required',
        ]);

        try{

            if($request->has('card_id')){
                Card::where('user_id',Auth::guard('user')->user()->id)->update(['is_default' => 0]);
                Card::where('card_id',$request->card_id)->update(['is_default' => 1]);
            }

            $serviceRequest = ServiceRequest::findOrFail($request->id);
            $serviceRequest->payment_mode = $request->payment_mode;
            $serviceRequest->save();


            $payment = ServiceRequestPayment::where('service_request_id', $serviceRequest->id)->first();

            if($payment != null) {
                $payment->payment_mode = $request->payment_mode;
                $payment->save();
            }

            $admin_service = AdminService::where('admin_service_name', 'SERVICE')->where('company_id', Auth::guard('user')->user()->company_id)->first();

            $user_request = UserRequest::where('request_id', $request->id)->where('admin_service_id', $admin_service->id )->first();
            $user_request->request_data = json_encode($serviceRequest);
            $user_request->save();

            (new SendPushNotification)->updateProviderStatus($user_request->provider_id, 'provider', trans('api.service.payment_updated'), 'Payment Mode Changed', '' ); 

            return Helper::getResponse(['message' => trans('api.service.payment_updated')]);
        }

        catch (ModelNotFoundException $e) {
            return Helper::getResponse(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    public function rate(Request $request) 
    {

        $this->validate($request, [
                'rating' => 'required|integer|in:1,2,3,4,5',
                'comment' => 'max:255',
            ],['comment.max'=>'character limit should not exceed 255']);
    
        $serviceRequest = ServiceRequest::findOrFail($request->id);
        if ($serviceRequest->paid == 0) {

          return Helper::getResponse(['status' => 422, 'message' => trans('api.user.not_paid'), 'error' => trans('api.user.not_paid')  ]);
        }
        try{
            $admin_service = AdminService::where('admin_service_name', 'SERVICE')->where('company_id', Auth::guard('user')->user()->company_id)->first();

            $serviceRequest = ServiceRequest::findOrFail($request->id);

            $ratingRequest = Rating::where('request_id', $serviceRequest->id)
                     ->where('admin_service_id', $admin_service->id )->first();
            
            if($ratingRequest == null) {
                $request->request->add(['company_id' => $serviceRequest->company_id ]);
                $request->request->add(['provider_id' => $serviceRequest->provider_id ]);
                $request->request->add(['user_id' => $serviceRequest->user_id ]);
                $request->request->add(['request_id' => $serviceRequest->id ]);
                (new \App\Http\Controllers\V1\Common\CommonController)->rating($request);
            } else {
                $serviceRequest->rating->update([
                      'user_rating' => $request->rating,
                      'user_comment' => $request->comment,
                    ]);
            }
            $serviceRequest->user_rated = 1;            
            $serviceRequest->save();
  
            $average = Rating::where('provider_id', $serviceRequest->provider_id)->avg('user_rating');
            
            $User = User::find($serviceRequest->user_id);
            $User->rating=$average;
            $User->save();

            // Send Push Notification to Provider
            return Helper::getResponse(['message' => trans('api.service.service_rated') ]);
  
        } catch (Exception $e) {
            return Helper::getResponse(['status' => 500, 'message' => trans('api.ride.request_completed'), 'error' => $e->getMessage() ]);
        }
      }

      public function payment(Request $request) {
         
         try {
  
              $tip_amount = 0;
  
              $serviceRequest = \App\Models\Service\ServiceRequest::find($request->id);
              $payment = \App\Models\Service\ServiceRequestPayment::where('service_request_id', $request->id)->first();
  
              $user = User::find($serviceRequest->user_id);
              $setting = Setting::where('company_id', $user->company_id)->first();
              $settings = json_decode(json_encode($setting->settings_data));
              $siteConfig = $settings->site;
              $serviceConfig = $settings->service;
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
  
              $random = $serviceConfig->booking_prefix.mt_rand(100000, 999999);
  
              if (isset($request->tips) && !empty($request->tips)) {
                  $tip_amount = round($request->tips, 2);
              }
  
              $totalAmount = $payment->payable + $tip_amount;
  
              $paymentMode = $request->has('payment_mode') ? strtoupper($request->payment_mode) : $serviceRequest->payment_mode;
              if($request->payment_mode != $paymentMode) {
  
                  if ($totalAmount == 0) {
  
                      $serviceRequest->payment_mode = $paymentMode;
                      $payment->card = $payment->payable;
                      $payment->payable = 0;
                      $payment->tips = $tip_amount;
                      $payment->provider_pay = $payment->provider_pay + $tip_amount;
                      $payment->save();
  
                      $serviceRequest->paid = 1;
                      $serviceRequest->status = 'COMPLETED';
                      $serviceRequest->save();

                       $requestData = ['type' => 'SERVICE', 'room' => 'room_'.$serviceRequest->company_id, 'id' => $serviceRequest->id, 'city' => ($setting->demo_mode == 0) ? $serviceRequest->city_id : 0, 'user' => $serviceRequest->user_id ];
                        app('redis')->publish('newRequest', json_encode( $requestData ));
  
                      return Helper::getResponse(['message' => trans('api.paid')]);
  
                  } else {
  
                      $log = new PaymentLog();
                      $log->admin_service = 'SERVICE';
                      $log->company_id = $user->company_id;
                      $log->user_type = 'user';
                      $log->transaction_code = $random;
                      $log->amount = $totalAmount;
                      $log->transaction_id = $serviceRequest->id;
                      $log->payment_mode = $paymentMode;
                      $log->user_id = $serviceRequest->user_id;
                      $log->save();
                      switch ($paymentMode) {
                          case 'CARD':  
                          $card = Card::where('user_id', $serviceRequest->user_id)->where('is_default', 1)->first();
  
                          if($card == null)  $card = Card::where('user_id', $serviceRequest->user_id)->first();
                          if($card == null){
                            return Helper::getResponse(['status' => 500,'message' => trans('api.add_card_required')]); 
                          }
  
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

                          $serviceRequest->paid = 1;
                          $serviceRequest->status = 'COMPLETED';
                          $serviceRequest->save();

                          //for create the transaction
                          (new ServeController)->callTransaction($serviceRequest->id);
                          $requestData = ['type' => 'SERVICE', 'room' => 'room_'.$serviceRequest->company_id, 'id' => $serviceRequest->id, 'city' => ($setting->demo_mode == 0) ? $serviceRequest->city_id : 0, 'user' => $serviceRequest->user_id ];
                            app('redis')->publish('newRequest', json_encode( $requestData ));

                          return Helper::getResponse(['message' => trans('api.paid')]);  
                      } else {
                          return Helper::getResponse(['message' => trans('Transaction Failed')]);
                      }
                  }
  
              } else {
                  $serviceRequest->paid = 1;
                  $serviceRequest->save();
                  $requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.$serviceRequest->company_id, 'id' => $serviceRequest->id, 'city' => ($setting->demo_mode == 0) ? $serviceRequest->city_id : 0, 'user' => $serviceRequest->user_id ];
                app('redis')->publish('newRequest', json_encode( $requestData ));
              }  
          } catch (\Throwable $e) {
               return Helper::getResponse(['status' => 500, 'message' => trans('api.ride.request_not_completed'), 'error' => $e->getMessage() ]);
          }
      }

    public function searchServiceDispute(Request $request)
    {
		$results=array();
		$term =  $request->input('stext');
		if($request->input('sflag')==1){			
			$queries = ServiceRequest::where('provider_id', $request->id)->with('service')->orderby('id', 'desc')->take(10)->get();
		}else{
			$queries = ServiceRequest::where('user_id', $request->id)->with('service')->orderby('id', 'desc')->take(10)->get();
		}
		foreach ($queries as $query)
		{
            $RequestDispute = ServiceRequestDispute::where('service_request_id',$query->id)->first();
            if(!$RequestDispute){
                $results[]=$query;
            }
		}
		return response()->json(array('success' => true, 'data'=>$results));
    }

    public function requestHistory(Request $request)
	{
		try {
            $history_status = array('CANCELLED','COMPLETED');
            $datum = ServiceRequest::where('company_id', Auth::user()->company_id)
                     ->whereIn('status',$history_status)
                     ->with('payment','user', 'provider');
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
    public function requestScheduleHistory(Request $request)
	{
		try {
            $scheduled_status = array('SCHEDULED');
            $datum = ServiceRequest::where('company_id', Auth::user()->company_id)
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
            $serviceRequests = ServiceRequest::where('company_id',  Auth::user()->company_id)
                     ->with('user', 'provider');

            if(Auth::user()->hasRole('FLEET')) {
                $serviceRequests->where('admin_id', Auth::user()->id);  
            }
            if($request->has('search_text') && $request->search_text != null) {
                $serviceRequests->Search($request->search_text);
            }
    
            if($request->has('order_by')) {
                $serviceRequests->orderby($request->order_by, $request->order_direction);
            }
            $type = isset($_GET['type'])?$_GET['type']:'';
            if($type == 'today'){
				$serviceRequests->where('created_at', '>=', Carbon::today());
			}elseif($type == 'monthly'){
				$serviceRequests->where('created_at', '>=', Carbon::now()->month);
			}elseif($type == 'yearly'){
				$serviceRequests->where('created_at', '>=', Carbon::now()->year);
			}elseif ($type == 'range') {   
                if($request->has('from') &&$request->has('to')) {             
                    if($request->from == $request->to) {
                        $serviceRequests->whereDate('created_at', date('Y-m-d', strtotime($request->from)));
                    } else {
                        $serviceRequests->whereBetween('created_at',[Carbon::createFromFormat('Y-m-d', $request->from),Carbon::createFromFormat('Y-m-d', $request->to)]);
                    }
                }
			}else{
                // dd(5);
            }
            $cancelservices = $serviceRequests;
            $orderCounts = $serviceRequests->count();
            $dataval = $serviceRequests->whereIn('status',$history_status)->paginate(10);
            $cancelledQuery = $cancelservices->where('status','CANCELLED')->count();
            $total_earnings = 0;
            foreach($dataval as $service){
                $service->status = $service->status == 1?'Enabled' : 'Disable';
                $serviceid  = $service->id;
                $earnings = ServiceRequestPayment::select('total')->where('service_request_id',$serviceid)->where('company_id',  Auth::user()->company_id)->first();
                if($earnings != null){
                    $service->earnings = $earnings->total;
                    $total_earnings = $total_earnings + $earnings->total;
                }else{
                    $service->earnings = 0;
                }
            }
            $data['services'] = $dataval;
            $data['total_services'] = $orderCounts;
            $data['revenue_value'] = $total_earnings;
            $data['cancelled_services'] = $cancelledQuery;
            return Helper::getResponse(['data' => $data]);

        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }
    public function requestHistoryDetails($id)
	{
		try {
			$data = ServiceRequest::with('user', 'provider','rating','service','serviceCategory')->findOrFail($id);
            return Helper::getResponse(['data' => $data]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

     public function webproviderservice(Request $request,$id)
    {
     
     try{
        $storetype=Service::with(array('provideradminservice'=>function($query) use ($id){
            $query->where('provider_id',$id);
        }))->with('serviceCategory','servicesubCategory')->where('company_id',Auth::user()->company_id)->get();

        return Helper::getResponse(['data' => $storetype ]);
    }catch (ModelNotFoundException $e) {
            return Helper::getResponse(['status' => 500, 'error' => $e->getMessage()]);
        }

    }

     public function getcity(Request $request)
    {
         //dd($request->city_id);
        $menudetails=Menu::select('menu_type_id')->where('id',$request->city_id)->first();
       
        $serviceprice=ServiceCityPrice::select('city_id')->where('service_id',$menudetails->menu_type_id)->get()->toArray();
        $company_cities = CompanyCity::with(['country','city','menu_city' => function($query) use($request) {
            $query->where('menu_id','=',$request->city_id);
        }])->where('company_id', Auth::user()->company_id);

        if($request->has('search_text') && $request->search_text != null) {
            $company_cities = $company_cities->Search($request->search_text);
        }
        $cities = $company_cities->paginate(500);

        foreach($cities as $key=>$value){

           $cities[$key]['city_price']=0;
           
           if(in_array($value->city_id,array_column($serviceprice,'city_id'))){
            
             $cities[$key]['city_price']=1;
           } 
        }


        return Helper::getResponse(['data' => $cities]);
    }


}