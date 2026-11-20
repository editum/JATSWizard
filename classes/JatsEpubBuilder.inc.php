<?php

/**
 * Builds an accessible EPUB 3 straight from a JATS-XML article.
 *
 * Each document is written as XHTML with the EPUB structural semantics vocabulary
 * (epub:type) plus the matching DPUB-ARIA roles, a linear heading hierarchy, relative
 * units only in the CSS and the schema.org accessibility metadata in the package document.
 */
class JatsEpubBuilder
{
    const XHTML = 'http://www.w3.org/1999/xhtml';
    const OPS = 'http://www.idpf.org/2007/ops';
    const XLINK = 'http://www.w3.org/1999/xlink';
    const XMLNS = 'http://www.w3.org/2000/xmlns/';
    const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    private static $I18N = [
        'es' => ['abstract' => 'Resumen', 'keywords' => 'Palabras clave', 'refs' => 'Referencias', 'notes' => 'Notas', 'contents' => 'Contenidos', 'text' => 'Texto', 'affs' => 'Afiliaciones', 'license' => 'Licencia', 'published' => 'Publicado', 'journal' => 'Revista', 'issnE' => 'ISSN electrónico', 'issnP' => 'ISSN impreso', 'cover' => 'Portada', 'back' => 'Volver al texto', 'orcid' => 'ORCID de', 'fig' => 'Figura', 'table' => 'Tabla', 'formula' => 'Fórmula', 'ack' => 'Agradecimientos', 'app' => 'Apéndice', 'funding' => 'Financiación', 'long' => 'Descripción detallada', 'vol' => 'vol.', 'num' => 'n.º', 'landmarks' => 'Puntos de referencia', 'start' => 'Inicio del texto',
            'summary' => 'Libro electrónico generado automáticamente a partir del XML-JATS del artículo. Incluye estructura semántica EPUB 3 (epub:type y roles ARIA), tabla de contenidos navegable, orden de lectura lineal, texto redimensionable y descripciones alternativas de las imágenes tomadas de los textos alternativos del XML o, en su defecto, de los pies de figura. No se ha realizado una auditoría de accesibilidad independiente.'],
        'en' => ['abstract' => 'Abstract', 'keywords' => 'Keywords', 'refs' => 'References', 'notes' => 'Notes', 'contents' => 'Contents', 'text' => 'Text', 'affs' => 'Affiliations', 'license' => 'License', 'published' => 'Published', 'journal' => 'Journal', 'issnE' => 'Online ISSN', 'issnP' => 'Print ISSN', 'cover' => 'Title page', 'back' => 'Back to text', 'orcid' => 'ORCID of', 'fig' => 'Figure', 'table' => 'Table', 'formula' => 'Formula', 'ack' => 'Acknowledgments', 'app' => 'Appendix', 'funding' => 'Funding', 'long' => 'Detailed description', 'vol' => 'vol.', 'num' => 'no.', 'landmarks' => 'Landmarks', 'start' => 'Start of text',
            'summary' => 'E-book generated automatically from the article JATS XML. It includes EPUB 3 structural semantics (epub:type and ARIA roles), a navigable table of contents, a linear reading order, resizable text and image descriptions taken from the XML alternative texts or, failing that, from the figure captions. No independent accessibility audit has been carried out.'],
        'pt' => ['abstract' => 'Resumo', 'keywords' => 'Palavras-chave', 'refs' => 'Referências', 'notes' => 'Notas', 'contents' => 'Conteúdo', 'text' => 'Texto', 'affs' => 'Afiliações', 'license' => 'Licença', 'published' => 'Publicado', 'journal' => 'Revista', 'issnE' => 'ISSN eletrônico', 'issnP' => 'ISSN impresso', 'cover' => 'Folha de rosto', 'back' => 'Voltar ao texto', 'orcid' => 'ORCID de', 'fig' => 'Figura', 'table' => 'Tabela', 'formula' => 'Fórmula', 'ack' => 'Agradecimentos', 'app' => 'Apêndice', 'funding' => 'Financiamento', 'long' => 'Descrição detalhada', 'vol' => 'v.', 'num' => 'n.', 'landmarks' => 'Pontos de referência', 'start' => 'Início do texto',
            'summary' => 'Livro eletrônico gerado automaticamente a partir do XML-JATS do artigo. Inclui semântica estrutural EPUB 3 (epub:type e papéis ARIA), sumário navegável, ordem de leitura linear, texto redimensionável e descrições alternativas das imagens obtidas dos textos alternativos do XML ou, na falta destes, das legendas das figuras. Não foi realizada uma auditoria de acessibilidade independente.'],
    ];

    private static $INLINE = [
        'italic' => 'em', 'bold' => 'strong', 'sup' => 'sup', 'sub' => 'sub', 'underline' => 'u', 'monospace' => 'code',
        'strike' => 's', 'abbrev' => 'abbr', 'sc' => 'span', 'roman' => 'span', 'sans-serif' => 'span', 'overline' => 'span',
        'named-content' => 'span', 'styled-content' => 'span', 'inline-formula' => 'span', 'x' => 'span', 'surname' => 'span',
        'given-names' => 'span',
    ];

    private static $SKIP = [
        'object-id', 'kwd-group', 'permissions', 'supplementary-material', 'ref-list', 'sec-meta', 'tex-math', 'media', 'target',
        'milestone-start', 'milestone-end', 'alt-text', 'long-desc', 'label', 'caption', 'table-wrap-foot-skip',
    ];

    /** Parents that only accept element children, so whitespace text is dropped. */
    private static $NO_TEXT = ['section', 'ul', 'ol', 'dl', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'colgroup', 'figure', 'blockquote', 'aside', 'div', 'body', 'nav'];

    /** @var DOMDocument */
    private $xml;
    /** @var DOMXPath */
    private $xp;
    /** @var JatsHtmlExporter */
    private $exporter;
    private $base;
    private $lang = 'en';
    private $title = '';
    private $idFile = [];
    private $units = [];
    private $tocStack = [];
    private $secN = 0;
    private $images = [];
    private $notes = [];
    private $noteNum = [];
    private $noteBack = [];
    private $refNum = [];
    private $warnings = [];
    private $imgTotal = 0;
    private $imgNoAlt = 0;
    private $longDesc = false;
    private $tables = false;

