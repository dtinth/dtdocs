<?php

require 'markdown.inc';
require 'config.php';

error_reporting(E_ALL);

function h($x) { return htmlspecialchars($x); }
function u($x) { return urlencode($x); }
function a($name, $value) {
	$value = htmlspecialchars($value);
	return ' ' . $name . '=' . ($value != '' && !preg_match('~[\s"\'=<>`]~', $value) ? $value : '"' . $value . '"');
}

class Page {
	
	var $name;
	var $hash = '';

	function __construct($name) {
		$this->name = $name;
		$this->splitHash();
		$this->normalize();
	}

	function parent() {
		if ($this->name == '') {
			return false;
		}
		return new self(preg_replace('~(^|/)([a-zA-Z0-9]+/?)$~', '$1', $this->name));
	}

	function splitHash() {
		$pos = strpos($this->name, '#');
		if ($pos !== false) {
			$this->hash = substr($this->name, $pos);
			$this->name = substr($this->name, 0, $pos);
			if ($this->hash == '#') {
				$this->hash = '';
			}
		} else {
			$this->hash = '';
		}
	}

	function normalize() {
		$this->name = preg_replace('~(^|/)Index$~', '$1', $this->name);
	}

	function valid() {
		return !!preg_match('~^$|^[a-zA-Z0-9]+(/[a-zA-Z0-9]+)*(/[a-zA-Z0-9]*)?$~', $this->name);
	}

	function filename() {
		$vname = $this->name;
		if ($vname == '' || substr($vname, -1) == '/') {
			$vname .= 'Index';
		}
		return DATA_DIR . '/' . $vname . '.md';
	}

	function href() {
		if (URL_REWRITTEN) {
			if ($this->name == '') {
				return URL_BASE . '/' . $this->hash;
			}
			return URL_BASE . '/' . $this->name . $this->hash;
		}
		if ($this->name == '') {
			return '.' . $this->hash;
		}
		return './?page=' . str_replace('%2F', '/', urlencode($this->name)) . $this->hash;
	}

	function basename() {
		preg_match('~([^/]*)/?$~', $this->name, $match);
		return $match[1];
	}

	function exists() {
		return $this->valid() && file_exists($this->filename());
	}

	function resolve() {
		if ($this->exists()) {
			return true;
		}
		if ($this->valid()) {
			if (substr($this->name, -1) == '/') {
				$this->name = substr($this->name, 0, -1);
			} else {
				$this->name = $this->name . '/';
			}
			if ($this->exists()) {
				return true;
			}
		}
		$this->name = '404';
		return false;
	}

	function rawContents() {
		return file_get_contents($this->filename());
	}

	function readMetadata($text) {
		$lines = explode("\n", $text);
		$o = array();
		foreach ($lines as $v) {
			$v = trim($v);
			if (preg_match('~^([a-z]+):\s*~', $v, $title)) {
				$o[$title[1]] = substr($v, strlen($title[0]));
			}
		}
		return $o;
	}

	function contents(&$outMeta) {
		$content = $this->rawContents();
		if (preg_match('~^\s*(([a-z]+:\s*)(.+?)\s*\n\s*)*~', $content, $meta)) {
			$content = substr($content, strlen($meta[0]));
			$outMeta = $this->readMetadata($meta[0]);
		} else {
			$outMeta = array();
		}
		return $content;
	}

	function navigate($target) {
		$hashpos = strpos($target, '#');
		$hash = '';
		if ($hashpos !== false) {
			$hash = substr($target, $hashpos);
			$target = substr($target, 0, $hashpos);
			if ($hash == '#') {
				$hash = '';
			}
		}
		if ($target == '') {
			return $this->name . $hash;
		}
		$parts  = explode('/', $this->name);
		$target = explode('/', $target);
		array_pop($parts);
		foreach ($target as $k => $v) {
			if ($k == 0 && $v == '') {
				$parts = array();
				continue;
			}
			if ($v == '..') {
				array_pop($parts);
				continue;
			}
			if ($v == '.') {
				continue;
			}
			$parts[] = $v;
		}
		return new self(implode('/', $parts) . $hash);
	}

}

class TocNode {

	var $parent, $level, $ref;
	var $children = array();

	function __construct($parent, $level, $ref) {
		$this->parent = $parent;
		$this->ref = $ref;
		$this->level = $level;
	}

	function render($doc) {
		$frag = $doc->createDocumentFragment();
		if ($this->ref) {
			$a = $doc->createElement('a');
			$a->setAttribute('class', 'toc-ref');
			$a->setAttribute('href', '#' . $this->ref->getAttribute('id'));
			foreach ($this->ref->childNodes as $child) {
				$a->appendChild($doc->importNode($child->cloneNode(true), true));
			}
			$frag->appendChild($a);
		}
		if (!empty($this->children)) {
			$ul = $doc->createElement('ul');
			foreach ($this->children as $child) {
				$li = $doc->createElement('li');
				$li->appendChild($child->render($doc));
				$ul->appendChild($li);
			}
			$frag->appendChild($ul);
		}
		return $frag;
	}

}

