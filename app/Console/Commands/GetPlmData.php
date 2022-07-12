<?php

namespace App\Console\Commands;

use App\Models\Plm;
use App\Models\ToolPlmGroup;
use App\Models\ToolPlmProduct;
use App\Models\ToolPlmProductFamily;
use App\Models\ToolPlmProject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GetPlmData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getPlmData {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get plm data';

    /**
     * Create a new command instance.
     *
     * @return void
     */

    /**
     * 更新出错psr列表
     *
     * @var array
     */
    protected $error_list = [];

    /**
     * 本地数据库psr数据
     *
     * @var array
     */
    protected $local_psr_data;

    protected $local_psr_list = [];

    protected $closed_psr_list = [];

    protected $insert_psr_list = [];

    protected $psrurl = "http://oa.kedacom.com/plmExt/ws/queryPlmDataServiceImpl?wsdl";

    protected $request_body1 = <<<xml
    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:web="http://webservice.plmext.kedacom.com/">
            <soapenv:Header/>
                <soapenv:Body>
                    <web:getAllPsrNumbers>
                        <!--Optional:-->
                    </web:getAllPsrNumbers>
            </soapenv:Body>
    </soapenv:Envelope>
xml;

    protected $request_body2 = <<<xml
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:web="http://webservice.plmext.kedacom.com/">
        <soapenv:Header/>
            <soapenv:Body>
                <web:getSoftPsrInfo>
                    <!--Optional:-->
                    <arg0>
                        %s
                    </arg0>
                </web:getSoftPsrInfo>
            </soapenv:Body>
        </soapenv:Envelope>
xml;

    protected $plmDataList = [];

    public function __construct()
    {
        parent::__construct();
        $psr_data = DB::table('plm_data')->select(['psr_number', 'status'])->get()->toArray();
        $psr_data_collection = collect($psr_data);
        $this->local_psr_list = $psr_data_collection->pluck('psr_number')->all();
        $this->closed_psr_list = $psr_data_collection->where('status', '关闭')->pluck('psr_number')->all();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('['.date('Y-m-d H:i:s').'] Plm数据导入开始 vvv');
        $this->getPsrList();
        !empty($this->error_list) && $this->error('['.date('Y-m-d H:i:s').'] --- 写入/更新数据失败编号为：'.json_encode($this->error_list));
        $this->info('['.date('Y-m-d H:i:s').'] Plm数据导入结束 ^^^');
    }
    public function getPsrList() {
        $requestBody = $this->request_body1;
        $result = $this->exeRequest($requestBody);
        $xmlparser = xml_parser_create();
        xml_parse_into_struct($xmlparser, $result, $psrvalue);
        xml_parser_free($xmlparser);
        $psrjson = json_decode($psrvalue[3]['value'], true);
        $psrlist = $psrjson["data"];
        $this->line('['.date('Y-m-d H:i:s').'] --- 获取psr编号结束 ===');

        // 移除无效数据
        $this->removeInvalidBugs($psrlist);

        // 写入新增数据
        $this->insertPsrData($psrlist);

        // 写入更新数据
        $this->updatePsrData($psrlist);
    }

    /**
     * 写入新增bug数据
     * @param $server_psr_list array plm服务器返回psr列表
     */
    public function insertPsrData($server_psr_list){
        $this->insert_psr_list = array_diff($server_psr_list, $this->local_psr_list);
        $this->line('[<<--新增-->>]数据条目数为:'.sizeof($this->insert_psr_list));

        sizeof($this->insert_psr_list) > 0
        &&
        $this->getFullList($this->insert_psr_list, true);
    }

    /**
     * 写入更新bug数据
     * 非周末时间只更新状态为未关闭状态的bug数据(若服务器端将bug状态由已关闭改为打开状态,将会造成数据不准确)
     * 周末更新全部数据
     * @param $server_psr_list array plm服务器返回psr列表
     */
    public function updatePsrData($server_psr_list){
        $update_psr_list = !in_array(Carbon::now()->dayOfWeek, array(0, 6)) && !$this->option('all')
            ? array_diff($server_psr_list, $this->closed_psr_list, $this->insert_psr_list)
            : array_diff($server_psr_list, $this->insert_psr_list);
        $this->line('[<<--更新-->>]数据条目数为:'.sizeof($update_psr_list));

        sizeof($update_psr_list) > 0
        &&
        $this->getFullList($update_psr_list, false);
    }

    /**
     * 删除无效bug数据(软删除，计算时须排除已删除数据)
     * @param array $server_psr_list plm服务器返回psr列表
     */
    public function removeInvalidBugs($server_psr_list){
        $deleted_psr_list = DB::table('plm_data')->whereNotNull('deleted_at')->pluck('psr_number')->all();
        $invalid_psr_list = array_diff($this->local_psr_list, $server_psr_list, $deleted_psr_list);
        $this->line('[<<--删除-->>]数据条目数为:'.sizeof($invalid_psr_list));

        $now = Carbon::now();

        sizeof($invalid_psr_list) > 0
        &&
        collect($invalid_psr_list)->chunk(50)->each(function ($item) use ($now) {
            // 减少手动拉取数据时对已删除数据的干扰
            if ($now->hour >3) {
                Plm::query()->whereIn('psr_number', $item)->delete();
            } else {
                Plm::query()->whereIn('psr_number', $item)->update([
                    'deleted_at' => $now->copy()->subDay()->endOfDay()->toDateTimeString()
                ]);
            }
        });
    }

    /**
     * 从plm服务器获取数据并更新至本地
     * @param $psrlist array psr编号列表
     * @param $is_insert bool 是否新增数据
     */
    public function getFullList($psrlist, $is_insert) {
        $chunk_size = sizeof($psrlist) > 50 ? 50 : ceil(sizeof($psrlist)/2);
        $temp = array_chunk($psrlist, $chunk_size);
        $bar = $this->output->createProgressBar(count($psrlist));
        foreach($temp as $key => $somePsrs) {
            $bar->advance($chunk_size);
            $fetch_start = microtime(true);
            $psrStr = json_encode($somePsrs);
            $requestBody = sprintf($this->request_body2, $psrStr);
            $result = $this->exeRequest($requestBody);
            $fetch_end = microtime(true);
            $result = utf8_encode($result);
            $parser = xml_parser_create();
            xml_parse_into_struct($parser, $result, $values, $index);
            xml_parser_free($parser);
            if(array_key_exists(3, $values)) {
                $dataArray = $values[3];
                if(array_key_exists('value', $dataArray)) {
                    $dataString = $dataArray['value'];
                    $data = json_decode($dataString, true);
                    if($data["success"] == true) {
                        $data = $data["data"];
                        $write_start = microtime(true);
                        $this->writeToDataBase($data);
                        $write_end = microtime(true);
                        $this->line(sprintf('：获取耗时（%4.2f），%s耗时（%4.2f），共耗时（%4.2f）', $fetch_end - $fetch_start, $is_insert ? '写入' : '更新', $write_end - $write_start, $write_end - $fetch_start));
                        continue;
                    }
                }
            }
            if (sizeof($somePsrs) > 1){
                $this->getFullList($somePsrs, $is_insert);
            } else {
                $this->error_list[] = implode('', $somePsrs);
                $this->error('['.date('Y-m-d H:i:s').'] --- '.($is_insert ? '写入' : '更新').'编号为【'.implode('', $somePsrs).'】数据失败：'.json_encode($values));
            }
        }
        $bar->finish();
    }
    public function exeRequest($requestBody) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->psrurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    public function writeToDataBase($plmDataList) {
        $plm_data_list = $this->formatData($plmDataList);
        collect($plm_data_list)->each(function ($item){
            Plm::withTrashed()->updateOrCreate(
                ['psr_number' => $item['psr_number']],
                $item
            );
            // 将当前审阅者添加至用户表
            if (!empty($item['user_emails'])){
                $arr = explode(',', $item['user_emails']);
                foreach ($arr as $value){
                    if (strpos($value, '@kedacom.com') !== false){
                        User::getOrCreateUser($value);
                    }
                }
            }
        });
    }

    public function formatData($plm_data_list) {
        return array_map(function ($item){
            $item['createTime'] = isset($item['createTime']) && !empty($item['createTime'])
                ? date('Y-m-d H:i:s', strtotime($item['createTime']) + 8*60*60)
                : null;
            $item['auditTime'] = isset($item['auditTime']) && !empty($item['auditTime'])
                ? date('Y-m-d H:i:s', strtotime($item['auditTime']) + 8*60*60)
                : null;
            $item['distributionTime'] = isset($item['distributionTime']) && !empty($item['distributionTime'])
                ? date('Y-m-d H:i:s', strtotime($item['distributionTime']) + 8*60*60)
                : null;
            $item['solveTime'] = isset($item['solveTime']) && !empty($item['solveTime'])
                ? date('Y-m-d H:i:s', strtotime($item['solveTime']) + 8*60*60)
                : null;
            $item['proSolveDate'] = isset($item['proSolveDate']) && !empty($item['proSolveDate'])
                ? date('Y-m-d H:i:s', strtotime($item['proSolveDate']) + 8*60*60)
                : null;
            $item['closeDate'] = isset($item['closeDate']) && !empty($item['closeDate'])
                ? date('Y-m-d H:i:s', strtotime($item['closeDate']) + 8*60*60)
                : null;
            $item['delayTime'] = isset($item['delayTime']) && !empty($item['delayTime'])
                ? date('Y-m-d H:i:s', strtotime($item['delayTime']) + 8*60*60)
                : null;
            $project_id = !isset($item['subject']) ? null : ToolPlmProject::firstOrCreate(['name' => $item['subject']])->getAttributeValue('id');
            $product_id = !isset($item['productName']) ? null : ToolPlmProduct::firstOrCreate(['name' => $item['productName']])->getAttributeValue('id');
            $group_id = !isset($item['groupName']) ? null : ToolPlmGroup::firstOrCreate(['name' => $item['groupName']])->getAttributeValue('id');
            $product_family_id = !isset($item['productfamily']) ? null : ToolPlmProductFamily::firstOrCreate(['name' => $item['productfamily']])->getAttributeValue('id');
            return [
                'product_family' => $item['productfamily'] ?? '',
                'group' => $item['groupName'] ?? '',
                'subject' => $item['subject'] ?? '',
                'creator' => $item['creator'] ?? '',
                'creator_mail' => $item['creatorEmail'] ?? '',
                'reviewer' => $item['reviewer'] ?? '',
                'reReviewer' => $item['reReviewer'] ?? '',
                'bug_explain' => $item['bugExplain'] ?? '',
                'fre_occurrence' => $item['freOccurrence'] ?? '',
                'inside_version' => $item['insideVersion'] ?? '',
                'version' => $item['version'] ?? '',
                'performance' => $item['performance'] ?? '',
                'product_name' => $item['productName'] ?? '',
                'reject' => intval($item['rejectNumber'] ?? 0),
                'seriousness' => $item['seriousness'] ?? '',
                'solution' => $item['solution'] ?? '',
                'solve_status' => $item['solveStatus'] ?? '',
                'description' => $item['description'] ?? '',
                'status' => $item['status'] ?? '未分配',
                'user_emails' => $item['userEmails'] ?? '',
                'create_time' => $item['createTime'], //
                'audit_time' => $item['auditTime'], //
                'distribution_time' => $item['distributionTime'], //
                'close_date' => $item['closeDate'], //
                'pro_solve_date' => $item['proSolveDate'],
                'solve_time' => $item['solveTime'], //
                'project_id' => $project_id ?: null,
                'product_id' => $product_id ?: null ,
                'group_id' => $group_id ?: null,
                'product_family_id' => $product_family_id ?: null,
                'psr_number' => $item['defectNumber'],
                'deleted_at' => null,
                'delay_at' => $item['delayTime'],
            ];
        }, $plm_data_list);
    }
}
