<?php

class ListUniquePageTypesTask extends BuildTask
{

    /**
     * @var string
     */
    protected $title = 'List Unique Page Types';

    /**
     * @var string
     */
    protected $description = 'List an example of each page type to help with regression testing';

    /**
     * @var array
     */
    protected $excludeClasses = [
        'BaseHomePage',
        'RedirectorPage',
        'VirtualPage',
        'SubsitesVirtualPage'
    ];

    /**
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {
        if (!Directory::isDev() && !Permission::check('ADMIN')) {
            echo 'Only admins may run this task';
            return;
        }
        $oldMode = Versioned::get_reading_mode();
        Versioned::reading_stage('Live');
        $this->echoStyles();
        $subsiteIDs = [-1];
        if (class_exists('Subsite')) {
            $subsiteIDs = Subsite::all_sites()->column('ID');
            Subsite::$use_session_subsiteid = true;
        }
        foreach ($subsiteIDs as $subsiteID) {
            $this->echoTable($subsiteID);
        }
        Versioned::set_reading_mode($oldMode);
    }

    /**
     * @param int $subsiteID
     */
    protected function echoTable($subsiteID = -1) {
        /** @var Subsite $subsite */
        /** @var Page $page */
        $absBaseUrl = Director::absoluteBaseURL();
        $classes = ClassInfo::subclassesFor('Page');
        sort($classes);
        if ($subsiteID != -1) {
            Subsite::changeSubsite($subsiteID);
            $subsite = Subsite::all_sites()->find('ID', $subsiteID);
            $absBaseUrl = $subsite->absoluteBaseURL();
            echo "<br>";
            echo "<h2>{$subsite->Title} - {$subsite->ID}</h2>";
        }
        $ids = [];
        $urls = [];
        $cmsLinks = [];
        echo "<table cellpadding='0' cellspacing='0'>";
        foreach ($classes as $class) {
            if (in_array($class, $this->excludeClasses)) {
                continue;
            }
            $page = Page::get()->filter('ClassName', $class)->exclude('ID', $ids)->first();
            if (!$page) {
                continue;
            }
            $ids[] = $page->ID;
            $link = $absBaseUrl . ltrim($page->Link(), '/');
            $cmsLink = $absBaseUrl. $page->CMSEditLink();
            echo "<tr><td>";
            echo implode("</td><td>", [
                $class,
                $page->ID,
                "<a href='$link' target='_blank'>$link</a>",
                "<a href='$cmsLink' target='_blank'>$cmsLink</a>",
            ]);
            echo "</td></tr>";
            $urls[] = "'{$page->Link()}',";
            $cmsUrls = "'{$page->CMSEditLink()}',";
        }
        echo "</table>";
        sort($urls);
        echo "<div class='urls'>" . implode("<br>", $urls) . "</div>";
        sort($cmsUrls);
        echo "<div class='urls'>" . implode("<br>", $cmsUrls) . "</div>";
    }

    /**
     * CSS styles
     */
    protected function echoStyles()
    {
        echo <<<EOT
            <style>
              table {
                border-collapse: collapse;
              }
              td {
                border: 1px solid #999;
                padding: 10px;
                font-family: courier new;
                font-size: 13px;
                vertical-align: top;
              }
              .urls {
                font-family: courier new;
                font-size: 13px;
                padding: 20px 0 0 0;
              }
            </style>        
EOT;
    }
}
