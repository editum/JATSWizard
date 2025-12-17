<?php

import('classes.file.PublicFileManager');

class JATSFront extends DOMDocument
{

	const DOCUMENT_PUBLICID = "-//NLM//DTD JATS (Z39.96) Journal Publishing DTD v1.1 20151215//EN";
	const DOCUMENT_SYSTEMID = "https://jats.nlm.nih.gov/publishing/1.1/JATS-journalpublishing1.dtd";
	const ARTICLE_ATTRIBUTES = [
		'dtd-version' => '1.1',
	];
	const SPECIFIC_USES = [
		'scielo' => ['specific-use' => 'sps-1.9']
	];
	/* @var $article \DOMElement */
	var $article;

	/* @var $front \DOMElement */
	var $front;

	/* @var $body \DOMElement */
	var $body;

	/* @var $back \DOMElement */
	var $back;

	var $specificUse;
	public function __construct($specificUse = null, $pathToFile = null)
	{
		parent::__construct('1.0', 'utf-8');
		$this->preserveWhiteSpace = false;
		$this->formatOutput = true;


		$this->specificUse = $specificUse;
		if ($pathToFile) {

			$this->load($pathToFile);
			$this->article = $this->getElementsByTagName('article')->item(0);
			$this->front = $this->getElementsByTagName('front')->item(0);
			$this->body = $this->getElementsByTagName('body')->item(0);
			$this->back = $this->getElementsByTagName('back')->item(0);
		} else {
			$this->setBasicStructure();
		}
	}

	public function getJatsFile(string $pathToFile)
	{
		$this->save($pathToFile);
	}

