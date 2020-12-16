<?php
namespace App\Http\Controllers\V1\Common\Admin\Resource;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Traits\Actions;
use App\Models\Common\PeakHour;
use App\Helpers\Helper;
use Auth;

class PeakHourController extends Controller
{
  

    use Actions;

    private $model;
    private $request;

    public function __construct(PeakHour $model)
    {
        $this->model = $model;
    }

    public function index(Request $request)
    {
     

     $datum = PeakHour::where('company_id', Auth::user()->company_id);

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
            'start_time' => 'required',
            'end_time' => 'required',           
        ]);

        try{
            $PeakHour = new PeakHour;
            $PeakHour->start_time = date('H:i:s', strtotime($request->start_time));
            $PeakHour->end_time = date('H:i:s', strtotime($request->end_time));
            $PeakHour->company_id=Auth::user()->company_id;                    
            $PeakHour->save();
            return Helper::getResponse(['status' => 200, 'message' => trans('admin.create')]);
        } 

        catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404, 'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        try {
            $PeakHour = PeakHour::findOrFail($id);
                return Helper::getResponse(['data' => $PeakHour]);
        } catch (\Throwable $e) {
            return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        
        $this->validate($request, [
            'start_time' => 'required',
            'end_time' => 'required',           
        ]);

        try{
            $PeakHour =  PeakHour::findOrFail($id);
            $PeakHour->start_time = date('H:i:s', strtotime($request->start_time));
            $PeakHour->end_time = date('H:i:s', strtotime($request->end_time));                    
            $PeakHour->save();
                return Helper::getResponse(['status' => 200, 'message' => trans('admin.update')]);
           
            } catch (\Throwable $e) {
                return Helper::getResponse(['status' => 404,'message' => trans('admin.something_wrong'), 'error' => $e->getMessage()]);
            }
    }

    public function destroy($id)
    {
        return $this->removeModel($id);
    }


}
