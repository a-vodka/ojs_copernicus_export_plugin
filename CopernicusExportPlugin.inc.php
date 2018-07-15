<?php

/**
 * @file plugins/importexport/copernicus/CopernicusExportPlugin.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopernicusExportPlugin
 * @ingroup plugins_importexport_copernicus
 *
 * @brief Copernicus import/export plugin
 */

import('classes.plugins.ImportExportPlugin');
import('lib.pkp.classes.xml.XMLCustomWriter');
import('classes.file.ArticleFileManager');
import('classes.file.PublicFileManager');
import('plugins.pubids.doi.DOIPubIdPlugin');

class CopernicusExportPlugin extends ImportExportPlugin
{
    /**
     * Called as a plugin is registered to the registry
     * @param $category String Name of category plugin was registered to
     * @return boolean True iff plugin initialized successfully; if false,
     *    the plugin will not be registered.
     */
    function register($category, $path)
    {
        $success = parent::register($category, $path);
        // Additional registration / initialization code
        // should go here. For example, load additional locale data:
        $this->addLocaleData();

        // This is fixed to return false so that this coding sample
        // isn't actually registered and displayed. If you're using
        // this sample for your own code, make sure you return true
        // if everything is successfully initialized.
        // return $success;
        return $success;
    }

    /**
     * Get the name of this plugin. The name must be unique within
     * its category.
     * @return String name of plugin
     */
    function getName()
    {
        // This should not be used as this is an abstract class
        return 'CopernicusExportPlugin';
    }

    function getDisplayName()
    {
        return __('plugins.importexport.copernicus.displayName');
    }

    function displayName()
    {
        return 'Copernicus export plugin';
    }

    function getDescription()
    {
        return __('plugins.importexport.copernicus.description');
    }

    function formatDate($date)
    {
        if ($date == '') return null;
        return date('Y-m-d', strtotime($date));
    }

    function multiexplode($delimiters, $string)
    {

        $ready = str_replace($delimiters, $delimiters[0], $string);
        $launch = explode($delimiters[0], $ready);
        return $launch;
    }

