<?php

namespace Sorry510\Commands;

use Illuminate\Console\Command;

class Swagger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger {module? : 扫描模块，默认为整个项目}
        {--o|outpath=./public : 生成文件路径}
        {--f|filename=openapi.json : 生成文件名称}
        {--format=json : 文件类型（yaml或json）}
        {--r|routeFilename= : 路由文件名称}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成swagger文件';

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
     * @return int
     */
    public function handle()
    {
        $module = trim($this->argument('module'));
        $outpath = trim($this->option('outpath'));
        $filename = trim($this->option('filename'));
        $format = trim($this->option('format'));
        $routeFilename = trim($this->option('routeFilename'));
        echo exec("php ./vendor/zircote/swagger-php/bin/openapi ./swaggerInfo.php ./app/Http/Controllers/{$module} -o {$outpath}/{$filename} --format {$format}");
        $this->info('swagger openapi.json completed successfully');
        if (!empty($routeFilename)) {
            $this->call('route:sync', [
                'module' => $module,
                '--auto' => 1,
                '--filename' => $routeFilename,
            ]);
        }
    }
}
