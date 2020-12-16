<?php

namespace App\Http\Controllers\V1\Common\Provider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Common\Setting;
use App\Models\Common\Provider;
use App\Models\Common\CountryBankForm;
use App\Models\Common\ProviderCard;
use App\Models\Common\ProviderVehicle;
use App\Models\Common\ProviderService;
use App\Models\Common\ProviderWallet;
use App\Models\Common\RequestFilter;
use App\Models\Common\AdminService;
use App\Models\Common\ProviderBankdetail;
use App\Models\Common\ProviderDocument;
use App\Models\Common\Document;
use App\Models\Service\ServiceRequest;
use App\Services\SendPushNotification; 
use App\Models\Common\CompanyCountry;
use App\Models\Common\Notifications;
use Illuminate\Support\Facades\Hash;
use App\Models\Common\CompanyCity;
use App\Models\Common\State;
use App\Models\Common\UserRequest;
use App\Models\Service\Service;
use App\Models\Common\Reason;
use App\Models\Common\Admin;
use App\Models\Order\StoreOrder;
use App\Models\Order\StoreOrderDispute;
use App\Services\ReferralResource;
use App\Models\Common\Chat;
use App\Helpers\Helper;
use Carbon\Carbon;
use Auth;
use App\Traits\Encryptable;
use Illuminate\Validation\Rule;
use DB;

class HomeController extends Controller
{
	use Encryptable;

