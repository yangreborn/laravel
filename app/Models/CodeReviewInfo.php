<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CodeReviewInfo extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'projects';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

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

    public static function CodeReviewIntimeTotal($tool_ids)
    {
        $receive_num = 0;
        $intime_num = 0;
        $res = AnalysisCodeReview::getReviewData('day',False,$tool_ids);
        if(!empty($res)){
            $receive_num = $res['receive_num'];
            $intime_num = $res['intime_num'];
        } 
        $intime_rate = $receive_num?round($intime_num/$receive_num*100,1):0;
        $result = [
            'receive_num'=> $receive_num,  
            'intime_num' => $intime_num,
            'intime_rate'=>$intime_rate,
        ];
        return !empty($tool_ids) ? $result : [];
    }

    public static function CodeReviewIntimeCount($tool_ids,$period)
    {
        $receive_num = 0;
        $intime_num = 0;
        $deadline = Carbon::now()->subDays($period)->endOfDay();
        $res = AnalysisCodeReview::getReviewData('day',$deadline,$tool_ids);
        if(!empty($res)){
            $receive_num = $res['receive_num'];
            $intime_num = $res['intime_num'];
        }    
        $intime_rate = $receive_num?round($intime_num/$receive_num*100,1):0;
        $result[] = [
            'receive_num'=> $receive_num,  
            'intime_num' => $intime_num,
            'intime_rate'=>$intime_rate,
        ];
        return !empty($tool_ids) ? $result : [];
    }

    public static function DiffcountTotal($tool_ids)
    {
        $res = AnalysisDiffcount::getDiffcountData('day',False,$tool_ids);
        $result = [];
        if(!empty($res)){
            $result = [
                'add_line'=> $res['add_line']??0,  
                'mod_line'=> $res['mod_line']??0,
            ];
            $result['sum_line']=$result['add_line']+$result['mod_line'];
        }
        return !empty($tool_ids) ? $result : [];
    }

    public static function DiffcountCount($tool_ids,$period)
    {
        $deadline = Carbon::now()->subDays($period)->endOfDay();
        $res = AnalysisDiffcount::getDiffcountData('day',$deadline,$tool_ids);
        if(!empty($res)){
            $result = [
                'add_line'=> $res['add_line']??0,  
                'mod_line'=> $res['mod_line']??0,
            ];
            $result['sum_line']=$result['add_line']+$result['mod_line'];
        }   
        return !empty($tool_ids) ? $result : [];
    }

    public static function codeReviewIntimeWeekSummary($tool_ids,$period)
    {
        for ($i = 0; $i < $period; $i++) { 
            $week = Carbon::now()->subWeeks($i)->format('o年W周');
            $deadline = Carbon::now()->subWeeks($i)->endOfWeek();
            $commit_num = $review_num = 0;
            $res = AnalysisCodeReview::getReviewData('week', $deadline,$tool_ids);
            if(!empty($res)){
                $commit_num = $res['commit_num'];
                $review_num = $res['review_num'];
            }
            $not_review_num = $commit_num - $review_num ;
            $result[] = [
                'date' => $week,
                'commit_num' => $commit_num,
                'review_num' => $review_num,
                'not_review_num' => $not_review_num,
            ];
        }
        return !empty($tool_ids) ? $result : [];
    }

    public static function diffcountWeekSummary($tool_ids,$period)
    {
        for ($i = 0; $i < $period; $i++) { 
            $week = Carbon::now()->subWeeks($i)->format('o年W周');
            $deadline = Carbon::now()->subWeeks($i)->endOfWeek();
            $add_line = $mod_line = $sum_line =  0;
            $res = AnalysisDiffcount::getDiffcountData('week', $deadline,$tool_ids);
            if(!empty($res)){
                $add_line = $res['add_line'];
                $mod_line = $res['mod_line'];
                $sum_line = $add_line + $mod_line;
            }
            $result[] = [
                'date' => $week,
                'add_line' => $add_line,
                'mod_line' => $mod_line,
                'sum_line' => $sum_line,
            ];
        }
        // wlog('week',$result);
        return !empty($tool_ids) ? $result : [];
    }
}