<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2017/12/20
 * Time: 10:49
 */

if (!function_exists('wlog')){
    function wlog( $name, $value ){
        if (env('APP_DEBUG')){
            $log_file = dirname(__DIR__).'/storage/logs/'.date('Ymd').'.log';
            $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            $message = '[' . date('Y-m-d H:i:s') . ']' . $name . ' ' . $value."\r\n";
            file_put_contents($log_file, $message, FILE_APPEND);
        }
    }
}

/**
 * 获取财年
 */
if (!function_exists('get_fiscal_year')) {
    function get_fiscal_year($date = null){
        $timestamp = $date ? strtotime($date) : time();
        $month_day = date('m-d', $timestamp);
        return $month_day >= '03-26' && $month_day <= '12-31' ? intval(date('Y', $timestamp)) : intval(date('Y', $timestamp)) - 1;
    }
}

/**
 * 获取财季
 */
if (!function_exists('get_fiscal_season')){
    function get_fiscal_season($date = null){
        $season = config('api.fiscal_season');
        $timestamp = $date ? strtotime($date) : time();

        $season_number = 0;
        $month_day = date('m-d', $timestamp);

        foreach ($season as $k=>$v) {
            if ($k !== 3) {
                if ($month_day >= $v[0] && $month_day <= $v[1]){
                    $season_number = $k + 1;
                    break;
                }
            }else{
                if ($month_day >= $v[0] && $month_day <= '12-31' || $month_day >= '01-01' && $month_day <= $v[1]){
                    $season_number = $k + 1;
                    break;
                }
            }
        }

        return $season_number;
    }
}

/**
 * 获取财月
 */
if (!function_exists('get_fiscal_month')){
    function get_fiscal_month($date = null){
        $day = intval(date('j', $date));
        $month = intval(date('n', $date));
        return $day > 25 ? $month + 1 : $month;
    }
}

if (!function_exists('get_date_from_week')){
    /**
     * 根据年份和周数获取该周日期范围（注意数据库周数起始值，本函数适用于起始位1）
     * @param $year integer 年份
     * @param $week integer 周数
     * @return array 返回该周日期范围
     */
    function get_date_from_week($year, $week){
        $time = strtotime("1 January $year", time());
        $day = date('w', $time);
        $week = $day>4?$week+1:$week;
        $time += ((7 * ($week - 1)) + 1 - $day)*24*3600;
        $return['start'] = date('Y-m-d H:i:s', $time);
        $time += 7*24*3600 - 1;
        $return['end'] = date('Y-m-d H:i:s', $time);
        return $return;
    }
}

if (!function_exists('get_has_delayed')){
    /**
     * 统计是否在有效期范围内（需排除周末）
     * @param $start_time string 开始时间
     * @param $end_time string 结束时间
     * @return bool 未超期：false，超期：true
     */
    function get_has_delayed($start_time, $end_time){
        $weekday = date('w', strtotime($start_time)); // 0,星期天；1,星期一....
        $interval = 24*60*60 + 20*60*60; // 第二天20点前（遇到周末另行计算）
        if ($weekday === 5) {
            $interval += 2*24*60*60;
        } elseif ($weekday === 6) {
            $interval += 1*24*60*60;
        }
        $start_timestamp = strtotime($start_time);
        $end_timestamp = strtotime($end_time);
        return $end_timestamp - $start_timestamp < $interval ? false : true;
    }

}

if (!function_exists('get_week_date')){
    //根据第几周获取当周的开始日期与最后日期
    function get_week_date($year,$weeknum){ 
        $firstdayofyear=mktime(0,0,0,1,1,$year); 
        $firstweekday=date('N',$firstdayofyear); 
        $firstweenum=date('W',$firstdayofyear); 
        if($firstweenum==1){ 
            $day=(1-($firstweekday-1))+7*($weeknum-1); 
            $startdate=date('Y-m-d',mktime(0,0,0,1,$day,$year)); 
            $enddate=date('Y-m-d',mktime(0,0,0,1,$day+6,$year)); 
        }else{ 
            $day=(9-$firstweekday)+7*($weeknum-1); 
            $startdate=date('Y-m-d',mktime(0,0,0,1,$day,$year)); 
            $enddate=date('Y-m-d',mktime(0,0,0,1,$day+6,$year)); 
        } 
        
        return array($startdate,$enddate);     
    }
}

if (!function_exists('get_last_week_date')){
    //根据第几周获取当周的开始日期与最后日期
    function get_last_week_date($date){ 
        $first=1; //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期
        $w=date('w',strtotime($date));  //获取当前周的第几天 周日是 0 周一到周六是 1 - 6
        $now_start=date('Y-m-d',strtotime("$date -".($w ? $w  : 7).' days')); //获取本周开始日期，如果$w是0，则表示周日，减去 6 天
        $last_start=date('Y-m-d',strtotime("$now_start - 6 days"));  //上周开始日期
        //获取上周起始日期
        $startdate = $last_start." 00:00:00";
        //获取本周起始日期
        $enddate = $now_start. " 23:59:59";
        
        return array($startdate,$enddate);     
    }
}

