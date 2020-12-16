<?php

namespace App\Http\Controllers\V1\Order\Shop\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Traits\Actions;
use App\Models\Order\Store;
use App\Models\Order\StoreOrder;
use App\Models\Order\StoreOrderInvoice;
use App\Models\Order\StoreOrderDispute;
use App\Models\Order\StoreOrderStatus;
use App\Models\Order\StoreWallet;
use App\Models\Common\User;
use App\Models\Common\UserRequest;
use App\Models\Common\RequestFilter;
use App\Models\Common\AdminService;
use App\Models\Common\Provider;
use App\Models\Common\Setting;
use App\Traits\Encryptable;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use DB;
use Auth;
use Log;

class AdminController extends Controller
{
    use Actions, Encryptable;

    
    private $request;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function password()
    {
        $password = Store::where('id',Auth::guard('shop')->user()->id)->where('company_id',\Auth::guard('shop')->user()->company_id)->first();
        return Helper::getResponse(['data' => $password]);
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
            'password' => 'required|min:6|confirmed',
        ]);

        try {

           $Admin = Store::where('id',Auth::guard('shop')->user()->id)->where('company_id',\Auth::guard('shop')->user()->company_id)->first();

            if(password_verify($request->old_password, $Admin->password))
            {
                $Admin->password = Hash::make($request->password);
                $Admin->save();
            }
            return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);
        }  catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function orders(Request $request)
    {
        
      $orders = StoreOrder::with('user', 'provider','invoice','storeOrderDispute')->where('store_id',Auth::guard('shop')->user()->id)->orderBy('id','desc');
      if($request->type == "ORDERED"){
            $orders = $orders->where('status',"ORDERED");
        } else if($request->type == "ACCEPTED"){
            $orders = $orders->whereNotIn('status',['ORDERED','SCHEDULED','CANCELLED','STORECANCELLED','COMPLETED']);
        }

        $orders = $orders->get();

       return Helper::getResponse(['data' => $orders]);
    }

    public function cancel_orders(Request $request)
    {
         StoreOrder::where('id',$request->id )->update(['status'=>'STORECANCELLED']);
         $storedispute =  new StoreOrderDispute;
         $storedispute->dispute_type='system';
         $storedispute->user_id=$request->user_id;
         $storedispute->store_id=$request->store_id;
         $storedispute->store_order_id=$request->id;
         $storedispute->dispute_name="Store Cancelled";
         $storedispute->dispute_type_comments="Store Cancelled";
         $storedispute->status="open";
         $storedispute->company_id=Auth::guard('shop')->user()->company_id;
         $storedispute->save();
         $storeorderstatus =  new StoreOrderStatus;
         $storeorderstatus->store_order_id=$request->id;
         $storeorderstatus->status='CANCELLED';
         $storeorderstatus->company_id=Auth::guard('shop')->user()->company_id;
         $storeorderstatus->save();
         //Send message to socket
        $requestData = ['type' => 'ORDER', 'room' => 'room_'.Auth::guard('shop')->user()->company_id, 'id' => $request->id,'shop'=> $request->store_id, 'user' => $request->user_id ];
            app('redis')->publish('newRequest', json_encode( $requestData ));

         return Helper::getResponse(['status' => 200, 'message' =>'Cancelled Succesfully']);
    }

    public function picked_up(Request $request)
    {
         StoreOrder::where('id',$request->id )->update(['status'=>'COMPLETED','paid'=>1,'provider_rated'=>1]);
         $storeorderstatus =  new StoreOrderStatus;
         $storeorderstatus->store_order_id=$request->id;
         $storeorderstatus->status='COMPLETED';
         $storeorderstatus->company_id=Auth::guard('shop')->user()->company_id;
         $storeorderstatus->save();
         
         //Send message to socket
        $requestData = ['type' => 'ORDER', 'room' => 'room_'.Auth::guard('shop')->user()->company_id, 'id' => $request->id,'shop'=> $request->store_id, 'user' => $request->user_id ];
            app('redis')->publish('newRequest', json_encode( $requestData ));

         return Helper::getResponse(['status' => 200, 'message' =>'Picked Succesfully']);
    }

     public function accept_orders(Request $request)
    {
       try {
         $storeorder =StoreOrder::findorfail($request->store_order_id);
         $user=User::findorfail($storeorder->user_id);
         $timezone= $storeorder->timezone;
         if($request->has('cooking_time')){
           $storeorder->order_ready_time=$request->cooking_time;
         }

         if($request->has('delivery_date')){
             
            $delivery_date = (Carbon::createFromFormat('Y-m-d H:i:s', (Carbon::parse($request->delivery_date)->format('Y-m-d H:i:s')), $timezone))->setTimezone('UTC');
            $storeorder->delivery_date=$delivery_date;
         }
          $storeorder->status='RECEIVED';
          $storeorder->save();

         $storeorderstatus =  new StoreOrderStatus;
         $storeorderstatus->store_order_id=$request->store_order_id;
         $storeorderstatus->status='RECEIVED';
         $storeorderstatus->company_id=Auth::guard('shop')->user()->company_id;
         $storeorderstatus->save();
         $requestData = ['type' => 'ORDER', 'room' => 'room_'.Auth::guard('shop')->user()->company_id, 'id' => $request->store_order_id,'shop'=> Auth::guard('shop')->user()->id, 'user' => $request->user_id ];
            app('redis')->publish('newRequest', json_encode( $requestData ));
           return Helper::getResponse(['status' => 200, 'message' =>'Accepted  Succesfully']);
         }  catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    

    }

    

    public function StoreAutoAssign()
    {
      try {
            
          $storeorder=StoreOrder::where('status','RECEIVED')
          // ->where('order_type','<>','TAKEAWAY')
          ->get();

          if(count($storeorder)>0){
             $cur_date=\Carbon\Carbon::now();
            
            foreach($storeorder as $k=>$v){
               
             if(empty($v->delivery_date)){
                Log::info('food');
                  if($v->order_type == 'TAKEAWAY'){
                      $takeaway_time = round($v->order_ready_time/1.5);
                      $receive_date_st = StoreOrderStatus::where('store_order_id',$v->id)->where('status','RECEIVED')->orderBy('id','DESC')->first();
                      $receive_date = \Carbon\Carbon::parse($receive_date_st->created_at)->addMinutes($takeaway_time); 
                      if($receive_date<=$cur_date){                        
                          StoreOrder::where('id',$v->id )->update(['order_ready_status'=>1]);
                      }else{
                          Log::info('Food Orders Time Not Left');                       
                      }
                  }else{
                      $half_order_time = round($v->order_ready_time/2);
                      $receive_date_st = StoreOrderStatus::where('store_order_id',$v->id)->where('status','RECEIVED')->orderBy('id','DESC')->first();
                      $receive_date = \Carbon\Carbon::parse($receive_date_st->created_at)->addMinutes($half_order_time); 
                      if($receive_date<=$cur_date){
                        //  manual and auto
                        if($v->request_type=='MANUAL'){

                          $userrequest=UserRequest::where('request_id',$v->id)->where('admin_service_id',$v->admin_service_id)->where('company_id',$v->company_id)->first();
                          $userrequest->status = 'SEARCHING';
                          $userrequest->save();
                          $v->status = 'SEARCHING';
                          $v->save();
                          $storeorderstatus =  new StoreOrderStatus;
                          $storeorderstatus->store_order_id = $v->id;
                          $storeorderstatus->status = 'SEARCHING';
                          $storeorderstatus->company_id = $v->company_id;
                          $storeorderstatus->save();
                        }else{
                         $data=$this->showproviders($v->id,$v->store_id);
                         Log::info($data);
                       }
                      }else{
                          Log::info('Food Orders Time Not Left');                       
                      }
                  }
              } else {
                   Log::info('grocery');
                   $delivery_date=\Carbon\Carbon::parse($v->delivery_date)->subMinutes(15);
                   if($delivery_date<=$cur_date){
                        $data=$this->showproviders($v->id,$v->store_id);
                        Log::info($data);
                    }else{
                        Log::info('Grocery Orders Time Not Left');
                    }

              }    

            }
             Log::info('Orders Search');
            // return Helper::getResponse(['status' => 200, 'message' =>'Orders Search']);
           } else {
            Log::info('No Order');
            // return Helper::getResponse(['status' => 200, 'message' =>'No Order']);
          }
       }
       catch (\Throwable $e) {
        Log::info($e->getMessage());
        // return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
      }

    }

    public function showproviders($store_order_id,$store_id)
    {
       try {
             
            $service = AdminService::where('admin_service_name', 'ORDER')->first(); 
            $storeorder=StoreOrder::findorfail($store_order_id);
            $store=Store::findorfail($store_id);
            $user = User::findorfail($storeorder->user_id);
            $userrequest=UserRequest::where('request_id',$store_order_id)->where('admin_service_id',$service->id)->where('company_id',$store->company_id)->first();

            $setting = Setting::where('company_id', $store->company_id)->first();

            $storeorderstatus =  new StoreOrderStatus;
            $settings = json_decode(json_encode($setting->settings_data));
            $siteConfig = $settings->site;
            $orderConfig = $settings->order;
            $distance = isset($orderConfig->store_search_radius) ? $orderConfig->store_search_radius : 100;
            $latitude = $store->latitude;
            $longitude = $store->longitude;
            $Providers = Provider::with('service');
            $Providers->select(DB::Raw("(6371 * acos( cos( radians('$latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(latitude) ) ) ) AS distance"),'id');
            $Providers->where('status', 'APPROVED');
            $Providers->where('is_online', 1);
            $Providers->where('is_assigned', 0);
            $Providers->whereRaw("(6371 * acos( cos( radians('$latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(latitude) ) ) ) <= $distance");
            $Providers->whereHas('service', function($query) use ( $service,$store) {
                $query->where('admin_service_id', $service->id);
                $query->where('category_id', $store->store_type_id);
            });
            $Providers->orderBy('distance','asc');
            $Providers->where('city_id', $user->city_id);
            $Providers->where('wallet_balance' ,'>=',$siteConfig->provider_negative_balance);
            $Providers = $Providers->get(); 

              if(count($Providers) > 0 ){
              foreach ($Providers as $Provider) {

                /*$unwantedRequests = RequestFilter::select('id')->whereHas('accepted_request')->where('provider_id', $Provider->id)->get();

                foreach ($unwantedRequests as $unwantedRequest) {
                    $unwantedRequest->delete();
                }*/

                $Filter = new RequestFilter;
                $Filter->admin_service_id = $service->id;
                $Filter->request_id = $userrequest->id;
                $Filter->provider_id = $Provider->id; 
                $Filter->company_id =$store->company_id;
                $Filter->save();
                
            }
                $storeorder->status = 'SEARCHING'; 
                $storeorder->assigned_at = Carbon::now();

                $storeorder->save();
                $storeorderstatus->store_order_id=$store_order_id;
                $storeorderstatus->status='SEARCHING';
                $storeorderstatus->company_id=$store->company_id;
                $storeorderstatus->save();
                $store_details=json_encode(StoreOrder::with('invoice')->where('id',$store_order_id)->first());
                UserRequest::where('request_id',$store_order_id)->where('admin_service_id',$service->id)->where('company_id',$store->company_id)->update(['status'=>'SEARCHING','request_data'=>$store_details]);
            } else {

             $storedisputedata=StoreOrderDispute::where('store_order_id',$store_order_id)->where('dispute_name','Provider Not Available')->get();
             if(count($storedisputedata) ==0){
             $storedispute =  new StoreOrderDispute;
             $storedispute->dispute_type='system';
             $storedispute->user_id=$storeorder->user_id;
             $storedispute->store_id=$storeorder->store_id;
             $storedispute->store_order_id=$storeorder->id;
             $storedispute->dispute_name="Provider Not Available";
             $storedispute->dispute_type_comments="Provider Not Available";
             $storedispute->status="open";
             $storedispute->company_id=$store->company_id;
             $storedispute->save();
             }

          }
          $requestData = ['type' => 'ORDER', 'room' => 'room_'.$store->company_id, 'id' => $store_order_id, 'city' => ($setting->demo_mode == 0) ? $storeorder->city_id : 0, 'user' => $storeorder->user_id,'shop'=> $store_id ];
          app('redis')->publish('newRequest', json_encode( $requestData ));
            
          return $storeorder;
         
       }
       catch (\Throwable $e) {
            Log::info($e->getMessage());
            // return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
      }    


    }

    public function wallet(Request $request)
    {
        $datum = StoreWallet::with('storesDetails')->where('company_id', Auth::guard('shop')->user()->company_id)->where('store_id',Auth::guard('shop')->user()->id);
        if ($request->has('search_text') && $request->search_text != null)
        {
            $datum->Search($request->search_text);
        }
        if ($request->has('order_by'))
        {
            $datum->orderby($request->order_by, $request->order_direction);
        }
        $data = $datum->paginate(10); 
        return Helper::getResponse(['data' => $data]);
    }

    public function total_orders()
    {
       try{

           $storeorder = StoreOrder::where('store_id',Auth::guard('shop')->user()->id);

            $data['received_data'] = $storeorder->count();
            $data['delivered_data'] = $storeorder->where('status','COMPLETED')->count();
            $data['recent_data']=$storeorder->with('user','orderInvoice','provider')->orderby('id','DESC')->limit(10)->get();
          
          $completed= StoreOrder::where('store_id',Auth::guard('shop')->user()->id)->where('status','COMPLETED')->where('company_id',Auth::guard('shop')->user()->company_id)->get(['id', 'created_at','timezone'])->groupBy(function($date) {
            return Carbon::parse($date->created_at)->format('m');
          });
          $cancelled= StoreOrder::where('store_id',Auth::guard('shop')->user()->id)->where('status','CANCELLED')->where('company_id',Auth::guard('shop')->user()->company_id)->get(['id', 'created_at','timezone'])->groupBy(function($date) {
            return Carbon::parse($date->created_at)->format('m');
          });


        $total_earnings = DB::connection('order')->table('store_order_invoices')->select(DB::raw('sum(total_amount - delivery_amount - commision_amount) as total_amount'))->where('store_id',Auth::guard('shop')->user()->id)->where('company_id',Auth::guard('shop')->user()->company_id);
        $data['total_earnings']=$total_earnings->first();
        $data['today_earnings']=$total_earnings->whereDate('created_at','=',Carbon::today())->first();
        
          $month=array('01','02','03','04','05','06','07','08','09','10','11','12');
           
          foreach($month as $k => $v){
              if(empty($completed[$v])){
                $complete[]=0;
              }else{
                $complete[]=count($completed[$v]);
              }

              if(empty($cancelled[$v])){
                $cancel[]=0;
              }else{
                $cancel[]=count($cancelled[$v]);
              }
          }

          $data['cancelled_data']=$cancel;
          $data['completed_data']=$complete;
          $data['max']=max($complete);
          $data['currency']=Auth::guard('shop')->user()->currency_symbol;

          if(max($complete) < max($cancel)){
            $data['max']=max($cancel);
          }
          
          
          return Helper::getResponse(['status' => 200,'data'=> $data]);

         }
         catch (Exception $e) {
            return Helper::getResponse(['status' => 500, 'message' => trans('api.something_went_wrong'), 'error' => $e->getMessage() ]);
        }
       
        return Helper::getResponse(['data' => $data]);
    }



    public function StoreTimeOut()
    {
      try {
            
          $storeorder=StoreOrder::where('status','ORDERED')->get();
          
          if(count($storeorder)>0){

            $cur_date=\Carbon\Carbon::now();
            foreach($storeorder as $k=>$v){
              $setting = Setting::where('company_id', $v->company_id)->first();

              $settings = json_decode(json_encode($setting->settings_data));
              $siteConfig = $settings->site;
              $orderConfig = $settings->order;
     
              $store_accept_time  = round($orderConfig->store_response_time);

              $receive_date_st = StoreOrderStatus::where('store_order_id',$v->id)->where('status','ORDERED')->orderBy('id','DESC')->first();
              $receive_date = \Carbon\Carbon::parse($receive_date_st->updated_at)->addSeconds($store_accept_time); 
              if($receive_date<=$cur_date){
                //  manual and auto
                 $storedispute =  new StoreOrderDispute;
                 $storedispute->dispute_type='system';
                 $storedispute->user_id=$v->user_id;
                 $storedispute->store_id=$v->store_id;
                 $storedispute->store_order_id=$v->id;
                 $storedispute->dispute_name="Store No Response";
                 $storedispute->dispute_type_comments="Store No Response";
                 $storedispute->status="open";
                 $storedispute->company_id=$v->company_id;
                 $storedispute->save();

                 $v->status = 'STORECANCELLED';
                 $v->save();

                 $requestData = ['type' => 'ORDER', 'room' => 'room_'.$v->company_id, 'id' => $v->id,'shop'=> $v->store_id, 'user' => $v->user_id ];
                  app('redis')->publish('newRequest', json_encode( $requestData ));
              }else{
                  Log::info('Food Orders Time Not Left');                       
              }
            }
              

            
            // return Helper::getResponse(['status' => 200, 'message' =>'Orders Search']);
           } else {
            Log::info('No Order');
            // return Helper::getResponse(['status' => 200, 'message' =>'No Order']);
          }
       }
       catch (\Throwable $e) {
        Log::info($e->getMessage());
        // return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
      }

    }


}
