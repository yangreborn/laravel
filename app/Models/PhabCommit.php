<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Carbon\Carbon;

class PhabCommit extends Authenticatable
{
    //
    use HasApiTokens, Notifiable;

    protected $table = 'phabricator_commits';
    
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

    protected static $init_datas = [//流统计数据初始化
        'all'=>[
            'commits'=>0,
            'reviews'=>0,
            'not_reviews'=>0,
            'rejects'=>0,
            'deals'=>0,
            'reviewRate'=>0,
            'valid'=>0,
        ],
        'diff'=>[
            'reviews'=>0,
            'rejects'=>0,
            'commitRate'=>0,
        ],
        'audit'=>[
            'commits'=>0,
            'reviews'=>0,
            'rejects'=>0,
            'reviewRate'=>0,
            'commitRate'=>0,
        ]
    ];

    public function review()
    {
        return $this->hasMany('App\Models\PhabReview', 'phabricator_commit_id', 'id');
    }

    public static function weeksData($tool_phabricator_id){
        $exclude_authors = [309, 495, 680, 448, 227, 286, 537, 723, 304];
        $exclude_authors = !empty($exclude_authors) ? implode(',', $exclude_authors) : '';
        $sql = <<<sql
SELECT
    count(DISTINCT id) AS commit_times,
    GROUP_CONCAT( DISTINCT id ORDER BY commit_time DESC ) AS ids,
    YEARWEEK( commit_time, 7 ) AS year_week 
FROM
    phabricator_commits
WHERE
    tool_phabricator_id = :id 
    AND author_id NOT IN ( $exclude_authors ) 
AND
    commit_status = 1
GROUP BY
    YEARWEEK( commit_time, 7 ) 
ORDER BY
    YEARWEEK( commit_time, 7 ) DESC 
    LIMIT 8
sql;
        $res = DB::select($sql, ['id' => $tool_phabricator_id]);
        foreach ($res as &$item){
            $data = [];
            $data['week'] = $item->year_week;
            $data['commits'] = $item->commit_times;
            $data['reviews'] = PhabReview::reviewTimes($item->ids, $item->year_week
        
        );
            $item = $data;
        }
        return $res;
    }

