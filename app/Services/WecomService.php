<?php
namespace App\Services;

use App\Models\Notification;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

// 企业微信接口使用限制 https://work.weixin.qq.com/api/doc/90000/90139/90312
class WecomService {
    private $base_uri = 'https://qyapi.weixin.qq.com';
    private $access_token;
    private $url;
    private $header = [
        'Content-Type' => 'application/json; charset=UTF-8',
    ];
    private $request_data = [];

    public function __construct() {
        $this->access_token = $this->getAccessToken();
    }

    public function updateTaskCardMessage($task_id, $clicked_key, $userids = []) {
        $this->url = '/cgi-bin/message/update_taskcard?access_token=' . $this->access_token;
        $params = [
            'userids' => !empty($userids) ? $userids : config('wechat.dev'),
            'agentid' => config('wechat.agent_id'),
            'task_id' => $task_id,
            'clicked_key' => $clicked_key,
        ];
        $this->request_data = [
            'json' => $params,
        ];
        return $this->response('POST');
    }

    public function sendAppMessage($data, $type, $to_users = []) {
        $this->url = '/cgi-bin/message/send?access_token=' . $this->access_token;
        $params = [
            'touser' => !empty($to_users) ? implode('|', $to_users) : implode('|', config('wechat.dev')),
            'agentid' => config('wechat.agent_id'),
            'msgtype' => $type,
            'enable_id_trans' => 1,
        ];
        switch($type) {
            case 'text':
                $params += [
                    'text' => [
                        'content' => $data,
                    ],
                ];
                break;
            case 'markdown':
                $params += [
                    'markdown' => [
                        'content' => $data
                    ],
                ];
                break;
            case 'textcard':
                $params += [
                    'textcard' => $data + [
                        "btntxt" => "更多"
                    ],
                ];
                break;
            case 'taskcard':
                $params += [
                    'taskcard' => [
                        "title" => "任务卡片测试",
                        "description" => "这是应用消息中任务卡片的测试示例，请忽略",
                        "task_id" => uniqid(),
                        "btn" => [
                            [
                                "key" => "approve",
                                "name" => "接受",
                                "replace_name" => "已接受",
                                "color" => "red",
                                "is_bold" => true
                            ], [
                                "key" => "reject",
                                "name" => "驳回",
                                "replace_name" => "已驳回"
                            ],
                        ]
                    ],
                ];
                break;
        }
        $this->request_data = [
            'json' => $params,
        ];

        $info = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        try {
            $status = 1;
            $res = $this->response('POST');
        } catch(Exception $e) {
            $status = 0;
        } finally {
            Notification::create([
                'name' => sprintf('%s::%s', $info[1]['class'], $info[1]['function']),
                'type' => 'wecom',
                'receiver' => '',
                'content' => serialize($this),
                'status' => $status
            ]);
        }

        return $res;
    }

    public function getAppDetail() {
        $this->url = '/cgi-bin/agent/get?access_token=' . $this->access_token . '&agentid=' . config('wechat.agent_id');
        return $this->response('POST');
    }

    private function getAccessToken() {
        $access_token = Cache::get('wechat_app_sqa_access_token');
        if (!empty($access_token)) {
            return $access_token;
        }
        $this->url = '/cgi-bin/gettoken?corpid=' . config('wechat.company_pid') . '&corpsecret=' . config('wechat.app_sqa_secret');
        $response = $this->response('GET');
        Cache::put('wechat_app_sqa_access_token', $access_token, Carbon::now()->addSeconds(7000));
        return $response->access_token;
    }

    /**
     * 获取接口返回信息
     * @return Object|Exception 
     */
    private function response($method) {
        $client = new Client([
            'base_uri' => $this->base_uri,
            'headers' => $this->header,
        ]);
        $response = $client->request($method, $this->url, $this->request_data);
        if ($response->getStatusCode() === 200) {
            $response = json_decode($response->getBody()->getContents());
            if ($response->errcode === 0 && $response->errmsg === 'ok') {
                return $response;
            } else {
                throw new \Exception($response->errmsg);
            }
        } else {
            throw new \Exception('Can\'t reach WeCom server!');
        }
    }
}