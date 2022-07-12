<?php

namespace App\Console\Commands;

use App\Models\TapdWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TransformTapdWorkflow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transform:tapd-workflow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'transform tapd workflow';

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
        $source_data = DB::table('tapd_status')->get()->toArray();
        foreach ($source_data as $item) {
            $item = json_encode($item);
            $item = json_decode($item, true);
            $workspace_id = $item['workspace_id'];
            unset($item['workspace_id'], $item['status_type']);
            $filtered_item = array_filter($item, function ($v){
                return !empty($v);
            });
            foreach ($filtered_item as $k=>$v) {
                TapdWorkflow::query()->updateOrCreate([
                    'project_id' => $workspace_id,
                    'tapd_status' => $k,
                ], ['custom_status' => $v]);
            }
        }
    }
}
