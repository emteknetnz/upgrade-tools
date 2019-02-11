<?php

namespace emteknetnz\UpgradeTools\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class RemoveDuplicateVersionsTablesTask extends BuildTask
{
    protected $description = 'Check for and remove any _versions table where an identical _Versions table exists - Import a fresh SS3 sspak, then run this, then run /dev/build';

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     */
    public function run($request)
    {
        $this->outputStyles();
        $data = $this->getTableMD5Data();
        if ($request->getVar('delete')) {
            $this->deleteTables($data);
        } else {
            $this->outputTableInfo($data);
        }
    }

    /**
     * Delete duplicate tables.
     * User must click a link in outputTableInfo() first
     *
     * @param array $data
     */
    protected function deleteTables($data)
    {
        foreach ($data as $table => $versions)
        {
            if (!isset($versions['versions']) || !isset($versions['Versions'])) {
                continue;
            }
            if ($versions['versions'] != $versions['Versions']) {
                $lines[] = "<p class='blue'>DIFFERENT CONTENT PLEASE CHECK: $table</p>";
            } else {
                DB::query("DROP TABLE $table". "_versions;");
                $lines[] = "<p class='red'>DELETED: $table" . "_versions</p>";
            }
        }
        echo implode("\n", $lines);
    }

    /**
     * Show the user which tables will be delete and which need to be manually looked at
     *
     * @param array $data
     */
    protected function outputTableInfo($data)
    {
        $c = 0;
        $lines = [];
        $lines[] = "<p>Click the link at the bottom of this page to delete tables</p>";
        foreach ($data as $table => $versions)
        {
            if (!isset($versions['versions']) || !isset($versions['Versions'])) {
                continue;
            }
            if ($versions['versions'] != $versions['Versions']) {
                $lines[] = "<p class='blue'>DIFFERENT CONTENT PLEASE CHECK: $table</p>";
            } else {
                $lines[] = "<p class='orange'>WILL DELETE: $table" . "_versions</p>";
                $c++;
            }
        }
        if ($c == 0) {
            $lines[] = '<p>No tables to delete</p>';
        } else {
            $lines[] = "<p><a href='?delete=1'>DELETE TABLES NOW</a></p>";
        }
        echo implode("\n", $lines);
    }

    /**
     * Generate an array of all _[vV]ersions tables
     *
     * @return array
     */
    protected function getTableMD5Data()
    {
        $data = [];
        $query = DB::query("SHOW TABLES");
        while ($row = $query->next()) {
            $values = array_values($row);
            $table = $values[0];
            if (preg_match('%^([A-Za-z0-9_]+)_([vV]ersions)$%', $table, $m)) {
                $name = $m[1];
                $version = $m[2];
                if (!isset($data[$name])) {
                    $data[$name] = [];
                }
                $data[$name][$version] = $this->getMD5OfTableContent($table);
            }
        }
        return $data;
    }

    /**
     * Generate an MD5 of table content based on the ID, RecordID columns
     *
     * @param string $table
     * @return string
     */
    protected function getMD5OfTableContent($table)
    {
        $data = [];
        $q = DB::query("SELECT ID, RecordID FROM $table");
        while ($row = $q->next()) {
            $data[] = $row['ID'];
            $data[] = $row['RecordID'];
        }
        if (empty($data)) {
            return '';
        }
        return md5(implode('', $data));
    }

    /**
     * Output CSS
     */
    protected function outputStyles()
    {
        echo <<<EOT
            <style>
              p.orange { color: darkorange } 
              p.red { color: red }
              p.blue { color: blue } 
            </style>
EOT;
    }
}
