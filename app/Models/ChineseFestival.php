<?php

namespace App\Models;

use Exception;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

class ChineseFestival extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date', 'type', 'remark', 'status'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * 判断当天是否休息日
     * 
     * @param str $date 给定日期字符串
     * @return int 休息日，1; 工作日，0; 无法判断，-1
    */
    static public function holiday($date = '') {
        $current = Carbon::now();
        if (!empty($date)) {
            $after_format = date('Y-m-d', strtotime($date));
            if ($after_format !== $date) {
                return -1;
            }
            $current = Carbon::createFromFormat('Y-m-d', $after_format);
        }

        $res = self::query()
            ->select('id', 'date', 'type')
            ->whereBetween('date',  [
                $current->copy()->startOfYear()->toDateString(),
                $current->copy()->endOfYear()->toDateString()
            ])
            ->get();
        
        if ($res->count() ==  0) {
            try {
                throw(new Exception('暂无该年份(' . $current->year . ')节假日数据！'));
            } catch(Exception $err) {
                report($err);
            }
            return -1;
        }

        $current_year_holidays = $res->filter(function ($v) {
           return $v->type === 1;
        })->map(function ($v) {
            return $v->date;
        })->toArray();

        $current_year_special_workday = $res->filter(function ($v) {
            return $v->type === 0;
         })->map(function ($v) {
             return $v->date;
         })->toArray();

        if (in_array($current->toDateString(), $current_year_holidays)) {
            return 1;
        }

        if (in_array($current->toDateString(), $current_year_special_workday)) {
            return 0;
        }

        return (int) $current->isWeekend();
    }

    /**
     * 距参考时间特定天数的最近工作日
     * 
     * @param int $days 天数
     * @param str $reference 参照时间
     */
    static public function workday($days, $reference=null, $direction='forward') {
        $temp = $reference ? Carbon::parse($reference) : Carbon::now();

        while($days > 0) {
            switch($direction) {
                case 'back':
                    $temp = $temp->subHours(24);
                    break;
                case 'forward':
                default:
                    $temp = $temp->addHours(24);
            }
            
            if (self::holiday($temp->toDateString()) === 1) {
                continue;
            }
            $days--;
        }
        return $temp->toDateTimeString();
    }

}
