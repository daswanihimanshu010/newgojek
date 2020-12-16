<?php

namespace App\Http\Controllers\V1\Order\User;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Models\Order\Store;
use App\Models\Order\Cuisine;
use App\Models\Common\UserAddress;
use App\Models\Common\RequestFilter;
use App\Models\Order\StoreItemAddon;
use App\Models\Order\StoreItem;
use App\Models\Order\StoreCart;
use App\Models\Common\Rating;
use App\Models\Common\User;
use App\Models\Common\State;
use App\Models\Order\StoreCityPrice;
use App\Models\Order\StoreOrderDispute;
use Auth;
use DB;
use Carbon\Carbon;
use App\Models\Common\Setting;
use App\Models\Order\StoreCartItemAddon; 
use App\Models\Common\Promocode;
use App\Models\Order\StoreOrder;
use App\Models\Order\StoreOrderInvoice;
use App\Models\Order\StoreOrderStatus;
use App\Models\Common\AdminService;
use App\Models\Common\UserRequest;
use App\Models\Common\PaymentLog;
use App\Services\PaymentGateway;
use App\Models\Common\Card;
use App\Services\Transactions;
use App\Services\SendPushNotification;
class HomeController extends Controller
{
	//Store Type
    public function store_list(Request $request,$id)
    {
		$store_list_all = Store::with('categories','storetype','StoreCusinie','StoreCusinie.cuisine')->where('company_id',Auth::guard('user')->user()->company_id)->where('store_type_id',$id)->select('id','store_type_id','company_id','store_name','store_location','latitude','longitude','picture','offer_min_amount','estimated_delivery_time','free_delivery','is_veg','rating','offer_percent');

		if($request->has('filter') && $request->filter!=''){
			$store_list_all->whereHas('StoreCusinie',function($q) use ($request){
				$q->whereIn('cuisines_id',[$request->filter]);

			});
		}
		if($request->has('qfilter') && $request->qfilter!=''){
			if($request->qfilter=='non-veg'){
				$store_list_all->where('is_veg','Non Veg');
			}
			if($request->qfilter=='pure-veg'){
				$store_list_all->where('is_veg','Pure Veg');
			}
			if($request->qfilter=='freedelivery'){
				$store_list_all->where('free_delivery','1');
			}
			
		}
		if($request->has('latitude') && $request->has('latitude')!='' && $request->has('longitude') && $request->has('longitude')!='')
        {
            $longitude = $request->longitude;
            $latitude = $request->latitude;
            $settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
            $setting_order = $settings->order;
            $distance = $setting_order->search_radius;
            // config('constants.store_search_radius', '10');
            if($distance>0){
                $store_list_all->select('*',\DB::raw("(6371 * acos( cos( radians('$latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(latitude) ) ) ) AS distance"))

                    ->whereRaw("(6371 * acos( cos( radians('$latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(latitude) ) ) ) <= $distance");
            }
        }

        $store_list_all->where('status',1);
        $store_list = $store_list_all->get();
        $store_list->map(function($shop) {
            if($shop->StoreCusinie->count()>0){
                foreach($shop->StoreCusinie as $cusine){
                    $cusines_list [] = $cusine->cuisine->name;
                }
            }else{
               $cusines_list=[]; 
            }
            $cuisinelist = implode($cusines_list,',');
            $shop->cusine_list = $cuisinelist;
            $shop->shopstatus = $this->shoptime($shop->id);
            return $shop;
        });
        return Helper::getResponse(['data' => $store_list]);
	}
	//Service Sub Category
	public function cusine_list(Request $request,$id) {
		$cusine_list = Cuisine::where('company_id',Auth::guard('user')->user()->company_id)->where('store_type_id',$id)
									 ->get();
        return Helper::getResponse(['data' => $cusine_list]);
	}
	//store details 
	public function store_details(Request $request,$id){

        $store_details = Store::with(['categories','storetype',
        'storecart' =>function($query) use ($request){
            $query->where('user_id',Auth::guard('user')->user()->id);
        },'products'=>function($query) use ($request){
			if($request->has('filter') && $request->filter!=''){
					$query->where('store_category_id',$request->filter);
			}
			if($request->has('search') && $request->search!=''){
				$query->where('item_name','LIKE', '%' . $request->search . '%' );
			}
			if($request->has('qfilter') && $request->qfilter!=''){
				if($request->qfilter=='non-veg'){
					$query->where('is_veg','Non Veg');
				}
				if($request->qfilter=='pure-veg'){
					$query->where('is_veg','Pure Veg');
				}
				if($request->qfilter=='discount'){
					$query->where('item_discount','<>','');
				}
			}
        },'products.itemsaddon','products.itemsaddon.addon'
        ,'products.itemcart' =>function($query) use ($request){
            $query->where('user_id',Auth::guard('user')->user()->id);
        }])->where('company_id',Auth::guard('user')->user()->company_id);
		if($request->has('latitude') && $request->has('latitude')!='' && $request->has('longitude') && $request->has('longitude')!='')
        {
            $longitude = $request->longitude;
            $latitude = $request->latitude;
            $settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
            $setting_order = $settings->order;
            $distance = $setting_order->search_radius;
            // config('constants.store_search_radius', '10');
                $store_details->select('id','store_type_id','company_id','store_name','store_location','latitude','longitude','picture','offer_min_amount','estimated_delivery_time','free_delivery','is_veg','rating','offer_percent',\DB::raw("(6371 * acos( cos( radians('$latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(latitude) ) ) ) AS distance"))

                    ->whereRaw("(6371 * acos( cos( radians('$latitude') ) * cos( radians(latitude) ) * cos( radians(longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(latitude) ) ) ) <= $distance");
        }else{
        	$store_details->select('id','store_type_id','company_id','store_name','store_location','latitude','longitude','picture','offer_min_amount','estimated_delivery_time','free_delivery','is_veg','rating','offer_percent');
        }
		$store_detail = $store_details->find($id);
			$store_detail->products->map(function($products) {
				$products->itemsaddon->filter(function($addon) {
		        	$addon->addon_name = $addon->addon->addon_name;;
		        	unset($addon->addon);
		        	return $addon;
		    	});
		    	return $products;
            });
            $totalcartprice =0;
        $store_detail->totalstorecart = count($store_detail->storecart);
        foreach($store_detail->storecart as $cart){
            $totalcartprice = $totalcartprice + $cart->total_item_price;
        }
        
		unset($store_detail->storecart);
		if(!empty($store_detail)){
			$store_detail->shopstatus = $this->shoptime($id);
		}
        $store_detail->usercart = $this->totalusercart();
        $store_detail->totalcartprice = $totalcartprice;
		return Helper::getResponse(['data' => $store_detail]);
	}

