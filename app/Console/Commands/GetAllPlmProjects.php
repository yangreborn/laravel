<?php

namespace App\Console\Commands;

use App\Models\PlmProject;
use Illuminate\Console\Command;
use SoapClient;

class GetAllPlmProjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plm:get_all_projects';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取plm上的所有项目基本信息';

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
        //
        $this->info('start getting all plm projects ==>');
        ini_set('default_socket_timeout', 360);
        $soap = new SoapClient(env('PLM_API'));
        $result = $soap->getAllProject([])->return;
        $result = json_decode($result, true);
        $bar = $this->output->createProgressBar(sizeof($result));
        foreach($result as $item) {
            $bar->advance();
            PlmProject::query()->updateOrCreate([
                'uid' => $item['number']
            ],[
                'product_line_full' => $item['productLine'],
                'name' => $item['programName'],
                'pm' => $item['userName'],
                'pm_pinyin' => $item['account'],
                'pm_pos' => $item['pos'],
                'status' => 0,

            ]);
        }
        $bar->finish();
        $this->line("\n" . '------------------------------------------');
        $result = $soap->getAllProjectForReport([])->return;
        $result = json_decode($result, true);
        $bar = $this->output->createProgressBar(sizeof($result));
        foreach($result as $item) {
            $bar->advance();
            $product_line = key_exists('productLine', $item) && !empty($item['productLine']) ? $item['productLine'] : '';
            $name = key_exists('programName', $item) && !empty($item['programName']) ? $item['programName'] : '';
            $pm = key_exists('userName', $item) && !empty($item['userName']) ? $item['userName'] : '';
            $pm_pinyin = key_exists('account', $item) && !empty($item['account']) ? \Illuminate\Support\Arr::first(explode('@', $item['account'])) : '';
            $pm_pos = key_exists('pos', $item) && !empty($item['pos']) ? $item['pos'] : '';

            PlmProject::query()->updateOrCreate([
                'uid' => $item['number']
            ],[
                'product_line' => $product_line,
                'name' => $name,
                'pm' => $pm,
                'pm_pinyin' => $pm_pinyin,
                'pm_pos' => $pm_pos,
                'status' => 0,

            ]);

            $product_line_full = PlmProject::query()
                ->where('product_line', '<>', '')
                ->where('product_line', $product_line)
                ->where('product_line_full', '<>', '')
                ->value('product_line_full');
            PlmProject::query()->updateOrCreate([
                'uid' => $item['number']
            ],[
                'product_line_full' => $product_line_full ?? '',
            ]);
        }
        $bar->finish();
        $this->info("\n" . '<== finish to get all plm projects' . "\n");
    }
}
