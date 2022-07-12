<?php

namespace App\Jobs;

use Adldap\Laravel\Facades\Adldap;
use App\Models\LdapDepartment;
use App\Models\LdapUser;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class SyncLdapUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->getLdapUser();
    }

    private function getLdapUser(){
        $departments = LdapDepartment::query()->pluck('id', 'name')->toArray();

        // 个人详情与部门详情中的部门信息存在字母大小写不一致现象
        $department_names = array_map(function ($item) {
            return strtolower($item);
        }, array_keys($departments));
        $department_ids = array_values($departments);
        $departments = array_combine($department_names, $department_ids);

        $ldap = Adldap::getFacadeRoot();
        $search = $ldap->search()
            ->select(['uid', 'displayname', 'samaccountname', 'mail', 'department', 'useraccountcontrol', 'userprincipalname'])
            ->where([
                ['objectCategory', '=', 'CN=Person,CN=Schema,CN=Configuration,DC=kedacom,DC=com'],
            ])
            ->get()->toArray();
        $lastest_data = [];
        foreach($search as $user) {
            $uid = Arr::first($user['uid']);
            if (!empty($uid)) {
                $name = Arr::first($user['displayname']);
                $name_pinyin = Arr::first($user['samaccountname']);
                $mail = Arr::first($user['mail']) ?? '';
                $department_name = strtolower(Arr::first($user['department']));
                $department_id = key_exists($department_name, $departments) ? $departments[$department_name] : 0;
                $user_principal_name = Arr::first($user['userprincipalname']);
                $user_account_control = Arr::first($user['useraccountcontrol']);
                $res = LdapUser::query()->updateOrCreate(
                    ['uid' => $uid],
                    [
                        'name' => $name,
                        'name_pinyin' => $name_pinyin,
                        'mail' => $mail,
                        'department_id' => $department_id,
                        'user_principal_name' => $user_principal_name,
                        'user_account_control' => $user_account_control,
                        'status' => $user_account_control === '546' ? 0 : 1,
                    ]
                );
                $lastest_data[] = $res->id;
            }
        }

        $all_data = LdapUser::all()->pluck('id')->toArray();
        $diff_data = array_values(array_diff($all_data, $lastest_data));
        LdapUser::query()->whereIn('id', $diff_data)->update([
            'status' => 0,
        ]);
        // Cache::forget('users');
        // LdapUser::activeUsers();
    }
}
