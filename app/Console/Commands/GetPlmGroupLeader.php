<?php

namespace App\Console\Commands;

use App\Mail\unrecognizedGroupNotification;
use App\Models\ToolPlmGroup;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Overtrue\Pinyin\Pinyin;

class GetPlmGroupLeader extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'common:get_plm_group_leader {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get the user_id of plm group leader';

    private $_is_production = false;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->_is_production = env('APP_ENV', 'local') === 'production';
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        $this->autoLinkGroups();
    }


    /**
     * 自动匹配负责小组负责人与度量平台用户
     *
     *匹配原则：
     *   1、将负责小组中的中文人名转化为拼音或者直接截取小组名称中自带的字母组合（若有）；
     *   2、用转化得来的拼音或字母组合在用户表中进行模糊查询；
     *   3、查询结果唯一则进行关联，不唯一或不存在则抛出进行手动匹配
     */
    private function autoLinkGroups(){
        $groups = ToolPlmGroup::query()
            ->when($this->option('all') === false, function($query) {
                $query->whereNull('user_id');
            })
            ->get();
        $unrecognized = [];
        $matched_group_ids = [];
        foreach ($groups as $group){
            if (strpos($group->name, '-') !== false){
                $short_name = \Illuminate\Support\Arr::last(explode('-', $group->name));
                $short_name = str_replace(['（', '）'], ['(', ')'], $short_name);
                $matches = [];
                if (preg_match('/\((.*?)\)/', $short_name, $matches)){
                    $pinyin = $matches[1];
                } else {
                    $pinyin = implode('', (new Pinyin())->convert($short_name));
                }
                $users = User::query()->where('email', 'like', "$pinyin%")->get();
                if (sizeof($users) === 1){
                    $this->info('Match: ' . $pinyin);
                    $user = $users[0];
                    $group->user_id = $user->id;
                    if (!empty($user->departments) && sizeof($user->departments['ids']) === 1) {
                        $group->relative_id = $user->departments['ids'][0];
                    }
                    $group->save();
                    $matched_group_ids[] = $group->id;
                } else {
                    $unrecognized[] = $group;
                    $this->error('Fail: ' . $group->name . ' => ' . $pinyin);
                }
            }
        }

        // $matched = [];
        // if (!empty($matched_group_ids)){
        //     $matched = ToolPlmGroup::query()->with('user')->whereIn('id', $matched_group_ids)->get()->toArray();
        // }

        // if (!empty($unrecognized) || !empty($matched_group_ids)){
        //     // 邮件通知手动关联未自动识别小组
        //     $notification = new unrecognizedGroupNotification([
        //         'unrecognized' => $unrecognized,
        //         'matched' => $matched,
        //     ]);
        //     $sqa = sqa();
        //     $to = $this->_is_production ? $sqa : config('api.dev_email');
        //     Mail::to($to)->cc(config('api.dev_email'))->send($notification);
        // }
        $this->info('自动匹配结束！');
    }


}
