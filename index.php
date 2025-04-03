<?php
// Target URL
$url = "https://nnn.ed.jp/news/";

// Get HTML
$html = file_get_contents($url);
if ($html === false) {
  header("HTTP/1.1 500 Internal Server Error");
  exit("Failed to retrieve content.");
}

// Suppress HTML parsing errors
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML($html);
$xpath = new DOMXPath($doc);

// Get site title (content of <title> tag)
$titleNodes = $doc->getElementsByTagName('title');
$siteTitle = ($titleNodes->length > 0) ? trim($titleNodes->item(0)->textContent) : 'No Title';

// Array to store RSS items
$items = [];

// Get elements corresponding to "#post-list-all-all a" using XPath
$anchors = $xpath->query("//*[@id='post-list-all-all']//a");

foreach ($anchors as $anchor) {
  // Title (text of h2 element)
  $h2Nodes = $xpath->query(".//h2", $anchor);
  $title = ($h2Nodes->length > 0) ? trim($h2Nodes->item(0)->textContent) : "";

  // Date (text of time element) → Convert to ISO format
  $timeNodes = $xpath->query(".//time", $anchor);
  $dateString = ($timeNodes->length > 0) ? trim($timeNodes->item(0)->textContent) : "";
  $date = "";
  if (!empty($dateString)) {
    try {
      $datetime = new DateTime($dateString);
      $date = $datetime->format(DateTime::ATOM);
    } catch (Exception $e) {
      $date = $dateString;
    }
  }

  // Category (text of span element)
  $spanNodes = $xpath->query(".//span", $anchor);
  $category = ($spanNodes->length > 0) ? trim($spanNodes->item(0)->textContent) : "";

  // Link (href attribute of a element)
  $href = $anchor->attributes->getNamedItem('href')->nodeValue;
  // Handle cases where href doesn't start with '/'
  $fullUrl = "https://nnn.ed.jp/" . ltrim($href, "/");

  $items[] = [
    "title"    => $title,
    "date"     => $date,
    "url"      => $fullUrl,
    "category" => $category
  ];
}

// Generate RSS XML using DOMDocument
$rssDoc = new DOMDocument('1.0', 'UTF-8');
$rssDoc->formatOutput = true; // Format output

// Create root element <rss>
$rss = $rssDoc->createElement('rss');
$rss->setAttribute('version', '2.0');
$rssDoc->appendChild($rss);

// Create <channel> element and add to root
$channel = $rssDoc->createElement('channel');
$rss->appendChild($channel);

// Add basic information to <channel>
$channel->appendChild($rssDoc->createElement('title', $siteTitle));
$channel->appendChild($rssDoc->createElement('link', 'https://nnn.ed.jp/news/'));
$channel->appendChild($rssDoc->createElement('description', 'This RSS feed was automatically generated from the website.'));

// Add each article as an <item>
foreach ($items as $item) {
  $itemElem = $rssDoc->createElement('item');
  $itemElem->appendChild($rssDoc->createElement('title', $item['title']));
  $itemElem->appendChild($rssDoc->createElement('link', $item['url']));
  $itemElem->appendChild($rssDoc->createElement('pubDate', $item['date']));
  $itemElem->appendChild($rssDoc->createElement('category', $item['category']));
  $channel->appendChild($itemElem);
}

// Set header and output XML
header("Content-Type: text/xml; charset=UTF-8");
echo $rssDoc->saveXML();
