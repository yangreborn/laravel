<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use App\Models\User;
use Carbon\Carbon;

date_default_timezone_set('PRC');

class GerritFetchData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string0
     */
    protected $signature = 'GerritFetchData';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $servers = ['172.16.6.169'];
    protected $sernum = [
        '172.16.8.9'=>89,
        '172.16.6.148'=>148,
        '172.16.6.169'=>169,
    ];
    protected $auth = [
        '172.16.8.9' => ['jenkins', "E9ggbJ6VeKcwc+UxfI2omGhjtBL4Y64dY4PHTuURUQ"],
        '172.16.6.148' => ['jenkins', "9mo8PJx6CBv5oPynFsy9/vyV4xO4WcVpDuREKQO4fw"],
        '172.16.6.169' => ['jenkins', "bBoNrHfXcznv8I++MNAl6iPdJTK6HkeOLI69MxAQOg"],
    ];

    protected $client_project,$client_change;
    protected $nocount_repo = ['All-Projects','All-Users'];
    protected $filter_reviewers = ['pclint','sysbuild'];
    protected $cmo_list = ['buildkdm39'];
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
        foreach($this->servers as $server){
            $this->info('['.date('Y-m-d H:i:s').']'.$server. '开始数据采集...'); 
            $this->client_project = new \GuzzleHttp\Client([
                'base_uri' => 'http://'.$server.':8080/a/projects/',
            ]);
            $this->client_change = new \GuzzleHttp\Client([
                'base_uri' => 'http://'.$server.':8080/a/changes/',
            ]);
            $this->handle_project_info($server);
            $this->handle_change_info($server);
            $this->info('['.date('Y-m-d H:i:s').']'.$server. '结束数据采集...'); 
        }
    }

    public function get_response($request_type,$data){
        $response = NULL;
        switch($request_type){
            case 'project':
                try {  
                    $response = $this->client_project->request('GET','', [
                        'auth' => $data['auth'],
                        'query' => [
                            'state'=>'ACTIVE',
                        ],
                    ]);
                } catch (\GuzzleHttp\Exception\RequestException $e) {  
                    print("request project error\n");  
                }  
                break;
            case 'change':
                $timeline = Carbon::now()->subMonths(1)->format('Y-m-d');

                // $timeline = Carbon::now()->subDay(1)->format('Y-m-d');
                try {  
                    $response = $this->client_change->request('GET', '', [
                        'auth' => $data['auth'],
                        'query' => [
                            'q'=>'after:'.$timeline,
                            'o'=>'CURRENT_REVISION',
                            
                        ],
                    ]);  
                } catch (\GuzzleHttp\Exception\RequestException $e) {  
                    print("request change error\n");  
                }  
                break;
            case 'change_detail':
                try {  
                    $response = $this->client_change->request('GET',$data['id'].'/detail', [
                        'auth' => $data['auth'],
                    ]);
                } catch (\GuzzleHttp\Exception\RequestException $e) {  
                    print("request detail ".$data['id']." error\n");  
                }  
                break;
            case 'default':
                $response = NULL;
        }
        if(!empty($response)){
            $res = $response->getBody()->getContents();
            $content = substr($res,4);
            return json_decode($content,true);
        }
        else{
            return [];
        }
    }

    public function handle_project_info($server){
        $data = [
            'auth'=>$this->auth[$server],
        ];
        $projects = $this->get_response('project',$data);
        // wlog('response',$projects);
        foreach($projects as $key=>$value){
            if(in_array($key,$this->nocount_repo)){
                continue;
            }
            if(!DB::table('tool_phabricators')->where('job_name',$key)->where('phab_id',$server)->first())#确认数据是否已存在
            {
                DB::table('tool_phabricators')->insert(['job_name' => $key,'phab_id'=> $server,'tool_type'=>2,'review_type'=>1]);
            }
            $tool_id = DB::table('tool_phabricators')->where('job_name',$key)->where('phab_id',$server)->value('id');
            $url = 'ssh://jenkins@'.$server.':29418/'.$key.'.git';
            $sqa_email = 'zuolin@kedacom.com';
            $version_flow_id = DB::table('version_flows')->where('url',$url)->value('id');
            if($version_flow_id){   
                if(!empty($sqa_email)){
                    DB::table('version_flows')->where('url',$url)->update(['sqa_email' => $sqa_email]);
                }
                if(DB::table('version_flow_tools')->where('version_flow_id',$version_flow_id)->where('tool_type','phabricator')->where('tool_id',$tool_id)->value('id')){
                    continue;
                }
                else{
                    DB::table('version_flow_tools')->insert(['version_flow_id'=>$version_flow_id,'tool_type'=>'phabricator','tool_id'=>$tool_id]);
                }
            } 
            else{
                DB::table('version_flows')->insert(['url'=>$url,'version_tool'=>'git']);
                $version_flow_id = DB::table('version_flows')->where('url',$url)->value('id');
                DB::table('version_flow_tools')->insert(['version_flow_id'=>$version_flow_id,'tool_type'=>'phabricator','tool_id'=>$tool_id]);
            }
        }
    }

    public function handle_change_info($server){
        $data = [
            'auth'=>$this->auth[$server],
        ];
        $changes = $this->get_response('change',$data);
        foreach($changes as $change){
            if(!preg_match("/wirelessdev/",$change['project'])){
                continue;
            }
            $reviewer_array = [];
            $data['id'] = $change['id'];
            wlog('data',$data);
            $detail = $this->get_response('change_detail',$data);
            wlog('detail',$detail);
            if(empty($detail)){
                continue;
            }
            $author_email = $detail['owner']['email']??$detail['owner']['username'].'@kedacom.com';
            $author_name = $detail['owner']['username'];
            $author_id = $this->get_author_id($author_email);
            if(!isset($detail['submitted'])){
                $this->info('['.date('Y-m-d H:i:s').']'.$change['change_id']. '评审未提交跳过...'); 
                continue;
            }
            if(in_array($detail['owner']['username'],$this->cmo_list)){
                $this->info('['.date('Y-m-d H:i:s').']'.$change['change_id']. '提交人是配置管理员账号跳过...'); 
                continue;
            }
            foreach($detail['reviewers']['REVIEWER'] as $reviewer){
                // if($reviewer['name']==$detail['owner']['username']){
                //     continue;
                // }
                $reviewer = $this->get_reviewer($reviewer);
                if(!empty($reviewer)){
                    $reviewer_array[] = $reviewer;
                }
            }
            if(!isset($change['current_revision'])){
                $change['current_revision'] = $change['change_id'];
            }
            $reviewers = implode(',',$reviewer_array);
            $tool_phabricator_id = DB::table('tool_phabricators')->where('job_name',$detail['project'])->where('phab_id',$server)->value('id');
            if(DB::table('phabricator_commits')->where('phab_id',$this->sernum[$server])->where('svn_id',$change['current_revision'])->where('tool_phabricator_id',$tool_phabricator_id)->first()){
                $this->info('['.date('Y-m-d H:i:s').']'.$change['current_revision']. '数据已写入...'); 
                continue;
            }
            $phabricator_commit_id = DB::table('phabricator_commits')->insertGetId(
                ['phab_id' => $this->sernum[$server],
                'svn_id' => $change['current_revision'],
                'author_id' => $author_id,
                'commit_person'=>$detail['owner']['username'],
                'tool_phabricator_id' => $tool_phabricator_id,
                'commit_time' => $detail['submitted'],
                'commit_status' => 1,
                ]);
            DB::table('phabricator_reviews')->insert(
                    ['phab_id' => $this->sernum[$server],
                    'review_id' => $detail['_number'],
                    'author_id' => $author_id,
                    'action' => 'create',
                    'comment' => $reviewers,
                    'action_time' => $detail['created'],
                    'phabricator_commit_id' => $phabricator_commit_id,
                    'tool_phabricator_id'=>$tool_phabricator_id,
                    'commit_time'=>$detail['submitted']
                    ]);
            foreach($detail['labels']['Code-Review']['all'] as $review){
                if(in_array($review['username'],$this->filter_reviewers)){
                    continue;
                }
                $reviewer_id = $this->get_reviewer($review);
                if(!isset($review['date'])){
                    $this->info('['.date('Y-m-d H:i:s').']'.$change['change_id'].' '.$review['username']. '没有评审时间跳过...'); 
                    continue;
                }
                DB::table('phabricator_reviews')->insert(
                    ['phab_id' => $this->sernum[$server],
                    'review_id' => $detail['_number'],
                    'author_id' => $reviewer_id,
                    'action' => $review['value']==2?'accept':'reject',
                    'comment' => '',
                    'action_time' => $review['date'],
                    'phabricator_commit_id' => $phabricator_commit_id,
                    'tool_phabricator_id'=>$tool_phabricator_id,
                    'commit_time'=>$detail['submitted']
                    ]);
            } 
            foreach($detail['messages'] as $item){
                $commenter = $item['author'];
                $new_str = $item['message'];
                $comment_array =  preg_split('/[\n]{2}/s', $new_str);
                if(!in_array($commenter['username'],$this->filter_reviewers)&&$commenter['username'] != $author_name && count($comment_array)>=2){
                    $author_id = $this->get_reviewer($commenter);
                    $comment = implode('\n',array_slice($comment_array,1));
                    DB::table('phabricator_reviews')->insert(
                        ['phab_id' => $this->sernum[$server],
                        'review_id' => $detail['_number'],
                        'author_id' => $author_id,
                        'action' => 'comment',
                        'comment' => $comment,
                        'action_time' => $review['date'],
                        'phabricator_commit_id' => $phabricator_commit_id,
                        'tool_phabricator_id'=>$tool_phabricator_id,
                        'commit_time'=>$detail['submitted']
                        ]);
                }     
            }
            
        }
    }

    public function get_author_id($author_email){
        $author_id = DB::table('users')->where('email',$author_email)->value('id');
        return $author_id;
    }

    public function get_reviewer($reviewer){
            $review_email = $reviewer['email']??$reviewer['username'].'@kedacom.com';
            return $this->get_author_id($review_email);
    }
}