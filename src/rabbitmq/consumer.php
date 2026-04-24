<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require __DIR__ . '/../../vendor/autoload.php';

use Slim\App;
use App\model\BookmarkModel;
use App\model\HighlightModel;
use App\model\BookModel;
use App\util\EncodingUtil;
use App\util\RequestUtil;
use App\util\TwitterUtil;
use App\rabbitmq\AmqpJobPublisher;
use App\enum\LogTypes;
use App\enum\JobTypes;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use ForceUTF8\Encoding;
use Goutte\Client;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$settings['settings'] = [
    'displayErrorDetails' => $_ENV['displayErrorDetails'],
    'debug' => $_ENV['debug']
];

$app = new App($settings);

$container = $app->getContainer();

$container['db'] = function ($container) {
    $dsn = "mysql:host=" . $_ENV['MYSQL_HOST'] . ";dbname=" . $_ENV['MYSQL_DATABASE'] . ";charset=utf8mb4";
    try {
        $db = new \PDO($dsn, $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASSWORD']);
    } catch (\Exception $e) {
        printLog('Database access problem: ' . $e->getMessage(), LogTypes::ERROR);
        die;
    }

    return $db;
};

$exchange = 'router';
$queue = 'msgs';
$consumerTag = 'consumer';

$connection = new AMQPStreamConnection($_ENV['RABBITMQ_HOST'], $_ENV['RABBITMQ_PORT'], $_ENV['RABBITMQ_USER'],
    $_ENV['RABBITMQ_PASSWORD'], $_ENV['RABBITMQ_VHOST']);
$channel = $connection->channel();

$channel->queue_declare($queue, false, true, false, false);
$channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
$channel->queue_bind($queue, $exchange);
$channel->basic_consume($queue, $consumerTag, false, false, false, false, 'process_message');
register_shutdown_function('shutdown', $channel, $connection);

while ($channel->is_consuming()) {
    $channel->wait();
}

/**
 * @param \PhpAmqpLib\Message\AMQPMessage $message
 */
function process_message($message)
{
    $messageBody = unserialize($message->body);
    $container = $GLOBALS['container'];
    $bookmarkModel = new BookmarkModel($container);
    $highlightModel = new HighlightModel($container);
    $bookModel = new BookModel($container);

    if ($messageBody['job_type'] === JobTypes::GET_PARENT_BOOKMARK_TITLE) {
        getParentBookmarkTitle($bookmarkModel, $messageBody);
    } elseif ($messageBody['job_type'] === JobTypes::GET_CHILD_BOOKMARK_TITLE) {
        getChildBookmarkTitle($bookmarkModel, $highlightModel, $messageBody);
    } elseif ($messageBody['job_type'] === JobTypes::SCRAPE_BOOK_ON_IDEFIX) {
        scrapeBookOnIdefix($bookModel, $messageBody);
    } elseif ($messageBody['job_type'] === JobTypes::GET_KEYWORD_ABOUT_BOOKMARK) {
        getKeywordAboutBookmark($bookmarkModel, $messageBody);
    } elseif ($messageBody['job_type'] === JobTypes::GET_BOOKMARK_DETAILS_USING_CLOUDFLARE_CRAWLER) {
        getBookmarkDetailsUsingCloudflareCrawler($bookmarkModel, $messageBody);
    }

    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

    if ($message->body === 'quit') {
        $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
    }
}

/**
 * @param \PhpAmqpLib\Channel\AMQPChannel $channel
 * @param \PhpAmqpLib\Connection\AbstractConnection $connection
 */
function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}

function getTextBySelector($crawler, $selector)
{
    try {
        return trim($crawler->filter($selector)->text());
    } catch (Exception $exception) {
        printLog("error occured while fetching '$selector', error: " . $exception->getMessage(), LogTypes::ERROR);
        return null;
    }
}

function getAttrBySelector($crawler, $selector, $attrName)
{
    try {
        return trim($crawler->filter($selector)->attr($attrName));
    } catch (Exception $exception) {
        printLog("error occured while fetching '$selector', error: " . $exception->getMessage(), LogTypes::ERROR);
        return null;
    }
}

