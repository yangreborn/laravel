<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

date_default_timezone_set('PRC');

class PhabFetchData extends Command
{
    protected $mysql_infos = array(
                                array('172.16.0.123','phab','123456'),
                                array('172.16.0.124','phab','123456'),
                                array('172.16.0.129','phab','123456'),
                                array('172.16.0.131','phab','123456'),
                                array('172.16.0.132','phab','123456')                                                   
    );#phabricator服务器数据库信息-host、user、password
    protected $phab_ids = array(123,124,129,131,132);#phabricator服务器编号
    protected $mysql_con = array();#mysql连接存储数组
    protected $phab_dbname = array('phabricator_repository','phabricator_differential','phabricator_audit','phabricator_user');#所需phabricator数据库
    protected $diff_actionType = array(  'core:comment','differential:inline','differential:update',
                                    'differential.revision.reject','differential.revision.accept',
                                    'differential.revision.abandon','differential:action',
                                    'differential:create'    
                                );#提交前评审操作类型
    protected $diff_action = array('comment','inline_comment','update','reject','accept','abandon');#提交前评审操作描述
    protected $audit_actionType = array('core:comment','audit:inline','diffusion.commit.concern',
                                    'diffusion.commit.accept','diffusion.commit.auditors','audit:action'
                                ); #提交后评审操作类型
    protected $audit_action = array('comment','inline_comment','concern','accept','create');#提交后评审操作描述
    protected $type = array('commit','review','audit','reviewer','auditor');#操作区分数组
    protected $mpl_dbname = array('tool_phabricators','phabricator_commits','phabricator_reviews');#度量平台phabricator数据库
    protected $conf_users = array(
                                array('lxjcumt'=>'PHID-USER-i56bb3t7gla7wsqa7la7','yanglin'=>'PHID-USER-6k7ycqjarjn2pooqnt3x','wukai'=>'PHID-USER-e3osgcwxbvkxvc7olqta','wanglei_sxcxcsb'=>'PHID-USER-6fyvu22rfswm627h6iko'),
                                array('shzhangrong_yw'=>'PHID-USER-gkefoogypiddqxiykcw4','liubin_znjt'=>'PHID-USER-73y7t7pi6c6nwbiqa4ys',
                                'guolei_znjt'=>'PHID-USER-4uv4hl2hc26wxinjfuiq','zhangyu_jkcpx'=>'PHID-USER-ibrist4kunrvqjsjayei'),
                                array('gongweifeng'=>'PHID-USER-veoq3nhsmdonqhroushn','chenpeng'=>'PHID-USER-wnfoxafzaklvpijxcadw'),
                                array(),
                                array()
    );//特殊账号
    protected $cmolist = array('build60','build90','buildnvr','VisualSVN Server','buildkdm39','admin','build1','buildsolution','sysbuild','fangyanzhi','buildkdm','wangqingmei','buildsys','mashasha','liuruiying','svnsuperadmin','mac','synckeda');
    protected $server = 0;
    protected $all_git_repos = array();
    protected $git_repos = array();
    protected $multiple_array = [3,10,3,3,3];
    protected $time_multiple = 4;
    protected $project_infos = [
        "NVR_V7"=>[
            "department"=>"ZFCPB",
            "SQA"=>"zuoronghua",
            "url"=>"http://172.16.6.108/svn/NVR_V7/trunk/nvrv7/nvr_vob",
            "tool_type"=>"svn",
            "phab_id"=>"172.16.0.124",
            "need_path"=>[          
                "trunk/nvrv7/nvr_vob/10-common/include",
                "trunk/nvrv7/nvr_vob/41-service_svr",
                "trunk/nvrv7/nvr_vob/51-app_svr"
            ]
        ],
        "NVR_V7_PRODUCT"=>[
            "department"=>"60-nms",
            "SQA"=>"liufeng",
            "url"=>"http://172.16.6.108/svn/NVR_V7/trunk/nvrv7/nvr_vob",
            "tool_type"=>"svn",
            "phab_id"=>"172.16.0.124",
            "need_path"=>[          
                "trunk/nvrv7/nvr_vob/51-appext2",
                "trunk/nvrv7/nvr_vob/41-service_nvr",
                "trunk/nvrv7/nvr_vob/60-nms"
            ]
        ]
    ];
    #protected $review_revision_datas = array();
    /**
     * The name and signature of the console command.
     *
     * @var string0
     */
    protected $signature = 'PhabFetchData';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('['.date('Y-m-d H:i:s').'] Phabricator数据导入开始 vvv');
        $this->get_mysql_info();
        
