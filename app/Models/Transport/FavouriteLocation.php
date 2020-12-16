<?php

namespace App\Models\Transport;

use App\Models\BaseModel;

class FavouriteLocation extends BaseModel
{
    protected $connection = 'transport';

    protected $hidden = [
     	'created_type', 'created_by', 'modified_type', 'modified_by', 'deleted_type', 'deleted_by', 'created_at', 'updated_at', 'deleted_at'
     ];
}