    public static function phabData($project_datas,$start_time,$end_time,$validity){
        $datas = [];
        $table2_index = ['rejects','in_time','deals','reviewDealrate','reviewIntimerate','author','receives'];
        foreach($project_datas as $value){
            /*$include_authors = !empty($value['members']) ? implode(',', $value['members']) : '';
            if($include_authors == '0'){
                $include_authors = '';
            }
            else{
                $include_authors = 'IN ('.$include_authors.')';
            }*/
            foreach($value['ids']  as $id){
                $tool_res = DB::table('tool_phabricators')->where('id',$id)->get();
                $repo_name = $tool_res[0]->job_name;
                $tool_type = $tool_res[0]->tool_type;
                $datas['table1'][$repo_name][0] = self::$init_datas;
                $datas['table2'][$repo_name] = [];
                $tmp_datas[$repo_name] = [//流统计数据初始化
                    'repo'=>[
                        'receives'=>0,
                        'deals'=>0,
                        'in_time'=>0,
                        'rejects'=>0,
                        'valid'=>0
                    ],
                ];
                [$review_datas,$datas['table1'][$repo_name],$review_type] = self::getPhabData($id,$start_time,$end_time,$datas['table1'][$repo_name]);
                $first = 1;
                $in_time_list = [];
                foreach($datas['table1'][$repo_name] as &$item){//评审提交数据表
                if(!isset($item['author']) && !$first){
                    break;
                }
                if($first){
                    $first = 0;
                    continue;
                }
                $timeline = $commit_id = $has_count = [];
                foreach($review_datas as $review){
                    if(in_array($review->phabricator_commit_id,$item['phabricator_commit_ids'])){
                        if($review->action == "reject"){
                            $item['diff']['rejects']++;
                            if(!isset($tmp_datas[$repo_name][$review->author_id]['rejects'])){
                                $tmp_datas[$repo_name][$review->author_id]['rejects'] = 0;
                            }
                            $tmp_datas[$repo_name][$review->author_id]['rejects']++;
                            if(!isset($tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['deals'])|| !$tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['deals']){
                                $tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['deals'] = 1;
                                if(!isset($tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['valid'])){
                                    $tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['valid'] = 0;
                                }
                            }
                            if(!isset($timeline[$review->phabricator_commit_id]['review']['time'])){
                                $timeline[$review->phabricator_commit_id]['review']['time'] = [];
                                $timeline[$review->phabricator_commit_id]['review']['author_id'] = [];
                            }
                            array_push($timeline[$review->phabricator_commit_id]['review']['time'] ,$review->action_time);
                            array_push($timeline[$review->phabricator_commit_id]['review']['author_id'],$review->author_id);
                        }
                        elseif($review->action == "create"){
                            $reviewer_list = explode(',',trim($review->comment,','));
                            foreach($reviewer_list as $reviewer){
                                if(!isset($tmp_datas[$repo_name][$reviewer]['receives'])){
                                    $tmp_datas[$repo_name][$reviewer]['receives'] = 0;
                                }
                                if(!isset($tmp_datas[$repo_name][$reviewer]['rejects'])){
                                    $tmp_datas[$repo_name][$reviewer]['rejects'] = 0;
                                }
                                if(!isset($tmp_datas[$repo_name][$reviewer][$review->phabricator_commit_id]['deals'])){
                                    $tmp_datas[$repo_name][$reviewer][$review->phabricator_commit_id]['deals'] = 0;
                                }
                                if(!isset($tmp_datas[$repo_name][$reviewer]['in_time'])){
                                    $tmp_datas[$repo_name][$reviewer]['in_time'] = 0;
                                }
                                if(!isset($tmp_datas[$repo_name][$reviewer][$review->phabricator_commit_id]['valid'])){
                                    $tmp_datas[$repo_name][$reviewer][$review->phabricator_commit_id]['valid'] = 0;
                                }
                                $tmp_datas[$repo_name][$reviewer]['receives']++;
                            }
                            $timeline[$review->phabricator_commit_id]['create']['time'] = $review->action_time;
                            $timeline[$review->phabricator_commit_id]['create']['author_id'] = $review->author_id;
                            
                            if(!in_array($review->phabricator_commit_id,$has_count)){
                                $item['all']['reviews']++;
                                $has_count[] = $review->phabricator_commit_id;
                                if($review->review_id){
                                    $item['diff']['reviews']++;  
                                }
                                else{
                                    $item['audit']['commits']++;
                                }
                            }    
                        }
                        elseif($review->action == "accept"){
                            if(!($review->review_id) && !(in_array($review->phabricator_commit_id,$commit_id))){
                                array_push($commit_id,$review->phabricator_commit_id);
                            }
                            //评审人数据设为deal
                            if(!isset($tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['deals'])||!$tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['deals']){
                                $tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['deals'] = 1;
                                if(!isset($tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['valid'])){
                                    $tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['valid'] = 0;
                                }
                            }
                            if(!isset($timeline[$review->phabricator_commit_id]['review']['time'])){
                                $timeline[$review->phabricator_commit_id]['review']['time'] = [];
                                $timeline[$review->phabricator_commit_id]['review']['author_id'] = [];
                            }
                            array_push($timeline[$review->phabricator_commit_id]['review']['time'] ,$review->action_time);
                            array_push($timeline[$review->phabricator_commit_id]['review']['author_id'],$review->author_id);
                        }
                        elseif($review->action == "comment"||$review->action == "inline_comment"){
                            if($review->action == "inline_comment"){
                                $tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['valid'] = 1;
                            }
                            elseif($review->duration>10||$tool_type==3){
                               
                                if(!preg_match("/^(ok|同意|通过|review ok|accept)$/i",trim($review->comment,""))){
                                    $tmp_datas[$repo_name][$review->author_id][$review->phabricator_commit_id]['valid'] = 1;
                                }
                            }
                        }
                    }
                }  
                if($item['all']['commits'] > 0){
                    $item['all']['reviewRate'] = round($item['all']['reviews']/$item['all']['commits'] * 100);
                    $item['all']['not_reviews'] = $item['all']['commits'] - $item['all']['reviews'];
                    $datas['table1'][$repo_name][0]['all']['commits'] += $item['all']['commits'];
                    $datas['table1'][$repo_name][0]['all']['reviews'] += $item['all']['reviews'];
                    if($item['diff']['reviews'] > 0){
                        $datas['table1'][$repo_name][0]['diff']['reviews'] += $item['diff']['reviews'];
                        $datas['table1'][$repo_name][0]['diff']['rejects'] += $item['diff']['rejects'];
                        $item['diff']['commitRate'] = round($item['diff']['reviews']/$item['all']['commits'] * 100);
                        $item['all']['rejects'] +=  $item['diff']['rejects'];
                    }
                    elseif($review_type !=1 && $review_type != 3){
                        if($item['diff']['rejects']>0){
                            $item['audit']['rejects'] = $item['diff']['rejects'];
                        }
                        $item['diff']['reviews'] = 'N/A';
                        $item['diff']['commitRate'] = 'N/A';
                        $item['diff']['rejects'] = 'N/A';
                    }
                    if($item['audit']['commits'] > 0){
                        if($item['diff']['rejects']>0){
                            $item['audit']['rejects'] = $item['diff']['rejects'];
                        }
                        $datas['table1'][$repo_name][0]['audit']['commits'] += $item['audit']['commits'];
                        $datas['table1'][$repo_name][0]['audit']['reviews'] += $item['audit']['reviews'];
                        $datas['table1'][$repo_name][0]['audit']['rejects'] += $item['audit']['rejects'];
                        $item['audit']['reviewRate'] = round($item['audit']['reviews']/$item['all']['commits'] * 100);
                        $item['audit']['commitRate'] = round($item['audit']['commits']/$item['all']['commits'] * 100);
                        $item['all']['rejects'] +=  $item['audit']['rejects'];
                    }
                    elseif($review_type != 2 && $review_type != 3){    
                            $item['audit']['reviews'] = 'N/A';
                            $item['audit']['commits'] = 'N/A';
                            $item['audit']['rejects'] = 'N/A';
                            $item['audit']['reviewRate'] = 'N/A';
                            $item['audit']['commitRate'] = 'N/A';    
                    }
                }
                else{
                    if($review_type != 2 && $review_type != 3){
                        $item['audit']['commits'] = 'N/A';
                        $item['audit']['reviews'] = 'N/A';
                        $item['audit']['rejects'] = 'N/A'; 
                        $item['audit']['reviewRate'] = 'N/A';
                        $item['audit']['commitRate'] = 'N/A';
                    }
                    elseif($review_type != 1 && $review_type != 3){
                        $item['diff']['reviews'] = 'N/A';
                        $item['diff']['commitRate'] = 'N/A';
                        $item['diff']['rejects'] = 'N/A';
                    }    
                }               
                foreach($item['phabricator_commit_ids'] as $commit_id){
                    if(isset($timeline[$commit_id]['review']['time'])){
                        $len = count($timeline[$commit_id]['review']['time']);
                        for($y=0;$y<$len;$y++){
                            if(!(isset($tmp_datas[$repo_name][$timeline[$commit_id]['review']['author_id'][$y]]['in_time']))){
                                $tmp_datas[$repo_name][$timeline[$commit_id]['review']['author_id'][$y]]['in_time'] = 0;
                            }
                            if(in_array($commit_id,$in_time_list)){
                                continue;
                            }
                            if(!get_has_delayed($timeline[$commit_id]['create']['time'],$timeline[$commit_id]['review']['time'][$y])){
                                $tmp_datas[$repo_name][$timeline[$commit_id]['review']['author_id'][$y]]['in_time']++;
                                $in_time_list[] = $commit_id;
                            }
                        }
                    }     
                }  
            }
            $key_array = ['receives','rejects','in_time','deals','valid'];
            $all_deal_count = 0;
            foreach($tmp_datas[$repo_name] as &$item){
                $deal_count = 0;
                $valid_count = 0;
                foreach($item as $key => $commit_id){
                    if(!in_array($key,$table2_index)){
                        if($item[$key]['valid'] == 1){
                            $valid_count++;
                            if(!isset($item[$key]['deals'])){
                                $item[$key]['deals'] = 1;
                            }
                        }
                        if($item[$key]['deals'] == 1){
                            $deal_count++;
                        }  
                        if(!in_array($key,$key_array)){
                            if($item[$key]['deals'] == 1){
                                $key_array[] = $key;
                                $all_deal_count++;
                            }   
                        }
                        unset($item[$key]);
                    }
                }
                if(!(isset($item['receives']))){//如果非评审人进行评审收到数设为0
                    $item['valid'] = $item['receives'] = $item['rejects'] = 0;
                }
                $item['deals'] = $deal_count > $item['receives'] ? $item['receives']:$deal_count;//如果评审处理数超过收到数
                $item['valid'] = $valid_count > $item['deals'] ? $item['deals']:$valid_count;
                if($item['receives'] > 0){
                    $tmp_datas[$repo_name]['repo']['receives'] += $item['receives'];      
                    $tmp_datas[$repo_name]['repo']['rejects'] += $item['rejects'];
                    $tmp_datas[$repo_name]['repo']['deals'] = $all_deal_count;
                    $item['reviewDealrate'] = round($item['deals']/$item['receives'] * 100);
                    if($item['deals']>0){
                        $item['reviewIntimerate'] = round($item['in_time']/$item['deals'] * 100);
                        $tmp_datas[$repo_name]['repo']['in_time'] += $item['in_time'];
                        if($validity){
                            $item['reviewValidrate'] = round($item['valid']/$item['deals'] * 100);
                            $tmp_datas[$repo_name]['repo']['valid'] += $item['valid'];
                        }
                    }
                    else{
                        $item['reviewIntimerate'] = 0;
                        $item['reviewValidrate'] = 0;
                    }
                }
                else{
                    $item['reviewDealrate'] = 0;  
                    $item['reviewIntimerate'] = 0;
                    if($validity){
                        $item['reviewValidrate'] = 0;
                    }
                }    
            }
            if($tmp_datas[$repo_name]['repo']['receives'] > 0){
                $tmp_datas[$repo_name]['repo']['reviewDealrate'] = round($tmp_datas[$repo_name]['repo']['deals']/$tmp_datas[$repo_name]['repo']['receives'] * 100);
                if($tmp_datas[$repo_name]['repo']['deals']>0){
                    $tmp_datas[$repo_name]['repo']['reviewIntimerate'] = round($tmp_datas[$repo_name]['repo']['in_time']/$tmp_datas[$repo_name]['repo']['deals'] * 100);
                    if($validity){
                        $tmp_datas[$repo_name]['repo']['reviewValidrate'] = round($tmp_datas[$repo_name]['repo']['valid']/$tmp_datas[$repo_name]['repo']['deals'] * 100);
                    }
                }
                else{
                    $tmp_datas[$repo_name]['repo']['reviewValidrate'] = 0;
                    $tmp_datas[$repo_name]['repo']['reviewIntimerate'] = 0;
                }
            }
            else{
                $tmp_datas[$repo_name]['repo']['reviewDealrate'] = 0;
                $tmp_datas[$repo_name]['repo']['reviewIntimerate'] = 0;
                if($validity){
                    $tmp_datas[$repo_name]['repo']['reviewValidrate'] = 0;
                }
            }
            if($datas['table1'][$repo_name][0]['all']['commits'] > 0){
                $datas['table1'][$repo_name][0]['all']['reviewRate'] = round($datas['table1'][$repo_name][0]['all']['reviews']/$datas['table1'][$repo_name][0]['all']['commits'] * 100);
                $datas['table1'][$repo_name][0]['all']['not_reviews'] = $datas['table1'][$repo_name][0]['all']['commits']-$datas['table1'][$repo_name][0]['all']['reviews'];
                if($datas['table1'][$repo_name][0]['diff']['reviews'] > 0){
                    $datas['table1'][$repo_name][0]['diff']['commitRate'] = round($datas['table1'][$repo_name][0]['diff']['reviews']/$datas['table1'][$repo_name][0]['all']['commits'] * 100);
                    $datas['table1'][$repo_name][0]['all']['rejects'] += $datas['table1'][$repo_name][0]['diff']['rejects'];
                }
                elseif($review_type !=1 && $review_type != 3){
                    $datas['table1'][$repo_name][0]['diff']['commitRate'] = 'N/A';
                    $datas['table1'][$repo_name][0]['diff']['reviews'] = 'N/A';
                    $datas['table1'][$repo_name][0]['diff']['rejects'] = 'N/A';
                }
                if($datas['table1'][$repo_name][0]['audit']['commits'] > 0){
                    $datas['table1'][$repo_name][0]['audit']['reviewRate'] = round($datas['table1'][$repo_name][0]['audit']['reviews']/$datas['table1'][$repo_name][0]['all']['commits'] * 100);
                    $datas['table1'][$repo_name][0]['audit']['commitRate'] = round($datas['table1'][$repo_name][0]['audit']['commits']/$datas['table1'][$repo_name][0]['all']['commits'] * 100);
                    $datas['table1'][$repo_name][0]['all']['rejects'] += $datas['table1'][$repo_name][0]['audit']['rejects'];
                }
                elseif($review_type !=2 && $review_type != 3){
                    $datas['table1'][$repo_name][0]['audit']['reviewRate'] = 'N/A';
                    $datas['table1'][$repo_name][0]['audit']['commitRate'] = 'N/A';
                    $datas['table1'][$repo_name][0]['audit']['commits'] = 'N/A';
                    $datas['table1'][$repo_name][0]['audit']['reviews'] = 'N/A';
                    $datas['table1'][$repo_name][0]['audit']['rejects'] = 'N/A';
                }
            }      
            else{
                if($review_type != 2 && $review_type != 3){
                    $datas['table1'][$repo_name][0]['audit']['reviewRate'] = 'N/A';
                    $datas['table1'][$repo_name][0]['audit']['commitRate'] = 'N/A';
                    $datas['table1'][$repo_name][0]['audit']['commits'] = 'N/A';
                    $datas['table1'][$repo_name][0]['audit']['reviews'] = 'N/A';
                    $datas['table1'][$repo_name][0]['audit']['rejects'] = 'N/A';
                }
                elseif($review_type != 1 && $review_type != 3){
                    $datas['table1'][$repo_name][0]['diff']['commitRate'] = 'N/A';
                    $datas['table1'][$repo_name][0]['diff']['reviews'] = 'N/A';
                    $datas['table1'][$repo_name][0]['diff']['rejects'] = 'N/A';
                }    
            }
            foreach ($tmp_datas[$repo_name] as $key => $value){
                if($key == 'repo'){
                    array_push($datas['table2'][$repo_name],$value);
                    $datas['table1'][$repo_name][0]['all']['deals'] = $value['deals'];
                    $datas['table1'][$repo_name][0]['all']['valid'] = $value['valid'];
                }
                else{
                    $value['reviewer'] = LdapUser::query()->whereRaw('uid = (select kd_uid from users where id = ?)',$key)->value('name');
                    array_push($datas['table2'][$repo_name],$value); //赋值
                }    
            }
        }
        }
        return $datas;      
    }

