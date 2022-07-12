<?php

namespace App\Jobs;

use App\Mail\tapdNotification;
use App\Models\TapdNotificationData;
use App\Models\User;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendTapdNotificationMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
        $contacts = TapdNotificationData::query()
            ->selectRaw('DISTINCT receiver')
            ->where('year_week', Carbon::now()->format('YW'))
            ->pluck('receiver')
            ->toArray();

        $this->sendMail($contacts);
    }

    private function sendMail($contact) {
        echo "邮件发送中...\n";
        foreach($contact as $item) {
            $mail = new tapdNotification(['receiver' => $item]);
            $user = User::query()
                ->select('email', 'remember_token AS token', 'kd_uid')
                ->where('email', $item)
                ->where('status', 1)
                ->first();
            if ($user) {
                $to = config('app.env') !== 'production' ? config('api.dev_email') : $item;
                try {
                    Mail::to($to)
                        ->send($mail);
                } catch(Exception $err) {
                    $info = [
                        'msg' => $err->getMessage(),
                        'mail' => 'tapdNotification',
                        'to' => $to,
                    ];
                    report(new Exception(json_encode($info, JSON_UNESCAPED_UNICODE)));
                }
            }
        }
        echo "邮件发送结束！\n";
    }
}
