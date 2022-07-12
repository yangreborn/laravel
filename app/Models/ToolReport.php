<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ToolReport extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'tool',
        'file_path',
        'conditions',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User')->select('id', 'name')->withDefault([
            'name' => '',
        ]);
    }

    /**
     * 已发送邮件计数
     * 
     * @param int $period 计数周期，单位“天”
     * @param bool $personal 个人计数
     */
    static public function mailCount($period = 0, $personal = false) {
        $user_id = Auth::guard('api')->id();
        $res = self::query()
            ->selectRaw('COUNT(DISTINCT tool, conditions, DATE_FORMAT(created_at, \'%Y-%m-%d\')) AS value, DATE_FORMAT(created_at, \'%Y-%m-%d\') AS date')
            ->groupBy('date')
            ->when($personal !== false, function ($query) use($user_id) {
                $query->where('user_id', $user_id);
            })
            ->when($period === 0, function ($query) {
                $query->where('created_at', '>', Carbon::now()->yesterday()->endOfDay());
            }, function ($query) use ($period) {
                $query->where('created_at', '>', Carbon::now()->subDays($period)->endOfDay());
            })
            ->get()
            ->toArray();
        return !empty($res) ? $res : [];
    }

    /**
     * 按工具获取所有已发送邮件
     */
    static public function totalMail($personal = false) {
        $user_id = Auth::guard('api')->id();
        $res = self::query()
            ->selectRaw('tool, COUNT(DISTINCT conditions, DATE_FORMAT(created_at,\'%Y-%m-%d\')) AS value')
            ->when($personal !== false, function ($query) use($user_id) {
                $query->where('user_id', $user_id);
            })
            ->whereNotNull('conditions')
            ->groupBy('tool')
            ->get()
            ->toArray();

        return !empty($res) ? $res : [];
    }

    static public function mailWeekCount($period = 0, $personal = false) {
        $res = self::query()
            ->selectRaw('tool, COUNT( DISTINCT conditions ) AS value, DATE_FORMAT( created_at, \'%x年%v周\' ) AS date')
            ->when($period > 0, function ($query) use($period) {
                $query->where('created_at', '>', Carbon::now()->subWeeks($period)->endOfweek());
            })
            ->when($personal !== false, function($query) {
                $user_id = Auth::guard('api')->id();
                $query->where('user_id', $user_id);
            })
            ->groupBy('date', 'tool')
            ->get()
            ->toArray();
        $res = collect($res)
            ->groupBy('date')
            ->map(function($item, $key) {
                $result = [];
                $result['date'] = $key;
                $result['total'] = 0;
                if(!empty($item)) {
                    foreach($item as $v) {
                        $result[$v['tool']] = $v['value'];
                        $result['total'] += $v['value'];
                    }
                }
                return $result;
            })
            ->values()
            ->toArray();

        // 补全缺失的周
        if ($period > 0) {
            $year_week = array_column($res, 'date');
            for ($i = 0; $i < $period; $i++) { 
                $week = Carbon::now()->subWeeks($i)->format('o年W周');
                if (!in_array($week, $year_week)) {
                    $res[] = ['date' => $week, 'total' => 0];
                }
            }
            if (sizeof($res) > 1) {
                usort($res, function ($a, $b) {
                    return $a['date'] <=> $b['date'];
                });
            }
        }
        return !empty($res) ? $res : [];
    }  

}
