<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use App\Models\User;
use Carbon\Carbon;

class GetGitlabData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string0
     */
    protected $signature = 'getgitlabdata';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '拉取gitlab评审数据';

    protected $gitlab_servers = [
        '172.16.1.154' => ['cxzlb','cxzlb','5432']
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(){
        
        $this->client_url = new \GuzzleHttp\Client([
            'base_uri' => 'https://szgitlab.kedacom.com/api/v4/projects',
        ]);
        $response = $this->client_url->request('GET','', [
            'query' => [
                'private_token' => 'vVp57_tdwNg_8PsSTvyy',
            ]
        ]);
        $res = $response->getBody()->getContents();
        $content =  json_decode($res,true);


        // phpinfo();
        foreach($this->gitlab_servers as $key=>$server){
            $dsn = "pgsql:host=".$key.";dbname=gitlabhq_production";#获取PDO连接
            $db = new \PDO($dsn,$server[0],$server[1],array(\PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"));
            // $dsn="pgsql:host=172.16.1.154;port=5432;dbname=gitlabhq_production;user=cxzlb;password=cxzlb";
            // $this->get_tool_phabricators($db,$key);
            $this->get_phabricator_commits($db,$key);
        }       
    }

    public function get_tool_phabricators($db,$ip){
        $select = "SELECT projects.id,projects.name,namespaces.path as npath,projects.path as ppath 
        from projects inner join namespaces on projects.namespace_id = namespaces.id";
        $project_datas = $this->pdo_mysql($db,$select);#连接数据库
        foreach($project_datas as $project_data){
            $name = $project_data->name;
            $repo_id = $project_data->id;
            $url = "git@".$ip.":".$project_data->npath."/".$project_data->ppath.".git";
            if(!DB::table('tool_phabricators')->where('job_name',$name)->where('phab_id',$ip)->first())#确认数据是否已存在
            {
                DB::table('tool_phabricators')->insert(['job_name' => $name,'phab_id'=> $ip,'repo_id' => $repo_id,'review_type'=>1,'tool_type'=>3]);
            }
            $tool_id = DB::table('tool_phabricators')->where('job_name',$name)->where('phab_id',$ip)->value('id');
            $version_flow_id = DB::table('version_flows')->where('url',$url)->value('id');
            if($version_flow_id){   
                if(!empty($sqa_email)){
                    DB::table('version_flows')->where('url',$url)->update(['sqa_email' => $sqa_email]);
                }
                if(DB::table('version_flow_tools')->where('version_flow_id',$version_flow_id)->where('tool_type','phabricator')->where('tool_id','!=',$tool_id)->whereNull('deleted_at')->value('id')){
                    if(!preg_match("/test|yfzlb/",$name)){
                        $this->info($name.' url is repetitive ');     
                    }   
                }
                elseif(DB::table('version_flow_tools')->where('version_flow_id',$version_flow_id)->where('tool_type','phabricator')->where('tool_id',$tool_id)->value('id')){
                    return;
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

    public function get_phabricator_commits($db,$ip){
        $start_time = $this->get_start_time();
        $start_time = "2020-1-1 00:00:00";
        $ip_array = explode('.',$ip);
        $ip_str = end($ip_array);
        $select = "SELECT merge_requests.merge_commit_sha as commit_sha,a.email as author_email,a.username,merge_requests.id as review_id,
        merge_request_metrics.merged_at as commit_time,merge_requests.source_project_id as project_id,b.email as reviewer_email,merge_requests.created_at
        FROM merge_requests,users a,users b,merge_request_metrics 
        WHERE merge_requests.author_id = a.id 
        AND merge_requests.id = merge_request_metrics.merge_request_id 
        AND merge_request_metrics.merged_by_id = b.id
        AND merge_requests.merge_commit_sha is not null
        AND merge_request_metrics.merged_at>='".$start_time."'";
        // print($select);
        $merge_datas = $this->pdo_mysql($db,$select);#连接数据库
        // var_dump($merge_datas);
        foreach($merge_datas as $merge_data){
            $author_email = $merge_data['author_email'];
            $reviewer_email = $merge_data['reviewer_email'];
            $commit_sha = $merge_data['commit_sha'];
            $review_id = $merge_data['review_id'];
            $author_name = $merge_data['username'];
            $commit_time = $merge_data['commit_time'];
            $project_id = $merge_data['project_id'];
            $created_at = $merge_data['created_at'];
            $author_id = $reviewer_id = 0;
            $commit_status = 1;
            $tool_id = DB::table('tool_phabricators')->where('phab_id',$ip)->where('repo_id',$project_id)->value('id');
            #判断数据是否存在
            if(DB::table('phabricator_commits')->where('phab_id',$ip_str)->where('svn_id',$commit_sha)->where('tool_phabricator_id',$tool_id)->first()){
                continue;
            }
            if(preg_match("/kedacom.com$/",$author_email)){
                $author_id = DB::table('users')->where('email',$author_email)->value('id');
            }
            if(preg_match("/kedacom.com$/",$reviewer_email)){
                $reviewer_id = DB::table('users')->where('email',$reviewer_email)->value('id');
            }
            $commit_status = $this->get_commit_status($db,$review_id);
            $phabricator_commit_id = DB::table('phabricator_commits')->insertGetId(
                ['phab_id' => $ip_str,
                'svn_id' => $commit_sha,
                'author_id' => $author_id,
                'commit_person'=>$author_name,
                'tool_phabricator_id' => $tool_id,
                'commit_time' => $commit_time,
                'commit_status' => $commit_status
                ]);
            DB::table('phabricator_reviews')->insert(
                ['phab_id' => $ip_str,
                'review_id' => $review_id,
                'author_id' => $author_id,
                'action' => 'create',
                'comment' => $reviewer_id,
                'action_time' => $created_at,
                'phabricator_commit_id' => $phabricator_commit_id,
                'tool_phabricator_id'=>$tool_id,
                'commit_time'=>$commit_time
                ]);
            DB::table('phabricator_reviews')->insert(
                ['phab_id' => $ip_str,
                'review_id' => $review_id,
                'author_id' => $reviewer_id,
                'action' => 'accept',
                'comment' => '',
                'action_time' => $commit_time,
                'phabricator_commit_id' => $phabricator_commit_id,
                'tool_phabricator_id'=>$tool_id,
                'commit_time'=>$commit_time
                ]);
            $comment_array = $this->get_review_comment($db,$review_id);
            foreach($comment_array as $value){
                DB::table('phabricator_reviews')->insert(
                    ['phab_id' => $ip_str,
                    'review_id' => $review_id,
                    'author_id' => $value['author_id'],
                    'action' => 'comment',
                    'comment' => $value['comment'],
                    'action_time' => $value['action_time'],
                    'phabricator_commit_id' => $phabricator_commit_id,
                    'tool_phabricator_id'=>$tool_id,
                    'commit_time'=>$commit_time
                    ]);
            }
        }
    }

    public function get_start_time(){
        $commit_time = DB::table('phabricator_commits')->selectRaw("max(commit_time) as max_time")->whereRaw("tool_phabricator_id in (select id from tool_phabricators where tool_type=3)")->get()->toArray();
        return $commit_time[0]->max_time;
    }

    public function pdo_mysql($mysql_con,$select){
        $statement = $mysql_con->prepare($select);
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function get_commit_status($db,$merge_id){
        $select = "SELECT * FROM merge_request_diff_files where merge_request_diff_id = 
        (select max(id) from merge_request_diffs where merge_request_id =".(strval($merge_id)).")";
        // print($select);
        $path_datas = $this->pdo_mysql($db,$select);#连接数据库
        // var_dump($path_datas);
        $allBin = True;
        $allAdd = True;
        foreach($path_datas as $path_data){
            $path = $path_data['new_path'];
            $add  = $path_data['new_file'];
            if(!preg_match("/^.+(\.pdf|\.a|\.ko|\.ios|\.linux|\.exe|\.gz|\.bz2|\.zip|\.cab|\.tar|\.rar|\.dll|\.lib|\.so|\.bin|\.arj|\.z|\.obj|\.pdb|\.jar|\.com|\.res|\.dat|\.gwf|\.ipa|
                \.war|\.png|\.jpg|\.bmp|\.doc|\.mpp|\.edx|\.xz|\.docx|\.xlsx|\.xls|\.ppt|\.pptx|\.xmind|\.rtf|\.vsd|\.vsdx|\.pcm|\.bit|\.mp3|\.dtb|\.img|\.acm|\.map|\.pem|\.apk|\.eddx
                |\.eot|\.svg|\.ttf|\.woff|\.ico|\.gif|\.jed|\.pb|\.index|\.md|\.one|\.trt)$/",$path) && preg_match("/\.[a-z]+/",$path)){
                $allBin = False;
                break;
            }
            if($add == 'f'){
                $allAdd = False;
            }
        }
        if($allAdd && count($path_datas)>50){
            return 1;
        }
        if($allBin){
            return 1;
        }
        return 0;
    }

    public function get_review_comment($db,$review_id){
        $comment_array = [];
        $select = "SELECT notes.note,notes.system,users.email,notes.updated_at FROM notes,merge_requests,users 
        where notes.noteable_id = merge_requests.id and notes.system = 'f' and notes.author_id = users.id and merge_requests.id = ".strval($review_id);
        $comment_datas = $this->pdo_mysql($db,$select);#连接数据库
        foreach($comment_datas as $comment_data){
            $author_email = $comment_data['email'];
            if(preg_match("/kedacom.com$/",$author_email)){
                $author_id = DB::table('users')->where('email',$author_email)->value('id');
                $comment_array[] = [
                    'comment'=>$comment_data['note'],
                    'author_id'=>$author_id,
                    'action_time'=>$comment_data['updated_at'],
                ];
            }
        }
        return $comment_array;
    }
}