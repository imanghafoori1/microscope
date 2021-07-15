<?php

namespace Imanghafoori\LaravelMicroscope\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use Imanghafoori\LaravelMicroscope\Analyzers\ComposerJson;
use Imanghafoori\LaravelMicroscope\CheckNamespaces;
use Imanghafoori\LaravelMicroscope\ErrorReporters\ErrorPrinter;
use Imanghafoori\LaravelMicroscope\FileReaders\Paths;
use Imanghafoori\LaravelMicroscope\LaravelPaths\FilePath;
use Imanghafoori\LaravelMicroscope\LaravelPaths\LaravelPaths;
use Imanghafoori\LaravelMicroscope\SpyClasses\RoutePaths;

class CheckPsr4 extends Command
{
    protected $signature = 'check:psr4 {--d|detailed : Show files being checked} {--f|force} {--s|nofix}';

    protected $description = 'Checks the validity of namespaces';

    public function handle(ErrorPrinter $errorPrinter)
    {
        $this->line('');
        $this->info('Started checking PSR-4 namespaces...');
        $time = microtime(true);

        $errorPrinter->printer = $this->output;

        $autoload = ComposerJson::readAutoload();
        $this->checkNamespaces($autoload);

        $this->option('nofix') && config(['microscope.no_fix' => true]);

        if (CheckNamespaces::$changedNamespaces && ! config('microscope.no_fix')) {
            $olds = \array_keys(CheckNamespaces::$changedNamespaces);
            $news = \array_values(CheckNamespaces::$changedNamespaces);

            $this->fixReferences($autoload, $olds, $news);
        }

        if (Str::startsWith(request()->server('argv')[1] ?? '', 'check:psr4')) {
            $this->reportResult($autoload);
            $this->printErrorsCount($errorPrinter, $time);
        } else {
            $this->getOutput()->writeln(' - '.CheckNamespaces::$checkedNamespaces.' namespaces were checked.');
        }

        $this->composerDumpIfNeeded($errorPrinter);
    }

    private function composerDumpIfNeeded(ErrorPrinter $errorPrinter)
    {
        if ($c = $errorPrinter->getCount('badNamespace')) {
            $this->output->write('- '.$c.' Namespace'.($c > 1 ? 's' : '').' Fixed, Running: "composer dump"');
            app(Composer::class)->dumpAutoloads();
            $this->info("\n".'finished: "composer dump"');
        }
    }

