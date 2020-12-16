<?php

namespace App\Http\Controllers\V1\Transport\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Models\Transport\RideRequest;
use App\Models\Transport\RideRequestDispute;
use App\Models\Transport\RideLostItem;
use App\Models\Common\Dispute;
use App\Models\Common\Setting;
use App\Models\Common\Rating;
use Auth;

class HomeController extends Controller
{
    public function trips(Request $request) {
        try{

			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
			$showType = isset($request->type)?$request->type:'past';
			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'transport';
			if($showType == 'past'){
				$history_status = array('CANCELLED','COMPLETED');
			}else if($showType=='history'){
                $history_status = array('SCHEDULED');
			}else{
				$history_status = array('SEARCHING','ACCEPTED','STARTED','ARRIVED','PICKEDUP','DROPPED');
			}
			if($request->has('limit')) {
				$RideRequests = RideRequest::select('id', 'booking_id', 'assigned_at', 's_address', 'd_address','provider_id','user_id','timezone','ride_delivery_id', 'status', 'provider_vehicle_id','created_at')
				->with([
				'user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','currency_symbol' ); },
				'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','mobile' ); },
				'provider_vehicle' => function($query){  $query->select('id', 'provider_id', 'vehicle_make', 'vehicle_model', 'vehicle_no' ); },
				'payment' => function($query){  $query->select('ride_request_id','total'); }, 
				'ride' => function($query){  $query->select('id','vehicle_name', 'vehicle_image'); }])
					->where('user_id', Auth::guard('user')->user()->id)
                    ->whereIn('ride_requests.status',$history_status)
                    ->orderBy('ride_requests.created_at','desc')
                    ->take($request->limit)->offset($request->offset)->get();
			} else {
				$RideRequests = RideRequest::select('id', 'booking_id', 'assigned_at', 's_address', 'd_address','provider_id','user_id','timezone','ride_delivery_id', 'status', 'provider_vehicle_id','schedule_at','created_at')
				->with([
				'user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','currency_symbol' ); },
				'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','mobile' ); },
				'provider_vehicle' => function($query){  $query->select('id', 'provider_id', 'vehicle_make', 'vehicle_model', 'vehicle_no' ); },
				'payment' => function($query){  $query->select('ride_request_id','total','payment_mode'); }, 
				'ride' => function($query){  $query->select('id','vehicle_name', 'vehicle_image'); }])
					->where('user_id', Auth::guard('user')->user()->id)
                    ->whereIn('ride_requests.status',$history_status);
                    if($request->has('search_text') && $request->search_text != null) {

			            $RideRequests->histroySearch($request->search_text);
			        }

			        if($request->has('order_by')) {

			            $RideRequests->orderby($request->order_by, $request->order_direction);
			        }
                    
                    $RideRequests=$RideRequests->orderby('id',"desc")->paginate(10);
			}
			$jsonResponse['total_records'] = count($RideRequests);
			
			if(!empty($RideRequests)){
				$map_icon = '';
				//asset('asset/img/marker-start.png');
				foreach ($RideRequests as $key => $value) {
					$RideRequests[$key]->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
							"autoscale=1".
							"&size=320x130".
							"&maptype=terrian".
							"&format=png".
							"&visual_refresh=true".
							"&markers=icon:".$map_icon."%7C".$value->s_latitude.",".$value->s_longitude.
							"&markers=icon:".$map_icon."%7C".$value->d_latitude.",".$value->d_longitude.
							"&path=color:0x191919|weight:3|enc:".$value->route_key.
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
	public function gettripdetails(Request $request,$id) {
		try{
			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
			$userid = Auth::guard('user')->user()->id;
			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'transport';
			$RideRequests = RideRequest::with(['provider','payment','service_type','ride','provider_vehicle'])
			->UserTrips($userid)->where('id',$id)
			->first();
			if(!empty($RideRequests)){
				$ratingQuery = Rating::select('id','user_rating','provider_rating','user_comment','provider_comment')
				->where('admin_service_id',1)
										->where('request_id',$RideRequests->id)->first();
				$RideRequests->rating = $ratingQuery;
				$map_icon = '';
				$RideRequests->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
						"autoscale=1".
						"&size=320x130".
						"&maptype=terrian".
						"&format=png".
						"&visual_refresh=true".
						"&markers=icon:".$map_icon."%7C".$RideRequests->s_latitude.",".$RideRequests->s_longitude.
						"&markers=icon:".$map_icon."%7C".$RideRequests->d_latitude.",".$RideRequests->d_longitude.
						"&path=color:0x191919|weight:3|enc:".$RideRequests->route_key.
						"&key=".$siteConfig->server_key;
				$RideRequests->dispute = RideRequestDispute::where(['user_id'=>$userid,'ride_request_id'=>$RideRequests->id,'dispute_type'=>'user'])->first();
				$RideRequests->lost_item = RideLostItem::where(['user_id'=>$userid,'ride_request_id'=>$id])->first();
			}
			$jsonResponse['transport'] = $RideRequests;
			return Helper::getResponse(['data' => $jsonResponse]);
		}
		catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')]);
		}
	}

    public function upcoming_trips(Request $request) {

        try{
			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));

			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'transport';
			if($request->has('limit')) {
				$RideRequests = RideRequest::with('provider','payment','ride')
				->UserUpcomingTrips(Auth::guard('user')->user()->id)
				->take($request->limit)->offset($request->offset)->get();
			}else{
				$RideRequests = RideRequest::with('provider','payment','ride')
				->UserUpcomingTrips(Auth::guard('user')->user()->id)->paginate(10);
			}
			if(!empty($RideRequests)){
				$map_icon = '';
				// asset('asset/img/marker-start.png');
				foreach ($RideRequests as $key => $value) {
					$RideRequests[$key]->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
							"autoscale=1".
							"&size=320x130".
							"&maptype=terrian".
							"&format=png".
							"&visual_refresh=true".
							"&markers=icon:".$map_icon."%7C".$value->s_latitude.",".$value->s_longitude.
							"&markers=icon:".$map_icon."%7C".$value->d_latitude.",".$value->d_longitude.
							"&path=color:0x000000|weight:3|enc:".$value->route_key.
							"&key=".$siteConfig->server_key;
				}
			}
			$jsonResponse['total_records'] = count($RideRequests);
			$jsonResponse['transport'] = $RideRequests;
			return Helper::getResponse(['data' => $jsonResponse]);
		}

		catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')], 500);
		}

	}
	public function getupcomingtrips(Request $request,$id) {

        try{
			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
			$userid=Auth::guard('user')->user()->id;
			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'transport';
			$RideRequests = RideRequest::with(['provider','payment','ride','service_type'])
			->UserUpcomingTrips($userid)
			->where('id',$id)->first();
			if(!empty($RideRequests)){
				$map_icon = '';
				$RideRequests->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
						"autoscale=1".
						"&size=320x130".
						"&maptype=terrian".
						"&format=png".
						"&visual_refresh=true".
						"&markers=icon:".$map_icon."%7C".$RideRequests->s_latitude.",".$RideRequests->s_longitude.
						"&markers=icon:".$map_icon."%7C".$RideRequests->d_latitude.",".$RideRequests->d_longitude.
						"&path=color:0x000000|weight:3|enc:".$RideRequests->route_key.
						"&key=".$siteConfig->server_key;
				$RideRequests->dispute = RideRequestDispute::where(['user_id'=>$userid,'dispute_type'=>'user'])->first();
				$RideRequests->lost_item = RideLostItem::where(['user_id'=>$userid,'ride_request_id'=>$id])->first();
			}
			$jsonResponse['transport'] = $RideRequests;
			return Helper::getResponse(['data' => $jsonResponse]);
		}

		catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')], 500);
		}

	}
	//Save the dispute details
	public function ride_request_dispute(Request $request) {
		$ride_request_dispute = RideRequestDispute::where('company_id',Auth::guard('user')->user()->company_id)
							    ->where('ride_request_id',$request->id)
								->where('dispute_type','user')
								->first();
		if($ride_request_dispute==null)
		{
			$this->validate($request, [
				'dispute_name' => 'required',
				'dispute_type' => 'required',
				'provider_id' => 'required',
			]);

			try{
				$ride_request_dispute = new RideRequestDispute;
				$ride_request_dispute->company_id = Auth::guard('user')->user()->company_id;  
				$ride_request_dispute->ride_request_id = $request->id;
				$ride_request_dispute->dispute_type = $request->dispute_type;
				$ride_request_dispute->user_id = Auth::guard('user')->user()->id;
				$ride_request_dispute->provider_id = $request->provider_id;                  
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
		$ride_request_dispute = RideRequestDispute::where('company_id',Auth::guard('user')->user()->company_id)
							    ->where('ride_request_id',$id)
								->where('dispute_type','user')
								->first();
		return Helper::getResponse(['data' => $ride_request_dispute]);
	}
	//Save the dispute details
	public function ride_lost_item(Request $request) {
		$ride_lost_item = RideLostItem::where('company_id',Auth::guard('user')->user()->company_id)
						  ->where('ride_request_id',$request->id)
						  ->first();
		if($ride_lost_item==null)
		{
			$this->validate($request, [
				'id' => 'required|numeric|exists:transport.ride_requests,id,user_id,'.Auth::guard('user')->user()->id,
				'lost_item_name' => 'required',
			]);
			try{
				$ride_lost_item = new RideLostItem;
				$ride_lost_item->ride_request_id = $request->id;
				$ride_lost_item->company_id = Auth::guard('user')->user()->company_id;  
				$ride_lost_item->user_id = Auth::guard('user')->user()->id;
				$ride_lost_item->lost_item_name = $request->lost_item_name;
				$ride_lost_item->save();
				return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);
			} 
			catch (\Throwable $e) {
				return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
			}
		}else{
			return Helper::getResponse(['status' => 404, 'message' => trans('Already Lost Items Created for the Ride Request')]);
		}
	}
	public function get_ride_lost_item(Request $request,$id) {
		$ride_lost_item = RideLostItem::where('company_id',Auth::guard('user')->user()->company_id)
								->where('ride_request_id',$id)
								->first();
		return Helper::getResponse(['data' => $ride_lost_item]);
	}
	public function getdisputedetails(Request $request)
	{
		$dispute = Dispute::select('id','dispute_name','service')->where('service','TRANSPORT')->where('dispute_type','provider')->get();
        return Helper::getResponse(['data' => $dispute]);
	}
	
	public function getUserdisputedetails(Request $request)
	{
		$dispute = Dispute::select('id','dispute_name','service')->where('service','TRANSPORT')->where('dispute_type','user')->get();
        return Helper::getResponse(['data' => $dispute]);
	}
}
