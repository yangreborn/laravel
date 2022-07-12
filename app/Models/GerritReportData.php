<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Carbon\Carbon;

class GerritReportData extends Authenticatable
{
    public static function getGerritData($project_datas,$start_time,$end_time){
        foreach($project_datas as $project_data){
            $tool_ids = $project_data['ids'];
            $diffcount_res = self::getDiffcountData($tool_ids,$start_time,$end_time);
            $phabricator_res = self::getPhabricatorData($tool_ids,$start_time,$end_time);
            return ["diffcount"=>$diffcount_res,"phabricator"=>$phabricator_res];
        }
    }

    public static function getDiffcountData($tool_ids,$start_time,$end_time){
        $commit_res = DiffcountCommits::query()->where('commit_time','>',$start_time)->where('commit_time','<',$end_time)->whereIn('tool_diffcount_id',$tool_ids)->where('commit_status',1)->where('author_id','>',0)->orderBy('id')->get()->toArray();
        $diffcount_commit_ids = [];
        foreach($commit_res as $item){
            $diffcount_commit_ids[] = $item['id'];
        }
        $file_res = DiffcountFiles::query()->selectRaw('diffcount_commit_id,sum(inc_nbnc_line) as nbnc_lines')->whereIn('diffcount_commit_id',$diffcount_commit_ids)->groupBy('diffcount_commit_id')->orderBy('diffcount_commit_id')->get()->toArray();
        // var_dump($diffcount_commit_ids);
        $author_array = [];
        $author_sort = [];
        $sum = ['commits'=>0,'lines'=>0];
        if(count($commit_res) == count($file_res)){
            for($i=0;$i<count($commit_res);$i++){
                $name = LdapUser::query()->whereRaw('uid = (select kd_uid from users where id = ?)',$commit_res[$i]['author_id'])->value('name');
                if(in_array($name,array_keys($author_array))){
                    $author_array[$name]['commits'] += 1;
                    $author_array[$name]['lines'] += $file_res[$i]['nbnc_lines'];
                }
                else{
                    $author_array[$name]['commits'] = 1;
                    $author_array[$name]['lines'] = $file_res[$i]['nbnc_lines'];
                }
                $sum['commits'] += 1;
                $sum['lines'] += $file_res[$i]['nbnc_lines'];
            }   
        }
        if(!empty($author_array)){
            foreach($author_array as $key => $value){
                $author_sort[] = array_merge(['author'=>$key],$value);
                $tmp_sort[] = $value['commits'];
            }
            array_multisort($tmp_sort,SORT_DESC,$author_sort);
        }
        return [$author_sort,$sum];
    }

    public static function getPhabricatorData($tool_ids,$start_time,$end_time){
        $review_res = PhabReview::query()->where('commit_time','>',$start_time)->where('commit_time','<',$end_time)->whereIn('tool_phabricator_id',$tool_ids)->get()->toArray();
        $author_array = [];
        $comment = False;
        $deal = False;
        $in_time = False;
        $sum = ['deal'=>0,'comment'=>0,'in_time'=>0];
        foreach($review_res as $item){
            if($item['action'] === 'create'){
                $create_time[$item['review_id']] = $item['action_time'];
                $reviewer_array = explode(',',$item['comment']);
                foreach($reviewer_array as $reviewer){
                    $name = LdapUser::query()->whereRaw('uid = (select kd_uid from users where id = ?)',$item['author_id'])->value('name');
                    if(!in_array($name,array_keys($author_array))){
                        $author_array[$name]['comment'] = 0;
                        $author_array[$name]['deal'] = 0;
                        $author_array[$name]['in_time'] = 0;
                    }
                }
            }
            if($item['action'] === 'comment'){
                $comment = True;
            }
            if($item['action'] === 'accept' || $item['action'] === 'reject'){
                $deal = True;
                if(!get_has_delayed($create_time[$item['review_id']],$item['action_time'])){
                    $in_time = True;
                }
            }
            $author_array[$name]['deal'] = $deal ? $author_array[$name]['deal'] +1:$author_array[$name]['deal'];
            $author_array[$name]['comment'] = $comment ? $author_array[$name]['comment'] +1:$author_array[$name]['comment'];
            $author_array[$name]['in_time'] = $in_time ? $author_array[$name]['in_time'] +1:$author_array[$name]['in_time'];

            $sum['deal'] = $deal ? $sum['deal']+1:$sum['deal'];
            $sum['comment'] = $comment ? $sum['comment']+1:$sum['comment'];
            $sum['in_time'] = $in_time ? $sum['in_time']+1:$sum['in_time'];

            $comment = False;
            $deal = False;
            $in_time = False;
        }
        if(!empty($author_array)){
            foreach($author_array as $key => $value){
                $author_sort[] = array_merge(['author'=>$key],$value);
                $tmp_sort[] = $value['deal'];
            }
            array_multisort($tmp_sort,SORT_DESC,$author_sort);
        }
        else{
            $author_sort = [];
        }
        // wlog('res',$author_sort);
        return [$author_sort,$sum];
    }
    
}