    private function str_contains($haystack, $needles)
    {
        foreach ($needles as $needle) {
            if (mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function fixRefs($_path, $olds, $news)
    {
        $lines = file($_path);
        $changed = [];
        foreach ($lines as $lineIndex => $lineContent) {
            if ($this->str_contains($lineContent, $olds)) {
                $count = 0;
                $lines[$lineIndex] = \str_replace($this->variants($olds), $this->variants($news), $lineContent, $count);
                if ($count === 0) {
                    app(ErrorPrinter::class)->printLink($_path, $lineIndex + 1);
                    $this->info($lineContent);
                    if ($this->confirm('Do you want to change the old namespace?', true)) {
                        $count = 0;
                        $lines[$lineIndex] = \str_replace($olds, $news, $lineContent, $count);
                        $changed[] = ($lineIndex + 1);
                    }
                } else {
                    $changed[] = ($lineIndex + 1);
                }
            }
        }

        $changed && \file_put_contents($_path, \implode('', $lines));

        return $changed;
    }

    private function variants($namespaces)
    {
        $variants = [];
        foreach ($namespaces as $namespace) {
            $variants[] = '|'.$namespace.' ';
            $variants[] = '|'.$namespace.'|';
            $variants[] = '|\\'.$namespace.' ';
            $variants[] = '|\\'.$namespace.'|';
            $variants[] = '@param \\'.$namespace.' ';
            $variants[] = '@param  \\'.$namespace.' ';
            $variants[] = '@param '.$namespace.' ';
            $variants[] = '@param '.$namespace."\n";
            $variants[] = '@param  '.$namespace."\n";
            $variants[] = '@param '.$namespace."\r\n";
            $variants[] = '@param  '.$namespace."\r\n";
            $variants[] = '@param  '.$namespace.' ';
            /* $variants[] = '@return \\'.$namespace."\n";
             $variants[] = '@return  \\'.$namespace."\n";
             $variants[] = '@return '.$namespace."\n";
             $variants[] = '@return  '.$namespace."\n";
             $variants[] = '@return \\'.$namespace."\r\n";
             $variants[] = '@return  \\'.$namespace."\r\n";
             $variants[] = '@return '.$namespace."\r\n";
             $variants[] = '@return  '.$namespace."\r\n";*/
            //$variants[] = ' '.$namespace.' ';
            //$variants[] = ' \\'.$namespace.' ';
            $variants[] = $namespace.'(';
            $variants[] = $namespace.'::';
            $variants[] = $namespace.';';
            $variants[] = $namespace."\n";
            $variants[] = $namespace."\r";
            $variants[] = $namespace."\r\n";
        }

        return $variants;
    }

    private function collectNonPsr4Paths()
    {
        $paths = [
            RoutePaths::get(),
            Paths::getAbsFilePaths(LaravelPaths::migrationDirs()),
            Paths::getAbsFilePaths(config_path()),
            Paths::getAbsFilePaths(LaravelPaths::factoryDirs()),
            Paths::getAbsFilePaths(LaravelPaths::seeders()),
            LaravelPaths::bladeFilePaths(),
        ];

        return $this->mergePaths($paths);
    }

    private function mergePaths($paths)
    {
        $all = [];
        foreach ($paths as $p) {
            $all = array_merge($all, $p);
        }

        return $all;
    }

    private function fixReferences($autoload, $olds, $news)
    {
        foreach ($autoload as $psr4Path) {
            $files = FilePath::getAllPhpFiles($psr4Path);
            foreach ($files as $classFilePath) {
                $_path = $classFilePath->getRealPath();
                $this->fixAndReport($_path, $olds, $news);
            }
        }

        foreach ($this->collectNonPsr4Paths() as $_path) {
            $this->fixAndReport($_path, $olds, $news);
        }
    }

    private function checkNamespaces(array $autoload)
    {
        foreach ($autoload as $psr4Namespace => $psr4Path) {
            $files = FilePath::getAllPhpFiles($psr4Path);
            CheckNamespaces::within($files, $psr4Path, $psr4Namespace, $this);
        }
    }

    private function report($_path, $lineNumber)
    {
        app(ErrorPrinter::class)->simplePendError('', $_path, $lineNumber, 'ns_replacement', 'Namespace replacement:');
    }

    private function printErrorsCount($errorPrinter, $time)
    {
        if ($errorCount = $errorPrinter->errorsList['total']) {
            $this->warn(PHP_EOL.$errorCount.' error(s) found in namespaces');
        } else {
            $this->noErrorFound($time);
        }
    }

    private function reportResult($autoload)
    {
        $this->getOutput()->writeln('');
        $this->getOutput()->writeln('<fg=green>Finished!</fg=green>');
        $this->getOutput()->writeln('==============================');
        $this->getOutput()->writeln('<options=bold;fg=yellow>'.CheckNamespaces::$checkedNamespaces.' classes were checked under:</>');
        $this->getOutput()->writeln(' - '.implode("\n - ", array_keys($autoload)).'');
    }

    private function noErrorFound($time)
    {
        $time = microtime(true) - $time;
        $this->line(PHP_EOL.'<fg=green>All namespaces are correct!</><fg=blue> You rock  \(^_^)/ </>');
        $this->line('<fg=red;options=bold>'.round($time, 5).'(s)</>');
        $this->line('');
    }

    private function fixAndReport($_path, $olds, $news)
    {
        $lineNumbers = $this->fixRefs($_path, $olds, $news);

        foreach ($lineNumbers as $line) {
            $this->report($_path, $line);
        }
    }
}