function printLog($message, $type = LogTypes::INFO)
{
    $timestamp = '[' . date('Y-m-d H:i:s', time()) . '] ';
    $message = $timestamp . $message;

    switch ($type) {
        case LogTypes::ERROR:
            echo "\033[31m$message \033[0m\n";
            break;
        case LogTypes::SUCCESS:
            echo "\033[32m$message \033[0m\n";
            break;
        case LogTypes::WARNING:
            echo "\033[33m$message \033[0m\n";
            break;
        case LogTypes::INFO:
            echo "\033[36m$message \033[0m\n";
            break;
        default:
            echo $message . PHP_EOL;
            break;
    }

}

function getKeywordAboutBookmark(BookmarkModel $bookmarkModel, array $messageBody)
{
    $bookmarkId = $messageBody['id'];
    $requestGoesTo = 'https://api.openai.com/v1/completions';
    $bookmarkDetails = $bookmarkModel->getParentBookmarkById($bookmarkId);

    if (!$bookmarkDetails) {
        printLog("bookmark not found. given bookmark id: $bookmarkId", LogTypes::ERROR);
        return;
    }

    $bodyParams = json_encode([
        'model' => "text-davinci-003",
        "prompt" => "could you give me one keyword about {$bookmarkDetails['bookmark']} this website?",
        "temperature" => 0.5,
        "max_tokens" => 60,
        "top_p" => 1.0,
        "frequency_penalty" => 0.8,
        "presence_penalty" => 0.0
    ]);

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer {$_ENV['OPENAI_API_KEY']}"
    ];

    $response = RequestUtil::makeHttpRequest($requestGoesTo, RequestUtil::HTTP_POST, $bodyParams, $headers);
    $keyword = trim($response['choices'][0]['text']);

    if ($keyword) {
        printLog("found a keyword for $bookmarkId -> $keyword");
        $keyword = strtolower(preg_replace('/\P{L}+/u', '', $keyword));
        $bookmarkModel->updateKeyword($bookmarkId, $keyword);
    }
}

function scrapeBookOnIdefix(BookModel $bookModel, array $messageBody) {
    session_start();
    $_SESSION['userInfos']['user_id'] = $messageBody['user_id'];
    $isbn = $messageBody['isbn'];

    $exist = $bookModel->getBookByISBN($isbn);

    if (!$exist) {
        $elements = [
            'bookTitle' => ['fetch' => 'text', 'selector' => '.mt0'],
            'author' => [
                'fetch' => 'text',
                'selector' => '.product-info-list > ul:nth-child(1) > li:nth-child(2) > span:nth-child(2)'
            ],
            'description' => ['fetch' => 'text', 'selector' => '.product-description'],
            'thumbnail' => [
                'fetch' => 'attribute',
                'selector' => '#main-product-img',
                'attributeName' => 'data-src'
            ],
//            'pageCount' => [
//                'fetch' => 'text',
//                'selector' => '.product-info-list > ul:nth-child(1) > li:nth-child(6) > a:nth-child(2)'
//            ],
            'publisher' => [
                'fetch' => 'text',
                'selector' => 'div.hidden-xs:nth-child(2) > div:nth-child(2) > a:nth-child(2)'
            ]
        ];


        $url = "https://www.idefix.com/search?q=$isbn&redirect=search";

        try {
            $client = new Client();
            $crawler = $client->request('GET', $url);

            $result = $crawler->filter(".box-title")->text();
            $link = $crawler->selectLink($result)->link();
            $crawler = $client->click($link);

            $bookData['info_link'] = $link->getUri();
        } catch (Exception $e) {
            printLog('error occured while scraping book on Idefix: ' . $e->getMessage(), LogTypes::ERROR);
        }

        $bookData['isbn'] = $isbn;
        $bookData['pdf'] = 0;
        $bookData['epub'] = 0;
        $bookData['pageCount'] = 0;

        foreach ($elements as $key => $element) {
            if ($element['fetch'] === 'text') {
                $bookData[$key] = getTextBySelector($crawler, $element['selector']);
            } elseif ($element['fetch'] === 'attribute') {
                $bookData[$key] = getAttrBySelector($crawler, $element['selector'], $element['attributeName']);
            }
        }

        printLog("author: {$bookData['author']}, title: {$bookData['bookTitle']}");

        $exist = $bookModel->getBookByGivenColumn('title', $bookData['bookTitle']);

        if (!$exist && $bookData['bookTitle'] && $bookData['author']) {

            if ($bookData['publisher']) {
                $publisherDetails = $bookModel->getPublisher($bookData['publisher']);
                $bookData['publisher'] = !$publisherDetails ? $bookModel->insertPublisher($bookData['publisher']) : $publisherDetails['id'];
            }

            $bookId = $bookModel->saveBook($bookData);

            if ($bookId) {
                $authors = $bookModel->createAuthorOperations($bookData['author']);

                foreach ($authors as $authorId) {
                    $bookModel->insertBookAuthor($bookId, $authorId);
                }
            }
        }
    }

    printLog('user id: ' . $_SESSION['userInfos']['user_id']);
    session_destroy();
}

