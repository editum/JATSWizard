<?php

/**
 * Converts a JATS-XML article into a static, self-contained HTML package:
 *
 *   <outDir>/index.html
 *   <outDir>/images/<figure files>
 *
 * Images referenced by <graphic>/<inline-graphic> are copied to ./images/ and
 * the references are rewritten to relative paths. The transformation uses the
 * NCBI JATS Preview XSLT (public domain) through PHP's XSLTProcessor.
 */
require_once __DIR__ . '/JatsEpubBuilder.inc.php';

class JatsHtmlExporter
{
    const XLINK_NS = 'http://www.w3.org/1999/xlink';
    const IMAGES_DIR = 'images';

    /** @var string */
    private $xsltPath;
    /** @var string */
    private $cssPath;
    /** @var string */
    private $viewerCssPath;
    /** @var string */
    private $viewerJsPath;
    /** @var string[] */
    private $warnings = [];

    /**
     * @param string $assetsDir Absolute path to the plugin "assets/xslt" directory
     */
    public function __construct($assetsDir)
    {
        $this->xsltPath = rtrim($assetsDir, '/\\') . '/main/jats-html.xsl';
        $this->cssPath = rtrim($assetsDir, '/\\') . '/jats-preview.css';
        $viewerDir = dirname(rtrim($assetsDir, '/\\')) . '/viewer';
        $this->viewerCssPath = $viewerDir . '/viewer.css';
        $this->viewerJsPath = $viewerDir . '/viewer.js';
        foreach (['xsl', 'dom'] as $ext) {
            if (!extension_loaded($ext)) {
                throw new Exception("PHP extension '$ext' is required to export HTML.");
            }
        }
        if (!is_file($this->xsltPath)) {
            throw new Exception('JATS stylesheet not found: ' . $this->xsltPath);
        }
    }

    /** @return string[] Non-fatal problems found during the last export */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * @param string $xmlPath   JATS XML file
     * @param string $assetsSrc Directory where the images referenced by the XML live
     * @param string $outDir    Existing, writable output directory
     * @param string $htmlName  Name of the HTML file to create
     * @return array ['html' => path, 'images' => [absolute paths]]
     */
    public function export($xmlPath, $assetsSrc, $outDir, $htmlName = 'index.html', $lang = null, $epubName = null)
    {
        $this->warnings = [];
        if (!is_file($xmlPath)) {
            throw new Exception('JATS XML not found: ' . $xmlPath);
        }
        $assetsSrc = realpath($assetsSrc);
        if ($assetsSrc === false || !is_dir($outDir)) {
            throw new Exception('Invalid source or output directory.');
        }

        $xml = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $xml->load($xmlPath, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new Exception('The JATS XML is not well formed.');
        }

        $images = $this->localizeImages($xml, $assetsSrc, $outDir);

        $epubPath = null;
        if ($epubName) {
            try {
                $epubPath = rtrim($outDir, '/\\') . '/' . $epubName;
                $builder = new JatsEpubBuilder();
                $builder->build($xml, $outDir, $epubPath, $lang, $this);
                $this->warnings = array_merge($this->warnings, $builder->getWarnings());
            } catch (Exception $e) {
                $this->warnings[] = 'ePub could not be built: ' . $e->getMessage();
                $epubPath = null;
            }
        }

        $xsl = new DOMDocument();
        $xsl->load($this->xsltPath, LIBXML_NONET);
        $processor = new XSLTProcessor();
        $processor->importStylesheet($xsl);
        $processor->setParameter('', 'css', '');
        $html = $processor->transformToDoc($xml);
        if (!$html) {
            throw new Exception('The XSLT transformation failed.');
        }
        $this->replaceFront($html, $xml);
        $this->formatReferences($html, $xml);

        $citation = $this->extractCitationMeta($xml);
        if ($epubPath) {
            $citation[] = ['jv-epub', '@@JV_EPUB_URL@@'];
            $citation[] = ['jv-assets', '@@JV_ASSETS_URL@@'];
        }
        $htmlString = $this->finalizeHtml($html, $lang, $citation);
        $htmlPath = rtrim($outDir, '/\\') . '/' . $htmlName;
        if (file_put_contents($htmlPath, $htmlString) === false) {
            throw new Exception('Could not write ' . $htmlPath);
        }

        return ['html' => $htmlPath, 'images' => $images, 'epub' => $epubPath];
    }

