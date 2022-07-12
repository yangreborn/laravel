<?php

namespace App\Console\Commands;

use App\Models\LdapDepartment;
use Illuminate\Console\Command;

class syncLdapToDepartment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:ldap_to_department';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步指定部门数据至度量平台部门表';

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
        $this->info('Sync Department Start ==>');
        $start = microtime(true);
        LdapDepartment::syncToDepartment();
        $this->comment(' -> 共耗时：' . round((microtime(true) - $start), 2) . 's');
        $this->info('Sync Department End <==');
    }
}