function getChildBookmarkTitle(BookmarkModel $bookmarkModel, HighlightModel $highlightModel, array $messageBody) {
    $bookmarkDetails = $bookmarkModel->getChildBookmarkById($messageBody['id'], $messageBody['user_id']);

    if (!$bookmarkDetails) {
        printLog("bookmark not found. given bookmark id: {$messageBody['id']}", LogTypes::WARNING);
        return;
    }

    if (!$bookmarkDetails['is_title_edited']) {
        if (TwitterUtil::isTwitterUrl($bookmarkDetails['bookmark'])) {
            $username = TwitterUtil::getUsernameFromUrl($bookmarkDetails['bookmark']);
            $title = 'Twitter - ' . strip_tags(trim($username));
            $bookmarkModel->updateChildBookmarkTitleByID($bookmarkDetails['id'], $title, $messageBody['user_id']);

            printLog("completed 'get_child_bookmark_title' job for: {$bookmarkDetails['id']}, title: $title (twitter-title)");
        } else {
            $metadata = RequestUtil::getUrlMetadata($bookmarkDetails['bookmark']);

            if (isset($metadata['title']) && $metadata['title']) {

                $metadata = array_map('trim', $metadata);

                $newBookmarkDetails['description'] = EncodingUtil::fixEncoding($metadata['description']);
                $newBookmarkDetails['thumbnail'] = EncodingUtil::fixEncoding($metadata['image']);
                $newBookmarkDetails['title'] = EncodingUtil::fixEncoding($metadata['title']);
                $newBookmarkDetails['site_name'] = EncodingUtil::fixEncoding($metadata['site_name']);
                $newBookmarkDetails['site_type'] = EncodingUtil::fixEncoding($metadata['type']);
                $newBookmarkDetails['note'] = $bookmarkDetails['note'];
                $newBookmarkDetails['status'] = $bookmarkDetails['status'];

                try {
                    $bookmarkModel->updateChildBookmark($bookmarkDetails['id'], $newBookmarkDetails,
                        $messageBody['user_id']);
                    printLog("completed 'get_child_bookmark_title' job for: {$bookmarkDetails['id']}, title: {$newBookmarkDetails['title']}");
                } catch (Exception $exception) {

                    printLog('error occured: ' . $exception->getMessage(), LogTypes::ERROR);

                    try {
                        $web = new \spekulatius\phpscraper;
                        $web->go($bookmarkDetails['bookmark']);
                        $newBookmarkDetails['title'] = strip_tags(trim($web->title));
                        $newBookmarkDetails['description'] = strip_tags(trim($web->description));
                        $bookmarkModel->updateChildBookmark($bookmarkDetails['id'], $newBookmarkDetails,
                            $messageBody['user_id']);
                        printLog("completed 'get_child_bookmark_title' job for: {$bookmarkDetails['id']} with spekulatius\phpscraper, title: {$newBookmarkDetails['title']}");
                    } catch (Exception $exception) {
                        printLog("error occured 'get_child_bookmark_title' job for: {$bookmarkDetails['id']} with spekulatius\phpscraper, details: {$exception->getMessage()}");
                    }

                }

                if ($bookmarkDetails['title'] !== $newBookmarkDetails['title']) {
                    $highlightModel->updateHighlightAuthorByBookmarkId($bookmarkDetails['id'], $newBookmarkDetails['title'],
                        $messageBody['user_id']);
                }

            } else {
                if ($messageBody['retry_count'] < 5) {
                    printLog("Retry count: {$messageBody['retry_count']}", LogTypes::WARNING);
                    $messageBody['retry_count']++;
                    $amqpPublisher = new AmqpJobPublisher();

                    $amqpPublisher->publishJob(JobTypes::GET_CHILD_BOOKMARK_TITLE, [
                        'id' => $bookmarkDetails['id'],
                        'retry_count' => $messageBody['retry_count'],
                        'user_id' => $messageBody['user_id']
                    ]);

                    printLog("trigged again 'get_child_bookmark_title' job for: {$bookmarkDetails['id']}, retry_count: {$messageBody['retry_count']}");
                }
            }
        }
    }
}