	public  function shoptime($id){
			$Shop = Store::find($id);
        $day_short = strtoupper(\Carbon\Carbon::now()->format('D'));

            if($shop_timing = $Shop->timings->where('store_day','ALL')
                        ->pluck('store_start_time','store_end_time')->toArray()){
            }else{
                $shop_timing = $Shop->timings->where('store_day',$day_short)
                    ->pluck('store_start_time','store_end_time')->toArray();
            }    
            if(!empty($shop_timing)){
                $key = key($shop_timing);
                $current_time = \Carbon\Carbon::now(); 
                $start_time = \Carbon\Carbon::parse($key); 
                $end_time = \Carbon\Carbon::parse($shop_timing[$key]);
                if($current_time->between($start_time,$end_time)){
                    return $timeout_class = 'OPEN';
                }else{
                    return $timeout_class = 'CLOSED'; 
                }
            }else{
                return 'CLOSED';
            }

    }

    public function useraddress(Request $request){
        $user_address = UserAddress::where('user_id',Auth::guard('user')->user()->id)
        ->where('company_id',Auth::guard('user')->user()->company_id)
        ->select('id','user_id','company_id','address_type','map_address','latitude','longitude','flat_no','street','title')->get();
        return Helper::getResponse(['data' => $user_address]);
    }
    public function show_addons(Request $request,$id){
    	$item_addons = StoreItem::with(['itemsaddon','itemsaddon.addon','itemcartaddon'])->where('company_id',Auth::guard('user')->user()->company_id)->select('id','item_name','item_price')->find($id);
            $itemcartaddon = $item_addons->itemcartaddon->pluck('store_item_addons_id','store_item_addons_id')->toArray();
            unset($item_addons->itemcartaddon);
            $item_addons->itemcartaddon = $itemcartaddon;
		    $item_addons->itemsaddon->map(function($da) {
		        $da->addon_name = $da->addon->addon_name;;
		        unset($da->addon);
		        return $da;
			});
        return Helper::getResponse(['data' => $item_addons]);
    }



    public function addcart(Request $request){
        $this->validate($request, [
			'item_id'    => 'required',
			'qty' => 'required'
		]);
        //return $request->all();
        $action = isset($request->action)?$request->action:'';
        $settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
        $orderConfig = $settings->order;
        $maxitemsAllowed = isset($orderConfig->max_items_in_order)?$orderConfig->max_items_in_order:'';
        $cart = StoreCart::where('user_id',Auth::guard('user')->user()->id)->first();
        $Item = StoreItem::find($request->item_id);
        if(!empty($cart)){
	    	if($Item->store_id!=$cart->store_id){
	    		StoreCart::where('user_id',Auth::guard('user')->user()->id)->delete();
	    		$cart = [];
	    	}else{
	    		$cart = StoreCart::where('user_id',Auth::guard('user')->user()->id)->where('store_item_id',$request->item_id)->first();
	    	}
    	}
    	if(empty($cart)){
    		$newcart = [];
    		$cart = new StoreCart();
            $cart->quantity = $request->qty;
    	}else{
    		$newcart = $cart;
            $cart->quantity = $request->qty;
        }       
        
        $cart->user_id = Auth::guard('user')->user()->id;
        $cart->store_id = $Item->store_id;
        $cart->store_item_id = $request->item_id;
        $cart->item_price = $Item->item_price;
        
        $cart->company_id = Auth::guard('user')->user()->company_id;
        $cart->note = $request->note;
        $cart->total_item_price = ($request->qty)*($Item->item_price);
        $cart->save();
        $tot_item_addon_price = 0;
        $cart = StoreCart::with('cartaddon')->find($cart->id);
        if( $action != '' && $action == 'addnew'){
            StoreCartItemAddon::where('store_cart_id',$cart->id)->delete();
            if($request->has('addons') && $request->addons !=''){
                $alladdons = @explode(',',$request->addons);
                $addons = StoreItemAddon::whereIn('id',$alladdons)->pluck('price','id')->toArray();                            
                foreach($addons as $key => $item){ 
                    if(in_array($key, $alladdons)){
                        $cartaddon = new StoreCartItemAddon();
                        $cartaddon->store_cart_item_id = $cart->store_item_id;
                        $cartaddon->store_item_addons_id = $key;
                        $cartaddon->store_cart_id = $cart->id;
                        $cartaddon->addon_price = $item;
                        $cartaddon->company_id = Auth::guard('user')->user()->company_id;
                        $cartaddon->save();
                        $tot_item_addon_price += $item;
                    }
                }	
            }
        }
        $cart->tot_addon_price = $tot_item_addon_price;        

        $cart->total_item_price += ($request->qty*$cart->tot_addon_price);
        $cart->save();
        return $this->viewcart($request);
    }

