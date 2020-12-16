<?php

namespace App\Models\Common;

use App\Models\BaseModel;

class PeakHour extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'start_time',
        'end_time',
        'status'        
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
         'created_at', 'updated_at'
    ];

    public function scopeSearch($query, $searchText='') {
        return $query
            ->where('start_time', 'like', "%" . $searchText . "%")
            ->orWhere('end_time', 'like', "%" . $searchText . "%");
           
          
    }
}
