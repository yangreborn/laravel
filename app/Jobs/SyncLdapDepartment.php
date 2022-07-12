<?php

namespace App\Jobs;

use Adldap\Laravel\Facades\Adldap;
use App\Models\LdapDepartment;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;

class SyncLdapDepartment implements ShouldQueue
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
        $this->getLdapDepartment();
    }

    private function getLdapDepartment(){
        $ldap = Adldap::getFacadeRoot();
        $search = $ldap->search()
            ->select(['cn', 'name'])
            ->where('objectCategory', '=', 'CN=Organizational-Unit,CN=Schema,CN=Configuration,DC=kedacom,DC=com');
        $search = $search->select(['cn', 'name', 'distinguishedname'])->get()->toArray();
        $lastest_data = [];
        foreach ($search as $item) {
            $uid = Arr::first($item['cn']);
            $name = Arr::first($item['name']);
            $dn = Arr::first($item['distinguishedname']);
            $parent_dn = str_replace('OU=' . $name . ',', '', $dn);
            $parent_id = LdapDepartment::query()->where('dn', $parent_dn)->value('id');
            $res = LdapDepartment::query()->updateOrCreate(['uid' => $uid],
                [
                    'name' => $name,
                    'parent_id' => $parent_id ?? 0,
                    'dn' => $dn,
                    'status' => 1,
                ]
            );
            $lastest_data[] = $res->id;
        }

        $all_data = LdapDepartment::all()->pluck('id')->toArray();
        $diff_data = array_values(array_diff($all_data, $lastest_data));
        LdapDepartment::query()->whereIn('id', $diff_data)->update([
            'status' => 0,
        ]);
    }
}
