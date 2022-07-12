<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;


class PhabReview extends Authenticatable
{
    //
    use HasApiTokens, Notifiable;

    protected $table = 'phabricator_reviews';
    
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

    /**
     * 获取一组版本提交的评审次数
     * @param $commit_ids string 版本提交编号
     * @param $year_week string 年周
     * @return  mixed 评审次数
     */
    public static function reviewTimes($commit_ids, $year_week) {
        $week_date = get_date_from_week(substr($year_week, 0, 4), substr($year_week, -2));
        return self::select('phabricator_commit_id')
            ->distinct()
            ->whereIn('phabricator_commit_id', explode(',', $commit_ids))
            ->whereBetween('action_time', [$week_date['start'], $week_date['end']])
            ->count('phabricator_commit_id');
    }

    public static function reviewData($commit_ids,$review_type){
        if($review_type == 1){
			$res = self::select('phabricator_commit_id','action_phid','author_id','action','comment','action_time','review_id','comment','duration')->
			whereIn('phabricator_commit_id', explode(',',$commit_ids))->whereNotNull('review_id')->get();
        }
		elseif($review_type == 2){
			$res = self::select('phabricator_commit_id','action_phid','author_id','action','comment','action_time','review_id','comment','duration')->
			whereIn('phabricator_commit_id', explode(',',$commit_ids))->whereNull('review_id')->get();
        }
        elseif($review_type == 3){
			$res = self::select('phabricator_commit_id','action_phid','author_id','action','comment','action_time','review_id','comment','duration')->
			whereIn('phabricator_commit_id', explode(',',$commit_ids))->get();
        }
        else{
            $res = [];
        }
        return $res;
    }

    public static function mailReviewTimes($commit_ids,$type) {
        if($type == 0){
            $res = self::select('phabricator_commit_id')
            ->distinct()
            ->whereIn('phabricator_commit_id', explode(',', $commit_ids))
            ->whereIn('action',['accept','reject','concern'])
            ->count('phabricator_commit_id');
        }
        elseif($type == 2){
            $res = self::select('phabricator_commit_id')
            ->distinct()
            ->whereIn('phabricator_commit_id', explode(',', $commit_ids))
            ->whereIn('action',['create'])
            ->count('phabricator_commit_id');
        }
        elseif($type == 1){            
            $sql = <<<sql
            SELECT
                count(DISTINCT phabricator_commit_id) AS all_reviews,
                count(DISTINCT review_id) AS diff_reviews
            FROM
                phabricator_reviews
            WHERE
                phabricator_commit_id in ($commit_ids)
            AND
                action in ("accept","reject","concern")
sql;

            $res = DB::select($sql);
        }
        return $res;
    }
    
}
