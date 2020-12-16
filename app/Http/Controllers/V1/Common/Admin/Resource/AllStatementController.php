<?php

namespace App\Http\Controllers\V1\Common\Admin\Resource;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Traits\Actions;
use App\Models\Common\UserRequest;
use App\Models\Common\User;
use App\Models\Common\Provider;
use App\Models\Common\AdminWallet;
use App\Models\Common\AdminService;
use App\Helpers\Helper;
use Auth;
use Carbon\Carbon;

class AllStatementController extends Controller
{
    use Actions;
    private $model;
    private $request;
    
    public function index(){
        //dd('good');   
    }
    
    public function statement_provider(Request $request)
    {

        try{
            $servicenames=array();
            $adminservices = AdminService::where('company_id', Auth::user()->company_id)->pluck('admin_service_name')->toArray();
            if($adminservices != null){
                $servicenames = $adminservices;
            }
            $datum = Provider::select('*','created_at as joined')->where('company_id', Auth::user()->company_id);
            if($request->has('search_text') && $request->search_text != null) {
                $datum->Search($request->search_text);
            }
            if($request->has('order_by')) {
                $datum->orderby($request->order_by, $request->order_direction);
            }
            $Providers = $datum->paginate(10);

		foreach($Providers as $index => $Provider){
            $ridePayment = 0;
            $servicePayment = 0;
            $orderPayment = 0;
            if(in_array('TRANSPORT',$servicenames) == TRUE){
                $Rides = \App\Models\Transport\RideRequest::where('provider_id',$Provider->id)
                            ->where('status','<>','CANCELLED')
                            ->get()->pluck('id');
                $Providers[$index]->rides_count = $Rides->count();
                $ridePaymentQ = \App\Models\Transport\RideRequestPayment::whereIn('ride_request_id', $Rides)
                                ->select(\DB::raw('SUM(ROUND(provider_pay)) as overall'))->first();
                if($ridePaymentQ != null){
                    $ridePayment = $ridePaymentQ->overall;
                }
            }
            if (in_array("SERVICE", $servicenames)){
                $Services = \App\Models\Service\ServiceRequest::where('provider_id',$Provider->id)
                                ->where('status','<>','CANCELLED')
                                ->where('status','<>','SCHEDULED')
                                ->get()->pluck('id');
                $Providers[$index]->services_count = $Services->count();
                $servicepaymentQ = \App\Models\Service\ServiceRequestPayment::whereIn('service_request_id', $Services)
                                ->select(\DB::raw(
                                'SUM(ROUND(total)) as serviceoverall' 
                                ))->first();
                if($servicepaymentQ != null){
                    $servicePayment = $servicepaymentQ->serviceoverall;
                }
            }
            if (in_array("ORDER", $servicenames)){
                $Orders = \App\Models\Order\StoreOrder::where('provider_id',$Provider->id)
                            ->where('status','<>','CANCELLED')
                            ->where('status','<>','SCHEDULED')
                            ->get()->pluck('id');
                $Providers[$index]->orders_count = $Orders->count();
                $orderpaymentQ = \App\Models\Order\StoreOrderInvoice::whereIn('store_order_id', $Orders)
                                ->select(\DB::raw(
                                'SUM(ROUND(total_amount)) as orderoverall' 
                                ),'cart_details')->first();
                if($orderpaymentQ != null){
                    $orderPayment = $orderpaymentQ->orderoverall;
                }
            }
            $Providers[$index]->payment = $ridePayment +  $servicePayment + $orderPayment;
        }
            return Helper::getResponse(['data' => $Providers]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

   public function statement_user(Request $request)
   {

	try{
        $servicenames=array();
        $adminservices = AdminService::where('company_id', Auth::user()->company_id)->pluck('admin_service_name')->toArray();
        if($adminservices != null){
            $servicenames = $adminservices;
        }
		$datum = User::where('company_id', Auth::user()->company_id);
        if($request->has('search_text') && $request->search_text != null) {
            $datum->Search($request->search_text);
        }
        if($request->has('order_by')) {
            $datum->orderby($request->order_by, $request->order_direction);
        }
        $Users = $datum->paginate(10);

        foreach($Users as $index => $User){
            $ridePayment = 0;
            $servicePayment = 0;
            $orderPayment = 0;
            if(in_array('TRANSPORT',$servicenames) == TRUE){
                $Rides = \App\Models\Transport\RideRequest::where('user_id',$User->id)
                            ->where('status','<>','CANCELLED')
                            ->where('status','<>','SCHEDULED')
                            ->get()->pluck('id');
                $Users[$index]->rides_count = $Rides->count();
                $ridePaymentQ = \App\Models\Transport\RideRequestPayment::whereIn('ride_request_id', $Rides)
                                ->select(\DB::raw(
                                'SUM(ROUND(total)) as overall' 
                                ))->first();
                if($ridePaymentQ != null){
                    $ridePayment = $ridePaymentQ->overall;
                }
            }
            if (in_array("SERVICE", $servicenames)){
                $Services = \App\Models\Service\ServiceRequest::where('user_id',$User->id)
                            ->where('status','<>','CANCELLED')
                            ->where('status','<>','SCHEDULED')
                            ->get()->pluck('id');
                $Users[$index]->services_count = $Services->count();
                $servicepaymentQ = \App\Models\Service\ServiceRequestPayment::whereIn('service_request_id', $Services)
                                ->select(\DB::raw(
                                'SUM(ROUND(total)) as serviceoverall' 
                                ))->first();
                if($servicepaymentQ != null){
                    $servicePayment = $servicepaymentQ->serviceoverall;
                }
            }
            if (in_array("ORDER", $servicenames)){
                $Orders = \App\Models\Order\StoreOrder::where('user_id',$User->id)
                            ->where('status','<>','CANCELLED')
                            ->where('status','<>','SCHEDULED')
                            ->get()->pluck('id');
                $Users[$index]->orders_count = $Orders->count();
                $orderpaymentQ = \App\Models\Order\StoreOrderInvoice::whereIn('store_order_id', $Orders)
                                ->select(\DB::raw(
                                'SUM(ROUND(total_amount)) as orderoverall' 
                                ),'cart_details')->first();
                if($orderpaymentQ != null){
                    $orderPayment = $orderpaymentQ->orderoverall;
                }
            }
            $Users[$index]->payment = $ridePayment +  $servicePayment + $orderPayment;           
        }
            return Helper::getResponse(['data' => $Users]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function statement_admin(Request $request)
    {

        try{        
        $datum = AdminWallet::select('*','created_at as dated')->where('company_id', Auth::user()->company_id);
        if($request->has('search_text') && $request->search_text != null) {
            $datum->Search($request->search_text);
        }
        if($request->has('order_by')) {
            $datum->orderby($request->order_by, $request->order_direction);
        }
        if($request->has('country_id')) {
            $datum->where('country_id',$request->country_id);
        }
        $type = isset($_GET['type'])?$_GET['type']:'';
        if($type == 'today'){
            $datum->where('created_at', '>=', Carbon::today());
        }elseif($type == 'monthly'){
            $datum->where('created_at', '>=', Carbon::now()->month);
        }elseif($type == 'yearly'){
            $datum->where('created_at', '>=', Carbon::now()->year);
        }elseif ($type == 'range') {   
            if($request->has('from') &&$request->has('to')) {             
                if($request->from == $request->to) {
                    $datum->whereDate('created_at', date('Y-m-d', strtotime($request->from)));
                } else {
                    $datum->whereBetween('created_at',[Carbon::createFromFormat('Y-m-d', $request->from),Carbon::createFromFormat('Y-m-d', $request->to)]);
                }
            }
        }else{
            // dd(5);
        }
        $result = $datum->paginate(10);
        foreach($result as $value){
            $value->amount_type = $value->type == 'C' ? 'Credit' :'Debit';
            if($value->admin_service_id != null && $value->admin_service_id != ''){
                $value->admin_service = AdminService::where('id',$value->admin_service_id)->pluck('display_name');
            }else{
                $value->admin_service = 'Others';
            }
            
        }		
            return Helper::getResponse(['data' => $result]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }
   
}