if (!function_exists('html_to_image')){
    /**
     * 获取页面截图（整张页面或者选择区域）
     * 
     * @param string $content 页面url或html
     * @param string $selector 区域选择标识
     * @param bool $with_auth 是否需要登陆验证
     * @return string|bool 成功时返回图片路径， 失败返回false
     */
    function html_to_image($content, $selector = null, $with_auth = false)
    {
        ini_set('max_execution_time', 360);
        ini_set('memory_limit', '2048M');

        $puppeteer = new \Nesk\Puphpeteer\Puppeteer;
        $browser = $puppeteer->launch([
            'executablePath' => '/usr/bin/chromium-browser',
            'args' => ['--no-sandbox', '--disable-setuid-sandbox'],
        ]);

        $page = $browser->newPage();
        $page->setViewport([
            'width' => 1280,
            'height' => 1024
        ]);

        if (strpos($content, 'http://') === 0) {
            $url = $content;
            // 获取授权信息
            if ($with_auth) {
                // 页面互动操作自带token
                $token = !key_exists('token', $_COOKIE) ? null : $_COOKIE['token'];
    
                // 非页面互动（如命令行操作）将手动获取token
                if (empty($token)) {
                    $user = [
                        'name' => 'bot',
                        'email' => 'bot',
                    ];
                    $password = \Illuminate\Support\Str::random(6);
                    $extra_info = [
                        'password' => bcrypt($password),
                        'password_expired' => \Carbon\Carbon::now()->addDays(config('api.password_expired'))->toDateTimeString(),
                        'remember_token' => \Illuminate\Support\Str::random(10),
                    ];
                    // 若使用Model会导致当前账户token失效退出
                    $result = \Illuminate\Support\Facades\DB::table('users')
                        ->where($user)
                        ->take(1)
                        ->count();
                    if ($result > 0) {
                        \Illuminate\Support\Facades\DB::table('users')
                            ->where($user)
                            ->update($extra_info);
                    } else {
                        \Illuminate\Support\Facades\DB::table('users')
                            ->insert(array_merge($user , $extra_info));
                    }
            
                    // 获取机器人用户token
                    $client = new \GuzzleHttp\Client([
                        'base_uri' => 'http://nginx',
                        'headers' => [
                            'Content-Type' => 'application/json; charset=UTF-8',
                        ],
                    ]);
                    $auth_url = '/api/oauth/token';
            
                    $params = array_merge(config('passport.proxy'), [
                        'username' => $user['email'],
                        'password' => $password,
                    ]);
            
                    $respond = $client->request('POST', $auth_url, [
                        'form_params' => $params,
                        'headers' => [
                            'Referer' => 'http://nginx' . $auth_url,
                        ],
                    ]);
                    if ($respond->getStatusCode() !== 401) {
                        $response = json_decode($respond->getBody()->getContents(), true);
                        $token = $response['token_type'] . ' ' . $response['access_token'];
                    } else {
                        // 获取授权失败，未能截图
                        return false;
                    }
                }
                $page->setCookie([
                    'name' => 'token',
                    'value' => $token,
                    'url' => $url,
                ]);
            }
            $page->goto($url, ['waitUntil' => 'networkidle0']);
        } else {
            $page->setContent($content, ['waitUntil' => 'networkidle0']);
        }
        $file_name = \Illuminate\Support\Str::random(40).'.png';
        $file_path = \Illuminate\Support\Facades\Storage::path('attach/'.$file_name);
        sleep(3);

        // 截图区域
        if ($selector) {
            $page->querySelectorEval('.ant-pro-fixed-header', Nesk\Rialto\Data\JsFunction::createWithParameters(['el'])->body("el.parentNode.removeChild(el)"));
            $page->querySelector($selector)->screenshot(['path' => $file_path]);
        } else {
            $page->screenshot(['path' => $file_path, 'fullPage' => true]);
        }
        $browser->close();
        compress_png($file_path, $file_path);
        return $file_path;
    }
}

if (!function_exists('compress_png')){
    function compress_png($source_png_file, $target_png_file, $max_quality = 90)
    {
        if (!file_exists($source_png_file)) {
            throw new Exception("File does not exist: $source_png_file");
        }

        // guarantee that quality won't be worse than that.
        $min_quality = 60;

        // '-' makes it use stdout, required to save to $compressed_png_content variable
        // '<' makes it read from the given file path
        // escapeshellarg() makes this safe to use with any path
        $compressed_png_content = shell_exec("pngquant --quality=$min_quality-$max_quality - < ".escapeshellarg($source_png_file));

        if (!$compressed_png_content) {
            throw new Exception("Conversion to compressed PNG failed. Is pngquant 1.8+ installed on the server?");
        }

        file_put_contents($target_png_file, $compressed_png_content);
    }
}