function getParentBookmarkTitle(BookmarkModel $bookmarkModel, array $messageBody) {
    $bookmarkDetails = $bookmarkModel->getParentBookmarkById($messageBody['id']);

    if (!$bookmarkDetails) {
        printLog("bookmark not found. given bookmark id: {$messageBody['id']}", LogTypes::WARNING);
        return;
    }

    if (TwitterUtil::isTwitterUrl($bookmarkDetails['bookmark'])) {
        $username = TwitterUtil::getUsernameFromUrl($bookmarkDetails['bookmark']);
        $title = 'Twitter - ' . strip_tags(trim($username));
        $bookmarkModel->updateParentBookmarkTitleByID($bookmarkDetails['id'], $title);

        printLog("completed 'get_parent_bookmark_title' job for: {$bookmarkDetails['id']}, title: $title (twitter-title)");
    } else {
        $metadata = RequestUtil::getUrlMetadata($bookmarkDetails['bookmark']);

        if (isset($metadata['title']) && $metadata['title']) {

            $metadata = array_map('trim', $metadata);

            $newBookmarkDetails['description'] = EncodingUtil::fixEncoding($metadata['description']);
            $newBookmarkDetails['thumbnail'] = EncodingUtil::fixEncoding($metadata['image']);
            $newBookmarkDetails['title'] = EncodingUtil::fixEncoding($metadata['title']);
            $newBookmarkDetails['site_name'] = EncodingUtil::fixEncoding($metadata['site_name']);
            $newBookmarkDetails['site_type'] = EncodingUtil::fixEncoding($metadata['type']);

            try {
                $bookmarkModel->updateParentBookmark($bookmarkDetails['id'], $newBookmarkDetails);
                printLog("completed 'get_parent_bookmark_title' job for: {$bookmarkDetails['id']}, title: {$newBookmarkDetails['title']}");
            } catch (Exception $exception) {
                printLog('error occured: ' . $exception->getMessage(), LogTypes::ERROR);

                try {
                    $web = new \spekulatius\phpscraper;
                    $web->go($bookmarkDetails['bookmark']);
                    $newBookmarkDetails['title'] = strip_tags(trim($web->title));
                    $newBookmarkDetails['description'] = strip_tags(trim($web->description));
                    $bookmarkModel->updateParentBookmark($bookmarkDetails['id'], $newBookmarkDetails);
                    printLog("completed 'get_parent_bookmark_title' job for: {$bookmarkDetails['id']} with spekulatius\phpscraper, title: {$newBookmarkDetails['title']}");
                } catch (Exception $exception) {
                    printLog("error occured 'get_parent_bookmark_title' job for: {$bookmarkDetails['id']} with spekulatius\phpscraper, details: {$exception->getMessage()}");
                }
            }

        } else {
            if ($messageBody['retry_count'] < 5) {
                printLog("Retry count: {$messageBody['retry_count']}", LogTypes::WARNING);
                $messageBody['retry_count']++;
                $amqpPublisher = new AmqpJobPublisher();

                $amqpPublisher->publishJob(JobTypes::GET_PARENT_BOOKMARK_TITLE, [
                    'id' => $bookmarkDetails['id'],
                    'retry_count' => $messageBody['retry_count']
                ]);
                printLog("trigged again 'get_parent_bookmark_title' job for: {$bookmarkDetails['id']}, retry_count: {$messageBody['retry_count']}");
            }
        }
    }
}

