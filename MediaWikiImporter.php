<?php

class MediaWikiImporter
{
    public function removeSectionsDOMDocument(DOMNode $node)
    {
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

    public function removeSections(\PHPHtmlParser\Dom\Node\HtmlNode $node)
    {
        $markForRemove = false;
        $indicesToRemove = [];
        $childrenCount = $node->countChildren();
        $children = $node->getChildren();
        for ($i = 0; $i < $childrenCount; $i++) {
            $subnode = $children[$i];
            if ($subnode instanceof \PHPHtmlParser\Dom\Node\HtmlNode && $subnode->getTag()->name() === 'h2') {
                $headingContent = trim($subnode->innerText());
                $markForRemove = $headingContent === 'References' ||
                    $headingContent === 'Gallery' ||
                    $headingContent === 'Bugs' ||
                    $headingContent === 'Behind the scenes' ||
                    $headingContent === 'Appearances';
            }
            if ($markForRemove) {
                $indicesToRemove[] = $subnode->id();
            }
        }

        foreach ($indicesToRemove as $id) {
            $node->removeChild($id);
        }
    }

    public function fixUrlsDOMDocument(DOMNode $node, $urlPrefix)
    {
        if ($node instanceof DOMElement && $node->tagName === 'a') {
            $href = $node->getAttribute('href');
            if ($href && strpos($href, '://') === false && substr($href, 0, 1) !== '#') {
                $href = $urlPrefix . str_replace('/wiki', '', $href);
                $node->setAttribute('href', $href);
            }
        }

        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $subnode = $node->childNodes[$i];
            if ($subnode instanceof DOMElement && $subnode->getAttribute('class') === 'printfooter') {
                $node->removeChild($subnode);
            } elseif ($subnode instanceof DOMElement && $subnode->getAttribute('class') === 'mw-editsection') {
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

    public function fixUrls($node, $urlPrefix)
    {
        if ($node instanceof \PHPHtmlParser\Dom\Node\HtmlNode && $node->getTag()->name() === 'a') {
            $href = $node->getAttribute('href');
            if ($href && strpos($href, '://') === false && substr($href, 0, 1) !== '#') {
                $href = $urlPrefix . str_replace('/wiki', '', $href);
                $node->setAttribute('href', $href);
            }
        }

        $childrenCount = $node->countChildren();
        $children = $node->getChildren();
        for ($i = $childrenCount - 1; $i >= 0; $i--) {
            $subnode = $children[$i];
            if ($subnode instanceof \PHPHtmlParser\Dom\Node\HtmlNode) {
                if ($subnode->getAttribute('class') === 'printfooter') {
                    $node->removeChild($i);
                } elseif ($subnode->getAttribute('class') === 'mw-editsection') {
                    $node->removeChild($i);
                } elseif (strpos($subnode->getAttribute('class'), 'va-navbox-bottom') !== false) {
                    $node->removeChild($i);
                } elseif ($subnode->getTag()->name() === '!--') {
                    $node->removeChild($i);
                } else {
                    self::fixUrls($subnode, $urlPrefix);
                }
            }
        }
    }

    public function removeAllAttributes($contentDiv)
    {
        $contentDivAttributeNames = [];
        foreach ($contentDiv->attributes as $contentDivAttribute) {
            $contentDivAttributeNames[] = $contentDivAttribute->name;
        }
        foreach ($contentDivAttributeNames as $contentDivAttributeName) {
            $contentDiv->removeAttribute($contentDivAttributeName);
        }
    }

    public function extractContentHtml($pageHtml, $urlPrefix)
    {
        $html = preg_replace('~.*<div class="mw-parser-output"><p>~s', '<div class="mw-parser-output">', $pageHtml);
        //echo '<pre>' . htmlspecialchars($html);exit;
        $dom = new \PHPHtmlParser\Dom();
        $dom->loadStr($html);
        $contentList = $dom->find('.mw-parser-output');
        if (count($contentList) === 0) {
            throw new RuntimeException("Cannot find div.mw-parser-output element.");
        }
        /** @var \PHPHtmlParser\Dom\Node\HtmlNode $contentDiv */
        $contentDiv = $contentList[0];
        self::removeSections($contentDiv);
        self::fixUrls($contentDiv, $urlPrefix);
        $html = $contentDiv->innerHtml();

        /*$doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        $contentList = $xpath->query('//div[@class="mw-parser-output"]');
        if ($contentList->length === 0) {
            throw new RuntimeException("Cannot find div.mw-parser-output element.");
        }
        $contentDiv = $contentList[0];
        self::removeSectionsDOMDocument($contentDiv);
        self::fixUrlsDOMDocument($contentDiv, '/' . pathinfo($page, PATHINFO_DIRNAME));
        self::removeAllAttributes($contentDiv);

        $html = $doc->saveXML($contentDiv);
        $html = substr($html, strlen('<div>'), -strlen('</div>'));
        */

        /*$doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $doc->loadXML('<?xml version="1.0" encoding="UTF-8"?><root>' . $html . '</root>');
        $html = $doc->saveXML($doc);*/

        $html = str_replace("<", "\n<", $html);
        //echo '<pre>' . htmlspecialchars($html);exit;
        return $html;
    }

    public function importPage($page)
    {
        if (strpos($page, '.fandom.com') === false) {
            return false;
        }
        $html = file_get_contents('https://' . $page);
        $html = self::extractContentHtml($html, '/' . pathinfo($page, PATHINFO_DIRNAME));
        file_put_contents(LIBRARY . '/' . $page, $html);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}
