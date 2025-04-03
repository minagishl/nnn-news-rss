<?php
// Cache configuration
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_EXPIRATION', 1800); // 30 minutes in seconds
define('CACHE_MAX_AGE', 86400); // 24 hours in seconds
define('CACHE_FILE', CACHE_DIR . '/rss_cache.xml');

// Create cache directory if it doesn't exist
if (!file_exists(CACHE_DIR)) {
  mkdir(CACHE_DIR, 0755, true);
}

// Clean up old cache files
$now = time();
foreach (glob(CACHE_DIR . '/*') as $file) {
  if (is_file($file) && ($now - filemtime($file) > CACHE_MAX_AGE)) {
    unlink($file);
  }
}

// Check if cache exists and is still valid
$useCache = false;
if (file_exists(CACHE_FILE)) {
  $fileAge = $now - filemtime(CACHE_FILE);
  if ($fileAge < CACHE_EXPIRATION) {
    $useCache = true;
    header("Content-Type: text/xml; charset=UTF-8");
    header("X-Cache: HIT");
    readfile(CACHE_FILE);
    exit;
  }
}

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

// Get elements corresponding to the news article links using XPath
$anchors = $xpath->query("//ul[contains(@class, 'ArticleArea_newsList')]//li//article//a");

foreach ($anchors as $anchor) {
  // Title (text of h3 element in the second div)
  $h3Nodes = $xpath->query(".//div[2]//h3", $anchor);
  $title = ($h3Nodes->length > 0) ? trim($h3Nodes->item(0)->textContent) : "";

  // Date (text of first time element) → Convert to ISO format
  $timeNodes = $xpath->query(".//time[1]", $anchor);
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

  // Category (text of span with Label class in first div)
  $spanNodes = $xpath->query(".//div[1]//span[contains(@class, 'Label_label')]", $anchor);
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

// Set headers and save cache
header("Content-Type: text/xml; charset=UTF-8");
header("X-Cache: MISS");

// Save to cache file
$xml = $rssDoc->saveXML();
file_put_contents(CACHE_FILE, $xml);

// Output XML
echo $xml;