class DtDOMUtil {

	static function renderChildren($nodeList) {
		foreach ($nodeList as $node) {
			self::renderNode($node);
		}
	}

	static function renderNode($node) {
		if ($node->nodeType == XML_TEXT_NODE) {
			if (preg_match('~\S~', $node->nodeValue))
				echo htmlspecialchars($node->nodeValue);
			return;
		}
		$tag = strtolower($node->nodeName);
		$close = !in_array($tag, array(
			'li', 'td', 'img', 'br', 'hr'
		));
		$newlines = in_array($tag, array(
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'p', 'ul', 'ol', 'li'
		));
		$newlines2 = in_array($tag, array(
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6'
		));
		$newlines3 = in_array($tag, array(
			'ul', 'ol'
		));
		if ($newlines) echo "\n";
		if ($newlines2) echo "\n";
		echo '<' . $tag;
		foreach ($node->attributes as $v) {
			echo a($v->name, $v->value);
		}
		echo '>';
		self::renderChildren($node->childNodes);
		if ($node->childNodes->length > 0 && $newlines3) echo "\n";
		if ($close) echo '</' . strtolower($node->nodeName) . '>';
	}

	static function splitText(DOMText &$node, $start, $end, &$offset) {
		if (!isset($offset)) {
			$offset = 0;
		}
		$node   = $node->splitText($start - $offset); $offset = $start;
		$middle = $node;
		$node   = $node->splitText($end - $offset);   $offset = $end;
		return $middle;
	}

	static function matchTextByRegex($pattern, DOMText &$node) {
		$out = array();
		if (preg_match_all($pattern, $node->nodeValue, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
			foreach ($matches as $match) {
				$start    = $match[0][1];
				$end      = $start + strlen($match[0][0]);
				$matched  = self::splitText($node, $start, $end, $offset);
				$out[]    = $match + array('node' => $matched);
			}
		}
		return $out;
	}

}

class PageTreeTransformer {

	var $page;
	var $xp, $doc;

	function __construct($page) {
		$this->page = $page;
	}

	var $id = 0;

	function id($node) {
		$this->id ++;
		if ($node->lastChild && $node->lastChild->nodeType == XML_TEXT_NODE) {
			if (preg_match('~\s+#([a-zA-Z0-9\-_]+)\s*$~', $node->lastChild->nodeValue, $match, PREG_OFFSET_CAPTURE)) {
				$node->lastChild->nodeValue = substr($node->lastChild->nodeValue, 0, $match[0][1]);
				return $match[1][0];
			}
		}
		return $node->nodeName . '_' . $this->id . $this->idtext($node);
	}

	function idtext($node) {
		if ($node->firstChild && $node->firstChild->nodeType == XML_TEXT_NODE) {
			$id = strtolower(trim(preg_replace('~\W+~', '_', $node->firstChild->nodeValue), '_'));
			if ($id !== '') {
				return '_' . $id;
			}
		}
		return '';
	}

	function query($xpath) {
		return $this->xp->query($xpath);
	}

	private $_toc, $_toc_fragment;

	function getToc() {
		if ($this->_toc) {
			return $this->_toc;
		}
		$root = new TocNode(NULL, 0, NULL);
		$current = $root;
		foreach ($this->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]') as $node) {
			$level = intval(substr($node->nodeName, 1));
			while ($current->level > $level - 1) {
				$current = $current->parent;
			}
			while ($current->level < $level - 1) {
				$new = new TocNode($current, $current->level + 1, NULL);
				$current->children[] = $new;
				$current = $new;
			}
			$new = new TocNode($current, $current->level + 1, $node);
			$current->children[] = $new;
			$current = $new;
		}
		$this->_toc = $root;
		return $root;
	}

	function createToc($toc) {
		$toc->appendChild($this->getToc()->render($this->doc));
	}