    public function viewcart(Request $request){
    	
        try{
        $CartItems  = StoreCart::with('product','store','store.storetype','store.StoreCusinie','store.StoreCusinie.cuisine','cartaddon','cartaddon.addon.addon')
        ->where('company_id',Auth::guard('user')->user()->company_id)
        ->where('user_id',Auth::guard('user')->user()->id)->get();

        	$tot_price = 0;
        	$discount = 0;
        	$tax  =0; 
        	$promocode_amount = 0; 
        	$total_net = 0; 
        	$total_wallet_balance = 0;
        	$payable = 0;
            $discount_promo = 0;
            $cusines_list = [];
            if(!$CartItems->isEmpty()) {
                if($CartItems[0]->store->StoreCusinie->count()>0){
                    foreach($CartItems[0]->store->StoreCusinie as $cusine){
                        $cusines_list [] = $cusine->cuisine->name;
                    }
                }
                $store_type_id=$CartItems[0]->store->store_type_id;
                $city_id=$CartItems[0]->store->city_id;
        		$cityprice=StoreCityPrice::where('store_type_id',$store_type_id)->where('company_id',Auth::guard('user')->user()->company_id)
                //->where('city_id',$city_id)
                ->first();
                foreach($CartItems as $Product){
                    $tot_qty = $Product->quantity;
                    //$Product->quantity. '--' .$Product->product->item_price;
                    $tot_price += $Product->quantity * $Product->product->item_price;
                    $tot_price_addons = 0;
                    if(count($Product->cartaddon)>0){
                        foreach($Product->cartaddon as $Cartaddon){

                           $tot_price_addons +=$Cartaddon->addon_price; 
                        }
                    }
                    $tot_price += $tot_qty*$tot_price_addons; 
                    
                }
                $tot_price = $tot_price;
                $net = $tot_price;
                if($Product->store->offer_percent){
                    if($tot_price > $Product->store->offer_min_amount){
                       //$discount = roundPrice(($tot_price*($Order->shop->offer_percent/100)));
                       $discount = ($tot_price*($Product->store->offer_percent/100));
                       //if()
                       $net = $tot_price - $discount;
                    }
                }
                $total_wallet_balance = 0;
                $store_tax = ($net*$Product->store->store_gst/100);
                $store_package_charge = $Product->store->store_packing_charges;
                if($Product->store->free_delivery==1){
                	$free_delivery = 0;
                }else{
                    if($cityprice){
                	   $free_delivery = $cityprice->delivery_charge;
                    }else{
                        $free_delivery = 0;
                    }
            	}
            	$total_net = ($net+$store_tax+$free_delivery+$store_package_charge);

                $promocode_id = 0;
                $discount_promo = 0;
                if($request->has('promocode_id') && $request->promocode_id !='') { 
                        $find_promo = Promocode::where('id',$request->promocode_id)->first();
                        if($find_promo != null){
                            $promocode_id = $find_promo->id;
                            $my_promo_discount = number_format($total_net*($find_promo->percentage/100),2,'.','');
                            if($my_promo_discount>$find_promo->max_amount){
                                $discount_promo = number_format($find_promo->max_amount,2,'.','');;
                                $total_net = $total_net - $find_promo->max_amount;
                            }else{
                                $discount_promo = number_format($my_promo_discount,2,'.','');;
                                $total_net = $total_net - $my_promo_discount;
                            }
                        }
                }
                $total_net = $payable = $total_net;
                if($request->wallet){
                    if(Auth::guard('user')->user()->wallet_balance > $total_net){
                        $total_wallet_balance_left = Auth::guard('user')->user()->wallet_balance - $total_net;
                        
                        $total_wallet_balance = $total_net;
                        $payable = 0;
                        
                    }else{ 
                        //$total_net = $total_net - $request->user()->wallet_balance;
                        $total_wallet_balance = Auth::guard('user')->user()->wallet_balance;
                        if($total_wallet_balance >0){
                            $payable = ($total_net - $total_wallet_balance);
                        }
                    }
                }

                //print($CartItems);exit;
                $CartItems->map(function($data) {
                   if(count($data->cartaddon)>0){
                    	$data->cartaddon->filter(function($da) {
                         $da->addon_name = $da->addon->addon->addon_name;
    			        unset($da->addon);
    			        return $da;
    			    	});
                    }
			    	return $data;
				});

                $Cart = [
                'delivery_charges' => $free_delivery,
                'delivery_free_minimum' => 0,
                'tax_percentage' => 0,
                'carts' => $CartItems,
                'total_price' => round($tot_price,2),
                'shop_discount' => round($discount,2),
                'store_type' => $CartItems[0]->store->storetype->category,
                //'tax' => round($tax,2),
                'promocode_id' => $promocode_id,
                'promocode_amount' => round($discount_promo,2),
                'net' => round($total_net,2),
                'wallet_balance' => round($total_wallet_balance,2),
                'payable' => round($payable,2),
                'total_cart' => count($CartItems),
                'shop_gst' => $CartItems[0]->store->store_gst,
                'shop_gst_amount' => round($store_tax,2),
                'shop_package_charge' =>  $store_package_charge,
                'store_id' => $CartItems[0]->store->id,
                'store_commision_per' => $CartItems[0]->store->commission,
                'shop_cusines' => implode($cusines_list,','),
                'rating' => $CartItems[0]->store->rating,
                'user_wallet_balance' => Auth::guard('user')->user()->wallet_balance,
                'user_currency' => Auth::guard('user')->user()->currency_symbol,
            ];
        }else{

            $Cart = [
                'delivery_charges' => 0,
                'delivery_free_minimum' => 0,
                'tax_percentage' => 0,
                'carts' => [],
                'total_price' => round($tot_price,2),
                'shop_discount' => round($discount,2),
                'store_type' => '',
                //'tax' => round($tax,2),
                'promocode_amount' => round($promocode_amount,2),
                'net' => round($total_net,2),
                'wallet_balance' => round($total_wallet_balance,2),
                'payable' => round($payable,2),
                'total_cart' => count($CartItems),
                'shop_gst' => 0,
                'shop_gst_amount' => 0.00,
                'shop_package_charge' =>  0,
                'store_id' => 0,
                'store_commision_per' => 0,
                'total_cart' => count($CartItems),
                'shop_cusines' => '',
                'rating' => '',
                'user_wallet_balance' => Auth::guard('user')->user()->wallet_balance,
                'user_currency' => Auth::guard('user')->user()->currency_symbol,
                ];

        }

        if($request->has('user_address_id') && $request->user_address_id !=''){
            return $Cart;
        }

        return Helper::getResponse(['data' => $Cart]);
        }catch(ModelNotFoundException $e){
			return Helper::getResponse(['status' => 500, 'message' => trans('api.provider.provider_not_found'), 'error' => trans('api.provider.provider_not_found') ]);
		} catch (Exception $e) {
			return Helper::getResponse(['status' => 500, 'message' => trans('api.provider.provider_not_found'), 'error' => trans('api.provider.provider_not_found') ]);
		}
    }

    public function removecart(Request $request){
    	$this->validate($request, [
			'cart_id'    => 'required'
		]);
    	$cart = StoreCart::find($request->cart_id)->delete();
        $cart_addon = StoreCartItemAddon::where('store_cart_id',$request->cart_id)->delete();
        return $this->viewcart($request);
    }

    public function totalusercart(){

    	$CartItems  = StoreCart::with('product','store')->where('company_id',Auth::guard('user')->user()->company_id)->where('user_id',Auth::guard('user')->user()->id)->count();
    	return $CartItems;
    }


