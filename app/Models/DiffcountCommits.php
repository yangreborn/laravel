<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;


class DiffcountCommits extends Authenticatable
{
    //
    use HasApiTokens, Notifiable;

    protected $table = 'diffcount_commits';
    
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
    
    public static function diffcountDatas($project_datas,$start_time,$end_time,$is_export=0)
    {
        $datas = [];
        foreach($project_datas as $key=>$value)
        {
            $project_name = DB::table('projects')->where('id',$value)->value('name');
            $job_name = DB::table('tool_diffcounts')->where('id',$key)->value('job_name');
            $job_name = preg_replace("/_diffcount$/i","",$job_name);
            $sql= <<<sql
SELECT
    GROUP_CONCAT( id ) as ids
FROM
    diffcount_commits
WHERE
    tool_diffcount_id = $key
    AND commit_time >= '$start_time'
    AND commit_time <= '$end_time'
sql;
            $datas["$job_name"] = [];
            $datas["$job_name"]["project_name"] = $project_name;
            $datas["$job_name"]["project_data"] = DB::select($sql,['start_time'=>$start_time,'end_time'=>$end_time]);
        }

        if ($is_export == 1){
            $res = DiffcountFiles::diffExportDatas($datas);
        }else{
            $res = DiffcountFiles::diffDatas($datas);
        }
        // var_dump($res);
        return $res;
    }
    
    public static function weekdata($project_datas,$start_time,$end_time){
        $datas = [];
        foreach($project_datas as $key=>$value)
        {
            $project_name = DB::table('projects')->where('id',$value)->value('name');
            $job_name = DB::table('tool_diffcounts')->where('id',$key)->value('job_name');
            $job_name = rtrim($job_name, "_diffcount");
            
            $sql= <<<sql
SELECT
	GROUP_CONCAT( DISTINCT id ORDER BY commit_time DESC ) AS ids,
	YEARWEEK( commit_time, 1 ) AS year_week 
FROM
    diffcount_commits
WHERE
    tool_diffcount_id = $key
    AND commit_status = 1
GROUP BY
	YEARWEEK( commit_time, 1 ) 
ORDER BY
	YEARWEEK( commit_time, 1 ) DESC
	LIMIT 7
sql;
        $datas["$job_name"] = [];
        $datas["$job_name"]["project_id"] = $value;
        $datas["$job_name"]["project_name"] = $project_name;
        $datas["$job_name"]["project_data"] = DB::select($sql);

        }
        $period = "week";
        $duration = floor((strtotime($end_time)-strtotime($start_time))/86400);
        if ($duration > 50){
            $period = "season";
        }
        $res = DiffcountFiles::weekdatas($datas, $period);
        // var_dump($res);
        return $res;
    }
    
    
    public static function perWeekdata($project_ids,$year_week){
        $week_date = get_date_from_week(substr($year_week, 0, 4), substr($year_week, -2));
        $ret = self::select('id')
                ->distinct()
                ->whereIn('tool_diffcount_id', explode(',',$project_ids))
                ->whereBetween('commit_time',[$week_date['start'],$week_date['end']])
                ->count('id');
        // var_dump($ret);
        return $ret;
        
    }

    public static function commitsData($project_ids){
        $res = self::select('tool_diffcount_id','commit_key','commit_person','commit_time')->
        whereIn('tool_diffcount_id', explode(',',$project_ids))->get(); 

        return $res;
    }
    
    public static function diffExportData($project_datas,$start_time,$end_time){
        $datas = self::diffcountDatas($project_datas,$start_time,$end_time,1);
        return $datas;
    }
}
