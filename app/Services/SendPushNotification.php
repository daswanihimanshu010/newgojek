<?php

namespace App\Services;

use App\Models\Common\Provider;
use App\Models\Common\Setting;
use Illuminate\Http\Request;
use App\Models\Common\User;
use Exception;
use Log;



class SendPushNotification
{

	/**
     * New Ride Accepted by a Driver.
     *
     * @return void
     */
    public function RideAccepted($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);
    	return $this->sendPushToUser($request->user_id, $type, trans('api.push.request_accepted'), 'Ride Accepted' );
    }

    public function ProviderAssign($provider, $type = null){

        $provider = Provider::where('id',$provider)->first();
        if($provider->language){
            $language = $provider->language;
            app('translator')->setLocale($language);
        }

        return $this->sendPushToProvider($provider, $type, trans('api.push.request_assign')  );
    }

    /**
     * Driver Arrived at your location.
     *
     * @return void
     */
    public function user_schedule($user, $type = null) {
         $user = User::where('id',$user)->first();
         $language = $user->language;
         app('translator')->setLocale($language);
        return $this->sendPushToUser($user, $type, trans('api.push.schedule_start')  );
    }

    /**
     * New Incoming request
     *
     * @return void
     */
    public function provider_schedule($provider, $type = null){

        $provider = Provider::where('id',$provider)->first();
        if($provider->language){
            $language = $provider->language;
            app('translator')->setLocale($language);
        }

        return $this->sendPushToProvider($provider, $type, trans('api.push.schedule_start')  );

    }

    /**
     * New Ride Accepted by a Driver.
     *
     * @return void
     */
    public function UserCancellRide($request, $type = null){

        if(!empty($request->provider_id)){

            $provider = Provider::where('id',$request->provider_id)->first();

            if($provider->language){
                $language = $provider->language;
                app('translator')->setLocale($language);
            }

            return $this->sendPushToProvider($request->provider_id, $type, trans('api.push.user_cancelled'), 'Request Cancelled', ''  );
        }
        
        return true;    
    } 

    public function StoreCanlled($request, $type = null){

        if(!empty($request->user_id)){

            $user = user::where('id',$request->user_id)->first();

            if($user->language){
                $language = $user->language;
                app('translator')->setLocale($language);
            }

            return $this->sendPushToUser($request->user_id, $type, trans('api.push.Cancelled'), 'Store Cancelled');
        }
        
        return true;    
    }

    public function ProviderWaiting($user_id, $status, $type = null){

        $user = User::where('id',$user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        if($status == 1) {
            return $this->sendPushToUser($user_id, $type, trans('api.push.provider_waiting_start'), 'Provider Waiting'  );
        } else {
            return $this->sendPushToUser($user_id, $type, trans('api.push.provider_waiting_end'), 'Provider Waiting'   );
        }
        
    }


    /**
     * New Ride Accepted by a Driver.
     *
     * @return void
     */
    public function ProviderCancellRide($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($request->user_id, $type, trans('api.push.provider_cancelled'), 'Provider Cancelled Ride'   );
    }

    /**
     * Driver Arrived at your location.
     *
     * @return void
     */
    public function Arrived($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($request->user_id, $type, trans('api.push.arrived'), 'Ride Arrived'  );
    }

    /**
     * Driver Picked You  in your location.
     *
     * @return void
     */
    public function Pickedup($request, $type = null){
        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($request->user_id, $type, trans('api.push.pickedup'), 'Ride Pickedup'  );
    }

    /**
     * Driver Reached  destination
     *
     * @return void
     */
    public function Dropped($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);
        
        return $this->sendPushToUser($request->user_id, $type, trans('api.push.dropped').$request->currency.$request->payment->total.' by '.$request->payment_mode, 'Ride Dropped');
    }

    /**
     * Your Ride Completed
     *
     * @return void
     */
    public function Complete($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($request->user_id, $type, trans('api.push.complete'), 'Ride Completed');
    }

    
     
    /**
     * Rating After Successful Ride
     *
     * @return void
     */
    public function Rate($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($request->user_id, $type, trans('api.push.rate')  );
    }


    /**
     * Money added to user wallet.
     *
     * @return void
     */
    public function ProviderNotAvailable($user_id, $type = null){
        $user = User::where('id',$user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($user_id, $type, trans('api.push.provider_not_available')  );
    }

    /**
     * New Incoming request
     *
     * @return void
     */
    public function IncomingRequest($provider, $type = null, $title = null){

        $provider = Provider::where('id',$provider)->first();
        if($provider->language){
            $language = $provider->language;
            app('translator')->setLocale($language);
        }
        return $this->sendPushToProvider($provider->id, $type, trans('api.push.incoming_request'), $title, ''  );

    }

    public function ChatPushProvider($provider, $type = null){
        
        $provider = Provider::where('id',$provider)->first();
        if($provider->language){
            $language = $provider->language;
            app('translator')->setLocale($language);
        }

        return $this->sendPushToProvider($provider->id, $type, trans('api.push.chat_message')  );

    }

    public function ChatPushUser($user, $type = null){
      
        $user = User::where('id',$user)->first();
        if($user->language){
            $language = $user->language;
            app('translator')->setLocale($language);
        }
 
        return $this->sendPushToUser($user->id, $type, trans('api.push.chat_message')  );

    }
    

    /**
     * Driver Documents verfied.
     *
     * @return void
     */
    public function DocumentsVerfied($provider_id, $type = null){

        $provider = Provider::where('id',$provider_id)->first();
        if($provider->language){
            $language = $provider->language;
            app('translator')->setLocale($language);
        }

        return $this->sendPushToProvider($provider_id, $type, trans('api.push.document_verfied')  );
    }


    /**
     * Money added to user wallet.
     *
     * @return void
     */
    public function WalletMoney($user_id, $money, $type = null, $title = null, $data = null){

        $user = User::where('id',$user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);
        return $this->sendPushToUser($user_id, $type, $money.' '.trans('api.push.added_money_to_wallet'), $title, $data  );
    }

    public function ProviderWalletMoney($user_id, $money, $type = null, $title = null, $data = null){

        $user = Provider::where('id',$user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToProvider($user_id, $type, $money.' '.trans('api.push.added_money_to_wallet'), $title, $data  );
    }

    /**
     * Money charged from user wallet.
     *
     * @return void
     */
    public function ChargedWalletMoney($user_id, $money, $type = null){

        $user = User::where('id',$user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($user_id, $type, $money.' '.trans('api.push.charged_from_wallet')  );

    }

    public function updateProviderStatus($provider_id, $message, $type = null){

        $provider = Provider::where('id',$provider_id)->first();
        if($provider->language){
            $language = $provider->language;
            app('translator')->setLocale($language);
        }

        return $this->sendPushToProvider($provider_id, $type, $message  );

    }


    public function provider_hold($provider_id, $type = null){

        $provider = Provider::where('id',$provider_id)->first();
        if($provider->language){
            $language = $provider->language;
            app('translator')->setLocale($language);
        }

        return $this->sendPushToProvider($provider_id, $type, trans('api.push.provider_status_hold')  );

    }


    /*
    *  SERVICE TYPE PUSH NOTIFICATIONS
    */ 
    /**
     * New Incoming request
     *
     * @return void
     */
    public function serviceIncomingRequest($provider, $type = null){

        $provider = Provider::where('id',$provider)->first();
        if($provider->language){
            $language = $provider->language;
            app('translator')->setLocale($language);
        }

        return $this->sendPushToProvider($provider->id, $type, trans('api.push.service.incoming_request')  );

    }
     /**
     * Provider Not Available
     *
     * @return void
     */
    public function serviceProviderNotAvailable($user_id, $type = null){
        $user = User::where('id',$user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($user_id, $type, trans('api.push.service.provider_not_available'));
    }
    /**
     * Service provider Arrived at your location.
     *
     * @return void
     */
    public function serviceProviderArrived($request, $type = null){
        if($request != null){
            $user = User::where('id',$request->user_id)->first();
            $language = $user->language;
            app('translator')->setLocale($language);
            $serviceAlias = isset($request->service->serviceCategory)? $request->service->serviceCategory->alias_name:'';
            $message = $serviceAlias .' ' . trans('api.push.service.arrived');
            return $this->sendPushToUser($request->user_id, $type, $message );
        }else{
            return false;
        }
    }
    /**
     * Your Service Completed
     *
     * @return void
     */
    public function serviceProviderComplete($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($request->user_id, $type, trans('api.push.service.complete')  );
    }
    /**
     * Provider Picked up service in your location.
     *
     * @return void
     */
    public function serviceProviderPickedup($request, $type = null){
        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($request->user_id, $type, trans('api.push.service.pickedup')  );
    }

     /**
     * Service provider end service
     *
     * @return void
     */
    public function serviceProviderDropped($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($request->user_id, $type, trans('api.push.service.dropped').$request->currency.$request->payment->total.' by '.$request->payment_mode  );

    }
    /**
     * confirmed the payment
     *
     * @return void
     */
    public function serviceProviderConfirmPay($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($request->user_id, $type, trans('api.push.service.confirmpay').$request->currency.$request->payment->total.' by '.$request->payment_mode  );

    }
    /*
    *  ORDER PUSH NOTIFICATIONS
    */
    public function orderProviderStarted($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

       return $this->sendPushToUser($request->user_id, $type, trans('api.push.order.started').config('constants.currency').$request->orderInvoice->total_amount .' by '.$request->orderInvoice->payment_mode  );

    }
    public function orderProviderArrived($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

       return $this->sendPushToUser($request->user_id, $type, trans('api.push.order.arrived').config('constants.currency').$request->orderInvoice->total_amount .' by '.$request->orderInvoice->payment_mode  );

    }
    public function orderProviderPickedup($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

       return $this->sendPushToUser($request->user_id, $type, trans('api.push.order.pickedup').config('constants.currency').$request->orderInvoice->total_amount .' by '.$request->orderInvoice->payment_mode  );

    }
    public function orderProviderConfirmPay($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

       return $this->sendPushToUser($request->user_id, $type, trans('api.push.order.confirmpay').config('constants.currency').$request->orderInvoice->total_amount .' by '.$request->orderInvoice->payment_mode  );

    }
        /**
     * Your Order Completed
     *
     * @return void
     */
    public function orderProviderComplete($request, $type = null){

        $user = User::where('id',$request->user_id)->first();
        $language = $user->language;
        app('translator')->setLocale($language);

        return $this->sendPushToUser($request->user_id, $type, trans('api.push.order.complete')  );
    }

    public function UserCancelOrder($request, $type = null){
        if(!empty($request->provider_id)){
            $provider = Provider::where('id',$request->provider_id)->first();
            if($provider->language){
                $language = $provider->language;
                app('translator')->setLocale($language);
            }
            return $this->sendPushToProvider($request->provider_id, $type, trans('api.push.user_cancelled'), 'Request Cancelled', ''  );
        }        
        return true;    
    } 



    /**
     * Sending Push to a user Device.
     *
     * @return void
     */
    public function sendPushToUser($user_id, $topic, $push_message, $title = null, $data = null){

        try{

            $user = User::findOrFail($user_id);

            $settings = Setting::where('company_id', $user->company_id)->first();

            if($title == null) $title = $settings->settings_data->site->site_title;

            if($user->device_token != ""){

                if($user->device_type == 'IOS'){

                    $config = [
                        'environment' => $settings->settings_data->site->environment,
                        'certificate' => app()->basePath('storage/app/public/'.$user->company_id.'/apns' ).'/user.pem',
                        'passPhrase'  => $settings->settings_data->site->ios_push_password,
                        'service'     => 'apns'
                    ];
                   

                }elseif($user->device_type == 'ANDROID'){

                    $config = [
                        'environment' => $settings->settings_data->site->environment,
                        'apiKey'      => $settings->settings_data->site->android_push_key,
                        'service'     => 'gcm'
                    ];   

                }

                $message = \PushNotification::Message($push_message, array(
                    'badge' => 1,
                    'sound' => 'default',
                    'custom' => [ "message" => [ "topic" => $topic, "notification" => [ "body" => $push_message, "title" => $title ], "data" => ($data == null) ? [] : $data ] ]
                ));

                return \PushNotification::app($config)
                        ->to($user->device_token)
                        ->send($message);
            }
            


        } catch(Exception $e){
            return $e;
        }

    }

    /**
     * Sending Push to a user Device.
     *
     * @return void
     */
    public function sendPushToProvider($provider_id, $topic, $push_message, $title = null, $data = null){

        try{          

            $user = Provider::findOrFail($provider_id);         

            $settings = Setting::where('company_id', $user->company_id)->first();

            if($title == null) $title = $settings->settings_data->site->site_title;

            if($user->device_token != ""){  

                if($user->device_type == 'IOS'){

                    $config = [
                        'environment' => $settings->settings_data->site->environment,
                        'certificate' => app()->basePath('storage/app/public/'.$user->company_id.'/apns' ).'/provider.pem',
                        'passPhrase'  => $settings->settings_data->site->ios_push_password,
                        'service'     => 'apns'
                    ];

                } elseif($user->device_type == 'ANDROID') {

                    $config = [
                        'environment' => $settings->settings_data->site->environment,
                        'apiKey'      => $settings->settings_data->site->android_push_key,
                        'service'     => 'gcm'
                    ];

                }

                $message = \PushNotification::Message($push_message, array(
                    'badge' => 1,
                    'sound' => 'default',
                    'custom' => [ "message" => [ "topic" => $topic, "notification" => [ "body" => $push_message, "title" => $title ], "data" => ($data == null) ? [] : $data ] ]
                ));
               
                return \PushNotification::app($config)
                        ->to($user->device_token)
                        ->send($message);
            }

            

        } catch(Exception $e){           
            return $e;
        }

    }

}