    //
    public static function previewData($project_datas,$start_time,$end_time,$validity,$compresive=False){
        $datas = self::phabData($project_datas,$start_time,$end_time,$validity);
        foreach($datas['table1'] as $key=>$project){
            $commit_num['table1'][] = $project[0]['all']['commits'];
            $reviewRate['table1'][] = $project[0]['all']['reviewRate'];
            $review_type = DB::table('tool_phabricators')->where('job_name',$key)->value('review_type');
            $mail['table1'][$key] = [];
            $mail['table1'][$key]['reviewRate'] = $project[0]['all']['reviewRate'];
            $mail['table1'][$key]['allCommits'] = $project[0]['all']['commits'] ;
            $mail['table1'][$key]['allReviews'] = $project[0]['all']['reviews']; 
            $mail['table1'][$key]['allDeals'] = $project[0]['all']['deals']; 
            $mail['table1'][$key]['allValid'] = $project[0]['all']['valid']; 
            $mail['table3'][$key] = [];
            $commit_num['table3'] = $reviewRate['table3'] = [];
            foreach($project as $in_key=>$person){
                if($in_key == 0){
                    continue;
                }
                $tmp_data = [
                    'author' =>$person['author'],  
                    'commits'=>$person['all']['commits'],
                    'not_reviews'=>$person['all']['not_reviews'],
                    'diffs'=>$person['diff']['reviews'],
                    'audits'=>$person['audit']['commits'],
                    'rejects'=>$person['all']['rejects'],
                    'diff_rejects'=>$person['diff']['rejects'],
                    'audit_rejects'=>$person['audit']['rejects'],
                    'reviewRate'=>$person['all']['reviewRate'],
                    'diffRate'=>$person['diff']['commitRate'],
                    'auditRate'=>$person['audit']['commitRate']
                ]; 
                array_push($mail['table3'][$key],$tmp_data); 
                $commit_num['table3'][] = $person['all']['commits'];
                $reviewRate['table3'][] = $person['all']['reviewRate'];
            }
            if(!empty($commit_num['table3'])&&!empty($reviewRate['table3'])){
                array_multisort($commit_num['table3'], SORT_DESC,$reviewRate['table3'],SORT_DESC,$mail['table3'][$key]);
            }
            if(empty($mail['table3'][$key])){
                $tmp_data = [
                    'author' =>'无提交数据',  
                    'commits'=>0,
                    'not_reviews'=>0,
                    'diffs'=>0,
                    'audits'=>0,
                    'rejects'=>0,
                    'diff_rejects'=>0,
                    'audit_rejects'=>0,
                    'reviewRate'=>0,
                    'diffRate'=>0,
                    'auditRate'=>0
                ];
                if($review_type != 2 && $review_type != 3){
                    $tmp_data['audits'] = 'N/A';
                    $tmp_data['audit_rejects'] = 'N/A';
                    $tmp_data['auditRate'] = 'N/A';
                }
                if($review_type != 1 && $review_type != 3){
                    $tmp_data['diffs'] = 'N/A';
                    $tmp_data['diff_rejects'] = 'N/A';
                    $tmp_data['diffRate'] = 'N/A';
                }
                array_push($mail['table3'][$key],$tmp_data);
            }
        }
        if(!empty($commit_num['table1'])&&!empty($reviewRate['table1'])){
            array_multisort($commit_num['table1'], SORT_DESC,$reviewRate['table1'],SORT_DESC,$mail['table1']);
        }
        //3、4
        $mail = self::setReviewerData($datas,$validity,$mail);
        if(!$compresive){
            $mail['table6'] = self::weeksReview($project_datas);
        } 
        // wlog('mail',$mail);
        return $mail;
    }

