<?php

namespace App\Models\Transport;

use App\Models\BaseModel;
use Auth;

class RideType extends BaseModel
{
    protected $connection = 'transport';

    protected $fillable = [
        'company_id','ride_name','status'
    ];

    protected $hidden = [
     	'company_id', 'created_type', 'created_by', 'modified_type', 'modified_by', 'deleted_type', 'deleted_by', 'created_at', 'updated_at', 'deleted_at'
    ];

    public function ride() {
    	return $this->hasMany('App\Models\Transport\RideDeliveryVehicle');
    }

    public function servicelist() {
    return $this->hasMany('\App\Models\Transport\RideDeliveryVehicle','ride_type_id','id')->where('status',1);
    }

    public function providerservice() {
    return $this->hasOne('App\Models\Common\ProviderService','category_id','id')->where('admin_service_id',1)->where('provider_id',Auth::guard('provider')->user()->id)->with('providervehicle');
    }

    public function provideradminservice() {
        return $this->hasOne('App\Models\Common\ProviderService','category_id','id')->where('admin_service_id',1);
    }

    public function scopeSearch($query, $searchText='') {
        return $query
            ->where('ride_name', 'like', "%" . $searchText . "%");
          
    }
}
