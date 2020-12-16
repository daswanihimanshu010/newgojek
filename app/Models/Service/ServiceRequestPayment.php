<?php

namespace App\Models\Service;

use App\Models\BaseModel;

class ServiceRequestPayment extends BaseModel
{
    protected $connection = 'service';

    protected $hidden = [
     	'created_type', 'created_by', 'modified_type', 'modified_by', 'deleted_type', 'deleted_by', 'created_at', 'updated_at', 'deleted_at'
     ];
    
    public function promoCode()
    {
       return $this->belongsTo('App\Models\Common\Promocode', 'promocode_id');
    }
}
