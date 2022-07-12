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
        // 'http://172.16.1.154:8090' => '_UvSYzvogqbruAd9kU69',
        'https://szgitlab.kedacom.com' => 'Fk1UcvYtadPLyWmrWnn2'
    ];

    protected $repo_array = [1502,1205,1452];

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
        foreach($this->gitlab_servers as $ip=>$token){
            $this->get_tool_phabricators($ip,$token);
            $this->get_phabricator_commits($ip,$token);
        }  
    }

    public function get_tool_phabricators($ip,$token){ 
        $url_part = explode('//',$ip)[1];
        foreach($this->repo_array as $repo_id){
            $base_url = $ip.'/api/v4/projects/'.$repo_id;
            $query = [
                'private_token' => $token,
            ];
            $project = $this->api_request($base_url,$query);
            // var_dump($project);
            $name = $project['name'];
            $repo_id = $project['id'];
            $url = "git@".$url_part.":".$project['path_with_namespace'].".git";
            if(!DB::table('tool_phabricators')->where('job_name',$name)->where('phab_id',$url_part)->first())#确认数据是否已存在
            {
                DB::table('tool_phabricators')->insert(['job_name' => $name,'phab_id'=> $url_part,'repo_id' => $repo_id,'review_type'=>1,'tool_type'=>3]);
            }
            $tool_id = DB::table('tool_phabricators')->where('job_name',$name)->where('phab_id',$url_part)->value('id');
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

    public function get_phabricator_commits($ip,$token){
        $url_part = explode('//',$ip)[1];
        $project_ids = $this->get_project_ids();
        // var_dump($project_ids);
        $start_time = $this->get_start_time();
        $ip_str = 888;
        foreach($project_ids as $project_id){
            $base_url =  $ip.'/api/v4/projects/'.$project_id.'/merge_requests';
            $query = [
                'private_token' => $token,
                'updated_after'=> $start_time,
                'per_page'=>1000
            ];
            $content = $this->api_request($base_url,$query);
            $content = array_reverse($content);
            foreach($content as $merge_data){
                // var_dump($merge_data);
                $iid = $merge_data['iid'];
                $author_id = $merge_data['author']['id'];
                $reviewer_id = $merge_data['assignee']['id'];
                $reviewer_name = $merge_data['assignee']['username'];
                $commit_sha = $merge_data['sha'];
                $review_id = $merge_data['id'];
                $author_name = $merge_data['author']['username'];
                $commit_time = $merge_data['merged_at'];
                $project_id = $merge_data['target_project_id'];
                $created_at = $merge_data['created_at'];
                $commit_status = 1;
                if(!$commit_time){
                    $this->info('['.date('Y-m-d H:i:s').']'.$commit_sha.'此次评审尚未提交'); 
                    continue;
                }
                else{
                    $commit_time = $this->handle_time_data($commit_time);
                }
                // print($commit_time);
                // exit(0);
                $tool_id = DB::table('tool_phabricators')->where('phab_id',$url_part)->where('repo_id',$project_id)->value('id');
                #判断数据是否存在
                if(DB::table('phabricator_commits')->where('phab_id',$ip_str)->where('svn_id',$commit_sha)->where('tool_phabricator_id',$tool_id)->first()){
                    continue;
                }
                $user_url =  $ip.'/api/v4/users';
                $query = [
                    'private_token' => $token,
                    'id'=> $author_id
                ];
                $author_info = $this->api_request($user_url,$query);
                $query = [
                    'private_token' => $token,
                    'id'=> $reviewer_id
                ];
                $reviewer_info = $this->api_request($user_url,$query);
                $author_id = $reviewer_id = 0;
                if(preg_match("/kedacom.com$/",$author_info[0]['email'])){
                    $author_id = DB::table('users')->where('email',$author_info[0]['email'])->value('id');
                }
                if(preg_match("/kedacom.com$/",$reviewer_info[0]['email'])){
                    $reviewer_id = DB::table('users')->where('email',$reviewer_info[0]['email'])->value('id');
                }
                // $author_id = DB::table('users')->where('email',$author_name.'@kedacom.com')->value('id')??0;
                // $reviewer_id = DB::table('users')->where('email',$reviewer_name.'@kedacom.com')->value('id')??0;

                [$commit_status,$paths] = $this->get_commit_status($base_url,$token,$iid);
                $phabricator_commit_id = DB::table('phabricator_commits')->insertGetId(
                    ['phab_id' => $ip_str,
                    'svn_id' => $commit_sha,
                    'author_id' => $author_id,
                    'commit_person'=>$author_name,
                    'tool_phabricator_id' => $tool_id,
                    'commit_time' => $commit_time,
                    'commit_status' => $commit_status
                    ]);
                // exit(0);
                foreach($paths as $path){
                    DB::table('phabricator_paths')->insert(
                        ['phabricator_commit_id' => $phabricator_commit_id,
                        'path' => $path]);
                }
                $created_at = $this->handle_time_data($created_at);
                
                [$action_array,$reviewer_array] = $this->get_review_action($base_url,$user_url,$token,$iid,$author_id);
                DB::table('phabricator_reviews')->insert(
                    ['phab_id' => $ip_str,
                    'review_id' => $review_id,
                    'author_id' => $author_id,
                    'action' => 'create',
                    'comment' => implode(',',$reviewer_array),
                    'action_time' => $created_at,
                    'phabricator_commit_id' => $phabricator_commit_id,
                    'tool_phabricator_id'=>$tool_id,
                    'commit_time'=>$commit_time
                    ]);

                foreach($action_array as $value){
                    DB::table('phabricator_reviews')->insert(
                        ['phab_id' => $ip_str,
                        'review_id' => $review_id,
                        'author_id' => $value['author_id'],
                        'action' => $value['action'],
                        'comment' => $value['comment'],
                        'action_time' => $value['action_time'],
                        'phabricator_commit_id' => $phabricator_commit_id,
                        'tool_phabricator_id'=>$tool_id,
                        'commit_time'=>$commit_time
                        ]);
                }
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

    public function get_commit_status($url,$token,$iid){
        $change_url =  $url.'/'.$iid.'/changes';
        $query = [
            'private_token' => $token,
        ];
        $change_datas = $this->api_request($change_url,$query);
        $allBin = True;
        $allAdd = True;
        $paths = [];
        $commit_status = 1;
        foreach($change_datas['changes'] as $change){
            $path = $change['new_path'];
            $add  = $change['new_file'];
            $paths[] = $path;
            if(!preg_match("/^.+(\.pdf|\.a|\.ko|\.ios|\.linux|\.exe|\.gz|\.bz2|\.zip|\.cab|\.tar|\.rar|\.dll|\.lib|\.so|\.bin|\.arj|\.z|\.obj|\.pdb|\.jar|\.com|\.res|\.dat|\.gwf|\.ipa|
                \.war|\.png|\.jpg|\.bmp|\.doc|\.mpp|\.edx|\.xz|\.docx|\.xlsx|\.xls|\.ppt|\.pptx|\.xmind|\.rtf|\.vsd|\.vsdx|\.pcm|\.bit|\.mp3|\.dtb|\.img|\.acm|\.map|\.pem|\.apk|\.eddx
                |\.eot|\.svg|\.ttf|\.woff|\.ico|\.gif|\.jed|\.pb|\.index|\.md|\.one|\.trt)$/",$path) && preg_match("/\.[a-z]+/",$path)){
                $allBin = False;
            }
            if(!$add){
                $allAdd = False;
            }
        }
        if($allAdd && count($change)>50){
            $commit_status = 0;
        }
        if($allBin){
            $commit_status = 0;
        }
        return [$commit_status,$paths];
    }

    public function get_review_action($url,$user_url,$token,$iid,$commit_author){
        $note_url =  $url.'/'.$iid.'/notes';
        $query = [
            'private_token' => $token,
            'per_page'=>1000
        ];
        $note_datas = $this->api_request($note_url,$query);
        $note_datas = array_reverse($note_datas);
        $action_array = [];
        $reviewer_array = [];
        $mention_accept = False;
        foreach($note_datas as $note_data){
            $author_id = $note_data['author']['id'];
            $query = [
                'private_token' => $token,
                'id'=> $author_id
            ];
            $author_info = $this->api_request($user_url,$query);
            $comment = $note_data['body'];
            // print($comment."\n");
            if(preg_match("/kedacom.com$/",$author_info[0]['email'])){
                $author_id = DB::table('users')->where('email',$author_info[0]['email'])->value('id');
            }
            else{
                $author_id = 0;
            }
            $action_time = $this->handle_time_data($note_data['updated_at']);
            if($note_data['system']){
                if(preg_match("/^(approved this merge request)/",$comment)){
                    if(!in_array($author_id,$reviewer_array)){
                        $reviewer_array[] = $author_id;
                    }
                    $action_array[] = [
                        'comment'=>'',
                        'action'=>'accept',
                        'author_id'=>$author_id,
                        'action_time'=>$action_time,
                    ];
                }
                elseif(preg_match("/^(unapproved this merge request)/",$comment)){
                    if(!in_array($author_id,$reviewer_array)){
                        $reviewer_array[] = $author_id;
                    }
                    $action_array[] = [
                        'comment'=>'',
                        'action'=>'reject',
                        'author_id'=>$author_id,
                        'action_time'=>$action_time,
                    ];
                }
                elseif(preg_match("/^mentioned in commit/",$comment)&&!$mention_accept){
                    $mention_accept = True;
                    if($commit_author!=$author_id){
                        if(!in_array($author_id,$reviewer_array)){
                            $reviewer_array[] = $author_id;
                        }
                        $action_array[] = [
                            'comment'=>'',
                            'action'=>'accept',
                            'author_id'=>$author_id,
                            'action_time'=>$action_time,
                        ];
                    }
                }
            }
            else{
                if(!in_array($author_id,$reviewer_array)){
                    $reviewer_array[] = $author_id;
                }
                $action_array[] = [
                    'comment'=>$comment,
                    'action'=>'comment',
                    'author_id'=>$author_id,
                    'action_time'=>$action_time,
                ];
            }  
        } 
        return [$action_array,$reviewer_array];
    }

    public function get_project_ids(){
        $project_ids = [];
        $res = DB::table('tool_phabricators')->select("repo_id")->where("tool_type",3)->orderBy('repo_id')->get()->toArray();
        foreach($res as $data){
            $project_ids[] = $data->repo_id;
        }
        return $project_ids;
    }

    public function api_request($base_url,$query){
        $client_url = new \GuzzleHttp\Client([
            'base_uri' => $base_url,
        ]);
        $response = $client_url->request('GET','', [
            'query' => $query
        ]);
        $res = $response->getBody()->getContents();
        $content =  json_decode($res,true);
        return $content;
    }

    public function handle_time_data($time_data){
        $time_str = explode('T',$time_data);
        $ymd = $time_str[0];
        $hms_str = explode('.',$time_str[1]);
        $hms = $hms_str[0];
        $time_data = $ymd.' '.$hms;
        return $time_data;
    }
}