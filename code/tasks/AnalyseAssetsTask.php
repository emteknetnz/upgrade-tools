<?php

// TODO: look to get this as a job that runs in the background (queuedjob), probably overnight
// possibly add combined file size of directories as new field on File (extension)
// - depends about file manager UI re showing thumbnails, _versions, _resampled folders
// - otherwise, just keep as standalone report with its own UI
// intention is to run this on prod servers for site owners to browse at their leisure, not just a dev tool
// will probably split this off as its own module

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
            $this->echoAssets();
        }

        // database
        if ($request->getVar('which') == 'database') {
            $sort = $request->getVar('sort') ?: 'MB';
            $this->echoDatabase($sort);
        }
    }

    private function echoAssets()
    {
        $results = $this->getDirContents(ASSETS_PATH);
        $html = $this->generateAssetsHtml($results);
        echo implode('', $html);
    }

    /**
     * @param array $results
     * @param int $level
     * @return array
     */
    private function generateAssetsHtml($results)
    {
        $html = [];
        $html[] = "<div class='assets-dirs' style='padding-left:10px'>";
        foreach ($results as $part => $data) {
            $dirsize = $this->formatBytes($data['dirsize']);
            $style = in_array($part, ['_resampled', '_versions']) ? 'display:none' : '';
            $style = '';
            $html[] = <<<EOT
                <div class='assets-dir' style="$style">
                    <span class='assets-dirname'>$part</span> -
                    <span class='assets-dirsize'>$dirsize</span>
EOT;
            $html = array_merge($html, $this->generateAssetsHtml($data['dirs']));
            $display = 'block';
            $html[] = "<div class='assets-files' style='display:$display;padding-left:10px'>";
            foreach ($data['files'] as $arr) {
                $filename = $arr['filename'];
                $filesize = $this->formatBytes($arr['filesize']);
                $html[] = <<<EOT
                    <div class='assets-file'>
                        <span class='assets-filename'>$filename</span> -
                        <span class='assets-filesize'>$filesize</span>
                    </div>
EOT;
            }
            $html[] = '</div></div>';
        }
        $html[] = '</div>';
        return $html;
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
            $results[$part] = ['dirs' => [], 'dirsize' => 0, 'files' => []];
        }
        if (empty($parts)) {
            $results[$part]['files'][] = ['filename' => $filename, 'filesize' => $filesize];
        } else {
            $this->updateResults($results[$part]['dirs'], $parts, $filesize, $filename);
        }
        $results[$part]['dirsize'] += $filesize;
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

    private function formatBytes($size, $precision = 1)
    {
        $base = log($size, 1024);
        $suffixes = array('', 'KB', 'MB', 'GB', 'TB');
        return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
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
                .assets-dir {}
                .assets-dirname {
                    color: blue;
                }
                .assets-file {}
                .assets-filename {
                    color: green;
                }
                .assets-dirsize,
                .assets-filesize {
                    color: purple;
                    font-size: 13px;
                }
            </style>
EOT;
    }
}
