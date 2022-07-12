<?php
#
namespace App\Console\Commands;

use App\Mail\plmBugProcessNotification;
use App\Mail\plmBugProcessReport;
use App\Models\Plm;
use App\Models\ToolPlmGroup;
use App\Models\ToolPlmProduct;
use App\Models\ToolPlmProject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RemoveUselessGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:groups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '移除无效的负责小组，即不在bug表中存在的小组。';
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
        $this->info(">>>>>>begin>>>>>>\n");
        $this->removeUselessGroups();
        $this->info("\n<<<<<<end<<<<<<\n");
    }

    private function removeUselessGroups(){
        $groups = ToolPlmGroup::withTrashed()->get();
        $bar = $this->output->createProgressBar(count($groups));
        foreach ($groups as $group){
            $bar->advance();
            $count = Plm::withTrashed()->where('group_id', $group->id)->count();
            if ($count == 0){
                $group->forceDelete();
                $this->line("\n小组--" . $group->name . "已被删除！\n");
            }
        }
        $bar->finish();
    }
}
