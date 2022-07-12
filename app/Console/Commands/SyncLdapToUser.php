<?php

namespace App\Console\Commands;

use App\Models\LdapUser;
use App\Models\User;
use Illuminate\Console\Command;

class SyncLdapToUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:ldap_to_user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步ldap数据至度量平台users表（包括用户二级部门信息）';

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
     * @return mixed
     */
    public function handle()
    {
        $this->info('Sync User Start ==>');
        $start = microtime(true);
        LdapUser::syncToUser();
        $this->comment('LdapUser::syncToUser -> 共耗时：' . round((microtime(true) - $start), 2) . 's');

        $start = microtime(true);
        User::syncUserWithLdap();
        $this->comment('User::syncUserWithLdap -> 共耗时：' . round((microtime(true) - $start), 2) . 's');
        $this->info('Sync User End <==');
    }
}
