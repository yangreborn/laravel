<?php

namespace App\Console\Commands;

use App\Models\CodeReviewSearchCondition;
use App\Models\Department;
use App\Models\Phabricator;
use App\Models\UserProject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CommonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'common:synchronize_code_review_condition';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize the data to table `code_review_search_conditions` from table `user_projects`';

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
        $user_projects = UserProject::all();

        collect($user_projects)->each(function ($item){
            $department_info = Department::query()
                ->where('id', $item['department_id'])
                ->first(['id', 'parent_id']);
            $department_id = [$department_info['parent_id'], $department_info['id']];
            $projects = DB::table('tool_phabricators')
                ->select(['project_id as key', 'job_name as label'])
                ->whereIn('project_id', $item['projects'])
                ->get()->map(function ($item){
                    $item->key = (string)$item->key;
                    return $item;
                })->toArray();
            $members_after_format = [];
            foreach ($item['members'] as $k => $v){
                if (in_array($k, array_column($projects, 'key'))){
                    $members_after_format[] = (string)$k;
                    $members_after_format = array_merge($members_after_format, array_map(function ($member) use ($k){
                        return $k . '-' . $member;
                    }, $v));
                }
            }
            if (!empty($members_after_format)){
                CodeReviewSearchCondition::updateOrCreate([
                    'title' => $item['name'],
                    'user_id' => $item['user_id'],
                ], [
                    'user_id' => $item['user_id'],
                    'title' => $item['name'],
                    'conditions' => json_encode([
                        'department_id' => $department_id,
                        'to_users' => [],
                        'cc_users' => [],
                        'projects' => $projects,
                        'members' => $members_after_format,
                        'review_tool_type' => (string)$item['tool_type'],
                        'validity' => false,
                    ]),
                ]);
            }
        });
    }
}