    public function promocodelist(Request $request){
        
        $Promocodes = Promocode::with('promousage')
        ->where('status','ADDED')
        ->where('company_id', Auth::guard('user')->user()->company_id)
        ->where('expiration','>=',date("Y-m-d H:i"))
        ->whereDoesntHave('promousage', function($query) {
                    $query->where('user_id',Auth::guard('user')->user()->id);
                })
        ->get();
        return Helper::getResponse(['data' => $Promocodes]);
    }

    public function checkout(Request $request){
        $messages = [
            'user_address_id.required' => trans('validation.custom.user_address_id_required')
        ];
        $this->validate($request, [
            'payment_mode'    => 'required',
            'user_address_id' => 'required|exists:user_addresses,id,deleted_at,NULL',
        ],$messages);
        $cart =  $this->viewcart($request);
       
        if(empty($cart['carts'])){
            return Helper::getResponse(['status' => 404,'message' =>'user cart is empty', 'error' => 'user cart is empty']);
        }

        $store_details = Store::with('storetype')->select('id','picture','contact_number','store_type_id','latitude','longitude','store_location','store_name','currency_symbol')->find($cart['store_id']);
        $address_details = UserAddress::select('id','latitude','longitude','map_address','flat_no','street')->find($request->user_address_id);
        $payment_id = '';
        $paymentMode = $request->payment_mode;
        if($request->payment_mode=='CARD'){
            $payable = $cart['payable'];
            if($payable!=0){
                $payment_id = $this->orderpayment($payable,$request);
                if($payment_id=='failed'){

                    return Helper::getResponse(['message' => trans('Transaction Failed')]);
                }  
            }
        }
        $setting = Setting::where('company_id', Auth::guard('user')->user()->company_id)->first();
        //return $request->all();
        $order = new StoreOrder ();
        $settings = json_decode(json_encode($setting->settings_data));
        $details = "https://maps.googleapis.com/maps/api/directions/json?origin=".$address_details->latitude.",".$address_details->longitude."&destination=".$store_details->latitude.",".$store_details->longitude."&mode=driving&key=".$settings->site->browser_key;
        $json = Helper::curl($details);
        $details = json_decode($json, TRUE);
        $route_key = (count($details['routes']) > 0) ? $details['routes'][0]['overview_polyline']['points'] : '';
        $order->description = isset($request->description)?$request->description:'';
        $serviceConfig = $settings->order;
        $bookingprefix = $serviceConfig->booking_prefix;
        $order->store_order_invoice_id = $bookingprefix.time().rand('0','999');
        if(!empty($payment_id)){
          $order->paid=1;
        }
        $order->user_id = Auth::guard('user')->user()->id;
        $order->user_address_id = $request->user_address_id;
        $order->assigned_at = (Carbon::now())->toDateTimeString();
        $order->order_type = $request->order_type;
        if($serviceConfig->manual_request==1){
            $order->request_type = 'MANUAL';
        }
        $order->order_otp = mt_rand(1000 , 9999);
        $order->timezone = (Auth::guard('user')->user()->state_id) ? State::find(Auth::guard('user')->user()->state_id)->timezone : '';
        $order->route_key = $route_key;
        $order->city_id = Auth::guard('user')->user()->city_id;
        $order->country_id = Auth::guard('user')->user()->country_id;
        $order->promocode_id = !empty($cart['promocode_id']) ? $cart['promocode_id']:0;
        if($request->has('delivery_date') && $request->delivery_date !=''){
            $order->delivery_date = Carbon::parse($request->delivery_date)->format('Y-m-d H:i:s');
            $order->schedule_status = 1;
        }
        $order->store_id = $cart['store_id'];
        $order->admin_service_id = 2;
        $order->order_ready_status = 0;
        $order->company_id = Auth::guard('user')->user()->company_id;
        $order->currency = Auth::guard('user')->user()->currency_symbol;
        $order->status = 'ORDERED';
        $order->delivery_address = json_encode($address_details);
        $order->pickup_address = json_encode($store_details);
        $order->save();
        if($order->id){
            $store_commision_amount = ($cart['net']*($cart['store_commision_per']/100));
            $orderinvoice = new StoreOrderInvoice ();
            $orderinvoice->store_order_id = $order->id;
            $orderinvoice->store_id = $order->store_id;
            $orderinvoice->payment_mode = $request->payment_mode;
            $orderinvoice->payment_id = $payment_id;
            $orderinvoice->company_id = Auth::guard('user')->user()->company_id;
            $orderinvoice->gross = $cart['total_price'];
            $orderinvoice->net = $cart['net'];
            $orderinvoice->discount = $cart['shop_discount'];
            $orderinvoice->promocode_id = $cart['promocode_id'];
            $orderinvoice->promocode_amount = $cart['promocode_amount'];
            $orderinvoice->wallet_amount = $cart['wallet_balance'];
            $orderinvoice->tax_per = $cart['shop_gst'];
            $orderinvoice->tax_amount = $cart['shop_gst_amount'];
            $orderinvoice->commision_per = $cart['store_commision_per'];
            $orderinvoice->commision_amount = $store_commision_amount;
            /*$orderinvoice->delivery_per = $cart['total_price'];*/
            $orderinvoice->delivery_amount = $cart['delivery_charges'];
            $orderinvoice->store_package_amount = $cart['shop_package_charge'];
            $orderinvoice->total_amount = $cart['payable'];
            $orderinvoice->cash = $cart['payable'];
            $orderinvoice->payable = $cart['payable'];
            $orderinvoice->status = 0;
            $orderinvoice->cart_details = json_encode($cart['carts']);
            $orderinvoice->save();
            $orderstatus = new StoreOrderStatus();
            $orderstatus->company_id = Auth::guard('user')->user()->company_id;
            $orderstatus->store_order_id = $order->id;
            $orderstatus->status = 'ORDERED';
            $orderstatus->save();

            //payment log update order id
            if($payment_id){
                $log = PaymentLog::where('transaction_id', $payment_id)->first();
                $log->transaction_id = $order->id;
                $log->transaction_code = $bookingprefix;
                $log->response = json_encode($order);
                $log->save();
            }
            //$User = User::find(Auth::guard('user')->user()->id);
            $Wallet = Auth::guard('user')->user()->wallet_balance;
            //$Total = 
            //
            if($cart['wallet_balance'] > 0){
                // charged wallet money push 
                // (new SendPushNotification)->ChargedWalletMoney($UserRequest->user_id,$Wallet, 'wallet');
                (new SendPushNotification)->ChargedWalletMoney(Auth::guard('user')->user()->id,Helper::currencyFormat($cart['wallet_balance'],Auth::guard('user')->user()->currency_symbol), 'wallet', 'Wallet Info');

                $transaction['amount']=$cart['wallet_balance'];
                $transaction['id']=Auth::guard('user')->user()->id;
                $transaction['transaction_id']=$order->id;
                $transaction['transaction_alias']=$order->store_order_invoice_id;
                $transaction['company_id']=Auth::guard('user')->user()->company_id;
                $transaction['transaction_msg']='order deduction';

                (new Transactions)->userCreditDebit($transaction,0);
            }
            //user request
            $user_request = new UserRequest();
            $user_request->company_id = Auth::guard('user')->user()->company_id;
            $user_request->user_id = Auth::guard('user')->user()->id;
            $user_request->request_id = $order->id;
            $user_request->request_data = json_encode(StoreOrder::with('invoice', 'store.storetype')->where('id',$order->id)->first());
            $user_request->admin_service_id = AdminService::where('admin_service_name','ORDER')->first()->id;
            $user_request->status = 'ORDERED';
            $user_request->save();

            $CartItem_ids  = StoreCart::where('company_id',Auth::guard('user')->user()->company_id)->where('user_id',Auth::guard('user')->user()->id)->pluck('id','id')->toArray();
            $CartItems  = StoreCart::where('company_id',Auth::guard('user')->user()->company_id)->where('user_id',Auth::guard('user')->user()->id)->delete();
            StoreCartItemAddon::whereIN('store_cart_id',$CartItem_ids)->delete();

            if($request->has('delivery_date') && $request->delivery_date !=''){
                // scheduling
                $schedule_status = 1;
            }else{
                //Send message to socket
                $requestData = ['type' => 'ORDER', 'room' => 'room_'.Auth::guard('user')->user()->company_id, 'id' => $order->id, 'city' => ($setting->demo_mode == 0) ? $order->store->city_id : 0, 'user' => $order->user_id ];
                app('redis')->publish('newRequest', json_encode( $requestData ));
            }


            //Send message to socket
            $requestData = ['type' => 'ORDER', 'room' => 'room_'.Auth::guard('user')->user()->company_id, 'id' => $order->id,'shop'=> $cart['store_id'], 'user' => $order->user_id ];
            app('redis')->publish('newRequest', json_encode( $requestData ));


            return  $this->orderdetails($order->id);
        }
        
    }

