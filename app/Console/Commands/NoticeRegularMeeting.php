<?php

namespace App\Console\Commands;

use App\Models\RegularMeeting;
use App\Models\User;
use Illuminate\Console\Command;

class NoticeRegularMeeting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notice:regular_meeting';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '例行会议通知';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('>>>>>>begin>>>>>>');
        $this->sendInfo();
        $this->info('<<<<<<end<<<<<<');
    }

    private function sendInfo(){
        $message = $this->getInfo();
        if (!empty($message)){
            wechat_bot($message['data'], $message['key'], $message['type']);
        }
    }

    private function getInfo(){
        $result = [];
        $at_string = '';
        $current_order = 0;
        $deadline = date('Y-m-d');
        $meeting_day = date('Y-m-d', strtotime('1 day'));
        $holiday = config('api.regular_meeting.holiday');
        $order = config('api.regular_meeting.odrer');
        $last_order = RegularMeeting::query()->orderBy('created_at', 'DESC')->value('order');
        if ($last_order){
            if ($last_order === 9){
                $current_order = 1;
            }else {
                $current_order = $last_order + 1;
            }
        }else{
            $current_order = 1;
        }
        $email = $order[$current_order];
        $name = User::query()->where('email', $email)->value('name');
        $users = User::query()
            ->where('email', $email)
            ->pluck('kd_uid')
            ->toArray();
        $at_string = array_map(function ($item) {
            return '<@' . $item . '>';
        }, $users);
        $at_string = implode(',', $at_string);

        $meeting_room = config('api.regular_meeting.meeting_room.Shanghai') . ' ' . config('api.regular_meeting.meeting_room.Suzhou');

        $content = <<<markdown
### 会议通知\n
> 各位好：
> 例会信息如下： 
> 时间： <font color="comment">$meeting_day  周三 下午13:30</font>
> 地点： <font color="comment">$meeting_room</font>
> 主持人: <font color="comment">$at_string</font>
> 请大家及时上传周报及Redmine问题更新。谢谢！\n
markdown;

        if (!in_array($deadline, $holiday)){
            $result = [
                'data' => ['content' => $content],
                'key' => config('wechat.wechat_robot_key.regular_meeting'),
                'type' => 'markdown',
            ];

            RegularMeeting::create([
                'kd_uid' => $users[0], 
                'name' => $name,
                'email' => $email,
                'order' => $current_order,
            ]);
        }else {
            $result = [];
        }
        return $result;
    }
}