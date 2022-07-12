<?php

namespace App\Console\Commands;

use App\Mail\SyncProjectToolDataNotification;
use App\Models\Diffcount;
use App\Models\Pclint;
use App\Models\Phabricator;
use App\Models\Project;
use Illuminate\Console\Command;
use App\Models\ProjectTool;
use App\Models\Tapd;
use App\Models\ToolPlmProject;
use App\Models\TscanCode;
use Illuminate\Support\Facades\Mail;

class SyncProjectToolData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:project_tool {tool* : options, \'plm\', \'tapd\', \'pclint\', \'diffcount\', \'phabricator\', or all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'syncronized project linked tool info';

    private $sync_failed;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->sync_failed = [];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info(">>>>>> begin    @ ".date('Y-m-d H:i:s')." >>>>>>");
        $argument = $this->argument('tool');
        in_array('plm', $argument) && $this->projectToolPlm();
        in_array('tapd', $argument) && $this->projectToolTapd();
        in_array('pclint', $argument) && $this->projectToolPclint();
        in_array('tscan', $argument) && $this->projectToolTscan();
        in_array('diffcount', $argument) && $this->projectToolDiffcount();
        in_array('phabricator', $argument) && $this->projectToolPhabricator();

        if (!empty($this->sync_failed)) {
            $mail = new SyncProjectToolDataNotification(['data' => $this->sync_failed]);
            $to = config('api.dev_email');
            $cc = config('api.test_email');
            if (config('app.env') === 'production') {
                $to = sqa();
                $cc = config('api.dev_email');
            }
            Mail::to($to)->cc($cc)->send($mail);
        }
        $this->info("<<<<<< end      @ ".date('Y-m-d H:i:s')." <<<<<<");
    }

    private function projectToolPlm()
    {
        $res = ToolPlmProject::query()->get();
        foreach ($res as $item) {
            if (!empty($item->relative_id)) {
                ProjectTool::updateOrCreate([
                    'relative_id' => $item->id,
                    'relative_type' => 'plm',
                ], [
                    'project_id' => $item->relative_id,
                ]);
            }
        }
        $this->line('   ==> syncronized plm finished!');
    }
    private function projectToolTapd()
    {
        $res = Tapd::query()->get();
        foreach ($res as $item) {
            if (!empty($item->relative_id)) {
                ProjectTool::updateOrCreate([
                    'relative_id' => $item->project_id,
                    'relative_type' => 'tapd',
                ], [
                    'project_id' => $item->relative_id,
                ]);
            }
        }
        $this->line('   ==> syncronized tapd finished!');
    }
    private function projectToolPclint()
    {
        $res = Pclint::query()->whereNotNull('project_id')->get();
        foreach($res as $item) {
            $flow_tool_info = $item->flowToolInfo;
            if ($flow_tool_info) {
                ProjectTool::updateOrCreate([
                    'relative_id' => $flow_tool_info->version_flow_id,
                    'relative_type' => 'flow',
                    'project_id' => $item->project_id,
                ], [
                    'status' => 1,
                ]);
            } else {
                $this->setSyncFailedData('pclint', $item);
            }
        }
        $this->line('   ==> syncronized pclint finished!');
    }
    private function projectToolTscan()
    {
        $res = TscanCode::query()->whereNotNull('project_id')->get();
        foreach($res as $item) {
            $flow_tool_info = $item->flowToolInfo;
            if ($flow_tool_info) {
                ProjectTool::updateOrCreate([
                    'relative_id' => $flow_tool_info->version_flow_id,
                    'relative_type' => 'flow',
                    'project_id' => $item->project_id,
                ], [
                    'status' => 1,
                ]);
            } else {
                $this->setSyncFailedData('tscan', $item);
            }
        }
        $this->line('   ==> syncronized tscan finished!');
    }
    private function projectToolDiffcount()
    {
        $res = Diffcount::query()->whereNotNull('project_id')->get();
        foreach($res as $item) {
            $flow_tool_info = $item->flowToolInfo;
            if ($flow_tool_info) {
                ProjectTool::updateOrCreate([
                    'relative_id' => $flow_tool_info->version_flow_id,
                    'relative_type' => 'flow',
                    'project_id' => $item->project_id,
                ], [
                    'status' => 1,
                ]);
            } else {
                $this->setSyncFailedData('diffcount', $item);
            }
        }
        $this->line('   ==> syncronized diffcount finished!');
    }
    private function projectToolPhabricator()
    {
        $res = Phabricator::query()->whereNotNull('project_id')->get();
        foreach($res as $item) {
            $flow_tool_info = $item->flowToolInfo;
            if ($flow_tool_info) {
                ProjectTool::updateOrCreate([
                    'relative_id' => $flow_tool_info->version_flow_id,
                    'relative_type' => 'flow',
                    'project_id' => $item->project_id,
                ], [
                    'status' => 1,
                ]);
            } else {
                $this->setSyncFailedData('phabricator', $item);
            }
        }
        $this->line('   ==> syncronized phabricator finished!');
    }
    private function setSyncFailedData($tool, $tool_info)
    {
        $project = Project::query()->with(['sqa'])->find($tool_info->project_id);
        $this->sync_failed[] = [
            'tool_type' => $tool,
            'project' => $project ? $project->name : '',
            'tool' => $tool_info->job_name,
            'sqa' => $project ? $project->sqa['name'] : '',
        ];
    }
}
