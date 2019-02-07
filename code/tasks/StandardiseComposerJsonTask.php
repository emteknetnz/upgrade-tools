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
        $arr = json_decode($jsonStr, true);
        $this->sortTopLevel($arr);
        $this->sortRequirements($arr);
        $str = json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $str .= "\n";
        return $str;
    }

    protected function sortTopLevel(&$arr)
    {
        $orderedKeys = array_flip([
            'name',
            'description',
            'require',
            'requireDev',
            'repositories'
        ]);
        uksort($arr, function($a, $b) use ($orderedKeys) {
            if (!isset($orderedKeys[$a]) || !isset($orderedKeys[$b])) {
                return 0;
            }
            return $orderedKeys[$a] < $orderedKeys[$b] ? -1 : 1;
        });
    }

    protected function sortRequirements(&$arr)
    {
        $keys = [
            'require',
            'requireDev',
        ];
        foreach ($keys as $key) {
            if (!isset($arr[$key])) {
                continue;
            }
            ksort($arr[$key]);
        }
    }
}