	function processClassId($node) {
		if ($node->firstChild && $node->firstChild->nodeType == XML_TEXT_NODE) {
			if (preg_match('~^\s*([#\.][a-zA-Z0-9\-_]+)*:\s~', $node->firstChild->nodeValue, $match)) {
				$node->firstChild->nodeValue = substr($node->firstChild->nodeValue, strlen($match[0]));
				$mode = null;
				foreach (preg_split('~([#\.])~', $match[1], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $v) {
					if ($v == '.') $mode = 'class';
					else if ($v == '#') $mode = 'id';
					else if ($mode == 'id') $node->setAttribute('id', $v);
					else if ($mode == 'class') $node->setAttribute('class', $node->hasAttribute('class') ? $node->getAttibute('class') . ' ' . $v : $v);
				}
			}
		}		
	}

	function replaceTwitter($textnode) {
		foreach (DtDOMUtil::matchTextByRegex('~@[a-zA-Z0-9_]+~', $textnode) as $match) {
			$twitter = $match['node'];
			$a = $textnode->ownerDocument->createElement('a');
			$a->setAttribute('href', 'https://twitter.com/' . substr($match[0][0], 1));
			$twitter->parentNode->replaceChild($a, $twitter);
			$a->appendChild($twitter);
		}
	}

	function replaceImageLink($textnode) {
		foreach (DtDOMUtil::matchTextByRegex('~\[\[Image:([^\s\]]+)(?: (.*?))?\]\]~i', $textnode) as $match) {
			$image = $match['node'];
			$img = $textnode->ownerDocument->createElement('img');
			$img->setAttribute('src', IMAGE_URL($match[1][0]));
			$img->setAttribute('alt', empty($match[2][0]) ? basename($match[1][0]) : $match[2][0]);
			$image->parentNode->replaceChild($img, $image);
		}
	}


	function replaceWikiLink($open, $close, $match) {
		$frag = $open->ownerDocument->createDocumentFragment();
		for ($node = $open->nextSibling; $node && $node !== $close; $node = $next) {
			$next = $node->nextSibling;
			$frag->appendChild($node);
		}
		$title = $match[1][0];
		$replacement = $frag;
		if (preg_match('~^[a-zA-Z0-9-#/\.]+$~', $title)) {
			$page = $this->page->navigate($title);
			if ($page->valid()) {
				$replacement = $open->ownerDocument->createElement('a');
				$replacement->setAttribute('href', $page->href());
				$empty = true;
				foreach ($frag->childNodes as $v) {
					if ($v->nodeType == XML_TEXT_NODE) {
						if (!$v->isWhitespaceInElementContent()) {
							$empty = false;
							break;
						}
					} else if ($v->nodeType == XML_ELEMENT_NODE) {
						$empty = false;
						break;
					}
				}
				if ($empty) {
					$frag->appendChild($open->ownerDocument->createTextNode($title));
				}
				$replacement->appendChild($frag);
			}
		}
		$close->parentNode->insertBefore($replacement, $close);
		if ($replacement !== $frag) {
			$open->parentNode->removeChild($open);
			$close->parentNode->removeChild($close);
		}
	}

	function findReplaceWikiLink($match) {
		$open = $match['node'];
		for ($node = $open->nextSibling; $node; $node = $node->nextSibling) {
			if ($node->nodeType == XML_TEXT_NODE && $node->nodeValue == ']]') {
				$this->replaceWikiLink($open, $node, $match);
				break;
			}
		}
	}

	function wikiLinks() {
		$open = array();
		foreach ($this->query('//text()[not(ancestor::a or ancestor::pre or ancestor::code)]') as $node) {
			$temp = DtDOMUtil::matchTextByRegex('~\[\[([^\s\]]+)~', $node);
			$open = array_merge($open, $temp);
		}
		foreach ($this->query('//text()[not(ancestor::a or ancestor::pre or ancestor::code)]') as $node) {
			DtDOMUtil::matchTextByRegex('~\]\]~', $node);
		}
		foreach ($open as $match) {
			$this->findReplaceWikiLink($match);
		}
		$this->doc->normalize();
	}

	function transformProc() {
		
		// add classes and ID based on the text it found
		foreach ($this->query('//*[text()]') as $node) {
			$this->processClassId($node);
		}

		// assign ID to headings
		foreach ($this->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]') as $node) {
			$node->setAttribute('id', $this->id($node));
		}

		// create table of contents
		foreach ($this->query('//div[@id="dtdocs-toc"][@class="toc"]') as $node) {
			$this->createToc($node);
			break;
		}

		// add external links
		foreach ($this->query('//a') as $node) {
			if (!$node->hasAttribute('class')) {
				$node->setAttribute('class', 'external');
			}
		}

		// add class to image containers
		foreach ($this->query('//*[self::p or self::li][img[@class="image"][not(preceding-sibling::*)][not(following-sibling::*)]]') as $node) {
			if (!$node->hasAttribute('class')) {
				$node->setAttribute('class', 'image-container');
			}
		}

		// replace twitter links
		foreach ($this->query('//text()[not(ancestor::a or ancestor::pre or ancestor::code)]') as $node) {
			$this->replaceTwitter($node);
		}
		$this->doc->normalize();

		// replace image links
		foreach ($this->query('//text()[not(ancestor::a or ancestor::pre or ancestor::code)]') as $node) {
			$this->replaceImageLink($node);
		}
		$this->doc->normalize();

		// replace wiki links
		$this->wikiLinks();

	}

