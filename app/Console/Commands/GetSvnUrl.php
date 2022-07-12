<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use App\Models\User;
use Carbon\Carbon;
use App\Services\CreateJenkinsJob;

date_default_timezone_set('PRC');

class GetSvnUrl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string0
     */
    protected $signature = 'notice:getsvnurl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $auth = [
        'yangjiawei', "Kedacom8278",
    ];

    protected $client_url;
    protected $sqainfo = [];
    protected $end = "";
    protected $autodeploy = ['视讯产品线终端产品部'];


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->initSqaInfo();
        $this->client_url = new \GuzzleHttp\Client([
            'base_uri' => 'https://cmo.kedacom.com/api/branches/',
        ]);
        $this->end = Carbon::now();
        $start = $this->end->copy()->subDay(7)->startOfDay()->toDateTimeString();
        $end = $this->end->copy()->startOfDay()->toDateTimeString();
        $num  = 1;
        $in = True;
        while($in){
            $response = $this->client_url->request('GET','', [
                'auth' => $this->auth,
                'query' => [
                    'page'=>$num,
                ],
            ]);
            $res = $response->getBody()->getContents();
            $content =  json_decode($res,true);
            foreach($content["results"] as $url){
                if($url["createtime"]==""){
                    print("createtime is null...break\n");
                    $in = False;
                    break;
                }
                if($url["createtime"]<$start){ 
                    print("createtime is too early...break\n");
                    $in = False;
                    break;
                }
                if($url["createtime"]>$end){ 
                    print("createtime is too late...continue\n");
                    continue;
                }
                if(preg_match("/\/tags\//",$url["branchurl"])){ 
                    print("url is tags...continue\n");
                    continue;
                }
                if($url["createtime"]>=$start){ 
                    $url["departname"] = str_replace(' ','',$url["departname"]);
                    $this->setUrl($url);
                    if(in_array($url['departname'],$this->autodeploy)){
                        // $this->createDiffcountJob($url['branchurl']);
                    } 
                }
            }
            $num += 1;
        }
        $this->sendInfo();
    }

    public function setUrl($url){
        $sqa = $this->getSqa($url["departname"]);
        $url['branchurl'] = rtrim($url['branchurl'],"/");
        $version_data=[
            "url"=>$url["branchurl"],
            "version_tool"=>"svn",
            "sqa_email"=>$sqa['mail'],
        ];
        $new_data = [
            "url"=>$url["branchurl"],
            "department"=>$url["departname"],
            "create_time"=>$url["createtime"]
        ];
        $id = DB::table("version_flows")->where("url",$url["branchurl"])->value('id');
        if($id == NULL)
        {
            print($version_data["url"]." insert...\n");
            DB::table("version_flows")->insert($version_data);
            DB::table("new_flows")->insert($new_data);
            // $this->sqainfo[$sqa->name][] = $url;
        }
        else{
            print($new_data["url"]." is exist...\n");
        }
        $this->sqainfo[$sqa['name']][] = $url;

    }
    
    public function getSqa($department){
        $res = DB::table("ldap_departments")
                ->join('ldap_department_sqa','ldap_departments.uid','=','ldap_department_sqa.department_uid')
                ->join('ldap_users','ldap_department_sqa.sqa_uid','=','ldap_users.uid')
                ->where('ldap_departments.name',$department)
                ->select("ldap_users.name","ldap_users.mail")
                ->get()
                ->toArray();
        if(isset($res[0])){
            $sqa['name'] = $res[0]->name;
            $sqa['mail'] = $res[0]->mail;
        }
        else{
            $sqa['name'] = '未知';
            $sqa['mail'] = '';
        }
        return $sqa;
    }

    public function initSqaInfo(){
        $sqas = DB::table("ldap_department_sqa")
                ->join('ldap_users','ldap_department_sqa.sqa_uid','=','ldap_users.uid')
                ->select('name')
                ->groupBy('name')
                ->get()
                ->toArray();
        foreach($sqas as $sqa){
            $this->sqainfo[$sqa->name] = [];
        }
        $this->sqainfo["未知"] = [];
    }

    public function sendInfo(){
        $part = "统计截止时间:".$this->end."\n";
        foreach($this->sqainfo as $sqa=>$urls){
            if(empty($urls)){
                continue;
            }
            $part = $part.'><font >**'.$sqa."**</font>:\n";
            foreach($urls as $url){
                $part = $part.'><font color="#1E90FF">'.$url["departname"]."(".$url["createtime"].")</font>\n";
                $part = $part.'><font color="comment">'.$url["branchurl"]."</font>\n";
            }   
        }
        $content = <<<markdown
### 上周新建svn分支通知\n
$part
markdown;
        $message = [
            'data' => ['content' => $content],
            'key' => config('wechat.wechat_robot_key.svnurl_remind'),
            'type' => 'markdown',
        ];
        if (!empty($message)){
            wechat_bot($message['data'], $message['key'], $message['type']);
        }
    }

    public function createDiffcountJob($url){
        $url = rtrim($url,'/');
        $data = [
            'to_members'=>[['value'=>'']],
            'cc_members'=>[['value'=>'']],
            'flow'=>[$url],
            'tool'=>['diffcount'],
            'description'=>'',
            'subject'=>''
        ];
        CreateJenkinsJob::handleData($data);
    }
}