<?php

class MediaWikiImporter {
	static function removeSections(DOMNode $node) {
		$markForRemove = false;
		$indicesToRemove = [];
		for ($i = 0; $i < $node->childNodes->length; $i++) {
			$subnode = $node->childNodes[$i];
			if ($subnode instanceof DOMElement && $subnode->nodeName === 'h2') {
				$headingContent = $subnode->textContent;
				$markForRemove = $headingContent === 'References' || 
					$headingContent === 'Gallery' || 
					$headingContent === 'Bugs' || 
					$headingContent === 'Behind the scenes' || 
					$headingContent === 'Appearances';
			}
			if ($markForRemove) {
				$indicesToRemove[] = $i;
			}
		}
			
		for ($i = count($indicesToRemove) - 1; $i >= 0; $i--) {
			$subnode = $node->childNodes[$indicesToRemove[$i]];
			$node->removeChild($subnode);
		}
	}
	
	static function fixUrls(DOMNode $node, $urlPrefix) {
		if ($node instanceof DOMElement && $node->tagName === 'a') {
			$href = $node->getAttribute('href');
			if ($href && strpos($href, '://') === false) {
				$href = $urlPrefix . str_replace('/wiki', '', $href);
				$node->setAttribute('href', $href);
			}
		}
		
		for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
			$subnode = $node->childNodes[$i];
			if ($subnode instanceof DOMElement && $subnode->getAttribute('class') === 'mw-editsection') {
				$node->removeChild($subnode);
			} elseif ($subnode instanceof DOMElement && strpos($subnode->getAttribute('class'), 'va-navbox-bottom') !== false) {
				$node->removeChild($subnode);
			} elseif ($subnode instanceof DOMComment) {
				$node->removeChild($subnode);
			} else {
				self::fixUrls($subnode, $urlPrefix);
			}
		}
	}
	
	static function importPage($page) {
		if (strpos($page, '.fandom.com') === false) {
			return false;
		}
		$url = 'https://' . $page;
		$html = file_get_contents($url);
		$doc = new DOMDocument();
		@$doc->loadHTML($html);
		$xpath = new DOMXPath($doc);
		$h1List = $xpath->query('//h1');
		if ($h1List->length === 0) {
			throw new RuntimeException("Cannot find h1 element.");
		}

		$contentList = $xpath->query('//div[@class="mw-parser-output"]');
		if ($contentList->length === 0) {
			throw new RuntimeException("Cannot find div.mw-parser-output element.");
		}
		$contentDiv = $contentList[0];
		self::removeSections($contentDiv);
		//$urlPrefix = preg_replace('~^(https?://[^/]+).*$~', '\\1', $url);
		$urlPrefix = '/' . pathinfo($page, PATHINFO_DIRNAME);
		self::fixUrls($contentDiv, $urlPrefix);
		$contentDivAttributeNames = [];
		foreach ($contentDiv->attributes as $contentDivAttribute) {
			$contentDivAttributeNames[] = $contentDivAttribute->name;
		}
		foreach ($contentDivAttributeNames as $contentDivAttributeName) {
			$contentDiv->removeAttribute($contentDivAttributeName);
		}
		$contentHtml = $doc->saveXML($contentDiv);
		$contentHtml = substr($contentHtml, strlen('<div>'), -strlen('</div>'));
		file_put_contents(LIBRARY . '/' . $page, $contentHtml);
		header('Location: ' . $_SERVER['REQUEST_URI']);
		exit;
	}
}
