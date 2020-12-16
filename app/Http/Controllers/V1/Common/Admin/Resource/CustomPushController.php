<?php

namespace App\Http\Controllers\V1\Common\Admin\Resource;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Helpers\Helper;
use App\Traits\Actions;
use App\Models\Common\CustomPush;
use DB;
use Auth;
class CustomPushController extends Controller
{
        use Actions;

        private $model;
        private $request;
        /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(CustomPush $model)
    {
        $this->model = $model;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $Pushes = CustomPush::where('company_id',Auth::user()->company_id)->paginate(10);
        return Helper::getResponse(['data' => $Pushes]);
    }
    /**
	 * pages.
	 *
	 * @param  \App\Provider  $provider
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request){


		$this->validate($request, [
				'send_to' => 'required|in:ALL,USERS,PROVIDERS',
				'user_condition' => ['required_if:send_to,USERS','in:ACTIVE,LOCATION,RIDES,AMOUNT'],
				'provider_condition' => ['required_if:send_to,PROVIDERS','in:ACTIVE,LOCATION,RIDES,AMOUNT'],
				'user_active' => ['required_if:user_condition,ACTIVE','in:HOUR,WEEK,MONTH'],
				'user_rides' => 'required_if:user_condition,RIDES',
				'user_location' => 'required_if:user_condition,LOCATION',
				'user_amount' => 'required_if:user_condition,AMOUNT',
				'provider_active' => ['required_if:provider_condition,ACTIVE','in:HOUR,WEEK,MONTH'],
				'provider_rides' => 'required_if:provider_condition,RIDES',
				'provider_location' => 'required_if:provider_condition,LOCATION',
				'provider_amount' => 'required_if:provider_condition,AMOUNT',
				'message' => 'required|max:100',
			]);

		try{

			$CustomPush = new CustomPush;
			$CustomPush->send_to = $request->send_to;
			$CustomPush->message = $request->message;
			$CustomPush->company_id = Auth::user()->company_id;  

			if($request->send_to == 'USERS'){

				$CustomPush->condition = $request->user_condition;

				if($request->user_condition == 'ACTIVE'){
					$CustomPush->condition_data = $request->user_active;
				}elseif($request->user_condition == 'LOCATION'){
					$CustomPush->condition_data = $request->user_location;
				}elseif($request->user_condition == 'RIDES'){
					$CustomPush->condition_data = $request->user_rides;
				}elseif($request->user_condition == 'AMOUNT'){
					$CustomPush->condition_data = $request->user_amount;
				}

			}elseif($request->send_to == 'PROVIDERS'){

				$CustomPush->condition = $request->provider_condition;

				if($request->provider_condition == 'ACTIVE'){
					$CustomPush->condition_data = $request->provider_active;
				}elseif($request->provider_condition == 'LOCATION'){
					$CustomPush->condition_data = $request->provider_location;
				}elseif($request->provider_condition == 'RIDES'){
					$CustomPush->condition_data = $request->provider_rides;
				}elseif($request->provider_condition == 'AMOUNT'){
					$CustomPush->condition_data = $request->provider_amount;
				}
			}

			if($request->has('schedule_date') && $request->has('schedule_time')){
				$CustomPush->schedule_at = date("Y-m-d H:i:s",strtotime("$request->schedule_date $request->schedule_time"));
			}
			$CustomPush->save();
			if($CustomPush->schedule_at == ''){
				$this->SendCustomPush($CustomPush->id);
			}

			return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);
		}
		catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
	}

    /**
     * Display the specified resource.
     *
     * @param  \App\Reason  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $custom_push = CustomPush::findOrFail($id);
            return Helper::getResponse(['data' => $custom_push]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Reason  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'send_to' => 'required|in:ALL,USERS,PROVIDERS',
            'user_condition' => ['required_if:send_to,USERS','in:ACTIVE,LOCATION,RIDES,AMOUNT'],
            'provider_condition' => ['required_if:send_to,PROVIDERS','in:ACTIVE,LOCATION,RIDES,AMOUNT'],
            'user_active' => ['required_if:user_condition,ACTIVE','in:HOUR,WEEK,MONTH'],
            'user_rides' => 'required_if:user_condition,RIDES',
            'user_location' => 'required_if:user_condition,LOCATION',
            'user_amount' => 'required_if:user_condition,AMOUNT',
            'provider_active' => ['required_if:provider_condition,ACTIVE','in:HOUR,WEEK,MONTH'],
            'provider_rides' => 'required_if:provider_condition,RIDES',
            'provider_location' => 'required_if:provider_condition,LOCATION',
            'provider_amount' => 'required_if:provider_condition,AMOUNT',
            'message' => 'required|max:100',
        ]);
        try {

            $CustomPush = CustomPush::findOrFail($id);
            $CustomPush->send_to = $request->send_to;
			$CustomPush->message = $request->message;
			$CustomPush->company_id = Auth::user()->company_id;  


			if($request->send_to == 'USERS'){

				$CustomPush->condition = $request->user_condition;

				if($request->user_condition == 'ACTIVE'){
					$CustomPush->condition_data = $request->user_active;
				}elseif($request->user_condition == 'LOCATION'){
					$CustomPush->condition_data = $request->user_location;
				}elseif($request->user_condition == 'RIDES'){
					$CustomPush->condition_data = $request->user_rides;
				}elseif($request->user_condition == 'AMOUNT'){
					$CustomPush->condition_data = $request->user_amount;
				}

			}elseif($request->send_to == 'PROVIDERS'){

				$CustomPush->condition = $request->provider_condition;

				if($request->provider_condition == 'ACTIVE'){
					$CustomPush->condition_data = $request->provider_active;
				}elseif($request->provider_condition == 'LOCATION'){
					$CustomPush->condition_data = $request->provider_location;
				}elseif($request->provider_condition == 'RIDES'){
					$CustomPush->condition_data = $request->provider_rides;
				}elseif($request->provider_condition == 'AMOUNT'){
					$CustomPush->condition_data = $request->provider_amount;
				}
			}

			if($request->has('schedule_date') && $request->has('schedule_time')){
				$CustomPush->schedule_at = date("Y-m-d H:i:s",strtotime("$request->schedule_date $request->schedule_time"));
			}
			$CustomPush->save();
            return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);   
        } 
        catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Reason  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        return $this->removeModel($id);
    }

}
