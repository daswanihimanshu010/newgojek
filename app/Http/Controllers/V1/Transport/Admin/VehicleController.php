<?php

namespace App\Http\Controllers\V1\Transport\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Traits\Actions;
use App\Helpers\Helper;
use App\Models\Transport\RideDeliveryVehicle;
use App\Models\Transport\RideCity;
use App\Models\Transport\RideCityPrice;
use App\Models\Transport\RidePeakPrice;
use App\Models\Common\AdminService;
use App\Models\Common\MenuCity;
use App\Models\Common\Menu;
use App\Models\Common\CompanyCity;

use App\Models\Common\CompanyCountry;
use App\Models\Common\PeakHour;
use App\Models\Transport\RideType;

use Illuminate\Support\Facades\Storage;
use Auth;

class VehicleController extends Controller
{
    use Actions;

    private $model;
    private $request;

    public function __construct(RideDeliveryVehicle $model)
    {
        $this->model = $model;
    }

    public function index(Request $request)
    {
    	$datum = RideDeliveryVehicle::with('ride_type')->where('company_id', Auth::user()->company_id);

        if($request->has('search_text') && $request->search_text != null) {
            $datum->Search($request->search_text);
        }

        if($request->has('order_by')) {
            $datum->orderby($request->order_by, $request->order_direction);
        }

        $data = $datum->paginate(10);

        return Helper::encryptResponse(['data' => $data]);
    } 

    public function vehicleList(Request $request)
    {
    	$datum = RideDeliveryVehicle::with('ride_type')->where('company_id', Auth::user()->company_id);

        if($request->has('search_text') && $request->search_text != null) {
            $datum->Search($request->search_text);
        }

        if($request->has('order_by')) {
            $datum->orderby($request->order_by, $request->order_direction);
        }

        $data = $datum->paginate(10);

        return Helper::getResponse(['data' => $data]);
    } 

