<?php

namespace Imanghafoori\LaravelMicroscope\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PrettyPrintRoutes extends Command
{
    protected $signature = 'pp:routes';

    protected $description = 'Pretty print routes';

    public function handle()
    {
        $calls = config('microscope.write.routes', []);

        foreach ($calls as $call) {
            foreach ($call['args'] as $val) {
                \is_array($val) && ($val = \implode('@', $val));
                $val = \trim($val ?? '');

                $route = $this->deduceRoute($val);
                /**
                 * @var  \Illuminate\Routing\Route  $route
                 */
                if ($route) {
                    ($call['function'] == 'microscope_write_route') && $this->writeIt($route, $call['file']);
                    ($call['function'] == 'microscope_pretty_print_route') && $this->printIt($route);
                } else {
                    $this->info('Route name not found.');
                }
            }
        }
    }

    private function writeIt($route, $filename)
    {
        try {
            $middlewares = $this->getMiddlewares($route);

            $methods = $route->methods();
            ($methods == ['GET', 'HEAD']) && $methods = ['GET'];

            $action = $this->getAction($route->getActionName());

            if (\count($methods) == 1) {
                $definition = PHP_EOL . $this->getMovableRoute($route, $methods, $action, $middlewares);

                file_put_contents($filename, $definition, FILE_APPEND);
            }
        } catch (Exception $e) {
            $this->handleRouteProblem($e);

            return;
        }
    }

    private function deduceRoute($value)
    {
        if (Str::containsAll($value, ['@', '\\'])) {
            $route = app('routes')->getByAction($value);
        } else {
            $route = app('routes')->getByName($value);
        }

        return $route;
    }

    private function printIt($route)
    {
        try {
            $middlewares = $this->getMiddlewares($route);
            $this->prettyPrintInConsole($route, $middlewares);
        } catch (Exception $e) {
            $this->handleRouteProblem($e);

            return;
        }
    }

    private function getMovableRoute($route, $methods, $action, $middlewareSection)
    {
        if ($action == '\Illuminate\Routing\ViewController::class') {
            $method = 'view';
            $uriAction = "('/" . $route->uri() . "', '" . $route->defaults['view'] . "')";
            $defaults = '';
        } else {
            $method = strtolower($methods[0]);
            $uriAction = "('/" . $route->uri() . "', " . $action . ')';
            $defaults = $this->getDefaults($route->defaults);
        }

        $nameSection = ($route->getName() ? ("->name('" . $route->getName() . "')") : '');

        return 'Route::' . $method . $uriAction . PHP_EOL . $middlewareSection . $nameSection . $defaults . ';';
    }

    private function getAction($action)
    {
        $action = Str::start($action, '\\');
        if (!Str::contains($action, ['@'])) {
            return $action . '::class';
        }

        $action = \explode('@', $action);

        return '[' . $action[0] . '::class' . ", '" . $action[1] . "']";
    }

    private function getMiddlewares($route)
    {
        $middlewares = $route->gatherMiddleware();
        $middlewares && $middlewares = "'" . \implode("', '", $route->gatherMiddleware()) . "'";

        return $middlewares ? '->middleware([' . $middlewares . '])' : '';
    }

    private function handleRouteProblem($e)
    {
        $this->info('The route has some problem.');
        $this->info($e->getMessage());
        $this->info($e->getFile());
    }

    private function prettyPrintInConsole($route, $middlewares)
    {
        $this->getOutput()->writeln('---------------------------------------------------');
        $this->info(' name:             ' . ($route->getName() ? ($route->getName()) : ''));
        $this->info(' uri:              ' . \implode(', ', $route->methods()) . "   '/" . $route->uri() . "'  ");
        $this->info(' middlewares:      ' . $middlewares);
        $this->info(' action:           ' . $route->getActionName());
    }

    private function getDefaults($values)
    {
        $defaults = '';
        
        foreach ($values as $key => $val) {
            $defaults .= "\n" . '->defaults(' . var_export($key, true) . ', ' . var_export($val, true) . ')';
        }

        return $defaults;
    }
}
