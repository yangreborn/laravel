<?php

namespace App\Console\Commands;

use App\Exports\PlmBugDataExport;
use Illuminate\Console\Command;

class ExportPlmData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:plm_data {conditions*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'export plm data accord to plm_search_conditions';

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
        $this->info(">>>>>> begin    @ ".date('Y-m-d H:i:s')." >>>>>>");
        $conditions = $this->argument('conditions');
        $export = new PlmBugDataExport($conditions);
        $export->store('attach/plm_export_data_' . date('Ymdhis') . '.xlsx');
        $this->info("<<<<<< end      @ ".date('Y-m-d H:i:s')." <<<<<<");
    }
}