function crawlWithCloudflare(string $url): array
{
    // calculate function execution time and log it
    $startTime = microtime(true);
    $endpoint = 'https://api.cloudflare.com/client/v4/accounts/' . $_ENV['CLOUDFLARE_ACCOUNT_ID'] . '/browser-rendering/content';

    $payload = json_encode([
        'url' => $url,
        'gotoOptions' => [
            'waitUntil' => 'networkidle0',
            'timeout'   => 10000, // 10 second
        ],
        'rejectResourceTypes' => ['image', 'font', 'media', 'stylesheet'],
    ]);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $_ENV['CLOUDFLARE_API_TOKEN'],
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $endpoint,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20, // second
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    printLog("Cloudflare crawl execution time for $url: {$executionTime}s", LogTypes::WARNING);

    if ($curlError) {
        return ['success' => false, 'http_code' => 0, 'error' => "cURL error: $curlError"];
    }

    $response = json_decode($response, true);
    if ($httpCode !== 200) {
        $data = $response;
        $msg  = $data['errors'][0]['message'] ?? $response;
        return ['success' => false, 'http_code' => $httpCode, 'error' => "HTTP $httpCode — $msg"];
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $response['result']);
    libxml_clear_errors();

    // Extract title
    $titleNode = $doc->getElementsByTagName('title')->item(0);
    $title = $titleNode ? trim($titleNode->textContent) : '';

    $mainNode = extractMainNode($response['result']);

    return [
        'success'   => true,
        'http_code' => $httpCode,
        'url'       => $url,
        'title'     => $title,
        'content'   => extractHtml($mainNode),
    ];
}

/**
 * Parse HTML and return the best main content DOMNode
 */
