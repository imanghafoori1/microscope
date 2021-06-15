<?php

namespace Imanghafoori\LaravelMicroscope;

use InvalidArgumentException;

class StubFileManager
{
    const STUBS_FOLDER_NAME = 'Stubs';

    const STUBS_FILE_FORMAT = '.stub';
    
    /**
     * Render The Stub With Passed Paramt into typeHintClassPath
     * 
     * @param string $stubName
     * 
     * @param array $keysMap
     * 
     * @return string
     */
    public static function getRenderedStub($stubFileName, $keysMap = null)
    {
        $mapping = [];

        $stubContent = self::getStubContentByFileName($stubFileName);

        // When Stub type is Staitc Stub
        if (is_null($keysMap)) {
            return $stubContent;
        }

        foreach ($keysMap as $key => $map) {

            $normalizedKey = str_replace('$', '', $key);

            $searchKey = "{{" . '$' . $normalizedKey . "}}";

            $mapping[$searchKey] = $map;
        }

        return str_replace(array_keys($mapping), array_values($mapping), $stubContent);
    }

    /**
     * Get the Stubs Content When Stub File is Exists!
     * 
     * @param string $stubName
     * 
     * @throws InvalidArgumentException
     * 
     * @return string
     */
    private static function getStubContentByFileName($stubName)
    {
        $stubName = str_replace(self::STUBS_FILE_FORMAT, '', $stubName);

        $stubFilePath = __DIR__ . DIRECTORY_SEPARATOR . self::STUBS_FOLDER_NAME . DIRECTORY_SEPARATOR . $stubName . self::STUBS_FILE_FORMAT;

        if (!(file_exists($stubFilePath) && is_readable($stubFilePath))) {
            throw new InvalidArgumentException("$stubName is not found in stubs folder");
        }

        return file_get_contents($stubFilePath);
    }
}
