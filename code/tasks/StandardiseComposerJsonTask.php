<?php

class StandardiseComposerJsonTask extends BuildTask
{

    protected $requirementKeys = [
        'require',
        'require-dev'
    ];

    protected $phpVersionRequires = [
        'php56' => '>=5.6',
        'php71' => '>=7.1',
    ];

    protected $phpVersionPlatforms = [
        'php56' => '5.6.38',
        'php71' => '7.1.24',
    ];

    /**
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {

    }

    protected function standardiseJsonString($jsonStr, $phpVersion)
    {
        $arr = json_decode($jsonStr, true);
        if (!$arr) {
            throw new Exception('Invalid composer.json');
        }
        $this->addPHPRequirements($arr, $phpVersion);
        $this->updateConfig($arr, $phpVersion);
        $this->sortRequirements($arr);
        $this->fixCaretRequirements($arr);
        $this->addPreferStable($arr);
        $this->sortTopLevel($arr);
        $str = json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $str .= "\n";
        return $str;
    }

    // add php version to allow phpstorm to auto-detect settings better
    protected function addPHPRequirements(array &$arr, $phpVersion)
    {
        if (!isset($arr['require'])) {
            return;
        }
        if (!isset($arr['require']['php'])) {
            $arr['require']['php'] = $this->phpVersionRequires[$phpVersion];
        }
    }

    protected function updateConfig(array &$arr, $phpVersion)
    {
        if (!isset($arr['config'])) {
            $arr['config'] = [];
        }
        if (!isset($arr['config']['platform'])) {
            $arr['config']['platform'] = [];
        }
        if (!isset($arr['config']['platform']['php'])) {
            $arr['config']['platform']['php'] = $this->phpVersionPlatforms[$phpVersion];
        }
        if (!isset($arr['config']['process-timeout'])) {
            $arr['config']['process-timeout'] = 600;
        }
    }

    protected function sortTopLevel(array &$arr)
    {
        $orderedKeys = array_flip([
            'name',
            'description',
            'require',
            'require-dev',
            'repositories',
            'prefer-stable',
            'minimum-stability',
            'config',
            'autoload',
            'extra',
        ]);
        uksort($arr, function($a, $b) use ($orderedKeys) {
            if (!isset($orderedKeys[$a]) || !isset($orderedKeys[$b])) {
                return 0;
            }
            return $orderedKeys[$a] < $orderedKeys[$b] ? -1 : 1;
        });
    }

    protected function sortRequirements(array &$arr)
    {
        foreach ($this->requirementKeys as $key) {
            if (!isset($arr[$key])) {
                continue;
            }
            uksort($arr[$key], function($a, $b) {
                if ($a == 'php') {
                    return -1;
                }
                if ($b == 'php') {
                    return 1;
                }
                return strcasecmp($a, $b);
            });
        }
    }

    // convert ^3.1 requirements to ^3
    protected function fixCaretRequirements(array &$arr)
    {
        foreach ($this->requirementKeys as $key) {
            if (!isset($arr[$key])) {
                continue;
            }
            $arr[$key] = preg_replace('%^\^([0-9]+)\..+$%', '^$1', $arr[$key]);
        }
    }

    protected function addPreferStable(array &$arr)
    {
        $keyValues = [
            'prefer-stable' => true,
            'minimum-stability' => 'dev'
        ];
        foreach ($keyValues as $key => $value) {
            if (!isset($arr[$key])) {
                $arr[$key] = $value;
            }
        }
    }
}