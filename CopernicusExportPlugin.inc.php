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
        $issn = $journal->getSetting('printIssn');
	$issn = $issn ? $issn : $journal->getSetting('onlineIssn');

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
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'abstract', strip_tags($article->getLocalizedData('abstract', $loc)), true);

                    foreach ($articleFileDao->getArticleFilesByArticle($article->getId()) as $files) {
                        $url = 'http://' . $_SERVER['HTTP_HOST'] . pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
                        $url .= '/article/viewFile/' . $article->getId() . '/' . $files->getFileId();
                        XMLCustomWriter::createChildWithText($doc, $lang_version, 'pdfFileUrl', $url, true);
                        break;
                    }

                    $pubdate = $article->getDatePublished() ? str_replace(' ', 'T', $article->getDatePublished()) . 'Z' : "";
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'publicationDate', $pubdate, false);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageFrom', $article->getStartingPage(), true);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageTo', $article->getEndingPage(), true);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'doi', $article->getStoredPubId('doi'), true);

                    $keywords = XMLCustomWriter::createChildWithText($doc, $lang_version, 'keywords', '', true);
                    $kwds = $this->multiexplode(array(',', ';'), $article->getLocalizedData('subject', $loc));
		    $j = 0;
                    foreach ($kwds as $k) {
                        XMLCustomWriter::createChildWithText($doc, $keywords, 'keyword', $k, true);
			$j++;
                    }
		    if ($j == 0) {
                        XMLCustomWriter::createChildWithText($doc, $keywords, 'keyword', " ", true);
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
                $citation_text = $article->getData('citations');

                if ($citation_text) {
                    $citation_arr = explode("\n", $citation_text);
                    $references_elem = XMLCustomWriter::createChildWithText($doc, $article_elem, 'references', '', true);
                    $index = 1;
                    foreach ($citation_arr as $citation) {
                        if ($citation == "") continue;
                        $reference_elem = XMLCustomWriter::createChildWithText($doc, $references_elem, 'reference', '', true);
                        XMLCustomWriter::createChildWithText($doc, $reference_elem, 'unparsedContent', $citation, true);
                        XMLCustomWriter::createChildWithText($doc, $reference_elem, 'order', $index, true);
                        XMLCustomWriter::createChildWithText($doc, $reference_elem, 'doi', '', true);
                        $index++;
                    }
                }

            }
        }
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
            header("Content-Disposition: attachment; filename=\"copernicus-issue-" . $issue->getYear() . '-' . $issue->getNumber() . ".xml\"");
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