if (!function_exists('report_refresh_detail')){
    /**
     * 
     * @param $period string 周期字符串缩写，如 day，week|1, week|3, month|24, season|1|25
     */
    function report_refresh_detail($period)
    {
        $period = explode('|', $period);
        $refresh_period = !empty($period) ? $period[0] : 'week'; // 默认为周
        
        $need_refresh = false;
        switch($refresh_period){
            case 'season':
                $current_month = \Carbon\Carbon::now()->month;
                $current_day = \Carbon\Carbon::now()->day;
                $refresh_month = !empty($period) && (sizeof($period) > 1) ? $period[1] : 1; // 默认为周期第一月
                $refresh_day = !empty($period) && (sizeof($period) > 2) ? $period[2] : 26; // 默认为周期第一天
                $create_at = \Carbon\Carbon::now()->subMonths($current_month%3)->startOfDay()->addDays($refresh_day - 1);
                $need_refresh = $current_month%3 === $refresh_month && $current_day === $refresh_day;
                break;
            case 'month':
                $refresh_day = !empty($period) && (sizeof($period) > 1) ? $period[1] : 1; // 默认为周期第一天
                $create_at = \Carbon\Carbon::now()->startOfMonth()->addDays($refresh_day - 1);
                $current_day = \Carbon\Carbon::now()->day;
                $need_refresh = intval($current_day) === intval($refresh_day);
                break;
            case 'day':
                $create_at = \Carbon\Carbon::now()->startOfDay();
                $need_refresh = true;
                break;
            case 'week': 
            default:
                $refresh_day = !empty($period) && (sizeof($period) > 1) ? $period[1] : 1; // 默认为周期第一天
                $create_at = \Carbon\Carbon::now()->startOfWeek()->addDays($refresh_day - 1);
                $current_day = \Carbon\Carbon::now()->dayOfWeekIso;
                $need_refresh = intval($current_day) === intval($refresh_day);
                break;
        }
        return [
            'need_refresh' => $need_refresh,
            'create_at' => $create_at,
        ];
    }
}

if (!function_exists('wechat_bot')) {
    /**
     * 企业微信群机器人(目前暂时只支持图片发送)
     * 
     * @param mix $data 根据{$type}不同，数据也各不相同
     *      --image： （string）图片文件路径
     *      --text： （array）
     *      --markdown： （array）
     * @param string $bot_key  群机器人key
     * @param string $type  消息类型：['image', 'text', 'makrdown']
     * @return array
     */
    function wechat_bot($data, $bot_key, $type = 'image') {
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://qyapi.weixin.qq.com',
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
        ]);
        $url = '/cgi-bin/webhook/send?key=' . $bot_key;

        $params = [];
        switch($type) {
            case 'image':
                $params = [
                    'msgtype' => 'image',
                    'image' => [
                        'base64' => base64_encode(file_get_contents($data)),
                        'md5' => md5_file($data),
                    ],
                ];
                break;
            case 'text':
                $params = [
                    'msgtype' => 'text',
                    'text' => $data + [
                        'content' => '',
                        'mentioned_list' => [],
                        'mentioned_mobile_list' => [],
                    ],
                ];
                break;
            case 'markdown':
                $params = [
                    'msgtype' => 'markdown',
                    'markdown' => $data + [
                        'content' => ''
                    ],
                ];
                break;
        }
        

        $response = $client->request('POST', $url, [
            'json' => $params,
        ]);
        sleep(3);

        return [
            'code' => $response->getStatusCode(),
            'msg' => $response->getHeaders(),
        ];
    }
}

if (!function_exists('sqa')) {
    function sqa() {
        $sqa_department = \Illuminate\Support\Facades\DB::table('ldap_departments')
            ->whereIn('name', ['软件质量组', '软件SQA组'])
            ->where('status', 1)
            ->pluck('id');
        if (!empty($sqa_department)) {
            $sqa = \Illuminate\Support\Facades\Cache::get("sqa");
            if (!$sqa) {
                $sqa = \Illuminate\Support\Facades\DB::table('ldap_users')
                    ->whereIn('department_id', $sqa_department)
                    ->where('status', '<>', 0)
                    ->select("name", "mail AS email", "uid")
                    ->get()
                    ->toArray();
                $sqa = array_map(function($item){
                    return (array)$item;
                }, $sqa);
                \Illuminate\Support\Facades\Cache::put('sqa', $sqa, 24*60);
            }
            return $sqa;
        }
        throw(new Exception("无法获取SQA部门信息！"));
    }
}
