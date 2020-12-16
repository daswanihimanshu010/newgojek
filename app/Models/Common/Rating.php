<?php

namespace App\Models\Common;

use App\Models\BaseModel;

class Rating extends BaseModel
{
	protected $connection = 'common';
	
    protected $fillable = [
        'admin_service_id', 'request_id', 'user_id', 'provider_id', 'company_id', 'user_rating', 'provider_rating', 'store_rating', 'user_comment', 'provider_comment', 'store_comment'
    ];


     public function user()
     {
         return $this->belongsTo('App\Models\Common\User');
     }
 

     public function provider()
     {
         return $this->belongsTo('App\Models\Common\Provider', 'provider_id');
     }
}