    /** @return string[] */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * @param DOMDocument $xml      JATS with xlink:href of the images already pointing to "images/<file>"
     * @param string      $baseDir  Directory that contains the images/ folder
     * @param string      $epubPath File to create
     * @param string|null $lang     Fallback language when the XML declares none
     * @param JatsHtmlExporter $exporter Provides the reference formatter
     */
    public function build(DOMDocument $xml, $baseDir, $epubPath, $lang, $exporter)
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception("PHP extension 'zip' is required to build the ePub.");
        }
        $this->xml = $xml;
        $this->exporter = $exporter;
        $this->base = realpath($baseDir);
        $this->xp = new DOMXPath($xml);
        $this->xp->registerNamespace('xlink', self::XLINK);
        $declared = $xml->documentElement->getAttributeNS(self::XML_NS, 'lang');
        $this->lang = strtolower(explode('_', str_replace('-', '_', $declared ?: ($lang ?: 'en')))[0]);
        $this->title = $this->q('string(//article-meta/title-group/article-title)');

        $this->indexArticle();

        $docs = [];
        $docs[] = $this->titlePage();
        if ($abs = $this->abstractPage()) {
            $docs[] = $abs;
        }
        foreach ($this->units as $unit) {
            $docs[] = $this->unitPage($unit);
        }
        if ($this->notes) {
            $docs[] = $this->notesPage();
        }
        if ($this->refNum) {
            $docs[] = $this->referencesPage();
        }

        $this->writePackage($epubPath, $docs);
        return $this->warnings;
    }

    /* ------------------------------------------------------------------ *
     *  Indexing
     * ------------------------------------------------------------------ */

    private function indexArticle()
    {
        $n = 0;
        $pending = [];
        $flush = function () use (&$pending, &$n) {
            if ($pending) {
                $n++;
                $this->units[] = ['file' => "chapter-$n.xhtml", 'kind' => 'text', 'node' => null, 'nodes' => $pending, 'title' => $this->t('text')];
                $pending = [];
            }
        };
        $body = $this->xp->query('/article/body')->item(0);
        if ($body) {
            foreach ($body->childNodes as $c) {
                if ($c->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }
                if ($c->nodeName === 'sec') {
                    $flush();
                    $n++;
                    $this->units[] = ['file' => "chapter-$n.xhtml", 'kind' => 'chapter', 'node' => $c, 'nodes' => [], 'title' => $this->titleOf($c, $this->t('text'))];
                } else {
                    $pending[] = $c;
                }
            }
            $flush();
        }
        $b = 0;
        foreach ($this->xp->query('/article/back/ack | /article/back/app | /article/back/app-group/app | /article/back/glossary | /article/back/notes') as $node) {
            $b++;
            $kind = $node->nodeName === 'app-group' ? 'app' : $node->nodeName;
            $default = $kind === 'ack' ? $this->t('ack') : ($kind === 'app' ? $this->t('app') : $this->t('notes'));
            $this->units[] = ['file' => "back-$b.xhtml", 'kind' => $kind, 'node' => $node, 'nodes' => [], 'title' => $this->titleOf($node, $default)];
        }

        foreach ($this->units as $unit) {
            $targets = $unit['node'] ? [$unit['node']] : $unit['nodes'];
            foreach ($targets as $t) {
                foreach ($this->xp->query('descendant-or-self::*[@id]', $t) as $el) {
                    $this->idFile[$el->getAttribute('id')] = $unit['file'];
                }
            }
        }

        $i = 0;
        foreach ($this->xp->query('//fn[not(ancestor::table-wrap-foot) and not(ancestor::front)]') as $fn) {
            $i++;
            if ($fn->getAttribute('id') === '') {
                $fn->setAttribute('id', 'fn-auto-' . $i);
            }
            $id = $fn->getAttribute('id');
            $this->notes[$id] = $fn;
            $this->noteNum[$id] = $i;
            $this->idFile[$id] = 'notes.xhtml';
        }
        $i = 0;
        foreach ($this->xp->query('//back//ref[@id]') as $ref) {
            $i++;
            $this->refNum[$ref->getAttribute('id')] = $i;
            $this->idFile[$ref->getAttribute('id')] = 'references.xhtml';
        }
        foreach ($this->xp->query('//article-meta//aff[@id]') as $aff) {
            $this->idFile[$aff->getAttribute('id')] = 'titlepage.xhtml';
        }
    }

    private function titleOf(DOMElement $node, $default)
    {
        $t = $this->xp->query('title', $node)->item(0);
        $label = $this->xp->query('label', $node)->item(0);
        $s = trim(($label ? $this->norm($label->textContent) . ' ' : '') . ($t ? $this->norm($t->textContent) : ''));
        return $s !== '' ? $s : $default;
    }

    /* ------------------------------------------------------------------ *
     *  Documents
     * ------------------------------------------------------------------ */

    private function titlePage()
    {
        list($d, $body) = $this->newDoc($this->title ?: $this->t('cover'));
        $body->setAttributeNS(self::OPS, 'epub:type', 'frontmatter');
        $main = $this->e($d, 'main');
        $sec = $this->e($d, 'section', ['epub:type' => 'titlepage', 'aria-labelledby' => 'article-title']);
        $header = $this->e($d, 'header');
        $h1 = $this->e($d, 'h1', ['id' => 'article-title', 'epub:type' => 'title']);
        $h1->appendChild($d->createTextNode($this->title ?: $this->t('cover')));
        $header->appendChild($h1);
        $sub = $this->q('string(//article-meta/title-group/subtitle)');
        if ($sub !== '') {
            $header->appendChild($this->e($d, 'p', ['epub:type' => 'subtitle', 'role' => 'doc-subtitle', 'class' => 'subtitle'], $sub));
        }
        foreach ($this->xp->query('//article-meta/title-group/trans-title-group') as $tg) {
            $l = $tg->getAttributeNS(self::XML_NS, 'lang');
            $txt = $this->norm($this->xp->evaluate('string(trans-title)', $tg));
            $ts = $this->norm($this->xp->evaluate('string(trans-subtitle)', $tg));
            if ($txt === '' || ($txt === $this->title && $l === $this->lang)) {
                continue;
            }
            $p = $this->e($d, 'p', ['class' => 'trans-title', 'lang' => $l ?: null, 'xml:lang' => $l ?: null]);
            $p->appendChild($this->e($d, 'em', [], $txt . ($ts !== '' ? ': ' . $ts : '')));
            $header->appendChild($p);
        }

        $authors = $this->xp->query('//article-meta/contrib-group/contrib[@contrib-type="author"]');
        if ($authors->length) {
            $ul = $this->e($d, 'ul', ['class' => 'authors', 'aria-label' => $this->lang === 'en' ? 'Authors' : ($this->lang === 'pt' ? 'Autores' : 'Autores')]);
            foreach ($authors as $c) {
                $li = $this->e($d, 'li');
                $name = $this->norm($this->xp->evaluate('string(name/given-names)', $c) . ' ' . $this->xp->evaluate('string(name/surname)', $c));
                if ($name === '') {
                    $name = $this->norm($this->xp->evaluate('string(collab)', $c));
                }
                $li->appendChild($this->e($d, 'span', ['class' => 'author-name'], $name));
                foreach ($this->xp->query('xref[@ref-type="aff"]', $c) as $x) {
                    $rid = $x->getAttribute('rid');
                    $label = $this->norm($this->xp->evaluate('string(//aff[@id="' . $rid . '"]/label)'));
                    if ($rid !== '' && $label !== '') {
                        $sup = $this->e($d, 'sup');
                        $sup->appendChild($this->e($d, 'a', ['href' => '#' . $rid, 'aria-label' => $this->t('affs') . ' ' . $label], $label));
                        $li->appendChild($sup);
                    }
                }
                $orcid = $this->norm($this->xp->evaluate('string(contrib-id[@contrib-id-type="orcid"])', $c));
                if (preg_match('#^https?://orcid\.org/[0-9X-]+$#i', $orcid)) {
                    $li->appendChild($d->createTextNode(' '));
                    $a = $this->e($d, 'a', ['href' => $orcid, 'class' => 'orcid']);
                    $a->appendChild($d->createTextNode('ORCID'));
                    $a->appendChild($this->e($d, 'span', ['class' => 'visually-hidden'], ' (' . $this->t('orcid') . ' ' . $name . ')'));
                    $li->appendChild($a);
                }
                $ul->appendChild($li);
            }
            $header->appendChild($ul);
        }
        $sec->appendChild($header);

        $affs = $this->xp->query('//article-meta//aff');
        if ($affs->length) {
            $as = $this->e($d, 'section', ['aria-labelledby' => 'affs-h', 'class' => 'affiliations']);
            $as->appendChild($this->e($d, 'h2', ['id' => 'affs-h'], $this->t('affs')));
            $ul = $this->e($d, 'ul');
            foreach ($affs as $aff) {
                $parts = [];
                foreach ($this->xp->query('institution | addr-line | city | country', $aff) as $p) {
                    $parts[] = $this->norm($p->textContent);
                }
                if (!$parts) {
                    $parts[] = $this->norm(preg_replace('/^\s*' . preg_quote($this->norm($this->xp->evaluate('string(label)', $aff)), '/') . '/', '', $aff->textContent));
                }
                $label = $this->norm($this->xp->evaluate('string(label)', $aff));
                $li = $this->e($d, 'li', ['id' => $aff->getAttribute('id')]);
                if ($label !== '') {
                    $li->appendChild($this->e($d, 'sup', [], $label));
                    $li->appendChild($d->createTextNode(' '));
                }
                $li->appendChild($d->createTextNode(implode(', ', array_filter($parts))));
                $ul->appendChild($li);
            }
            $as->appendChild($ul);
            $sec->appendChild($as);
        }

        $dl = $this->e($d, 'dl', ['class' => 'article-data']);
        $add = function ($label, $value, $href = null) use ($d, $dl) {
            if ($value === '') {
                return;
            }
            $dl->appendChild($this->e($d, 'dt', [], $label));
            $dd = $this->e($d, 'dd');
            if ($href) {
                $dd->appendChild($this->e($d, 'a', ['href' => $href], $value));
            } else {
                $dd->appendChild($d->createTextNode($value));
            }
            $dl->appendChild($dd);
        };
        $jt = $this->norm($this->xp->evaluate('string(//journal-meta/journal-title-group/journal-title[1])'));
        $vol = $this->q('string(//article-meta/volume)');
        $iss = $this->q('string(//article-meta/issue)');
        $add($this->t('journal'), trim($jt . ($vol !== '' ? ', ' . $this->t('vol') . ' ' . $vol : '') . ($iss !== '' ? ', ' . $this->t('num') . ' ' . $iss : '')));
        $add($this->t('issnE'), $this->q('string(//journal-meta/issn[@pub-type="epub" or @publication-format="electronic"])'));
        $add($this->t('issnP'), $this->q('string(//journal-meta/issn[@pub-type="ppub" or @publication-format="print"])'));
        $doi = $this->q('string(//article-meta/article-id[@pub-id-type="doi"])');
        if ($doi !== '') {
            $url = preg_match('#^https?:#i', $doi) ? $doi : 'https://doi.org/' . $doi;
            $add('DOI', $url, $url);
        }
        $add($this->t('published'), $this->pubDate());
        $licUrl = trim($this->xp->evaluate('string(//article-meta/permissions/license/@xlink:href)'));
        $licText = $this->q('string(//article-meta/permissions/license/license-p)');
        $add($this->t('license'), $licText !== '' ? $licText : $licUrl, preg_match('#^https?://#i', $licUrl) ? $licUrl : null);
        $add($this->t('funding'), $this->q('string(//article-meta/funding-group/funding-statement)'));
        if ($dl->hasChildNodes()) {
            $sec->appendChild($dl);
        }
        $main->appendChild($sec);
        $body->appendChild($main);
        return ['file' => 'titlepage.xhtml', 'id' => 'titlepage', 'title' => $this->t('cover'), 'dom' => $d, 'toc' => (object)['href' => 'titlepage.xhtml', 'title' => $this->title ?: $this->t('cover'), 'children' => []]];
    }

    private function abstractPage()
    {
        $abstracts = [];
        foreach ($this->xp->query('//article-meta/abstract | //article-meta/trans-abstract') as $a) {
            $abstracts[] = $a;
        }
        if (!$abstracts) {
            return null;
        }
        list($d, $body) = $this->newDoc($this->t('abstract'));
        $body->setAttributeNS(self::OPS, 'epub:type', 'frontmatter');
        $main = $this->e($d, 'main');
        $used = [];
        $first = null;
        foreach ($abstracts as $i => $a) {
            $l = $a->getAttributeNS(self::XML_NS, 'lang') ?: $this->lang;
            $l = strtolower(explode('_', str_replace('-', '_', $l))[0]);
            $id = 'abstract-' . ($i + 1);
            $sec = $this->e($d, 'section', ['id' => $id, 'epub:type' => 'abstract', 'role' => 'doc-abstract', 'aria-labelledby' => $id . '-h', 'lang' => $l, 'xml:lang' => $l]);
            $te = $this->xp->query('title', $a)->item(0);
            $h = $this->e($d, 'h1', ['id' => $id . '-h']);
            $h->appendChild($d->createTextNode($te ? $this->norm($te->textContent) : $this->t('abstract', $l)));
            $sec->appendChild($h);
            if ($first === null) {
                $first = $id;
            }
            foreach ($a->childNodes as $c) {
                if ($c->nodeType === XML_ELEMENT_NODE && $c->nodeName === 'title') {
                    continue;
                }
                $this->node($c, $sec, $d, 'abstract.xhtml', 2);
            }
            foreach ($this->xp->query('//article-meta/kwd-group[@xml:lang="' . $l . '"]') as $g) {
                $used[spl_object_id($g)] = true;
                $this->keywords($d, $sec, $g, $l);
            }
            $main->appendChild($sec);
        }
        foreach ($this->xp->query('//article-meta/kwd-group') as $g) {
            if (!isset($used[spl_object_id($g)])) {
                $l = strtolower(explode('_', str_replace('-', '_', $g->getAttributeNS(self::XML_NS, 'lang') ?: $this->lang))[0]);
                $this->keywords($d, $main, $g, $l);
            }
        }
        $body->appendChild($main);
        return ['file' => 'abstract.xhtml', 'id' => 'abstract', 'title' => $this->t('abstract'), 'dom' => $d, 'toc' => (object)['href' => 'abstract.xhtml#' . $first, 'title' => $this->t('abstract'), 'children' => []]];
    }

    private function keywords(DOMDocument $d, DOMElement $dst, DOMElement $g, $l)
    {
        $kw = [];
        foreach ($this->xp->query('kwd', $g) as $k) {
            $kw[] = $this->norm($k->textContent);
        }
        if (!$kw) {
            return;
        }
        $t = $this->norm($this->xp->evaluate('string(title)', $g)) ?: $this->t('keywords', $l);
        $div = $this->e($d, 'div', ['class' => 'keywords', 'epub:type' => 'keywords', 'lang' => $l, 'xml:lang' => $l]);
        $p = $this->e($d, 'p');
        $p->appendChild($this->e($d, 'strong', [], $t . ': '));
        $p->appendChild($d->createTextNode(implode('; ', $kw)));
        $div->appendChild($p);
        $dst->appendChild($div);
    }

    private function unitPage(array $unit)
    {
        list($d, $body) = $this->newDoc($unit['title']);
        $isBack = !in_array($unit['kind'], ['chapter', 'text'], true);
        $body->setAttributeNS(self::OPS, 'epub:type', $isBack ? 'backmatter' : 'bodymatter');
        $main = $this->e($d, 'main');
        $root = (object)['href' => $unit['file'], 'title' => $unit['title'], 'children' => []];
        $this->tocStack = [$root];
        if ($unit['node']) {
            $this->section($unit['node'], $main, $d, $unit['file'], 1, $unit['kind'], $root);
        } else {
            $id = 'text-' . substr($unit['file'], 8, -6);
            $sec = $this->e($d, 'section', ['id' => $id, 'epub:type' => 'chapter', 'role' => 'doc-chapter', 'aria-label' => $this->title ?: $unit['title']]);
            foreach ($unit['nodes'] as $n) {
                $this->node($n, $sec, $d, $unit['file'], 2);
            }
            $main->appendChild($sec);
            $root->href = $unit['file'] . '#' . $id;
        }
        $body->appendChild($main);
        return ['file' => $unit['file'], 'id' => pathinfo($unit['file'], PATHINFO_FILENAME), 'title' => $unit['title'], 'dom' => $d, 'toc' => $root, 'kind' => $unit['kind']];
    }

    private function notesPage()
    {
        list($d, $body) = $this->newDoc($this->t('notes'));
        $body->setAttributeNS(self::OPS, 'epub:type', 'backmatter');
        $main = $this->e($d, 'main');
        $sec = $this->e($d, 'section', ['id' => 'notes', 'epub:type' => 'footnotes', 'role' => 'doc-endnotes', 'aria-labelledby' => 'notes-h']);
        $sec->appendChild($this->e($d, 'h1', ['id' => 'notes-h'], $this->t('notes')));
        foreach ($this->notes as $id => $fn) {
            $aside = $this->e($d, 'aside', ['id' => $id, 'epub:type' => 'footnote', 'role' => 'doc-footnote']);
            $label = $this->norm($this->xp->evaluate('string(label)', $fn)) ?: (string)$this->noteNum[$id];
            $has = false;
            foreach ($fn->childNodes as $c) {
                if ($c->nodeType === XML_ELEMENT_NODE && $c->nodeName === 'label') {
                    continue;
                }
                if ($c->nodeType === XML_ELEMENT_NODE && $c->nodeName === 'p') {
                    $has = true;
                }
            }
            if ($has) {
                $first = true;
                foreach ($fn->childNodes as $c) {
                    if ($c->nodeType !== XML_ELEMENT_NODE || $c->nodeName === 'label') {
                        continue;
                    }
                    $before = $aside->childNodes->length;
                    $this->node($c, $aside, $d, 'notes.xhtml', 2);
                    if ($first && $aside->firstChild && $aside->childNodes->length > $before) {
                        $lead = $this->e($d, 'strong', ['class' => 'note-label'], $label . '. ');
                        $aside->firstChild->insertBefore($lead, $aside->firstChild->firstChild);
                        $first = false;
                    }
                }
            } else {
                $p = $this->e($d, 'p');
                $p->appendChild($this->e($d, 'strong', ['class' => 'note-label'], $label . '. '));
                $this->conv($fn, $p, $d, 'notes.xhtml', 2);
                $aside->appendChild($p);
            }
            if (isset($this->noteBack[$id])) {
                $p = $this->e($d, 'p', ['class' => 'backlink']);
                $a = $this->e($d, 'a', ['href' => $this->noteBack[$id][0] . '#' . $this->noteBack[$id][1], 'epub:type' => 'backlink', 'role' => 'doc-backlink']);
                $a->appendChild($d->createTextNode("\u{21A9}"));
                $a->appendChild($this->e($d, 'span', ['class' => 'visually-hidden'], ' ' . $this->t('back')));
                $p->appendChild($a);
                $aside->appendChild($p);
            }
            $sec->appendChild($aside);
        }
        $main->appendChild($sec);
        $body->appendChild($main);
        return ['file' => 'notes.xhtml', 'id' => 'notes', 'title' => $this->t('notes'), 'dom' => $d, 'toc' => (object)['href' => 'notes.xhtml#notes', 'title' => $this->t('notes'), 'children' => []]];
    }

    private function referencesPage()
    {
        $rl = $this->xp->query('//back/ref-list')->item(0);
        $title = $rl ? $this->norm($this->xp->evaluate('string(title)', $rl)) : '';
        $title = $title !== '' ? $title : $this->t('refs');
        list($d, $body) = $this->newDoc($title);
        $body->setAttributeNS(self::OPS, 'epub:type', 'backmatter');
        $main = $this->e($d, 'main');
        $sec = $this->e($d, 'section', ['id' => 'references', 'epub:type' => 'bibliography', 'role' => 'doc-bibliography', 'aria-labelledby' => 'references-h']);
        $sec->appendChild($this->e($d, 'h1', ['id' => 'references-h'], $title));
        $ol = $this->e($d, 'ol', ['class' => 'references']);
        foreach ($this->xp->query('//back//ref[@id]') as $ref) {
            $li = $this->e($d, 'li', ['id' => $ref->getAttribute('id'), 'epub:type' => 'biblioentry']);
            $cit = $this->xp->query('element-citation', $ref)->item(0);
            if ($cit && !$this->xp->query('mixed-citation', $ref)->length) {
                $this->exporter->fillReference($d, $li, $this->xp, $cit);
                $this->fixNamespaces($li, $d);
            } else {
                $mixed = $this->xp->query('mixed-citation | citation | nlm-citation', $ref)->item(0);
                $this->conv($mixed ?: $ref, $li, $d, 'references.xhtml', 2);
            }
            $ol->appendChild($li);
        }
        $sec->appendChild($ol);
        $main->appendChild($sec);
        $body->appendChild($main);
        return ['file' => 'references.xhtml', 'id' => 'references', 'title' => $title, 'dom' => $d, 'toc' => (object)['href' => 'references.xhtml#references', 'title' => $title, 'children' => []]];
    }

    /** Elements created with createElement() carry no namespace; move them to XHTML. */
    private function fixNamespaces(DOMElement $el, DOMDocument $d)
    {
        foreach (iterator_to_array($el->childNodes) as $c) {
            if ($c->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $this->fixNamespaces($c, $d);
            if ($c->namespaceURI === null) {
                $n = $d->createElementNS(self::XHTML, $c->nodeName);
                foreach (iterator_to_array($c->attributes) as $a) {
                    if ($a->nodeName !== 'target') {
                        $n->setAttribute($a->nodeName, $a->nodeValue);
                    }
                }
                while ($c->firstChild) {
                    $n->appendChild($c->firstChild);
                }
                $el->replaceChild($n, $c);
            }
        }
    }

    /* ------------------------------------------------------------------ *
     *  JATS -> XHTML conversion
     * ------------------------------------------------------------------ */

    private function conv(DOMNode $src, DOMNode $dst, DOMDocument $d, $file, $lvl)
    {
        foreach ($src->childNodes as $n) {
            $this->node($n, $dst, $d, $file, $lvl);
        }
    }

    private function node(DOMNode $n, DOMNode $dst, DOMDocument $d, $file, $lvl)
    {
        if ($n->nodeType === XML_TEXT_NODE || $n->nodeType === XML_CDATA_SECTION_NODE) {
            $t = preg_replace('/\s+/u', ' ', $n->nodeValue);
            if (trim($t) === '' && ($dst->nodeName === '#document' || in_array($dst->localName, self::$NO_TEXT, true))) {
                return;
            }
            $dst->appendChild($d->createTextNode($t));
            return;
        }
        if ($n->nodeType !== XML_ELEMENT_NODE) {
            return;
        }
        $name = $n->nodeName;
        if (in_array($name, self::$SKIP, true)) {
            return;
        }
        if (isset(self::$INLINE[$name])) {
            $el = $this->e($d, self::$INLINE[$name], $name === 'sc' ? ['class' => 'sc'] : []);
            $this->conv($n, $el, $d, $file, $lvl);
            $dst->appendChild($el);
            return;
        }
        switch ($name) {
            case 'sec':
                $this->section($n, $dst, $d, $file, $lvl, 'sub');
                return;
            case 'title':
                $p = $this->e($d, 'p', ['class' => 'block-title']);
                $strong = $this->e($d, 'strong');
                $this->conv($n, $strong, $d, $file, $lvl);
                $p->appendChild($strong);
                $dst->appendChild($p);
                return;
            case 'p':
                $p = $this->e($d, 'p');
                $this->conv($n, $p, $d, $file, $lvl);
                if ($p->hasChildNodes() && trim($p->textContent) !== '' || $p->getElementsByTagNameNS(self::XHTML, 'img')->length) {
                    $dst->appendChild($p);
                }
                return;
            case 'break':
                $dst->appendChild($this->e($d, 'br'));
                return;
            case 'xref':
                $this->xref($n, $dst, $d, $file, $lvl);
                return;
            case 'ext-link':
            case 'uri':
            case 'email':
                $this->link($n, $dst, $d, $file, $lvl);
                return;
            case 'list':
                $type = $n->getAttribute('list-type');
                $list = $this->e($d, in_array($type, ['bullet', 'simple', ''], true) ? 'ul' : 'ol');
                $this->conv($n, $list, $d, $file, $lvl);
                $dst->appendChild($list);
                return;
            case 'list-item':
                $li = $this->e($d, 'li');
                $this->conv($n, $li, $d, $file, $lvl);
                $dst->appendChild($li);
                return;
            case 'def-list':
                $dl = $this->e($d, 'dl');
                $this->conv($n, $dl, $d, $file, $lvl);
                $dst->appendChild($dl);
                return;
            case 'term':
                $dt = $this->e($d, 'dt');
                $this->conv($n, $dt, $d, $file, $lvl);
                $dst->appendChild($dt);
                return;
            case 'def':
                $dd = $this->e($d, 'dd');
                $this->conv($n, $dd, $d, $file, $lvl);
                $dst->appendChild($dd);
                return;
            case 'disp-quote':
                $bq = $this->e($d, 'blockquote');
                $this->conv($n, $bq, $d, $file, $lvl);
                $dst->appendChild($bq);
                return;
            case 'attrib':
                $p = $this->e($d, 'p', ['class' => 'attrib']);
                $this->conv($n, $p, $d, $file, $lvl);
                $dst->appendChild($p);
                return;
            case 'preformat':
                $pre = $this->e($d, 'pre', [], $n->textContent);
                $dst->appendChild($pre);
                return;
            case 'boxed-text':
            case 'statement':
                $a = $this->e($d, 'aside', ['class' => $name === 'boxed-text' ? 'boxed' : 'statement']);
                $this->conv($n, $a, $d, $file, $lvl);
                $dst->appendChild($a);
                return;
            case 'fig':
                $this->fig($n, $dst, $d, $file);
                return;
            case 'table-wrap':
                $this->tableWrap($n, $dst, $d, $file, $lvl);
                return;
            case 'table':
                $dst->appendChild($this->table($n, $d, $file, null));
                return;
            case 'graphic':
            case 'inline-graphic':
                $img = $this->graphic($n, $d, '');
                if ($img) {
                    $dst->appendChild($img);
                }
                return;
            case 'alternatives':
                $pick = null;
                foreach ($n->childNodes as $c) {
                    if ($c->nodeType === XML_ELEMENT_NODE && ($c->nodeName === 'table' || ($pick === null && $c->nodeName === 'graphic'))) {
                        $pick = $c;
                    }
                }
                if ($pick) {
                    $this->node($pick, $dst, $d, $file, $lvl);
                }
                return;
            case 'disp-formula':
                $div = $this->e($d, 'div', ['class' => 'formula', 'id' => $n->getAttribute('id') ?: null]);
                $alt = $this->norm($this->xp->evaluate('string(tex-math)', $n)) ?: ($this->norm($this->xp->evaluate('string(label)', $n)) ?: $this->t('formula'));
                foreach ($n->childNodes as $c) {
                    if ($c->nodeType === XML_ELEMENT_NODE && ($c->nodeName === 'graphic' || $c->nodeName === 'alternatives')) {
                        $g = $c->nodeName === 'graphic' ? $c : $this->xp->query('graphic', $c)->item(0);
                        $img = $g ? $this->graphic($g, $d, $alt) : null;
                        if ($img) {
                            $div->appendChild($img);
                        }
                    } elseif ($c->nodeType === XML_ELEMENT_NODE && $c->nodeName === 'tex-math') {
                        $div->appendChild($this->e($d, 'code', [], $this->norm($c->textContent)));
                    } elseif ($c->nodeType === XML_ELEMENT_NODE && $c->localName === 'math') {
                        $div->appendChild($this->mathml($c, $d));
                    }
                }
                if ($div->hasChildNodes()) {
                    $dst->appendChild($div);
                }
                return;
            case 'table-wrap-foot':
                $div = $this->e($d, 'div', ['class' => 'table-foot']);
                $this->conv($n, $div, $d, $file, $lvl);
                $dst->appendChild($div);
                return;
            case 'fn':
                if ($this->xp->query('ancestor::table-wrap-foot', $n)->length) {
                    $this->conv($n, $dst, $d, $file, $lvl);
                    return;
                }
                $id = $n->getAttribute('id');
                if ($id !== '' && isset($this->noteNum[$id])) {
                    $dst->appendChild($this->noteref($d, $id, '[' . $this->noteNum[$id] . ']', $file));
                }
                return;
            default:
                if (strpos($name, ':') !== false) {
                    if ($n->localName === 'math') {
                        $dst->appendChild($this->mathml($n, $d));
                    }
                    return;
                }
                $this->conv($n, $dst, $d, $file, $lvl);
        }
    }

    private function section(DOMElement $s, DOMNode $dst, DOMDocument $d, $file, $lvl, $kind, $rootToc = null)
    {
        $id = $s->getAttribute('id');
        if ($id === '') {
            $id = 'sec-' . (++$this->secN);
        }
        $titleEl = $this->xp->query('title', $s)->item(0);
        $labelEl = $this->xp->query('label', $s)->item(0);
        $title = $this->titleOf($s, '');
        $map = [
            'chapter' => ['chapter', 'doc-chapter'], 'sub' => ['subchapter', null], 'ack' => ['acknowledgments', 'doc-acknowledgments'],
            'app' => ['appendix', 'doc-appendix'], 'glossary' => ['glossary', 'doc-glossary'], 'notes' => ['chapter', 'doc-chapter'],
        ];
        $m = $map[$kind] ?? $map['chapter'];
        $attrs = ['id' => $id, 'epub:type' => $m[0]];
        if ($m[1]) {
            $attrs['role'] = $m[1];
        }
        if ($s->getAttributeNS(self::XML_NS, 'lang') !== '') {
            $attrs['lang'] = $attrs['xml:lang'] = $s->getAttributeNS(self::XML_NS, 'lang');
        }
        if ($title !== '') {
            $attrs['aria-labelledby'] = 'h-' . $id;
        } else {
            $attrs['aria-label'] = $this->title;
        }
        $sec = $this->e($d, 'section', $attrs);
        if ($title !== '') {
            $h = $this->e($d, 'h' . min(max($lvl, 1), 6), ['id' => 'h-' . $id]);
            if ($labelEl) {
                $h->appendChild($d->createTextNode($this->norm($labelEl->textContent) . ' '));
            }
            if ($titleEl) {
                $this->conv($titleEl, $h, $d, $file, $lvl);
            }
            $sec->appendChild($h);
        }
        if ($rootToc) {
            $rootToc->href = $file . '#' . $id;
            $rootToc->title = $title !== '' ? $title : $rootToc->title;
        } elseif ($title !== '') {
            $entry = (object)['href' => $file . '#' . $id, 'title' => $title, 'children' => []];
            end($this->tocStack)->children[] = $entry;
            $this->tocStack[] = $entry;
        }
        foreach ($s->childNodes as $c) {
            if ($c->nodeType === XML_ELEMENT_NODE && in_array($c->nodeName, ['title', 'label'], true)) {
                continue;
            }
            $this->node($c, $sec, $d, $file, $lvl + 1);
        }
        if (!$rootToc && $title !== '') {
            array_pop($this->tocStack);
        }
        $dst->appendChild($sec);
        if ($sec->hasAttribute('id') && !isset($this->idFile[$id])) {
            $this->idFile[$id] = $file;
        }
    }

    private function xref(DOMElement $x, DOMNode $dst, DOMDocument $d, $file, $lvl)
    {
        $rid = trim(explode(' ', trim($x->getAttribute('rid')))[0]);
        $type = $x->getAttribute('ref-type');
        $target = $rid !== '' ? ($this->idFile[$rid] ?? null) : null;
        $hasText = trim($x->textContent) !== '';
        if (!$target) {
            if ($hasText) {
                $span = $this->e($d, 'span');
                $this->conv($x, $span, $d, $file, $lvl);
                $dst->appendChild($span);
            }
            return;
        }
        if ($type === 'fn' && isset($this->noteNum[$rid])) {
            $a = $this->noteref($d, $rid, $hasText ? null : '[' . $this->noteNum[$rid] . ']', $file);
            if ($hasText) {
                $this->conv($x, $a, $d, $file, $lvl);
            }
            $dst->appendChild($a);
            return;
        }
        $attrs = ['href' => $target . '#' . $rid];
        if ($type === 'bib') {
            $attrs['epub:type'] = 'biblioref';
            $attrs['role'] = 'doc-biblioref';
        }
        $a = $this->e($d, 'a', $attrs);
        if ($hasText) {
            $this->conv($x, $a, $d, $file, $lvl);
        } else {
            $a->appendChild($d->createTextNode($this->xrefLabel($type, $rid)));
        }
        $dst->appendChild($a);
    }

    private function xrefLabel($type, $rid)
    {
        if (isset($this->refNum[$rid])) {
            return '[' . $this->refNum[$rid] . ']';
        }
        $el = $this->xp->query('//*[@id="' . $rid . '"]')->item(0);
        $label = $el ? $this->norm($this->xp->evaluate('string(label)', $el)) : '';
        return $label !== '' ? $label : $rid;
    }

    private function noteref(DOMDocument $d, $id, $text, $file)
    {
        $anchor = 'nr-' . $id;
        $attrs = ['href' => 'notes.xhtml#' . $id, 'epub:type' => 'noteref', 'role' => 'doc-noteref', 'class' => 'noteref'];
        if (!isset($this->noteBack[$id])) {
            $attrs['id'] = $anchor;
            $this->noteBack[$id] = [$file, $anchor];
        }
        $a = $this->e($d, 'a', $attrs);
        if ($text !== null) {
            $a->appendChild($d->createTextNode($text));
        }
        return $a;
    }

    private function link(DOMElement $n, DOMNode $dst, DOMDocument $d, $file, $lvl)
    {
        $href = $n->nodeName === 'email' ? 'mailto:' . $this->norm($n->textContent) : trim($n->getAttributeNS(self::XLINK, 'href'));
        if ($href === '' && $n->nodeName === 'uri') {
            $href = $this->norm($n->textContent);
        }
        if ($href === '' && $n->nodeName === 'ext-link') {
            $href = $this->norm($n->textContent);
        }
        if (!preg_match('#^(https?://|mailto:|ftp://)#i', $href)) {
            $span = $this->e($d, 'span');
            $this->conv($n, $span, $d, $file, $lvl);
            $dst->appendChild($span);
            return;
        }
        $a = $this->e($d, 'a', ['href' => $href]);
        if (trim($n->textContent) !== '') {
            $this->conv($n, $a, $d, $file, $lvl);
        } else {
            $a->appendChild($d->createTextNode($href));
        }
        $dst->appendChild($a);
    }

    private function fig(DOMElement $f, DOMNode $dst, DOMDocument $d, $file)
    {
        $id = $f->getAttribute('id');
        $label = $this->norm($this->xp->evaluate('string(label)', $f));
        $cap = $this->xp->query('caption', $f)->item(0);
        $capTitle = $cap ? $this->norm($this->xp->evaluate('string(title)', $cap)) : '';
        $capId = $id !== '' ? 'cap-' . $id : null;
        $figure = $this->e($d, 'figure', ['id' => $id ?: null, 'class' => 'fig']);

        $alt = $this->altText($f);
        if ($alt === '') {
            $alt = trim($label . ($label !== '' && $capTitle !== '' ? '. ' : '') . $capTitle);
        }
        $long = $this->norm($this->xp->evaluate('string(long-desc)', $f));
        $ldId = null;
        $describedBy = [];
        if ($capId && ($label !== '' || $cap)) {
            $describedBy[] = $capId;
        }
        if ($long !== '') {
            $this->longDesc = true;
            $ldId = 'ld-' . ($id ?: ++$this->secN);
            $describedBy[] = $ldId;
        }

        $placed = false;
        foreach ($f->childNodes as $c) {
            if ($c->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $g = $c->nodeName === 'graphic' ? $c : ($c->nodeName === 'alternatives' ? $this->xp->query('graphic', $c)->item(0) : null);
            if ($g) {
                $img = $this->graphic($g, $d, $alt);
                if ($img) {
                    if ($describedBy && !$img->hasAttribute('role')) {
                        $img->setAttribute('aria-describedby', implode(' ', $describedBy));
                    }
                    $figure->appendChild($img);
                    $placed = true;
                }
            }
        }
        if ($label !== '' || $cap) {
            $fc = $this->e($d, 'figcaption', ['id' => $capId]);
            $p = $this->e($d, 'p');
            if ($label !== '') {
                $p->appendChild($this->e($d, 'strong', [], $label . ($capTitle !== '' ? '. ' : '')));
            }
            if ($cap && ($t = $this->xp->query('title', $cap)->item(0))) {
                $this->conv($t, $p, $d, $file, 2);
            }
            if ($p->hasChildNodes()) {
                $fc->appendChild($p);
            }
            if ($cap) {
                foreach ($this->xp->query('p', $cap) as $cp) {
                    $this->node($cp, $fc, $d, $file, 2);
                }
            }
            foreach ($this->xp->query('attrib | permissions/copyright-statement', $f) as $at) {
                $this->node($at, $fc, $d, $file, 2);
            }
            $figure->appendChild($fc);
        }
        if ($long !== '') {
            $det = $this->e($d, 'details', ['class' => 'long-desc', 'id' => $ldId]);
            $det->appendChild($this->e($d, 'summary', [], $this->t('long')));
            $det->appendChild($this->e($d, 'p', [], $long));
            $figure->appendChild($det);
        }
        if ($placed || $figure->hasChildNodes()) {
            $dst->appendChild($figure);
        }
    }

    private function tableWrap(DOMElement $w, DOMNode $dst, DOMDocument $d, $file, $lvl)
    {
        $this->tables = true;
        $id = $w->getAttribute('id');
        $capId = $id !== '' ? 'cap-' . $id : 'cap-t' . (++$this->secN);
        $label = $this->norm($this->xp->evaluate('string(label)', $w));
        $cap = $this->xp->query('caption', $w)->item(0);
        $figure = $this->e($d, 'figure', ['id' => $id ?: null, 'class' => 'table-wrap']);
        if ($label !== '' || $cap) {
            $fc = $this->e($d, 'caption', ['id' => $capId]);
            $p = $this->e($d, 'p');
            $capTitle = $cap ? $this->norm($this->xp->evaluate('string(title)', $cap)) : '';
            if ($label !== '') {
                $p->appendChild($this->e($d, 'strong', [], $label . ($capTitle !== '' ? '. ' : '')));
            }
            if ($cap && ($t = $this->xp->query('title', $cap)->item(0))) {
                $this->conv($t, $p, $d, $file, 2);
            }
            $fc->appendChild($p);
            if ($cap) {
                foreach ($this->xp->query('p', $cap) as $cp) {
                    $this->node($cp, $fc, $d, $file, 2);
                }
            }
            $tableCaption = $fc;
        } else {
            $capId = null;
            $tableCaption = null;
        }
        foreach ($w->childNodes as $c) {
            if ($c->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            if ($c->nodeName === 'table') {
                $figure->appendChild($this->table($c, $d, $file, $tableCaption));
            } elseif (in_array($c->nodeName, ['graphic', 'alternatives', 'table-wrap-foot'], true)) {
                $this->node($c, $figure, $d, $file, $lvl);
            }
        }
        $dst->appendChild($figure);
    }

    private function table(DOMElement $t, DOMDocument $d, $file, $caption)
    {
        $table = $this->e($d, 'table');
        if ($caption) {
            $table->appendChild($caption);
        }
        $this->complexTable = $this->xp->query('.//*[(self::th or self::td) and (@rowspan > 1 or @colspan > 1)] | .//thead/tr[2]', $t)->length > 0;
        $this->tableRows($t, $table, $d, $file);
        return $table;
    }

    private $complexTable = false;

    private function tableRows(DOMElement $src, DOMElement $dst, DOMDocument $d, $file)
    {
        foreach ($src->childNodes as $c) {
            if ($c->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $name = $c->nodeName;
            if (!in_array($name, ['thead', 'tbody', 'tfoot', 'tr', 'colgroup', 'col', 'th', 'td'], true)) {
                continue;
            }
            $attrs = [];
            foreach (['colspan', 'rowspan', 'span'] as $a) {
                if ($c->hasAttribute($a)) {
                    $attrs[$a] = $c->getAttribute($a);
                }
            }
            $align = $c->getAttribute('align');
            if (in_array($align, ['left', 'right', 'center'], true)) {
                $attrs['class'] = 'al-' . $align;
            }
            if ($name === 'th') {
                $attrs['scope'] = $this->xp->query('ancestor::thead', $c)->length ? 'col' : 'row';
                if ($this->complexTable) {
                    $attrs['id'] = 'th-' . dechex(crc32($c->getNodePath()));
                }
            } elseif ($name === 'td' && $this->complexTable) {
                $hdr = [];
                $col = $this->colIndex($c);
                foreach ($this->xp->query('ancestor::table[1]//th', $c) as $th) {
                    $inHead = $this->xp->query('ancestor::thead', $th)->length > 0;
                    $sameRow = $th->parentNode->isSameNode($c->parentNode);
                    if (($inHead && $this->colCovers($th, $col)) || ($sameRow && !$inHead)) {
                        $hdr[] = 'th-' . dechex(crc32($th->getNodePath()));
                    }
                }
                if ($hdr) {
                    $attrs['headers'] = implode(' ', $hdr);
                }
            }
            $el = $this->e($d, $name, $attrs);
            if ($name === 'th' || $name === 'td') {
                $this->conv($c, $el, $d, $file, 2);
            } else {
                $this->tableRows($c, $el, $d, $file);
            }
            $dst->appendChild($el);
        }
    }

    private $hasMath = false;

    private function mathml(DOMElement $m, DOMDocument $d)
    {
        $this->hasMath = true;
        $ns = 'http://www.w3.org/1998/Math/MathML';
        $out = $d->createElementNS($ns, $m->localName);
        foreach ($m->attributes as $a) {
            if ($a->namespaceURI === null && preg_match('/^[a-zA-Z][\w-]*$/', $a->name)) {
                $out->setAttribute($a->name, $a->value);
            }
        }
        foreach ($m->childNodes as $c) {
            if ($c->nodeType === XML_ELEMENT_NODE) {
                $out->appendChild($this->mathml($c, $d));
            } elseif ($c->nodeType === XML_TEXT_NODE) {
                $out->appendChild($d->createTextNode($c->nodeValue));
            }
        }
        return $out;
    }

    /** libxml names the MathML default namespace "default:"; the subtree declares xmlns itself, so the prefix can go. */
    private function serialize(DOMDocument $d)
    {
        return preg_replace('#<(/?)default:#', '<$1', $d->saveXML());
    }

    private function colIndex(DOMElement $cell)
    {
        $i = 0;
        for ($s = $cell->previousSibling; $s; $s = $s->previousSibling) {
            if ($s->nodeType === XML_ELEMENT_NODE && in_array($s->nodeName, ['td', 'th'], true)) {
                $i += max(1, (int)$s->getAttribute('colspan'));
            }
        }
        return $i;
    }

    private function colCovers(DOMElement $th, $col)
    {
        $start = $this->colIndex($th);
        return $col >= $start && $col < $start + max(1, (int)$th->getAttribute('colspan'));
    }

    private function altText(DOMElement $n)
    {
        $a = $this->xp->query('alt-text', $n)->item(0);
        return $a ? $this->norm($a->textContent) : '';
    }

    /**
     * @param string $fallbackAlt Alternative text to use when the graphic has none of its own
     * @return DOMElement|null
     */
    private function graphic(DOMElement $g, DOMDocument $d, $fallbackAlt)
    {
        $href = trim($g->getAttributeNS(self::XLINK, 'href'));
        $path = $href !== '' ? realpath($this->base . '/' . $href) : false;
        if (!$path || strpos($path, $this->base) !== 0 || !is_file($path)) {
            $this->warnings[] = 'Image not found for the ePub: ' . $href;
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];
        if (!isset($types[$ext])) {
            $this->warnings[] = 'Unsupported image type for the ePub: ' . $href;
            return null;
        }
        $name = 'images/' . basename($path);
        if (!isset($this->images[$name])) {
            $this->images[$name] = ['path' => $path, 'type' => $types[$ext], 'id' => 'img' . (count($this->images) + 1)];
        }
        $alt = $this->altText($g) ?: $fallbackAlt;
        $this->imgTotal++;
        if ($alt === '') {
            $this->imgNoAlt++;
            return $this->e($d, 'img', ['src' => $name, 'alt' => '', 'role' => 'presentation']);
        }
        return $this->e($d, 'img', ['src' => $name, 'alt' => $alt]);
    }

    /* ------------------------------------------------------------------ *
     *  Package
     * ------------------------------------------------------------------ */

    private function writePackage($epubPath, array $docs)
    {
        $nav = $this->navPage($docs);
        $lang = $this->lang;

        $manifest = [
            '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>',
            '<item id="css" href="css/book.css" media-type="text/css"/>',
        ];
        $spine = ['<itemref idref="nav" linear="no"/>'];
        foreach ($docs as $doc) {
            $props = strpos($this->serialize($doc['dom']), '<math') !== false ? ' properties="mathml"' : '';
            $manifest[] = '<item id="' . $this->x($doc['id']) . '" href="' . $this->x($doc['file']) . '"' . $props . ' media-type="application/xhtml+xml"/>';
            $spine[] = '<itemref idref="' . $this->x($doc['id']) . '"/>';
        }
        foreach ($this->images as $name => $img) {
            $manifest[] = '<item id="' . $img['id'] . '" href="' . $this->x($name) . '" media-type="' . $img['type'] . '"/>';
        }

        $doi = $this->q('string(//article-meta/article-id[@pub-id-type="doi"])');
        $uid = $doi !== '' ? 'urn:doi:' . preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi) : $this->uuid($this->title . $this->q('string(//journal-meta/journal-id)'));
        $meta = ['<dc:identifier id="pub-id">' . $this->x($uid) . '</dc:identifier>'];
        $meta[] = '<dc:title>' . $this->x($this->title ?: 'Article') . '</dc:title>';
        $meta[] = '<dc:language>' . $this->x($lang) . '</dc:language>';
        foreach ($this->xp->query('//article-meta/contrib-group/contrib[@contrib-type="author"]') as $i => $c) {
            $name = $this->norm($this->xp->evaluate('string(name/given-names)', $c) . ' ' . $this->xp->evaluate('string(name/surname)', $c));
            $name = $name !== '' ? $name : $this->norm($this->xp->evaluate('string(collab)', $c));
            if ($name !== '') {
                $meta[] = '<dc:creator id="creator' . ($i + 1) . '">' . $this->x($name) . '</dc:creator>';
            }
        }
        $publisher = $this->q('string(//journal-meta//publisher-name)');
        if ($publisher !== '') {
            $meta[] = '<dc:publisher>' . $this->x($publisher) . '</dc:publisher>';
        }
        $date = $this->pubDate();
        if (preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $date)) {
            $meta[] = '<dc:date>' . $date . '</dc:date>';
        }
        $jt = $this->q('string(//journal-meta/journal-title-group/journal-title[1])');
        if ($jt !== '') {
            $src = $jt;
            $vol = $this->q('string(//article-meta/volume)');
            $iss = $this->q('string(//article-meta/issue)');
            if ($vol !== '') {
                $src .= ', ' . $this->t('vol') . ' ' . $vol;
            }
            if ($iss !== '') {
                $src .= ', ' . $this->t('num') . ' ' . $iss;
            }
            $meta[] = '<dc:source>' . $this->x($src) . '</dc:source>';
            $meta[] = '<meta property="belongs-to-collection" id="journal">' . $this->x($jt) . '</meta>';
            $meta[] = '<meta refines="#journal" property="collection-type">series</meta>';
            if ($iss !== '' && is_numeric($iss)) {
                $meta[] = '<meta refines="#journal" property="group-position">' . $this->x($iss) . '</meta>';
            }
        }
        foreach (['epub' => 'string(//journal-meta/issn[@pub-type="epub" or @publication-format="electronic"])', 'ppub' => 'string(//journal-meta/issn[@pub-type="ppub" or @publication-format="print"])'] as $issnQ) {
            $issn = $this->q($issnQ);
            if ($issn !== '') {
                $meta[] = '<dc:relation>urn:issn:' . $this->x($issn) . '</dc:relation>';
            }
        }
        foreach ($this->xp->query('//article-meta/kwd-group/kwd') as $k) {
            $meta[] = '<dc:subject>' . $this->x($this->norm($k->textContent)) . '</dc:subject>';
        }
        $abs = $this->norm($this->xp->evaluate('string((//article-meta/abstract)[1])'));
        if ($abs !== '') {
            $meta[] = '<dc:description>' . $this->x(mb_strlen($abs) > 600 ? mb_substr($abs, 0, 597) . '...' : $abs) . '</dc:description>';
        }
        $lic = trim($this->xp->evaluate('string(//article-meta/permissions/license/@xlink:href)'));
        if ($lic !== '') {
            $meta[] = '<dc:rights>' . $this->x($lic) . '</dc:rights>';
        }
        $meta[] = '<meta property="dcterms:modified">' . gmdate('Y-m-d\TH:i:s\Z') . '</meta>';
        $meta[] = '<meta property="rendition:layout">reflowable</meta>';
        $meta[] = '<meta property="rendition:flow">auto</meta>';

        $hasImages = (bool)$this->images;
        $allAlt = $hasImages && $this->imgNoAlt === 0;
        $meta[] = '<meta property="schema:accessMode">textual</meta>';
        if ($hasImages) {
            $meta[] = '<meta property="schema:accessMode">visual</meta>';
        }
        $meta[] = '<meta property="schema:accessModeSufficient">textual</meta>';
        $features = ['structuralNavigation', 'tableOfContents', 'readingOrder', 'displayTransformability', 'ARIA'];
        if ($allAlt) {
            $features[] = 'alternativeText';
        }
        if ($this->longDesc) {
            $features[] = 'longDescription';
        }
        if ($this->hasMath) {
            $features[] = 'MathML';
        }
        if ($this->notes) {
            $features[] = 'noteReference';
        }
        foreach ($features as $f) {
            $meta[] = '<meta property="schema:accessibilityFeature">' . $f . '</meta>';
        }
        $meta[] = '<meta property="schema:accessibilityHazard">noFlashingHazard</meta>';
        $meta[] = '<meta property="schema:accessibilityHazard">noMotionSimulationHazard</meta>';
        $meta[] = '<meta property="schema:accessibilityHazard">noSoundHazard</meta>';
        $summary = $this->t('summary');
        if ($this->imgNoAlt > 0) {
            $summary .= $this->lang === 'en' ? ' Some images have no alternative text.' : ' Algunas imágenes no tienen texto alternativo.';
        }
        $meta[] = '<meta property="schema:accessibilitySummary">' . $this->x($summary) . '</meta>';

        $opf = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="' . $this->x($lang) . '">' . "\n"
            . '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n" . implode("\n", $meta) . "\n</metadata>\n"
            . "<manifest>\n" . implode("\n", $manifest) . "\n</manifest>\n"
            . "<spine>\n" . implode("\n", $spine) . "\n</spine>\n</package>\n";

        $zip = new ZipArchive();
        if (file_exists($epubPath)) {
            unlink($epubPath);
        }
        if ($zip->open($epubPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Could not create the ePub file.');
        }
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>' . "\n");
        $zip->addFromString('OEBPS/content.opf', $opf);
        $zip->addFromString('OEBPS/nav.xhtml', $nav->saveXML());
        $zip->addFromString('OEBPS/css/book.css', self::css());
        foreach ($docs as $doc) {
            $zip->addFromString('OEBPS/' . $doc['file'], $this->serialize($doc['dom']));
        }
        foreach ($this->images as $name => $img) {
            $zip->addFile($img['path'], 'OEBPS/' . $name);
        }
        if (!$zip->close()) {
            throw new Exception('Could not write the ePub file.');
        }
    }

    private function navPage(array $docs)
    {
        list($d, $body) = $this->newDoc($this->t('contents'), 'nav.xhtml');
        $nav = $this->e($d, 'nav', ['epub:type' => 'toc', 'role' => 'doc-toc', 'id' => 'toc']);
        $nav->appendChild($this->e($d, 'h1', ['id' => 'toc-h'], $this->t('contents')));
        $nav->appendChild($this->navList($d, array_map(function ($doc) {
            return $doc['toc'];
        }, $docs), 1));
        $body->appendChild($nav);

        $land = $this->e($d, 'nav', ['epub:type' => 'landmarks', 'hidden' => 'hidden']);
        $land->appendChild($this->e($d, 'h2', ['id' => 'landmarks-h'], $this->t('landmarks')));
        $ol = $this->e($d, 'ol');
        $add = function ($type, $href, $text) use ($d, $ol) {
            $li = $this->e($d, 'li');
            $li->appendChild($this->e($d, 'a', ['epub:type' => $type, 'href' => $href], $text));
            $ol->appendChild($li);
        };
        $add('toc', 'nav.xhtml#toc', $this->t('contents'));
        foreach ($docs as $doc) {
            if (in_array($doc['kind'] ?? '', ['chapter', 'text'], true)) {
                $add('bodymatter', $doc['file'], $this->t('start'));
                break;
            }
        }
        if ($this->refNum) {
            $add('bibliography', 'references.xhtml#references', $this->t('refs'));
        }
        $land->appendChild($ol);
        $body->appendChild($land);
        return $d;
    }

    private function navList(DOMDocument $d, array $items, $depth)
    {
        $ol = $this->e($d, 'ol');
        foreach ($items as $it) {
            $li = $this->e($d, 'li');
            $li->appendChild($this->e($d, 'a', ['href' => $it->href], $it->title));
            if ($it->children && $depth < 3) {
                $li->appendChild($this->navList($d, $it->children, $depth + 1));
            }
            $ol->appendChild($li);
        }
        return $ol;
    }

    /* ------------------------------------------------------------------ *
     *  Helpers
     * ------------------------------------------------------------------ */

    /** @return array{0:DOMDocument,1:DOMElement} */
    private function newDoc($title, $file = null)
    {
        $impl = new DOMImplementation();
        $d = $impl->createDocument(self::XHTML, 'html', $impl->createDocumentType('html'));
        $d->encoding = 'UTF-8';
        $root = $d->documentElement;
        $root->setAttributeNS(self::XMLNS, 'xmlns:epub', self::OPS);
        $root->setAttribute('lang', $this->lang);
        $root->setAttributeNS(self::XML_NS, 'xml:lang', $this->lang);
        $head = $this->e($d, 'head');
        $head->appendChild($this->e($d, 'meta', ['charset' => 'utf-8']));
        $head->appendChild($this->e($d, 'title', [], $title !== '' ? $title : 'Article'));
        $head->appendChild($this->e($d, 'link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => 'css/book.css']));
        $root->appendChild($head);
        $body = $this->e($d, 'body');
        $root->appendChild($body);
        return [$d, $body];
    }

    private function e(DOMDocument $d, $name, array $attrs = [], $text = null)
    {
        $el = $d->createElementNS(self::XHTML, $name);
        foreach ($attrs as $k => $v) {
            if ($v === null) {
                continue;
            }
            if ($k === 'epub:type') {
                $el->setAttributeNS(self::OPS, 'epub:type', $v);
            } elseif ($k === 'xml:lang') {
                $el->setAttributeNS(self::XML_NS, 'xml:lang', $v);
            } else {
                $el->setAttribute($k, $v);
            }
        }
        if ($text !== null) {
            $el->appendChild($d->createTextNode($text));
        }
        return $el;
    }

    private function q($expr)
    {
        return $this->norm($this->xp->evaluate($expr));
    }

    private function norm($s)
    {
        return trim(preg_replace('/\s+/u', ' ', (string)$s));
    }

    private function x($s)
    {
        return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function t($key, $lang = null)
    {
        $l = $lang ?: $this->lang;
        $tr = self::$I18N[$l] ?? self::$I18N['en'];
        return $tr[$key] ?? self::$I18N['en'][$key] ?? $key;
    }

    private function pubDate()
    {
        $n = $this->xp->query('//article-meta/pub-date[@publication-format="electronic" or @date-type="pub"]')->item(0)
            ?: $this->xp->query('//article-meta/pub-date')->item(0);
        if (!$n) {
            return '';
        }
        $y = $this->norm($this->xp->evaluate('string(year)', $n));
        $m = str_pad($this->norm($this->xp->evaluate('string(month)', $n)), 2, '0', STR_PAD_LEFT);
        $dd = str_pad($this->norm($this->xp->evaluate('string(day)', $n)), 2, '0', STR_PAD_LEFT);
        if ($y === '') {
            return '';
        }
        return $y . (preg_match('/^\d\d$/', $m) && $m !== '00' ? '-' . $m . (preg_match('/^\d\d$/', $dd) && $dd !== '00' ? '-' . $dd : '') : '');
    }

    private function uuid($seed)
    {
        $h = md5($seed);
        return 'urn:uuid:' . substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3) . '-a' . substr($h, 17, 3) . '-' . substr($h, 20, 12);
    }

    private static function css()
    {
        return <<<'CSS'
@charset "utf-8";
html { font-size: 100%; }
body { box-sizing: border-box; max-width: 48em; margin: 1.25em auto; padding: 2.5em 3.125em; background-color: #fcfbfa; color: #2c2c2c; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 1em; line-height: 1.7; text-align: left; hyphens: manual; orphans: 2; widows: 2; }
h1, h2, h3, h4, h5, h6 { font-family: Georgia, "Times New Roman", serif; font-weight: 300; line-height: 1.3; text-align: left; margin: 1.8em 0 0.8em; page-break-after: avoid; break-after: avoid; }
h1 { font-size: 1.8em; text-transform: uppercase; letter-spacing: 0.0625em; border-bottom: 0.0625em solid #e5e5e0; padding-bottom: 0.4em; }
h2 { font-size: 1.4em; }
h3 { font-size: 1.2em; }
h4, h5, h6 { font-size: 1.05em; }
p { margin: 0 0 0.9em; text-indent: 1.5em; text-align: justify; text-justify: inter-word; }
h1 + p, h2 + p, h3 + p, h4 + p, h5 + p, h6 + p, div + p, section + p { text-indent: 0; }
a { text-decoration: underline; overflow-wrap: anywhere; }
a:focus { outline: 0.15em solid currentColor; outline-offset: 0.1em; }
sup, sub { font-size: 0.75em; line-height: 0; }
a.noteref { font-size: 0.8em; vertical-align: super; line-height: 0; text-decoration: none; }
.sc { font-variant: small-caps; }
.subtitle { font-size: 1.15em; font-style: italic; margin-top: 0; }
.trans-title { margin: 0 0 0.4em; }
ul.authors, ul.affiliations, section.affiliations ul { list-style: none; margin: 0.8em 0; padding: 0; }
ul.authors li, section.affiliations li { margin: 0 0 0.35em; }
.author-name { font-weight: bold; }
dl.article-data { margin: 1.4em 0; }
dl.article-data dt { font-weight: bold; margin-top: 0.7em; }
dl.article-data dd { margin: 0 0 0 1em; overflow-wrap: anywhere; }
.keywords { margin-top: 0.8em; }
blockquote { font-style: italic; margin: 1.5em 2em; padding-left: 1.2em; border-left: 0.1875em solid #007aff; color: inherit; }
.attrib { text-align: right; font-size: 0.9em; }
aside.boxed { margin: 1em 0; padding: 0.6em 1em; border: 0.08em solid; }
figure { margin: 1.3em 0; padding: 0; text-align: center; page-break-inside: avoid; break-inside: avoid; }
figure img { max-width: 100%; height: auto; }
figcaption { font-size: 0.85em; text-align: left; margin-top: 0.5em; font-style: italic; }
figcaption p { margin-bottom: 0.4em; }
details.long-desc { text-align: left; font-size: 0.9em; margin-top: 0.5em; }
figure.table-wrap { text-align: left; }
figure.table-wrap figcaption { margin: 0 0 0.5em; }
table { border-collapse: collapse; width: 100%; margin: 0.6em 0; font-size: 0.9em; }
th, td { border: 0.07em solid; padding: 0.35em 0.5em; text-align: left; vertical-align: top; }
th { font-weight: bold; }
.al-center { text-align: center; }
.al-right { text-align: right; }
.table-foot { font-size: 0.85em; }
.formula { margin: 1em 0; text-align: center; overflow-wrap: anywhere; }
ol.references { padding-left: 1.6em; }
ol.references li { margin: 0 0 0.7em; overflow-wrap: anywhere; }
aside[role="doc-footnote"] { margin: 0 0 0.9em; font-size: 0.95em; }
.backlink { margin: 0; }
nav ol { list-style: none; padding-left: 1em; }
nav > ol { padding-left: 0; }
nav li { margin: 0.3em 0; }
[hidden] { display: none; }
.visually-hidden { position: absolute; width: 0.0625em; height: 0.0625em; margin: -0.0625em; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
@media (max-width: 40em) {
  body { margin: 0 auto; padding: 1.25em; }
  blockquote { margin-right: 0.5em; margin-left: 0.5em; }
}
CSS;
    }
}
