<?php

/**
 * Used to list all unique page and content block types
 *
 * Works with Elemental and Sheadawson content blocks
 *
 * Intended to be used with wraith
 */
class ListUniquePageTypesTask extends BuildTask
{
    protected $title = 'List Unique Page Types';

    protected $description = 'List an example of each page type to help with regression testing';

    protected $excludeClasses = [
        'BaseHomePage',
        'RedirectorPage',
        'VirtualPage',
        'SubsitesVirtualPage'
    ];

    protected $pageIDsToBlockClassNames = [];

    /**
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {
        if (!Director::isDev() && !Permission::check('ADMIN')) {
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
            if ($subsiteID != -1) {
                Subsite::changeSubsite($subsiteID);
                $subsite = Subsite::all_sites()->find('ID', $subsiteID);
                echo "<br>";
                echo "<h2>{$subsite->Title} - {$subsite->ID}</h2>";
            }
            $blockPages = $this->getContentBlockPages($subsiteID);
            foreach ($blockPages as $blockPage) {
                $id = $blockPage['id'];
                if (!array_key_exists($id, $this->pageIDsToBlockClassNames)) {
                    $this->pageIDsToBlockClassNames[$id] = [];
                }
                $this->pageIDsToBlockClassNames[$id][] = $blockPage['blockclass'];
            }
            $pages = array_merge(
                $this->getContentBlockPages($subsiteID),
                $this->getPages()
            );
            // unique page ids & classes
            $pageIDs = [];
            $pageClasses = [];
            $pages = array_filter($pages, function($page) use (&$pageIDs, &$pageClasses) {
                if (in_array($page['id'], $pageIDs)) {
                    return false;
                }
                if (in_array($page['class'], $pageClasses) && $page['blockclass'] == '') {
                    return false;
                }
                $pageIDs[] = $page['id'];
                $pageClasses[] = $page['class'];
                return true;
            });
            $this->echoTable($pages);
        }
        Versioned::set_reading_mode($oldMode);
    }

    protected function echoTable($pages) {
        /** @var Subsite $subsite */
        /** @var Page $page */
        $absBaseUrl = Director::absoluteBaseURL();
        echo <<<EOT
            <table cellpadding='0' cellspacing='0'>
                <tr>
                    <td><b>PageClassName</b></td>
                        <td><b>BlockClassNames</b></td>
                        <td><b>PageID</b></td>
                        <td><b>FrontEndUrl</b></td>
                        <td><b>CMSUrl</b></td>
                </tr>
EOT;
        foreach ($pages as $page) {
            if ($page['cms'] == '/admin') {
                continue;
            }
            echo "<tr><td>";
            $frontEndUrl = $absBaseUrl . ltrim($page['frontend'], '/');
            $cmsUrl = $absBaseUrl . ltrim($page['cms'], '/');
            $blockClassNames = array_key_exists($page['id'], $this->pageIDsToBlockClassNames)
                ? implode('<br>', $this->pageIDsToBlockClassNames[$page['id']]) : '';
            echo implode("</td><td>", [
                $page['class'],
                $blockClassNames,
                $page['id'],
                "<a href='$frontEndUrl' target='_blank'>$frontEndUrl</a>",
                "<a href='$cmsUrl' target='_blank'>$cmsUrl</a>",
            ]);
            echo "</td></tr>";
        }
        echo "</table>";

        // get frontend urls and sort them
        $frontEndUrls = array_map(function($page) { return $page['frontend']; }, $pages);
        sort($frontEndUrls);

        // wraith capture:
        $baselineDomain = rtrim(preg_replace('%^(.+)\.(.+)$%', '$1-baseline.$2', $absBaseUrl), '/');
        $featureDomain = rtrim($absBaseUrl, '/');
        echo "<h3>Urls for wraith configs/capture.yaml</h3>";
        echo "<pre class='yml'>" . $this->createWraithCaptureYml($baselineDomain, $featureDomain, $frontEndUrls) . "</pre>";

