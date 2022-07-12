<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CommandController extends ApiController
{

    private $commands = [
        [
            'key' => 'GetPlmData',
            'label' => '更新plm数据',
            'options' => [
                '--all' => true,
            ],
        ],
        [
            'key' => 'Get2weekData',
            'label' => '更新双周报数据',
            'options' => [],
        ],
        [
            'key' => 'GetMonthData',
            'label' => '更新月报数据',
            'options' => [],
        ],
        [
            'key' => 'PhabFetchData',
            'label' => '更新phabricator数据',
            'options' => [],
        ],
        [
            'key' => 'cache:clear',
            'label' => '清除缓存',
            'options' => [],
        ],
        [
            'key' => 'queue:restart',
            'label' => '队列重启',
            'options' => [],
        ],
    ];
    public function commandList()
    {
        return $this->success('获取可用命令列表成功！', $this->commands);
    }

    public function commandRun(Request $request)
    {
        $command_name = $request->command ?? '';
        $key = array_search($command_name, array_column($this->commands, 'key'));
        if ($key !== false) {
            $command = $this->commands[$key];
            Artisan::queue($command['key'], $command['options']);
            return $this->success('命令触发成功！');
        }
        return $this->failed('命令触发失败！');
    }
}
