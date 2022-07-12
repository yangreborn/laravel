<?php

namespace App\Models;

use Carbon\Carbon;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LdapUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uid',
        'department_id',
        'name',
        'name_pinyin',
        'mail',
        'status',
        'user_principal_name',
        'user_account_control',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    static public function syncToUser() {
        $res = self::all()->toArray();
        foreach ($res as $value) {
            // echo json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
            $department_id = $value['department_id'];
            $departments = LdapDepartment::getParents($department_id);
            if (empty($departments) || !in_array($departments[0], array_keys(config('api.sync_ldap_departments')))) {
               continue; 
            }

            $result = User::firstOrNew(['email' => $value['mail']]);

            $result->kd_uid = $value['uid'];
            $result->name = $value['name_pinyin'];
            $result->status = $value['status'];
            $result->is_department_conformed = 1;
            $result->password = $result->password ?: bcrypt(\Illuminate\Support\Str::random(6));
            $result->password_expired = $result->password_expired ?: Carbon::now()->addDays(config('api.password_expired'))->toDateTimeString();
            $result->remember_token = $result->remember_token ?: \Illuminate\Support\Str::random(10);
            $result->save();

            // departments表中id
            $user_department = Department::query()->where('name', $departments[sizeof($departments) - 1])->first()->toArray();
            // UserDepartment::query()->where('user_id', $result->id)->delete();
            UserDepartment::updateOrCreate([
                'user_id' => $result->id,
            ], [
                'department_id' => $user_department['id'],
            ]);
        }
    }

    static public function activeUsers() {
        return Cache::rememberForever('users', function () {
            return self::query()->select(['id', 'uid', 'name', 'mail as email'])->where('status', 1)->get();
        });
    }

}
