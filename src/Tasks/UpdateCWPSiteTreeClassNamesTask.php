<?php

namespace emteknetnz\UpgradeTools\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class UpdateCWPSiteTreeClassNamesTask extends BuildTask
{
    protected $title = 'Update CWP SiteTree ClassNames Task';

    protected $description = "Prefixes CWP SiteTree ClassNames with CWP\\CWP\\PageTypes\\ - Import a fresh SS3 sspak, then run /dev/build, then run this";

    public function run ($request)
    {
        $tables = [
            'SiteTree',
            'SiteTree_Live',
            'SiteTree_Versions'
        ];

        $classes = [
            'NewsPage',
            'NewsHolder',
            'FooterHolder'
        ];

        foreach ($tables as $table) {
            foreach ($classes as $ss3ClassName) {
                $ss4ClassName = "CWP\\\\CWP\\\\PageTypes\\\\$ss3ClassName";
                if ($request->getVar('update')) {
                    $sql = "UPDATE $table SET ClassName = '$ss4ClassName' WHERE ClassName = '$ss3ClassName';";
                    DB::query($sql);
                } else {
                    $sql = "SELECT count(*) AS C FROM $table WHERE ClassName = '$ss3ClassName';";
                    $query = DB::Query($sql);
                    $row = $query->next();
                    echo "$sql -> " . $row['C'] . "<BR>\n";
                }
            }
        }
        if ($request->getVar('update')) {
            echo "Tables updated";
        } else {
            echo "<a href='?update=1'>UPDATE tables</a>";
        }
    }
}