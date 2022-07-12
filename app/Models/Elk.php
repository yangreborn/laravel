<?php

namespace App\Models;

use Carbon\Carbon;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Elk extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'elk_data';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'data', 'status'
    ];

    protected $casts = [
        'data' => 'array',
    ];

    protected $appends = ['clean_data'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    public function getCleanDataAttribute()
    {
        $result = [];
        if (key_exists('data', $this->attributes)) {
            $data = json_decode($this->attributes['data'], true);
            $result['jenkins_msg'] = $this->cleanJenkinsData($data['jenkins_msg']);
            $result['server_info'] = $this->cleanSystemData($data['server_info']);
        }
        return $result;
    }

    private function cleanJenkinsData($items = [])
    {
        $res = [];
        if (!empty($items)) {
            $i = 0;
            foreach ($items as $k => $v) {
                if (key_exists('url', $v) && trim($v['url']) !== '') {
                    $keys = array_keys($v);
                    $v = array_map(function ($cell, $name) {
                        if (in_array($name, ['url'])) {
                            return $cell;
                        }
                        if (in_array($name, ['email'])) {
                            return $cell;
                        }
                        return ucwords(strtolower($cell), ',');
                    }, $v, $keys);
                    $v = array_combine($keys, $v);
                    $res[] = ['key' => $i++, 'title' => $k] + $v;
                }
            }
        }
        return $res;
    }

    private function cleanSystemData($items = []) {
        $res = [];
        if (!empty($items)) {
            foreach($items as $v) {
                if (key_exists('ip', $v) && trim($v['ip']) !== '') {
                    $result = array_map(function ($item, $key) {
                        $arr = explode(' ', $item);
                        if ($key === 'disk') {
                            return array_map(function ($cell) {
                                $arr_cell = explode(':', $cell);
                                return ['part' => $arr_cell[0]] + $this->stringFormat($arr_cell[1]);
                            }, $arr);
                        }
                        return $this->stringFormat(array_pop($arr));
                    }, $v, array_keys($v));
                    $res[] = array_combine(array_keys($v), $result);
                }
            }
        }

        return $res;
    }
    private function stringFormat($str) {
        $res = null;
        if (!empty($str)) {
            $str = str_replace('_', '', $str);

            // keys
            $arr = preg_split('/\((.+?)\)/', $str);
            $arr_keys = array_filter($arr, function ($item) {
                return trim($item) !== '';
            });

            // values
            preg_match_all('/\((.+?)\)/', $str, $result);
            $arr_values = $result[1];

            if (!empty($arr_values)) {
                $arr_values = array_map(function ($item) {
                    return preg_replace('/[A-Za-z]+/', '', $item);
                }, $arr_values);
                $res = array_combine($arr_keys, $arr_values);
            } else {
                $res = $arr_keys[0];
            }
        }

        return $res;
    }

    static public function shareInfo($type = ['text', 'image']) {
        $result = [];
        if (in_array('text', $type)) {
            // $res = self::query()->orderBy('updated_at', 'desc')->first();
            // $data = $res->clean_data;
            // $data = !key_exists('jenkins_msg', $data) ? [] : $data['jenkins_msg'];
    
            $today = Carbon::now()->toDateString();
            $data = JenkinsDatas::JenkinsErrorData();
            // markdown 消息
            if(!empty($data)){
                foreach ($data as $key=>$item) {
                    $title = $key;
                    $url = $item['url'];
                    $fresh_time = $item['freshTime'];
                    $duration = $item['duration'];
                    $duration_color = 'comment';
                    $duration_parts = explode('hour',$duration);
                    if(count($duration_parts)>1){ 
                        if((int)$duration_parts[0]>=6){
                            $duration_color = '#FF0000';
                        }
                    }
                    $build_state = $item['buildState'];
                    $build_state_color = $item['buildState'] === 'SUCCESS' ? 'info' : '#FF0000';
                    if($item['buildState'] === 'Building' or $item['buildState'] === 'UNSTABLE'){
                        $build_state_color = 'warning';
                    }
                    $email_state = $item['emailState'];
                    $email_state_color = $item['emailState'] === 'SUCCESS' ? 'info' : '#FF0000';
                    $invalid = $item['invalid'];
                    $invalid_color = $item['invalid'] === 'NONE' ? 'info' : '#FF0000';
                    $mysqlduration = $item['mysqlduration'];
                    $mysqlduration_color = $item['mysqlduration'] === 'Normal' ? 'info' : '#FF0000';
                    $at_string = '';
                    $emails = $item['email'];
                    $users = User::query()
                        ->whereIn('email', $emails)
                        ->pluck('kd_uid')
                        ->toArray();
                    $users = array_map(function ($item) {
                        return '<@' . $item . '>';
                    }, $users);
                    $at_string = implode(',', $users);
                    if ($build_state === 'None'
                        && $email_state === 'None'
                        && $invalid === 'None'
                        && $mysqlduration === 'Normal'
                        ) {
                        continue;
                    }
                    $content = <<<markdown
### Jenkins异常Job @ $today\n
> 名称： [$title]($url)
> 更新时间： <font color="comment">$fresh_time</font>
> 构建时长： <font color="$duration_color">$duration</font>
> 构建状态： <font color="$build_state_color">$build_state</font>
> 邮件状态： <font color="$email_state_color">$email_state</font>
> 异常邮件人： <font color="$invalid_color">$invalid</font>
> 数据存储状态： <font color="$mysqlduration_color">$mysqlduration</font>
> 责任人： <font color="comment">$at_string</font>\n
markdown;
                    $result[] = [
                        'data' => ['content' => $content],
                        'key' => config('wechat.wechat_robot_key.elk'),
                        'type' => 'markdown',
                    ];
                }
            }
        }

        // 图片消息
        if (in_array('image', $type)) {
            $server_data = ServerData::lastDaytimeData();
            if (!empty($server_data)) {
                $file_path = html_to_image(config('app.url') . '/manage-tool/elk', '#share-server-info', true);
                if (file_exists($file_path)) {
                    $result[] = [
                        'data' => $file_path,
                        'key' => config('wechat.wechat_robot_key.elk'),
                        'type' => 'image',
                    ];
                }
            }
        }
        return $result;
    }
}
