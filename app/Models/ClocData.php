<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClocData extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'cloc_datas';

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

    public static function getLatestPeriodData($tool_id){
        $now = Carbon::now();
        $current_month = $now->copy()->month;
        $season = config('api.season');
        $current_season = [];
        foreach ($season as $item){
            if (in_array($current_month, $item)){
                $current_season = $item;
            }
        }
        $up = 0;
        $down = 0;
        $end = $now->copy()->subMonth(3  + $current_month - \Illuminate\Support\Arr::last($current_season))->endOfMonth()->toDateTimeString();
        if (!empty($tool_id)){
            foreach ($tool_id as $item){
                $result = self::query()
                        ->where('tool_cloc_id', $item)
                        ->where('created_at', '<', $end)
                        ->orderBy('created_at', 'desc')
                        ->take(1)
                        ->get()
                        ->toArray();
                foreach ($result as $value){
                    $up += $value['comment'];
                    $down += $value['comment'] + $value['code'];
                }
            }
        }
        return array(
            'up' => $up, 
            'down' => $down
        );
    }

    public static function getPublishedData($tool_id, $publishded_at){
        $result = [];
        if ($tool_id){
            $cloc_data = self::query()
                        ->where('tool_cloc_id', $tool_id)
                        ->where('created_at', '<', $publishded_at)
                        ->orderBy('created_at', 'desc')
                        ->take(1)
                        ->get()
                        ->toArray();
            if (!empty($cloc_data)){
                foreach ($cloc_data as $item){
                    $result = [
                        'total' => $item['total'],
                        'files' => $item['files'],
                        'blank' => $item['blank'],
                        'comment' => $item['comment'],
                        'code' => $item['code'],
                    ];
                    return $result;
                }
            }else{
                return [];
            }
        }else {
            return [];
        }
    }
}
