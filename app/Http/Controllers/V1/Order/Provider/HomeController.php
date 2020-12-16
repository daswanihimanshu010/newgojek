<?php

namespace App\Http\Controllers\V1\Order\Provider;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Order\StoreType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use App\Traits\Actions;
use App\Helpers\Helper; 
use Carbon\Carbon;
use Auth;
use DB;

class HomeController extends Controller
{
	  public function shoptype(Request $request)
		{
				try{
						$storetype=StoreType::with('providerservice')->where('status',1)->where('company_id',Auth::guard('provider')->user()->company_id)->get();
						return Helper::getResponse(['data' => $storetype ]);
				}catch (ModelNotFoundException $e) {
						return Helper::getResponse(['status' => 500, 'error' => $e->getMessage()]);
				}
		}

		public function assign_next_provider($request_id) 
		{
		    try {
				    $userRequest = UserRequest::where('request_id', $request_id)->first();
			  } catch (ModelNotFoundException $e) {
					// Cancelled between update.
					return false;
				}

				$admin_service = AdminService::find($userRequest->admin_service_id)->where('company_id', Auth::guard('provider')->user()->company_id)->first();

				try {
						if($admin_service != null && $admin_service->admin_service_name == "SERVICE" ) {
							$newRequest = \App\Models\Order\StoreOrder::with('user')->find($userRequest->request_id);
						}
				} catch(\Throwable $e) { }

				$RequestFilter = RequestFilter::where('request_id', $userRequest->id)->orderBy('id')->first();

				if($RequestFilter != null) {
					$RequestFilter->delete();
				}				

			try {
				$next_provider = RequestFilter::where('request_id', $userRequest->id)->orderBy('id')->first();
				if($next_provider != null) {
					$newRequest->assigned_at = Carbon::now();
					$newRequest->save();
					// incoming request push to provider
					(new SendPushNotification)->serviceIncomingRequest($next_provider->provider_id, 'order_incoming_request');
				} else {
					$userRequest->delete();
					$newRequest->status = 'CANCELLED';
					$newRequest->save();
				}
			
			} catch (ModelNotFoundException $e) {
						RideRequest::where('id', $newRequest->id)->update(['status' => 'CANCELLED']);
						// No longer need request specific rows from RequestMeta
						$RequestFilter = RequestFilter::where('request_id', $userRequest->id)->orderBy('id')->first();
				if($RequestFilter != null) {
						$RequestFilter->delete();
			}
						//  request push to user provider not available
						(new SendPushNotification)->serviceProviderNotAvailable($userRequest->user_id, 'order');
			}
	}
	

	
    
}
