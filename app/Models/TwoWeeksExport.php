<?php

namespace App\Models;

use App\Models\TwoWeeksData;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TwoWeeksExport extends Authenticatable
{
    //
    use HasApiTokens, Notifiable;
    
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    
    protected $hidden = [];

    protected $appends = [];

    public static function TwoWeeksExportData($type){
        $res = TwoWeeksData::codeReviewData($type);
        // wlog('res',$res);
        return $res['job_Hdatas'];
    }

}