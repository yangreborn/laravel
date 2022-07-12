<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class LintData extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'pclint_datas';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

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

    public static function getPublishedData($tool_id, $publishded_at){
        if ($tool_id){
            $result = self::query()
                        ->where('tool_pclint_id', $tool_id)
                        ->where('created_at', '<', $publishded_at)
                        ->orderBy('created_at', 'desc')
                        ->take(1)->value('error');
            if ($result){
                return $result;
            }else{
                return 0;
            }
        }else {
            return 0;
        }
    }
}