    //
    public static function weeksReview($project_datas){
        $datas = [];
        foreach($project_datas as $project_id => $value){
            $data = [
                'commits' => 0,
                'audit_rate'=> 0,
                'diff_rate' => 0,
                'all_rate' => 0,
            ];
            $tmp_data = [];
            /*$include_authors = !empty($value['members']) ? implode(',', $value['members']) : '';
            if($include_authors == '0'){
                $include_authors = '';
            }
            else{
                $include_authors = 'IN ('.$include_authors.')';
            }*/
            foreach($value['ids']  as $id){
            $index = 1;
            $sql = <<<sql
SELECT
    count(DISTINCT id) AS commit_times,
    GROUP_CONCAT( DISTINCT id ORDER BY commit_time DESC ) AS ids,
    YEARWEEK( commit_time, 3 ) AS year_week 
FROM
    phabricator_commits
WHERE
    tool_phabricator_id = :id
AND
    commit_status = 1
GROUP BY
    YEARWEEK(commit_time, 3 ) 
ORDER BY
    YEARWEEK(commit_time, 3 ) DESC 
    LIMIT 8
sql;
            $res = DB::select($sql, ['id' => $id]);
            if(empty($res)){
                continue;
            }
            $review_type = DB::table('tool_phabricators')->where('id',$id)->value('review_type');
            $now_time = Carbon::parse(Carbon::now());
            $search_time = Carbon::parse(DB::table('phabricator_commits')->where('tool_phabricator_id',$id)->where('commit_status',1)->max('commit_time'));
            if($now_time->weekOfYear > $search_time->weekOfYear or $now_time->year != $search_time->year){
                $index = 0;
            }
            $data['repo_name'] = DB::table('tool_phabricators')->where('id',$id)->value('job_name');
            if(isset($res[$index]->commit_times)){
                $data['commits'] = $res[$index]->commit_times;
                $tmp_data = PhabReview::mailReviewTimes($res[$index]->ids,1);
                $create_reviews = PhabReview::mailReviewTimes($res[$index]->ids,2);
            }
            if($data['commits'] && $res[0]->year_week > 201830){
                if($tmp_data[0]->diff_reviews){
                    $data['diff_rate'] = $create_reviews?round($tmp_data[0]->diff_reviews/$create_reviews * 100):0;
                }
                else{
                    $data['diff_rate'] = ($review_type !=1 && $review_type != 3)?'N/A':0;
                }
                $tmp_audits = $tmp_data[0]->all_reviews - $tmp_data[0]->diff_reviews;
                if($tmp_audits){
                    $data['audit_rate'] = $create_reviews?round(($tmp_data[0]->all_reviews - $tmp_data[0]->diff_reviews)/$create_reviews * 100):0;
                }
                else{
                    $data['audit_rate'] = ($review_type !=2 && $review_type != 3)?'N/A':0;
                }
                $data['all_rate'] = $create_reviews?round($tmp_data[0]->all_reviews/$create_reviews * 100):0;
            }
            else{
                if($res[0]->year_week <= 201830){
                    $data['commits'] = 'N/A';
                    $data['audit_rate'] = 'N/A';
                    $data['diff_rate'] = 'N/A';
                    $data['all_rate'] = 'N/A';    
                }
                elseif($review_type != 2 && $review_type != 3){
                    $data['audit_rate'] = 'N/A';
                }
                elseif($review_type != 1 && $review_type != 3){
                    $data['diff_rate'] = 'N/A';
                }                   
            }
            $data['week'] = [];
            $first = True;
            foreach ($res as $key=>$item){
                if(($key == 0 && $index) || $item->year_week <= 201830){
                    continue;
                }
                if($first){
                    $year_week = str_split($item->year_week,4);
                    $created_at = get_date_from_week($year_week[0],$year_week[1]);
                    $date_list = explode(' ',$created_at['end']);
                    $data['created_at'] = $date_list[0];
                    $first = False;
                }
                $deal_num = PhabReview::mailReviewTimes($item->ids,0);
                $create_num = PhabReview::mailReviewTimes($item->ids,2);
                array_unshift($data['week'],$create_num?round($deal_num/$create_num * 100):0);
            }
            // $data['ctime'] = strtotime($data['created_at']);
            array_unshift($datas,$data);
            // $sort_array['ctime'][] = $data['ctime'];
            // $sort_array['commits'][] = $data['commits'];
        }
        }
        return $datas;
    }

