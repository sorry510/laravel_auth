<?php

namespace Sorry510\Commands;

use function DeepCopy\deep_copy;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpParser\Builder\Method;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class RouteAst extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:sync {module : 模块名称}
        {--f|filename=v1.php : 路由文件名称}
        {--auto|auto=0 : 是否自动确认}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '根据 openapi.json 对路由文件同步更新';

    protected $stub;

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
        $this->module = $module = trim($this->argument('module'));
        $filename = trim($this->option('filename'));
        $auto = trim($this->option('auto'));
        if (!$auto) {
            if (!$this->confirm('请确保 openapi.json 内容与所选 module 一致, 是否继续? [y|n]')) {
                return;
            }
        }

        $this->info('route sync start:' . date('Y-m-d H:i:s'));
        $filePath = base_path("routes/{$filename}");
        $asts = $this->parseFile($filePath);
        $hasRouter = false; // 检查之前是否含有重复的路由分组
        foreach ($asts as $ast) {
            // if ($key != 4) {
            //     continue;
            // }
            if ($ast instanceof \PhpParser\Node\Stmt\Expression) { // Route
                if ($this->isMethodCall($ast, 'expr')) { // 判断是方法(由外到内解析)
                    $data = []; // 路由信息s
                    /**
                     * @var \PhpParser\Node\Expr\MethodCall
                     */
                    $method = $ast->expr;
                    $data[] = $this->getOriginRouteGroup($method);
                    while ($this->isMethodCall($method, 'var')) {
                        $method = $method->var;
                        $data[] = $this->getOriginRouteGroup($method);
                    }
                    if ($this->isStaticCall($method, 'var')) {
                        $data[] = $this->getOriginRouteGroup($method->var);
                    }
                    [$result, $value] = $this->checkRouter($data, $module);
                    if ($result) {
                        $hasRouter = $result;
                        $this->addRouters($value);
                        break;
                    }
                }
            }
        }
        if (!$hasRouter) {
            $asts[] = $this->setRouteGroup();
        }

        $prettyPrinter = new Standard;
        $newFile = $prettyPrinter->prettyPrintFile($asts);
        file_put_contents($filePath, $newFile);
        $this->info('route sync end:' . date('Y-m-d H:i:s'));
        return 1;
    }

    /**
     * 设置新的路由分组
     * @Author sorry510 491559675@qq.com
     * @DateTime 2021-03-13
     *
     * @return void
     */
    protected function setRouteGroup()
    {
        [$groupStmt, $_] = $this->getRouteStub(); // 获取分组和具体路由信息
        /**
         * expr group
         * var namespace
         * var prefix
         * var middleware
         * @Author sorry510 491559675@qq.com
         * @DateTime 2021-03-13
         *
         * @return Expression
         */
        $groupStmt->expr->var->args[0]->value->value = ucfirst($this->module); // 设置 namespace
        $groupStmt->expr->var->var->args[0]->value->value = Str::kebab($this->module); // 设置 prefix
        $groupStmt->expr->args[0]->value = $this->addRouters($groupStmt->expr->args[0]->value);
        return $groupStmt;
    }

    /**
     * 追加路由
     * @Author sorry510 491559675@qq.com
     * @DateTime 2021-03-13
     *
     * @param Closure $closure
     * @return void
     */
    protected function addRouters(Closure $closure)
    {
        [$_, $routeStmt] = $this->getRouteStub();
        $routeList = collect($this->getRouteList());

        // 智能合并之前的路由
        // foreach ($closure->stmts as $stmt) {
        //     $routeList = $routeList->filter(fn($item) => $item['method'] !== $stmt->expr->var->var->name->name && $item['uri'] && $stmt->expr->var->var->args[0]->value->value);
        // }
        $stmts = [];
        foreach ($routeList as $key => $routeInfo) {
            // $route = clone $routeStmt; // 浅拷贝
            $route = deep_copy($routeStmt);
            $route->expr->args[0]->value->value = $routeInfo['desc'];
            $route->expr->var->args[0]->value->value = $routeInfo['permission'];
            $route->expr->var->var->name->name = $routeInfo['method'];
            $route->expr->var->var->args[0]->value->value = $routeInfo['uri'];
            $route->expr->var->var->args[1]->value->value = $routeInfo['action'];
            $stmts[] = $route;
            // $closure->stmts[] = $route;
        }
        $closure->stmts = $stmts;
        return $closure;
    }

    /**
     * 获取路由的桩信息
     * @Author sorry510 491559675@qq.com
     * @DateTime 2021-03-13
     *
     * @return void
     */
    protected function getRouteStub()
    {
        if (!$this->stub) {
            $routeStub = __DIR__ . '/stub/route.stub';
            $this->stub = $this->parseFile($routeStub);
        }
        return $this->stub;
    }

    /**
     * 解析路由文件
     * @Author sorry510 491559675@qq.com
     * @DateTime 2021-03-13
     *
     * @param [type] $path
     * @return array
     */
    protected function parseFile($path)
    {
        $code = file_get_contents($path);
        $parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        try {
            $ast = $parser->parse($code);
        } catch (\Error $error) {
            echo "Parse error: {$error->getMessage()}\n";
            die;
        }

        return $ast;
    }

    /**
     * 获取新的路由信息
     * @Author sorry510 491559675@qq.com
     * @DateTime 2021-03-13
     *
     * @return array
     */
    protected function getRouteList()
    {

        if (!file_exists(public_path('openapi.json'))) {
            $this->error('openapi.json not find');
            die;
        }
        $json_string = file_get_contents(public_path('openapi.json'));
        $data = json_decode($json_string, true);
        $paths = $data["paths"];
        $result = [];
        foreach ($paths as $key => $val) {
            $key = explode('/', (string) $key, 2);
            foreach ($val as $key1 => $val1) {
                $method = $key1;
                $res = [
                    "method" => "",
                    'uri' => '',
                    'action' => '',
                    'permission' => '',
                    'desc' => '',
                ];
                $operationId = $val1['operationId'];
                $operationId = str_replace("::", "@", $operationId);
                $array = explode('\\', $operationId);
                $action = $array[count($array) - 1];
                $res['method'] = $method;
                $res['uri'] = $key[1];
                $res['action'] = $action;
                if (array_key_exists('permission', $val1)) {
                    $permission = $val1['permission'];
                    $res['permission'] = $permission;
                }
                if (array_key_exists('summary', $val1)) {
                    $desc = $val1["summary"];
                    $res['desc'] = $desc;
                }
                array_push($result, $res);
            }
        }
        return $result;
    }

    /**
     * 获取路由分组信息
     * @Author sorry510 491559675@qq.com
     * @DateTime 2021-03-13
     *
     * @param MethodCall|StaticCall $method
     * @return array
     */
    protected function getOriginRouteGroup($method): array
    {
        $data = ['name' => '', 'value' => null];
        $data['name'] = $method->name->name;
        if (count($method->args)) {
            /**
             * @var Closure|String_
             */
            $argsValue = $method->args[0]->value;
            if ($argsValue instanceof Closure) {
                $data['value'] = $argsValue; // 闭包的路由
            } else if ($argsValue instanceof String_) {
                $data['value'] = $argsValue->value;
            }
        }
        return $data;
    }

    /**
     * 检查是否含有当前路由信息
     * @Author sorry510 491559675@qq.com
     * @DateTime 2021-03-13
     *
     * @return void
     */
    protected function checkRouter(array $data, $module)
    {
        $value = null;
        $hasPrefix = false;
        $hasNamespace = false;
        foreach ($data as $row) {
            if ($row['name'] === 'group') {
                $value = $row['value'];
            }
            if ($row['name'] === 'prefix') {
                $hasPrefix = $row['value'] === Str::kebab($module);
            }
            if ($row['name'] === 'namespace') {
                $hasNamespace = $row['value'] === ucfirst($module);
            }
        }
        if ($hasPrefix && $hasNamespace) {
            return [true, $value];
        }
        return [false, $value];
    }

    protected function isUse_($ast, $prop)
    {
        return isset($ast->{$prop}) ? $ast->{$prop} instanceof Use_ : false;
    }

    protected function isExpression($ast, $prop)
    {
        return isset($ast->{$prop}) ? $ast->{$prop} instanceof Expression : false;
    }

    protected function isMethodCall($ast, $prop)
    {
        return isset($ast->{$prop}) ? $ast->{$prop} instanceof MethodCall : false;
    }

    protected function isStaticCall($ast, $prop)
    {
        return isset($ast->{$prop}) ? $ast->{$prop} instanceof StaticCall : false;
    }

    protected function isNodeName($ast, $prop)
    {
        return isset($ast->{$prop}) ? $ast->{$prop} instanceof Name : false;
    }

    protected function isIdentifier($ast, $prop)
    {
        return isset($ast->{$prop}) ? $ast->{$prop} instanceof Identifier : false;
    }

    protected function isString_($ast, $prop)
    {
        return isset($ast->{$prop}) ? $ast->{$prop} instanceof String_ : false;
    }
}