        $repo_datas = $this->get_repo_data();#获取评审流数据
        $this->insert_repo_data($repo_datas);#插入评审流数据
        for($x=1;$x<2;$x++){#评审表及提交表   
            $this->git_repos = $this->all_git_repos[$x];#获取服务器的git流ID
            $phab_id = $this->phab_ids[$x];
            $this->time_multiple = $this->multiple_array[$x];
            $this->info('['.date('Y-m-d H:i:s').'] '.$phab_id.'服务器开始'); 
            // $mysql_con = $this->mysql_con[$x];#获取服务器连接
            $mysql_con = $this->mysql_infos[$x];#获取服务器连接
            $this->conf_user = $this->conf_users[$x];
            
            $this->info('['.date('Y-m-d H:i:s').'] 写入提交数据...');
            $commit_datas = $this->get_commit_data($mysql_con);#获取提交数据
            $this->info('['.date('Y-m-d H:i:s').'] 获取作者名...');
            $commit_datas = $this->get_author_name_data($mysql_con,$commit_datas,$this->type[0]);#获取作者名
            $this->info('['.date('Y-m-d H:i:s').'] 获取tool_phabricator_id...');
            $commit_datas = $this->get_tool_phabricator_id($commit_datas,$this->mysql_infos[$x][0]);#获取tool_phabricator_id
            $this->info('['.date('Y-m-d H:i:s').'] 插入提交数据...');
            $this->insert_commit_data($commit_datas,$phab_id,$mysql_con);#插入提交数据
            
            $this->info('['.date('Y-m-d H:i:s').'] 写入提交前评审数据...');
            $review_revision_datas = $this->get_review_revision_data($mysql_con);#获取提交前评审版本号数据
            $this->info('['.date('Y-m-d H:i:s').'] 获取提交前评审版本号数据...');
            $review_datas = $this->get_review_action_data($mysql_con,$review_revision_datas);#获取提交前评审版本号数据
            $this->info('['.date('Y-m-d H:i:s').'] 处理提交前评审操作数据...');
            $review_datas = $this->handle_review_action($mysql_con,$review_datas,$this->type[1]);#处理提交前评审操作数据
            $this->info('['.date('Y-m-d H:i:s').'] 设置提交前评审行数...');
            $this->set_diffline($mysql_con,$review_datas);#设置提交前评审行数
            $this->info('['.date('Y-m-d H:i:s').'] 获取提交前评审评语操作数据...');
            $review_datas = $this->get_review_comment_data($mysql_con,$review_datas,$this->type[1]);#获取提交前评审评语操作数据
            $this->info('['.date('Y-m-d H:i:s').'] 获取提交前评审操作作者名数据...');
            $review_datas = $this->get_author_name_data($mysql_con,$review_datas,$this->type[1]);#获取提交前评审操作作者名数据  
            $this->info('['.date('Y-m-d H:i:s').'] 获取commitPHID数据...');
            $review_datas = $this->get_commitPHID($mysql_con,$review_datas);#获取commitPHID数据
            $this->info('['.date('Y-m-d H:i:s').'] 插入提交前评审数据...');
            $this->insert_review_data($review_datas,$phab_id,$this->type[1]);#插入提交前评审数据

            $this->info('['.date('Y-m-d H:i:s').'] 写入提交后评审数据...');
            $audit_datas = $this->get_audit_data($mysql_con);#获取提交后操作数据
            $this->info('['.date('Y-m-d H:i:s').'] 获取提交后评语数据...');
            $audit_datas = $this->get_review_comment_data($mysql_con,$audit_datas,$this->type[2]);#获取提交后评语数据 
            $this->info('['.date('Y-m-d H:i:s').'] 处理提交后评审操作数据...');
            $audit_datas = $this->handle_review_action($mysql_con,$audit_datas,$this->type[2]);#处理提交后评审操作数据
            $this->info('['.date('Y-m-d H:i:s').'] 获取提交后评审操作作者名数据...');
            $audit_datas = $this->get_author_name_data($mysql_con,$audit_datas,$this->type[2]);#获取提交后评审操作作者名数据
            $this->info('['.date('Y-m-d H:i:s').'] 插入提交后评审数据...');
            $this->insert_review_data($audit_datas,$phab_id,$this->type[2]);#插入提交后评审数据
            // exit(0);

            $this->info('['.date('Y-m-d H:i:s').'] '.$phab_id.'服务器结束'); 
        }
        
