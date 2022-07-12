<?php
namespace App\Console\Commands;

use App\Jobs\SyncLdapDepartment;
use App\Jobs\SyncLdapUser;
use Illuminate\Console\Command;

class SyncLdapData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:ldap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步Ldap数据';

    private $departments = [];
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
        $this->info('Sync Department Start ==>');
        SyncLdapDepartment::dispatch();
        $this->info('Sync Department End <==' . "\n");

        $this->info('Sync Current User Start ==>');
        SyncLdapUser::dispatch();
        $this->info('Sync Current User End <==');
    }
}
