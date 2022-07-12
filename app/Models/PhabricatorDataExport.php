<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;


class PhabricatorDataExport extends Authenticatable
{
    //
    use HasApiTokens, Notifiable;

    protected $table = 'tool_phabricators';
    
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    
    protected $hidden = [];

    protected $appends = [];

    public static function exportCommitsData($period, $members){
        
        $result = [];
        
        $workflows = DB::table('project_tools')
            ->whereIn('project_id', array_keys($members))
            ->where('relative_type','flow')
            ->select(['project_id', 'relative_id'])
            ->get();
        
        foreach ($workflows as $workflow) {
            $tool_id = DB::table('version_flow_tools')->where('version_flow_id', $workflow->relative_id)->where('tool_type','phabricator')->value('tool_id');
            $job_info = DB::table('tool_phabricators')->where('id', $tool_id)->get();
            if(!count($job_info)){
                continue;
            }
            $commits_model = DB::table('phabricator_commits')
                ->where('tool_phabricator_id', $tool_id)
                ->whereBetween('commit_time', [$period[0].' 00:00:00', $period[1].' 23:59:59']);
            $commits = $commits_model->select(['id as phabricator_commit_id', 'author_id', 'svn_id', 'commit_time as action_time'])->get();
            $reviews = DB::table('phabricator_reviews')
                ->select(['phabricator_commit_id', 'review_id', 'author_id', 'action', 'action_time', 'comment'])
                ->whereIn('phabricator_commit_id', $commits->pluck('phabricator_commit_id'))
                ->get();
            // 没评审数据
            $reviewed_commit_id = $reviews->pluck('phabricator_commit_id')->unique()->values()->sort();
            $unreviewed_commit = $commits->whereNotIn('phabricator_commit_id', $reviewed_commit_id);

            $reviews = $reviews->merge($unreviewed_commit);
            $users = DB::table('users')
                ->whereIn('id', $reviews->pluck('author_id')->all())
                ->select(['id', 'name'])
                ->get();
            $commits = $commits->pluck('svn_id', 'phabricator_commit_id')->toArray();
            $detail = $reviews->map(function ($value) use ($users, $commits, $job_info) {
                $url_start = $job_info[0]->tool_type === 3 ? 'https://':'http://';
                $value->review_id = $value->review_id ?? '--';
                $value->action = $value->action ?? 'submit_directly';
                $value->comment = $value->comment ?? '';
                $value->author = $users->where('id', $value->author_id)->pluck('name')->first();
                $value->url = $url_start . $job_info[0]->phab_id . '/r' . $job_info[0]->callsign . $commits[$value->phabricator_commit_id];
                return $value;
            });
            $data = [
                'workflow' => $job_info[0]->job_name,
                'server_ip' => $job_info[0]->phab_id,
                'detail' => $detail,
            ];
            $result[] = $data;
            // wlog("result: ",$result);
        }
        return $result;
    }

    public static function exportReviewsData($period, $members){
        $result = [];
        
        $workflows = DB::table('project_tools')
            ->whereIn('project_id', array_keys($members))
            ->where('relative_type','flow')
            ->select(['project_id', 'relative_id'])
            ->get();
        foreach ($workflows as $workflow) {
            $tool_id = DB::table('version_flow_tools')->where('version_flow_id', $workflow->relative_id)->where('tool_type','phabricator')->value('tool_id');
            $job_info = DB::table('tool_phabricators')->where('id', $tool_id)->get();
            $commits_model = DB::table('phabricator_commits')
                ->where('tool_phabricator_id', $tool_id)
                ->whereBetween('commit_time', [$period[0].' 00:00:00', $period[1].' 23:59:59'])
                ->select(['id', 'svn_id']);
            $commits = $commits_model->orderBy('author_id')->get();
            $reviews = DB::table('phabricator_reviews')
                ->select(['phabricator_commit_id', 'review_id', 'author_id', 'action', 'action_time', 'comment'])
                ->whereIn('phabricator_commit_id', $commits->pluck('id'))
                ->orderBy('phabricator_commit_id')
                ->orderBy('action_time')
                ->get();
            $reviews_filtered = $reviews->filter(function ($value){
                return $value->action === 'create' && !empty($value->comment);
            });
            $commits_format = $commits->pluck('svn_id', 'id');
            $reviews_filtered = $reviews_filtered->map(function ($value) use ($commits_format){
                $value->svn_id = $commits_format->get($value->phabricator_commit_id);
                return $value;
            });
            $detail = [];
            foreach ($reviews_filtered as $item){
                $reviewers = explode(',', $item->comment);
                if (!empty($reviewers)) {
                    $target_reviews = $reviews->filter(function($value) use ($item){
                        return $value->phabricator_commit_id === $item->phabricator_commit_id;
                    });
                    $detail = array_merge($detail, array_map(function ($value) use ($item, $target_reviews) {
                        $handle_action = $target_reviews
                            ->where('author_id', $value)
                            ->whereIn('action', ['accept', 'reject'])
                            ->first();
                        $comments = $target_reviews
                            ->where('author_id', $value)
                            ->whereIn('action', ['comment', 'inline_comment'])
                            ->pluck('comment')
                            ->all();

                        return [
                            'svn_id' => $item->svn_id, // 提交号
                            'review_id' => $item->review_id, // 评审号
                            'submitter' => DB::table('users')->where('id', $item->author_id)->value('name'), // 提交人
                            'reviewer' => DB::table('users')->where('id', $value)->value('name'), // 评审人
                            'action' => $handle_action ? $handle_action->action : '', // 操作
                            'create_time' => $item->action_time, // 创建评审时间
                            'handle_time' => $handle_action ? $handle_action->action_time : '', // 处理时间
                            'comment' => !empty($comments) ? implode($comments , PHP_EOL) : '', // 评语
                            'has_delayed' => $handle_action ? get_has_delayed($handle_action->action_time, $item->action_time) : '' // 是否延期
                        ];
                    }, $reviewers));
                } else {
                    continue;
                }
            }
            $data = [
                'workflow' => $job_info[0]->job_name,
                'detail' => $detail,
            ];
            $result[] = $data;
        }
        return $result;
    }
}