    public static function getPhabData($id,$start_time,$end_time,$repo_datas){
        $sql= <<<sql
SELECT 
    DISTINCT author_id,
    GROUP_CONCAT( id ) as ids
FROM
    phabricator_commits
WHERE
    tool_phabricator_id = $id
    AND commit_time >= '$start_time'
    AND commit_time <= '$end_time'
    AND author_id != 0
    AND branch is null
    AND commit_status =1
GROUP BY
    author_id
sql;
            $res =  DB::select($sql);    
            $count = count($res);
            $review_type = DB::table('tool_phabricators')->where('id',$id)->value('review_type');
            $commit_ids = "";
            for($len=0;$len<$count;$len++){
                $repo_datas[$len+1] = self::$init_datas;
                $repo_datas[$len+1]['author'] = LdapUser::query()->whereRaw('uid = (select kd_uid from users where id = ?)',$res[$len]->author_id)->value('name');
                $repo_commit_ids = explode(',',$res[$len]->ids);
                $repo_datas[$len+1]['phabricator_commit_ids'] = $repo_commit_ids;
                $repo_datas[$len+1]['all']['commits'] = count($repo_commit_ids);
                if($commit_ids==""){
                    $commit_ids = $res[$len]->ids;
                    continue;
                }
                $commit_ids = $commit_ids.",".$res[$len]->ids;
            }
            $review_datas = PhabReview::reviewData($commit_ids,$review_type);
            return [$review_datas,$repo_datas,$review_type];
    }

