<?php

namespace App\Http\Controllers\V1\Service\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Models\Service\ServiceRequest;
use App\Models\Service\ServiceRequestDispute;
use App\Models\Common\Dispute;
use App\Models\Common\Setting;
use App\Models\Service\ServiceCategory;
use App\Models\Service\ServiceSubcategory;
use App\Models\Service\Service;
use App\Models\Service\ServiceCityPrice;
use Auth;

class HomeController extends Controller
{
	//Service Type
    public function service_category(Request $request)
    {
		$service_list = ServiceCategory::where('company_id',Auth::guard('user')->user()->company_id)
						->get();
        return Helper::getResponse(['data' => $service_list]);
	}
	//Service Sub Category
	public function service_sub_category(Request $request,$id) {
		$service_sub_category_list = ServiceSubcategory::where('company_id',Auth::guard('user')->user()->company_id)->where('service_subcategory_status',1)->where('service_category_id',$id)->get();
        return Helper::getResponse(['data' => $service_sub_category_list]);
	}
	//Service Sub Category
	public function service($category_id,$subcategory_id) {
		$service = Service::with(['service_city'=>function($query){
			$query->where('city_id',Auth::guard('user')->user()->city_id);
		}])->where('company_id',Auth::guard('user')->user()->company_id)
					->where('service_subcategory_id',$subcategory_id)
                    ->where('service_category_id',$category_id)
                    ->where('service_status',1)
					->get();
        return Helper::getResponse(['data' => $service]);
	}//Service Sub Category
	public function service_city_price(Request $request,$id) {
		$service_city_price = ServiceCityPrice::with('service')->where('company_id',Auth::guard('user')->user()->company_id)
							  ->where('fare_type','FIXED')
							  	->where('city_id',Auth::guard('user')->user()->city_id)->where('service_id',$id)
							   ->get();
        return Helper::getResponse(['data' => $service_city_price]);
	}
	
