<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectExpectIndexs;
use App\Models\ProjectIndexs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TransformProjectIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transform:project-index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'modify current indexs of project accroding to tools deployed';

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
        $arr = ProjectExpectIndexs::all();
        $index = config('api.project_index');
        foreach($arr as $item) {
            $project_id = $item->project_id;
            $project = Project::find($project_id);
            $project_tools = $project->tools;
            $project_tools = array_map(function($v){
                return $v['type'];
            }, (array)$project_tools);
            $project_index = $item->expect_index;
            $project_index_format = array_map(function($v) use($index, $project_tools) {
                $tool = $index[$v['key']]['tool'];
                $v['tool'] = $tool;
                if (!empty($tool)) {
                    $project_tools_filter = array_filter($tool, function($v) use($project_tools) {
                        return in_array($v, $project_tools);
                    });
                    if ($v['status'] === 1) {
                        $v['status'] = sizeof($project_tools_filter) > 0 ? 1 : 0;
                    }
                }
                return $v;
            }, $project_index);

            $this->info("Project ID: " . $item->project_id);
            $modify_indexs = [];
            array_map(function($origin, $current) use(&$modify_indexs) {
                if ($origin['status'] !== $current['status']) {
                    $this->error("Index: " . $origin['key'] . " | Origin: " . $origin['status'] . " | Current: " . $current['status']);
                    $modify_indexs[] = $origin['key'];
                } else {
                    $this->info("Index: " . $origin['key'] . " | Origin: " . $origin['status'] . " | Current: " . $current['status']);
                }
            }, $item->expect_index, $project_index_format);
            if (!empty($modify_indexs)) {
                $this->info("Modified Indexs: " . implode(',', $modify_indexs));
                $modify_indexs_format = array_map(function($v) {
                    return '\'$.' . $v . '\'';
                }, $modify_indexs);
                $json_field_remove_sql = implode(',', $modify_indexs_format);
                DB::table('project_indexs')->where('project_id', $item->project_id)->update([
                    'index' => DB::raw("json_remove(`index`, $json_field_remove_sql)")
                ]);
                unset($modify_indexs);
            }
            $this->info("======================================");
            $item->expect_index = $project_index_format;
            $item->save();
        }
    }
}