function extractMainNode(string $html): ?DOMNode
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    // Remove noise
    foreach ([
                 '//nav', '//header', '//footer', '//aside',
                 '//script', '//style', '//noscript',
                 '//*[contains(@class,"menu")]',
                 '//*[contains(@class,"sidebar")]',
                 '//*[contains(@class,"advertisement")]',
                 '//*[contains(@class,"cookie")]',
                 '//*[contains(@id,"nav")]',
                 '//*[contains(@id,"footer")]',
                 '//*[contains(@id,"header")]',
             ] as $selector) {
        foreach ($xpath->query($selector) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    // Find best content container
    foreach ([
                 '//main', '//article',
                 '//*[@id="content"]', '//*[@id="main-content"]', '//*[@id="main"]',
                 '//*[contains(@class,"content")]',
                 '//*[contains(@class,"article")]',
                 '//*[contains(@class,"post")]',
                 '//body',
             ] as $selector) {
        $nodes = $xpath->query($selector);
        if ($nodes && $nodes->length > 0) {
            $node = $nodes->item(0);
            if (strlen(trim($node->textContent)) > 200) {
                return $node;
            }
        }
    }

    return null;
}

/**
 * Return cleaned inner HTML of the main content node
 */
/**
 * Return cleaned inner HTML of the main content node
 */
function extractHtml(?DOMNode $node): string
{
    if (!$node) return '';

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);

    foreach ($node->childNodes as $child) {
        $doc->appendChild($doc->importNode($child, true));
    }

    $xpath = new DOMXPath($doc);

    // Remove noise tags entirely
    $noiseTags = [
        '//script', '//style', '//noscript', '//iframe',
        '//nav', '//header', '//footer', '//aside', '//form',
        '//button', '//input', '//select', '//textarea',
        '//svg', '//canvas', '//video', '//audio', '//map',
        '//figure//figcaption',  // optional: remove captions
    ];

    foreach ($noiseTags as $tag) {
        foreach ($xpath->query($tag) as $noiseNode) {
            $noiseNode->parentNode?->removeChild($noiseNode);
        }
    }

    // Remove noise by class/id patterns
    $noisePatterns = [
        'ad', 'ads', 'advert', 'advertisement',
        'banner', 'popup', 'modal', 'overlay',
        'cookie', 'gdpr', 'consent',
        'sidebar', 'side-bar',
        'menu', 'nav', 'navigation',
        'header', 'footer',
        'share', 'sharing', 'social',
        'comment', 'comments', 'disqus',
        'related', 'recommended', 'suggestion',
        'newsletter', 'subscribe', 'subscription',
        'breadcrumb', 'pagination',
        'tag', 'tags', 'label', 'labels',
        'toolbar', 'widget', 'promo',
    ];

    foreach ($noisePatterns as $pattern) {
        $query = "//*[
            contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '$pattern') or
            contains(translate(@id,    'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '$pattern')
        ]";
        foreach ($xpath->query($query) as $noiseNode) {
            $noiseNode->parentNode?->removeChild($noiseNode);
        }
    }

    // Remove empty tags (no text content and no meaningful children)
    $emptyTagsToRemove = ['p', 'div', 'span', 'section', 'li', 'ul', 'ol'];
    // Run twice to catch nested empty elements
    for ($pass = 0; $pass < 2; $pass++) {
        foreach ($emptyTagsToRemove as $tag) {
            foreach ($xpath->query("//$tag") as $el) {
                if (trim($el->textContent) === '') {
                    $el->parentNode?->removeChild($el);
                }
            }
        }
    }

    // Strip noisy attributes, keep only semantic ones
    $allowedAttributes = ['href', 'src', 'alt', 'title', 'colspan', 'rowspan', 'type'];
    foreach ($xpath->query('//*[@*]') as $el) {
        $attrsToRemove = [];
        foreach ($el->attributes as $attr) {
            if (!in_array($attr->name, $allowedAttributes)) {
                $attrsToRemove[] = $attr->name;
            }
        }
        foreach ($attrsToRemove as $attr) {
            $el->removeAttribute($attr);
        }
    }

    // Keep only semantic/content tags, replace divs/spans with their inner content
    $html = $doc->saveHTML();

    // Unwrap purely structural tags that add no semantic value
    $html = preg_replace('/<(div|span|section)[^>]*>(.*?)<\/\1>/is', '$2', $html);

    // Clean up excessive whitespace and blank lines
    $html = preg_replace('/(\n\s*){3,}/', "\n\n", $html);
    $html = preg_replace('/[ \t]+/', ' ', $html);
    $html = preg_replace('/ >/', '>', $html);

    return trim($html);
}

function getBookmarkDetailsUsingCloudflareCrawler(BookmarkModel $bookmarkModel, $messageBody){

    $bookmarkDetails = $bookmarkModel->getParentBookmarkById($messageBody['id']);

    if (!$bookmarkDetails) {
        printLog("bookmark not found. given bookmark id: {$messageBody['id']}", LogTypes::WARNING);
        return;
    }

    printLog("crawling bookmark with cloudflare crawler, id: {$bookmarkDetails['id']}, url: {$bookmarkDetails['bookmark']}");

    $result = crawlWithCloudflare($bookmarkDetails['bookmark']);
    // http_code: $result['http_code'] -- CF may return 200 even if the origin site returns 404/500, so check 'success' flag and 'error' message

    if (!$result['success']) {
        printLog('error occured while crawling with cloudflare crawler: ' . $result['error'], LogTypes::ERROR);
        return;
    }

    printLog("successfully crawled with cloudflare crawler, url: {$result['url']}");

    if (!$result['title']) {
        return;
    }

    printLog("extracted title: {$result['title']}");

    $newBookmarkDetails['title'] = $result['title'];
    $newBookmarkDetails['content'] = $result['content'] ?? '';

    $bookmarkModel->updateParentBookmark($bookmarkDetails['id'], $newBookmarkDetails);
}