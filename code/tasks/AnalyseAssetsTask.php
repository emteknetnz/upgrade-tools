<?php

class AnalyseAssetsTask extends BuildTask
{
    protected $description = 'List assets';

    /**
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {
        // styles
        $this->echoStyles();

        // options
        $this->echoOptions();

        // assets
        if ($request->getVar('which') == 'assets') {
            $this->showAssets();
        }

        // database
        if ($request->getVar('which') == 'database') {
            $sort = $request->getVar('sort') ?: 'MB';
            $this->echoDatabase($sort);
        }
    }

    private function showAssets()
    {
        var_dump($this->getDirContents(ASSETS_PATH));
    }

    /**
     * @param string $dir
     * @param array $results
     * @return array
     */
    private function getDirContents($dir, &$results = array())
    {
        foreach(scandir($dir) as $key => $value){
            if (in_array($value, ['.', '..'])) {
                continue;
            }
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (is_dir($path)) {
                $this->getDirContents($path, $results);
            } else {
                $this->updateResults($results, $this->getParts($path), filesize($path), basename($path));
            }
        }
        return $results;
    }

    /**
     * /var/www/mysite/www/assets/Uploads/SomeDir => ['Uploads', 'Somedir']
     *
     * @param string $path
     * @return array
     */
    private function getParts($path)
    {
        return array_merge(array_filter(explode(DIRECTORY_SEPARATOR, str_replace(ASSETS_PATH, '', dirname($path)))));
    }

    /**
     * ['Uploads', 'SomeDir'] => $results['Uploads'] = ['dirs' => [
     *   'SomeDir' => ['dirs' => [], 'files' => [], 'size' => 0]
     * ], 'files' => [], 'size' => 0]
     *
     * @param array $results
     * @param array $parts
     * @param int $filesize
     * @param string $filename
     */
    private function updateResults(array &$results, array $parts, $filesize, $filename)
    {
        $part = array_shift($parts);
        if (!$part) {
            return;
        }
        if (!isset($results[$part])) {
            $results[$part] = ['dirs' => [], 'files' => [], 'size' => 0];
        }
        if (empty($parts)) {
            $results[$part]['files'][] = $filename;
        } else {
            $this->updateResults($results[$part]['dirs'], $parts, $filesize, $filename);
        }
        $results[$part]['size'] += $filesize;
    }

    private function echoDatabase($sort)
    {
        $lines = ['<th>' . implode('</th><th>', [
            '<a href="?which=database&sort=Name">Name</a>',
            '<a href="?which=database&sort=Rows">Rows</a>',
            '<a href="?which=database&sort=MB">MB</a>'
        ]) . '</th>'];
        foreach ($this->getTableData($sort) as $r) {
            $lines[] = '<td>' . implode('</td><td>', [$r['Name'], $r['Rows'], $r['MB']]) . '</td>';
        }
        echo '<table><tr>' . implode('</tr><tr>', $lines) . '</tr></table>';
    }

    private function getTableData($sort)
    {
        $data = [];
        foreach (DB::query("SHOW TABLE STATUS") as $r) {
            $data[] = [
                'Name' => $r['Name'],
                'Rows' => $r['Rows'],
                'MB' => round(($r[ "Data_length" ] + $r[ "Index_length" ]) / (1024 * 1024), 2)
            ];
        }
        usort($data, function($a, $b) use ($sort) {
            if (!isset($a[$sort])) {
                return 0;
            }
            if (in_array($sort, ['MB', 'Rows'])) {
                return $a[$sort] > $b[$sort] ? -1 : 1;
            } else {
                return strcasecmp($a[$sort], $b[$sort]);
            }
        });
        return $data;
    }

    private function echoOptions()
    {
        echo <<<EOT
            <p>
                <a href="?which=assets">Assets</a> |
                <a href="?which=database">Database</a>
            </p>
EOT;

    }

    private function echoStyles()
    {
        echo <<<EOT
            <style>
                table {
                    border-collapse: collapse;
                }
                th {
                    text-align: left;
                }
                th, td {
                    border: 1px solid #ccc;
                    padding: 5px;
                }
            </style>
EOT;
    }
}
