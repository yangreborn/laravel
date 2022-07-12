<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ServerData extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'server_data';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    protected $casts = [
        'disk' => 'array',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    static public function shareInfo() {
        $result = [];
        
        // 图片消息
        $file_path = html_to_image(config('app.url') . '/manage-tool/elk', '#share-server-info', true);
        if (file_exists($file_path)) {
            $result[] = [
                'data' => $file_path,
                'key' => config('wechat.wechat_robot_key.elk'),
                'type' => 'image',
            ];
        }
        return $result;
    }

    /**
     * 过去24小时服务器信息统计
     */
    static public function lastDaytimeData() {
        $start = Carbon::now()->subHours(24)->format('Y-m-d H:00:00');
        $res = self::query()
            ->select(
                DB::Raw('max(id) as id'),
                'ip',
                DB::Raw('max(mem_total - mem_free) as mem_max_usage'),
                DB::Raw('max(cpu_rate) as cpu_max_rate'),
                DB::Raw('avg(mem_total - mem_free) as mem_avg_usage'),
                DB::Raw('avg(cpu_rate) as cpu_avg_rate')
            )
            ->groupBy('ip')
            ->where('created_at', '>', $start)
            ->get()
            ->toArray();
        $base = self::query()
            ->select('ip', 'cpu_count', 'mem_total', 'disk')
            ->whereIn('id', array_column($res, 'id'))
            ->get()
            ->toArray();
        $ips = array_column($base, 'ip');
        $base = array_combine($ips, $base);

        $res = array_map(function ($item) use($base, $start) {
             $result = self::query()
                ->select('cpu_rate', DB::Raw('(mem_total - mem_free) as mem_usage'), 'created_at')
                ->where('ip', $item['ip'])
                ->where('created_at', '>', $start)
                ->get()
                ->toArray();
            foreach($result as $cell) {
                $created_at = $cell['created_at'];
                $item['cpu'][] = [
                    'value' => $cell['cpu_rate'],
                    'time' => $created_at,
                ];
                if ($cell['cpu_rate'] === $item['cpu_max_rate']) {
                    $item['cpu_max_rate'] = [
                        'value' => $cell['cpu_rate'],
                        'time' => $created_at,
                    ];
                }

                $mem_usage = round($cell['mem_usage']*1000)/1000;
                $item['mem'][] = [
                    'value' => $mem_usage,
                    'time' => $created_at,
                ];
                if ($cell['mem_usage'] === $item['mem_max_usage']) {
                    $item['mem_max_usage'] = [
                        'value' => $mem_usage,
                        'time' => $created_at,
                    ];
                }
            }
            $item['cpu_avg_rate'] = round($item['cpu_avg_rate']*100)/100;
            $item['mem_avg_usage'] = round($item['mem_avg_usage']*1000)/1000;
            return $base[$item['ip']] + $item;
        }, $res);
        return $res;
    }
}