	function transform($text) {
		$this->doc = new DOMDocument;
		$this->doc->loadHTML('<html><body>' . $text . '</body></html>');
		$this->xp = new DOMXPath($this->doc);
		$this->transformProc();
		ob_start();
		DtDOMUtil::renderChildren($this->doc->getElementsByTagName('body')->item(0)->childNodes);
		return ob_get_clean();
	}

}

class PageContentRenderer {

	var $page;
	var $tree;

	function __construct($page) {
		$this->page = $page;
	}

	function cb_link($match) {
		$page = $text = $match[1];
		if (!empty($match[2])) {
			$text = $match[2];
		}
		$page = $this->page->navigate($page);
		if (!$page->valid()) {
			return $match[0];
		}
		return '<a href="' . h($page->href()) . '" class="page">'. $text . '</a>';
	}

	function cb_key($match) {
		return '<span class="key">' . $match[1] . '</span>';
	}

	function cb_twitter($match) {
		return '<a href="' . h('https://twitter.com/' . substr($match[1], 1)) . '">' . $match[0] . '</a>';
	}

	function cb_image($match) {
		$alt = $match[1];
		if (!empty($match[2])) {
			$alt = $match[2];
		}
		return '<img' . a('class', 'image') . a('src', IMAGE_URL($match[1])) . a('alt', $alt) . '>';
	}

	function process($text) {
		
		// simple processing!
		$text = preg_replace_callback('~\|([^\s\|]+)\|~si', array($this, 'cb_key'), $text);
		$text = str_replace('{{toc}}', '<div id="dtdocs-toc" class="toc"></div>', $text);
		$text = str_replace('{{beta}}', '<span class="beta">BETA</span>', $text);

		// markdown process!
		$text = Markdown($text);

		// page tree process!
		$this->tree = new PageTreeTransformer($this->page);
		$text = $this->tree->transform($text);

		return $text;

	}

	function render($text) {
		echo $this->process($text);
	}

}

class TemplateFileRenderer {

	var $context, $name;

	function __construct($context, $name) {
		$this->context = $context;
		$this->name = $name;
	}

	function filename() {
		return './Templates/' . $this->name . '.php';
	}

	function render($content) {
		$this->context->includeHere($this->filename(), array('content' => $content));
	}

}

class PageRenderer {

	var $page;
	var $meta = array(
		'name' => array(),
		'breadcrumb' => array(),
		'theme' => 'default',
		'class' => '',
		'book' => 'DtDocs',
		'bookdef' => '',
	);
	var $stack = array();

	function __construct($page) {
		$this->page = $page;
	}

	function beginContent($renderer) {
		ob_start();
		if (is_string($renderer)) {
			$renderer = new TemplateFileRenderer($this, $renderer);
		}
		array_push($this->stack, array('renderer' => $renderer));
	}

	function endContent() {
		$content = ob_get_clean();
		$options = array_pop($this->stack);
		$renderer = $options['renderer'];
		$renderer->render($content);
	}

	function includeHere() {
		extract(func_get_arg(1));
		include(func_get_arg(0));
	}

	function redirect($location) {
		header('Location: ' . $location);
		exit;
	}

	function addMeta($page, $array) {
		if (!isset($array['name']))       $array['name']       = $page->basename();
		if (!isset($array['title']))      $array['title']      = $page->basename();
		if (!isset($array['breadcrumb'])) $array['breadcrumb'] = $page->name;
		if (isset($array['book']))        $array['bookdef']    = $page->name;
		foreach ($array as $k => $v) {
			$this->setMeta($k, $v);
		}
	}

	function setMeta($k, $v) {
		if (isset($this->meta[$k]) && is_array($this->meta[$k])) {
			$this->meta[$k][] = $v;
		} else {
			$this->meta[$k] = $v;
		}
	}

	function render() {
		if ($this->page->resolve()) {
			if (isset($_GET['page'])) {
				if ($this->page->name == '' || $this->page->name != $_GET['page']) {
					$this->redirect($this->page->href());
				}
			}
		}

		$metas = array();
		$this->beginContent('main');
		$this->beginContent(new PageContentRenderer($this->page));

		echo $this->page->contents($meta);
		$this->addParentMetas($this->page->parent());
		$this->addMeta($this->page, $meta);

		$this->endContent();
		$this->endContent();
	}

	function renderPartial($template) {
		$this->beginContent($template);
		$this->endContent();
	}

	function addParentMetas($page) {
		if (!$page) return false;
		if ($page->exists()) {
			$page->contents($meta);
		}
		$this->addParentMetas($page->parent());
		$this->addMeta($page, $meta);
	}

}

$page = new Page(isset($_GET['page']) ? $_GET['page'] : '');
$renderer = new PageRenderer($page);
$renderer->render();

