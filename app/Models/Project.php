<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;


class Project extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'introduction',
        'department_id',
        'supervisor_id',
        'sqa_id',
        'version_tool',
        'stage',
        'classification',
        'weekly_assessment',
        'quarterly_assessment',
        'published_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = ['members', 'expect_index', 'tools'];

    public function sqa()
    {
        return $this->belongsTo('App\Models\User', 'sqa_id')->select('id', 'name', 'kd_uid')->withDefault([
            'name' => '',
        ]);
    }

    public function supervisor()
    {
        return $this->belongsTo('App\Models\User', 'supervisor_id')->select('id', 'name', 'kd_uid')->withDefault([
            'name' => '',
        ]);
    }

    public function department()
    {
        return $this->belongsTo('App\Models\Department', 'department_id')->select('id', 'name')->withDefault([
            'name' => '',
        ]);
    }

    public function pclint()
    {
        return $this->hasOne('App\Models\Pclint', 'project_id', 'id')->select(['id', 'project_id', 'job_name', 'server_ip']);
    }

    public function phabricator()
    {
        return $this->hasOne('App\Models\Phabricator', 'project_id', 'id')->select(['id', 'project_id', 'job_name', 'phab_id as server_ip', 'review_type', 'tool_type']);
    }

    public function diffcount()
    {
        return $this->hasOne('App\Models\Diffcount', 'project_id', 'id')->select(['id', 'project_id', 'job_name', 'server_ip']);
    }

    public function plmProject()
    {
        return $this->hasOne('App\Models\ToolPlmProject', 'relative_id', 'id')->select(['id', 'relative_id', 'name']);
    }

    public function tapdProject()
    {
        return $this->hasOne('App\Models\Tapd', 'relative_id', 'id')->select(['project_id as id', 'relative_id', 'name']);
    }

    public function tscan()
    {
        return $this->hasOne('App\Models\TscanCode', 'project_id', 'id')->select(['id', 'project_id', 'job_name', 'server_ip']);
    }

    public function getMembersAttribute()
    {
        $data = $this->members()->select('user_id')->get()->toArray();
        return [
            'ids' => array_column($data, 'user_id'),
            'names' => array_column($data, 'username'),
            'data' => array_map(function($item){
                return [
                    'id' => $item['user_id'],
                    'name' => $item['username'],
                ];
            }, $data),
        ];
    }

    public function members()
    {
        return $this->hasMany('App\Models\ProjectUser', 'project_id', 'id')
            ->select('user_id');
    }

    public function getExpectIndexAttribute()
    {
        $index = config('api.project_index');
        $result = [];
        foreach ($index as $key=>$item){
            $item['key'] = $key;
            $item['status'] = 0;
            $classification = key_exists('classification', $this->attributes) ? $this->attributes['classification'] : 0;
            if (in_array($classification, $item['classification'])){
                $result[] = $item;
            }
        }
        
        $expect_index = key_exists('id', $this->attributes) ? ProjectExpectIndexs::where('project_id', $this->attributes['id'])->value('expect_index') : null;
        
        return !empty($expect_index) ? $expect_index : $result;
    }

    public function tools()
    {
        return $this->hasMany('App\Models\ProjectTool', 'project_id', 'id');
    }

    public function getToolsAttribute()
    {
        $data = $this->tools()->get();
        $result = [];
        foreach($data as $item){
            if ($item->relative_type === 'flow') {
                if ($item->relative) {
                    $flow_tools = $item->relative->tools;
                    $url = $item->relative->url;
                    $version_tool = $item->relative->version_tool;
                    foreach($flow_tools as $flow_tool){
                        if ($flow_tool->tool) {
                            $result[] = [
                                    'tool_id' => $flow_tool->tool->id,
                                    'name' => $flow_tool->tool->job_name,
                                    'flow_id' => $flow_tool->version_flow_id,
                                    'flow' => $url,
                                    'version_tool' => $version_tool,
                                    'last_update_time' => $flow_tool->tool->last_updated_time,
                                ] + ['type' => $flow_tool->tool_type];
                        }
                    }
                }
            } else {
                if ($item->relative) {
                    $result[] = [
                        'tool_id' => $item->relative_id,
                        'name' => $item->relative->name,
                        'last_update_time' => $item->relative->last_updated_time,
                    ] + ['type' => $item->relative_type];
                }
            }
        }
        return $result;
    }

    /**
     * 获取项目成员信息是否确认
     * @param $project_id
     * @return bool
     */
    public static function isMemberConformed($project_id){
        $status = self::where('id', $project_id)->value('is_member_conformed');
        return $status == 1 ? true : false;
    }

    /**
     * 获取项目指标统计数据
     *
     * @param $project_id integer 项目id
     * @param $index_name string 指标名称
     * @return mixed
     */
    public static function getIndexData($project_id, $index_name){
        switch ($index_name){
            case 'static_check_serious_bug_count': // 严重缺陷遗漏数=pclint错误数+tscancode全部问题数+findbugs高优先级告警数+eslint错误数
                $result = self::getStaticCheckSeriousBugCount($project_id);
            break;
            case 'issue_static_check_serious_bug_count':
                $result = self::getIssueStaticCheckSeriousBugCount($project_id);
            break;
            case 'code_annotation_rate': // 代码注释率=注释行数/（注释行数+代码行数）
                $data = self::getCodeAnnotationRate($project_id);
                if (!empty($data)){
                    $result = ['up' => $data['up'], 'down' => $data['down']];
                }else {
                    $result = ['up' => 0, 'down' => 0];
                }
                break;
            case 'code_online_review_coverage_rate': // 代码评审覆盖率-线上=经Phabricator评审的提交数/总提交数
                $result = self::AnalysisCodeReviewRate($project_id,'coverage');
                break;
            case 'issue_code_review_coverage_online_rate':
                $result = self::AnalysisPublishedCodeReviewRate($project_id);
                break;
            case 'code_online_review_efficiency_rate':
                $result = self::AnalysisCodeReviewRate($project_id,'efficiency');
                break;
            case 'code_online_review_timely_rate':
                $result = self::AnalysisCodeReviewRate($project_id,'timely');
                break;
            default:
                $result = 0;
                break;
        }
        return $result;
    }

    /**
     * 获取项目静态检查遗留统计数据
     *
     * @param $project_id integer 项目id
     * @return mixed
     */
    public static function getStaticCheckSeriousBugCount($project_id){
        $result = 0;
        $project_checkdata_tscan = 0;
        $project_checkdata_pclint = 0;
        $project_checkdata_findbugs = 0;
        $project_checkdata_eslint = 0;
        if ($project_id){
            $projects = Project::query()->where('id', $project_id)->get();
            foreach ($projects as $project) {
                foreach ($project->tools as $tool) {
                    // tscancode全部问题数
                    if($tool['type'] === 'tscancode') {
                        $project_checkdata_tscan += AnalysisTscancode::getLatestPeriodData($tool['tool_id'], 'season');
                    }
                    // pclint错误数
                    if($tool['type'] === 'pclint') {
                        $project_checkdata_pclint += AnalysisPclint::getLatestPeriodData($tool['tool_id'], 'season');
                    }
                    // findbugs高优先级告警数
                    if($tool['type'] === 'findbugs') {
                        $project_checkdata_findbugs += AnalysisFindbugs::getLatestPeriodData($tool['tool_id'], 'season');
                    }
                    // eslint错误数
                    if($tool['type'] === 'eslint') {
                        $project_checkdata_eslint += AnalysisEslint::getLatestPeriodData($tool['tool_id'], 'season');
                    }
                }
            }
            $result = $project_checkdata_tscan + $project_checkdata_pclint + $project_checkdata_findbugs + $project_checkdata_eslint;
        }
        return $result;
    }

    /**
     * 获取发布项目静态检查遗留统计数据
     *
     * @param $project_id integer 项目id
     * @return mixed
     */
    public static function getIssueStaticCheckSeriousBugCount($project_id){
        $result = 0;
        $project_checkdata_tscan = 0;
        $project_checkdata_pclint = 0;
        $project_checkdata_findbugs = 0;
        $project_checkdata_eslint = 0;
        if ($project_id){
            $projects = Project::query()->where('id', $project_id)->where('published_at', '<>', null)->get();
            foreach ($projects as $project) {
                $publishded_at = $project->published_at;
                foreach ($project->tools as $tool) {
                    // tscancode全部问题数
                    if($tool['type'] === 'tscancode') {
                        $project_checkdata_tscan += TscancodeData::getPublishedData($tool['tool_id'], $publishded_at);
                    }
                    // pclint错误数
                    if($tool['type'] === 'pclint') {
                        $project_checkdata_pclint += LintData::getPublishedData($tool['tool_id'], $publishded_at);
                    }
                    // findbugs高优先级告警数
                    if($tool['type'] === 'findbugs') {
                        $project_checkdata_findbugs += FindbugsData::getPublishedData($tool['tool_id'], $publishded_at);
                    }
                    // eslint错误数
                    if($tool['type'] === 'eslint') {
                        $project_checkdata_eslint += EslintData::getPublishedData($tool['tool_id'], $publishded_at);
                    }
                }
            }
            $result = $project_checkdata_tscan + $project_checkdata_pclint + $project_checkdata_findbugs + $project_checkdata_eslint;
        }
        return $result;
    }

    /**
     * 获取项目代码行统计数据
     *
     * @param $project_id integer 项目id
     * @return mixed
     */
    public static function getCodeAnnotationRate($project_id){
        $cloc_id = [];
        if ($project_id){
            $projects = Project::query()->where('id', $project_id)->get();
            foreach ($projects as $project) {
                foreach ($project->tools as $tool) {
                    if($tool['type'] === 'cloc') {
                        array_push($cloc_id, $tool['tool_id']);
                    }
                }
            }
            $result = ClocData::getLatestPeriodData($cloc_id);
            return $result;
        }else {
            return [];
        }
    }

    /**
     * 获取项目代码线上评审覆盖率&&处理率&&有效率
     *
     * @param $project_id integer 项目id
     * @return mixed
     */
    public static function AnalysisCodeReviewRate($project_id,$type){
        $fiscal_year = get_fiscal_year();
        $fiscal_season = get_fiscal_season();
        switch($fiscal_season){
            case 1:
                $deadline = $fiscal_year.'-03-31 23:59:59';
                break;
            case 2:
                $deadline = $fiscal_year.'-06-30 23:59:59';
                break;
            case 3:
                $deadline = $fiscal_year.'-09-30 23:59:59';
                break;
            case 4:
                $deadline = $fiscal_year.'-12-31 23:59:59';
                break;
            default:
                $deadline = $fiscal_year.'-xx-xx  23:59:59';
                break;
        }
        $phabricator_ids = [];
        if ($project_id){
            $projects = Project::query()->where('id', $project_id)->get();
            foreach ($projects as $project) {
                foreach ($project->tools as $tool) {
                    if($tool['type'] === 'phabricator') {
                        array_push($phabricator_ids, $tool['tool_id']);
                    }
                }
            }
            $result = AnalysisCodeReview::getReviewData('season',$deadline,$phabricator_ids);
            if('efficiency'==$type){
                return ['up'=>$result['valid_num'],'down'=>$result['deal_num']];
            }
            elseif('timely'==$type){
                return ['up'=>$result['intime_num'],'down'=>$result['true_review_num']];
            }
            elseif('coverage'==$type){
                return ['up'=>$result['review_num'],'down'=>$result['commit_num']];
            }
        }
        else{
            return ['up'=>0,'down'=>0];
        }
    }

     /**
     * 获取发布项目代码线上评审覆盖率
     *
     * @param $project_id integer 项目id
     * @return mixed
     */
    public static function AnalysisPublishedCodeReviewRate($project_id){
        $phabricator_ids = [];
        $pulished_at = self::where('id',$project_id)->value('published_at').' 00:00:00';
        if ($project_id){
            $projects = Project::query()->where('id',$project_id)->get();
            foreach ($projects as $project) {
                foreach ($project->tools as $tool) {
                    if($tool['type'] === 'phabricator') {
                        array_push($phabricator_ids, $tool['tool_id']);
                    }
                }
            }
            $result = PhabCommit::getPublishedData($phabricator_ids,$pulished_at);
            return $result;
        }
        else{
            return ['up'=>0,'down'=>0];
        }
    }
}
