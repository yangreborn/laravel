<?php

namespace App\Providers;

use App\Events\MailSendResult;
use App\Models\Notification;
use App\Models\Phabricator;
use App\Models\Project;
use App\Models\User;
use App\Observers\PhabricatorObserver;
use App\Observers\ProjectObserver;
use App\Services\WecomService;
use Exception;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Project::observe(ProjectObserver::class);
        Phabricator::observe(PhabricatorObserver::class);

        Relation::morphMap([
            'plm' => 'App\Models\ToolPlmProject',
            'tapd' => 'App\Models\Tapd',
            'flow' => 'App\Models\VersionFlow',

            'pclint' => 'App\Models\Pclint',
            'diffcount' => 'App\Models\Diffcount',
            'tscancode' => 'App\Models\TscanCode',
            'phabricator' => 'App\Models\Phabricator',
            'findbugs' => 'App\Models\Findbugs',
            'eslint' => 'App\Models\Eslint',
            'compile' => 'App\Models\Compile',
            'cloc' => 'App\Models\Cloc',
        ]);

        // queue failed notification
        Queue::failing(function (JobFailed $event) {
            $description = $event->exception->getMessage() ?? '';
            try {
                throw($event->exception);
            } catch(Exception $e) {}

            $job = json_decode($event->job->getRawBody(), true);
            $command = unserialize($job['data']['command']);
            if (property_exists($command, 'mailable')) {
                $this->mailResultNotification($command->mailable, 'failed', $description);
            }
                
        });

        // queue after notification
        Queue::after(function (JobProcessed $event) {
            $job = json_decode($event->job->getRawBody(), true);
            $command = unserialize($job['data']['command']);
            if (property_exists($command, 'mailable')) {
                $this->mailResultNotification($command->mailable, 'success');
            }
        });
    }

    private function mailResultNotification(Mailable $mail, $status = 'success', $message = '') {
        Notification::create([
            'name' => get_class($mail),
            'type' => 'mail',
            'receiver' => '',
            'content' => serialize($mail),
            'status' => $status === 'success' ? 1 : 0
        ]);
        $user_id = property_exists($mail, 'user_id') ? $mail->user_id : null;
        $subject = $mail->subject;
        if ($user_id && $subject) {
            $color = 'comment';
            switch($status) {
                case 'success':
                    $status_text = '??????';
                    $color = 'info';
                    broadcast(new MailSendResult([
                        'user_id' => $user_id,
                        'subject' => $subject,
                        'description' => '?????????????????????',
                        'type' => 'success',
                    ]));
                    break;
                case 'failed':
                    broadcast(new MailSendResult([
                        'user_id' => $user_id,
                        'subject' => $subject,
                        'description' => !empty($message) ? $message : '?????????????????????',
                        'type' => 'error',
                    ]));
                    $status_text = '??????';
                    $color = 'warning';
                    break;
            }
            $user = User::query()->find($user_id);
            if (!empty($user)) {
                $today = Carbon::now()->toDateTimeString();
                $content = <<<markdown
### ????????????????????????\n
> ??????????????? <font color="comment">??? $subject ???</font>
> ??????????????? <font color="comment">$today</font>
> ??????????????? <font color="$color">$status_text</font>\n
markdown;
                $wecom = new WecomService();
                $wecom->sendAppMessage($content, 'markdown', [$user->kd_uid]);
            }
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