	public function index(Request $request)
	{
		try{

			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('provider')->user()->company_id)->first()->settings_data));
			$siteConfig = $settings->site;            
			$Provider = Auth::guard('provider')->user();
			$provider = $Provider->id;
			$transport = AdminService::where('admin_service_name','TRANSPORT')->where('company_id', Auth::guard('provider')->user()->company_id)->first();
			$service = AdminService::where('admin_service_name','SERVICE')->where('company_id', Auth::guard('provider')->user()->company_id)->first();
			$order = AdminService::where('admin_service_name','ORDER')->where('company_id', Auth::guard('provider')->user()->company_id)->first();
			$adminService = [];
			if($transport != null) {
				$adminService[] = $transport->id;
				$admin_service_name=$transport->admin_service_name;
			}
			if($service != null) {
				$adminService[] = $service->id;
				$admin_service_name=$service->admin_service_name;
			}
			if($order != null) {
				$adminService[] = $order->id;
				$admin_service_name=$order->admin_service_name;
			}    
			$IncomingRequests = RequestFilter::with(['request.user', 'request.service', 'request.service', 'request'])
			->whereHas('request', function($query) {
				$query->where('status','<>', 'CANCELLED');
				$query->where('status','<>', 'SCHEDULED');
			})
			->where('provider_id', $provider)
			->whereIn('admin_service_id', $adminService)
			->where('assigned', '0')
			->get();

			if(!empty($request->latitude)) {
				$Provider->update([
						'latitude' => $request->latitude,
						'longitude' => $request->longitude,
				]);

				//when the provider is idle for a long time in the mobile app, it will change its status to hold. If it is waked up while new incoming request, here the status will change to active
				//DB::table('provider_services')->where('provider_id',$Provider->id)->where('status','hold')->update(['status' =>'active']);
			}
			$is_otp = 0;
			$config ='';

			if(!empty($IncomingRequests)){

				for ($i=0; $i < sizeof($IncomingRequests); $i++) {

					$admin_service = AdminService::where('id',$IncomingRequests[$i]->admin_service_id)->where('company_id', Auth::guard('provider')->user()->company_id)->first();

					$admin_service_name=$admin_service->admin_service_name;

					if($IncomingRequests[$i]->admin_service_id == $transport->id){
						$config = $settings->transport;
						$is_otp = $config->ride_otp;
						if($config !='' && $config->manual_request == 0){
							$Timeout = $config->provider_select_timeout;
								$IncomingRequests[$i]->request->time_left_to_respond = $Timeout - (time() - strtotime($IncomingRequests[$i]->request->request->assigned_at));
							
							if($IncomingRequests[$i]->request->status == 'SEARCHING' && $IncomingRequests[$i]->request->time_left_to_respond < 0) {
								if($config->broadcast_request == 1){
									$this->assign_destroy($IncomingRequests[$i]->request->request->id, $admin_service);
								}else{
									$this->assign_next_provider($IncomingRequests[$i]->request->request->id, $admin_service);
								}
							}
						}
					}elseif($IncomingRequests[$i]->admin_service_id == $service->id){
						$serviceRequestid = $IncomingRequests[$i]->request->request_id;
						$serveRequest = ServiceRequest::select('id','service_id')->where('id',$serviceRequestid)->first();
						if($serveRequest != null){
							$IncomingRequests[$i]->request->service_details = Service::where('id',$serveRequest->service_id)->first();
						}else{
							$IncomingRequests[$i]->request->service_details = null;
						}
						$config = $settings->service;
						$is_otp = $config->serve_otp;
						if($config !='' && $config->manual_request == 0){
							$Timeout = $config->provider_select_timeout;
								$IncomingRequests[$i]->request->time_left_to_respond = $Timeout - (time() - strtotime($IncomingRequests[$i]->request->request->assigned_at));
							if($IncomingRequests[$i]->request->status == 'SEARCHING' && $IncomingRequests[$i]->request->time_left_to_respond < 0) {
								if($config->broadcast_request == 1){
									$this->assign_destroy($IncomingRequests[$i]->request->request->id, $admin_service);
								}else{
									$this->assign_next_provider($IncomingRequests[$i]->request->request->id, $admin_service);
								}
							}
						}
					}elseif($IncomingRequests[$i]->admin_service_id == $order->id){
						$serviceRequestid = $IncomingRequests[$i]->request_id;
						$IncomingRequests[$i]->request->store_orders = $serveRequest = StoreOrder::select('id','store_id','delivery_address','pickup_address')
						->with(['storesDetails' => function($query){  $query->select('id', 'store_name','store_location' ); }
						])->where('id',$serviceRequestid)->first();
						$config = $settings->order;
						$is_otp = $config->serve_otp;
						if($config !='' && $config->manual_request == 0){
							$Timeout = $config->provider_select_timeout;
							$IncomingRequests[$i]->request->time_left_to_respond = $Timeout - (time() - strtotime($IncomingRequests[$i]->request->request->assigned_at));
							if($IncomingRequests[$i]->request->status == 'SEARCHING' && $IncomingRequests[$i]->request->time_left_to_respond < 0) {
								if($config->broadcast_request == 1){
									$this->assign_destroy($IncomingRequests[$i]->request->request->id, $admin_service);
								}else{
									$this->assign_next_provider($IncomingRequests[$i]->request->request->id, $admin_service);
								}
							}
						}
					}else{

					}
					
				}
			}

			$Reason=Reason::where('type','PROVIDER')->get();

			$referral_total_count = (new ReferralResource)->get_referral('provider', Auth::guard('provider')->user()->id)[0]->total_count;
			$referral_total_amount = (new ReferralResource)->get_referral('provider', Auth::guard('provider')->user()->id)[0]->total_amount;

			$Response = [ 
					'account_status' => $Provider->status,
					'service_status' => (count($IncomingRequests) > 0) ? $admin_service_name : 'ACTIVE',
					'requests' => (count($IncomingRequests) > 0) ? [$IncomingRequests[0]->request]: [],
					'provider_details' => $Provider,
					'reasons' => $Reason,
					'referral_count' => $siteConfig->referral_count,
					'referral_amount' => $siteConfig->referral_amount,
					'ride_otp' => $is_otp,
					'referral_total_count' => $referral_total_count,
					'referral_total_amount' => $referral_total_amount,
				];

			if(count($IncomingRequests) > 0){
				if(!empty($request->latitude) && !empty($request->longitude)) {
					//$this->calculate_distance($request,$IncomingRequests[0]->request_id);
				}    
			}
			return Helper::getResponse(['data' => $Response ]);
		} catch (ModelNotFoundException $e) {
			return Helper::getResponse(['status' => 500, 'error' => $e->getMessage()]);
		}
	}

	public function accept_request(Request $request) 
	{
		$this->validate($request, [
			  'service_id' => 'required|numeric|exists:common.admin_services,id',
		   ]);

		try {

			$provider = Provider::find( Auth::guard('provider')->user()->id );

			$provider_vehicle = null;

			$setting = Setting::where('company_id', $provider->company_id)->first();

			$settings = json_decode(json_encode($setting->settings_data));

			$siteConfig = $settings->site;
			
			$admin_service = AdminService::where('id', $request->service_id)->where('company_id', $provider->company_id)->first();

			try {
				if($admin_service != null && $admin_service->admin_service_name == "TRANSPORT" ) {

					$this->validate($request, [
					  'id' => 'required|numeric|exists:transport.ride_requests,id',
					]);

					$newRequest = \App\Models\Transport\RideRequest::with('user')->find($request->id);

					$provider_vehicle = ProviderVehicle::where('provider_id', $provider->id)->where('vehicle_service_id', $newRequest->ride_delivery_id)->first();

					if($provider->admin_id != null) {
						$newRequest->admin_id = $provider->admin_id;
					}

					if($newRequest->status != "SEARCHING") {
						return Helper::getResponse(['status' => 422, 'message' => trans('api.ride.request_inprogress') ]);
					}
				}
			} catch(\Throwable $e) { }  

			try {
				if($admin_service != null && $admin_service->admin_service_name == "SERVICE" ) {

					$this->validate($request, [
					  'id' => 'required|numeric|exists:service.service_requests,id',
					]);

					$newRequest = \App\Models\Service\ServiceRequest::with('user')->find($request->id);
					if($newRequest->status != "SEARCHING") {
						return Helper::getResponse(['status' => 422, 'message' => trans('api.service.request_inprogress') ]);
					}
				}
			} catch(\Throwable $e) { }
			try {
				if($admin_service != null && $admin_service->admin_service_name == "ORDER" ) {

					$this->validate($request, [
					  'id' => 'required|numeric|exists:order.store_orders,id',
					]);

					try{
						$newRequest = \App\Models\Order\StoreOrder::with('user')->find($request->id);
					}catch(Exception $e){
						return Helper::getResponse(['status' => 500, 'message' => trans('api.order.order_not_found'), 'error' => trans('api.order.order_not_found') ]);
					}
					if($newRequest->status != "SEARCHING") {
						return Helper::getResponse(['status' => 422, 'message' => trans('api.order.request_inprogress') ]);
					}
				}
			} catch(\Throwable $e) {
				return Helper::getResponse(['status' => 500, 'message' => trans('api.order.order_not_found'), 'error' => $e->getMessage() ]);
			}     

			$user_request = UserRequest::where('request_id', $newRequest->id)->where('admin_service_id', $admin_service->id)->first();        
			
			$newRequest->provider_id = $provider->id;
			if($admin_service != null && $admin_service->admin_service_name == "TRANSPORT" ) {
				$newRequest->provider_vehicle_id = $provider_vehicle->id;
			}



			if($newRequest->schedule_at != ""){

				$beforeschedule_time = strtotime($newRequest->schedule_at."- 1 hour");
				$afterschedule_time = strtotime($newRequest->schedule_at."+ 1 hour");


				try {

					if($admin_service != null && $admin_service->admin_service_name == "TRANSPORT" ) {
						$CheckScheduling = \App\Models\Transport\RideRequest::where('status','SCHEDULED')
							->where('provider_id', Auth::guard('provider')->user()->id)
							->whereBetween('schedule_at',[$beforeschedule_time,$afterschedule_time])
							->count();

						if($CheckScheduling > 0 ){
							return Helper::getResponse(['status' => 403, 'message' => trans('api.ride.request_already_scheduled') ]);
						}
					}

				} catch (\Throwable $e) { }

				try {

					if($admin_service != null && $admin_service->admin_service_name == "ORDER" ) {
						$CheckScheduling = \App\Models\Order\StoreOrder::where('status','SCHEDULED')
							->where('provider_id', Auth::guard('provider')->user()->id)
							->whereBetween('schedule_at',[$beforeschedule_time,$afterschedule_time])
							->count();

						if($CheckScheduling > 0 ){
							return Helper::getResponse(['status' => 403, 'message' => trans('api.order.request_already_scheduled') ]);
						}
					}

				} catch (\Throwable $e) { }

				try {

					if($admin_service != null && $admin_service->admin_service_name == "SERVICE" ) {
						$CheckScheduling = \App\Models\Service\ServiceRequest::where('status','SCHEDULED')
							->where('provider_id', Auth::guard('provider')->user()->id)
							->whereBetween('schedule_at',[$beforeschedule_time,$afterschedule_time])
							->count();

						if($CheckScheduling > 0 ){
							return Helper::getResponse(['status' => 403, 'message' => trans('api.service.request_already_scheduled') ]);
						}
					}

				} catch (\Throwable $e) { }

				$newRequest->status = "SCHEDULED";
				$newRequest->save();

				$user_request->provider_id = $newRequest->provider_id;
				$user_request->status = $newRequest->status;
				$user_request->request_data = json_encode($newRequest);

			}else{

				if($admin_service != null && $admin_service->admin_service_name == "SERVICE" ) {
					$newRequest->status = "ACCEPTED";        
					$newRequest->save();    
					//Send message to socket
					$requestData = ['type' => 'SERVICE', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $newRequest->id, 'user' => $newRequest->user_id, 'city' => $newRequest->city_id ];
				
				}else if($admin_service != null && $admin_service->admin_service_name == "ORDER" ) {
					$newRequest->status = "PROCESSING";    
					$newRequest->save();
					//Send message to socket
					$requestData = ['type' => 'ORDER', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $newRequest->id, 'user' => $newRequest->user_id, 'city' => $newRequest->city_id ];
								
				}else{
					$newRequest->status = "STARTED";
					$newRequest->save();    
					//Send message to socket
					$requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $newRequest->id, 'city' => ($setting->demo_mode == 0) ? $newRequest->city_id : 0, 'user' => $newRequest->user_id, 'message' => 'testing' ];
				}                

				$user_request->provider_id = $newRequest->provider_id;
				$user_request->status = $newRequest->status;
				$user_request->request_data = json_encode($newRequest);

				app('redis')->publish('newRequest', json_encode( $requestData ));
				
			}

			$user_request->save();

			$provider->is_assigned = 1;
			$provider->save();

			$Filters = RequestFilter::select('id')->where('admin_service_id', $user_request->admin_service_id)->where('request_id', $user_request->id)->where('provider_id', '!=', Auth::guard('provider')->user()->id)->get();

			$user_request_last = UserRequest::where('status', 'SEARCHING')->where('admin_service_id', $admin_service->id)->orderby('id', 'desc')->first();

			if($user_request_last != null) {
				DB::table('request_filters')->whereIn('id', $Filters)->update(['request_id' => $user_request_last->id]);

				app('redis')->publish('newRequest', json_encode( $requestData ));
			} else {
				foreach ($Filters as $Filter) {
					$Filter->delete();
				}
			}

			// Send Push Notification to User
			(new SendPushNotification)->RideAccepted($newRequest, strtolower($admin_service->admin_service_name));
			
			return Helper::getResponse(['data' => $newRequest  ]);    
		} catch (ModelNotFoundException $e) {
			return Helper::getResponse(['status' => 500, 'message' => $e->getMessage() ]);
		} catch (Exception $e) {
			return Helper::getResponse(['status' => 500, 'message' => $e->getMessage() ]);
		}
	}

	public function cancel_request(Request $request) {

		$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('provider')->user()->company_id)->first()->settings_data));

		$siteConfig = $settings->site;

		$userRequest = UserRequest::where('admin_service_id' , $request->service_id)->where('request_id', $request->id)->first();

		if($userRequest == null) {
			return Helper::getResponse(['message' => trans('api.ride.request_rejected') ]);
		}

		$request_filter = RequestFilter::select('id')->where('request_id', $userRequest->id)->where('admin_service_id', $request->service_id)->where('provider_id', Auth::guard('provider')->user()->id)->first();
		
		try {
			if($request_filter != null) {
				$request_filter->delete();
			} else {
				return Helper::getResponse(['message' => trans('api.ride.request_rejected') ]);
			}
			$admin_service = AdminService::where('id', $request->service_id)->where('company_id', Auth::guard('provider')->user()->company_id)->first();

			try {


				if($admin_service != null && $admin_service->admin_service_name == "TRANSPORT" ) {

					$config = $settings->transport;

				}


				if($admin_service != null && $admin_service->admin_service_name == "SERVICE" ) {

					$config = $settings->service;

					$serviceRequest = ServiceRequest::findOrFail($request->id);
					$cancelreason = isset($request->reason)?$request->reason:'cancelled';
					ServiceRequest::where('id', $serviceRequest->id)->update(['status' => 'CANCELLED','cancelled_by'=>'PROVIDER','cancel_reason'=>$cancelreason]);
					//ProviderService::where('provider_id',$serviceRequest->provider_id)->update(['status' => 'active']);
					Provider::where('id', $serviceRequest->provider_id)->update(['is_assigned' => 0]);
				}

				if($admin_service != null && $admin_service->admin_service_name == "ORDER" ) {

					$config = $settings->order;

					$other_request_filter= RequestFilter::where('request_id' , $userRequest->request_id)->where('admin_service_id' , $request->service_id)->where('provider_id' ,'!=', Auth::guard('provider')->user()->id)->get();
					if(count($other_request_filter) <= 0){
						$OrderDetails = StoreOrder::findOrFail($userRequest->request_id);
						$storedisputedata=StoreOrderDispute::where('store_order_id',$userRequest->request_id)->get();
						if(count($storedisputedata) ==0){
							$storedispute =  new StoreOrderDispute;
							$storedispute->dispute_type='system';
							$storedispute->user_id= $userRequest->user_id;
							$storedispute->store_id= $OrderDetails->store_id;
							$storedispute->store_order_id= $OrderDetails->id;
							$storedispute->dispute_name="Provider Not Available";
							$storedispute->dispute_type_comments="Provider Not Available";
							$storedispute->status="open";
							$storedispute->company_id=$userRequest->company_id;
							$storedispute->save();
						}
					}
				}

				if($config->broadcast_request == 1){
					$this->assign_destroy($request->id, $admin_service);
				}else{
					$this->assign_next_provider($request->id, $admin_service);
				}

			} catch(\Throwable $e) { }  
			return Helper::getResponse(['message' => trans('api.ride.request_rejected') ]);
		} catch(\Throwable $e) {
			return Helper::getResponse(['status' => 500, 'error' => $e->getMessage() ]);
		}    
	}
	
	public function show_profile()
	{
		$provider_details = Provider::with('service','country','state','city')->where('id',Auth::guard('provider')->user()->id)->where('company_id',Auth::guard('provider')->user()->company_id)->first();

		 $provider_details['referral']=(object)array();
   
		$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('provider')->user()->company_id)->first()->settings_data));
		$siteConfig = $settings->site;
		if($provider_details->wallet_balance >= $siteConfig->provider_negative_balance){
			$provider_details['is_walletbalance_min'] = 1;
		}else{
			$provider_details['is_walletbalance_min'] = 0;
		}
		if($settings->site->referral==1){
			 $provider_details['referral']->referral_code=$provider_details['referral_unique_id'];        
			   $provider_details['referral']->referral_amount= number_format($settings->site->referral_amount, 2, '.', '');
			$provider_details['referral']->referral_count=(int)$settings->site->referral_count;
			$provider_details['referral']->user_referral_count = (int)(new ReferralResource)->get_referral(2, Auth::guard('provider')->user()->id)[0]->total_count;
			$provider_details['referral']->user_referral_amount =(new ReferralResource)->get_referral(2, Auth::guard('provider')->user()->id)[0]->total_amount;
		}


		return Helper::getResponse(['data' => $provider_details]);
	}

	public function update_location(Request $request)
	{
		$this->validate($request, [
			'provider_id' => 'required|numeric',
			'latitude' => 'required|numeric',
			'longitude' => 'required|numeric',
	    ]);

		try {
			Provider::where('id', $request->provider_id)->update([
				'latitude' => $request->latitude,
				'longitude' => $request->longitude,
			]);

			return Helper::getResponse(['message' => 'Successfully updated'  ]);

		} catch (\Throwable $e) {
			return Helper::getResponse(['status' => 500, 'message' => $e->getMessage() ]);
		}
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  \App\Provider  $provider
	 * @return \Illuminate\Http\Response
	 */
	public function update_profile(Request $request)
	{
		if($request->has('mobile')) {
			$request->merge([
				'mobile' => $this->cusencrypt($request->mobile,env('DB_SECRET')),
			]);
			$mobile=$request->mobile;
			$company_id=Auth::guard('provider')->user()->company_id;
			$id=Auth::guard('provider')->user()->id;

			$this->validate($request, [          
				'mobile' =>[ Rule::unique('providers')->where(function ($query) use($mobile,$company_id,$id) {
					return $query->where('mobile', $mobile)->where('company_id', $company_id)->whereNotIn('id', [$id]);
				})],
			]);

			$request->merge([
				'mobile' => $this->cusdecrypt($request->mobile,env('DB_SECRET')),
			]); 

		}

		
		try{
			$provider = Provider::where('id',Auth::guard('provider')->user()->id)->where('company_id',Auth::guard('provider')->user()->company_id)->first();

			if($request->has('first_name')) {
				$provider->first_name = $request->first_name;
			}
			if($request->has('last_name')) {
				$provider->last_name = $request->last_name;
			}
			if($request->has('email')) {
				$provider->email = $request->email;
			}
			if($request->has('language')) {
				$provider->language = $request->language;
			}
			if($request->has('gender')) {
				$provider->gender = $request->gender;
			}
			if($request->has('mobile')) {
				$provider->mobile = $request->mobile;
			}
			
			if($request->has('city_id')) {
				$provider->city_id = $request->city_id;
			}
			if($request->has('city_id')) {
				$provider->city_id = $request->city_id;
			} if($request->has('country_code')) {
				$provider->country_code = $request->country_code;
			}
			if($request->has('latitude')) {
				$provider->latitude = $request->latitude;
			}
			if($request->has('longitude')) {
				$provider->longitude = $request->longitude;
			}if($request->has('current_location')) {
				$provider->current_location = $request->current_location;
			}

			if($request->hasFile('picture')) {
				$provider->picture = Helper::upload_file($request->file('picture'), 'provider', null, Auth::guard('provider')->user()->company_id);
			}
			$provider->save();

			return Helper::getResponse(['status' => 200, 'message' => trans('admin.update'), 'data' => $provider]);
		}
		catch (\Throwable $e) {
			return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}
	}
	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  \App\Provider  $provider
	 * @return \Illuminate\Http\Response
	 */
	public function password_update(Request $request)
	{
		$this->validate($request,[
			'old_password' => 'required',
			'password' => 'required|min:6|different:old_password',
			'password_confirmation' => 'required'
		],['password.different'=>'The new password and old password should not be same']);
		 
		try {

			$provider =Provider::where('id',Auth::guard('provider')->user()->id)->where('company_id',Auth::guard('provider')->user()->company_id)->first();
			if(password_verify($request->old_password, $provider->password))
			{   
				$provider->password = Hash::make($request->password);
				$provider->save();
				return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);
			} else {

				return Helper::getResponse(['status' => 422, 'message' => trans('admin.old_password_incorrect')]);
			}
			
		}  catch (\Throwable $e) {
			return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}
	}



	public function addcard(Request $request)
	{
		$this->validate($request,[
			'stripe_token' => 'required', 
		]);

		try{

			$customer_id = $this->customer_id();
			$this->set_stripe();
			$customer = \Stripe\Customer::retrieve($customer_id);
			$card = $customer->sources->create(["source" => $request->stripe_token]);
			

			$user = Auth::guard('provider')->user();

			 $exist = ProviderCard::where('provider_id',$user->id)
						 ->where('last_four',$card['last4'])
						 ->where('brand',$card['brand'])
						 ->count();

			if($exist == 0){

				   $create_card = new ProviderCard;
					$create_card->provider_id = $user->id;
					$create_card->card_id = $card->id;
					$create_card->last_four = $card->last4;
					$create_card->brand = $card->brand;
					$create_card->company_id = $user->company_id;
					$create_card->month = $card->exp_month;
					$create_card->year = $card->exp_year;
					$create_card->holder_name = $card->name;
					$create_card->funding = $card->funding;               
					$create_card->save();
			 }else{
				return Helper::getResponse(['status' => 403, 'message' => trans('api.card_already')]);     
			}

			return Helper::getResponse(['message' => trans('api.card_added')]); 

			} catch(Exception $e){
				return Helper::getResponse(['status' => 500, 'error' => $e->getMessage()]);
			}
	}

	public function provider_services() {

		$services = [];

		try {
			$services["transport"] = \App\Models\Transport\RideDeliveryVehicle::select('id', 'vehicle_name', \DB::raw("'2 mins' AS estimated_time"))->where('company_id', Auth::guard('provider')->user()->company_id)->where('status', 1)->get();
		} catch(\Throwable $e) { }
		
		return Helper::getResponse(['data' => $services ]);
	}


	public function vehicle_list(Request $request) {
		$provider_vehicle = ProviderVehicle::with('vehicle_type','provider_service')->where('provider_id',Auth::guard('provider')->user()->id)->where('company_id',Auth::guard('provider')->user()->company_id)->paginate('10');
		return Helper::getResponse(['data' => $provider_vehicle]);
	}

	public function deleteproviderdocument(Request $request,$id)
	{   
		Provider::where('id',Auth::guard('provider')->user()->id)->update(['is_document' => 0]); 
		ProviderDocument::where('id',$id)->delete();
		return Helper::getResponse(['message' => trans('Provider Document Deleted Successfully')]);
	}

	public function available(Request $request) {

		$this->validate($request,[
			'status' => 'in:ACTIVE,OFFLINE'
		]);

		$provider = Auth::guard('provider')->user();

		$service = ProviderService::where('provider_id', $provider->id )->first();

		if($provider->status == 'APPROVED') {
			$service->status = $request->status;
			$service->save();
		} else {
			return Helper::getResponse(['status' => 403, 'message' => trans('api.provider.not_approved') ]);
		}
		
		return Helper::getResponse(['data' => $service ]);
	}

	public function set_stripe(){

		$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('provider')->user()->company_id)->first()->settings_data));

		$paymentConfig = json_decode( json_encode( $settings->payment ) , true);;

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


		return \Stripe\Stripe::setApiKey( $stripe_secret_key );
	}

	public function customer_id()
	{
		if(Auth::guard('provider')->user()->stripe_cust_id != null){
			return Auth::guard('provider')->user()->stripe_cust_id;
		}else{

			try{ 
				$stripe = $this->set_stripe();
				$customer = \Stripe\Customer::create([
					'email' => Auth::guard('provider')->user()->email,
				]);

		   Provider::where('id',Auth::guard('provider')->user()->id)->update(['stripe_cust_id' => $customer['id']]);
				return $customer['id'];

			} catch(Exception $e){
				return $e;
			}
		}
	}

	public function order_status(Request $request){

		$order_status = UserRequest::where('user_id',Auth::guard('user')->user()->id)
						->whereNotIn('status',['CANCELLED','SCHEDULED'])->get();
		return Helper::getResponse(['data' => $order_status]);
	}

	public function carddetail(Request $request)
	{
		$provider_cards = ProviderCard::where('provider_id',Auth::guard('provider')->user()->id)->where('company_id',Auth::guard('provider')->user()->company_id)->get();
		return Helper::getResponse(['data' => $provider_cards]);    
	}

	public function deleteCard(Request $request,$id)
	{
		$provider_card = ProviderCard::where('id', $id)->first();
		if($provider_card){
			try {
				ProviderCard::where('id',$id)->delete();
				return Helper::getResponse(['message' => trans('api.card_deleted')]);
			} catch (Exception $e) {
				return Helper::getResponse(['status' => 422, 'message' => 'Card Not Found', 'error' => $e->getMessage()]);
			}
		}else{
			return Helper::getResponse(['status' => 422, 'message' => 'Card Not Found']);
		}
	}

	public function providerlist(){
		$provider_list = Provider::where('id',Auth::guard('provider')->user()->id)->where('company_id',Auth::guard('provider')->user()->company_id)->with('country')->first();
		return Helper::getResponse(['data' => $provider_list]);
	}

	public function walletlist(Request $request)
	{
		if($request->has('limit')) {
			$provider_wallet = ProviderWallet::select('id', 'transaction_id','transaction_alias', 'transaction_desc', 'type', 'amount','created_at')->with(['payment_log' => function($query){  $query->select('id','company_id','is_wallet','user_type','payment_mode','user_id','amount','transaction_code'); }])
			->where('company_id',Auth::guard('provider')->user()->company_id)
			->where('provider_id',Auth::guard('provider')->user()->id)->orderBy('id', 'desc');
			$totalRecords = $provider_wallet->count();
			$provider_wallet = $provider_wallet->take($request->limit)->offset($request->offset)->get();
			$response['total_records'] = $totalRecords;
			$response['data'] = $provider_wallet;
			return Helper::getResponse(['data' => $response]);
		} else {
			$provider_wallet = ProviderWallet::select('id','provider_id', 'transaction_id','transaction_alias', 'transaction_desc', 'type', 'amount','created_at')->with(['payment_log' => function($query){  $query->select('id','company_id','is_wallet','user_type','payment_mode','user_id','amount','transaction_code'); },'provider' => function($query){  $query->select('id','currency_symbol'); }])->where('company_id',Auth::guard('provider')->user()->company_id)->where('provider_id',Auth::guard('provider')->user()->id);

			if($request->has('search_text') && $request->search_text != null) {
						$provider_wallet->Search($request->search_text);
			}
			if($request->has('order_by')) {
				$provider_wallet->orderby($request->order_by, $request->order_direction);
			}                    
			$provider_wallet=$provider_wallet->paginate(10);
			return Helper::getResponse(['data' => $provider_wallet]);
		}
	}


	public function countries(Request $request) {

		$country_list = CompanyCountry::with('country.company_city.city_list')->has('companyCountryCities')->where('company_id', base64_decode($request->salt_key) )->where('status', 1)->get();
		$countries = [];
		foreach ($country_list as $country) {
			$object = new \stdClass();
			$object->id = $country->country->id;
			$object->country_name = $country->country->country_name;
			$object->country_code = $country->country->country_code;
			$object->country_phonecode = $country->country->country_phonecode;
			$object->country_currency = $country->country->country_currency;
			$object->country_symbol = $country->country->country_symbol;
			$object->status = $country->country->status;
			$object->timezone = $country->country->timezone;
			foreach ($country->country->company_city as $value) {
				$object->city[] = $value->city_list;
			}
			$countries[] = $object;
		}

		return Helper::getResponse(['data' => $countries]);
	}



	public function assign_destroy($id, $admin_service)
	{

		if($admin_service->admin_service_name == "TRANSPORT") {
			try {
				$newRequest =  \App\Models\Transport\RideRequest::findOrFail($id);
				$newRequest->status = 'CANCELLED';
				$newRequest->save();

				$setting = Setting::where('company_id', $newRequest->company_id)->first();

				$requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $newRequest->id, 'user' => $newRequest->user_id, 'city' => ($setting->demo_mode == 0) ? $newRequest->city_id : 0 ];
				app('redis')->publish('newRequest', json_encode( $requestData ));
				\Log::info($requestData);

			} catch (\Throwable $e) {}

		} else if($admin_service->admin_service_name == "ORDER") {
			try {
				$newRequest =  \App\Models\Order\StoreOrder::findOrFail($id);
				$newRequest->status = 'CANCELLED';
				$newRequest->save();

				$setting = Setting::where('company_id', $newRequest->company_id)->first();

				$requestData = ['type' => 'ORDER', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $newRequest->id, 'user' => $newRequest->user_id, 'city' => ($setting->demo_mode == 0) ? $newRequest->city_id : 0 ];
				app('redis')->publish('newRequest', json_encode( $requestData ));

			} catch (\Throwable $e) {}

		} else if($admin_service->admin_service_name == "SERVICE") {
			try {
				$newRequest =  \App\Models\Service\ServiceRequest::findOrFail($id);
				$newRequest->status = 'CANCELLED';
				$newRequest->save();

				$setting = Setting::where('company_id', $newRequest->company_id)->first();

				$requestData = ['type' => 'SERVICE', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $newRequest->id, 'user' => $newRequest->user_id, 'city' => ($setting->demo_mode == 0) ? $newRequest->city_id : 0 ];
				app('redis')->publish('newRequest', json_encode( $requestData ));

			} catch (\Throwable $e) {}

		}

		if(isset($newRequest)) {
			// No longer need request specific rows from RequestMeta
			$userRequest = UserRequest::where('admin_service_id' , $admin_service->id)->where('request_id', $newRequest->id)->first();
			$request_filter = RequestFilter::where('admin_service_id' , $admin_service->id)->where('request_id' , $userRequest->id)->get();

			$user_request_last = UserRequest::where('status', 'SEARCHING')->where('admin_service_id', $admin_service->id)->orderby('id', 'desc')->first();

			if(count($request_filter) > 0) {
				RequestFilter::where('admin_service_id' , $admin_service->id)->where('request_id' , $userRequest->id)->delete();

				$user_request_last = UserRequest::where('status', 'SEARCHING')->where('admin_service_id', $admin_service->id)->orderby('id', 'desc')->first();

				if($user_request_last != null) {

					$request_data = json_decode($user_request_last->request_data);

					if($admin_service->admin_service_name == "TRANSPORT") {

						$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('provider')->user()->company_id)->first()->settings_data));

				        $siteConfig = $settings->site;

				        $transportConfig = $settings->transport;

						$distance = isset($transportConfig->provider_search_radius) ? $transportConfig->provider_search_radius : 100;

						$Providers = Provider::with(['service' => function ($q) use ($request_data) {
							$q->where('admin_service_id',$request_data->admin_service_id);
							$q->where('ride_delivery_id',$request_data->ride_delivery_id);
						} ])
						->select(DB::Raw("(6371 * acos( cos( radians('$request_data->s_latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$request_data->s_longitude') ) + sin( radians('$request_data->s_latitude') ) * sin( radians(latitude) ) ) ) AS distance"),'id')
						->where('status', 'approved')
						->where('is_online', 1)
						->where('is_assigned', 0)
						->where('company_id', Auth::guard('provider')->user()->company_id)
						->whereRaw("(6371 * acos( cos( radians('$request_data->s_latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$request_data->s_longitude') ) + sin( radians('$request_data->s_latitude') ) * sin( radians(latitude) ) ) ) <= $distance")
						->whereHas('service', function($query) use ($request_data){          
									$query->where('admin_service_id', $request_data->admin_service_id);
									$query->where('ride_delivery_id', $request_data->ride_delivery_id);
								})
						->whereHas('service.vehicle', function($query) use ($request_data){    
								/*if($request_data->child_seat != 0) {
									$query->where('child_seat', $request_data->child_seat);
								}
								if($request_data->wheel_chair != 0) {
									$query->where('wheel_chair',$request_data->wheel_chair);
								}*/
								})
						->orderBy('distance','asc')
						->get();

						if(count($Providers) > 0) {
							foreach ($Providers as $key => $Provider) {

								if($transportConfig->broadcast_request == 1){
		                           (new SendPushNotification)->IncomingRequest($Provider->id, 'transport_incoming_request', 'Taxi Incoming Request'); 
		                        }

		                        $Filter = new RequestFilter;
	                            // Send push notifications to the first provider
	                            // incoming request push to provider
	                            $Filter->admin_service_id = $user_request_last->admin_service_id;
	                            $Filter->request_id = $user_request_last->id;
	                            $Filter->provider_id = $Provider->id;
	                            $Filter->company_id = Auth::guard('provider')->user()->company_id; 
	                            $Filter->save();

							}
						}

					}

					
					app('redis')->publish('newRequest', json_encode( $requestData ));
				}
			}

			if($userRequest != null) {
				$userRequest->delete();
			}

			//  request push to user provider not available
			(new SendPushNotification)->ProviderNotAvailable($newRequest->user_id, strtolower($admin_service->admin_service_name));
		}
		
	}

	public function assign_next_provider($request_id, $admin_service) 
	{

		try {
			$userRequest = UserRequest::where('admin_service_id' , $admin_service->id)->where('request_id', $request_id)->first();
		} catch (ModelNotFoundException $e) {
			// Cancelled between update.
			return false;
		}

		if($admin_service != null && $admin_service->admin_service_name == "TRANSPORT" ) {
			try {
				$newRequest = \App\Models\Transport\RideRequest::with('user')->find($userRequest->request_id);

				$setting = Setting::where('company_id', $newRequest->company_id)->first();

				$requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $newRequest->id, 'user' => $newRequest->user_id, 'city' => ($setting->demo_mode == 0) ? $newRequest->city_id : 0 ];

			} catch(\Throwable $e) { }
		} else if($admin_service != null && $admin_service->admin_service_name == "ORDER" ) {
			try {
				$newRequest = \App\Models\Order\StoreOrder::with('user')->find($userRequest->request_id);

				$requestData = ['type' => 'ORDER', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $newRequest->id, 'user' => $newRequest->user_id, 'city' => ($setting->demo_mode == 0) ? $newRequest->city_id : 0 ];

			} catch(\Throwable $e) { }
		} else if($admin_service != null && $admin_service->admin_service_name == "SERVICE" ) {
			try {
				$newRequest = \App\Models\Service\ServiceRequest::with('user')->find($userRequest->request_id);

				$requestData = ['type' => 'SERVICE', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $newRequest->id, 'user' => $newRequest->user_id, 'city' => ($setting->demo_mode == 0) ? $newRequest->city_id : 0 ];

			} catch(\Throwable $e) { }
		}

		$RequestFilter = RequestFilter::where('admin_service_id' , $admin_service->id)->where('request_id', $userRequest->id)->where('assigned', 0)->orderBy('id')->first();

		if($RequestFilter != null) {
			$RequestFilter->delete();
		}
		
		try {

			$next_provider = RequestFilter::where('admin_service_id' , $admin_service->id)->where('request_id', $userRequest->id)->orderBy('id')->first();
			
			if($next_provider != null) {
				$next_provider->assigned = 0;
				$next_provider->save();
				
				$newRequest->assigned_at = (Carbon::now())->toDateTimeString();
				$newRequest->save();


				$userRequest->request_data = json_encode($newRequest);
				$userRequest->save();

				// incoming request push to provider
				(new SendPushNotification)->IncomingRequest($next_provider->provider_id, strtolower($admin_service->admin_service_name));
			} else {
				$userRequest->delete();

				$newRequest->status = 'CANCELLED';
				$newRequest->save();
			}

			if(isset($requestData)) app('redis')->publish('newRequest', json_encode( $requestData ));
			
		} catch (ModelNotFoundException $e) {

			if($admin_service != null && $admin_service->admin_service_name == "TRANSPORT" ) {
				try {
					\App\Models\Transport\RideRequest::where('id', $newRequest->id)->update(['status' => 'CANCELLED']);
				} catch(\Throwable $e) { }
			} else if($admin_service != null && $admin_service->admin_service_name == "ORDER" ) {
				try {
					\App\Models\Order\StoreOrder::where('id', $newRequest->id)->update(['status' => 'CANCELLED']);
				} catch(\Throwable $e) { }
			} else if($admin_service != null && $admin_service->admin_service_name == "SERVICE" ) {
				try {
					\App\Models\Service\ServiceRequest::where('id', $newRequest->id)->update(['status' => 'CANCELLED']);
				} catch(\Throwable $e) { }
			}

			// No longer need request specific rows from RequestMeta
			$RequestFilter = RequestFilter::where('request_id', $userRequest->id)->orderBy('id')->first();

			if($RequestFilter != null) {
				$RequestFilter->delete();
			}


			if(isset($requestData)) app('redis')->publish('newRequest', json_encode( $requestData ));

			//  request push to user provider not available
			(new SendPushNotification)->ProviderNotAvailable($userRequest->user_id, strtolower($admin_service->admin_service_name));
		}
	}


	public function reasons(Request $request)
	{
		$reason = Reason::where('company_id', Auth::guard('provider')->user()->company_id)
					->where('service', $request->type)
					->where('type', 'PROVIDER')
					->where('status','active')
					->get();

		return Helper::getResponse(['data' => $reason]);

	}   

	public function adminservices(Request $request)
	{
		$adminservices = AdminService::with('providerservices')
		->where('company_id', Auth::guard('provider')->user()->company_id)
		->get();

		return Helper::getResponse(['data' => $adminservices]);

	}  

	public function addvechile(Request $request)
	{

		$transport = AdminService::where('admin_service_name', 'TRANSPORT')->where('company_id', Auth::guard('provider')->user()->id)->first();
		$order = AdminService::where('admin_service_name', 'ORDER')->where('company_id', Auth::guard('provider')->user()->id)->first();
		$service = AdminService::where('admin_service_name', 'SERVICE')->where('company_id', Auth::guard('provider')->user()->id)->first();

		$transport_id = $transport != null ? $transport->id : 1;
		$order_id = $order != null ? $order->id : 2;
		$service_id = $service != null ? $service->id : 3;

		$this->validate($request, [
			 'vehicle_id' => ['required_if:admin_service_id,==,'.$transport_id],
			'vehicle_year' => ['required_if:admin_service_id,==,'.$transport_id],
			'vehicle_make' => ['required_if:admin_service_id,==,'.$transport_id.','.$order_id],
			'vehicle_model' => ['required_if:admin_service_id,==,'.$transport_id],
			'vehicle_no' => ['required_if:admin_service_id,==,'.$transport_id.','.$order_id],
			'admin_service_id' =>'required',
			'category_id' => 'required',
			'picture' => ['required_if:admin_service_id,==,'.$transport_id.','.$order_id],
			'picture1' => ['required_if:admin_service_id,==,'.$transport_id.','.$order_id],
			
			],["picture.required_if"=>'Please Upload RC ',"picture1.required_if"=>'Please Upload Insurance ']);

		try{
			if($request->admin_service_id !=$service_id){
				$providervehicle = new ProviderVehicle;
				$providervehicle->provider_id=Auth::guard('provider')->user()->id;
				if($request->has('vehicle_id')) {
				$providervehicle->vehicle_service_id=$request->vehicle_id;
				}
				$providervehicle->company_id=Auth::guard('provider')->user()->company_id;
				if($request->has('vehicle_year')) {
				$providervehicle->vehicle_year=$request->vehicle_year;
				}
				if($request->has('vehicle_make')) {
				$providervehicle->vehicle_make=$request->vehicle_make;
				}
				if($request->has('vehicle_model')) {
				$providervehicle->vehicle_model=$request->vehicle_model;
				}
				if($request->has('vehicle_color')) {
				$providervehicle->vehicle_color=$request->vehicle_color;
				}
				$providervehicle->vehicle_no=$request->vehicle_no;
				if($request->has('wheel_chair')) {
				$providervehicle->wheel_chair=$request->wheel_chair;
				}else{
				$providervehicle->wheel_chair=0;    
				}
				if($request->has('child_seat')) {
				$providervehicle->child_seat=$request->child_seat;
				}else{
				$providervehicle->child_seat=0;    
				}
				if($request->hasFile('vechile_image')) {
					$providervehicle->vechile_image = Helper::upload_file($request->file('vechile_image'), 'provider', null, Auth::guard('provider')->user()->company_id);
				}
				if($request->hasFile('picture')) {
					$providervehicle->picture = Helper::upload_file($request->file('picture'), 'provider', null, Auth::guard('provider')->user()->company_id);
				}
				if($request->hasFile('picture1')) {
					$providervehicle->picture1 = Helper::upload_file($request->file('picture1'), 'provider', null, Auth::guard('provider')->user()->company_id);
				}
				$providervehicle->save();

			}

			$providerservice=new  ProviderService;
			$providerservice->provider_id=Auth::guard('provider')->user()->id;
			$providerservice->company_id=Auth::guard('provider')->user()->company_id;
			if($request->admin_service_id != $service_id){
				$providerservice->provider_vehicle_id=$providervehicle->id;
			}
			$providerservice->admin_service_id=$request->admin_service_id;
			$providerservice->category_id=$request->category_id;
			if($request->admin_service_id==$transport_id){
				$providerservice->ride_delivery_id=$request->vehicle_id;
			} 
			else if($request->admin_service_id==$service_id){
				foreach($request->service  as $key =>$val){
					$providerservice=new  ProviderService;    
					$providerservice->provider_id=Auth::guard('provider')->user()->id;
					$providerservice->company_id=Auth::guard('provider')->user()->company_id;
					$providerservice->admin_service_id=$request->admin_service_id;
					$providerservice->category_id=$request->category_id;
					$providerservice->sub_category_id=$request->sub_category_id;
					$providerservice->service_id=$val['service_id'];
					if(isset($val['base_fare'])){
						$providerservice->base_fare=$val['base_fare'];
					}
					if(isset($val['per_miles'])){
						$providerservice->per_miles=$val['per_miles'];
					}
					if(isset($val['per_mins'])){
						$providerservice->per_mins=$val['per_mins'];
					}
					$providerservice->save();
				}
			}
			if($request->admin_service_id==$transport_id || $request->admin_service_id==$order_id) {
			   $providerservice->save();
			}

			$service=AdminService::findorfail($request->admin_service_id);
			$servicename=$service->admin_service_name;
			$document=Document::whereHas('provider_document')->with('provider_document')->where('type',$servicename)->where('status',1)->get();
			$provider= Provider::findOrFail(Auth::guard('provider')->user()->id);
			$provider->is_service=1;
			if(count($document)==0){
				$provider->is_document=0;
				$provider->status='DOCUMENT';

				(new SendPushNotification)->updateProviderStatus($provider->id, 'provider', trans('admin.providers.status_changed'), 'Account Info', ['service' => $provider->is_service, 'document' => $provider->is_document, 'bank' => $provider->is_bankdetail] );
			}
			$provider->save();

			return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);
		}
		catch (\Throwable $e) {

			return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}
		 
		
	  

	}

  


	public function editvechile(Request $request)
	{

		$transport = AdminService::where('admin_service_name', 'TRANSPORT')->where('company_id', Auth::guard('provider')->user()->id)->first();
		$order = AdminService::where('admin_service_name', 'ORDER')->where('company_id', Auth::guard('provider')->user()->id)->first();
		$service = AdminService::where('admin_service_name', 'SERVICE')->where('company_id', Auth::guard('provider')->user()->id)->first();

		$transport_id = $transport != null ? $transport->id : 1;
		$order_id = $order != null ? $order->id : 2;
		$service_id = $service != null ? $service->id : 3;

		$this->validate($request, [
			'vehicle_id' => ['required_if:admin_service_id,==,'.$transport_id],
			'vehicle_year' => ['required_if:admin_service_id,==,'.$transport_id],
			'vehicle_make' => ['required_if:admin_service_id,==,'.$transport_id.','.$order_id],
			'id'            => ['required_if:admin_service_id,==,'.$transport_id.','.$order_id],
			'vehicle_model' => ['required_if:admin_service_id,==,'.$transport_id],
			'vehicle_no' => ['required_if:admin_service_id,==,'.$transport_id.','.$order_id],
			'admin_service_id' =>'required',
			'category_id'   => 'required',
		]);


		try{
			if($request->admin_service_id !=$service_id){
			    $providervehicle = ProviderVehicle::findOrFail($request->id);
			    $providervehicle->provider_id=Auth::guard('provider')->user()->id;
			    $providervehicle->vehicle_service_id=$request->vehicle_id;
			    $providervehicle->company_id=Auth::guard('provider')->user()->company_id;
			    $providervehicle->vehicle_year=$request->vehicle_year;
			    $providervehicle->vehicle_make=$request->vehicle_make;
			    $providervehicle->vehicle_model=$request->vehicle_model;
			    $providervehicle->vehicle_no=$request->vehicle_no;
			    $providervehicle->vehicle_color=$request->vehicle_color;
			    if($request->has('wheel_chair')) {
			     $providervehicle->wheel_chair=1;
			    }else{
			      $providervehicle->wheel_chair=0;    
			    }
			    if($request->has('child_seat')) {
			      $providervehicle->child_seat=1;
			    }else{
			       $providervehicle->child_seat=0;    
			    }
			    if($request->hasFile('vechile_image')) {
			        $providervehicle->vechile_image = Helper::upload_file($request->file('vechile_image'), 'provider', null, Auth::guard('provider')->user()->company_id);
			    }
			    if($request->hasFile('picture')) {
			        $providervehicle->picture = Helper::upload_file($request->file('picture'), 'provider', null, Auth::guard('provider')->user()->company_id);
			    }
			    if($request->hasFile('picture1')) {
			        $providervehicle->picture1 = Helper::upload_file($request->file('picture1'), 'provider', null, Auth::guard('provider')->user()->company_id);
			    }

			    $providervehicle->save();

			}

			if($request->admin_service_id==$service_id){
				ProviderService::where('admin_service_id',$service_id)->where('sub_category_id',$request->sub_category_id)->where('category_id',$request->category_id)->where('provider_id',Auth::guard('provider')->user()->id)->delete();
				if($request->has("service")){
					foreach($request->service  as $key =>$val){
						$providerservice=new  ProviderService;    
						$providerservice->provider_id=Auth::guard('provider')->user()->id;
						$providerservice->company_id=Auth::guard('provider')->user()->company_id;
						$providerservice->admin_service_id=$request->admin_service_id;
						$providerservice->category_id=$request->category_id;
						$providerservice->sub_category_id=$request->sub_category_id;
						$providerservice->service_id=$val['service_id'];
						if(isset($val['base_fare'])){
						$providerservice->base_fare=$val['base_fare'];
						}
						if(isset($val['per_miles'])){
						$providerservice->per_miles=$val['per_miles'];
						}
						if(isset($val['per_mins'])){
						$providerservice->per_mins=$val['per_mins'];
						}
						$providerservice->save();
					}
				} 

			} 
			else if($request->admin_service_id==$transport_id) {
				ProviderService::where('provider_vehicle_id',$request->id)->update(['ride_delivery_id'=>$request->vehicle_id]);
		   
			}
			return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);
		}
		catch (\Throwable $e) {

			return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}
	}

	public function updatelanguage(Request $request)
	{
	 
	 $this->validate($request, [
			'language' => 'required',
		]);
		try{
		   $provider= Provider::findOrFail(Auth::guard('provider')->user()->id);
		   $provider->language=$request->language;
		   $provider->save();
			return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);
		}
		 catch (\Throwable $e) {
			return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}

	}
	
	public function search_provider(Request $request)
	{

		$results=array();
		$term =  $request->input('stext');  
		$queries = Provider::where(function ($query) use($term) {
			$query->where('first_name', 'LIKE', $term.'%')
				->orWhere('last_name', 'LIKE', $term.'%');
			})->take(5)->get();
		foreach ($queries as $query)
		{
			$results[]=$query;
		}
		return response()->json(array('success' => true, 'data'=>$results));

	}

	public function notification(Request $request)
	{
		try{
			$timezone=(Auth::guard('provider')->user()->state_id) ? State::find(Auth::guard('provider')->user()->state_id)->timezone : '';
			$jsonResponse = [];
			if($request->has('limit')) {
						$notifications = Notifications::where('company_id', Auth::guard('provider')->user()->company_id)->where('notify_type','!=', "user")->where('status','active')->whereDate('expiry_date','>=',Carbon::today())->take($request->limit)->offset($request->offset)->get();
			}else{
					$notifications = Notifications::where('company_id', Auth::guard('provider')->user()->company_id)->where('notify_type','!=', "user")->where('status','active')->whereDate('expiry_date','>=',Carbon::today())->paginate(10);  
			}
			if(count($notifications) > 0){
				foreach($notifications as $k=>$val){
	              $notifications[$k]['created_at']=(\Carbon\Carbon::createFromFormat('Y-m-d H:i:s',$val['created_at'], 'UTC'))->setTimezone($timezone)->format('Y-m-d H:i:s');    
	            } 
           } 
			   $jsonResponse['total_records'] = count($notifications);
			$jsonResponse['notification'] = $notifications;
		}catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')]);
		}
		return Helper::getResponse(['data' => $jsonResponse]);
	}

	public function template(Request $request)
	{
		try{
			 $guard=Helper::getGuard();
			 if($guard == 'SHOP'){
				 $country_id=Auth::guard('shop')->user()->country_id;
				 $company_id=Auth::guard('shop')->user()->company_id;
				 $id=Auth::guard('shop')->user()->id;
				 $type="SHOP";
			 }else if($guard == 'PROVIDER'){
				$type="PROVIDER";
				$country_id=Auth::guard('provider')->user()->country_id;
				$company_id=Auth::guard('provider')->user()->company_id;
				$id=Auth::guard('provider')->user()->id;
			 } else {
				 $type="FLEET";
				 $country_id=Auth::guard('admin')->user()->country_id;
				 $company_id=Auth::guard('admin')->user()->company_id;
				 $id=Auth::guard('admin')->user()->id;

			 }


			 $countryform = CountryBankForm::with(['bankdetails' => function($query) use($id,$type) {
				 $query->where('type_id',$id);
				 $query->where('type',$type); 
			}])->where('country_id', $country_id)->where('company_id',$company_id)->get();

			return Helper::getResponse(['data' => $countryform]);
		}
		  catch (\Throwable $e) {
			return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}
	}

	public function addbankdetails(Request $request)
	{

	  for ($i = 0; $i < count($request->bankform_id); $i++) {
		 $this->validate($request,
		 [
		  'bankform_id.'.$i => 'required',
		  'keyvalue.'.$i => 'required',
		], 
		[
		'keyvalue.'.$i.'.'.'required' => 'Please fill All Details', 
		]);
	   }
	   
	  try{
			
			$guard=Helper::getGuard();
			 if($guard == 'SHOP'){
				 $company_id=Auth::guard('shop')->user()->company_id;
				 $id=Auth::guard('shop')->user()->id;
				 $type="SHOP";
			 }else if($guard == 'PROVIDER'){
				$type="PROVIDER";
			   $company_id=Auth::guard('provider')->user()->company_id;
				$id=Auth::guard('provider')->user()->id;
			 } else {
				 $type="FLEET";
				 $company_id=Auth::guard('admin')->user()->company_id;
				 $id=Auth::guard('admin')->user()->id;

			 }

		   
			for ($i=0; $i < count($request->bankform_id) ; $i++) { 
				$providerbank= new ProviderBankdetail;
				$providerbank->bankform_id=$request->bankform_id[$i];
				$providerbank->keyvalue=$request->keyvalue[$i];
				$providerbank->type_id=$id;
				$providerbank->company_id=$company_id;
				$providerbank->type=$type;
				$providerbank->save();

			}
			 
			if($type=="PROVIDER"){
				$provider= Provider::findOrFail($id);
				$provider->is_bankdetail=1;
				$provider->save();

				(new SendPushNotification)->updateProviderStatus($provider->id, 'provider', trans('admin.document_msgs.document_saved'), 'Account Info', ['service' => $provider->is_service, 'document' => $provider->is_document, 'bank' => $provider->is_bankdetail] );

			}else if($type=="FLEET"){
				$admin= Admin::findOrFail($id);
				$admin->is_bankdetail=1;
				$admin->save();    

			}
			else if($type=="SHOP") {
			 try{     
				$store= \App\Models\Order\Store::findOrFail($id);
				$store->is_bankdetail=1;
				$store->save();
			 }    
			 catch (\Throwable $e) {
				return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
			 }    

			}
			
			return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);

		}
		 catch (\Throwable $e) {
			return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}
	
	}


	public function editbankdetails(Request $request)
	{

		for ($i = 0; $i < count($request->bankform_id); $i++) {
			$this->validate($request,
			[
				'bankform_id.'.$i => 'required',
				'keyvalue.'.$i => 'required',
				'id.'.$i => 'required',
			], 
			[
				'keyvalue.'.$i.'.'.'required' => 'Please fill All Details', 
			]);
		}     
	  
		try{
			for ($i=0; $i < count($request->bankform_id) ; $i++) { 
				$providerbank= ProviderBankdetail::findOrFail($request->id[$i]);
				$providerbank->bankform_id=$request->bankform_id[$i];
				$providerbank->keyvalue=$request->keyvalue[$i];
				$providerbank->save();
			}
			
		 return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);
		}
		catch (\Throwable $e) {
				return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}
	}

	public function onlinestatus(Request $request,$id)
	{
	   try{
		   if($id > 2  ){
		   return Helper::getResponse(['status' => 422, 'message' => 'Send Valid Status']);
			  }
		   $provider= Provider::findOrFail(Auth::guard('provider')->user()->id);
		   if($provider['status']=='APPROVED'){
			$provider->is_online=$id;
			$provider->save();
			return Helper::getResponse(['status' => 200,"data"=>['provider_status'=>$provider->is_online], 'message' => trans('admin.update')]);
		   }else{
			   return Helper::getResponse(['status' => 422, 'message' => 'Status Not Updated Contact Admin']);
		   }

		}
		 catch (\Throwable $e) {
			return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}
	
	}
	//For friend refer status

	public function referemail(Request $request)
	{  
	  try{
			return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);

		}
		 catch (\Throwable $e) {
			return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}
	}

	public function updatelocation(Request $request)
	{  
		$this->validate($request, [
			'latitude' => 'required',
			'longitude' => 'required',
		]);

		try{
			  if(Auth::guard('provider')->user()->is_assigned==1){
			  return Helper::getResponse(['status' => 404, 'message' =>'Provider in Ride ']);    
			  }else{
				  RequestFilter::where("provider_id",Auth::guard('provider')->user()->id)->delete();
			  }
		   Provider::where('id',Auth::guard('provider')->user()->id)->update([
						'latitude' => $request->latitude,
						'longitude' => $request->longitude,
				]);
			
			return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);

		}
		 catch (\Throwable $e) {
			return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}
	}

	public function totalEarnings(Request $request, $id)
	{
		try{
			$Provider = Provider::findOrFail($id);
			$types = array('1'=>'today','2'=>'week','3'=>'month');
			foreach($types as $key=>$type){
				$RideRequest = \App\Models\Transport\RideRequestPayment::select(DB::Raw('SUM(provider_pay) as salary'))->where('provider_id',$Provider->id);
				$ServiceRequest = \App\Models\Service\ServiceRequestPayment::select(DB::Raw('SUM(provider_pay) as salary'))->where('provider_id',$Provider->id);
				$OrderRequest = StoreOrder::with(['invoice' => function($query){  
									$query->select('id','store_order_id',DB::Raw('SUM(delivery_amount) as salary'),'cart_details','updated_at' ); }
								])->select('id','delivery_address','pickup_address')->where('provider_id',$Provider->id);
				if($type == 'week'){                
					$RideRequest =     $RideRequest->where('updated_at', '>', Carbon::now()->startOfWeek())
									->where('updated_at', '<', Carbon::now()->endOfWeek())->first();
					$ServiceRequest = $ServiceRequest->where('updated_at', '>', Carbon::now()->startOfWeek())
									->where('updated_at', '<', Carbon::now()->endOfWeek())->first();
					$OrderRequest = $OrderRequest->where('updated_at', '>', Carbon::now()->startOfWeek())
									->where('updated_at', '<', Carbon::now()->endOfWeek())->first();
				}else if($type == 'month'){
					$RideRequest =     $RideRequest->whereMonth('updated_at', Carbon::now()->month)->first();
					$ServiceRequest = $ServiceRequest->whereMonth('updated_at', Carbon::now()->month)->first();
					$OrderRequest = $OrderRequest->whereMonth('updated_at', Carbon::now()->month)->first();
				}else{
					$RideRequest =     $RideRequest->whereRaw('Date(updated_at) = CURDATE()')->first();
					$ServiceRequest = $ServiceRequest->whereRaw('Date(updated_at) = CURDATE()')->first();
					$OrderRequest = $OrderRequest->whereRaw('Date(updated_at) = CURDATE()')->first();
				}
				$earnings =0;
				if($RideRequest != null){
					$earnings += $RideRequest->salary;
				}
				if($ServiceRequest != null){
					$earnings += $ServiceRequest->salary;
				}
				if($OrderRequest != null){
					$earnings += $OrderRequest->invoice->salary;
				}
				$response[$type] = Helper::currencyFormat($earnings);                
			}
			return Helper::getResponse([ 'message' =>'Provider Earnings','data' => $response]);
		}catch(ModelNotFoundException $e){
			return Helper::getResponse(['status' => 500, 'message' => trans('api.provider.provider_not_found'), 'error' => trans('api.provider.provider_not_found') ]);
		} catch (Exception $e) {
			return Helper::getResponse(['status' => 500, 'message' => trans('api.provider.provider_not_found'), 'error' => trans('api.provider.provider_not_found') ]);
		}
	}

  
	public function clear(Request $request)
	{  
		$provider = Provider::find($request->provider_id);

		if($provider != null) {
			$provider->is_assigned = 0;
			$provider->is_online = 1;
			$provider->status = 'APPROVED';
			$provider->activation_status = 1;
			$provider->save();
		}

		$userRequests = UserRequest::where('provider_id', $provider->id)->get();

		foreach ($userRequests as $userRequest) {
			$userRequest->delete();

			$admin_service = AdminService::where('id', $userRequest->admin_service_id )->where('company_id', Auth::guard('provider')->user()->company_id)->first()->admin_service_name;

			if($admin_service == "TRANSPORT" ) {
				$newRequests = \App\Models\Transport\RideRequest::where('provider_id', $provider->id)->whereNotIn('status', ['SCHEDULED', 'COMPLETED', 'CANCELLED'])->get();
				foreach ($newRequests as $newRequest) {
					$newRequest->delete();
				}
			}

		}

		return response()->json([
			'data' => $provider, 
		]);
	}

	public function get_chat(Request $request) {

		$this->validate($request,[
			'admin_service' => 'required|in:TRANSPORT,ORDER,SERVICE', 
			'id' => 'required', 
		]);

		$admin_service = AdminService::where('admin_service_name', $request->admin_service)->where('company_id', Auth::guard('provider')->user()->company_id)->first();

		$chat=Chat::where('admin_service_id', $admin_service->id)->where('request_id', $request->id)->where('company_id', Auth::guard('provider')->user()->company_id)->get();

		return Helper::getResponse(['data' => $chat]);
	}

	public function updateDeviceToken(Request $request){
		$this->validate($request,[
			'device_token' => 'required'
		]);
		$provider = Provider::find( Auth::guard('provider')->user()->id );
		try{
			$company_id = Auth::guard('provider')->user()->company_id;
			$user_id = Auth::guard('provider')->user()->id;
			$update = Provider::where('id',$user_id)->update(['device_token'=>$request->device_token]);
			if($update){
				return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);
			}else{
				return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
			}
		}catch (ModelNotFoundException $e) {
			return Helper::getResponse(['status' => 500,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
		}
	}


    public function addproviderservice(Request $request){    

        $this->validate($request, [

            'admin_service_id' =>'required',

            'category_id' => 'required',

            'sub_category_id' => 'required',

            'service_id' => 'required',

            ],['service_id.required'=>'Please On Atleast One Service']);

            try{

                foreach($request->service_id  as $key =>$val){

                    $providerservice=new  ProviderService;    

                    $providerservice->provider_id=Auth::guard('provider')->user()->id;

                    $providerservice->company_id=Auth::guard('provider')->user()->company_id;

                    $providerservice->admin_service_id=$request->admin_service_id;

                    $providerservice->category_id=$request->category_id[$key];

                    $providerservice->service_id=$val;

                    if(isset($request->base_fare[$key])){

                        $providerservice->base_fare=$request->base_fare[$key];

                    }
                    if(isset($request->per_miles[$key])){

                        $providerservice->per_miles= isset($request->per_miles[$key]) ? $request->per_miles[$key] : 0.00; 
                    }   
                    if(isset($request->per_mins[$key])){
                        $providerservice->per_mins=$request->per_mins[$key];

                    }

                    $providerservice->sub_category_id=$request->sub_category_id[$key];

                    $providerservice->save();

                }

               

                $service=AdminService::findorfail($request->admin_service_id);

                $servicename=$service->admin_service_name;

                $document=Document::whereHas(['provider_document' => function($query){

                $query->where('provider_id',Auth::guard('provider')->user()->id);

                }])->where('type',$servicename)->where('status',1)->get();

                $provider= Provider::findOrFail(Auth::guard('provider')->user()->id);

                $provider->is_service=1;

                if(count($document)==0){

                    $provider->is_document=0;

                    $provider->status='DOCUMENT';

                }

                $provider->save();

                return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);

            }

            catch (\Throwable $e) {



                return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);

            }

    }





   public function editproviderservice(Request $request)

   {    

        $this->validate($request, [

             'admin_service_id' =>'required',

            'category_id' => 'required',

            'sub_category_id' => 'required',

            'service_id' => 'required',

            ],['service_id.required'=>'Please On Atleast One Service']);



        try{ 

            $service = AdminService::where('admin_service_name', 'SERVICE')->where('company_id', Auth::guard('provider')->user()->id)->first();

            $service_id = $service != null ? $service->id : 3;



        ProviderService::where('admin_service_id',$service_id)->where('provider_id',Auth::guard('provider')->user()->id)->delete();    



            foreach($request->service_id  as $key =>$val){

                $providerservice=new  ProviderService;    

                $providerservice->provider_id=Auth::guard('provider')->user()->id;

                $providerservice->company_id=Auth::guard('provider')->user()->company_id;

                $providerservice->admin_service_id=$request->admin_service_id;

                $providerservice->category_id=$request->category_id[$key];

                $providerservice->service_id=$val;

                if(isset($request->base_fare[$key])){

                    $providerservice->base_fare=$request->base_fare[$key];

                } 
                if(isset($request->per_miles[$key])){

                    $providerservice->per_miles= isset($request->per_miles[$key]) ? $request->per_miles[$key] : 0.00;    
                }
                if(isset($request->per_mins[$key])){

                    $providerservice->per_mins=$request->per_mins[$key];
                }

                $providerservice->sub_category_id=$request->sub_category_id[$key];

                $providerservice->save();

            }

           

            $provider= Provider::findOrFail(Auth::guard('provider')->user()->id);

            $provider->is_service=1;

            $provider->save();

           return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);

        }

        catch (\Throwable $e) {



            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);

        }

   } 

}