    public static function setReviewerData($datas,$validity,$mail){
        foreach($datas['table2'] as $key=>$project){
            $mail['table1'][$key]['reviewIntimerate'] = $project[0]['reviewIntimerate'];
            if($validity){
                // $mail['table1'][$key]['reviewIntimerate'] = $project[0]['reviewIntimerate'];
                $mail['table1'][$key]['reviewValidrate'] = $project[0]['reviewValidrate'];
            }
            $mail['table4'][$key] = [];
            $receive_num['table4'] = $deal_num['table4'] = [];
            foreach($project as $in_key=>$person){
                if($in_key == 0 || !$person['receives']){
                    continue;
                }
                $tmp_data = [
                    'reviewer' =>$person['reviewer'], 
                    'receives'=>$person['receives'],
                    'deals'=>$person['deals'],
                    'rejects'=>$person['rejects'],
                    'reviewDealrate'=>$person['reviewDealrate'],
                    'in_time'=>$person['in_time'],
                    'reviewIntimerate'=>$person['reviewIntimerate']
                ];
                if($validity){
                    // $tmp_data['in_time'] = $person['in_time'];
                    // $tmp_data['reviewIntimerate'] = $person['reviewIntimerate'];
                    $tmp_data['valid'] = $person['valid'];
                    $tmp_data['reviewValidrate'] = $person['reviewValidrate'];
                } 
                array_push($mail['table4'][$key],$tmp_data);
                $receive_num['table4'][] = $person['receives'];
                $deal_num['table4'][] = $person['deals'];
            }
            if(!empty($receive_num['table4'])&&!empty($deal_num['table4'])){
                array_multisort($receive_num['table4'], SORT_DESC,$deal_num['table4'],SORT_DESC,$mail['table4'][$key]);
            }
            if(empty($mail['table4'][$key])){
                $tmp_data = [
                    'reviewer' =>'无评审数据', 
                    'receives'=>0,
                    'deals'=>0,
                    'rejects'=>0,
                    'reviewDealrate'=>0,
                    'in_time'=>0,
                    'reviewIntimerate'=>0
                ];
                if($validity){
                    // $tmp_data['in_time'] = 0;
                    // $tmp_data['reviewIntimerate'] = 0;
                    $tmp_data['valid'] = 0;
                    $tmp_data['reviewValidrate'] = 0;
                }
                array_push($mail['table4'][$key],$tmp_data);
            }
        }
        return $mail;
    }

    public function reviewData(){
        return $this->hasMany('App\Models\PhabReview', 'phabricator_commit_id', 'id');
    }
    
    public static function getPublishedData($phabricator_ids,$published_at){
        $commit_num = self::whereIn('tool_phabricator_id',$phabricator_ids)->where('commit_time','<=',$published_at)->count();
        $review_num = PhabReview::whereRaw("phabricator_commit_id in (select id from phabricator_commits where tool_phabricator_id in (?) and commit_time <= ?)",[implode(',',$phabricator_ids),$published_at])->where('action','=','create')->count();
        $res = ['up'=>$review_num,'down'=>$commit_num];
        return $res;
    }
}                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  