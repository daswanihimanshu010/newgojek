<?php

namespace App\Models\Order;

use App\Models\BaseModel;

class StoreOrderDispute extends BaseModel
{
    protected $connection = 'order';
    protected $hidden = [
     	'created_type', 'created_by', 'modified_type', 'modified_by', 'deleted_type', 'deleted_by', 'updated_at', 'deleted_at'
     ];
      

 public function user()
    {
        return $this->belongsTo('App\Models\Common\User');
    }

    /**
     * The provider assigned to the request.
     */
    public function provider()
    {
        return $this->belongsTo('App\Models\Common\Provider', 'provider_id');
    }

    public function request()
    {
        return $this->belongsTo('App\Models\Order\StoreOrder','store_order_id');
    }

    
}
