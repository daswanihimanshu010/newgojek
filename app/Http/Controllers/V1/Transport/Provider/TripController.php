<?php

namespace App\Http\Controllers\V1\Transport\Provider;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Transport\RideRequestPayment;
use App\Models\Transport\RideCity;
use Illuminate\Support\Facades\Storage;
use App\Models\Transport\RideRequestWaitingTime;
use App\Models\Common\RequestFilter;
use App\Services\SendPushNotification;
use App\Models\Common\ProviderService;
use Illuminate\Support\Facades\Hash;
use App\Services\ReferralResource;
use App\Models\Transport\RideRequest;
use App\Models\Common\Provider;
use Location\Distance\Vincenty;
use Location\Coordinate;
use App\Models\Transport\RidePeakPrice;
use App\Models\Common\Setting;
use App\Services\V1\ServiceTypes;
use App\Models\Common\Reason;
use App\Models\Common\Rating;
use App\Models\Common\UserRequest;
use App\Models\Common\AdminService;
use App\Models\Common\User;
use App\Models\Common\Promocode;
use App\Models\Common\PromocodeUsage;
use App\Models\Common\PeakHour;
use App\Models\Transport\RideRequestDispute;
use App\Models\Transport\RideLostItem;
use App\Traits\Actions;
use App\Helpers\Helper;
use Carbon\Carbon;
use App\Services\Transactions;
use App\Models\Common\Admin;
use App\Models\Common\Chat;
use Auth;
use Log;
use DB;

class TripController extends Controller
{

