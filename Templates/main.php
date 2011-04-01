<!DOCTYPE html><html<?php echo a('class', 'theme-' . $this->meta['theme']); ?>>
<head>
	<meta charset="utf-8"><meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<title><?php echo h($this->meta['title']); ?> &#x2605; <?php echo h($this->meta['book']); ?></title>
	<link rel="stylesheet" href="<?php echo URL_BASE; ?>/CSS/Main.css">
</head>
<body<?php if ($this->meta['class'] != '') echo a('class', $this->meta['class']); ?>>

<div id="dtdocs-title">
<div id="dtdocs-breadcrumb">
<?php

foreach ($this->meta['breadcrumb'] as $k => $v) {
	$first = $k == 0;
	$last = $k == count($this->meta['breadcrumb']) - 1;
	if (!$first) {
		echo ' <span class="separator">&raquo;</span> ';
	}
	$page = new Page($v);
	if ($last) {
		echo '<strong>' . h($this->meta['name'][$k]) . '</strong>';
	} else {
		echo '<a' . a('href', $page->href()) . '>' . h($this->meta['name'][$k]) . '</a>';
	}
}

?>
</div>
<div id="dtdocs-book"><a<?php
	$page = new Page($this->meta['bookdef']);
	echo a('class', 'name'), a('href', $page->href());
?>><?php echo h($this->meta['book']); ?></a></div>
</div>

<div id="dtdocs-content"><?php echo $content, "\n\n"; ?></div>

<div id="dtdocs-footer"><?php $this->renderPartial('footer'); ?></div>

</body>
</html>