        $this->info('['.date('Y-m-d H:i:s').'] 写入评审时间...'); 
        $this->set_duration();
        $this->set_manytomany();
        // Artisan::call('getgitlabdata');
        $this->info('['.date('Y-m-d H:i:s').'] Phabricator数据导入结束 ^^^');
    }

    #获取服务器数据库的连接
    public function get_mysql_info(){
        for($x=0;$x<5;$x++){
            $tmp = array();
            array_push($this->mysql_con,$tmp);
            for($y=0;$y<4;$y++){
                $dsn = "mysql:host=".$this->mysql_infos[$x][0].";dbname=".$this->phab_dbname[$y];#获取PDO连接
                $db = new \PDO($dsn,$this->mysql_infos[$x][1],$this->mysql_infos[$x][2],array(\PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"));
                array_push($this->mysql_con[$x],$db);
            }   
        }
    }
    #获取流数据
    public function get_repo_data(){
        $update_time = $this->get_update_time($this->mpl_dbname[0]);
        $repo_datas=array(); 
        $select = "select id,name,callsign,versionControlSystem,details from repository where details like \"%\\\"tracking-enabled\\\":true%\" or details like \"%\\\"tracking-enabled\\\":\\\"active\\\"%\"";
        for($x=0;$x<5;$x++){
            $tmp_repos = array();
            $repo_datas[$x] = $this->pdo_mysql($this->mysql_infos[$x],0,$select);#连接数据库
            $repo_len = count($repo_datas[$x]);
            for($y=0;$y<$repo_len;$y++){
                $repo_datas[$x][$y]['url']="";
                $repo_datas[$x][$y]['sqa_email'] = "";
                $str = str_replace("\\","",$repo_datas[$x][$y]['details']);#去掉details中多余的\
                if($repo_datas[$x][$y]['versionControlSystem'] == 'git'){//写入git url
                    array_push($tmp_repos,$repo_datas[$x][$y]['id']);
                    if(preg_match("/\"remote-uri\":\"([^,]+)\",.+/",$str,$matches)){
                        $repo_datas[$x][$y]['url'] = $matches[1];
                    }
                }
                else{#写入svn url
                    if(preg_match("/\"remote-uri\":\"([^,]+)\",.*\"svn-subpath\":\"([^,]+)\",.+/",$str,$matches)){
                        if(!preg_match("/\/$/",$matches[1])){
                            $matches[1] = $matches[1]."/";
                        }
                        $repo_datas[$x][$y]['url'] = rtrim($matches[1].$matches[2],"/");
                    }
                }
                if(preg_match("/\"description\":\".*sqa:([^,]+)\",.+/",$str,$matches)){
                    $repo_datas[$x][$y]['sqa_email'] = $matches[1];
                }
            }
            array_push($this->all_git_repos,$tmp_repos);          
        }
        return $repo_datas;
    }

#########################提交表数据获取1##############################################    

    #获取提交数据
    public function get_commit_data($mysql_con){       
        $update_time = $this->get_update_time($this->mpl_dbname[1]);#获取上次插入最新数据时间
        $commit_datas = array();
        $select = "select id,phid,repositoryID,commitIdentifier,epoch,authorPHID,importStatus from repository_commit where  epoch >=".$update_time;
        // $res_datas = $this->pdo_mysql($mysql_con[0],$select);#连接数据库  
        $res_datas = $this->pdo_mysql($mysql_con,0,$select);#连接数据库
        $commit_datas = $this->get_true_commitdata($mysql_con,$res_datas);     
        return $commit_datas;
    }

    #过滤不需要度量的提交/更正git提交时间/去除git已删除提交
    public function get_true_commitdata($mysql_con,$res_datas){
        $commit_len = count($res_datas);
        $commit_datas = array();
        for($x=0;$x<$commit_len;$x++){
            if($res_datas[$x]['importStatus'] == 3087){
                if(DB::table('phabricator_commits')->where('commit_phid',$res_datas[$x]['phid'])->where('svn_id',$res_datas[$x]['commitIdentifier'])->first()){
                    DB::table('phabricator_commits')->where('commit_phid',$res_datas[$x]['phid'])->where('svn_id',$res_datas[$x]['commitIdentifier'])->delete();
                }
                continue;
            }
            $filter = False;
            $select = "select commitMessage from repository_commitdata where commitID = ".$res_datas[$x]['id'];
            // $msg_datas = $this->pdo_mysql($mysql_con[0],$select);#连接数据库 
            $msg_datas = $this->pdo_mysql($mysql_con,0,$select);#连接数据库
            $commit_msg = explode("\n",$msg_datas[0]['commitMessage']);
            foreach($commit_msg as $line){#检查日志中是否有过滤信息
                if(preg_match("/Merge.+|Revert.+/i",$line)){
                    $select = "select versionControlSystem from repository where id = ".$res_datas[$x]['repositoryID'];
                    // $versionSys = $this->pdo_mysql($mysql_con[0],$select);#连接数据库
                    $versionSys = $this->pdo_mysql($mysql_con,0,$select);#连接数据库
                    if($versionSys[0]['versionControlSystem'] == 'git'){
                        $filter = True;
                        break;
                    }
                }
                if(preg_match("/CodeReview:No/",$line)){
                    $res_datas[$x]['filter'] = False;
                }
            }
            if($filter){
                $this->info($res_datas[$x]['phid'].' is merge or revert,filter...');
                continue;
            }
            array_push($commit_datas,$res_datas[$x]);
        }
        return $commit_datas;
    }

#########################提交表数据获取2##############################################

#########################评审表数据获取1##############################################

    #获取提交前评审版本数据
    public function get_review_revision_data($mysql_con){
        $update_time = $this->get_update_time($this->mpl_dbname[2]);#获取上次插入最新数据时间
        $review_revision_datas = array();
        $select = "select id,phid,authorPHID,dateCreated,lineCount from differential_revision where dateModified >=".$update_time;
        // $review_revision_datas = $this->pdo_mysql($mysql_con[1],$select);#连接数据库 
        $review_revision_datas = $this->pdo_mysql($mysql_con,1,$select);#连接数据库
        return $review_revision_datas;    
    }

    #获取提交前评审操作数据
    public function get_review_action_data($mysql_con,$review_revision_datas){
        $review_action_datas = array();
        $review_len = count($review_revision_datas);
        for($x=0;$x<$review_len;$x++){#获取提交前评审操作数据
            $select = "select phid,authorPHID,commentPHID,transactionType,newValue,dateCreated,dateModified from differential_transaction where objectPHID = \"".$review_revision_datas[$x]['phid']."\"";
            // $review_action_datas[$x] = $this->pdo_mysql($mysql_con[1],$select);#连接数据库 
            $review_action_datas[$x] = $this->pdo_mysql($mysql_con,1,$select);#连接数据库
            #插入提交前评审版本数据
            for($y=0;$y<count($review_action_datas[$x]);$y++){
                $review_action_datas[$x][$y]['review_id'] = $review_revision_datas[$x]['id'];
            }
            #添加所需元素
            $review_revision_datas[$x]['transactionType'] = 'differential:create';
            $review_revision_datas[$x]['newValue'] = 'create';
            $review_revision_datas[$x]['commentPHID'] = NULL;
            $review_revision_datas[$x]['review_id'] = $review_revision_datas[$x]['id'];
            $review_revision_datas[$x]['content'] = $this->get_reviewer_data($mysql_con,$review_revision_datas[$x]['phid']);
            array_unshift($review_action_datas[$x],$review_revision_datas[$x]);
        }
        return $review_action_datas;
    }

    #获取评审评语数据
    public function get_review_comment_data($mysql_con,$review_datas,$type){
        $review_comment_datas = array();
        $audit_comment_datas = array();
        $review_len = count($review_datas);#获取评审号数组长度
        #获取评审评语数据
        for($x=0;$x<$review_len;$x++){
            if($type == $this->type[1]){#提交前评语
                for($y=0;$y<count($review_datas[$x]);$y++){
                    $commentPHID = $review_datas[$x][$y]['commentPHID'];
                    if($commentPHID  == NULL){#判断是否次操作没有评语
                        if($review_datas[$x][$y]['newValue'] != 'create'){
                            $review_datas[$x][$y]['content'] = NULL;
                        }
                        continue;
                    }
                    $select = "select content from differential_transaction_comment where phid = \"".$commentPHID."\"";
                    // $review_comment_datas = $this->pdo_mysql($mysql_con[1],$select);#连接数据库
                    $review_comment_datas = $this->pdo_mysql($mysql_con,1,$select);#连接数据库  
                    $review_datas[$x][$y]['content'] = $review_comment_datas[0]['content'];
                }
            }
            #提交后评语
            elseif($type == $this->type[2]){
                $commentPHID = $review_datas[$x]['commentPHID'];
                if($commentPHID  == NULL){
                    if($review_datas[$x]['newValue'] != 'create'){
                        $review_datas[$x]['content'] = NULL;
                    }
                    continue;
                }
                $select = "select content from audit_transaction_comment where phid = \"".$commentPHID."\"";
                // $audit_comment_datas = $this->pdo_mysql($mysql_con[2],$select);#连接数据库 
                $audit_comment_datas = $this->pdo_mysql($mysql_con,2,$select);#连接数据库  
                $review_datas[$x]['content'] = $audit_comment_datas[0]['content'];
            }
        }
        return $review_datas;
    }

    #获取commitPHID数据
    public function get_commitPHID($mysql_con,$review_datas){
        $review_commitPHID_datas = array();
        $review_len = count($review_datas);#获取评审号数组长度
        #获取提交前评审评语数据
        for($x=0;$x<$review_len;$x++){
            #获取单个评审号操作数组长度
            $review_action_len = count($review_datas[$x]);
            for($y=0;$y<$review_action_len;$y++){
                $select = "select commitPHID from differential_commit where revisionID = ".$review_datas[$x][$y]['review_id'];
                // $review_commitPHID_datas = $this->pdo_mysql($mysql_con[1],$select);#连接数据库 
                $review_commitPHID_datas = $this->pdo_mysql($mysql_con,1,$select);#连接数据库  
                if($review_commitPHID_datas == NULL){#如果评审没有对应提交记录设为NULL
                    $review_datas[$x][$y]['commitPHID'] = NULL;
                    continue;
                }
                $review_datas[$x][$y]['commitPHID'] = $review_commitPHID_datas[0]['commitPHID'];
            }  
        }
        return $review_datas;
    }

    #获取提交后评审记录
    public function get_audit_data($mysql_con){
        $update_time = $this->get_update_time($this->mpl_dbname[2]);#获取上次插入最新数据时间
        $audit_datas = array();
        $transactions = implode("','",$this->audit_actionType);
        $select = "select id,phid,authorPHID,dateCreated,transactionType,newValue,commentPHID,objectPHID from audit_transaction where transactionType in ('".$transactions."') and dateModified >=".$update_time;
        print($select."\n");
        exit(0);
        // $audit_datas = $this->pdo_mysql($mysql_con[2],$select);#连接数据库\
        $audit_datas = $this->pdo_mysql($mysql_con,2,$select);#连接数据库
        return $audit_datas;
    }

#########################评审表数据获取2###############################################  

#########################度量平台数据插入1##############################################

    #插入tool_phabricators表数据project_infos
    public function insert_repo_data($repo_datas){
        for($x=0;$x<5;$x++){
            $phab_id = $this->mysql_infos[$x][0];#获取服务器id
            $repo_len = count($repo_datas[$x]);
            for($y=0;$y<$repo_len;$y++){
                $this->handle_repo_data($repo_datas[$x][$y],$phab_id);
            }
        }
        foreach($this->project_infos as $key=>$project_info){
            $repo_data = [];
            if($project_info['department'] == '60-nms'){
                $repo_data['url'] = $project_info['url']."/".$project_info['department'];
            }
            else{
                $repo_data['url'] = $project_info['url']."-".$project_info['department'];
            }
            $repo_data['id'] = 0;
            $repo_data['callsign'] = "";
            $repo_data['name'] = $key."-".$project_info['department'];

            $repo_data['versionControlSystem']=$project_info['tool_type'];
            $repo_data['sqa_email'] = $project_info['SQA']."@kedacom.com";
            $phab_id = $project_info['phab_id'];
            $this->handle_repo_data($repo_data,$phab_id);
        }
    }

    #插入phabricator_reviews表数据
    public function insert_review_data($review_datas,$phab_id,$type){
        $review_len = count($review_datas);#获取评审号数组长度
        for($x=0;$x<$review_len;$x++){
            if($type == $this->type[1]){#提交前评审
                for($y=0;$y<count($review_datas[$x]);$y++){
                    if(in_array($review_datas[$x][$y]['transactionType'],$this->diff_actionType)){#判断transactionType是否需要
                        $commit_res = DB::table('phabricator_commits')->where('phab_id',$phab_id)->where('commit_phid',$review_datas[$x][$y]['commitPHID'])->get()->toArray();
                        if(!isset($commit_res[0]->id)){
                            $this->line($review_datas[$x][$y]['commitPHID']." is not exist!");
                            continue;
                        }
                        else{
                            $commit_id = $commit_res[0]->id;
                            $tool_phabricator_id = $commit_res[0]->tool_phabricator_id;
                            $commit_time = $commit_res[0]->commit_time;
                        }
                        $data = DB::table('phabricator_reviews')->where('phab_id',$phab_id)->where('action_phid',$review_datas[$x][$y]['phid'])->first();
                        if($data){#判断数据是否存在
                            DB::table('phabricator_reviews')->where('phab_id',$phab_id)->where('action_phid',$review_datas[$x][$y]['phid'])->update(['phabricator_commit_id'=>$commit_id,'comment'=>$review_datas[$x][$y]['content']]);
                            continue;
                        }
                        if(($review_datas[$x][$y]['dateCreated']<1451577600) || !isset($review_datas[$x][$y]['author_id'])){
                            continue;
                        } 
                        $action_time = date("Y-m-d H:i:s",$review_datas[$x][$y]['dateCreated']);
                        if($review_datas[$x][$y]['newValue'] == 'create'){
                            $data = DB::table('phabricator_reviews')->where('phabricator_commit_id',$commit_id)->where('action','create')->whereNotNull('review_id')->first();
                            if(isset($data)){
                                $reviewer_list = $dealer_list = [];
                                if($data->action_time==$action_time){
                                    continue;
                                }
                                $deal_datas = DB::table('phabricator_reviews')->where('phabricator_commit_id',$commit_id)->whereIn('action',['accept','reject'])->get();
                                foreach($deal_datas as $deal_data){
                                    $dealer_list[] = $deal_data->author_id;
                                }
                                if($data->action_time<$action_time){
                                    $reviewer_list = explode(',',$review_datas[$x][$y]['content']);
                                }
                                if($data->action_time>$action_time){
                                    $reviewer_list = explode(',',$data->comment);
                                }
                                if(isset($dealer_list)){
                                    foreach($dealer_list as $dealer){
                                        if(!in_array($dealer,$reviewer_list)){
                                            $reviewer_list[] = $dealer;
                                        }
                                    }
                                }
                                $reviewers = trim(implode(',',$reviewer_list),',');
                                DB::table('phabricator_reviews')->where('phabricator_commit_id',$commit_id)->where('action','create')->whereNotNull('review_id')->update(['comment'=>$reviewers]);
                                continue;
                            }     
                        }  
                        DB::table('phabricator_reviews')->insert(
                            ['phab_id' => $phab_id,
                            'action_phid' => $review_datas[$x][$y]['phid'],
                            'review_id' => $review_datas[$x][$y]['review_id'],
                            'author_id' => $review_datas[$x][$y]['author_id'],
                            'action' => $review_datas[$x][$y]['newValue'],
                            'comment' => $review_datas[$x][$y]['content'],
                            'action_time' => $action_time,
                            'phabricator_commit_id' => $commit_id,
                            'tool_phabricator_id'=>$tool_phabricator_id,
                            'commit_time'=>$commit_time
                            ]);
                    }
                }
            }
            #提交后评审
            elseif($type == $this->type[2]){
                if(in_array($review_datas[$x]['transactionType'],$this->audit_actionType)){
                    $commit_res = DB::table('phabricator_commits')->where('phab_id',$phab_id)->where('commit_phid',$review_datas[$x]['objectPHID'])->get()->toArray();
                    if(!isset($commit_res[0]->id)){
                        $this->info('['.date('Y-m-d H:i:s').']'.$review_datas[$x]['objectPHID'].'提交后评审数据异常...'); 
                        continue;
                    }
                    else{
                        $commit_id = $commit_res[0]->id;
                        $tool_phabricator_id = $commit_res[0]->tool_phabricator_id;
                        $commit_time = $commit_res[0]->commit_time;
                    }
                    
                    $data = DB::table('phabricator_reviews')->where('phab_id',$phab_id)->where('action_phid',$review_datas[$x]['phid'])->first();
                    if($data){#判断数据是否存在
                        DB::table('phabricator_reviews')->where('phab_id',$phab_id)->where('action_phid',$review_datas[$x]['phid'])->update(['phabricator_commit_id'=>$commit_id,'comment'=>$review_datas[$x]['content']]);
                        continue;
                    }
                    if(($review_datas[$x]['dateCreated']<1451577600) || !isset($review_datas[$x]['author_id']))
                    {
                        continue;
                    } 
                    $action_time = date("Y-m-d H:i:s",$review_datas[$x]['dateCreated']);
                    if($review_datas[$x]['newValue'] == 'create'){#
                        $data = DB::table('phabricator_reviews')->where('phabricator_commit_id',$commit_id)->where('action','create')->whereNull('review_id')->first();
                        if(isset($data)){
                            $reviewer_list = $dealer_list = [];
                            if($data->action_time==$action_time){
                                continue;
                            }
                            $deal_datas = DB::table('phabricator_reviews')->where('phabricator_commit_id',$commit_id)->whereIn('action',['accept','reject'])->get();
                            foreach($deal_datas as $deal_data){
                                $dealer_list[] = $deal_data->author_id;
                            }
                            if($data->action_time<$action_time){
                                $reviewer_list = explode(',',$review_datas[$x]['content']);
                            }
                            if($data->action_time>$action_time){
                                $reviewer_list = explode(',',$data->comment);
                            } 
                            if(isset($dealer_list)){
                                foreach($dealer_list as $dealer){
                                    if(!in_array($dealer,$reviewer_list)){
                                        $reviewer_list[] = $dealer;
                                    }
                                }
                            }
                            $reviewers = trim(implode(',',$reviewer_list),',');
                            DB::table('phabricator_reviews')->where('phabricator_commit_id',$commit_id)->where('action','create')->whereNull('review_id')->update(['comment'=>$reviewers]);
                            $this->info('['.date('Y-m-d H:i:s').']'.$review_datas[$x]['phid'].' 更新评审人信息...'.$reviewers); 
                            continue;   
                        }
                    } 
                    if($review_datas[$x]['newValue'] == 'concern'){
                        $review_datas[$x]['newValue'] = 'reject';
                    }
                    DB::table('phabricator_reviews')->insert(
                        ['phab_id' => $phab_id,
                        'action_phid' => $review_datas[$x]['phid'],
                        'review_id' => NULL,
                        'author_id' => $review_datas[$x]['author_id'],
                        'action' => $review_datas[$x]['newValue'],
                        'comment' => $review_datas[$x]['content'],
                        'action_time' => $action_time,
                        'phabricator_commit_id' => $commit_id,
                        'tool_phabricator_id'=>$tool_phabricator_id,
                        'commit_time'=>$commit_time
                        ]);
                } 
            }
        }       
    }

    #插入phabricator_commits表数据
    public function insert_commit_data($commit_datas,$phab_id,$mysql_con)
    {
        $projects = array();
        $repo_projects = DB::table('tool_phabricators')->whereNotNull('project_id')->get();#获取project_id
        foreach($repo_projects as $item){
            $projects[$item->id] = $item->project_id;
            $repos[] = $item->id;
        }
        $users = array();
        foreach($projects as $key=>$item){
            $project_users = DB::table('project_users')->where('project_id',$item)->get();
            $users[$key] = array(); 
            foreach($project_users as $user){
                array_push($users[$key],$user->user_id);
            }                      
        }
        $commit_len = count($commit_datas);#获取提交数组长度
        for($x=0;$x<$commit_len;$x++){
            $is_third = False;
            $unknown_author = False;
            $paths = array();
            #判断数据是否存在
            if(DB::table('phabricator_commits')->where('phab_id',$phab_id)->where('svn_id',$commit_datas[$x]['commitIdentifier'])->where('tool_phabricator_id',$commit_datas[$x]['tool_phabricator_id'])->first()){
                continue;
            }
            #判断时间是否过早
            if(($commit_datas[$x]['epoch']<1451577600)&&($commit_datas[$x]['epoch']!=1)||$commit_datas[$x]['epoch'] == 0||$commit_datas[$x]['epoch']>time()){
                continue;
            }
            if(!isset($commit_datas[$x]['author_id'])){
                if(isset($commit_datas[$x]['author_name'])){
                    $commit_datas[$x]['author_id'] = 0;
                    $unknown_author = True;
                }
                else{
                    continue;
                }
            }   
            //去除文件全部为二进制的提交
            $select = "select pathID from repository_pathchange where isDirect = 1 and commitID = ".$commit_datas[$x]['id'];#changeType 1:Add 2:Modify 3:del 4:move|rename 7:svn cp 
            // $path_id = $this->pdo_mysql($mysql_con[0],$select);#连接数据库
            $path_id = $this->pdo_mysql($mysql_con,0,$select);#连接数据库
            $path_len = count($path_id);
            $not_bin = 0;
            $commit_status = 0;
            $path_ids = array();
            for($y=0;$y<$path_len;$y++){
                array_push($path_ids,$path_id[$y]['pathID']);
            }
            $select = "select * from repository_path where id in (".implode(",",$path_ids).")";
            // $tmp_paths = $this->pdo_mysql($mysql_con[0],$select);#连接数据库
            $tmp_paths = $this->pdo_mysql($mysql_con,0,$select);#连接数据库
            foreach($tmp_paths as $tmp_path){
                array_push($paths,$tmp_path['path']);
            }
            if(!isset($commit_datas[$x]['filter'])){
                if(!$unknown_author){
                    //判断提交是否为第三方文件
                    $select = "select count(*) as path_count from repository_pathchange where isDirect = 1 and commitID = ".$commit_datas[$x]['id'];#changeType 1:Add 2:Modify 3:del 4:move|rename 7:svn cp 
                    // $path_count = $this->pdo_mysql($mysql_con[0],$select);#连接数据库
                    $path_count = $this->pdo_mysql($mysql_con,0,$select);#连接数据库
                    if($path_count[0]['path_count']>=50){
                        $select = "select count(*) as not_add from repository_pathchange where isDirect = 1 and changeType != 1 and commitID = ".$commit_datas[$x]['id'];#changeType 1:Add 2:Modify 3:del 4:move|rename 7:svn cp 
                        // $not_add = $this->pdo_mysql($mysql_con[0],$select);#连接数据库
                        $not_add = $this->pdo_mysql($mysql_con,0,$select);#连接数据库
                        if($not_add[0]['not_add']==0){
                            $is_third = True;
                        }
                    }
                    if(!$is_third){
                        foreach($paths as $path){
                            if(!preg_match("/^.+(\.pdf|\.a|\.ko|\.ios|\.linux|\.exe|\.gz|\.bz2|\.zip|\.cab|\.tar|\.rar|\.dll|\.lib|\.so|\.bin|\.arj|\.z|\.obj|\.pdb|\.jar|\.com|\.res|\.dat|\.gwf|\.ipa|
                            \.war|\.png|\.jpg|\.bmp|\.doc|\.mpp|\.edx|\.xz|\.docx|\.xlsx|\.xls|\.ppt|\.pptx|\.xmind|\.rtf|\.vsd|\.vsdx|\.pcm|\.bit|\.mp3|\.dtb|\.img|\.acm|\.map|\.pem|\.apk|\.eddx
                            |\.eot|\.svg|\.ttf|\.woff|\.ico|\.gif|\.jed|\.pb|\.index|\.md|\.one|\.trt)$/",$path) && preg_match("/\.[a-z]+$/",$path)){
                                $not_bin = 1;
                                break;
                            }
                        }
                    }
                }  
                if($not_bin){
                    $tool_phabricator_id = $commit_datas[$x]['tool_phabricator_id'];        
                    if(in_array($tool_phabricator_id,$repos)){
                        if(!(in_array($commit_datas[$x]['author_id'],$users[$tool_phabricator_id]))){#添加项目成员
                            if(!DB::table('project_users')->where('project_id',$projects[$tool_phabricator_id])->where('user_id',$commit_datas[$x]['author_id'])->first()){
                                DB::table('project_users')->insert(
                                    ['project_id'=> $projects[$tool_phabricator_id],
                                    'user_id'=> $commit_datas[$x]['author_id']
                                    ]);
                            }    
                        }
                    }
                    $commit_status = 1;
    
                    $filter_str = DB::table('tool_phabricators')->where('id',$tool_phabricator_id)->value('filter_type');
                    $filter_array = explode(',',$filter_str);
                    $filter_str = implode('|\\.',$filter_array);
                    $file_num = 0;
                    foreach($paths as $path){
                        if(!preg_match("/^.+(\.".$filter_str.")$/",$path)&&preg_match("/\.[a-z]+/",$path)){
                            break;
                        }   
                        else{
                            $file_num++;
                        }
                    }
                    if($file_num == count($paths)){
                        $commit_status = 0;
                    }   
                }
                else{
                    $this->line($commit_datas[$x]['phid']." is all binary!");
                }  
            }
            
            $phabricator_commit_id = DB::table('phabricator_commits')->insertGetId(
                ['phab_id' => $phab_id,
                'commit_phid' => $commit_datas[$x]['phid'],
                'svn_id' => $commit_datas[$x]['commitIdentifier'],
                'author_id' => $commit_datas[$x]['author_id'],
                'commit_person'=>$commit_datas[$x]['author_name'],
                'tool_phabricator_id' => $commit_datas[$x]['tool_phabricator_id'],
                'commit_time' => date("Y-m-d H:i:s",$commit_datas[$x]['epoch']),
                'commit_status' => $commit_status
                ]);
            foreach($paths as $path){
                DB::table('phabricator_paths')->insert(
                    ['phabricator_commit_id' => $phabricator_commit_id,
                    'path' => $path]);
            }    
        }
    }

#########################度量平台数据插入2##############################################

#########################数据处理1##############################################

    public function get_update_time($table){#获取度量平台表最新更新时间
        #获取最新更新时间
        $fresh_time = DB::table($table)->max('created_at');
        $update_time = strtotime($fresh_time)-86400 * $this->time_multiple;
        return $update_time;
    }

    public function handle_review_action($mysql_con,$review_datas,$type){#处理提交前评审操作
        $review_len = count($review_datas);
        for($x=0;$x<$review_len;$x++){
            if($type == $this->type[1]){#提交前评审
                $review_inner_len = count($review_datas[$x]);
                for($y=0;$y<$review_inner_len;$y++){
                    for($z=0;$z<count($this->diff_action);$z++){
                        if($review_datas[$x][$y]['transactionType'] == $this->diff_actionType[$z]){#判断是否在要处理的操作类型内
                            $review_datas[$x][$y]['newValue'] = $this->diff_action[$z];
                        }
                    }
                    if($review_datas[$x][$y]['transactionType'] == "differential:action"){
                        $review_datas[$x][$y]['newValue'] = trim($review_datas[$x][$y]['newValue'],"\"");#去除旧版操作数据中的""
                    }
                }
            }
            elseif($type == $this->type[2]){#提交后评审
                if($review_datas[$x]['transactionType'] == $this->audit_actionType[4] && $review_datas[$x]['newValue'] != "[]"){
                    $review_datas[$x]['content'] = $this->get_author_name_data($mysql_con,$review_datas[$x]['newValue'],$this->type[4]);
                }
                for($z=0;$z<count($this->audit_action);$z++){
                    if($review_datas[$x]['transactionType'] == $this->audit_actionType[$z]){
                        $review_datas[$x]['newValue'] = $this->audit_action[$z];
                    }
                }
                if($review_datas[$x]['transactionType'] == "audit:action"){
                    $review_datas[$x]['newValue'] = trim($review_datas[$x]['newValue'],"\"");#去除旧版操作数据中的""
                }
            }
        }
        return $review_datas;
    }

    #获取作者名数据
    public function get_author_name_data($mysql_con,$datas,$type){
        $review_author_name_datas = array();
        if($type == $this->type[1]){#提交前评审
            $len = count($datas);
            for($x=0;$x<$len;$x++){
                $review_action_len = count($datas[$x]);
                for($y=0;$y<$review_action_len;$y++){
                    if(preg_match("/PHID-APPS/",$datas[$x][$y]['authorPHID'])){
                        $datas[$x][$y]['author_id'] = NULL;
                        continue;
                    }
                    if(!isset($datas[$x][$y]['authorPHID']) ){
                        $datas[$x][$y]['author_id'] = NULL;
                        $this->line($datas[$x][$y]['review_id']." ".$datas[$x][$y]['newValue']);
                        continue;
                    }
                    $select = "select address from user_email where userPHID =\"".$datas[$x][$y]['authorPHID']."\"";
                    // $review_email_datas = $this->pdo_mysql($mysql_con[3],$select);#连接数据库
                    $review_email_datas = $this->pdo_mysql($mysql_con,3,$select);#连接数据库
                    if(!empty($review_email_datas)){
                        $id = DB::table('users')->where('email',$review_email_datas[0]['address'])->value('id');
                    }
                    if(!isset($id)){#如果用户已被删除设为Unknown
                        $datas[$x][$y]['author_id'] = NULL;
                    }
                    else{
                        $datas[$x][$y]['author_id'] = $id;
                    } 
                }
            }
        }
        #提交后评审/提交
        if($type == $this->type[0] or $type == $this->type[2]) {
            $len = count($datas);
            for($x=0;$x<$len;$x++){
                if($type == $this->type[0]){
                    $select = "select authorName from repository_commitdata where commitID = ".$datas[$x]['id']; 
                    // $author_name = $this->pdo_mysql($mysql_con[0],$select);#连接数据库
                    if($type == $this->type[2]){
                        print($select."\n");
                    }
                    // print($select."\n");
                    $author_name = $this->pdo_mysql($mysql_con,0,$select);#连接数据库
                    if(isset($author_name[0]['authorName'])){
                        if(!in_array($author_name[0]['authorName'],$this->cmolist)){
                            $datas[$x]['author_name'] = $author_name[0]['authorName'];
                        }
                        else{
                            continue;
                        }
                    }
                }
                if(preg_match("/PHID-APPS/",$datas[$x]['authorPHID'])){
                    $datas[$x]['author_id'] = NULL;
                    continue;
                }
                if(!isset($datas[$x]['authorPHID'])){
                    $select = "select revisionID from differential_commit where commitPHID = \"".$datas[$x]['phid']."\"";
                    // $revision_id = $this->pdo_mysql($mysql_con[1],$select);#连接数据库
                    $revision_id = $this->pdo_mysql($mysql_con,1,$select);#连接数据库
                    if(!isset($revision_id[0]['revisionID'])){
                        if(in_array($author_name[0]['authorName'],$this->conf_user)){
                            $datas[$x]['authorPHID'] = $this->conf_user[$author_name[0]['authorName']];
                        }
                        elseif(!in_array($author_name[0]['authorName'],$this->cmolist)){
                            $datas[$x]['authorPHID'] = NULL;
                            if($type == $this->type[0]){
                                $this->line($author_name[0]['authorName']." ".$datas[$x]['phid']." ".$datas[$x]['commitIdentifier']);
                            }
                            if($type == $this->type[2]){
                                $this->line($author_name[0]['authorName']." ".$datas[$x]['phid']." ".$datas[$x]['newValue']);
                            }
                            continue;
                        }
                        else{
                            continue;
                        }
                    }
                    else{
                        $select = "select authorPHID from differential_revision where id = ".$revision_id[0]['revisionID'];
                        // $authorPHID = $this->pdo_mysql($mysql_con[1],$select);#连接数据库
                        $authorPHID = $this->pdo_mysql($mysql_con,1,$select);#连接数据库
                        $datas[$x]['authorPHID'] = $authorPHID[0]['authorPHID'];
                    }  
                }
                $select = "select address from user_email where userPHID =\"".$datas[$x]['authorPHID']."\"";
                // if($type == $this->type[2]){
                //     print($select."\n");
                // }
                // $commit_email_datas = $this->pdo_mysql($mysql_con[3],$select);#连接数据库
                $commit_email_datas = $this->pdo_mysql($mysql_con,3,$select);#连接数据库
                if(isset($commit_email_datas[0])){
                    $id = DB::table('users')->where('email',$commit_email_datas[0]['address'])->value('id');
                }
                if(!isset($id)){    
                    if($type == $this->type[0]){
                        $this->line($commit_email_datas[0]['address']." ".$datas[$x]['phid']." ".$datas[$x]['commitIdentifier']);
                    }
                    if($type == $this->type[2]){
                        $this->line($commit_email_datas[0]['address']." ".$datas[$x]['phid']." ".$datas[$x]['newValue']);
                    }
                    $datas[$x]['author_id'] = NULL;
                }
                else{
                    $datas[$x]['author_id'] = $id;
                }
            }
        }
        if($type == $this->type[3]){#获取评审人
            $select = "select address from user_email where userPHID =\"".$datas."\"";
            // $commit_email_datas = $this->pdo_mysql($mysql_con[3],$select);#连接数据库
            $commit_email_datas = $this->pdo_mysql($mysql_con,3,$select);#连接数据库
            //判断评审人账号是否已删除
            if(!isset($commit_email_datas[0])){
                $datas = NULL;
            }
            else{
                $id = DB::table('users')->where('email',$commit_email_datas[0]['address'])->value('id');
                if(!isset($id)){
                    $datas = NULL;
                }
                else{
                    $datas = $id;
                }
            }
        }
        if($type == $this->type[4]){#获取提交后评审人
            $datas = explode(',',$datas);
            $x = 0;
            foreach($datas as $item){
                $list2[$x++] = explode(':',$item);
            }
            $pattern = "/PHID-USER-[a-z0-9]+/";
            $datas = [];
            foreach($list2 as $item){
                preg_match($pattern, $item[0], $matches);
                array_push($datas,$matches);
            }
            $reviewer_list = [];
            foreach($datas as $value){
                $select = "select address from user_email where userPHID =\"".$value[0]."\"";
                // $commit_email_datas = $this->pdo_mysql($mysql_con[3],$select);#连接数据库
                $commit_email_datas = $this->pdo_mysql($mysql_con,3,$select);#连接数据库
                if(isset($commit_email_datas[0])){
                    $id = DB::table('users')->where('email',$commit_email_datas[0]['address'])->value('id');
                    array_push($reviewer_list,$id);
                }  
            }
            $datas = implode(',',$reviewer_list);
        }
        return $datas;
    }

    #获取tool_phabricator_id
    public function get_tool_phabricator_id($commit_datas,$phab_id){
        $len = count($commit_datas);
        for($x=0;$x<$len;$x++){
            $id = DB::table('tool_phabricators')->where('repo_id',$commit_datas[$x]['repositoryID'])->where('phab_id',$phab_id)->value('id');
            $commit_datas[$x]['tool_phabricator_id'] = $id;
        }
        return $commit_datas;
    }

    //获取提交前评审人
    public function get_reviewer_data($mysql_con,$review_phid){
        $select = "select reviewerPHID from differential_reviewer where revisionPHID = \"".$review_phid."\"";
        // $reviewer_datas = $this->pdo_mysql($mysql_con[1],$select);#连接数据库
        $reviewer_datas = $this->pdo_mysql($mysql_con,1,$select);#连接数据库
        $reviewer_list = [];
        foreach($reviewer_datas as $item){
            array_push($reviewer_list,$this->get_author_name_data($mysql_con,$item['reviewerPHID'],$this->type[3]));
        }
        $reviewer_list = implode(',',$reviewer_list);
        return $reviewer_list;
    }

#########################数据处理2##############################################

    public function handle_repo_data($repo_data,$phab_id){
        $name = $repo_data['name'];
        $repo_id = $repo_data['id'];
        $callsign = $repo_data['callsign'];
        $url = $repo_data['url'];
        // if(!preg_match("/\/$/",$url)){
        //     $url = $url."/";
        // }
        $version_tool = $repo_data['versionControlSystem'];
        $sqa_email = $repo_data['sqa_email'];
        if(DB::table('tool_phabricators')->where('job_name',$name)->where('phab_id',$phab_id)->first())#确认数据是否已存在
        {
            DB::table('tool_phabricators')->where('job_name',$name)->update(['callsign' => $callsign]);
        }
        else{
            if($version_tool == 'git'){
                DB::table('tool_phabricators')->insert(['job_name' => $name,'phab_id'=> $phab_id,'repo_id' => $repo_id,'callsign' => $callsign,'review_type'=>2]);
            }
            else{
                DB::table('tool_phabricators')->insert(['job_name' => $name,'phab_id'=> $phab_id,'repo_id' => $repo_id,'callsign' => $callsign]);
            }
            
        }
        if($url==""){#此处判断是否写入version_flows和version_flow_tools
            return;
        }
        $tool_id = DB::table('tool_phabricators')->where('job_name',$name)->where('phab_id',$phab_id)->value('id');
        $version_flow_id = DB::table('version_flows')->where('url',$url)->value('id');
        if($version_flow_id){   
            if(!empty($sqa_email)){
                DB::table('version_flows')->where('url',$url)->update(['sqa_email' => $sqa_email]);
            }
            if(DB::table('version_flow_tools')->where('version_flow_id',$version_flow_id)->where('tool_type','phabricator')->where('tool_id','!=',$tool_id)->whereNull('deleted_at')->value('id')){
                if(!preg_match("/test|yfzlb/",$name)){
                    $this->info($name.' url is repetitive ');
                    // $today = Carbon::now()->toDateTimeString();
//                     $content = <<<markdown
// ### 度量平台异常信息 @ $today\n
// > 信息： <font color="comment">phabricator工具url重复部署</font>
// > 部署名： <font color="comment">$name</font>
// > URL： <font color="comment">$url</font>
// markdown;
//                     $message = [
//                         'data' => ['content' => $content],
//                         'key' => config('wechat.wechat_robot_key.svnurl_remind'),
//                         'type' => 'markdown',
//                     ];
//                     if(!empty($message)){
//                         wechat_bot($message['data'], $message['key'], $message['type']);
//                     }      
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
            DB::table('version_flows')->insert(['url'=>$url,'version_tool'=>$version_tool]);
            $version_flow_id = DB::table('version_flows')->where('url',$url)->value('id');
            DB::table('version_flow_tools')->insert(['version_flow_id'=>$version_flow_id,'tool_type'=>'phabricator','tool_id'=>$tool_id]);
        }
    }

    public function set_manytomany(){
        $update_time = $this->get_update_time($this->mpl_dbname[1]);
        $update_time = date("Y-m-d H:i:s",$update_time);
        foreach($this->project_infos as $key=>$project_info){
            $path_str = "";
            $path_list = $project_info['need_path'];
            $commit_infos = [];
            foreach($path_list as $path){
                $path_str = $path_str."or path like '%".$path."%' ";
            }       
            $len = strlen($path_str)-2;
            $need_str = substr($path_str,0-$len);
            $child_id = DB::table('tool_phabricators')->where('job_name',$key."-".$project_info['department'])->value('id');
            $commits = DB::table('phabricator_paths')->whereRaw("phabricator_commit_id in (select id from phabricator_commits where commit_time>=? and tool_phabricator_id = (select id from tool_phabricators
            where job_name = ?))",["'$update_time'","'$key'"])->whereRaw($need_str)->get()->toArray();
            foreach($commits as $commit){
                $commit_infos[$commit->phabricator_commit_id][] = $commit->path;
            }
            foreach($commit_infos as $commit_id=>$commit_paths){
                $commit_data = DB::table('phabricator_commits')->where('id',$commit_id)->get()->toArray();
                $review_datas = DB::table('phabricator_reviews')->where('phabricator_commit_id',$commit_id)->get()->toArray();
                $data = DB::table('phabricator_commits')->where('tool_phabricator_id',$child_id)->where('svn_id',$commit_data[0]->svn_id)->where('phab_id',$commit_data[0]->phab_id)->get()->toArray();
                if(!empty($data)){
                    continue;
                }
                $phabricator_commit_id = DB::table('phabricator_commits')->insertGetId(
                    ['phab_id' => $commit_data[0]->phab_id,
                    'commit_phid' => $commit_data[0]->commit_phid,
                    'svn_id' => $commit_data[0]->svn_id,
                    'author_id' => $commit_data[0]->author_id,
                    'tool_phabricator_id' => $child_id,
                    'commit_time' => $commit_data[0]->commit_time,
                    'commit_status' => $commit_data[0]->commit_status
                    ]);
                foreach($commit_paths as $path){
                    DB::table('phabricator_paths')->insert(
                        ['phabricator_commit_id' => $phabricator_commit_id,
                        'path' => $path]);
                }
                foreach($review_datas as $review_data){
                    DB::table('phabricator_reviews')->insert(
                        ['phab_id' => $review_data->phab_id,
                        'action_phid' => $review_data->action_phid,
                        'review_id' => $review_data->review_id,
                        'author_id' => $review_data->author_id,
                        'action' => $review_data->action,
                        'comment' => $review_data->comment,
                        'action_time' => $review_data->action_time,
                        'phabricator_commit_id' => $review_data->phabricator_commit_id,
                        'tool_phabricator_id'=>$child_id,
                        'commit_time'=>$review_data->commit_time,
                        'duration'=>$review_data->duration
                        ]);
                }
            }
        }
    }

    #插入提交前修改行数
    public function set_diffline($mysql_con,$review_datas){
        foreach($review_datas as $item){
            $lineCount = $item[0]['lineCount'];
            foreach($item as $action){
                $duration_id = DB::table('phabricator_review_duration')->where('phid',$action['phid'])->value('id');
                if($duration_id){
                    DB::table('phabricator_review_duration')
                    ->where('id', $duration_id)
                    ->update(['linecount' => $lineCount]);
                } 
            } 
        } 
    }

    #更新phabricator_reviews表添加duration数据
    public function set_duration(){
        $this->time_multiple = 1;
        $update_time = $this->get_update_time($this->mpl_dbname[1]);#获取上次插入最新数据时间
        $durations = DB::table('phabricator_review_duration')->where('render_time','>',$update_time)->get();
        foreach($durations as $item){
            if(DB::table('phabricator_reviews')->where('action_phid',$item->phid)->whereNotNull('duration')->first()){#判断数据是否存在
                continue;
            }
            else{
                DB::table('phabricator_reviews')->where('action_phid',$item->phid)->update(['duration'=> $item->duration]);
            }      
        }  
    }
 
    public function pdo_mysql($mysql_con,$index,$select){
        $dsn = "mysql:host=".$mysql_con[0].";dbname=".$this->phab_dbname[$index];#获取PDO连接
        $db = new \PDO($dsn,$mysql_con[1],$mysql_con[2],array(\PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"));
        $statement = $db->prepare($select);
        $statement->execute();
        $db = null;
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
}