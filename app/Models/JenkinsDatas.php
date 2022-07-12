<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class JenkinsDatas extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'jenkins_datas';

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
        return $result;
    }

    /**
     * 获取Jenkins服务器异常数据
     */
    static public function JenkinsErrorData() {
        $ips = ['172.16.1.147','172.16.1.148','172.16.1.153','172.16.2.146','172.16.2.147'];
        $res = [];
        $start = Carbon::today();
        $end = Carbon::tomorrow();
        $result =  self::query()->whereBetween('created_at',[$start,$end])->get()->toArray();
        $data_ips = [];
        foreach($result as $item){
            $data_ips[] = $item['server_ip'];
            $error_datas = json_decode($item['error_datas'],true);
            // var_dump($error_datas);
            $res = array_merge($res,$error_datas);
        }
        $diff_ips = array_diff($ips,$data_ips);
        foreach($diff_ips as $diff_ip){
            $nodata[$diff_ip] = [
                'url'=>$diff_ip.':8080',
                'email'=>[],
                'invalid'=>'NODATA',
                'duration'=>'NODATA',
                'freshTime'=>'NODATA',
                'receivers'=>'NODATA',
                'buildState'=>'NODATA',
                'emailState'=>'NODATA',
                'mysqlduration'=>'NODATA',
            ];
            $res = array_merge($res,$nodata);
        }
        return $res;
    }
}