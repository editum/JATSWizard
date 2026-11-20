<!DOCTYPE html>
<html lang="{$lang|escape}" xml:lang="{$lang|escape}">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{translate key="plugins.generic.jatsWizard.flipbook.title"}</title>
	<link rel="stylesheet" href="{$flipbookAssetsUrl|escape}flipbook.css?v={$flipbookAssetVersion|escape:'url'}">
</head>
<body>
	<main id="jv-flipbook" class="jv-flipbook" data-pdf-viewer="{$pdfJsViewerUrl|escape}">
		<header class="jv-flipbook-head">
			<h1>{translate key="plugins.generic.jatsWizard.flipbook.title"}</h1>
			<a id="jv-flipbook-source" href="flipbook-source.pdf">{translate key="plugins.generic.jatsWizard.flipbook.downloadPdf"}</a>
		</header>
		<nav class="jv-flipbook-toolbar" aria-label="{translate key="plugins.generic.jatsWizard.flipbook.controls"}">
			<button type="button" data-action="previous" aria-label="{translate key="plugins.generic.jatsWizard.flipbook.previous"}">&#8249;</button>
			<button type="button" data-action="next" aria-label="{translate key="plugins.generic.jatsWizard.flipbook.next"}">&#8250;</button>
			<label class="jv-flipbook-page-label" for="jv-flipbook-page">{translate key="plugins.generic.jatsWizard.flipbook.page"}</label>
			<input id="jv-flipbook-page" type="number" min="1" value="1" inputmode="numeric" aria-label="{translate key="plugins.generic.jatsWizard.flipbook.page"}">
			<span data-page-count aria-live="polite">/ --</span>
			<form data-search-form class="jv-flipbook-search">
				<input type="search" data-search-query minlength="2" placeholder="{translate key="plugins.generic.jatsWizard.flipbook.search"}" aria-label="{translate key="plugins.generic.jatsWizard.flipbook.search"}">
				<button type="submit">{translate key="plugins.generic.jatsWizard.flipbook.find"}</button>
			</form>
			<output data-search-status role="status" aria-live="polite"></output>
			<button type="button" data-action="search-previous" disabled aria-label="{translate key="plugins.generic.jatsWizard.flipbook.searchPrevious"}">&#9650;</button>
			<button type="button" data-action="search-next" disabled aria-label="{translate key="plugins.generic.jatsWizard.flipbook.searchNext"}">&#9660;</button>
			<button type="button" data-action="sound" aria-pressed="false" aria-label="{translate key="plugins.generic.jatsWizard.flipbook.soundOff"}" title="{translate key="plugins.generic.jatsWizard.flipbook.soundOff"}">&#9835;</button>
			<button type="button" data-action="fullscreen" aria-pressed="false" aria-label="" title="">&#x26F6;</button>
		</nav>
		<p class="jv-flipbook-status" data-status role="status" aria-live="polite">{translate key="plugins.generic.jatsWizard.flipbook.loading"}</p>
		<section class="jv-flipbook-stage" aria-label="{translate key="plugins.generic.jatsWizard.flipbook.pages"}">
			<div id="jv-flipbook-book" class="jv-flipbook-book"></div>
		</section>
	</main>
	<script src="{$flipbookAssetsUrl|escape}page-flip.browser.js?v={$flipbookAssetVersion|escape:'url'}"></script>
	<script src="{$flipbookAssetsUrl|escape}flipbook.js?v={$flipbookAssetVersion|escape:'url'}"></script>
</body>
</html>
