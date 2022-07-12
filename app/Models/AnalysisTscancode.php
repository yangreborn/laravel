<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AnalysisTscancode extends Authenticatable
{
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
    protected $fillable = [
        'tool_tscancode_id',
        'period',
        'deadline',
        'summary',
        'extra',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];

    public static function getLatestPeriodData($tool_id, $period){
        if ($tool_id){
            $result = self::query()
                        ->where('tool_tscancode_id', $tool_id)
                        ->where('period', $period)
                        ->orderBy('created_at', 'desc')
                        ->take(1)->value('summary');
            return $result;
        }else {
            return 0;
        }
    }
}