        // raw paths:
        echo "<h3>Raw paths</h3>";
        echo "<div class='urls'>'" . implode("',<br>'", $frontEndUrls) . "',</div>";
    }

    protected function getPages()
    {
        $classes = ClassInfo::subclassesFor('Page');
        sort($classes);
        $pages = [];
        foreach ($classes as $class) {
            if (in_array($class, $this->excludeClasses)) {
                continue;
            }
            $page = Page::get()->filter('ClassName', $class)->first();
            if (!$page) {
                continue;
            }
            $pages[] = [
                'id' => $page->ID,
                'class' => $class,
                'blockclass' => '',
                'frontend' => $page->Link(),
                'cms' => str_replace('?Locale=en_NZ', '', '/' . $page->CMSEditLink())
            ];
        }
        return $pages;
    }

    protected function getContentBlockPages($subsiteID = -1)
    {
        $pages = [];
        $baseClasses = [
            // sheadawson blocks
            'Block',
            // dna elements blocks
            'BaseElement'
        ];
        foreach ($baseClasses as $baseClass) {
            if (!class_exists($baseClass)) {
                continue;
            }
            $classes = ClassInfo::subclassesFor($baseClass);
            foreach ($classes as $class) {
                if (in_array($class, $this->excludeClasses)) {
                    continue;
                }
                $subsiteWhere = '';
                if ($subsiteID != -1) {
                    $subsiteWhere = "where SiteTree_Live.SubsiteID = $subsiteID";
                }
                $sql = '';
                if ($baseClass == 'Block') {
                    $sql = <<<EOT
                        select
                            SiteTree_Live.ID as ID,
                            SiteTree_Live.ClassName as ClassName,
                            Block_Live.ClassName as BlockClassName
                        from
                            SiteTree_Blocks
                        inner join
                            SiteTree_Live on SiteTree_Blocks.SiteTreeID = SiteTree_Live.ID
                        inner join
                            Block_Live on SiteTree_Blocks.BlockID = Block_Live.ID
                        $subsiteWhere
                        and
                            Block_Live.ClassName = '$class'
                        limit 1
EOT;
                }
                if ($baseClass == 'BaseElement') {
                    $sql = <<<EOT
                        select
                            SiteTree_Live.ID as ID,
                            SiteTree_Live.ClassName as ClassName,
                            Widget_Live.ClassName as BlockClassName
                        from
                            Widget_Live
                        inner join
                            Page_Live
                        on
                            Widget_Live.ParentID = Page_Live.ElementAreaID
                        inner join
                            SiteTree_Live
                        on
                            Page_Live.ID = SiteTree_Live.ID
                        $subsiteWhere
                        and
                            Widget_Live.ClassName = '$class'
                        limit 1
EOT;
                }
                $query = DB::query($sql);
                $r = $query->first();
                if (!$r) {
                    continue;
                }
                $page = Page::get()->byID($r['ID']);
                if (!$page) {
                    continue;
                }
                $pages[] = [
                    'id'         => $page->ID,
                    'class'      => $r['ClassName'],
                    'blockclass' => $r['BlockClassName'],
                    'frontend'   => $page->Link(),
                    'cms'        => str_replace('?Locale=en_NZ', '', '/' . $page->CMSEditLink())
                ];
            }
        }
        return $pages;
    }

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
                background-color: #efefef;
                border: 1px solid green;
                padding: 20px;
              }
              .yml {
                background-color: #efefef;
                border: 1px solid blue;
                padding: 20px;
              }
            </style>        
EOT;
    }

    protected function createWraithCaptureYml($baselineDomain, $featureDomain, array $paths)
    {
        $pathsYml = $this->createPathsYml($paths);
        return <<<EOT
domains:
  baseline: '$baselineDomain'
  feature:  '$featureDomain'

paths:
$pathsYml

screen_widths:
  - 1280

threshold: 2

directory: 'shots'

browser: 'phantomjs'

fuzz: '20%'

EOT;
    }

    protected function createPathsYml(array $paths)
    {
        $arr = [];
        for ($i = 1; $i <= count($paths); $i++) {
            $path = $paths[$i - 1];
            $x = 'x' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $arr[] = "  $x: $path";
        }
        return implode("\n", $arr);
    }
}