    function &generateIssueDom(&$doc, &$journal, &$issue)
    {

        define('JATS_DEFAULT_EXPORT_LOCALE', 'en_US');

        $issn = $journal->getSetting('printIssn');

        $root =& XMLCustomWriter::createElement($doc, 'ici-import');
        $journal_elem = XMLCustomWriter::createChildWithText($doc, $root, 'journal', '', true);
        XMLCustomWriter::setAttribute($journal_elem, 'issn', $issn);

        $issue_elem = XMLCustomWriter::createChildWithText($doc, $root, 'issue', '', true);


        XMLCustomWriter::setAttribute($issue_elem, 'number', $issue->getNumber());
        XMLCustomWriter::setAttribute($issue_elem, 'volume', $issue->getVolume());
        XMLCustomWriter::setAttribute($issue_elem, 'year', $issue->getYear());


        $sectionDao =& DAORegistry::getDAO('SectionDAO');
        $publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
        $articleFileDao =& DAORegistry::getDAO('ArticleFileDAO');
        $publicFileManager = new PublicFileManager();
        $doiplugin = new DOIPubIdPlugin();
        foreach ($sectionDao->getSectionsForIssue($issue->getId()) as $section) {

            foreach ($publishedArticleDao->getPublishedArticlesBySectionId($section->getId(), $issue->getId()) as $article) {

                $locales = array_keys($article->_data['title']);
                $article_elem = XMLCustomWriter::createChildWithText($doc, $issue_elem, 'article', '', true);
                XMLCustomWriter::createChildWithText($doc, $article_elem, 'type', 'ORIGINAL_ARTICLE');
                foreach ($locales as $loc) {
                    $lc = explode('_', $loc);
                    $lang_version = XMLCustomWriter::createChildWithText($doc, $article_elem, 'languageVersion', '', true);
                    XMLCustomWriter::setAttribute($lang_version, 'language', $lc[0]);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'title', $article->getLocalizedTitle($loc), true);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'abstract', $article->getLocalizedData('abstract', $loc), true);

                    foreach ($articleFileDao->getArticleFilesByArticle($article->getId()) as $files) {
                        $url = 'http://' . $_SERVER['HTTP_HOST'] . pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
                        $url .= '/article/viewFile/' . $article->getId() . '/' . $files->getFileId();
                        XMLCustomWriter::createChildWithText($doc, $lang_version, 'pdfFileUrl', $url, true);
                        break;
                    }
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'publicationDate', $article->getDatePublished(), false);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageFrom', $article->getStartingPage(), true);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageTo', $article->getEndingPage(), true);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'doi', $doiplugin->getPubId($article), true);

                    $keywords = XMLCustomWriter::createChildWithText($doc, $lang_version, 'keywords', '', true);
                    $kwds = $this->multiexplode(array(',', ';'), $article->getLocalizedData('subject', $loc));

                    foreach ($kwds as $k) {
                        XMLCustomWriter::createChildWithText($doc, $keywords, 'keyword', $k, true);
                    }

                }
                $authors_elem = XMLCustomWriter::createChildWithText($doc, $article_elem, 'authors', '', true);
                $index = 1;
                foreach ($article->getAuthors() as $author) {
                    $author_elem = XMLCustomWriter::createChildWithText($doc, $authors_elem, 'author', '', true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'name', $author->getFirstName(), true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'name2', $author->getMiddleName(), false);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'surname', $author->getLastName(), true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'email', $author->getEmail(), false);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'order', $index, true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'instituteAffiliation', $author->getAffiliation(null), false);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'role', 'AUTHOR', true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'ORCID', $author->getData('orcid'), false);

                    $index++;
                }

            }
        }
        return $root;
        //NativeExportDom::generatePubId($doc, $root, $issue, $issue);
        XMLCustomWriter::setAttribute($root, 'published', $issue->getPublished() ? 'true' : 'false');
        switch (
            (int)$issue->getShowVolume() .
            (int)$issue->getShowNumber() .
            (int)$issue->getShowYear() .
            (int)$issue->getShowTitle()
        ) {
            case '1110':
                $idType = 'num_vol_year';
                break;
            case '1010':
                $idType = 'vol_year';
                break;
            case '0010':
                $idType = 'year';
                break;
            case '1000':
                $idType = 'vol';
                break;
            case '0001':
                $idType = 'title';
                break;
            default:
                $idType = null;
        }
        XMLCustomWriter::setAttribute($root, 'identification', $idType, false);
        XMLCustomWriter::setAttribute($root, 'current', $issue->getCurrent() ? 'true' : 'false');
        XMLCustomWriter::setAttribute($root, 'public_id', $issue->getPubId('publisher-id'), false);
        if (is_array($issue->getTitle(null))) {
            foreach ($issue->getTitle(null) as $locale => $title) {
                $titleNode =& XMLCustomWriter::createChildWithText($doc, $root, 'title', $title, false);
                if ($titleNode) XMLCustomWriter::setAttribute($titleNode, 'locale', $locale);
                unset($titleNode);
            }
        }
        if (is_array($issue->getDescription(null))) foreach ($issue->getDescription(null) as $locale => $description) {
            $descriptionNode =& XMLCustomWriter::createChildWithText($doc, $root, 'description', $description, false);
            if ($descriptionNode) XMLCustomWriter::setAttribute($descriptionNode, 'locale', $locale);
            unset($descriptionNode);
        }
        XMLCustomWriter::createChildWithText($doc, $root, 'volume', $issue->getVolume(), false);
        XMLCustomWriter::createChildWithText($doc, $root, 'number', $issue->getNumber(), false);
        XMLCustomWriter::createChildWithText($doc, $root, 'year', $issue->getYear(), false);
        if (is_array($issue->getShowCoverPage(null))) foreach (array_keys($issue->getShowCoverPage(null)) as $locale) {
            if ($issue->getShowCoverPage($locale)) {
                $coverNode =& XMLCustomWriter::createElement($doc, 'cover');
                XMLCustomWriter::appendChild($root, $coverNode);
                XMLCustomWriter::setAttribute($coverNode, 'locale', $locale);
                XMLCustomWriter::createChildWithText($doc, $coverNode, 'caption', $issue->getCoverPageDescription($locale), false);
                $coverFile = $issue->getFileName($locale);
                if ($coverFile != '') {
                    $imageNode =& XMLCustomWriter::createElement($doc, 'image');
                    XMLCustomWriter::appendChild($coverNode, $imageNode);
                    import('classes.file.PublicFileManager');
                    $publicFileManager = new PublicFileManager();
                    $coverPagePath = $publicFileManager->getJournalFilesPath($journal->getId()) . '/';
                    $coverPagePath .= $coverFile;
                    $embedNode =& XMLCustomWriter::createChildWithText($doc, $imageNode, 'embed', base64_encode($publicFileManager->readFile($coverPagePath)));
                    XMLCustomWriter::setAttribute($embedNode, 'filename', $issue->getOriginalFileName($locale));
                    XMLCustomWriter::setAttribute($embedNode, 'encoding', 'base64');
                    XMLCustomWriter::setAttribute($embedNode, 'mime_type', String::mime_content_type($coverPagePath));
                }
                unset($coverNode);
            }
        }
        XMLCustomWriter::createChildWithText($doc, $root, 'date_published', $this->formatDate($issue->getDatePublished()), false);
        if (XMLCustomWriter::createChildWithText($doc, $root, 'access_date', $this->formatDate($issue->getOpenAccessDate()), false) == null) {
            // This may be an open access issue. Check and flag
            // as necessary.
            if ( // Issue flagged as open, or subscriptions disabled
                $issue->getAccessStatus() == ISSUE_ACCESS_OPEN ||
                $journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN
            ) {
                $accessNode =& XMLCustomWriter::createElement($doc, 'open_access');
                XMLCustomWriter::appendChild($root, $accessNode);
            }
        }
        $sectionDao =& DAORegistry::getDAO('SectionDAO');
        /*foreach ($sectionDao->getSectionsForIssue($issue->getId()) as $section) {
            $sectionNode = $this->generateSectionDom($doc, $journal, $issue, $section);
            XMLCustomWriter::appendChild($root, $sectionNode);
            unset($sectionNode);
        }*/
        return $root;
    }

    function exportIssue(&$journal, &$issue, $outputFile = null)
    {
        //$this->import('JATSExportDom');
        $doc =& XMLCustomWriter::createDocument();
        $issueNode = $this->generateIssueDom($doc, $journal, $issue);
        XMLCustomWriter::appendChild($doc, $issueNode);
        if (!empty($outputFile)) {
            if (($h = fopen($outputFile, 'wb')) === false) return false;
            fwrite($h, XMLCustomWriter::getXML($doc));
            fclose($h);
        } else {
            header("Content-Type: application/xml");
            header("Cache-Control: private");
            header("Content-Disposition: attachment; filename=\"issue-" . $issue->getId() . ".xml\"");
            XMLCustomWriter::printXML($doc);
        }
        return true;
    }

    function display(&$args, $request)
    {
        parent::display($args);
        $issueDao =& DAORegistry::getDAO('IssueDAO');
        $journal =& $request->getJournal();
        switch (array_shift($args)) {
            case 'exportIssue':
                $issueId = array_shift($args);
                $issue =& $issueDao->getIssueById($issueId, $journal->getId());
                if (!$issue) $request->redirect();
                $this->exportIssue($journal, $issue);
                break;

            default:
                // Display a list of issues for export
                $journal =& Request::getJournal();
                $issueDao =& DAORegistry::getDAO('IssueDAO');
                $issues =& $issueDao->getIssues($journal->getId(), Handler::getRangeInfo('issues'));

                $templateMgr =& TemplateManager::getManager();
                $templateMgr->assign_by_ref('issues', $issues);
                $templateMgr->display($this->getTemplatePath() . 'issues.tpl');
        }
    }

    /**
     * Execute import/export tasks using the command-line interface.
     * @param $args Parameters to the plugin
     */
    function executeCLI($scriptName, &$args)
    {
        $this->usage($scriptName);
    }

    /**
     * Display the command-line usage information
     */
    function usage($scriptName)
    {
        echo "USAGE NOT AVAILABLE.\n"
            . "This is a sample plugin and does not actually perform a function.\n";
    }
}
