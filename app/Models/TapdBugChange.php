<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TapdBugChange extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'tapd_bug_changes';

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

    static public function getExecuteInfo($info, $current_status_sort, $tapd_bug_changes, $type = 'new_value'){
        $changes = $tapd_bug_changes;
        $status = $info['tapd_status'];
        $user_field = $info['executor'];
        $sort = $info['sort'];

        $filtered_status_change = \Illuminate\Support\Arr::first(array_filter($changes, function($item) use ($status){
            return $item['field'] === 'status' && $item['new_value'] === $status;
        }));

        $is_in_workflow = $current_status_sort !== 0 && $sort !== 0 && $sort <= $current_status_sort;

        if ($is_in_workflow && !empty($filtered_status_change)) {
            $status_change_datetime = $filtered_status_change['created'];

            $filtered_user_change = \Illuminate\Support\Arr::first(array_filter($changes, function($item) use ($user_field, $status_change_datetime){
                return $item['field'] === $user_field && $item['created'] === $status_change_datetime;
            }));

            $status_change_user = !empty($filtered_user_change) ? $filtered_user_change[$type] : '';
            $status_change_user = trim(str_replace(';', '|', $status_change_user), '|');

            return [
                'author' => $filtered_status_change['author'],
                'user' => $status_change_user,
                'datetime' => $status_change_datetime,
            ];
        }

        return [];
    }

}