	public function loadFile(string $pathToFile)
	{
		$this->load($pathToFile);
		echo "<pre>";
		print_r($this->article);
		echo "</pre>";
		exit;
	}
	public function adjustSpecificUse()
	{
		if ($this->specificUse != 'scielo') {
			// Borrar todos los elementos <mixed-citation> que se encuentren dentro de las referencias que hay en el back
			$refs = $this->back->getElementsByTagName('ref');
			foreach ($refs as $ref) {
				$mixedCitations = $ref->getElementsByTagName('mixed-citation');
				if ($mixedCitations->length > 0 and $mixedCitations->item(0) instanceof \DOMElement)
					$ref->removeChild($mixedCitations->item(0));
			}
		}
	}
	public function ensureArticleAttributes($submission = null)
	{
		$this->article->setAttributeNS(
			"http://www.w3.org/2000/xmlns/",
			"xmlns:xlink",
			"http://www.w3.org/1999/xlink"
		);
		foreach (self::ARTICLE_ATTRIBUTES as $key => $value)
			$this->article->setAttribute($key, $value);
		if ($submission) {
			$this->article->setAttribute('xml:lang', substr($submission->getLocale(), 0, 2));
		}

		$this->article->setAttribute('article-type', 'research-article');

		$this->article->removeAttribute('specific-use');
		if ($this->specificUse && array_key_exists($this->specificUse, self::SPECIFIC_USES)) {
			foreach (self::SPECIFIC_USES[$this->specificUse] as $key => $value)
				$this->article->setAttribute($key, $value);
		}
	}
	private function setBasicStructure()
	{
		// Doctype
		$impl = new \DOMImplementation();
		$this->appendChild($impl->createDocumentType("article", self::DOCUMENT_PUBLICID, self::DOCUMENT_SYSTEMID));
		$this->article = $this->createElement('article');
		$this->ensureArticleAttributes();

		$this->appendChild($this->article);

		$this->front = $this->createElement('front');
		$this->article->appendChild($this->front);

		$this->body = $this->createElement('body');
		$this->article->appendChild($this->body);

		$this->back = $this->createElement('back');
		$this->article->appendChild($this->back);
	}
	public function setDocumentMeta(Request $request, Submission $submission)
	{
		$this->ensureArticleAttributes($submission);
		$journal = $request->getJournal();
		$site = $request->getSite();

		// Delete all nodes if exist
		while ($this->front->hasChildNodes()) {
			$this->front->removeChild($this->front->firstChild);
		}
		$journalMeta = $this->createElement("journal-meta");
		$this->front->appendChild($journalMeta);
		$journalId = $this->createElement("journal-id", $request->getRequestedJournalPath());
		$journalId->setAttribute("journal-id-type", "publisher-id");
		$journalMeta->appendChild($journalId);

		$journaltitleGroup = $this->createElement("journal-title-group");
		$journalMeta->appendChild($journaltitleGroup);

		$journalDao = DAORegistry::getDAO('JournalDAO');
		/** @var JournalDAO $journalDao */
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		/** @var SectionDAO $sectionDao */
		//$journal = $journalDao->getById($journalId);
		$journalName = $journal->getLocalizedName();
		$journalAbbreviation = $journal->getLocalizedData("abbreviation");

		$journalTitle = $this->createElement("journal-title", $journalName);
		$journalTitle->setAttribute('xml:lang', substr($submission->getLocale(), 0, 2));
		$journaltitleGroup->appendChild($journalTitle);

		foreach ($journal->getName(null) as $locale => $name) {
			if ($locale == $submission->getLocale()) continue;
			if (trim($name) === '') continue;
			$journaltranstitleGroup = $this->createElement("trans-title-group");
			$journaltranstitleGroup->setAttribute("xml:lang", substr($locale, 0, 2));
			$journaltitleGroup->appendChild($journaltranstitleGroup);
			$journaltransTitle = $this->createElement('trans-title', $name);
			$journaltranstitleGroup->appendChild($journaltransTitle);
		}

		$abbrevjournalTitle = $this->createElement("abbrev-journal-title", $journalAbbreviation); //Paco Gil: OK <abbrev-journal-title
		$abbrevjournalTitle->setAttribute("abbrev-type", "publisher");
		$journaltitleGroup->appendChild($abbrevjournalTitle);

		if ($journal->getData('printIssn')) {
			$journalMeta->appendChild($this->createElement("issn", $journal->getData('printIssn')))->setAttribute("pub-type", "ppub");
		}
		if ($journal->getData('onlineIssn')) {
			$journalMeta->appendChild($this->createElement("issn", $journal->getData('onlineIssn')))->setAttribute("pub-type", "epub");
		}
		$journalMeta->appendChild($this->createElement("publisher"))
			->appendChild($this->createElement('publisher-name', $journal->getData('publisherInstitution')));


		// Append nodes according to Texture specifications
		$articleMeta = $this->createElement("article-meta");
		$this->front->appendChild($articleMeta);

		$publication = $submission->getCurrentPublication();
		if ($publication) {
			$articleMeta->appendChild($this->createElement('article-id', $publication->getData('pub-id::doi')))
				->setAttribute('pub-id-type', 'doi');

			$categoryDao = DAORegistry::getDAO('CategoryDAO');
			$categories = $categoryDao->getByPublicationId($publication->getId())->toArray();
			if (count($categories)) {
				//TODO: only support for one category
				$articleCategories = $this->createElement('article-categories');
				$articleMeta->appendChild($articleCategories);

				$subjGroup = $this->createElement('subj-group');
				$subjGroup->appendChild($this->createElement('subject', $categories[0]->getLocalizedData('title')));
				$subjGroup->setAttribute('subj-group-type', 'heading');
				$articleCategories->appendChild($subjGroup);
			} else {
				$section = $sectionDao->getById($submission->getSectionId());
				if ($section) {
					$articleCategories = $this->createElement('article-categories');
					$articleMeta->appendChild($articleCategories);

					$subjGroup = $this->createElement('subj-group');
					$subjGroup->appendChild($this->createElement('subject', $section->getLocalizedTitle()));
					$subjGroup->setAttribute('subj-group-type', 'heading');
					$articleCategories->appendChild($subjGroup);
				}
			}
		}

		$issueDao = DAORegistry::getDAO('IssueDAO');
		/** @var IssueDAO $issueDao */
		$oaiDao = DAORegistry::getDAO('OAIDAO');
		$article = $submission->getCurrentPublication();
		$issue = $issueDao->getById($article->getData('issueId'));
		$titleGroup = $this->createElement("title-group");
		$articleMeta->appendChild($titleGroup);

		$articleTitle = $this->createElement("article-title", htmlspecialchars($submission->getLocalizedTitle()));
		$titleGroup->appendChild($articleTitle);

		if ($submission->getLocalizedSubtitle()) {
			$subtitle = $this->createElement("subtitle", htmlspecialchars($submission->getLocalizedSubtitle()));
			$titleGroup->appendChild($subtitle);
		}
		foreach ($submission->getTitle(null) as $locale => $title) {
			if ($locale == $submission->getLocale()) continue;
			if (trim($title) === '') continue;
			$transtitleGroup = $this->createElement("trans-title-group");
			$transtitleGroup->setAttribute("xml:lang", substr($locale, 0, 2));
			$titleGroup->appendChild($transtitleGroup);
			$transTitle = $this->createElement('trans-title', $title);
			$transtitleGroup->appendChild($transTitle);
			if (!empty($subtitle = $submission->getSubtitle($locale))) {
				$transSubtitle = $this->createElement('trans-subtitle', $submission->getSubtitle($locale));
				$transtitleGroup->appendChild($transSubtitle);
			}
		}

		if (!empty($submission->getAuthors())) {
			$contribGroup = $this->createElement("contrib-group");
			$contribGroup->setAttribute("content-type", "author");
			$articleMeta->appendChild($contribGroup);
			$indexLabel = 1;
			foreach ($submission->getAuthors() as $key => $author) {
				/* @var $author Author */
				$contrib = $this->createElement("contrib");
				$contrib->setAttribute("contrib-type", $author->getId() == $article->getData('primaryContactId') ? "corresp" : "author");
				$contribGroup->appendChild($contrib);

				if ($author->getOrcid()) {
					$orcid = $this->createElement("contrib-id", htmlspecialchars($author->getOrcid()));
					$orcid->setAttribute("contrib-id-type", "orcid");
					$contrib->appendChild($orcid);
				}

				$name = $this->createElement("name");
				$contrib->appendChild($name);

				if ($author->getLocalizedFamilyName()) {
					$surname = $this->createElement("surname", htmlspecialchars($author->getLocalizedFamilyName()));
					$name->appendChild($surname);
				}

				$givenNames = $this->createElement("given-names", htmlspecialchars($author->getLocalizedGivenName()));
				$name->appendChild($givenNames);

				if ($author->getEmail()) {
					$email = $this->createElement("email", htmlspecialchars($author->getEmail()));
					$contrib->appendChild($email);
				}

				$xref = $this->createElement("xref");
				$xref->setAttribute("ref-type", "aff");
				$xref->setAttribute("rid", "aff-" . ($key + 1));
				$contrib->appendChild($xref);

				$aff = $this->createElement("aff");
				$aff->setAttribute("id", "aff-" . ($key + 1));
				$articleMeta->appendChild($aff);

				$label = $this->createElement("label", (string)$indexLabel++);
				$aff->appendChild($label);
				$institution = $this->createElement("institution", htmlspecialchars($author->getLocalizedAffiliation()));
				$institution->setAttribute('content-type', 'original');
				$aff->appendChild($institution);
				$country = $this->createElement("country", htmlspecialchars($author->getCountryLocalized()));
				$country->setAttribute('country', $author->getData('country'));
				$aff->appendChild($country);
			}
		}

		if ($submission->getDatePublished()) {
			$datePublished = $this->createElement("date");
			$datePublished->setAttribute("data-type", "published");
			$dpf = new DateTime($submission->getDatePublished());
			$datePublished->setAttribute("iso-8601-date", $dpf->format("Y-m-d"));
			$pubDate = $this->createElement('pub-date');
			$pubDate->setAttribute('publication-format', 'electronic');
			$pubDate->setAttribute('date-type', 'pub');
			$articleMeta->appendChild($pubDate);
			$day = $this->createElement('day', $dpf->format("d"));
			$pubDate->appendChild($day);
			$month = $this->createElement('month', $dpf->format("m"));
			$pubDate->appendChild($month);
			$year = $this->createElement('year', $dpf->format("Y"));
			$pubDate->appendChild($year);
		}
		if (!empty($issue)) {
			$volume = $this->createElement("volume", $issue->getVolume());
			$articleMeta->appendChild($volume);

			$issueElement = $this->createElement("issue", $issue->getNumber());
			$articleMeta->appendChild($issueElement);
		}
		$firstPage = ($article->getStartingPage() == $article->getEndingPage()) ? 1 : $article->getStartingPage();
		$fpage = $this->createElement("fpage", $firstPage);
		$articleMeta->appendChild($fpage);
		$lpage = $this->createElement("lpage", $article->getEndingPage());
		$articleMeta->appendChild($lpage);

		$history = $this->createElement("history");
		$articleMeta->appendChild($history);

		$dateReceived = $this->createElement("date");
		$dateReceived->setAttribute("date-type", "received");
		$drf = new DateTime($submission->getDateSubmitted());
		$dateReceived->setAttribute("iso-8601-date", $drf->format("Y-m-d"));
		$history->appendChild($dateReceived);

		$dayReceived = $this->createElement("day", $drf->format("d"));
		$dateReceived->appendChild($dayReceived);
		$monthReceived = $this->createElement("month", $drf->format("m"));
		$dateReceived->appendChild($monthReceived);
		$yearReceived = $this->createElement("year", $drf->format("Y"));
		$dateReceived->appendChild($yearReceived);

		$permissions = $this->createElement("permissions");
		$articleMeta->appendChild($permissions);
		$license = $this->createElement("license");
		$license->setAttribute("license-type", "open-access");
		$license->setAttribute("xlink:href", "https://creativecommons.org/licenses/by/4.0/");
		$license->setAttribute("xml:lang", substr($submission->getLocale(), 0, 2));
		$permissions->appendChild($license);
		$licensep = $this->createElement("license-p", "Este es un artículo publicado en acceso abierto bajo una licencia Creative Commons");
		$license->appendChild($licensep);


		$coverImage = $issue->getCoverImage($submission->getLocale());
		if (!$coverImage) {
			// fallback a cualquier idioma disponible
			$coverImage = $issue->getCoverImage($journal->getPrimaryLocale());
		}
		if ($coverImage) {
			$publicFileManager = new PublicFileManager();
			$issueCoverUrl = $request->getBaseUrl() .'/'. $publicFileManager->getContextFilesPath($issue->getJournalId())
				. '/' . $coverImage;

			$selfUri = $this->createElement("self-uri");
			$selfUri->setAttribute("xlink:href", $issueCoverUrl);
			$selfUri->setAttribute("conten-type", "image");
			$selfUri->setAttribute("specific-use", "issue-cover");
			$articleMeta->appendChild($selfUri);			
		}

		$abstract = $this->createElement("abstract");
		$articleMeta->appendChild($abstract);
		$p = $this->createElement('p', strip_tags(html_entity_decode($submission->getAbstract($submission->getLocale()))));
		$abstract->appendChild($p);


		foreach ($submission->getAbstract(null) as $locale => $abstract) {
			if ($locale == $submission->getLocale()) continue;
			if (trim($abstract) === '') continue;
			$transAbstract = $this->createElement("trans-abstract");
			$transAbstract->setAttribute("xml:lang", substr($locale, 0, 2));
			$p = $this->createElement('p', strip_tags(html_entity_decode($abstract)));
			$transAbstract->appendChild($p);
			$articleMeta->appendChild($transAbstract);
		}


		foreach ($article->getData('keywords') as $locale => $keywords) {
			if (empty($keywords)) continue;
			$keywordGroup = $this->createElement('kwd-group');
			$keywordGroup->setAttribute("xml:lang", substr($locale, 0, 2));
			$keywordGroup->setAttribute("kwd-group-type", 'author-keywords');
			$articleMeta->appendChild($keywordGroup);
			foreach ($keywords as $keyword) {
				$keyword = $this->createElement('kwd', htmlspecialchars($keyword));
				$keywordGroup->appendChild($keyword);
			}
		}
		$articlePageCount = intval($article->getEndingPage());
		if ($articlePageCount > 0) {
			$counts = $this->createElement("counts");
			$articleMeta->appendChild($counts);
			$pageCount = $this->createElement("page-count");
			$pageCount->setAttribute("count", $articlePageCount);
			$counts->appendChild($pageCount);
		}

	}
}
