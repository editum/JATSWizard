{**
 * Embedded accessible reader for ePub galleys (epub.js).
 *}
<!DOCTYPE html>
<html lang="{$currentLocale|replace:"_":"-"}" xml:lang="{$currentLocale|replace:"_":"-"}">
{capture assign="pageTitleTranslated"}{translate key="article.pageTitle" title=$article->getLocalizedTitle()|escape}{/capture}
{include file="frontend/components/headerHead.tpl"}
<body class="pkp_page_{$requestedPage|escape} pkp_op_{$requestedOp|escape} jv-epub-page">
	<link rel="stylesheet" href="{$epubReaderCssUrl|escape}">

	<header class="header_view">
		{capture assign="articleUrl"}{url page="article" op="view" path=$article->getBestId()}{/capture}
		<a href="{$articleUrl}" class="return">
			<span class="pkp_screen_reader">{translate key="article.return"}</span>
		</a>
		<a href="{$articleUrl}" class="title">{$article->getLocalizedTitle()|escape}</a>
	</header>

	<main id="jv-epub-app" class="jv-epub-app" data-theme="day"
		data-epub="{$epubUrl|escape}" data-assets="{$epubAssetsUrl|escape}" data-lang="{$currentLocale|substr:0:2}"
		data-searching="{translate key="plugins.generic.jatsWizard.epub.searching"|escape}"
		data-no-results="{translate key="plugins.generic.jatsWizard.epub.noResults"|escape}"
		data-search-error="{translate key="plugins.generic.jatsWizard.epub.searchError"|escape}"
		data-results="{translate key="plugins.generic.jatsWizard.epub.results"|escape}"
		data-bookmark-empty="{translate key="plugins.generic.jatsWizard.epub.bookmarkEmpty"|escape}"
		data-bookmark-saved="{translate key="plugins.generic.jatsWizard.epub.bookmarkSaved"|escape}"
		data-note-prompt="{translate key="plugins.generic.jatsWizard.epub.notePrompt"|escape}"
		data-no-notes="{translate key="plugins.generic.jatsWizard.epub.noNotes"|escape}"
		data-storage-error="{translate key="plugins.generic.jatsWizard.epub.storageError"|escape}"
		data-stylus-active="{translate key="plugins.generic.jatsWizard.epub.stylusActive"|escape}">
		<div class="jv-epub-device">
			<div class="jv-epub-bar" role="toolbar" aria-label="{translate key="plugins.generic.jatsWizard.epub.toolbar"}">
				<span class="jv-epub-brandline"><strong>EDITUM</strong><i>revistas.um.es</i></span>
				<div class="jv-epub-actions">
					<button type="button" data-act="return-reading" aria-label="{translate key="plugins.generic.jatsWizard.epub.returnToReading"}" title="{translate key="plugins.generic.jatsWizard.epub.returnToReading"}" hidden>&#8630;</button>
				<button type="button" data-act="prev" aria-label="{translate key="plugins.generic.jatsWizard.epub.prev"}">&#8249;</button>
				<button type="button" data-act="toc-toggle" aria-expanded="false" aria-controls="jv-epub-toc" aria-label="{translate key="plugins.generic.jatsWizard.epub.contents"}">&#9776;</button>
				<button type="button" data-act="next" aria-label="{translate key="plugins.generic.jatsWizard.epub.next"}">&#8250;</button>
				<button type="button" data-act="bookmark" aria-pressed="false" aria-label="{translate key="plugins.generic.jatsWizard.epub.bookmark"}">&#9734;</button>
				<button type="button" data-act="bookmark-go" disabled aria-label="{translate key="plugins.generic.jatsWizard.epub.bookmarkGo"}">&#9875;</button>
				<button type="button" data-act="search-toggle" aria-expanded="false" aria-controls="jv-epub-search" aria-label="{translate key="plugins.generic.jatsWizard.epub.search"}">&#9906;</button>
				<button type="button" data-act="settings" aria-expanded="false" aria-controls="jv-epub-settings" aria-label="{translate key="plugins.generic.jatsWizard.epub.settings"}">Aa</button>
				<details class="jv-epub-a11y">
					<summary aria-label="{translate key="plugins.generic.jatsWizard.epub.a11yAria"}">
						<span aria-hidden="true">&#9855;</span>
						{if $daisyAceStatus == 'pass'}
							<span class="jv-epub-a11y-badge is-pass">DAISY Ace {$daisyAceVersion|escape}: {translate key="plugins.generic.jatsWizard.epub.valAcePass"}</span>
						{elseif $daisyAceStatus == 'fail'}
							<span class="jv-epub-a11y-badge is-fail">DAISY Ace {$daisyAceVersion|escape}: {translate key="plugins.generic.jatsWizard.epub.valAceFail" issues=$daisyAceIssues}</span>
						{else}
							<span class="jv-epub-a11y-badge is-unavailable">{translate key="plugins.generic.jatsWizard.epub.valUnavailable" tool="DAISY Ace"}</span>
						{/if}
					</summary>
					<div class="jv-epub-a11y-panel">
						<h2>{translate key="plugins.generic.jatsWizard.epub.a11yTitle"}</h2>
						<p>{translate key="plugins.generic.jatsWizard.epub.a11yReflow"}</p>
						<p>{translate key="plugins.generic.jatsWizard.epub.a11yNav"}</p>
						<p>{translate key="plugins.generic.jatsWizard.epub.a11yAlt"}</p>
						<p>
							{if $epubcheckStatus == 'pass'}
								{translate key="plugins.generic.jatsWizard.epub.valEpubcheckPass" version=$epubcheckVersion warnings=$epubcheckWarnings}
							{elseif $epubcheckStatus == 'fail'}
								{translate key="plugins.generic.jatsWizard.epub.valEpubcheckFail" version=$epubcheckVersion errors=$epubcheckErrors}
							{else}
								{translate key="plugins.generic.jatsWizard.epub.valUnavailable" tool="EPUBCheck"}
							{/if}
						</p>
						<p>
							{if $daisyAceStatus == 'pass'}
								{translate key="plugins.generic.jatsWizard.epub.valAcePass"} ({$daisyAceVersion|escape})
							{elseif $daisyAceStatus == 'fail'}
								{translate key="plugins.generic.jatsWizard.epub.valAceFail" issues=$daisyAceIssues} ({$daisyAceVersion|escape})
							{else}
								{translate key="plugins.generic.jatsWizard.epub.valUnavailable" tool="DAISY Ace"}
							{/if}
						</p>
						{if $epubValidationDate}
							<p>{translate key="plugins.generic.jatsWizard.epub.valDate" date=$epubValidationDate}</p>
						{/if}
						<p>{translate key="plugins.generic.jatsWizard.epub.a11yNoAudit"}</p>
					</div>
				</details>
				</div>
			</div>

			<aside id="jv-epub-toc" class="jv-epub-drawer jv-epub-toc-panel" aria-label="{translate key="plugins.generic.jatsWizard.epub.contents"}" hidden>
				<div class="jv-epub-panel-heading">
					<h2>{translate key="plugins.generic.jatsWizard.epub.contents"}</h2>
					<button type="button" data-act="toc-toggle" aria-label="{translate key="plugins.generic.jatsWizard.epub.close"}">&times;</button>
				</div>
				<nav data-toc-list aria-label="{translate key="plugins.generic.jatsWizard.epub.contents"}"></nav>
				<section class="jv-epub-notes">
					<h3>{translate key="plugins.generic.jatsWizard.epub.notes"}</h3>
					<ul data-notes-list></ul>
				</section>
			</aside>

			<section id="jv-epub-search" class="jv-epub-popover jv-epub-search" aria-label="{translate key="plugins.generic.jatsWizard.epub.search"}" hidden>
				<form data-search-form>
					<label for="jv-epub-search-input">{translate key="plugins.generic.jatsWizard.epub.search"}</label>
					<div class="jv-epub-search-form">
						<input id="jv-epub-search-input" type="search" data-search-query required minlength="2" autocomplete="off" placeholder="{translate key="plugins.generic.jatsWizard.epub.searchPlaceholder"}">
						<button type="submit">{translate key="plugins.generic.jatsWizard.epub.searchSubmit"}</button>
					</div>
				</form>
				<div class="jv-epub-search-navigation" aria-label="{translate key="plugins.generic.jatsWizard.epub.searchNavigation"}">
					<button type="button" data-act="search-prev" disabled aria-label="{translate key="plugins.generic.jatsWizard.epub.searchPrevious"}">&#9650;</button>
					<output data-search-current>0 / 0</output>
					<button type="button" data-act="search-next" disabled aria-label="{translate key="plugins.generic.jatsWizard.epub.searchNext"}">&#9660;</button>
				</div>
				<p data-search-status role="status" aria-live="polite"></p>
				<ol data-search-results></ol>
			</section>

			<section id="jv-epub-settings" class="jv-epub-popover jv-epub-settings" aria-label="{translate key="plugins.generic.jatsWizard.epub.settings"}" hidden>
				<div class="jv-epub-setting-row">
					<label for="jv-epub-font">{translate key="plugins.generic.jatsWizard.epub.font"}</label>
					<select id="jv-epub-font" data-act="font">
						<option value="literata">{translate key="plugins.generic.jatsWizard.epub.fontLiterata"}</option>
						<option value="bookerly">{translate key="plugins.generic.jatsWizard.epub.fontBookerly"}</option>
						<option value="serif">{translate key="plugins.generic.jatsWizard.epub.fontSerif"}</option>
						<option value="sans-serif">{translate key="plugins.generic.jatsWizard.epub.fontSans"}</option>
						<option value="monospace">{translate key="plugins.generic.jatsWizard.epub.fontMono"}</option>
					</select>
				</div>
				<div class="jv-epub-setting-row">
					<span>{translate key="plugins.generic.jatsWizard.epub.fontSize"}</span>
					<div class="jv-epub-font-size">
						<button type="button" data-act="smaller" aria-label="{translate key="plugins.generic.jatsWizard.epub.smaller"}">A&minus;</button>
						<output data-font-size>110%</output>
						<button type="button" data-act="larger" aria-label="{translate key="plugins.generic.jatsWizard.epub.larger"}">A+</button>
					</div>
				</div>
				<div class="jv-epub-setting-row">
					<span>{translate key="plugins.generic.jatsWizard.epub.theme"}</span>
					<div class="jv-epub-themes" role="group" aria-label="{translate key="plugins.generic.jatsWizard.epub.theme"}">
						<button type="button" data-act="theme" data-theme="day" aria-label="{translate key="plugins.generic.jatsWizard.epub.themeDay"}"></button>
						<button type="button" data-act="theme" data-theme="sepia" aria-label="{translate key="plugins.generic.jatsWizard.epub.themeSepia"}"></button>
						<button type="button" data-act="theme" data-theme="night" aria-label="{translate key="plugins.generic.jatsWizard.epub.themeNight"}"></button>
						<button type="button" data-act="theme" data-theme="white" aria-label="{translate key="plugins.generic.jatsWizard.epub.themeWhite"}"></button>
					</div>
				</div>
				<div class="jv-epub-setting-row">
					<label for="jv-epub-highlight-color">{translate key="plugins.generic.jatsWizard.epub.highlightColor"}</label>
					<select id="jv-epub-highlight-color" data-highlight-color>
						<option value="yellow">{translate key="plugins.generic.jatsWizard.epub.highlightYellow"}</option>
						<option value="pink">{translate key="plugins.generic.jatsWizard.epub.highlightPink"}</option>
						<option value="blue">{translate key="plugins.generic.jatsWizard.epub.highlightBlue"}</option>
						<option value="mint">{translate key="plugins.generic.jatsWizard.epub.highlightMint"}</option>
					</select>
				</div>
				<label class="jv-epub-toggle"><input type="checkbox" data-act="stylus"> {translate key="plugins.generic.jatsWizard.epub.stylus"}</label>
				<div class="jv-epub-setting-actions">
					<button type="button" data-act="flow" aria-pressed="false" aria-label="{translate key="plugins.generic.jatsWizard.epub.flow"}">&#9783; {translate key="plugins.generic.jatsWizard.epub.flow"}</button>
					<a class="jv-epub-dl" href="{$epubUrl|escape}" download aria-label="{translate key="plugins.generic.jatsWizard.epub.downloadAria"}">{translate key="plugins.generic.jatsWizard.epub.download"}</a>
				</div>
			</section>

			<div class="jv-epub-bezel">
				<div class="jv-epub-reading-area">
					<div id="jv-epub-view" class="jv-epub-view" tabindex="0" aria-label="{translate key="submission.representationOfTitle" representation=$galley->getLabel() title=$galleyPublication->getLocalizedFullTitle()|escape}">
						<p class="jv-epub-msg" role="status">{translate key="plugins.generic.jatsWizard.epub.loading"}</p>
					</div>
					<div class="jv-epub-page-turn" aria-hidden="true"></div>
				</div>
				<footer class="jv-epub-footer">
					<span data-progress-label>0%</span>
					<progress data-progress max="100" value="0" aria-label="{translate key="plugins.generic.jatsWizard.epub.progress"}"></progress>
				</footer>
			</div>
			<div class="jv-epub-chin">
				<button type="button" class="jv-epub-physical-btn" data-act="prev" aria-label="{translate key="plugins.generic.jatsWizard.epub.prev"}">&#8249;</button>
				<img class="jv-epub-logo" src="{$epubLogoUrl|escape}" alt="Universidad de Murcia">
				<button type="button" class="jv-epub-physical-btn" data-act="next" aria-label="{translate key="plugins.generic.jatsWizard.epub.next"}">&#8250;</button>
			</div>
			<div class="jv-epub-selection-menu" role="toolbar" aria-label="{translate key="plugins.generic.jatsWizard.epub.selectionActions"}" hidden>
				<button type="button" data-act="highlight" data-color="yellow" aria-label="{translate key="plugins.generic.jatsWizard.epub.highlightYellow"}">&#9632;</button>
				<button type="button" data-act="highlight" data-color="pink" aria-label="{translate key="plugins.generic.jatsWizard.epub.highlightPink"}">&#9632;</button>
				<button type="button" data-act="highlight" data-color="blue" aria-label="{translate key="plugins.generic.jatsWizard.epub.highlightBlue"}">&#9632;</button>
				<button type="button" data-act="note" aria-label="{translate key="plugins.generic.jatsWizard.epub.addNote"}">&#9998;</button>
				<a data-lookup="rae" target="_blank" rel="noopener noreferrer">{translate key="plugins.generic.jatsWizard.epub.lookupRae"}</a>
				<a data-lookup="wikipedia" target="_blank" rel="noopener noreferrer">{translate key="plugins.generic.jatsWizard.epub.lookupWikipedia"}</a>
			</div>
		</div>
		<noscript><p class="jv-epub-msg"><a href="{$epubUrl|escape}">{translate key="plugins.generic.jatsWizard.epub.downloadAria"}</a></p></noscript>
	</main>
	<script src="{$epubAssetsUrl}jszip.min.js"></script>
	<script src="{$epubAssetsUrl}epub.min.js"></script>
	<script src="{$epubReaderJsUrl|escape}"></script>
	{call_hook name="Templates::Common::Footer::PageFooter"}
</body>
</html>
