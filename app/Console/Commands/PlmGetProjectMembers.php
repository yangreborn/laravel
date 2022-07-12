<?php

namespace App\Console\Commands;

use App\Models\PlmProjectMember;
use Illuminate\Console\Command;
use SoapClient;

class PlmGetProjectMembers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plm:get_project_members';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '通过plm接口获取plm项目成员';

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
        //
        $this->info('start getting plm project members ==>');
        ini_set('default_socket_timeout', 360);
        $soap = new SoapClient(env('PLM_API'));
        $result = $soap->getProjectMember2([])->return;
        $result = json_decode($result, true);
        $bar = $this->output->createProgressBar(sizeof($result));
        foreach($result as $item) {
            $bar->advance();
            PlmProjectMember::query()->updateOrCreate([
                'email' => $item['MEMBERACCOUNT'],
                'project_uid' => $item['PROJECTCODE'],
            ], [
                'pos' => $item['MEMBERNAME'],
            ]);
        }
        $bar->finish();
        $this->info("\n" . '<== finish to get plm project members' . "\n");
    }
}
