<?php

class StandardiseComposerJsonTaskTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testStandardise()
    {
        $jsonStr = 'abc';
        $class = new ReflectionClass('StandardiseComposerJsonTask');
        $method = $class->getMethod('standardiseJsonString');
        $method->setAccessible(true);
        $inst = StandardiseComposerJsonTask::create();
        $method->invokeArgs($inst, $jsonStr);
    }
}