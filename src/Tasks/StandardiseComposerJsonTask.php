<?php

namespace emteknetnz\UpgradeTools\Tasks;

use SilverStripe\Dev\BuildTask;

class StandardiseComposerJsonTask extends BuildTask
{
    protected $description = 'Standardise the composer.json file';

    protected $requirementKeys = [
        'require',
        'require-dev'
    ];

    protected $phpVersionRequires = [
        'php56' => '>=5.6',
        'php71' => '>=7.1',
        'php73' => '>=7.3',
    ];

    protected $phpVersionPlatforms = [
        'php56' => '5.6.38',
        'php71' => '7.1.24',
        'php71' => '7.3',
    ];

    /**
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {
        $path = BASE_PATH . '/composer.json';
        if (!file_exists($path)) {
            echo "<p>composer.json does not exist</p>\n";
            return;
        }
        echo <<<EOT
            <p>Select a php version:</p>
            <ul>
                <li><a href='?phpversion=php56'>5.6</a></li>
                <li><a href='?phpversion=php71'>7.1</a></li>
                <li><a href='?phpversion=php73'>7.3</a></li>
            </ul>
EOT;
        $phpVersion = $request->getVar('phpversion');
        if (!$phpVersion) {
            return;
        }
        if (!in_array($phpVersion, array_keys($this->phpVersionRequires))) {
            echo "<p>Invalid PHP Version</p>\n";
            return;
        }
        $jsonStr = file_get_contents($path);
        $newJsonStr = $this->standardiseJsonString($jsonStr, $phpVersion);
        if ($jsonStr == $newJsonStr) {
            echo "<p>Existing composer.json file has already been standardised to $phpVersion</p>";
            return;
        }
        if ($request->getVar('convert')) {
            file_put_contents($path, $newJsonStr);
            echo "<p>composer.json file has been standardised to $phpVersion</p>\n";
            return;
        }
        echo <<<EOT
            <p><a href='?phpversion=$phpVersion&convert=1'>Click here</a> to convert composer.json to this:</p>
            <pre style="background-color:#eee;border:1px solid #ddd;padding:10px;">$newJsonStr</pre>
EOT;
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
        $arr['require']['php'] = $this->phpVersionRequires[$phpVersion];
    }

    protected function updateConfig(array &$arr, $phpVersion)
    {
        if (!isset($arr['config'])) {
            $arr['config'] = [];
        }
        if (!isset($arr['config']['platform'])) {
            $arr['config']['platform'] = [];
        }
        $arr['config']['platform']['php'] = $this->phpVersionPlatforms[$phpVersion];
        if (!isset($arr['config']['process-timeout'])) {
            $arr['config']['process-timeout'] = 600;
        }
        ksort($arr['config']);
    }

    protected function sortTopLevel(array &$arr)
    {
        $orderedKeys = array_flip([
            'name',
            'description',
            'type',
            'license',
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
            $arr[$key] = preg_replace('%^\^([1-9][0-9]*)\..+$%', '^$1', $arr[$key]);
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
