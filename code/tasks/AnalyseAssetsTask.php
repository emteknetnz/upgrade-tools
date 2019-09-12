<?php

class AnalyseAssetsTask extends BuildTask
{
    protected $description = 'List assets';

    /**
     * @param SS_HTTPRequest $request
     */
    public function run($request): void
    {
        $sort = $request->getVar('sort') ?: 'MB';

        // styles
        $this->echoStyles();

        // assets

        // database
        $this->showTables($sort);
    }

    private function showTables(string $sort): void
    {
        $lines = ['<th>' . implode('</th><th>', [
            '<a href="?sort=Name">Name</a>',
            '<a href="?sort=Rows">Rows</a>',
            '<a href="?sort=MB">MB</a>'
        ]) . '</th>'];
        foreach ($this->getTableData($sort) as $r) {
            $lines[] = '<td>' . implode('</td><td>', [$r['Name'], $r['Rows'], $r['MB']]) . '</td>';
        }
        echo '<table><tr>' . implode('</tr><tr>', $lines) . '</tr></table>';
    }

    private function getTableData(string $sort): array
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
            return $a[$sort] <=> $b[$sort];
        });
        if (in_array($sort, ['MB', 'Rows'])) {
            $data = array_reverse($data);
        }
        return $data;
    }

    private function echoStyles(): void
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