    public function store(Request $request)
    {
        $this->validate($request, [
            'vehicle_name' => 'required|max:255',            
            'capacity' => 'required|numeric',
            'ride_type_id' => 'required',
            'vehicle_image' => 'sometimes|nullable|mimes:ico,png',
            'vehicle_marker' => 'sometimes|nullable|mimes:ico,png',
        ]);

        try {
                $rideDeliveryVehicle = new RideDeliveryVehicle;
                $rideDeliveryVehicle->company_id = Auth::user()->company_id; 
                $rideDeliveryVehicle->vehicle_name = $request->vehicle_name;            
                $rideDeliveryVehicle->capacity = $request->capacity;
                $rideDeliveryVehicle->vehicle_type = 'RIDE';
                $rideDeliveryVehicle->status = $request->status;
                $rideDeliveryVehicle->ride_type_id = $request->ride_type_id;
                if($request->hasFile('vehicle_image')) {
                    $rideDeliveryVehicle->vehicle_image = Helper::upload_file($request->file('vehicle_image'), 'vehicle/image');
                }
                if($request->hasFile('vehicle_marker')) {
                    $rideDeliveryVehicle->vehicle_marker = Helper::upload_file($request->file('vehicle_marker'), 'vehicle/marker');
                }
                $rideDeliveryVehicle->save();

                return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);
           } 
           catch (\Throwable $e) 
           {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
           }
     }


    public function show($id)
    {
        try {
            $rideDeliveryVehicle = RideDeliveryVehicle::findOrFail($id);
            return Helper::getResponse(['data' => $rideDeliveryVehicle]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'vehicle_name' => 'required|max:255',            
            'capacity' => 'required|numeric',
            'ride_type_id' => 'required',
            'vehicle_image' => 'mimes:ico,png',
            'vehicle_marker' => 'mimes:ico,png',
        ]);

        try{
                $rideDeliveryVehicle = RideDeliveryVehicle::findOrFail($id);
                if($rideDeliveryVehicle)
                {
                    if($request->hasFile('vehicle_image')) {
                        if($rideDeliveryVehicle->vehicle_image) {
                            Helper::delete_picture($rideDeliveryVehicle->vehicle_image);
                        }
                        $rideDeliveryVehicle->vehicle_image = Helper::upload_file($request->file('vehicle_image'), 'vehicle/image');
                    }
                    if($request->hasFile('vehicle_marker')) {
                        if($rideDeliveryVehicle->vehicle_marker) {
                            Helper::delete_picture($rideDeliveryVehicle->vehicle_marker);
                        }
                        $rideDeliveryVehicle->vehicle_marker = Helper::upload_file($request->file('vehicle_marker'), 'vehicle/marker');
                    }
                    $rideDeliveryVehicle->vehicle_name = $request->vehicle_name;            
                    $rideDeliveryVehicle->capacity = $request->capacity;
                    $rideDeliveryVehicle->vehicle_type = 'RIDE';
                    $rideDeliveryVehicle->status = $request->status;
                    $rideDeliveryVehicle->ride_type_id = $request->ride_type_id;
                    $rideDeliveryVehicle->save();
                    return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);
                }
                else
                {
                return Helper::getResponse(['status' => 404, 'message' => trans('admin.not_found')]); 
                }
            } 
            catch (\Throwable $e) {
                return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
            }
    }
    public function vehicletype()
    {
        $vehicle_type = RideDeliveryVehicle::where('company_id', Auth::user()->company_id)->where('status',1)->get();
        return Helper::getResponse(['data' => $vehicle_type]);
    }

    public function destroy($id)
    {
        return $this->removeModel($id);
    }

    public function multidestroy(Request $request)
    {
        $this->request = $request;
        return $this->removeMultiple();
    }

    public function statusChange(Request $request)
    {
        $this->request = $request;
        return $this->changeStatus();
    }

    public function statusChangeMultiple(Request $request)
    {
        $this->request = $request;
        return $this->changeStatusAll();
    }
    public function comission(Request $request)
    {
       
        $this->validate($request, [
             'country_id' => 'required',
             'city_id' => 'required',
             'admin_service_id' => 'required',
             'comission' => 'required',
             'fleet_comission' => 'required',
             'tax' => 'required',
             'night_charges' => 'required',

        ],['comission.required' => 'Please Enter Commission',
           'fleet_comission.required' => 'Please Enter Fleet Commission'
    ]);
       
        try{
            if($request->ride_city_id !=''){
                $rideCity = RideCity::findOrFail($request->ride_city_id);
            }else{
                $rideCity = new RideCity;
            }
           
            $rideCity->company_id = Auth::user()->company_id;  
            $rideCity->country_id = $request->country_id; 
            $rideCity->city_id = $request->city_id;  
            $rideCity->admin_service_id = $request->admin_service_id;  
            $rideCity->comission = $request->comission;  
            $rideCity->fleet_comission = $request->fleet_comission; 
            $rideCity->tax = $request->tax; 
            $rideCity->night_charges = $request->night_charges; 
            $rideCity->save();
       
            return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);
        } 

        catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function getComission($country_id,$city_id,$admin_service_id)
    {
       
            $rideCity = RideCity::where([['company_id',Auth::user()->company_id],
                                         ['country_id',$country_id],
                                         ['city_id',$city_id],
                                         ['admin_service_id',$admin_service_id]])->first();
             if($rideCity){
                return Helper::getResponse(['data' => $rideCity]);
             }
             return Helper::getResponse(['data' => '']);
  

    }
    public function gettaxiprice($id)
    {
       $admin_service_id = AdminService::where('admin_service_name','TRANSPORT')->value('id');
       if($admin_service_id){
       $cityList = CompanyCountry::with('country','companyCountryCities')->get();
    //    print_r($cityList);exit;
       }
            // $rideCity = RideCity::where([['company_id',Auth::user()->company_id],
            //                              ['country_id',$country_id],
            //                              ['city_id',$city_id],
            //                              ['admin_service_id',$admin_service_id]])->first();
            //  if($rideCity){
                return Helper::getResponse(['data' => $cityList]);
            //  }
            //  return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong')]);
  
    }

    public function rideprice(Request $request)
    {
        // print_r($request->all());exit;
       /* $this->validate($request, [
             'city_id' => 'required',
             'fixed' => 'required',
             'price' => 'required',
             'minute' => 'required',
             'hour' => 'required',
             'distance' => 'required',
             'waiting_free_mins' => 'required',
             'waiting_min_charge' => 'required',
        ]);*/

         $this->validate($request, [
            'city_id' => 'required',
            'fixed' => 'required|numeric',
            'price' => 'sometimes|nullable|numeric',
            'minute' => 'sometimes|nullable|numeric',
            'hour' => 'sometimes|nullable|numeric',
            'distance' => 'sometimes|nullable|numeric',
            'calculator' => 'required|in:MIN,HOUR,DISTANCE,DISTANCEMIN,DISTANCEHOUR'            
        ]);
       
        try{
            if($request->ride_price_id !=''){
                $ridePrice = RideCityPrice::findOrFail($request->ride_price_id);
            }else{
                $ridePrice = new RideCityPrice;
            }
           
            $ridePrice->company_id = Auth::user()->company_id;  
            $ridePrice->fixed = $request->fixed; 
            $ridePrice->city_id = $request->city_id;  
            $ridePrice->ride_delivery_vehicle_id = $request->ride_delivery_vehicle_id;  
            $ridePrice->calculator = $request->calculator;  

            if(!empty($request->price))
                $ridePrice->price = $request->price;
            else
                $ridePrice->price=0;

            if(!empty($request->minute))
                $ridePrice->minute = $request->minute;
            else
                $ridePrice->minute=0;

            if(!empty($request->hour))
                $ridePrice->hour = $request->hour;
            else
                $ridePrice->hour=0;

            if(!empty($request->distance))
                $ridePrice->distance = $request->distance;
            else
                $ridePrice->distance=0;

            if(!empty($request->waiting_free_mins))
                $ridePrice->waiting_free_mins = $request->waiting_free_mins;
            else
                $ridePrice->waiting_free_mins=0;

            if(!empty($request->waiting_min_charge))
                $ridePrice->waiting_min_charge = $request->waiting_min_charge;
            else
                $ridePrice->waiting_min_charge=0;

            if(!empty($request->commission))
                $ridePrice->commission = $request->commission;
            else
                $ridePrice->commission=0;

            if(!empty($request->fleet_commission))
                $ridePrice->fleet_commission = $request->fleet_commission;
            else
                $ridePrice->fleet_commission=0;

            if(!empty($request->tax))
                $ridePrice->tax = $request->tax;
            else
                $ridePrice->tax=0;

            if(!empty($request->peak_commission))
                $ridePrice->peak_commission = $request->peak_commission;
            else
                $ridePrice->peak_commission=0;
            
            if(!empty($request->waiting_commission))
                $ridePrice->waiting_commission = $request->waiting_commission;
            else
                $ridePrice->waiting_commission=0;

            $ridePrice->save();
            if($request->peak_price){

                foreach ($request->peak_price as $key => $peak_price) {

                    if(!empty($peak_price['value'])) {

                        if($peak_price['id'] !=''){
                            $RidePeakPrice = RidePeakPrice::findOrFail($peak_price['id']);
                        }else{
                            $RidePeakPrice = new RidePeakPrice;
                        }  
                        //$RidePeakPrice = new RidePeakPrice;
                        $RidePeakPrice->ride_city_price_id =$ridePrice->id;
                        $RidePeakPrice->ride_delivery_id =$request->ride_delivery_vehicle_id;
                        $RidePeakPrice->peak_hour_id =$key;
                        $RidePeakPrice->peak_price =($peak_price['value'] !='')?$peak_price['value']:'0.00';
                        $RidePeakPrice->save();
                    } 

                }

            }
       
            return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);
        } 

        catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function getRidePrice($ride_delivery_vehicle_id,$city_id)
    {
       
            $rideCityPrice = RideCityPrice::where([['company_id',Auth::user()->company_id],
                                         ['ride_delivery_vehicle_id',$ride_delivery_vehicle_id],
                                         ['city_id',$city_id]])->first();
            $peakHour = PeakHour::get();
             if($rideCityPrice){
                 foreach($peakHour as $key=>$value){
                 $RidePeakPrice = RidePeakPrice::where([['ride_city_price_id',$rideCityPrice->id],['peak_hour_id',$value->id]])->first();
                 if($RidePeakPrice){
                    $peakHour[$key]['ridePeakhour'] = $RidePeakPrice;
                 }
                }
                return Helper::getResponse(['data' => ['price'=>$rideCityPrice,'peakHour'=> $peakHour]]);
             }
             return Helper::getResponse(['data' =>['price'=>'','peakHour'=> $peakHour]]);

    }


    public function getcity(Request $request)
    {
         //dd($request->city_id);
        $menudetails=Menu::select('menu_type_id')->where('id',$request->city_id)->first();
       
        $rideprice=RideCityPrice::select('city_id')->where('ride_delivery_vehicle_id',$menudetails->menu_type_id)->get()->toArray();
        $company_cities = CompanyCity::with(['country','city','menu_city' => function($query) use($request) {
            $query->where('menu_id','=',$request->city_id);
        }])->where('company_id', Auth::user()->company_id);

        if($request->has('search_text') && $request->search_text != null) {
            $company_cities = $company_cities->Search($request->search_text);
        }
        $cities = $company_cities->paginate(500);

        foreach($cities as $key=>$value){

           $cities[$key]['city_price']=0;
           
           if(in_array($value->city_id,array_column($rideprice,'city_id'))){
            
             $cities[$key]['city_price']=1;
           } 
        }


        return Helper::getResponse(['data' => $cities]);
    }





    public function getvehicletype(){

     $vehicle_type = RideType::all();
                                       
             return Helper::getResponse(['data' =>['vehicle_type'=>$vehicle_type]]);


    }

    public function updateStatus(Request $request, $id)
    {
        
        try {

            $datum = RideDeliveryVehicle::findOrFail($id);
            
            if($request->has('status')){
                if($request->status == 1){
                    $datum->status = 0;
                }else{
                    $datum->status = 1;
                }
            }
            $datum->save();
           
           
            return Helper::getResponse(['status' => 200, 'message' => trans('admin.activation_status')]);

        } 

        catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }




    
}
