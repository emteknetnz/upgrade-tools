<?php

class StandardiseComposerJsonTaskTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testStandardise()
    {
        $dataDir = dirname(__FILE__) . '/data';
        $jsonStr = file_get_contents("$dataDir/1-before.json");
        $class = new ReflectionClass('StandardiseComposerJsonTask');
        $method = $class->getMethod('standardiseJsonString');
        $method->setAccessible(true);
        $inst = StandardiseComposerJsonTask::create();
        $expected = file_get_contents("$dataDir/2-after.json");
        $actual = $method->invokeArgs($inst, [$jsonStr, 'php71']);
        $this->assertEquals($expected, $actual);
    }
}