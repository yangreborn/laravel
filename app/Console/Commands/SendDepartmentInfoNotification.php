<?php

namespace App\Console\Commands;

use App\Mail\DepartmentInfoNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDepartmentInfoNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:department_info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '度量平台部门信息校对邮件（内容为当前部门信息）';

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
     */
    public function handle()
    {
        //
        $mail = new DepartmentInfoNotification();
        Mail::to(sqa())->cc(config('api.dev_email'))->send($mail);
    }
}
