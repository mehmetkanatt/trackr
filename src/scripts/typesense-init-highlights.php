<?php

// Static
require __DIR__ . '/app.php';

/** @var \Psr\Container\ContainerInterface $container */

// Dynamic
ini_set('memory_limit', -1);

use App\enum\LogTypes;
use App\model\HighlightModel;
use App\util\CLI;
use App\util\Typesense;

$collectionName = 'highlights';
$highlightModel = new HighlightModel($container);
$typesenseClient = new Typesense($collectionName);

$_SESSION['userInfos']['user_id'] = null; // Replace with the actual user ID

// Script Logic

$typesenseClient->deleteCollection($collectionName);

CLI::printLog('Deleted existing Typesense collection (if it existed): ' . $collectionName, LogTypes::SUCCESS);

$schema = [
    'name' => $collectionName,
    'fields' => [
        ['name' => 'highlight', 'type' => 'string'],
        ['name' => 'author', 'type' => 'string'],
        ['name' => 'source', 'type' => 'string'],
        ['name' => 'created', 'type' => 'int32'],
        ['name' => 'updated', 'type' => 'int32'],
        ['name' => 'is_encrypted', 'type' => 'int32'],
        ['name' => 'is_secret', 'type' => 'int32'],
        ['name' => 'blog_path', 'type' => 'string'],
        ['name' => 'is_deleted', 'type' => 'int32'],
        ['name' => 'user_id', 'type' => 'int32'],
    ]
];

$typesenseClient->createCollection($schema);

CLI::printLog('Created Typesense collection: ' . $collectionName, LogTypes::SUCCESS);

CLI::printLog('Fetching highlights to index');

$highlights = $highlightModel->getRawHighlights();

CLI::printLog('Starting... Total highlights to index: ' . count($highlights));

foreach ($highlights as $highlight) {

    $document = [
        'id' => (string)$highlight['id'],
        'highlight' => $highlight['highlight'],
        'author' => $highlight['author'] ?? '',
        'source' => $highlight['source'] ?? '',
        'created' => (int)$highlight['created'],
        'updated' => (int)$highlight['updated'],
        'user_id' => (int)$highlight['user_id'],
        'blog_path' => $highlight['blog_path'] ?? '',
        'is_encrypted' => (int)$highlight['is_encrypted'],
        'is_secret' => (int)$highlight['is_secret'],
        'is_deleted' => (int)$highlight['is_deleted'],
    ];

    $result = $typesenseClient->indexDocument($document);

    if (isset($result['error'])) {
        CLI::printLog('Error indexing highlight ID: ' . $highlight['id'] . ' - ' . $result['error'], LogTypes::ERROR);
    }
}

CLI::printLog('Finished indexing highlights', LogTypes::SUCCESS);