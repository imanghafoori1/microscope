<?php

namespace Imanghafoori\LaravelMicroscope\Analyzers;

use Imanghafoori\LaravelMicroscope\ForPsr4LoadedClasses;

class Fixer
{
    private static function getCorrect($class)
    {
        $class_list = ForPsr4LoadedClasses::classList();
        $segments = \explode('\\', $class);
        $className = array_pop($segments);
        $correct = $class_list[$className] ?? [];

        return [$className, $correct];
    }

    public static function fixReference($absPath, $inlinedClassRef, $lineNum)
    {
        if (config('microscope.no_fix')) {
            return [false, []];
        }

        [$classBaseName, $correct] = self::getCorrect($inlinedClassRef);

        if (\count($correct) !== 1) {
            return [false, $correct];
        }
        $fullClassPath = $correct[0];

        $contextClassNamespace = NamespaceCorrector::getNamespacedClassFromPath($absPath);

        if (NamespaceCorrector::haveSameNamespace($contextClassNamespace, $fullClassPath)) {
            return [FileManipulator::replaceFirst($absPath, $inlinedClassRef, class_basename($fullClassPath), $lineNum), $correct];
        }

        $uses = ParseUseStatement::parseUseStatements(token_get_all(file_get_contents($absPath)))[1];

        // if there is any use statement at the top
        if (count($uses) && ! isset($uses[$classBaseName])) {
            // replace in the class reference
            FileManipulator::replaceFirst($absPath, $inlinedClassRef, $classBaseName, $lineNum);

            // insert a new import at the top
            $lineNum = array_values($uses)[0][1]; // first use statement

            return [FileManipulator::insertAtLine($absPath, "use $fullClassPath;", $lineNum), $correct];
        }

        isset($uses[$classBaseName]) && ($fullClassPath = $classBaseName);

        return [FileManipulator::replaceFirst($absPath, $inlinedClassRef, $fullClassPath, $lineNum), $correct];
    }

    public static function fixImport($absPath, $import, $lineNum, $isAliased)
    {
        if (config('microscope.no_fix')) {
            return [false, []];
        }

        [$classBaseName, $correct] = self::getCorrect($import);

        if (\count($correct) !== 1) {
            return [false, $correct];
        }

        $hostNamespacedClass = NamespaceCorrector::getNamespacedClassFromPath($absPath);
        // We just remove the wrong import if it is not needed.
        if (! $isAliased && NamespaceCorrector::haveSameNamespace($hostNamespacedClass, $correct[0])) {
            $chars = 'use '.$import.';';

            $result = FileManipulator::replaceFirst($absPath, $chars, '', $lineNum);

            return [$result, [' Deleted!']];
        }

        return [FileManipulator::replaceFirst($absPath, $import, $correct[0], $lineNum), $correct];
    }
}
