<?php

namespace App\Console\Commands;

use App\Models\Pclint;
use App\Models\TscanCode;
use App\Models\Findbugs;
use App\Models\Eslint;
use App\Models\Cloc;
use App\Models\VersionFlowTool;
use Illuminate\Console\Command;

class SyncJenkinsJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:jenkins_job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jenkins Job信息校对';

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
     * @return void
     */
    public function handle()
    {
        $this->info('[' . date('Y-m-d H:i:s') . ']' . ' |==Jenkins Job信息校对开始');
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--Jenkins Job信息汇总');
        $content = $this->getJenkinsJobList();
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--Pclint Job信息同步');
        $this->compareDatabaseList('pclint', $content);
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--TscanCode Job信息同步');
        $this->compareDatabaseList('tscancode', $content);
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--Findbugs Job信息同步');
        $this->compareDatabaseList('findbugs', $content);
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--Eslint Job信息同步');
        $this->compareDatabaseList('eslint', $content);
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--Cloc Job信息同步');
        $this->compareDatabaseList('cloc', $content);
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    Jenkins Job信息校对完成==|');

    }

    private function getJenkinsJobList(){
        $content = [];
        $index = config('api.jenkins_api');
        foreach ($index['job_list'] as $item){
            $client = new \GuzzleHttp\Client([
                'base_uri' => $item,
                'headers' => [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ],
            ]);
            $response = $client->request('GET');
            $jobs = json_decode($response->getBody()->getContents(), true);
            if (isset($jobs) && is_array($jobs)) {
                foreach ($jobs['jobs'] as $job){
                    if (key_exists($job['name'], $content)){
                        if ($content[$job['name']] === 'disabled'){
                            if ($job['color'] !== 'disabled'){
                                $content[$job['name']] = $job['color'];
                            }else {
                                continue;
                            }
                        }else {
                            if ($job['color'] === 'disabled'){
                                continue;
                            }
                        }
                    }else {
                        $content[$job['name']] = $job['color'];
                    }
                }
            }
        }
        return $content;
    }


    private function compareDatabaseList($type, $content){
        switch ($type){
            case 'pclint':
                $tool_pclints = Pclint::query()->select('job_name', 'id')->get()->each(function($name){
                    $name->makeHidden(['last_updated_time']);
                })->toArray();
                foreach ($tool_pclints as $tool_pclint){
                    if (array_key_exists($tool_pclint['job_name'], $content)){
                        if ($content[$tool_pclint['job_name']] === 'disabled'){
                            Pclint::query()->where('job_name', $tool_pclint['job_name'])->delete();
                            VersionFlowTool::query()->where('tool_id', $tool_pclint['id'])->where('tool_type', 'pclint')->delete();
                        }
                    }else{
                        Pclint::query()->where('job_name', $tool_pclint['job_name'])->delete();
                        VersionFlowTool::query()->where('tool_id', $tool_pclint['id'])->where('tool_type', 'pclint')->delete();
                    }
                }
                break;
            case 'tscancode':
                $tool_tscancodes = TscanCode::query()->select('job_name', 'id')->get()->each(function($name){
                    $name->makeHidden(['last_updated_time']);
                })->toArray();
                foreach ($tool_tscancodes as $tool_tscancode){
                    if (array_key_exists($tool_tscancode['job_name'], $content)){
                        if ($content[$tool_tscancode['job_name']] === 'disabled'){
                            TscanCode::query()->where('job_name', $tool_tscancode['job_name'])->delete();
                            VersionFlowTool::query()->where('tool_id', $tool_tscancode['id'])->where('tool_type', 'tscancode')->delete();
                        }
                    }else{
                        TscanCode::query()->where('job_name', $tool_tscancode['job_name'])->delete();
                        VersionFlowTool::query()->where('tool_id', $tool_tscancode['id'])->where('tool_type', 'tscancode')->delete();
                    }
                }
                break;
            case 'findbugs':
                $tool_findbugs = Findbugs::query()->select('job_name', 'id')->get()->each(function($name){
                    $name->makeHidden(['last_updated_time']);
                })->toArray();
                foreach ($tool_findbugs as $tool_findbug){
                    if (array_key_exists($tool_findbug['job_name'], $content)){
                        if ($content[$tool_findbug['job_name']] === 'disabled'){
                            Findbugs::query()->where('job_name', $tool_findbug['job_name'])->delete();
                            VersionFlowTool::query()->where('tool_id', $tool_findbug['id'])->where('tool_type', 'findbugs')->delete();
                        }
                    }else{
                        Findbugs::query()->where('job_name', $tool_findbug['job_name'])->delete();
                        VersionFlowTool::query()->where('tool_id', $tool_findbug['id'])->where('tool_type', 'findbugs')->delete();
                    }
                }
                break;
            case 'eslint':
                $tool_eslints = Eslint::query()->select('job_name', 'id')->get()->each(function($name){
                    $name->makeHidden(['last_updated_time']);
                })->toArray();
                foreach ($tool_eslints as $tool_eslint){
                    if (array_key_exists($tool_eslint['job_name'], $content)){
                        if ($content[$tool_eslint['job_name']] === 'disabled'){
                            Eslint::query()->where('job_name', $tool_eslint['job_name'])->delete();
                            VersionFlowTool::query()->where('tool_id', $tool_eslint['id'])->where('tool_type', 'eslint')->delete();
                        }
                    }else{
                        Eslint::query()->where('job_name', $tool_eslint['job_name'])->delete();
                        VersionFlowTool::query()->where('tool_id', $tool_eslint['id'])->where('tool_type', 'eslint')->delete();
                    }
                }
                break;
            case 'cloc':
                $tool_clocs = Cloc::query()->select('job_name', 'id')->get()->each(function($name){
                    $name->makeHidden(['last_updated_time']);
                })->toArray();
                foreach ($tool_clocs as $tool_cloc){
                    if (array_key_exists($tool_cloc['job_name'], $content)){
                        if ($content[$tool_cloc['job_name']] === 'disabled'){
                            Cloc::query()->where('job_name', $tool_cloc['job_name'])->delete();
                            VersionFlowTool::query()->where('tool_id', $tool_cloc['id'])->where('tool_type', 'cloc')->delete();
                        }
                    }else{
                        Cloc::query()->where('job_name', $tool_cloc['job_name'])->delete();
                        VersionFlowTool::query()->where('tool_id', $tool_cloc['id'])->where('tool_type', 'cloc')->delete();
                    }
                }
                break;
        }
    }
}