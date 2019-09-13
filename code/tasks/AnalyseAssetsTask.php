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
            $this->showDatabase($sort);
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

            $parts = array_filter(explode(DIRECTORY_SEPARATOR, str_replace(ASSETS_PATH, '', dirname($path))));
            $this->thinger($results, $parts);

            if (is_dir($path)) {
                if (!isset($results[$path])) {
                    $results[$path] = [];
                }
                $this->getDirContents($path, $results);
            } else {
                $results[$path] = [
                    'filename' => $value,
                    'filesize' => filesize($path)
                ];
            }

//            if (!is_dir($path)) {
//                // filename
//                // $results[] = $path;
//                // filesize
//                $results[] = filesize($path);
//            } else {
//                $this->getDirContents($path, $results);
//                $results[] = $path;
//            }
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $parts
     */
    private function thinger(array &$results, array $parts)
    {
        $c = count($parts);
        if ($c > 1) {
            //if (!)
        }
    }

    private function showDatabase($sort)
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
