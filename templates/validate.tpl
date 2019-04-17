{**
 * plugins/importexport/copernicus/templates/validate.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * List of issues to potentially export
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.copernicus.selectIssue.long"}
{assign var="pageCrumbTitle" value="plugins.importexport.copernicus.selectIssue.short"}
{include file="common/header.tpl"}
{/strip}

<br/>

<style type="text/css">
	{literal}
	pre, cont
	{
		counter-reset: line;
		font-family: monospace;
		background-color: #fff;
		padding: 0.5em;
		border-radius: .25em;
		box-shadow: .1em .1em .5em rgba(0,0,0,.45);
		line-height: 0;
	}
	pre span
	{
		counter-increment: line;
		display: block;
		line-height: 1.5rem;
		overflow: hidden;
	}
	pre span::before
	{
		content: counter(line);
		-webkit-user-select: none;

		display: inline-block;
		border-right: 1px solid #ddd;
		padding: 0 .5em;
		margin-right: .5em;
		color: #888;
		width:2em;
		text-align: right;
	 }
	.warning
	{
		background-color: yellow;
	}
	.error
	{
		background-color: #DD4A68;
	}
	.fatal
	{
		background-color: orchid;
	}
	.ok
	{
		background-color: lightgreen;
	}
	{/literal}
</style>

<div class="cont">
	<h2>Validation results:</h2>
	{foreach from=$xml_errors item=error}
		<p>
			{if $error->level==LIBXML_ERR_WARNING}
				<b class="warning">Warning {$error->code}</b>:
			{elseif $error->level==LIBXML_ERR_ERROR}
				<b class="error">Error {$error->code}</b>:
			{elseif $error->level==LIBXML_ERR_FATAL}
				<b class="fatal">Fatal Error {$error->code}</b>:
			{/if}

			<a href="#{$error->line}">{$error->message} at line {$error->line}</a></p>
	{/foreach}

	{if count($xml_errors)==0}
		<p class="ok">Everything is ok. Xml file is valid</p>
	{/if}


</div>

<div>
	<h2>Generated XML file</h2>
<pre>
{foreach from=$xml_lines item=line key=i}
	<span id="{$i+1}">{$line}</span>
{/foreach}
</pre>
</div>

<!--
<div id="issues">
<table width="100%" class="listing">
	<tr>
		<td colspan="4" class="headseparator">&nbsp;</td>
	</tr>
	<tr class="heading" valign="bottom">
		<td width="65%">{translate key="issue.issue"}</td>
		<td width="15%">{translate key="editor.issues.published"}</td>
		<td width="15%">{translate key="editor.issues.numArticles"}</td>
		<td width="5%" align="right">{translate key="common.action"}</td>
	</tr>
	<tr>
		<td colspan="4" class="headseparator">&nbsp;</td>
	</tr>
	
	{iterate from=issues item=issue}
	<tr valign="top">
		<td><a href="{url page="issue" op="view" path=$issue->getId()}" class="action">{$issue->getIssueIdentification()|strip_unsafe_html|nl2br}</a></td>
		<td>{$issue->getDatePublished()|date_format:"$dateFormatShort"|default:"&mdash;"}</td>
		<td>{$issue->getNumArticles()|escape}</td>
		<td align="right"><a href="{plugin_url}exportIssue/{$issue->getId()}" class="pkp_button export">{translate key="common.export"}</a></td>
	</tr>
	<tr>
		<td colspan="4" class="{if $issues->eof()}end{/if}separator">&nbsp;</td>
	</tr>
{/iterate}
{if $issues->wasEmpty()}
	<tr>
		<td colspan="4" class="nodata">{translate key="issue.noIssues"}</td>
	</tr>
	<tr>
		<td colspan="4" class="endseparator">&nbsp;</td>
	</tr>
{else}
	<tr>
		<td colspan="1" align="left">{page_info iterator=$issues}</td>
		<td colspan="3" align="right">{page_links anchor="issues" name="issues" iterator=$issues}</td>
	</tr>
{/if}
</table>
</div>

-->
{include file="common/footer.tpl"}
