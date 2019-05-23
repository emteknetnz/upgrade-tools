<?php

class StandardiseComposerJsonTask extends BuildTask
{

    /**
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {

    }

    protected function standardiseJsonString($jsonStr)
    {
        echo $jsonStr;
    }
}