    public function index(Request $request)
	{
		try{

			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('provider')->user()->company_id)->first()->settings_data));

	        $siteConfig = $settings->site;

	        $transportConfig = $settings->transport;

	        $admin_service_id = AdminService::where('admin_service_name', 'TRANSPORT')->where('company_id', Auth::guard('provider')->user()->company_id)->first();

			$Provider = Provider::with(['service'  => function($query) use($admin_service_id) {  
				$query->where('admin_service_id', $admin_service_id->id ); 
			}])->where('id', Auth::guard('provider')->user()->id)->first();

			$provider = $Provider->id;

			$IncomingRequests = RideRequest::with(['user', 'payment', 'chat'])
				->where('status','<>', 'CANCELLED')
				->where('status','<>', 'SCHEDULED')
				->where('provider_rated', '0')
				->where('provider_id', $provider )->first();
			
			if(!empty($request->latitude)) {
				$Provider->update([
						'latitude' => $request->latitude,
						'longitude' => $request->longitude,
				]);

				//when the provider is idle for a long time in the mobile app, it will change its status to hold. If it is waked up while new incoming request, here the status will change to active
				//DB::table('provider_services')->where('provider_id',$Provider->id)->where('status','hold')->update(['status' =>'active']);
			}

			$Reason=Reason::where('type','PROVIDER')->get();

			$referral_total_count = (new ReferralResource)->get_referral('provider', Auth::guard('provider')->user()->id)[0]->total_count;
			$referral_total_amount = (new ReferralResource)->get_referral('provider', Auth::guard('provider')->user()->id)[0]->total_amount;

			$Response = [
					'sos' => isset($siteConfig->sos_number) ? $siteConfig->sos_number : '911' , 
                	'emergency' => isset($siteConfig->contact_number) ? $siteConfig->contact_number : [['number' => '911']],
					'account_status' => $Provider->status,
					'service_status' => !empty($IncomingRequests) ? 'TRANSPORT':'ACTIVE',
					'request' => $IncomingRequests,
					'provider_details' => $Provider,
					'reasons' => $Reason,
					'waitingStatus' => !empty($IncomingRequests) ? $this->waiting_status($IncomingRequests->id) : 0,
					'waitingTime' => !empty($IncomingRequests) ? (int)$this->total_waiting($IncomingRequests->id) : 0,
					'referral_count' => $siteConfig->referral_count,
					'referral_amount' => $siteConfig->referral_amount,
					'ride_otp' => $transportConfig->ride_otp,
					'referral_total_count' => $referral_total_count,
					'referral_total_amount' => $referral_total_amount,
				];

			if($IncomingRequests != null){
				if(!empty($request->latitude) && !empty($request->longitude)) {
					//$this->calculate_distance($request,$IncomingRequests->id);
				}	
			}

			return Helper::getResponse(['data' => $Response ]);

		} catch (ModelNotFoundException $e) {
			return Helper::getResponse(['status' => 500, 'error' => $e->getMessage()]);
		}
	}

	public function update_ride(Request $request)
	{
		$this->validate($request, [
			  'id' => 'required|numeric|exists:transport.ride_requests,id,provider_id,'.Auth::guard('provider')->user()->id,
			  'status' => 'required|in:ACCEPTED,STARTED,ARRIVED,PICKEDUP,DROPPED,PAYMENT,COMPLETED',
		   ]);
		
		try{
			$setting = Setting::where('company_id', Auth::guard('provider')->user()->company_id)->first();
			$settings = json_decode(json_encode($setting->settings_data));

	        $siteConfig = $settings->site;


	        $transportConfig = $settings->transport;

	        $ride_otp = $transportConfig->ride_otp;

	        $admin_service_id = AdminService::where('admin_service_name', 'TRANSPORT')->where('company_id', Auth::guard('provider')->user()->company_id)->first();

			$rideRequest = RideRequest::with('user')->findOrFail($request->id);

	        /*if($request->status == 'DROPPED' && $request->d_latitude != null && $request->d_longitude != null && ($rideRequest->is_drop_location == 0 || $rideRequest->finished_vehicle_type == "RENTAL")) {
				$rideRequest->d_latitude = $request->d_latitude;
				$rideRequest->d_longitude = $request->d_longitude;
				$rideRequest->d_address = $request->d_address;
				$rideRequest->save();
			}*/

			//Add the Log File for ride
			$user_request = UserRequest::where('request_id', $request->id)->where('admin_service_id', $admin_service_id->id)->first();

			if($request->status == 'DROPPED' && $request->d_latitude != null && $request->d_longitude != null) {

				$rideRequest->d_latitude = $request->d_latitude;
				$rideRequest->d_longitude = $request->d_longitude;
				$rideRequest->d_address = $request->d_address;
				$rideRequest->save();

				$details = "https://maps.googleapis.com/maps/api/directions/json?origin=".$rideRequest->s_latitude.",".$rideRequest->s_longitude."&destination=".$request->d_latitude.",".$request->d_longitude."&mode=driving&key=".$siteConfig->server_key;

				$json = Helper::curl($details);

				$details = json_decode($json, TRUE);

				$route_key = (count($details['routes']) > 0) ? $details['routes'][0]['overview_polyline']['points'] : '';

				$rideRequest->route_key = $route_key;
				
			}


			if($request->status == 'DROPPED' && $rideRequest->payment_mode != 'CASH') {
				$rideRequest->status = 'COMPLETED';
				$rideRequest->paid = 0;

				(new SendPushNotification)->Complete($rideRequest, 'transport');
			} else if ($request->status == 'COMPLETED' && $rideRequest->payment_mode == 'CASH') {
				
				if($rideRequest->status=='COMPLETED'){
					//for off cross clicking on change payment issue on mobile
					return true;
				}
				
				$rideRequest->status = $request->status;
				$rideRequest->paid = 1;                
				
				(new SendPushNotification)->Complete($rideRequest, 'transport');

				//for completed payments
				$RequestPayment = RideRequestPayment::where('ride_request_id', $request->id)->first();
				$RequestPayment->payment_mode = 'CASH';
				$RequestPayment->cash = $RequestPayment->payable;
				$RequestPayment->payable = 0;                
				$RequestPayment->save();               

			} else {
				$rideRequest->status = $request->status;

				if($request->status == 'ARRIVED'){
					(new SendPushNotification)->Arrived($rideRequest, 'transport');
				}
			}

			if($request->status == 'PICKEDUP'){
				if($ride_otp==1){
					if(isset($request->otp) && $rideRequest->request_type != "MANUAL"){
						if($request->otp == $rideRequest->otp){
							$rideRequest->started_at = Carbon::now();
							(new SendPushNotification)->Pickedup($rideRequest, 'transport');
					    }else{
							return Helper::getResponse(['status' => 500, 'message' => trans('api.otp'), 'error' => trans('api.otp') ]);
						}
					}else{
						$rideRequest->started_at = Carbon::now();
						(new SendPushNotification)->Pickedup($rideRequest, 'transport');
					}
				}else{
					$rideRequest->started_at = Carbon::now();
					(new SendPushNotification)->Pickedup($rideRequest, 'transport');
				}
			}

			$rideRequest->save();

			if($request->status == 'DROPPED') {

				$waypoints = [];

				$chat=Chat::where('admin_service_id', $admin_service_id->id)->where('request_id', $rideRequest->id)->where('company_id', Auth::guard('provider')->user()->company_id)->first();

				if($chat != null) {
					$chat->delete();
				}

				if($request->has('distance')) {
					$rideRequest->distance  = ($request->distance / 1000); 
				}

				if($request->has('location_points')) {

					foreach($request->location_points as $locations) {
						$waypoints[] = $locations['lat'].",".$locations['lng'];
					}

					$details = "https://maps.googleapis.com/maps/api/directions/json?origin=".$rideRequest->s_latitude.",".$rideRequest->s_longitude."&destination=".$request->latitude.",".$request->longitude."&waypoints=" . implode($waypoints, '|')."&mode=driving&key=".$siteConfig->server_key;

					$json = Helper::curl($details);

					$details = json_decode($json, TRUE);

					$route_key = (count($details['routes']) > 0) ? $details['routes'][0]['overview_polyline']['points'] : '';

					$rideRequest->route_key = $route_key;
					$rideRequest->location_points = json_encode($request->location_points);
				}
				
				$rideRequest->finished_at = Carbon::now();
				$StartedDate  = date_create($rideRequest->started_at);
				$FinisedDate  = Carbon::now();
				$TimeInterval = date_diff($StartedDate,$FinisedDate);
				$MintuesTime  = $TimeInterval->i;
				$rideRequest->travel_time = $MintuesTime;
				$rideRequest->save();
				$rideRequest->with('user')->findOrFail($request->id);
				$rideRequest->invoice = $this->invoice($request->id, ($request->toll_price != null) ? $request->toll_price : 0);
			   
			    if($rideRequest->invoice) {
			    	(new SendPushNotification)->Dropped($rideRequest, 'transport');
			    }
				

			}

			$user_request->provider_id = $rideRequest->provider_id;
			$user_request->status = $rideRequest->status;
			$user_request->request_data = json_encode($rideRequest);

			$user_request->save();

			//Send message to socket
            $requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $rideRequest->id, 'city' => ($setting->demo_mode == 0) ? $rideRequest->city_id : 0, 'user' => $rideRequest->user_id ];
            app('redis')->publish('newRequest', json_encode( $requestData ));
			
			// Send Push Notification to User
	   
			return Helper::getResponse(['data' => $rideRequest ]);

		} catch (ModelNotFoundException $e) {
			return Helper::getResponse(['status' => 500, 'message' => trans('api.unable_accept'), 'error' => $e->getMessage() ]);
		} catch (Exception $e) {
			return Helper::getResponse(['status' => 500, 'message' => trans('api.connection_err'), 'error' => $e->getMessage() ]);
		}
	}

	public function cancel_ride(Request $request)
	{

		$this->validate($request, [
			  'id' => 'required|numeric|exists:transport.ride_requests,id,provider_id,'.Auth::guard('provider')->user()->id,
			  //'service_id' => 'required|numeric|exists:common.admin_services,id',
			  'reason'=>'required',
		   ]);

		try {
			$setting = Setting::where('company_id', Auth::guard('provider')->user()->company_id)->first();

			$settings = json_decode(json_encode($setting->settings_data));

	        $siteConfig = $settings->site;

	        $transportConfig = $settings->transport; 

			$rideRequest = RideRequest::find($request->id);

	        if($rideRequest->status == 'CANCELLED')
	        {
	            return Helper::getResponse(['status' => 404, 'message' => trans('api.ride.already_cancelled')]);
	        }

			$admin_service = AdminService::where('admin_service_name', 'TRANSPORT')->where('company_id', Auth::guard('provider')->user()->company_id)->first();

			$user_request = UserRequest::where('request_id', $request->id)->where('admin_service_id', $admin_service->id )->first();

			if($user_request == null) {
				return Helper::getResponse(['status' => 404, 'message' => trans('api.ride.already_cancelled')]);
			}

			$rideDelete = RequestFilter::where('admin_service_id' , $admin_service->id)->where('request_id', $user_request->id)->first();

			if($rideDelete == null) {
				return Helper::getResponse(['status' => 404, 'message' => trans('api.ride.already_cancelled')]);
			}

			$rideDelete->delete();

			$user_request->delete();

			if($request->reason != null) {
				$rideRequest->status = 'CANCELLED';
				$rideRequest->cancelled_by = 'PROVIDER';
				$rideRequest->cancel_reason = $request->reason;
				$rideRequest->save();

				//ProviderService::where('provider_id',$rideRequest->provider_id)->update(['status' => 'ACTIVE']);
			}

			$provider = Provider::find(Auth::guard('provider')->user()->id);
			$provider->is_assigned = 0;
			$provider->save();

			//Send message to socket
	        $requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $rideRequest->id, 'city' => ($setting->demo_mode == 0) ? $rideRequest->city_id : 0, 'user' => $rideRequest->user_id ];
	        app('redis')->publish('newRequest', json_encode( $requestData ));
			
			 if($transportConfig->broadcast_request == 1){
			 	return Helper::getResponse(['message' => trans('api.ride.request_rejected') ]);
			 }else{
				 (new \App\Http\Controllers\Common\Provider\HomeController)->assign_next_provider($rideRequest->id);
				 return Helper::getResponse(['data' => $rideRequest->with('user')->get() ]);
			 }
			 
				
		} catch (\Throwable $e) {
		 	return Helper::getResponse(['status' => 500, 'message' => trans('api.connection_err'), 'error' => $e->getMessage() ]);
		}
	}

	public function invoice($request_id, $toll_price = 0)
	{

		try {                      

			$rideRequest = RideRequest::with('provider')->findOrFail($request_id);      
			/*$RideCommission = RideCity::where('city_id',$rideRequest->city_id)->first();
			$tax_percentage = $RideCommission->tax ? $RideCommission->tax : 0;
			$commission_percentage = $RideCommission->comission ? $RideCommission->comission : 0;
			$waiting_percentage = $RideCommission->waiting_percentage ? $RideCommission->waiting_percentage : 0;
			$peak_percentage = $RideCommission->peak_percentage ? $RideCommission->peak_percentage : 0;*/

			$tax_percentage = $commission_percentage = $waiting_percentage = $peak_percentage =0;

			$Fixed = 0;
			$Distance = 0;
			$Discount = 0; // Promo Code discounts should be added here.
			$Wallet = 0;			
			$ProviderPay = 0;
			$Distance_fare =0;
			$Minute_fare =0;
			$calculator ='DISTANCE';
			$discount_per =0;

			//added the common function for calculate the price
			$requestarr['kilometer']=$rideRequest->distance;
			$requestarr['time']=0;
			$requestarr['seconds']=0;
			$requestarr['minutes']=$rideRequest->travel_time;
			$requestarr['ride_delivery_id']=$rideRequest->ride_delivery_id;
			$requestarr['city_id']=$rideRequest->city_id;
			$requestarr['service_type']=$rideRequest->ride_delivery_id;
			
			$response = new ServiceTypes();			
			$pricedata=$response->applyPriceLogic($requestarr,1);
			
			if(!empty($pricedata)){
				$Distance =$pricedata['price'];
				$Fixed = $pricedata['base_price'];
				$Distance_fare = $pricedata['distance_fare'];
				$Minute_fare = $pricedata['minute_fare'];
				$Hour_fare = $pricedata['hour_fare'];
				$calculator = $pricedata['calculator'];
				$RideCityPrice = $pricedata['ride_city_price'];
				$rideRequest->calculator=$pricedata['calculator'];
				$rideRequest->save();

				$tax_percentage = isset($RideCityPrice->tax) ? $RideCityPrice->tax : 0;
				$commission_percentage = isset($RideCityPrice->commission) ? $RideCityPrice->commission : 0;
				$waiting_percentage = isset($RideCityPrice->waiting_commission) ? $RideCityPrice->waiting_commission : 0;
				$peak_percentage = isset($RideCityPrice->peak_commission) ? $RideCityPrice->peak_commission : 0;
			}
			 
			
			$Distance=$Distance;
			$Tax = ($Distance) * ( $tax_percentage/100 );
			

			if($rideRequest->promocode_id>0){
				if($Promocode = Promocode::find($rideRequest->promocode_id)){
					$max_amount = $Promocode->max_amount;
					$discount_per = $Promocode->percentage;

					$discount_amount = (($Distance + $Tax) * ($discount_per/100));

					if($discount_amount>$Promocode->max_amount){
						$Discount = $Promocode->max_amount;
					}
					else{
						$Discount = $discount_amount;
					}

					$PromocodeUsage = new PromocodeUsage;
					$PromocodeUsage->user_id =$rideRequest->user_id;
					$PromocodeUsage->company_id =Auth::guard('provider')->user()->company_id;
					$PromocodeUsage->promocode_id =$rideRequest->promocode_id;
					$PromocodeUsage->status ='USED';
					$PromocodeUsage->save();

					// $Total = $Distance + $Tax;
					// $payable_amount = $Distance + $Tax - $Discount;

				}                
			}
		   
			$Total = $Distance + $Tax;
			$payable_amount = $Distance + $Tax - $Discount;


			if($Total < 0){
				$Total = 0.00; // prevent from negative value
				$payable_amount = 0.00;
			}


			//changed by tamil
			$Commision = ($Total) * ( $commission_percentage/100 );
			$Total += $Commision;
			$payable_amount += $Commision;
			
			$ProviderPay = (($Total+$Discount) - $Commision)-$Tax;

			$Payment = new RideRequestPayment;


			$Payment->company_id = Auth::guard('provider')->user()->company_id;
			$Payment->ride_request_id = $rideRequest->id;

			$Payment->user_id=$rideRequest->user_id;
			$Payment->provider_id=$rideRequest->provider_id;

			if(!empty($rideRequest->admin_id)){
				$Fleet = Admin::where('id',$rideRequest->admin_id)->where('type','FLEET')->where('company_id',Auth::guard('provider')->user()->company_id)->first();

				$fleet_per=0;

				if(!empty($Fleet)){
					if(!empty($Commision)){										
						$fleet_per=$Fleet->commision ? $Fleet->commision : 0;
					}
					else{
						$fleet_per=$RideCityPrice->fleet_commission ? $RideCityPrice->fleet_commission :0;
					}

					$Payment->fleet_id=$rideRequest->provider->admin_id;
					$Payment->fleet_percent=$fleet_per;
				}
			}


			//check peakhours and waiting charges
			$total_waiting_time=$total_waiting_amount=$peakamount=$peak_comm_amount=$waiting_comm_amount=0;

			if($RideCityPrice->waiting_min_charge>0){
				$total_waiting=round($this->total_waiting($rideRequest->id)/60);
				if($total_waiting>0){
					if($total_waiting > $RideCityPrice->waiting_free_mins){
						$total_waiting_time = $total_waiting - $RideCityPrice->waiting_free_mins;
						$total_waiting_amount = $total_waiting_time * $RideCityPrice->waiting_min_charge;
						$waiting_comm_amount = ($waiting_percentage/100) * $total_waiting_amount;

					}
				}
			}

			$start_time = $rideRequest->started_at;
			$end_time = $rideRequest->finished_at;

			$start_time_check = PeakHour::where('start_time', '<=', $start_time)->where('end_time', '>=', $start_time)->where('company_id', '>=', Auth::guard('provider')->user()->company_id)->first();

			if($start_time_check){

				$Peakcharges = RidePeakPrice::where('ride_city_price_id',$rideRequest->city_id)->where('ride_delivery_id',$rideRequest->ride_delivery_id)->where('peak_hour_id',$start_time_check->id)->first();


				if($Peakcharges){
					$peakamount=($Peakcharges->peak_price/100) * $Fixed;
					$peak_comm_amount = ($peak_percentage/100) * $peakamount;
				}

			}
			

			$Total += $peakamount+$total_waiting_amount+$toll_price;
			$payable_amount += $peakamount+$total_waiting_amount+$toll_price;

			$ProviderPay = $ProviderPay + ($peakamount+$total_waiting_amount) + $toll_price;

			$Payment->fixed = $Fixed + $Commision + $peakamount;
			$Payment->distance = $Distance_fare;
			$Payment->minute  = $Minute_fare;
			$Payment->hour  = $Hour_fare;
			$Payment->payment_mode  = $rideRequest->payment_mode;
			$Payment->commision = $Commision;
			$Payment->commision_percent = $commission_percentage;
			$Payment->toll_charge = $toll_price;
			$Payment->total = $Total;
			$Payment->provider_pay = $ProviderPay;
			$Payment->peak_amount = $peakamount;
			$Payment->peak_comm_amount = $peak_comm_amount;
			$Payment->total_waiting_time = $total_waiting_time;
			$Payment->waiting_amount = $total_waiting_amount;
			$Payment->waiting_comm_amount = $waiting_comm_amount;
			if($rideRequest->promocode_id>0){
				$Payment->promocode_id = $rideRequest->promocode_id;
			}
			$Payment->discount = $Discount;
			$Payment->discount_percent = $discount_per;
			$Payment->company_id = Auth::guard('provider')->user()->company_id;


			if($Discount  == ($Distance + $Tax)){
				$rideRequest->paid = 1;
			}

			if($rideRequest->use_wallet == 1 && $payable_amount > 0){

				$User = User::find($rideRequest->user_id);
				$currencySymbol = $rideRequest->currency;
				$Wallet = $User->wallet_balance;

				if($Wallet != 0){

					if($payable_amount > $Wallet) {

						$Payment->wallet = $Wallet;
						$Payment->is_partial=1;
						$Payable = $payable_amount - $Wallet;
						
						$Payment->payable = abs($Payable);

						$wallet_det=$Wallet;                      

					} else {

						$Payment->payable = 0;
						$WalletBalance = $Wallet - $payable_amount;
						
						$Payment->wallet = $payable_amount;
						
						$Payment->payment_id = 'WALLET';
						$Payment->payment_mode = $rideRequest->payment_mode;

						$rideRequest->paid = 1;
						$rideRequest->status = 'COMPLETED';
						$rideRequest->save();

						$wallet_det=$payable_amount;
					   
					}
					
					(new SendPushNotification)->ChargedWalletMoney($rideRequest->user_id,Helper::currencyFormat($wallet_det,$currencySymbol), 'transport');

					//for create the user wallet transaction

					$transaction['amount']=$wallet_det;
					$transaction['id']=$rideRequest->user_id;
					$transaction['transaction_id']=$rideRequest->id;
					$transaction['transaction_alias']=$rideRequest->booking_id;
					$transaction['company_id']=$rideRequest->company_id;
					$transaction['transaction_msg']='transport deduction';

					(new Transactions)->userCreditDebit($transaction,0);

				}

			} else {
				if($rideRequest->payment_mode == 'CASH'){
					$Payment->round_of = round($payable_amount)-abs($payable_amount);
					$Payment->total = $Total;
					$Payment->payable = round($payable_amount);
				}
				else{
					$Payment->total = abs($Total);
					$Payment->payable = abs($payable_amount);	
				}				
			}

			$Payment->tax = $Tax;

			$Payment->tax_percent = $tax_percentage;

			$Payment->save();
			//dd($Payment);

			return $Payment;

		} catch (\Throwable $e) {
			$newRequest = RideRequest::findOrFail($rideRequest->id);
			$newRequest->status = "PICKEDUP";
			$newRequest->save();
			return false;
		}
	}


	public function rate(Request $request)
    {

        $this->validate($request, [
              'id' => 'required|numeric|exists:transport.ride_requests,id,provider_id,'.Auth::guard('provider')->user()->id,
              'rating' => 'required|integer|in:1,2,3,4,5',
              'comment' => 'max:255',
              'admin_service_id' => 'required|integer',
          ],['comment.max'=>'character limit should not exceed 255']);

            try {

            		 

            		 $admin_service = AdminService::where('admin_service_name', 'TRANSPORT')->where('company_id', Auth::guard('provider')->user()->company_id)->first();

                     $rideRequest = RideRequest::where('id', $request->id)
                             ->where('status', 'COMPLETED')
                             ->firstOrFail();

                     $setting = Setting::where('company_id', Auth::guard('provider')->user()->company_id)->first();

                     $ratingRequest = Rating::where('request_id', $rideRequest->id)
					 ->where('admin_service_id', $admin_service->id )->first();

                     if($ratingRequest == null) {
                             Rating::create([
                                             'company_id' => Auth::guard('provider')->user()->company_id,
                                             'admin_service_id' => $request->admin_service_id,
                                             'provider_id' => $rideRequest->provider_id,
                                             'user_id' => $rideRequest->user_id,
                                             'request_id' => $rideRequest->id,
                                             'provider_rating' => $request->rating,
                                             'provider_comment' => $request->comment]);
                     } else {
                             $rideRequest->rating->update([
                                             'provider_rating' => $request->rating,
                                             'provider_comment' => $request->comment,
                                     ]);
                     }

                     $rideRequest->update(['provider_rated' => 1]);

                     // Delete from filter so that it doesn't show up in status checks.
                     $admin_service = AdminService::where('admin_service_name', 'TRANSPORT')->where('company_id', Auth::guard('provider')->user()->company_id)->first();

					 $user_request = UserRequest::where('request_id', $request->id)->where('admin_service_id', $admin_service->id )->first();

					 if($user_request) {
					 	RequestFilter::where('request_id', $user_request->id)->delete();
                     	$user_request->delete();
					 }
                     

                     $provider = Provider::find($rideRequest->provider_id);

                     /*if($provider->wallet_balance <= config('constants.minimum_negative_balance')) {
                             ProviderService::where('provider_id', $provider->id)->update(['status' => 'balance']);
                             Provider::where('id', $provider->id)->update(['status' => 'balance']);*/
                     //} else {
                             //ProviderService::where('provider_id',$provider->id)->update(['status' =>'ACTIVE']);
                     //}

                     $provider->is_assigned = 0;

                     // Send Push Notification to Provider 
                     $average = Rating::where('provider_id', $rideRequest->provider_id)->avg('provider_rating');

                     $provider->rating = $average;

                     $provider->save();

                     //$rideRequest->user->update(['rating' => $average]);
                     (new SendPushNotification)->Rate($rideRequest, 'transport');

                     //Send message to socket
			         $requestData = ['type' => 'TRANSPORT', 'room' => 'room_'.Auth::guard('provider')->user()->company_id, 'id' => $rideRequest->id, 'city' => ($setting->demo_mode == 0) ? $rideRequest->city_id : 0, 'user' => $rideRequest->user_id ];
			         app('redis')->publish('newRequest', json_encode( $requestData ));

                     return Helper::getResponse(['message' => trans('api.ride.request_completed') ]);

             } catch (ModelNotFoundException $e) {
                     return Helper::getResponse(['status' => 500, 'message' => trans('api.ride.request_not_completed'), 'error' =>trans('api.ride.request_not_completed') ]);
             }
     }

	public function waiting(Request $request){

		$this->validate($request, [  
			'id' => 'required|numeric|exists:transport.ride_requests,id,provider_id,'.Auth::guard('provider')->user()->id,             
		]);

		$user_id = RideRequest::find($request->id)->user_id;

		if($request->has('status')) {

			$waiting = RideRequestWaitingTime::where('ride_request_id', $request->id)->whereNull('ended_at')->first();

			if($waiting != null) {
				$waiting->ended_at = Carbon::now();
				$waiting->waiting_mins = (Carbon::parse($waiting->started_at))->diffInSeconds(Carbon::now());
				$waiting->save();
			} else {
				$waiting = new RideRequestWaitingTime();
				$waiting->ride_request_id = $request->id;
				$waiting->started_at = Carbon::now();
				$waiting->save();
			}

			(new SendPushNotification)->ProviderWaiting($user_id, $request->status, 'transport');
		}

		return response()->json(['waitingTime' => (int)$this->total_waiting($request->id), 'waitingStatus' => (int)$this->waiting_status($request->id)]);
	}

	public function total_waiting($id){

		$waiting = RideRequestWaitingTime::where('ride_request_id', $id)->whereNotNull('ended_at')->sum('waiting_mins');

		$uncounted_waiting = RideRequestWaitingTime::where('ride_request_id', $id)->whereNull('ended_at')->first();

		if($uncounted_waiting != null) {
			$waiting += (Carbon::parse($uncounted_waiting->started_at))->diffInSeconds(Carbon::now());
		}

		return $waiting;
	}

	public function waiting_status($id){

		$waiting = RideRequestWaitingTime::where('ride_request_id', $id)->whereNull('ended_at')->first();

		return ($waiting != null) ? 1 : 0;
	}
	/**
	 * Get the trip history of the provider
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function trips(Request $request)
	{
		try{
			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('provider')->user()->company_id)->first()->settings_data));

			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'transport';
			if($request->has('limit')) {

				$RideRequests = RideRequest::select('id', 'booking_id', 'assigned_at', 's_address', 'd_address','provider_id','user_id','timezone','ride_delivery_id', 'status', 'provider_vehicle_id','started_at')
				->with([
				'user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture' ); },
				'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture' ); },
				'provider_vehicle' => function($query){  $query->select('id', 'provider_id', 'vehicle_make', 'vehicle_model', 'vehicle_no' ); },
				'payment' => function($query){  $query->select('ride_request_id','total'); }, 
				'ride' => function($query){  $query->select('id','vehicle_name', 'vehicle_image'); }])
					->where('provider_id', Auth::guard('provider')->user()->id)
					->where('status', 'COMPLETED')
					->orderBy('ride_requests.created_at','desc')
                    ->take($request->limit)->offset($request->offset)->get();

			} else {

				$RideRequests = RideRequest::select('id', 'booking_id', 'assigned_at', 's_address', 'd_address','provider_id','user_id','timezone','ride_delivery_id', 'status', 'provider_vehicle_id','started_at')
				->with([
				'user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','currency_symbol' ); },
				'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture' ); },
				'provider_vehicle' => function($query){  $query->select('id', 'provider_id', 'vehicle_make', 'vehicle_model', 'vehicle_no' ); },
				'payment' => function($query){  $query->select('ride_request_id','total'); }, 
				'ride' => function($query){  $query->select('id','vehicle_name', 'vehicle_image'); }])
					->where('provider_id', Auth::guard('provider')->user()->id)
					->where('status', 'COMPLETED')
					->orderBy('created_at','desc')
					->with('payment','service_type');

				if($request->has('search_text') && $request->search_text != null) {

			            $RideRequests->ProviderhistroySearch($request->search_text);
			        }

			        if($request->has('order_by')) {

			            $RideRequests->orderby($request->order_by, $request->order_direction);
			        }	
					

					$RideRequests=$RideRequests->paginate(10);
			}			
			$jsonResponse['total_records'] = count($RideRequests);
			if(!empty($RideRequests)){
				$map_icon_start = '';
				//asset('asset/img/marker-start.png');
				$map_icon_end = '';
				//asset('asset/img/marker-end.png');
				foreach ($RideRequests as $key => $value) {
					$RideRequests[$key]->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
							"autoscale=1".
							"&size=600x300".
							"&maptype=terrian".
							"&format=png".
							"&visual_refresh=true".
							"&markers=icon:".$map_icon_start."%7C".$value->s_latitude.",".$value->s_longitude.
							"&markers=icon:".$map_icon_end."%7C".$value->d_latitude.",".$value->d_longitude.
							"&path=color:0x000000|weight:3|enc:".$value->route_key.
							"&key=".$siteConfig->server_key;
				}
			}
			$jsonResponse['transport'] = $RideRequests;
			return Helper::getResponse(['data' => $jsonResponse]);
		}
		catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')]);
		}
	}
	/**
	 * Get the trip history of the provider
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function gettripdetails(Request $request,$id)
	{
		try{
			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('provider')->user()->company_id)->first()->settings_data));
			$providerId=Auth::guard('provider')->user()->id;
			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'transport';
			$RideRequests = RideRequest::with(array('payment','ride','user','service_type',
			'rating'=>function($query){
				$query->select('id','request_id','user_comment','provider_comment');
				$query->where('admin_service_id',3);
			}))
					->where('provider_id', $providerId)
					->where('status','!=', 'SCHEDULED')
					->orderBy('created_at','desc')
					->where('id',$id)->first();

			if(!empty($RideRequests)){
				$map_icon_start = '';
				//asset('asset/img/marker-start.png');
				$map_icon_end = '';
				$RideRequests->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
						"autoscale=1".
						"&size=600x300".
						"&maptype=terrian".
						"&format=png".
						"&visual_refresh=true".
						"&markers=icon:".$map_icon_start."%7C".$RideRequests->s_latitude.",".$RideRequests->s_longitude.
						"&markers=icon:".$map_icon_end."%7C".$RideRequests->d_latitude.",".$RideRequests->d_longitude.
						"&path=color:0x000000|weight:3|enc:".$RideRequests->route_key.
						"&key=".$siteConfig->server_key;
				$RideRequests->dispute = RideRequestDispute::where(['provider_id'=>$providerId,'dispute_type'=>'provider','ride_request_id' => $id])->first();
			}
			$jsonResponse['transport'] = $RideRequests;
			return Helper::getResponse(['data' => $jsonResponse]);
		}
		catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')]);
		}
	}
	//Save the dispute details
	public function ride_request_dispute(Request $request) {

		$this->validate($request, [
				'id' => 'required|numeric|exists:transport.ride_requests,id,provider_id,'.Auth::guard('provider')->user()->id, 
				'user_id' => 'required',
				'dispute_name' => 'required',
				'dispute_type' => 'required',
			]);

		$ride_request_dispute = RideRequestDispute::where('company_id',Auth::guard('provider')->user()->company_id)
							    ->where('ride_request_id',$request->id)
								->where('dispute_type','provider')
								->first();
		if($ride_request_dispute==null)
		{
			
			try{
				$ride_request_dispute = new RideRequestDispute;
				$ride_request_dispute->company_id = Auth::guard('provider')->user()->company_id;  
				$ride_request_dispute->ride_request_id = $request->id;
				$ride_request_dispute->dispute_type = $request->dispute_type;
				$ride_request_dispute->user_id = $request->user_id;
				$ride_request_dispute->provider_id = Auth::guard('provider')->user()->id;                
				$ride_request_dispute->dispute_name = $request->dispute_name;
				$ride_request_dispute->dispute_title =  $request->dispute_title; 
				$ride_request_dispute->comments =  $request->comments; 
				$ride_request_dispute->save();
				return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);
			} 
			catch (\Throwable $e) {
				return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
			}
		}else{
			return Helper::getResponse(['status' => 404, 'message' => trans('Already Dispute Created for the Ride Request')]);
		}
	}
	public function get_ride_request_dispute(Request $request,$id) {
		$ride_request_dispute = RideRequestDispute::where('company_id',Auth::guard('provider')->user()->company_id)
							    ->where('ride_request_id',$id)
								->where('dispute_type','provider')
								->first();
		return Helper::getResponse(['data' => $ride_request_dispute]);
	}
	public function getdisputedetails(Request $request)
	{
		$dispute = Dispute::select('id','dispute_name')->get();
        return Helper::getResponse(['data' => $dispute]);
	}
    

	public function callTransaction($request_id){  

		$UserRequest = RideRequest::with('provider')->with('payment')->findOrFail($request_id);

		if($UserRequest->paid==1){
			$transation=array();
			$transation['admin_service_id']=1;
			$transation['company_id']=$UserRequest->company_id;
			$transation['transaction_id']=$UserRequest->id;
			$transation['country_id']=$UserRequest->country_id;
        	$transation['transaction_alias']=$UserRequest->booking_id;		

			$paymentsRequest = RideRequestPayment::where('ride_request_id',$request_id)->first();

			$provider = Provider::where('id',$paymentsRequest->provider_id)->first();

			$fleet_amount=$discount=$admin_commision=$credit_amount=$balance_provider_credit=$provider_credit=0;                

			if($paymentsRequest->is_partial==1){
				//partial payment
				if($paymentsRequest->payment_mode=="CASH"){
					$credit_amount=$paymentsRequest->wallet + $paymentsRequest->tips;
				}
				else{
					$credit_amount=$paymentsRequest->total + $paymentsRequest->tips;
				}
			}
			else{
				if($paymentsRequest->payment_mode=="CARD" || $paymentsRequest->payment_id=="WALLET"){
					$credit_amount=$paymentsRequest->total + $paymentsRequest->tips;
				}
				else{

					$credit_amount=0;                    
				}    
			}                
			

			//admin,fleet,provider calculations
			if(!empty($paymentsRequest->commision)){

				$admin_commision=$paymentsRequest->commision;

				if(!empty($paymentsRequest->fleet_id)){
					//get the percentage of fleet owners
					$fleet_per=$paymentsRequest->fleet_percent;
					$fleet_amount=($admin_commision) * ( $fleet_per/100 );
					$admin_commision=$admin_commision;

				}

				//check the user applied discount
				if(!empty($paymentsRequest->discount)){
					$balance_provider_credit=$paymentsRequest->discount;
				}  

			}
			else{

				if(!empty($paymentsRequest->fleet_id)){
					$fleet_per=$paymentsRequest->fleet_percent;
					$fleet_amount=($paymentsRequest->total) * ( $fleet_per/100 );
					$admin_commision=$fleet_amount;
				}
				if(!empty($paymentsRequest->discount)){
					$balance_provider_credit=$paymentsRequest->discount;
				}    
			}                

			if(!empty($admin_commision)){
				//add the commission amount to admin wallet and debit amount to provider wallet, update the provider wallet amount to provider table				
        		$transation['id']=$paymentsRequest->provider_id;
        		$transation['amount']=$admin_commision;
			   (new Transactions)->adminCommission($transation);
			}

			if(!empty($paymentsRequest->fleet_id) && !empty($fleet_amount)){
				$paymentsRequest->fleet=$fleet_amount;
				$paymentsRequest->save();
				//create the amount to fleet account and deduct the amount to admin wallet, update the fleet wallet amount to fleet table				
        		$transation['id']=$paymentsRequest->fleet_id;
        		$transation['amount']=$fleet_amount;
			   	(new Transactions)->fleetCommission($transation);
				                       
			}
			if(!empty($balance_provider_credit)){
				//debit the amount to admin wallet and add the amount to provider wallet, update the provider wallet amount to provider table				
        		$transation['id']=$paymentsRequest->provider_id;
        		$transation['amount']=$balance_provider_credit;
			   	(new Transactions)->providerDiscountCredit($transation);				
			}

			if(!empty($paymentsRequest->tax)){
				//debit the amount to provider wallet and add the amount to admin wallet
				$transation['id']=$paymentsRequest->provider_id;
        		$transation['amount']=$paymentsRequest->tax;
				(new Transactions)->taxCredit($transation);
			}

			if(!empty($paymentsRequest->peak_comm_amount)){
				//add the peak amount commision to admin wallet
				$transation['id']=$paymentsRequest->provider_id;
        		$transation['amount']=$paymentsRequest->peak_comm_amount;
				(new Transactions)->peakAmount($transation);
			}

			if(!empty($paymentsRequest->waiting_comm_amount)){
				//add the waiting amount commision to admin wallet
				$transation['id']=$paymentsRequest->provider_id;
        		$transation['amount']=$paymentsRequest->waiting_comm_amount;
				(new Transactions)->waitingAmount($transation);
			}  
			
			if($credit_amount>0){               
				//provider ride amount
				//check whether provider have any negative wallet balance if its deduct the amount from its credit.
				//if its negative wallet balance grater of its credit amount then deduct credit-wallet balance and update the negative amount to admin wallet
				$transation['id']=$paymentsRequest->provider_id;
				$transation['amount']=$credit_amount;

				if($provider->wallet_balance>0){
					$transation['admin_amount']=$credit_amount-($admin_commision+$paymentsRequest->tax);

				}
				else{
					$transation['admin_amount']=$credit_amount-($admin_commision+$paymentsRequest->tax)+($provider->wallet_balance);
				}

				(new Transactions)->providerRideCredit($transation);
			}

			return true;
		}
		else{
			
			return true;
		}
		
	}

}
