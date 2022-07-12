<?php

namespace App\Console\Commands;

use App\Models\Plm;
use App\Models\ToolPlmProject;
use App\Models\Tapd;
use App\Models\TapdBug;
use App\Models\Project;
use App\Models\ProjectTool;
use App\Models\Department;
use App\Models\LdapUser;
use App\Models\User;
use App\Mail\NotUpdatedNotification;
use Illuminate\Console\Command;
use Illuminate\Foundation\Console\EventMakeCommand;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class SendNotUpdatedNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:Tapd_Plm_NoUpdated';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计已关联的Tapd与Plm项目,两周内未提交新bug,向项目经理推送项目信息,并统计一份全部的至研发质量处';

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
        $this->info('>>>>>>begin>>>>>>');
        $this->sendMail();
        $this->info('<<<<<<end<<<<<<');

    }

    private function sendMail(){
        $data = $this->getAllData();
        $email_to_inside = array_keys(array_flip(array_column($data["inside"], "sqa_emails")));
        $email_cc_inside = config('api.chao_emails');
        $mail = new NotUpdatedNotification([
            'addressee' => "inside",
            'data' => $data["inside"],
        ]);
        Mail::to($email_to_inside)->cc($email_cc_inside)->send($mail);
    }

    private function getAllData(){
        $data = [];
        $now = Carbon::now();
        $deadline = date('Y-m-d H:i:s', strtotime('-2 week'));
        $project_tools = ProjectTool::query()
                    ->where('relative_type', '<>', 'flow')
                    ->get();
        foreach($project_tools as $project_tool){
            $project = Project::query()->where('id', $project_tool['project_id'])->get();
            if (!empty($project)){
                foreach ($project as $item){
                    if($item['stage'] != 5){
                        $project = $item['name'];
                        $department = Department::query()->where('id', $item['department_id'])->value('name');
                        $parent_id = Department::query()->where('id', $item['department_id'])->value('parent_id');
                        if ($parent_id){
                            $product_line = Department::query()->where('id', $parent_id)->value('name');
                        }
                        $sqa_info = User::query()->select(['name', 'email'])->find($item['sqa_id']);
                        $sqa_emails = !empty($sqa_info['email']) ? $sqa_info['email'] : [];
                        $sqa = $item->sqa->name;

                        $supervisor_info = User::query()->select(['name', 'email'])->find($item['supervisor_id']);
                        $supervisor_emails = !empty($supervisor_info['email']) ? $supervisor_info['email'] : [];
                        $supervisor = $item->supervisor->name;

                        $project_tool->relative;
                        $last_modified = $this->getData($project_tool);
                        $last_modified_bak = date('Y-m-d', strtotime($last_modified));
                        if ($last_modified){
                            if ($deadline > $last_modified){
                                $now = new Carbon($now);
                                $last_modified = new Carbon($last_modified);
                                $diff = $now->diffInDays($last_modified);
                                $last_modified_status = $diff;
                            }else {
                                $last_modified_status = "正常";
                            }
                        }else {
                            $last_modified_status = "尚未提交";
                        }
                        $data['inside'][] = [
                            'tool_project' => $project_tool['relative']['name'],
                            'parent_project' => $project,
                            'product_line' => $product_line,
                            'department' => $department,
                            'relative_type' => $project_tool['relative_type'],
                            'supervisor' => $supervisor,
                            'sqa' => $sqa,
                            'last_modified' => $last_modified_bak,
                            'last_modified_status' => $last_modified_status,
                            'sqa_emails' => $sqa_emails,
                        ];
                        $data[$supervisor_emails][] = [
                            'tool_project' => $project_tool['relative']['name'],
                            'parent_project' => $project,
                            'product_line' => $product_line,
                            'department' => $department,
                            'relative_type' => $project_tool['relative_type'],
                            'supervisor' => $supervisor,
                            'sqa' => $sqa,
                            'last_modified' => $last_modified_bak,
                            'last_modified_status' => $last_modified_status,
                            'sqa_emails' => $sqa_emails,
                        ];
                    }
                }
            }
        }
        foreach ($data as &$value){
            if (count($value) > 1){
                $collection = collect($value);
                $sorted = $collection->sortByDesc(function ($product, $key) {
                    if ($product['last_modified_status'] !== '正常' and $product['last_modified_status'] !== '尚未提交'){
                        $product['last_modified_status'] += 100;
                    }else {
                        $product['last_modified_status'] = 100;
                    }
                    return $product['sqa'].$product['supervisor'].$product['product_line'].$product['department'].$product['last_modified_status'].$product['relative_type'];
                });
                $value = $sorted->values()->all();
            }
        }
        return $data;
    }

    private function getData($project_tool){
        switch ($project_tool['relative_type']){
            case 'plm':
                $last_modified = Plm::query()
                                    ->where('project_id', $project_tool['relative']['id'])
                                    ->orderBy('create_time', 'DESC')
                                    ->value('create_time');
                
                break;
            case 'tapd':
                $last_modified = TapdBug::query()
                                    ->where('workspace_id', $project_tool['relative']['project_id'])
                                    ->orderBy('created', 'DESC')
                                    ->value('created');
                break;
            default:
                break;
        }
        return $last_modified;
    }
}