    public function cancelOrder(Request $request){
        $this->validate($request, [
            'id' => 'required|numeric|exists:order.store_orders,id,user_id,'.Auth::guard('user')->user()->id,
            'cancel_reason'=> 'required|max:255',
        ]);
        try{
            $orderRequest = StoreOrder::findOrFail($request->id);
            $setting = Setting::where('company_id', $orderRequest->company_id)->first();
            if($orderRequest->status == 'CANCELLED')
            {
                return Helper::getResponse(['status' => 404, 'message' => trans('api.order.ride_cancelled')]);
            } 
            if(in_array($orderRequest->status, ['ORDERED','STORECANCELLED'])) {
                $orderRequest->status = 'CANCELLED';
                $cancelreason = isset($request->cancel_reason)?$request->cancel_reason:'';
                $cancelreason_opt = isset($request->cancel_reason_opt)?$request->cancel_reason_opt:'';
                if( $cancelreason=='others')
                    $orderRequest->cancel_reason = $cancelreason_opt;
                else
                    $orderRequest->cancel_reason = $cancelreason;

                $orderRequest->cancelled_by = 'USER';
                $orderRequest->save();

                $admin_service = AdminService::where('admin_service_name','ORDER')->where('company_id', Auth::guard('user')->user()->company_id)->first();
                $user_request = UserRequest::where('admin_service_id', $admin_service->id )->where('request_id',$orderRequest->id)->first();
                RequestFilter::where('admin_service_id', $admin_service->id )->where('request_id', $user_request->id)->delete();
                if($orderRequest->status != 'SCHEDULED'){
                    if($orderRequest->provider_id != null){
                        Provider::where('id', $orderRequest->provider_id)->update(['is_assigned' => 0]);
                    }
                }
                // Send Push Notification to User
                (new SendPushNotification)->UserCancelOrder($orderRequest, 'order');
                $user_request->delete();
                //Send message to socket
                $requestData = ['type' => 'ORDER', 'room' => 'room_'.Auth::guard('user')->user()->company_id, 'id' => $orderRequest->id, 'city' => ($setting->demo_mode == 0) ? $orderRequest->city_id : 0, 'user' => $orderRequest->user_id ];
                app('redis')->publish('newRequest', json_encode( $requestData ));

                return Helper::getResponse(['message' => trans('api.order.ride_cancelled')]);
            } else {
                return Helper::getResponse(['status' => 403, 'message' => trans('api.ride.already_onride')]);
            }
        }catch (ModelNotFoundException $e) {
            return Helper::getResponse(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    public function orderdetails($id){
        $order = StoreOrder::with(['store','store.storetype','deliveryaddress','invoice','user','chat',
        'provider' => function($query){  $query->select('id', 'first_name','last_name','country_code','mobile','rating','latitude','longitude','picture' ); },
			])->find($id);;
        
            return Helper::getResponse(['data' => $order]);
    }
    //status
    public function status(Request $request)
    {
        try{

            $settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
            $siteConfig = $settings->site;
            $orderConfig = $settings->order;
            $check_status = ['CANCELLED', 'SCHEDULED'];
            $admin_service = AdminService::where('admin_service_name','ORDER')->where('company_id', Auth::guard('user')->user()->company_id)->first();

            $orderRequest = StoreOrder::OrderRequestStatusCheck(Auth::guard('user')->user()->id, $check_status, $admin_service->id)
                                        ->get()
                                        ->toArray();            
            $search_status = ['SEARCHING','SCHEDULED'];
            $Timeout = $orderConfig->provider_select_timeout ? $orderConfig->provider_select_timeout : 60 ;
            $response_time = $Timeout;

            return Helper::getResponse(['data' => [
                'response_time' => $response_time, 
                'data' => $orderRequest, 
                'sos' => isset($siteConfig->sos_number) ? $siteConfig->sos_number : '911' , 
                'emergency' => isset($siteConfig->contact_number) ? $siteConfig->contact_number : [['number' => '911']]  ]]);

        } catch (Exception $e) {
            return Helper::getResponse(['status' => 500, 'message' => trans('api.something_went_wrong'), 'error' => $e->getMessage() ]);
        }
    }
    public function orderdetailsRating(Request $request){
       $this->validate($request, [
            'request_id' => 'required|integer|exists:order.store_orders,id,user_id,'.Auth::guard('user')->user()->id,
            'shopid' => 'required|integer|exists:order.stores,id',
            'rating' => 'required|integer|in:1,2,3,4,5',
            'shoprating' => 'required|integer|in:1,2,3,4,5',
            'comment' => 'max:255',
        ],['comment.max'=>'character limit should not exceed 255']);
        $orderRequest = StoreOrder::findOrFail($request->request_id);
        if ($orderRequest->paid == 0) {
          return Helper::getResponse(['status' => 422, 'message' => trans('api.user.not_paid'), 'error' => trans('api.user.not_paid')  ]);
        }
        try{
            $userCompany = Auth::guard('user')->user()->company_id;
            $admin_service = AdminService::where('admin_service_name', 'ORDER')->where('company_id', $userCompany)->first();
            $orderRequest = StoreOrder::findOrFail($request->request_id);
            $ratingRequest = Rating::where('request_id', $orderRequest->id)
                     ->where('admin_service_id', $admin_service->id )->first();
            if($ratingRequest == null) {
                Rating::create([
                    'company_id' => $userCompany,
                    'admin_service_id' => $admin_service->id,
                    'provider_id' => $orderRequest->provider_id,
                    'user_id' => $orderRequest->user_id,
                    'request_id' => $orderRequest->id ,
                    'user_rating' => $request->rating,
                    'store_rating' => $request->shoprating,
                    'store_id' => $orderRequest->store_id,
                    'user_comment' => $request->comment,
                  ]);
            } else {
                Rating::where('id',$ratingRequest->id)->update([
                      'user_rating' => $request->rating,
                      'store_rating' => $request->shoprating,
                      'user_comment' => $request->comment,
                      'store_id' => $orderRequest->store_id,
                    ]);
            }
            $orderRequest->user_rated = 1;            
            $orderRequest->save();
  
            $average = Rating::where('provider_id', $orderRequest->provider_id)->avg('user_rating');
            $store_average = Rating::where('store_id', $orderRequest->store_id)->avg('store_rating');
            $User = User::find($orderRequest->user_id);
            $User->rating=$average;
            $User->save();

            $StoreQuery = Store::find($orderRequest->store_id);
            $StoreQuery->rating=$store_average;
            $StoreQuery->save();

            // Send Push Notification to Provider
            return Helper::getResponse(['message' => trans('api.order.service_rated') ]);
  
        } catch (Exception $e) {
            return Helper::getResponse(['status' => 500, 'message' => trans('api.order.request_not_completed'), 'error' => $e->getMessage() ]);
        }
    }

    public function tripsList(Request $request) { 
        try{
			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
			$showType = isset($request->type)?$request->type:'past';			
			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'order';
			
			if($request->has('limit')) {
				$OrderRequests = StoreOrder::select('store_orders.*',DB::raw('(select total_amount from store_order_invoices where store_orders.id=store_order_invoices.store_order_id) as total_amount'),DB::raw('(select payment_mode from store_order_invoices where store_orders.id=store_order_invoices.store_order_id) as payment_mode'))
				->with(['user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture' ); },
				'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','mobile' ); },
				])
				->OrderUserTrips(Auth::guard('user')->user()->id,$showType)
				->take($request->limit)->offset($request->offset)->get();
			} else {
				$OrderRequests = $OrderRequests = StoreOrder::with([
                'user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','currency_symbol' ); },
                'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','mobile' ); },'invoice' 

                 ])
                ->OrderUserTrips(Auth::guard('user')->user()->id,$showType);
               if($request->has('search_text') && $request->search_text != null) {
                  $OrderRequests->Search($request->search_text);
                }
               
                 $OrderRequests=$OrderRequests->orderby('id','desc')->paginate(10);
                
			}
			$jsonResponse['total_records'] = count($OrderRequests);
			if(!empty($OrderRequests)){
				$map_icon = '';
				//asset('asset/img/marker-start.png');
				foreach ($OrderRequests as $key => $value) {
					$OrderRequests[$key]->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
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
			$jsonResponse['order'] = $OrderRequests;
			return Helper::getResponse(['data' => $jsonResponse]);
		}

		catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')]);
		}

    }
    
    public function getOrderHistorydetails(Request $request,$id)
	{
		try{
			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
			$userId=Auth::guard('user')->user()->id;
			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'order';
			$OrderRequests = StoreOrder::with(array('orderInvoice'=>function($query){
				$query->select('id','store_order_id','gross','wallet_amount','total_amount','payment_mode','tax_amount','delivery_amount','promocode_amount','payable','cart_details','discount','store_package_amount');
			},'user'=>function($query){
				$query->select('id','first_name','last_name','rating','picture','mobile');
			},
            'provider'=>function($query){
                $query->select('id','first_name','last_name','rating','picture','mobile');
            }
            ))->select('id','store_order_invoice_id','user_id','provider_id','admin_service_id','company_id','pickup_address','delivery_address','created_at','status','timezone')
					->where('user_id', Auth::guard('user')->user()->id)
					->orderBy('created_at','desc')
					->where('id',$id)->first();

			if(!empty($OrderRequests)){
				$ratingQuery = Rating::select('id','user_rating','provider_rating','store_rating','user_comment','provider_comment')
				->where('admin_service_id', $OrderRequests->admin_service_id)
										->where('request_id',$OrderRequests->id)->first();
					$OrderRequests->rating = $ratingQuery;

				$map_icon_start = '';
				//asset('asset/img/marker-start.png');
				$map_icon_end = '';
				//asset('asset/img/marker-end.png');
					$OrderRequests->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
							"autoscale=1".
							"&size=600x300".
							"&maptype=terrian".
							"&format=png".
							"&visual_refresh=true".
							"&markers=icon:".$map_icon_start."%7C".$OrderRequests->s_latitude.",".$OrderRequests->s_longitude.
							"&markers=icon:".$map_icon_end."%7C".$OrderRequests->d_latitude.",".$OrderRequests->d_longitude.
							"&path=color:0x000000|weight:3|enc:".$OrderRequests->route_key.
							"&key=".$siteConfig->server_key;
					$OrderRequests->dispute = StoreOrderDispute::where(['user_id'=>$userId,'store_order_id'=>$OrderRequests->id,'dispute_type'=>'user'])->first();
			}
			$jsonResponse['order'] = $OrderRequests;
			return Helper::getResponse(['data' => $jsonResponse]);
		}
		catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')]);
		}
    }
    public function tripsUpcomingList(Request $request) {
        try{
			$settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
			$showType = isset($request->type)?$request->type:'past';			
			$siteConfig = $settings->site;
			$jsonResponse = [];
			$jsonResponse['type'] = 'order';
			
			if($request->has('limit')) {
				$OrderRequests = StoreOrder::select('store_orders.*',DB::raw('(select total_amount from store_order_invoices where store_orders.id=store_order_invoices.store_order_id) as total_amount'),DB::raw('(select payment_mode from store_order_invoices where store_orders.id=store_order_invoices.store_order_id) as payment_mode'))
				->with(['user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture' ); },
				'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','mobile' ); },
				])
				->OrderUserUpcomingTrips(Auth::guard('user')->user()->id,$showType)
				->take($request->limit)->offset($request->offset)->get();
			} else {
				$OrderRequests = StoreOrder::select('store_orders.*',DB::raw('(select total_amount from store_order_invoices where store_orders.id=store_order_invoices.store_order_id) as total_amount'),DB::raw('(select payment_mode from store_order_invoices where store_orders.id=store_order_invoices.store_order_id) as payment_mode'))
				->with(['user' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','currency_symbol' ); },
				'provider' => function($query){  $query->select('id', 'first_name', 'last_name', 'rating', 'picture','mobile'); },
				])
				->OrderUserUpcomingTrips(Auth::guard('user')->user()->id,$showType)->paginate(10);
			}
			$jsonResponse['total_records'] = count($OrderRequests);
			if(!empty($OrderRequests)){
				$map_icon = '';
				//asset('asset/img/marker-start.png');
				foreach ($OrderRequests as $key => $value) {
					$OrderRequests[$key]->static_map = "https://maps.googleapis.com/maps/api/staticmap?".
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
			$jsonResponse['order'] = $OrderRequests;
			return Helper::getResponse(['data' => $jsonResponse]);
		}

		catch (Exception $e) {
			return response()->json(['error' => trans('api.something_went_wrong')]);
		}

    }

    public function requestHistory(Request $request)
	{
		try {
            $history_status = array('CANCELLED','COMPLETED');
            $datum = StoreOrder::select('store_orders.*',DB::raw('(select total_amount from store_order_invoices where store_orders.id=store_order_invoices.store_order_id) as total_amount'),DB::raw('(select payment_mode from store_order_invoices where store_orders.id=store_order_invoices.store_order_id) as payment_mode'))
                        ->where('company_id', Auth::user()->company_id)
                     ->whereIn('status',$history_status)
                     ->with('user', 'provider');
            /*if(Auth::user()->hasRole('FLEET')) {
                $datum->where('admin_id', Auth::user()->id);  
            }*/
            if($request->has('search_text') && $request->search_text != null) {
                $datum->Search($request->search_text);
            }    
            
            $data = $datum->orderby('id','desc')->paginate(10);    
            return Helper::getResponse(['data' => $data]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }
    public function requestScheduleHistory(Request $request)
	{
		try {
            $scheduled_status = array('SCHEDULED');
            $datum = StoreOrder::select('store_orders.*',DB::raw('(select total_amount from store_order_invoices where store_orders.id=store_order_invoices.store_order_id) as total_amount'),DB::raw('(select payment_mode from store_order_invoices where store_orders.id=store_order_invoices.store_order_id) as payment_mode'))
            ->where('company_id', Auth::guard('user')->user()->company_id)
                    ->where('schedule_status',1)
                     ->with('user', 'provider');
            /*if(Auth::user()->hasRole('FLEET')) {
                $datum->where('admin_id', Auth::user()->id);  
            }*/
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
            $orderRequests = StoreOrder::select('*','created_at as joined')->where('company_id',  Auth::user()->company_id)
                     ->with('user', 'provider');
            if($request->has('country_id')) {
                $orderRequests->where('country_id',$request->country_id);
            }
            if(Auth::user()->hasRole('FLEET')) {
                $orderRequests->where('admin_id', Auth::user()->id);  
            }
            if($request->has('search_text') && $request->search_text != null) {
                $orderRequests->Search($request->search_text);
            }
    
            if($request->has('order_by')) {
                $orderRequests->orderby($request->order_by, $request->order_direction);
            }
            $type = isset($_GET['type'])?$_GET['type']:'';
            if($type == 'today'){
				$orderRequests->where('created_at', '>=', Carbon::today());
			}elseif($type == 'monthly'){
				$orderRequests->where('created_at', '>=', Carbon::now()->month);
			}elseif($type == 'yearly'){
				$orderRequests->where('created_at', '>=', Carbon::now()->year);
			}elseif ($type == 'range') {   
                if($request->has('from') &&$request->has('to')) {             
                    if($request->from == $request->to) {
                        $orderRequests->whereDate('created_at', date('Y-m-d', strtotime($request->from)));
                    } else {
                        $orderRequests->whereBetween('created_at',[Carbon::createFromFormat('Y-m-d', $request->from),Carbon::createFromFormat('Y-m-d', $request->to)]);
                    }
                }
			}else{
                // dd(5);
            }
            $cancelservices = $orderRequests;
            $orderCounts = $orderRequests->count();
            $dataval = $orderRequests->whereIn('status',$history_status)->paginate(10);
            $cancelledQuery = $cancelservices->where('status','CANCELLED')->count();
            $total_earnings = 0;
            foreach($dataval as $order){
                $order->status = $order->status == 1?'Enabled' : 'Disable';
                $orderid  = $order->id;
                $earnings = StoreOrderInvoice::select('total_amount','payment_mode')->where('store_order_id',$orderid)->where('company_id',  Auth::user()->company_id)->first();
                if($earnings != null){
                    $order->payment_mode = $earnings->payment_mode;
                    $order->earnings = $earnings->total_amount;
                    $total_earnings = $total_earnings + $earnings->total_amount;
                }else{
                    $order->earnings = 0;
                }
            }
            $data['orders'] = $dataval;
            $data['total_orders'] = $orderCounts;
            $data['revenue_value'] = $total_earnings;
            $data['cancelled_orders'] = $cancelledQuery;
            return Helper::getResponse(['data' => $data]);

        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function requestHistoryDetails($id)
	{
		try {
			$data = StoreOrder::with('user', 'provider','orderInvoice')->findOrFail($id);
            return Helper::getResponse(['data' => $data]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function search(Request $request,$id){
        $Shops = [];
        $dishes = [];
        if($request->has('q')){
            $prodname = $request->q;
            $search_type = $request->t;
            if($search_type=='store'){ 
                $shops = Store::with(['categories'])->where('company_id',Auth::guard('user')->user()->company_id)->where('store_type_id',$id)->select('id','store_type_id','company_id','store_name','store_location','latitude','longitude','picture','offer_min_amount','estimated_delivery_time','free_delivery','is_veg','rating','offer_percent')->where('store_name','LIKE', '%' . $prodname . '%')->get();
                $shops->map(function ($shop) {
                    $shop->name = $shop->store_name;
                    $shop->item_discount = $shop->offer_percent;
                    $shop->store_id = $shop->id;
                    $shop->delivery_time = $shop->estimated_delivery_time;
                    $shop['shopstatus'] = $this->shoptime($shop->id);
                    //$shop['category'] = $shop->categories()->select(\DB::raw('group_concat(store_category_name) as names'))->names;
                    $cat = [];
                    foreach($shop->categories as $item){
                        $cat[]=$item->store_category_name;
                    }
                    $shop['category'] = implode(',',$cat);
                    unset($shop->categories);
                    return $shop;
                });
                $data = $shops;
            }else{
                $data = StoreItem::with(['store', 'categories'])->where('company_id',Auth::guard('user')->user()->company_id)->where('item_name','LIKE', '%' . $prodname . '%')->select('id','store_id','store_category_id','item_name','picture','item_discount')
                ->whereHas('store',function($q) use ($id){
                    $q->where('store_type_id',$id);
                })
                ->get();
                $data->map(function ($item) {
                    $item->name = $item->item_name;
                    $item->rating = $item->store->rating;
                    $item->delivery_time = $item->store->estimated_delivery_time;
                    $item['shopstatus'] = $this->shoptime($item->store_id);
                    if($item->categories->count()>0){
                    $item['category'] = $item->categories[0]->store_category_name;
                    }else{
                    $item['category'] = null;  
                    }
                    unset($item->store);
                    unset($item->categories);
                    return $item;
                });
            }
        }

        return Helper::getResponse(['data' => $data]);
    }


    public function orderpayment($totalAmount,$request){
        $paymentMode = $request->payment_mode;
        $settings = json_decode(json_encode(Setting::where('company_id', Auth::guard('user')->user()->company_id)->first()->settings_data));
              $siteConfig = $settings->site;
              $orderConfig = $settings->order;
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
  
              $random = $orderConfig->booking_prefix.mt_rand(100000, 999999);

                switch ($paymentMode) {
                    case 'CARD':  

                    if($request->has('card_id')){

                        Card::where('user_id',Auth::guard('user')->user()->id)->update(['is_default' => 0]);
                        Card::where('card_id',$request->card_id)->update(['is_default' => 1]);
                    }
                        
                    $card = Card::where('user_id', Auth::guard('user')->user()->id)->where('is_default', 1)->first();

                    //if($card == null)  $card = Card::where('user_id', Auth::guard('user')->user()->id)->first();
                    $log = new PaymentLog();
                    $log->admin_service = 'ORDER';
                    $log->company_id = Auth::guard('user')->user()->company_id;
                    $log->user_type = 'user';
                    $log->transaction_code = $random;
                    $log->amount = $totalAmount;
                    $log->transaction_id = '';
                    $log->payment_mode = $paymentMode;
                    $log->user_id = Auth::guard('user')->user()->id;
                    $log->save();
                    $gateway = new PaymentGateway('stripe');

                    $response = $gateway->process([
                          'order' => $random,
                          "amount" => $totalAmount,
                          "currency" => $stripe_currency,
                          "customer" => Auth::guard('user')->user()->stripe_cust_id,
                          "card" => $card->card_id,
                          "description" => "Payment Charge for " . Auth::guard('user')->user()->email,
                          "receipt_email" => Auth::guard('user')->user()->email,
                    ]);

                  break;
                }
                //return $response;
                if($response->status == "SUCCESS") {  
                    $log->transaction_id = $response->payment_id;
                    $log->save();
                    
                    return $response->payment_id; 
                } else {
                  return 'failed';
                }
    }

    public function order_request_dispute(Request $request) {
        
        $this->validate($request, [
                'dispute_name' => 'required',
                'store_order_id' => 'required'
            ]);
        $order_request_disputes = StoreOrderDispute::where('company_id',Auth::guard('user')->user()->company_id)
                                ->where('store_order_id',$request->store_order_id)
                                ->where('dispute_type','user')
                                ->first();
        if($order_request_disputes==null)
        {
            

            try{
                $order_request_dispute = new StoreOrderDispute;
                $order_request_dispute->company_id = Auth::guard('user')->user()->company_id;  
                $order_request_dispute->store_order_id = $request->store_order_id;
                $order_request_dispute->dispute_type ="user";
                $order_request_dispute->user_id = $request->user_id;
                $order_request_dispute->provider_id = $request->provider_id;                  
                $order_request_dispute->dispute_name = $request->dispute_name;
                $order_request_dispute->dispute_title ="User Dispute"; 
                $order_request_dispute->comments =  $request->comments; 
                $order_request_dispute->save();
                return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);
            } 
            catch (\Throwable $e) {
                return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
            }
        }else{
            return Helper::getResponse(['status' => 404, 'message' => trans('Already Dispute Created for the Ride Request')]);
        }
    }

    public function get_order_request_dispute(Request $request,$id) {
        $order_request_dispute = StoreOrderDispute::where('company_id',Auth::guard('user')->user()->company_id)
                                ->where('store_order_id',$id)
                                ->where('dispute_type','user')
                                ->first();
        return Helper::getResponse(['data' => $order_request_dispute]);
    }

}
