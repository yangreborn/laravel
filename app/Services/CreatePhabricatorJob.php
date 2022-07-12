<?php
namespace App\Services;

use GuzzleHttp\Client;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CreatePhabricatorJob {
    private static $server_array = [
        "172.16.0.123"=>["api_token"=>"cli-tgdq7t7s52ltijv2g3ejuog3jnkb","svn_phid"=>"PHID-CDTL-l3yxmiti4vroh4cbtdf7","git_phid"=>"",
        "department_ids"=>[65,61,67,63,21,13,37]],
        "172.16.0.124"=>["api_token"=>"cli-rmgu2ehhvx7zffiei66jya2mxgoy","svn_phid"=>"PHID-CDTL-lohc6gkfi74coz5cwsdf","git_phid"=>"PHID-CDTL-rmwzs7hpmameurcyqybm",
        "department_ids"=>[9,8,17,18,20,33,48,47,39,36,40,152,153,46,42,41,43,151,22,154]],
        "172.16.0.129"=>["api_token"=>"cli-smcl4fhg7eho2j4qeo6axoqwj2ca","svn_phid"=>"PHID-CDTL-fghp55ehkzhd7owy7zoq","git_phid"=>"",
        "department_ids"=>[4,12]],
        "172.16.0.131"=>["api_token"=>"cli-ei7rnevj65kvf75jhs7n7rncc5vo","svn_phid"=>"PHID-CDTL-szofw55woyhjsg52squ7","git_phid"=>"",
        "department_ids"=>[15]],
        "172.16.0.132"=>["api_token"=>"cli-2ot4os3ckvubcxbiap3w5nf64ouy","svn_phid"=>"PHID-CDTL-2riixsgpf4u4k7vxzewr","git_phid"=>"",
        "department_ids"=>[25,2,28,27,31]],
    ];


    public static function handleData($data){
        $root = '';
        $branch = '';
        $ip = '';
        foreach(self::$server_array as $key=>$item){
            if(in_array($data['department_id'],$item['department_ids'])){
                $ip = $key;
                break;
            }
        }
        if(empty($ip)){
            return;
        }
        if($data['tool_type'] === 'svn'){
            $matches = self::check_url($data['flow'][0]);
            if($matches){
                $root = $matches[1];
                $branch = $matches[2].'/'.$matches[3];
                $credential = self::$server_array[$ip]['svn_phid'];
            }
        }
        else{
            $root = $data['flow'][0];
            $credential = self::$server_array[$ip]['git_phid'];
        }
        $data['callsign'] = self::createCallsign($data['name'],$ip);
        // $user = Auth::guard('api')->user();
        // $name = User::query()->where('id', $user['id'])->value('name');

        $repo_phid = self::repository_edit($data,$branch,$ip);
        self::uri_edit($data,$repo_phid,$root,$credential,$ip);
    }

    public static function repository_edit($data,$branch,$ip){
        
        $base_url = "http://".$ip."/api/diffusion.repository.edit";
        $query = [
            'api.token' => self::$server_array[$ip]["api_token"],
            'transactions'=>[
                ["type"=>"name","value"=>$data['name']],
                ["type"=>"vcs","value"=>$data['tool_type']],
                ["type"=>"callsign","value"=>$data['callsign']],
                ["type"=>"description","value"=>$data['description']],
                ["type"=>"encoding","value"=>"GB2312"],
                ["type"=>"importOnly","value"=>$branch],
                ["type"=>"status","value"=>"active"],
            ],
            // 'objectIdentifier'=>180
        ];
        $res = self::api_request($base_url,$query);
        return $res["result"]["object"]["phid"];
    }

    public static function uri_edit($data,$repo_phid,$root,$credential,$ip){
        $base_url = "http://".$ip."/api/diffusion.uri.edit";
        $query = [
            'api.token' => self::$server_array[$ip]["api_token"],
            'transactions'=>[
                ["type"=>"repository","value"=>$repo_phid],
                ["type"=>"uri","value"=>$root],
                ["type"=>"io","value"=>"observe"],
                ["type"=>"credential","value"=>$credential],
            ],
            'objectIdentifier'=>''
        ];
        $res = self::api_request($base_url,$query);
        // var_dump($res);
    }

    public static function api_request($base_url,$query){
        $client_url = new \GuzzleHttp\Client([
            'base_uri' => $base_url,
        ]);
        $response = $client_url->request('GET','', [
            'query' => $query
        ]);
        $res = $response->getBody()->getContents();
        $content =  json_decode($res,true);
        wlog('res',$content);
        return $content;
    }

    public static function check_url($url){
        if(preg_match("/(.+)(branch|trunk)\/(.*)/",$url,$matches)){
            return $matches;
        }
        else{
            return False;
        }
    }

    public static function createCallsign($name,$ip){
        $letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $callsign = "";
        for($i=0;$i<strlen($name);$i++){//遍历字符串追加给数组
            if(preg_match("/[a-zA-Z]/",$name[$i])){
                $callsign = $callsign.$name[$i];
            }
        }
        $str_len = strlen($callsign);
        if($str_len>=32){
            $callsign = substr($callsign,0,31);
        }
        else{
            $callsign = substr($callsign,0,$str_len);
        }
        $callsign = strtoupper($callsign);
        $dsn = "mysql:host=".$ip.";dbname=phabricator_repository";#获取PDO连接
        $db = new \PDO($dsn,'phab','123456',array(\PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"));
        $select = "select count(*) as num from repository where callsign like \"$callsign%\"";
        $statement = $db->prepare($select);
        $statement->execute();
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        if($result[0]['num']){
            $callsign = $callsign.$letters[$result[0]['num']];
        }
        return $callsign;
    }
}