    /**
     * Builds a ZIP with the HTML at the root and the images folder next to it.
     * @param string $outDir Directory produced by export()
     * @param string $zipPath
     */
    public function zip($outDir, $zipPath)
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Could not create the ZIP file.');
        }
        $root = realpath($outDir);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            $relative = str_replace('\\', '/', substr($file->getRealPath(), strlen($root) + 1));
            $zip->addFile($file->getRealPath(), $relative);
        }
        if (!$zip->close()) {
            throw new Exception('Could not save the ZIP file.');
        }
    }

    /**
     * Copies every referenced local image to <outDir>/images and rewrites the
     * xlink:href attribute to "images/<name>".
     * @return string[] Absolute paths of the copied images
     */
    private function localizeImages(DOMDocument $xml, $assetsSrc, $outDir)
    {
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('xlink', self::XLINK_NS);
        $nodes = $xpath->query('//graphic[@xlink:href] | //inline-graphic[@xlink:href]');

        $imagesDir = rtrim($outDir, '/\\') . '/' . self::IMAGES_DIR;
        if (!is_dir($imagesDir) && !mkdir($imagesDir, 0777, true)) {
            throw new Exception('Could not create ' . $imagesDir);
        }

        $copied = [];
        $mapping = [];
        foreach ($nodes as $node) {
            $href = trim($node->getAttributeNS(self::XLINK_NS, 'href'));
            if ($href === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
                // Remote or data: URIs are left untouched
                continue;
            }
            if (!isset($mapping[$href])) {
                $mapping[$href] = $this->copyImage($href, $assetsSrc, $imagesDir, $copied);
            }
            if ($mapping[$href] !== null) {
                $node->setAttributeNS(self::XLINK_NS, 'xlink:href', self::IMAGES_DIR . '/' . $mapping[$href]);
            }
        }
        return array_values($copied);
    }

    private function copyImage($href, $assetsSrc, $imagesDir, array &$copied)
    {
        $candidates = [$assetsSrc . '/' . $href, $assetsSrc . '/' . basename($href)];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            // The image must exist and stay inside the source directory
            if ($real === false || !is_file($real) || strpos($real, $assetsSrc) !== 0) {
                continue;
            }
            $name = $this->uniqueName($imagesDir, $real);
            if (!copy($real, $imagesDir . '/' . $name)) {
                throw new Exception('Could not copy image ' . $href);
            }
            $copied[$name] = $imagesDir . '/' . $name;
            return $name;
        }
        $this->warnings[] = "Image not found: $href";
        return null;
    }

    private function uniqueName($dir, $sourcePath)
    {
        $info = pathinfo($sourcePath);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $info['filename']);
        $ext = isset($info['extension']) ? '.' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', $info['extension'])) : '';
        $name = $base . $ext;
        for ($i = 2; file_exists($dir . '/' . $name); $i++) {
            if (md5_file($dir . '/' . $name) === md5_file($sourcePath)) {
                break;
            }
            $name = $base . '-' . $i . $ext;
        }
        return $name;
    }

    /**
     * Replaces the generic NCBI metadata dump with an article "ficha" that
     * mirrors the Javeriana viewer: authors (affiliation, country, e-mail,
     * ORCID), licence, dates, DOI, funding, and abstracts with keywords per language.
     */
    private function replaceFront(DOMDocument $html, DOMDocument $xml)
    {
        $hx = new DOMXPath($html);
        $front = $hx->query('//*[@id="article-front"]')->item(0);
        if (!$front) {
            return;
        }
        $xp = new DOMXPath($xml);
        $xp->registerNamespace('xlink', self::XLINK_NS);
        $clean = function ($s) {
            return trim(preg_replace('/\s+/u', ' ', $s));
        };
        $str = function ($q, $ctx = null) use ($xp, $clean) {
            return $clean($xp->evaluate('string(' . $q . ')', $ctx ?: $xp->document->documentElement));
        };
        $root = $xml->documentElement;
        $artLang = strtolower(substr($root->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang') ?: 'es', 0, 2));

        $L = [
            'es' => ['abs' => 'Resumen', 'kw' => 'Palabras clave', 'rec' => 'Recibido', 'acc' => 'Aceptado', 'pub' => 'Publicado', 'corr' => 'Autor/a de correspondencia', 'fund' => 'Financiación', 'months' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']],
            'en' => ['abs' => 'Abstract', 'kw' => 'Keywords', 'rec' => 'Received', 'acc' => 'Accepted', 'pub' => 'Published', 'corr' => 'Corresponding author', 'fund' => 'Funding', 'months' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']],
            'pt' => ['abs' => 'Resumo', 'kw' => 'Palavras-chave', 'rec' => 'Recebido', 'acc' => 'Aceito', 'pub' => 'Publicado', 'corr' => 'Autor/a para correspondência', 'fund' => 'Financiamento', 'months' => ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro']],
        ];
        $lab = function ($lang) use ($L) {
            return $L[substr(strtolower($lang), 0, 2)] ?? $L['en'];
        };
        $main = $lab($artLang);
        $date = function ($node) use ($xp, $clean, $main) {
            if (!$node) {
                return '';
            }
            $d = $clean($xp->evaluate('string(day)', $node));
            $m = (int) $xp->evaluate('string(month)', $node);
            $y = $clean($xp->evaluate('string(year)', $node));
            return trim(($d !== '' ? (int) $d . ' ' : '') . ($m >= 1 && $m <= 12 ? $main['months'][$m - 1] . ' ' : '') . $y);
        };

        $mk = function ($parent, $tag, array $attrs = [], $text = null) use ($html) {
            $e = $html->createElement($tag);
            foreach ($attrs as $k => $v) {
                $e->setAttribute($k, $v);
            }
            if ($text !== null && $text !== '') {
                $e->appendChild($html->createTextNode($text));
            }
            $parent->appendChild($e);
            return $e;
        };

        $new = $html->createElement('div');
        $new->setAttribute('id', 'article-front');
        $new->setAttribute('class', 'front jv-front');
        $about = $mk($new, 'div', ['id' => 'articulo-acerca']);
        $general = $mk($about, 'div', ['id' => 'ficha-general']);

        // Subtitle and translated titles
        $mainTitle = $str('//article-meta/title-group/article-title');
        $sub = $str('//article-meta/title-group/subtitle');
        if ($sub !== '') {
            $mk($general, 'p', ['class' => 'articulo-subtitulo'], $sub);
        }
        foreach ($xp->query('//article-meta/title-group/trans-title-group') as $tg) {
            $t = $str('trans-title', $tg);
            $s = $str('trans-subtitle', $tg);
            if ($t === '' || ($t === $mainTitle && ($s === '' || $s === $sub))) {
                continue;
            }
            $mk($general, 'p', ['class' => 'articulo-titulo-traduccion'], $t . ($s !== '' ? ': ' . $s : ''));
        }

        // Authors
        $authors = $mk($general, 'div', ['id' => 'autores-articulo']);
        $affs = [];
        foreach ($xp->query('//article-meta/aff') as $a) {
            $affs[$a->getAttribute('id')] = $a;
        }
        $corrIndex = 0;
        foreach ($xp->query('//article-meta/contrib-group/contrib[@contrib-type="author"]') as $c) {
            $box = $mk($authors, 'div', ['class' => 'autor']);
            $p = $mk($box, 'p', ['class' => 'nombre-completo espacio-p']);
            $given = $str('name/given-names', $c);
            $sur = $str('name/surname', $c);
            if ($given !== '') {
                $mk($p, 'span', ['class' => 'nombre'], $given);
                $p->appendChild($html->createTextNode(' '));
            }
            if ($sur !== '') {
                $mk($p, 'span', ['class' => 'apellidos'], $sur);
            }
            $orcid = $str('contrib-id[@contrib-id-type="orcid"]', $c);
            if ($orcid !== '') {
                $p->appendChild($html->createTextNode(' '));
                $mk($p, 'span', ['class' => 'orcid'], preg_match('#^https?:#i', $orcid) ? $orcid : 'https://orcid.org/' . $orcid);
            }
            if ($c->getAttribute('corresp') === 'yes') {
                $corrIndex++;
                $sup = $mk($p, 'sup');
                $mk($sup, 'a', ['href' => '#corresp' . $corrIndex], chr(96 + $corrIndex));
            }
            $mail = $str('email', $c);
            if ($mail !== '') {
                $p->appendChild($html->createTextNode(' '));
                $mk($p, 'span', ['class' => 'email'], $mail);
            }
            foreach ($xp->query('xref[@ref-type="aff"]', $c) as $x) {
                $a = $affs[$x->getAttribute('rid')] ?? null;
                if (!$a) {
                    continue;
                }
                $inst = $str('institution[not(@content-type="orgdiv1")][1]', $a) ?: $clean($a->textContent);
                $line = $mk($box, 'p', ['class' => 'espacio-p']);
                $mk($line, 'span', ['class' => 'institucion'], $inst);
                $country = $str('country', $a);
                if ($country !== '') {
                    $line->appendChild($html->createTextNode(', '));
                    $mk($line, 'span', ['class' => 'pais'], $country);
                }
            }
        }

        // Licence
        $lic = $xp->query('//article-meta/permissions/license')->item(0);
        if ($lic) {
            $href = $lic->getAttributeNS(self::XLINK_NS, 'href');
            $txt = $str('license-p', $lic);
            if ($txt !== '' || $href !== '') {
                $box = $mk($general, 'div', ['class' => 'creative']);
                if ($href !== '') {
                    $mk($box, 'a', ['href' => $href, 'target' => '_blank', 'rel' => 'noopener'], $txt !== '' ? $txt : $href);
                } else {
                    $box->appendChild($html->createTextNode($txt));
                }
            }
        }

        // Dates
        $dates = $mk($general, 'div', ['class' => 'fechas-h']);
        $addDate = function ($class, $label, $node) use ($mk, $dates, $date, $html) {
            $v = $date($node);
            if ($v === '') {
                return;
            }
            $p = $mk($dates, 'p', ['class' => $class]);
            $mk($p, 'span', ['class' => 'word-black'], $label . ':');
            $p->appendChild($html->createTextNode(' ' . $v));
        };
        $addDate('recepcion', $main['rec'], $xp->query('//article-meta/history/date[@date-type="received"]')->item(0));
        $addDate('aprobacion', $main['acc'], $xp->query('//article-meta/history/date[@date-type="accepted"]')->item(0));
        $addDate('publicacion', $main['pub'], $xp->query('//article-meta/pub-date[@date-type="pub" or not(@date-type)][1]')->item(0));

        // DOI
        $doi = $str('//article-meta/article-id[@pub-id-type="doi"]');
        if ($doi !== '') {
            $p = $mk($mk($general, 'div', ['class' => 'url-r']), 'p');
            $mk($p, 'span', ['class' => 'doi'], 'DOI: ');
            $url = preg_match('#^https?:#i', $doi) ? $doi : 'https://doi.org/' . $doi;
            $mk($p, 'a', ['class' => 'doi-value', 'href' => $url, 'target' => '_blank', 'rel' => 'noopener'], $url);
        }

        // Funding
        $funds = [];
        foreach ($xp->query('//article-meta/funding-group/award-group') as $g) {
            $funds[] = $clean($g->textContent);
        }
        if (!$funds) {
            $st = $str('//article-meta/funding-group/funding-statement');
            if ($st !== '') {
                $funds[] = $st;
            }
        }
        if ($funds) {
            $p = $mk($mk($general, 'div', ['class' => 'financiamientos-r']), 'p');
            $mk($p, 'span', ['class' => 'word-black'], $main['fund'] . ': ');
            $p->appendChild($html->createTextNode(implode('; ', $funds)));
        }

        // Abstracts and keywords, one block per language
        $abstractsBox = $mk($about, 'div', ['id' => 'resumen-bloque']);
        $kwByLang = [];
        foreach ($xp->query('//article-meta/kwd-group') as $g) {
            $lg = strtolower(substr($g->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang') ?: $artLang, 0, 2));
            $items = [];
            foreach ($xp->query('kwd', $g) as $k) {
                $items[] = $clean($k->textContent);
            }
            $kwByLang[$lg] = ($kwByLang[$lg] ?? []);
            $kwByLang[$lg] = array_merge($kwByLang[$lg], $items);
        }
        $done = [];
        foreach ($xp->query('//article-meta/abstract | //article-meta/trans-abstract') as $ab) {
            $lg = strtolower(substr($ab->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang') ?: $artLang, 0, 2));
            if (isset($done[$lg])) {
                continue;
            }
            $done[$lg] = true;
            $l = $lab($lg);
            $block = $mk($abstractsBox, 'div', ['class' => 'jv-abs', 'lang' => $lg]);
            $head = $mk($block, 'p', ['class' => 'articulo-resumen']);
            $mk($head, 'span', ['class' => 'resumen negrita callout-title'], $l['abs'] . ':');
            $paras = [];
            foreach ($xp->query('.//p', $ab) as $pp) {
                $paras[] = $clean($pp->textContent);
            }
            if (!$paras) {
                $paras[] = $clean($ab->textContent);
            }
            $mk($block, 'p', ['class' => 'capital'], implode("\n", array_filter($paras)));
            if (!empty($kwByLang[$lg])) {
                $kp = $mk($block, 'p', ['class' => 'articulo-palabras']);
                $mk($kp, 'span', ['class' => 'negrita'], $l['kw'] . ': ');
                $kp->appendChild($html->createTextNode(implode(', ', $kwByLang[$lg]) . '.'));
            }
        }

        $front->parentNode->replaceChild($new, $front);

        // Drop the NISO "Article Information (continued)" footer and credit the viewer instead
        $footer = $hx->query('//*[@id="article-footer"]')->item(0);
        if ($footer) {
            $credit = $html->createElement('div');
            $credit->setAttribute('id', 'article-footer');
            $credit->setAttribute('class', 'footer jv-credit');
            $credit->appendChild($html->createTextNode('Visor desarrollado por pa'));
            $k = $html->createElement('span');
            $k->setAttribute('class', 'k');
            $k->setAttribute('aria-label', 'K');
            $k->appendChild($html->createTextNode('K'));
            $credit->appendChild($k);
            $credit->appendChild($html->createTextNode('ojil'));
            $footer->parentNode->replaceChild($credit, $footer);
        }
    }

    /**
     * The NCBI stylesheet prints element-citation children as a bare run of
     * text. Rebuilds each one as a readable reference
     * (Authors (year). Title. Source, vol(issue), pages. DOI/URL).
     * Entries with mixed-citation are left as the author wrote them.
     */
    private function formatReferences(DOMDocument $html, DOMDocument $xml)
    {
        $xp = new DOMXPath($xml);
        $xp->registerNamespace('xlink', self::XLINK_NS);
        $hx = new DOMXPath($html);
        $clean = function ($s) {
            return trim(preg_replace('/\s+/u', ' ', $s));
        };
        foreach ($xp->query('//ref-list/ref[element-citation and not(mixed-citation)]') as $ref) {
            $id = $ref->getAttribute('id');
            $cit = $xp->query('element-citation', $ref)->item(0);
            if ($id === '' || !$cit) {
                continue;
            }
            $anchor = $hx->query('//a[@id=' . $this->xpathLiteral($id) . ']')->item(0);
            $row = $anchor;
            while ($row && !($row->nodeType === XML_ELEMENT_NODE && strpos(' ' . $row->getAttribute('class') . ' ', ' row ') !== false)) {
                $row = $row->parentNode;
            }
            if (!$row) {
                continue;
            }
            $target = $hx->query('.//p[contains(concat(" ", normalize-space(@class), " "), " citation ")]', $row)->item(0);
            if (!$target) {
                continue;
            }
            while ($target->firstChild) {
                $target->removeChild($target->firstChild);
            }
            $anchor2 = $html->createElement('a');
            $anchor2->setAttribute('id', 'cit-' . $id);
            $target->appendChild($anchor2);
            $this->fillReference($html, $target, $xp, $cit);
        }
    }

    /**
     * Appends a formatted element-citation to $target. $doc is the document that owns $target
     * (HTML for the viewer, XHTML for the ePub).
     */
    public function fillReference(DOMDocument $html, DOMElement $target, DOMXPath $xp, DOMElement $cit)
    {
        $clean = function ($s) {
            return trim(preg_replace('/\s+/u', ' ', $s));
        };
        $f = function ($q) use ($xp, $cit, $clean) {
            return $clean($xp->evaluate('string(' . $q . ')', $cit));
        };

        $names = [];
        foreach ($xp->query('person-group[@person-group-type="author" or not(@person-group-type)]/name | person-group[@person-group-type="author" or not(@person-group-type)]/collab', $cit) as $n) {
            if ($n->nodeName === 'collab') {
                $names[] = $clean($n->textContent);
                continue;
            }
            $sur = $clean($xp->evaluate('string(surname)', $n));
            $given = $clean($xp->evaluate('string(given-names)', $n));
            $names[] = $sur !== '' && $given !== '' ? "$sur, $given" : ($sur !== '' ? $sur : $given);
        }
        $authors = count($names) > 1
            ? implode('; ', array_slice($names, 0, -1)) . ' & ' . end($names)
            : ($names[0] ?? '');

        $year = $f('year');
        $title = $f('article-title') ?: $f('chapter-title');
        $source = $f('source');
        $type = $cit->getAttribute('publication-type');
        $vol = $f('volume');
        $issue = $f('issue');
        $pages = $f('page-range');
        if ($pages === '') {
            $fp = $f('fpage');
            $lp = $f('lpage');
            $pages = $fp !== '' && $lp !== '' ? "$fp–$lp" : $fp;
        }
        $pages = str_replace('-', '–', $pages);
        $publisher = $f('publisher-name');
        $place = $f('publisher-loc');
        $edition = $f('edition');

        $text = function ($t) use ($html, $target) {
            $target->appendChild($html->createTextNode($t));
        };
        $em = function ($t, $class = 'ref-source') use ($html, $target) {
            $e = $html->createElement('i');
            $e->setAttribute('class', $class);
            $e->appendChild($html->createTextNode($t));
            $target->appendChild($e);
        };

        if ($authors !== '') {
            $text($authors . ($year !== '' ? ' (' . $year . '). ' : '. '));
        } elseif ($year !== '') {
            $text('(' . $year . '). ');
        }
        if ($type === 'book' || $title === '') {
            if ($source !== '') {
                $em($source);
                $text($edition !== '' ? " ($edition ed.)" : '');
                $text('. ');
            }
            if ($title !== '') {
                $text($title . '. ');
            }
        } else {
            $text($title . (preg_match('/[.?!]$/u', $title) ? ' ' : '. '));
            if ($source !== '') {
                $em($source);
            }
            $detail = ($vol !== '' ? ', ' . $vol : '') . ($issue !== '' ? '(' . $issue . ')' : '') . ($pages !== '' ? ', ' . $pages : '');
            $text($detail . ($source !== '' || $detail !== '' ? '. ' : ''));
        }
        $pub = trim(($place !== '' ? $place . ($publisher !== '' ? ': ' : '') : '') . $publisher);
        if ($pub !== '') {
            $text($pub . '. ');
        }
        $doi = $f('pub-id[@pub-id-type="doi"]');
        $links = [];
        if ($doi !== '') {
            $links[] = preg_match('#^https?:#i', $doi) ? $doi : 'https://doi.org/' . $doi;
        }
        foreach ($xp->query('ext-link/@xlink:href | uri', $cit) as $u) {
            $url = $clean($u->nodeValue ?? $u->textContent);
            if ($url !== '' && !in_array($url, $links, true)) {
                $links[] = $url;
            }
        }
        foreach ($links as $i => $url) {
            if ($i) {
                $text(' ');
            }
            $a = $html->createElement('a');
            $a->setAttribute('href', $url);
            $a->setAttribute('target', '_blank');
            $a->setAttribute('rel', 'noopener');
            $a->appendChild($html->createTextNode($url));
            $target->appendChild($a);
        }
        // Trim a dangling space left by the last piece
        if ($target->lastChild && $target->lastChild->nodeType === XML_TEXT_NODE) {
            $target->lastChild->nodeValue = rtrim($target->lastChild->nodeValue);
        }
    }

    private function xpathLiteral($s)
    {
        return strpos($s, "'") === false ? "'" . $s . "'" : '"' . $s . '"';
    }

    /**
     * Reads the bibliographic data used by the viewer's "Cite" dialog.
     * @return array<int, array{0:string,1:string}>
     */
    private function extractCitationMeta(DOMDocument $xml)
    {
        $xp = new DOMXPath($xml);
        $one = function ($query) use ($xp) {
            $n = $xp->query($query)->item(0);
            return $n ? trim(preg_replace('/\s+/', ' ', $n->textContent)) : '';
        };
        $meta = [];
        $add = function ($name, $value) use (&$meta) {
            if ($value !== '') {
                $meta[] = [$name, $value];
            }
        };
        $add('citation_title', $one('//article-meta/title-group/article-title'));
        foreach ($xp->query('//article-meta/contrib-group/contrib[@contrib-type="author"]/name') as $name) {
            $sur = trim($xp->evaluate('string(surname)', $name));
            $given = trim($xp->evaluate('string(given-names)', $name));
            $add('citation_author', $given !== '' ? "$sur, $given" : $sur);
            $orcid = trim($xp->evaluate('string(../contrib-id[@contrib-id-type="orcid"])', $name));
            $meta[] = ['citation_author_orcid', $orcid];
        }
        $add('citation_journal_title', $one('//journal-meta//journal-title'));
        $add('citation_publication_date', $one('//article-meta/pub-date[1]/year'));
        $add('citation_volume', $one('//article-meta/volume'));
        $add('citation_issue', $one('//article-meta/issue'));
        $add('citation_firstpage', $one('//article-meta/fpage'));
        $add('citation_lastpage', $one('//article-meta/lpage'));
        $add('citation_publisher', $one('//journal-meta//publisher-name'));
        $add('citation_issn_epub', $one('//journal-meta/issn[@pub-type="epub" or @publication-format="electronic"]'));
        $add('citation_issn_ppub', $one('//journal-meta/issn[@pub-type="ppub" or @publication-format="print"]'));
        if ($one('//journal-meta/issn') !== '' && $one('//journal-meta/issn[@pub-type or @publication-format]') === '') {
            $add('citation_issn', $one('//journal-meta/issn[1]'));
        }
        $logo = $xp->query('//journal-meta//graphic | //journal-meta//inline-graphic')->item(0);
        $logoRef = $logo ? trim($logo->getAttributeNS(self::XLINK_NS, 'href')) : '';
        if ($logoRef === '') {
            $logoRef = trim($xp->evaluate('string(//self-uri[@specific-use="issue-cover"]/@*[local-name()="href"])'));
        }
        if ($logoRef === '') {
            $logoRef = $one('//journal-meta//custom-meta[meta-name="logo"]/meta-value');
        }
        if (preg_match('#^(https?:|data:image/|images/)#i', $logoRef)) {
            $add('citation_journal_logo', $logoRef);
        }
        $add('citation_doi', $one('//article-meta/article-id[@pub-id-type="doi"]'));
        return $meta;
    }

    /**
     * Inlines the stylesheet, forces UTF-8 and emits an HTML5 document.
     */
    private function finalizeHtml(DOMDocument $html, $lang = null, array $citation = [])
    {
        $xpath = new DOMXPath($html);
        if ($lang && preg_match('/^[a-z]{2,3}$/i', $lang)) {
            $html->documentElement->setAttribute('lang', strtolower($lang));
        }
        $head = $xpath->query('//head')->item(0);
        if ($head) {
            foreach ($xpath->query('//head/meta[@http-equiv] | //head/link[@rel="stylesheet"]') as $node) {
                $node->parentNode->removeChild($node);
            }
            $charset = $html->createElement('meta');
            $charset->setAttribute('charset', 'UTF-8');
            $head->insertBefore($charset, $head->firstChild);
            if (is_file($this->cssPath)) {
                $style = $html->createElement('style');
                $style->appendChild($html->createTextNode(file_get_contents($this->cssPath)));
                $head->appendChild($style);
            }
            foreach ($citation as $pair) {
                $m = $html->createElement('meta');
                $m->setAttribute('name', $pair[0]);
                $m->setAttribute('content', $pair[1]);
                $head->appendChild($m);
            }
            $viewport = $html->createElement('meta');
            $viewport->setAttribute('name', 'viewport');
            $viewport->setAttribute('content', 'width=device-width, initial-scale=1');
            $head->appendChild($viewport);
            if (is_file($this->viewerCssPath)) {
                $vstyle = $html->createElement('style');
                $vstyle->appendChild($html->createTextNode(file_get_contents($this->viewerCssPath)));
                $head->appendChild($vstyle);
            }
        }
        if (is_file($this->viewerJsPath) && ($bodyNode = $xpath->query('//body')->item(0))) {
            // "</" is escaped so the inline script can never close itself early
            $js = str_replace('</', '<\\/', file_get_contents($this->viewerJsPath));
            $script = $html->createElement('script');
            $script->appendChild($html->createTextNode($js));
            $bodyNode->appendChild($script);
        }
        // The stylesheet falls back to the file name as alt text; the figure caption already describes it
        foreach ($xpath->query('//img[@alt]') as $img) {
            if ($img->getAttribute('alt') === $img->getAttribute('src')) {
                $img->setAttribute('alt', '');
            }
        }
        $body = $html->saveHTML($html->documentElement);
        return "<!DOCTYPE html>\n" . $body . "\n";
    }
}
