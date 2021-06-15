<?php

namespace Imanghafoori\LaravelMicroscope\Tests;

use Imanghafoori\LaravelMicroscope\StubFileManager;

class StubFileManagerTest extends BaseTestClass
{
    public function test_service_provider_stub()
    {
        $mapping = [
            'correctNamespace' => 'Testing\Namespace',
            'className' => 'TestClass',
            'name' => 'TestName'
        ];

        $this->checkByMappingValues($mapping);
    }

    public function test_service_provider_by_dollar_sign_key()
    {
        $mapping = [
            '$correctNamespace' => 'Testing\Namespace',
            '$className' => 'TestClass',
            '$name' => 'TestName'
        ];

        $this->checkByMappingValues($mapping);
    }

    public function test_service_provider_by_dollar_sign_key_mixed_normal_key()
    {
        $mapping = [
            'correctNamespace' => 'Testing\Namespace',
            '$className' => 'TestClass',
            'name' => 'TestName'
        ];

        $this->checkByMappingValues($mapping);
    }

    public function checkByMappingValues($mapping)
    {
        //check stub without afffix (.stub) like a microscopeServiceProvider
        $this->checkEqualsResult($mapping);

        //check stub with afffix (.stub) like a microscopeServiceProvider.stub
        $this->checkEqualsResult($mapping, 'with_stubs_format');
    }

    public function checkEqualsResult($mapping, $type = 'without_stub_file_format')
    {
        $expected = file_get_contents(__DIR__ . '/stubs/microscopeServiceProvider.stub');

        $stubNameOrFileName = 'microscopeServiceProvider';

        // adding the stub afffix into name when tester need to test stub with affix (.stub)
        if ($type != 'without_stub_file_format') {
            $stubNameOrFileName .= '.stub';
        }

        $uses = StubFileManager::getRenderedStub($stubNameOrFileName, $mapping);

        $this->assertEquals($expected, $uses);
    }
}