    public function trips(Request $request) {
        try{
			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
			$showType = isset($request->type)?$request->type:'past';			
			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'service';
			
			if($request->has('limit')) {
				$ServiceRequests = ServiceRequest::select('id','booking_id','user_id','provider_id','service_id','status','s_address','assigned_at','created_at','timezone')
				->with(['payment',
				'service' => function($query){  $query->select('id', 'service_name'); },
				'user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture' ); },
				'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','mobile' ); },
				])
				->ServiceUserTrips(Auth::guard('user')->user()->id,$showType)
				->take($request->limit)->offset($request->offset)->get();
			} else {
				$ServiceRequests = ServiceRequest::select('id','booking_id','user_id','provider_id','service_id','status','s_address','assigned_at','created_at','timezone')
				->with(['payment',
				'service' => function($query){  $query->select('id', 'service_name'); },
				'user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','currency_symbol'); },
				'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','mobile'); },
				])
				->ServiceUserTrips(Auth::guard('user')->user()->id,$showType);
				if($request->has('search_text') && $request->search_text != null) {
                  $ServiceRequests->ServiceSearch($request->search_text);
                }
                $ServiceRequests=$ServiceRequests->orderby('id',"desc")->paginate(10);
			}
			$jsonResponse['total_records'] = count($ServiceRequests);
			if(!empty($ServiceRequests)){
				$map_icon = '';
				//asset('asset/img/marker-start.png');
				foreach ($ServiceRequests as $key => $value) {
					$ServiceRequests[$key]->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
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
			$jsonResponse['service'] = $ServiceRequests;
			return Helper::getResponse(['data' => $jsonResponse]);
		}

		catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')]);
		}

	}
	public function gettripdetails(Request $request,$id) {
		try{
			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));

			$siteConfig = $settings->site;
			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'service';
			$ServiceRequests = ServiceRequest::with(['provider','payment','service.servicesubCategory','dispute'=>function($query){
               $query->where('dispute_type','user');
			},'rating'=>function($query){
				$query->select('id','request_id','user_comment','provider_comment','user_rating','provider_rating');
				$query->where('admin_service_id',3);
			}])
			->ServiceUserTrips(Auth::guard('user')->user()->id)->where('id',$id)->first();
			if(!empty($ServiceRequests)){
				$map_icon = '';
				//asset('asset/img/marker-start.png');
					$ServiceRequests->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
							"autoscale=1".
							"&size=320x130".
							"&maptype=terrian".
							"&format=png".
							"&visual_refresh=true".
							"&markers=icon:".$map_icon."%7C".$ServiceRequests->s_latitude.",".$ServiceRequests->s_longitude.
							"&markers=icon:".$map_icon."%7C".$ServiceRequests->d_latitude.",".$ServiceRequests->d_longitude.
							"&path=color:0x191919|weight:3|enc:".$ServiceRequests->route_key.
							"&key=".$siteConfig->server_key;
			}
			$jsonResponse['service'] = $ServiceRequests;
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
			$jsonResponse['type'] = 'service';
			if($request->has('limit')) {
				$ServiceRequests = ServiceRequest::select('id','booking_id','user_id','provider_id','service_id','status','s_address','assigned_at','schedule_at','created_at','timezone')
					->with(['payment','service',
					'user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture' ); },
					'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture' ); },
					])->UserUpcomingTrips(Auth::guard('user')->user()->id)
					->take($request->limit)->offset($request->offset)->get();				
			} else {
				$ServiceRequests = ServiceRequest::select('id','booking_id','user_id','provider_id','service_id','status','s_address','assigned_at','schedule_at','created_at','timezone')
					->with(['payment','service',
						'user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture' ); },
						'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture' ); },
						])->UserUpcomingTrips(Auth::guard('user')->user()->id);
            	$ServiceRequests=$ServiceRequests->orderby('id',"desc")->paginate(10);
			
			}
			if($request->has('search_text') && $request->search_text != null) {
				$ServiceRequests->ServiceSearch($request->search_text);
			}
			$jsonResponse['total_records'] = count($ServiceRequests);
			if(!empty($ServiceRequests)){
				$map_icon = '';
				// asset('asset/img/marker-start.png');
				foreach ($ServiceRequests as $key => $value) {
					$ServiceRequests[$key]->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
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
			$jsonResponse['service'] = $ServiceRequests;
			return Helper::getResponse(['data' => $jsonResponse]);
		}

		catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')], 500);
		}

	}
	public function getupcomingtrips(Request $request,$id) {

        try{
			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));

			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'service';
			$ServiceRequests = ServiceRequest::with('provider','payment','service.servicesubCategory','dispute')
			->UserUpcomingTrips(Auth::guard('user')->user()->id)->where('id',$id)->first();
			if(!empty($ServiceRequests)){
				$map_icon = '';
					$ServiceRequests->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
							"autoscale=1".
							"&size=320x130".
							"&maptype=terrian".
							"&format=png".
							"&visual_refresh=true".
							"&markers=icon:".$map_icon."%7C".$ServiceRequests->s_latitude.",".$ServiceRequests->s_longitude.
							"&markers=icon:".$map_icon."%7C".$ServiceRequests->d_latitude.",".$ServiceRequests->d_longitude.
							"&path=color:0x000000|weight:3|enc:".$ServiceRequests->route_key.
							"&key=".$siteConfig->server_key;
			}
			$jsonResponse['service'] = $ServiceRequests;
			return Helper::getResponse(['data' => $jsonResponse]);
		}

		catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')], 500);
		}

	}
	//Save the dispute details
	public function service_request_dispute(Request $request,$id) {
		$service_request_dispute = ServiceRequestDispute::where('company_id',Auth::guard('user')->user()->company_id)
							    ->where('service_request_id',$id)
								->where('dispute_type','user')
								->first();
		if($service_request_dispute==null)
		{
			$this->validate($request, [
				'dispute_name' => 'required',
			]);

			try{
				$service_request_dispute = new ServiceRequestDispute;
				$service_request_dispute->company_id = Auth::guard('user')->user()->company_id;  
				$service_request_dispute->service_request_id = $id;
				$service_request_dispute->dispute_type = $request->dispute_type;
				$service_request_dispute->user_id = $request->user_id;
				$service_request_dispute->provider_id = $request->provider_id;                  
				$service_request_dispute->dispute_name = $request->dispute_name;
				$service_request_dispute->dispute_title =  $request->dispute_title; 
				$service_request_dispute->comments =  $request->comments; 
				$service_request_dispute->save();
				return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);
			} 
			catch (\Throwable $e) {
				return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
			}
		}else{
			return Helper::getResponse(['status' => 404, 'message' => trans('Already Dispute Created for the Ride Request')]);
		}
	}
	public function get_service_request_dispute(Request $request,$id) {
		$service_request_dispute = ServiceRequestDispute::where('company_id',Auth::guard('user')->user()->company_id)
							    ->where('service_request_id',$id)
								->where('dispute_type','user')
								->first();
		return Helper::getResponse(['data' => $service_request_dispute]);
	}
	public function getdisputedetails(Request $request)
	{
		$dispute = Dispute::select('id','dispute_name','service')->where('service','SERVICE')->where('dispute_type','provider')->get();
        return Helper::getResponse(['data' => $dispute]);
	}
	public function getUserdisputedetails(Request $request)
	{
		$dispute = Dispute::select('id','dispute_name','service')->where('service','SERVICE')->where('dispute_type','user')->get();
        return Helper::getResponse(['data' => $dispute]);
	}
	
}
