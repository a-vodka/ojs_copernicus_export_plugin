<?php

/**
 * @file plugins/importexport/copernicus/CopernicusExportPlugin.inc.php
 *
 * Copyright (c) 2018 Oleksii Vodka
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopernicusExportPlugin
 * @ingroup plugins_importexport_copernicus
 *
 * @brief Copernicus import/export plugin
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');
import('lib.pkp.classes.xml.XMLCustomWriter');


class CopernicusExportPlugin extends ImportExportPlugin
{
    /**
     * Called as a plugin is registered to the registry
     * @param $category String Name of category plugin was registered to
     * @return boolean True iff plugin initialized successfully; if false,
     *    the plugin will not be registered.
     */
    function register($category, $path, $mainContextId = NULL)
    {
        $success = parent::register($category, $path, $mainContextId);
        // Additional registration / initialization code
        // should go here. For example, load additional locale data:
        $this->addLocaleData();
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);
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

    function formatXml($simpleXMLElement)
    {
        $xmlDocument = new DOMDocument('1.0');
        $xmlDocument->preserveWhiteSpace = false;
        $xmlDocument->formatOutput = true;
        $xmlDocument->loadXML($simpleXMLElement->saveXML());

        return $xmlDocument->saveXML();
    }

    function &generateIssueDom(&$doc, &$journal, &$issue)
    {
        $issn = $journal->getSetting('printIssn');
        $issn = $issn ? $issn : $journal->getSetting('onlineIssn');

        $jpath = $journal->_data['path'];

        $root =& XMLCustomWriter::createElement($doc, 'ici-import');
        XMLCustomWriter::setAttribute($root, "xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
        XMLCustomWriter::setAttribute($root, "xsi:noNamespaceSchemaLocation", "https://journals.indexcopernicus.com/ic-import.xsd");


        $journal_elem = XMLCustomWriter::createChildWithText($doc, $root, 'journal', '', true);
        XMLCustomWriter::setAttribute($journal_elem, 'issn', $issn);

        $issue_elem = XMLCustomWriter::createChildWithText($doc, $root, 'issue', '', true);

        $pub_issue_date = $issue->getDatePublished() ? str_replace(' ', "T", $issue->getDatePublished()) . 'Z' : '';


        XMLCustomWriter::setAttribute($issue_elem, 'number', $issue->getNumber());
        XMLCustomWriter::setAttribute($issue_elem, 'volume', $issue->getVolume());
        XMLCustomWriter::setAttribute($issue_elem, 'year', $issue->getYear());
        XMLCustomWriter::setAttribute($issue_elem, 'publicationDate', $pub_issue_date, false);

        $sectionDao =& DAORegistry::getDAO('SectionDAO');
        $publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
        $articleFileDao =& DAORegistry::getDAO('ArticleGalleyDAO');
        $submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
        $num_articles = 0;
        foreach ($sectionDao->getByIssueId($issue->getId()) as $section) {

            foreach ($publishedArticleDao->getPublishedArticlesBySectionId($section->getId(), $issue->getId()) as $article) {

                if (!$article->getStartingPage()) continue;

                $locales = array_keys($article->_data['title']);
                $article_elem = XMLCustomWriter::createChildWithText($doc, $issue_elem, 'article', '', true);
                XMLCustomWriter::createChildWithText($doc, $article_elem, 'type', 'ORIGINAL_ARTICLE');
                foreach ($locales as $loc) {
                    $lc = explode('_', $loc);
                    $lang_version = XMLCustomWriter::createChildWithText($doc, $article_elem, 'languageVersion', '', true);
                    XMLCustomWriter::setAttribute($lang_version, 'language', $lc[0]);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'title', $article->getLocalizedTitle($loc), true);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'abstract', strip_tags($article->getLocalizedData('abstract', $loc)), true);


                    if (is_a($article, 'PublishedArticle')) {
                        foreach ($article->getGalleys() as $galley) {
                            $url = Request::url($journal->getPath()) . '/article/download/' . $article->getBestArticleId() . '/' . $galley->getBestGalleyId();
                            break;
                        }
                        XMLCustomWriter::createChildWithText($doc, $lang_version, 'pdfFileUrl', $url, true);
                    }

                    $publicationDate = $article->getDatePublished() ? str_replace(' ', "T", $article->getDatePublished()) . 'Z' : '';

                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'publicationDate', $publicationDate, false);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageFrom', $article->getStartingPage(), true);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageTo', $article->getEndingPage(), true);
                    XMLCustomWriter::createChildWithText($doc, $lang_version, 'doi', $article->getStoredPubId('doi'), true);

                    $keywords = XMLCustomWriter::createChildWithText($doc, $lang_version, 'keywords', '', true);

                    $kwds = $submissionKeywordDao->getKeywords($article->getId(), array($loc));
                    $kwds = $kwds[$loc];
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

                    $author_FirstName = '';
                    $author_MiddleName = '';
                    $author_LastName = '';

                    if (method_exists($author, "getLocalizedFirstName")) { # for ojs multilang by litvinovg https://github.com/litvinovg/ojs/tree/ojs-3.1.1-multilanguage
                        $author_FirstName = $author->getLocalizedFirstName();
                        $author_MiddleName = $author->getLocalizedMiddleName();
                        $author_LastName = $author->getLocalizedLastName();
                    } elseif (method_exists($author, "getLocalizedGivenName")) { # for ojs >= 3.1.2
                        $author_FirstName = $author->getLocalizedGivenName();
                        $author_MiddleName = '';
                        $author_LastName = $author->getLocalizedFamilyName();
                    } else { # for 3.0.0 < ojs < 3.1.2
                        $author_FirstName = $author->getFirstName();
                        $author_MiddleName = $author->getMiddleName();
                        $author_LastName = $author->getLastName();
                    }


                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'name', $author_FirstName, true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'name2', $author_MiddleName, false);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'surname', $author_LastName, true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'email', $author->getEmail(), false);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'order', $index, true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'instituteAffiliation', substr($author->getLocalizedAffiliation(), 0, 250), false);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'role', 'AUTHOR', true);
                    XMLCustomWriter::createChildWithText($doc, $author_elem, 'ORCID', $author->getData('orcid'), false);

                    $index++;
                }
                if (method_exists($article,getLocalizedCitations))
                    $citation_text = $article->getLocalizedCitations();
                else
                    $citation_text = $article->getCitations();

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
                $num_articles++;
            }
        }
        XMLCustomWriter::setAttribute($issue_elem, 'numberOfArticles', $num_articles, false);
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
            header("Content-Disposition: attachment; filename=\"copernicus-issue-" . $journal->getLocalizedAcronym() . '-' . $issue->getYear() . '-' . $issue->getNumber() . ".xml\"");
            echo $this->formatXml($doc);
        }
        return true;
    }


    function display($args, $request)
    {
        parent::display($args, $request);
        $issueDao =& DAORegistry::getDAO('IssueDAO');
        $journal =& $request->getJournal();
        switch (array_shift($args)) {
            case 'exportIssue':
                $issueId = array_shift($args);
                $issue = $issueDao->getById($issueId, $journal->getId());
                if (!$issue) $request->redirect();
                $this->exportIssue($journal, $issue);
                break;

            case 'validateIssue':

                $issueId = array_shift($args);
                $issue = $issueDao->getById($issueId, $journal->getId());
                if (!$issue) $request->redirect();

                $doc =& XMLCustomWriter::createDocument();

                $issueNode = $this->generateIssueDom($doc, $journal, $issue);
                XMLCustomWriter::appendChild($doc, $issueNode);

                $xmlDocument = new DOMDocument('1.0');
                $xmlDocument->preserveWhiteSpace = false;
                $xmlDocument->formatOutput = true;
                $xmlDocument->loadXML($doc->saveXML());
                $xmlDocument->loadXML($xmlDocument->saveXML());

                // Enable user error handling
                libxml_use_internal_errors(true);

                $xmlDocument->schemaValidate($this->getPluginPath().'/ic-import.xsd');
                $xml_lines = explode("\n", htmlentities($xmlDocument->saveXML()));
                $xml_errors = libxml_get_errors();
                libxml_clear_errors();

                $templateMgr = TemplateManager::getManager($request);

                if (method_exists($this, "getTemplateResource")) { # for ojs >= 3.1.2
                    $templateMgr->assignByRef('xml_lines', $xml_lines);
                    $templateMgr->assignByRef('xml_errors', $xml_errors);

                    $templateMgr->display($this->getTemplateResource('validate.tpl'));
                } else { #for ojs < 3.1.2
                    $templateMgr->assign_by_ref('xml_lines', $xml_lines);
                    $templateMgr->assign_by_ref('xml_errors', $xml_errors);

                    $templateMgr->display($this->getTemplatePath() . '/templates/validate.tpl');
                }

                break;

            default:
                // Display a list of issues for export

                $journal =& Request::getJournal();
                $issueDao =& DAORegistry::getDAO('IssueDAO');
                $issues = $issueDao->getIssues($journal->getId(), Handler::getRangeInfo($request, 'issues'));

                $templateMgr = TemplateManager::getManager($request);

                if (method_exists($this, "getTemplateResource")) { # for ojs >= 3.1.2
                    $templateMgr->assignByRef('issues', $issues);
                    $templateMgr->display($this->getTemplateResource('issues.tpl'));
                } else { #for ojs ojs < 3.1.2
                    $templateMgr->assign_by_ref('issues', $issues);
                    $templateMgr->display($this->getTemplatePath() . '/templates/issues.tpl');
                }
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
