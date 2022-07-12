<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;
use Doctrine\Common\Cache\Version;
use Illuminate\Foundation\Auth\User as Authenticatable;


class ComprehensiveAnalyze extends Authenticatable
{
    //
    use HasApiTokens, Notifiable, SoftDeletes;

    private $report_condition;
    private $record;
    private $count_start;
    private $count_end;
    private $dataSummary = ['staticCheck'=>[],'reviewBelow60'=>[],'reviewBelow100'=>[],'commitNum'=>[],'rejectNum'=>[],'reject'=>[],'rejectSum'=>0];
    private $summary = ["staticExplain"=>"","review60Explain"=>"","review100Explain"=>"","tableNotes"=>""];
    private $nocommit = [];

    public function __construct($report_condition,$record)
    {
        $this->report_condition = $report_condition;
        $this->record = $record;
        // list('start' => $this->count_start, 'end' => $this->count_end) = $this->getPeriodDatetime();
    }

    public function initData()
    {
        $data = json_decode($this->record,true);
        if(!isset($data['data'])){
            return;
        }
        if(isset($data['data']['summary']) ){
            $this->summary = $data['data']['summary'];
        }
        if(isset($data['data']['summary']) ){
            $this->nocommit = $data['data']['nocommit'];
        }
    }

    public function getReportData()
    {
        $this->initData();
        $conditions = $this->report_condition['conditions'];
        $date=date('Y-m-d');  //当前日期
        $last_period = get_last_week_date($date);
        $last2_period = get_last_week_date($last_period[0]);
        $last3_period = get_last_week_date($last2_period[0]);
        $toolDatas = [];
        $attachDatas = [];
        $projects =  $conditions['projects'];
        $explain = $conditions['explain']??False;
        $sum = [
            'findbugsNum' => 0,
            'tscancodeNum' => 0,
            'eslintNum' => 0,
            'lastCommitNum' => 0,
            'last2CommitNum' => 0,
            'commitCompNum' => 0,
            'lastDealNum' => 0,
            'last2DealNum' => 0,
            'dealCompNum' => 0,
            'lastDealRate' => 0,
            'last2DealRate' => 0,
            'dealCompRate' => 0
        ];
        foreach($projects as $item){
            $project_ids = explode('-', $item);
            $project_id = (int)($project_ids[2]);
            $lastCommitNum = $lastDealNum = $last2CommitNum = $last2DealNum = 0;
            $findbugsNum = $tscancodeNum = $eslintNum = 0;
            $project_data = [];
            $project_res = Project::with(['department','supervisor','tools'])->where('id',$project_id)->get();
            // wlog('res',$project_res);
            $projectType = $project_res[0]['created_at'] > $last3_period[0] ? "new" : "old";
            $name = $project_res[0]->name;
            $supervisor = $project_res[0]->supervisor->name;
            $department = $project_res[0]->department->name;
            $tools = $project_res[0]->tools;
            if(empty($tools)){
                continue;
            }
            $toolDatas[$department] = $toolDatas[$department] ?? [];
            $toolDatas[$department][$supervisor] = $toolDatas[$department][$supervisor] ?? [];
            $toolDatas[$department][$supervisor][$name] = $toolDatas[$department][$supervisor][$name] ?? [];
            $attachDatas[$name] = $attachDatas[$name] ?? [];
            foreach($tools as $tool){//按流获取静态检查数据&&整理评审流数据
                switch($tool['type']){
                    case 'findbugs':
                        $findbugsNum = $findbugsNum??0;
                        $findbugsNum += FindbugsData::query()->where('tool_findbugs_id',$tool['tool_id'])->orderBy('id','desc')->take(1)->value('High');
                        break;
                    case 'tscancode':
                        $tscancodeNum = $tscancodeNum??0;
                        $res= TscancodeData::query()->selectRaw('(sum(nullpointer)+sum(bufoverrun)+sum(memleak)+sum(compute)+sum(logic)+sum(suspicious)) as sum_data')
                        ->where('tool_tscancode_id',$tool['tool_id'])->orderBy('id','desc')->take(1)->get();
                        $tscancodeNum += $res[0]['sum_data'];
                        break;
                    case 'eslint':
                        $eslintNum = $eslintNum??0;
                        $eslintNum += EslintData::query()->where('tool_eslint_id',$tool['tool_id'])->orderBy('id','desc')->take(1)->value('error');
                        break;
                    case 'phabricator':
                        $project_data[$project_res[0]->id]['ids'][] = $tool['tool_id'];
                    default:
                        break;
                }
            }
            $last_res = PhabCommit::previewData($project_data,$last_period[0],$last_period[1],1,True);
            foreach($last_res['table1'] as $item){
                $lastCommitNum += $item['allCommits'];
                $lastDealNum += $item['allDeals'];
            }
            $lastDealRate = $lastCommitNum ? round(100 * $lastDealNum / $lastCommitNum ,2) : 0;
            if($lastDealRate < 100){
                // wlog('table4',$last_res['table4']);
                foreach($last_res['table4'] as $repo=>$item){ 
                    if($item[0]['reviewer'] == '无评审数据'){
                       continue; 
                    }
                    $attachDatas[$name][$repo] = $attachDatas[$name][$repo] ?? [];
                    foreach($item as $value){
                        if($value['reviewDealrate']<100){
                            $attachDatas[$name][$repo][] = $value;
                        }     
                    }
                    if(empty($attachDatas[$name][$repo])){
                        unset($attachDatas[$name][$repo]);
                    }
                }
            }
            $last2_res = PhabCommit::previewData($project_data,$last2_period[0],$last2_period[1],1,True);
            foreach($last2_res['table1'] as $item){
                $last2CommitNum += $item['allCommits'];
                $last2DealNum += $item['allDeals'];
            }
            $last2DealRate = $last2CommitNum ? round(100 * $last2DealNum / $last2CommitNum ,2) : 0;
            $commitCompNum = $lastCommitNum - $last2CommitNum;
            $dealCompNum = $lastDealNum - $last2DealNum;
            $dealCompRate = round($lastDealRate - $last2DealRate,2);
            $toolDatas[$department][$supervisor][$name] = [
                'findbugsNum' => $findbugsNum ?? '/',
                'tscancodeNum' => $tscancodeNum ?? '/',
                'eslintNum' => $eslintNum ?? '/',
                'lastCommitNum' => $lastCommitNum ?? 'NA',
                'last2CommitNum' => $last2CommitNum ?? 'NA',
                'commitCompNum' => $lastCommitNum || $last2CommitNum ? $commitCompNum:'NA',
                'lastDealNum' => $lastCommitNum ? $lastDealNum:'NA',
                'last2DealNum' => $last2CommitNum ? $last2DealNum:'NA',
                'dealCompNum' =>  $lastCommitNum || $last2CommitNum ? $dealCompNum:'NA',
                'lastDealRate' => $lastCommitNum ? $lastDealRate:'NA',
                'last2DealRate' => $last2CommitNum ? $last2DealRate:'NA',
                'dealCompRate' => $lastDealRate ? $dealCompRate:'NA',
                'projectType' => $projectType ?? 'old',
            ];
            $sum['findbugsNum'] += $findbugsNum ?? 0;
            $sum['tscancodeNum'] += $tscancodeNum ?? 0;
            $sum['eslintNum'] += $eslintNum ?? 0;
            $sum['lastCommitNum'] += $lastCommitNum;
            $sum['last2CommitNum'] += $last2CommitNum;
            $sum['commitCompNum'] += $commitCompNum;
            $sum['lastDealNum'] += $lastDealNum;
            $sum['last2DealNum'] += $last2DealNum;
            $sum['dealCompNum'] += $dealCompNum;
            //获取问题数据项目
            if(!empty($findbugsNum) || !empty($tscancodeNum) || !empty($eslintNum)){
                if($projectType === 'old'){
                    // $staticData = ['name'=>'','value'=>0];
                    // $staticData['name'] = $name;
                    $staticCheckSum = 0;
                    if(!empty($findbugsNum)){
                        $staticCheckSum += $findbugsNum;
                    }
                    if(!empty($tscancodeNum)){
                        $staticCheckSum += $tscancodeNum;
                    }
                    if(!empty($eslintNum)){
                        $staticCheckSum += $eslintNum;
                    }
                    // $staticData['value'] = $staticCheckSum;
                    // $this->dataSummary['staticCheck'][] = $staticData;
                    $this->dataSummary['staticCheck'][$name] = $staticCheckSum;
                }   
            }
            if($lastDealRate < 60 && $lastCommitNum && $projectType === 'old'){
                // $review60 = ['name'=>'','value'=>0];
                // $review60['name'] = $name;
                // $review60['value'] = $lastDealRate;
                // $this->dataSummary['reviewBelow60'][] = $review60;
                $this->dataSummary['reviewBelow60'][$name] = $lastDealRate;
            }
            if($lastDealRate < 100 && $lastCommitNum && $projectType === 'old'){
                // $review100 = ['name'=>'','value'=>0];
                // $review100['name'] = $name;
                // $review100['value'] = $lastDealRate;
                // $this->dataSummary['reviewBelow100'][] = $review100;
                $this->dataSummary['reviewBelow100'][$name] = $lastDealRate;
            }
            if($lastCommitNum === 0){
                // $this->dataSummary['commitNum'][] = $name;
                if($explain){
                    // $nocommit[] = ['name'=>$name,'value'=>''];
                    if(empty($this->nocommit)){
                        $nocommit[$name] = '';
                    }        
                    $this->dataSummary['commitNum'][] = $name;
                }
            }
            foreach($last_res['table4'] as $rproject){
                foreach($rproject as $item){
                    $reviewer = $item['reviewer'];
                    if($item['rejects'] > 0 ){
                        if(in_array($reviewer,array_keys($this->dataSummary['rejectNum']))){
                            if(in_array($name,array_keys($this->dataSummary['rejectNum'][$reviewer]))){
                                $this->dataSummary['rejectNum'][$reviewer][$name] += $item['rejects'];
                            }
                            $this->dataSummary['rejectNum'][$reviewer][$name] = $item['rejects'];
                        }
                        else{
                            $this->dataSummary['rejectNum'][$reviewer] = [
                                $name => $item['rejects'],
                            ];
                        }                  
                    }
                } 
            }
        }
        
        $sum['lastDealRate'] = $sum['lastCommitNum'] ? round(100 * $sum['lastDealNum'] / $sum['lastCommitNum'],2):0;
        $sum['last2DealRate'] = $sum['last2CommitNum'] ? round(100 * $sum['last2DealNum'] / $sum['last2CommitNum'],2):0;
        $sum['dealCompRate'] = round($sum['lastDealRate'] - $sum['last2DealRate'],2);
        $sum['lastDealRate'] = $sum['lastDealRate'];
        $sum['last2DealRate'] = $sum['last2DealRate'];
        $sum['dealCompRate'] = $sum['dealCompRate'];
        //数据临时处理
        $resData = [];
        // wlog('toolDatas',$toolDatas);
        foreach($toolDatas as $key=>$depart){
            $detail = [];
            $detail['title'] = $key;
            $detail['children'] = [];
            foreach($depart as $leader=>$value){
                foreach($value as $project=>$item){
                    $item['leader'] = $leader;
                    $item['project'] = $project;
                    $detail['children'][] = $item;
                } 
            }
            $resData[] = $detail;
        } 
        $sum_data['title'] = '总结';
        $sum_data['children'] = [$sum];
        $sum_data['leader'] = '';
        $sum_data['project'] = '';
        $resData[] = $sum_data;
        foreach($this->dataSummary['rejectNum'] as $reviewer=>$project){
            $reject_array = [];
            $rejectSum = 0;
            foreach($project as $name=>$value){
                $reject_array[] = $name.'(驳回'.$value.'次)';
                $this->dataSummary['rejectSum'] += $value;
                $rejectSum += $value;
            }
            $rejectArray = ['name'=>$reviewer.'( '.implode(',',$reject_array).' )','value'=>$rejectSum];
            $this->dataSummary['reject'][] = $rejectArray;   
        }
        return [
            'detail'=>$resData,
            'dataSummary'=>$this->dataSummary,
            'attach'=>$attachDatas,
            'period'=>$last_period,
            'explain'=>$explain,
            'summary'=>$this->summary,
            'nocommit'=>$this->nocommit
        ];
    }
}