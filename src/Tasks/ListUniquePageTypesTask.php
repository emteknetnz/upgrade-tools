<?php

namespace emteknetnz\UpgradeTools\Tasks;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;

/**
 * Used to list all unique page and content block types
 *
 * Works with Elemental and Sheadawson content blocks
 *
 * Intended to be used with wraith
 */
class ListUniquePageTypesTask extends BuildTask
{
    protected $description = 'List an example of each page type to help with regression testing';

    protected $excludedPageClasses = [
        'BaseHomePage', // TODO: this
        'SilverStripe\CMS\Model\RedirectorPage',
        'SilverStripe\CMS\Model\VirtualPage',
        'SilverStripe\Subsites\Pages\SubsitesVirtualPage',
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
        $subsiteSingleton = $this->getSubsiteSingleton();
        $oldMode = Versioned::get_reading_mode();
        Versioned::set_reading_mode('Stage.Live');
        $this->echoStyles();
        $subsiteIDs = [-1];
        if ($subsiteSingleton) {
            $subsiteIDs = $subsiteSingleton::all_sites()->column('ID');
        }
        foreach ($subsiteIDs as $subsiteID) {
            if ($subsiteID != -1) {
                $subsiteStateSingleton = $this->getSubsiteStateSingleton();
                $subsiteStateSingleton->setSubsiteId($subsiteID);
                $subsite = $subsiteSingleton::all_sites()->find('ID', $subsiteID);
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
            $pagePlusBlockClasses = [];
            $pages = array_filter($pages, function($page) use (&$pageIDs, &$pageClasses, &$pagePlusBlockClasses) {
                if (in_array($page['id'], $pageIDs)) {
                    return false;
                }
                if (in_array($page['class'], $pageClasses) && $page['blockclass'] == '') {
                    return false;
                }
                $pagePlusBlockClass = $page['class'] . $page['blockclass'];
                if (in_array($pagePlusBlockClass, $pagePlusBlockClasses)) {
                    return false;
                }
                $pageIDs[] = $page['id'];
                $pageClasses[] = $page['class'];
                $pagePlusBlockClasses[] = $pagePlusBlockClass;
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
        $classes = ClassInfo::subclassesFor('SilverStripe\CMS\Model\SiteTree');
        sort($classes);
        $pages = [];
        foreach ($classes as $class) {
            if (in_array($class, $this->excludedPageClasses)) {
                continue;
            }
            $page = SiteTree::get()->filter('ClassName', $class)->first();
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
        $baseElementalClass = 'DNADesign\Elemental\Models\BaseElement';
        if (!class_exists($baseElementalClass)) {
            return $pages;
        }
        $elementalClasses = ClassInfo::subclassesFor($baseElementalClass);
        $elementalClasses = array_diff($elementalClasses, [$baseElementalClass]);
        foreach ($elementalClasses as $elementalClass) {
            $subsiteWhere = '';
            if ($subsiteID != -1) {
                $subsiteWhere = "SiteTree_Live.SubsiteID = $subsiteID AND";
            }
            $escapedElementalClass = addslashes($elementalClass);
            $escapedPageClasses = [];
            foreach ($this->excludedPageClasses as $pageClass) {
                $escapedPageClasses[] = addslashes($pageClass);
            }
            $siteTreeNotIn = implode("','", $escapedPageClasses);

            $pageClasses = ClassInfo::subclassesFor('Page');
            foreach ($pageClasses as $pageClass) {

                $pos = strrpos($pageClass, '\\');
                $pageClassNoNamespace = $pos ? substr($pageClass, $pos + 1) : $pageClass;

                $tableName = $pageClassNoNamespace . '_Live';
                if (!ClassInfo::hasTable($tableName)) {
                    continue;
                }

                $query = DB::query("SELECT * FROM $tableName");
                $r = $query->first();
                if (!$r) {
                    continue;
                }
                if (!isset($r['ElementalAreaID'])) {
                    continue;
                }

                $sql = <<<EOT
                    SELECT
                        SiteTree_Live.ID as ID,
                        SiteTree_Live.ClassName as ClassName,
                        Element_Live.ClassName as BlockClassName
                    FROM
                        Element_Live
                    INNER JOIN
                        $tableName
                    ON
                        Element_Live.ParentID = $tableName.ElementalAreaID
                    INNER JOIN
                        SiteTree_Live
                    ON
                        $tableName.ID = SiteTree_Live.ID
                    WHERE
                        $subsiteWhere
                        Element_Live.ClassName = '$escapedElementalClass'
                    AND
                        SiteTree_Live.ClassName NOT IN ('$siteTreeNotIn')
                    LIMIT 1
EOT;
                $query = DB::query($sql);
                $r = $query->first();
                if (!$r) {
                    continue;
                }
                $page = SiteTree::get()->byID($r['ID']);
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

    /**
     * Not all sites will have subsites, so cannot use the Subsite namespace
     * Using a singleton instead to make it dynamic
     */
    protected function getSubsiteSingleton()
    {
        $class = 'SilverStripe\Subsites\Model\Subsite';
        return class_exists($class) ? singleton($class) : null;
    }

    protected function getSubsiteStateSingleton()
    {
        $class = 'SilverStripe\Subsites\State\SubsiteState';
        return class_exists($class) ? singleton($class) : null;
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
# docker-wraith capture configs/capture.yaml        
        
domains:
  baseline: '$baselineDomain'
  feature:  '$featureDomain'

paths:
$pathsYml

screen_widths:
  - 1280

threshold: 2

fuzz: '20%'

directory: 'shots'

mode: diffs_first

browser: 'phantomjs'

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
