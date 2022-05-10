<?php

include_once __DIR__ . '/init.php';

if(isset($argv[1])){
    $bookmarkDetails = getBookmarkByID($argv[1]);
    $title = getTitle($bookmarkDetails['bookmark']);
    $title =  strip_tags(trim($title));

    if($title){
        updateBookmarkTitle($bookmarkDetails['id'], $title);
    }

} 

die;

function getBookmarkByID($bookmarkID)
{
    $dbConnection = $GLOBALS['dbConnection'];

    $sql = 'SELECT * FROM bookmarks WHERE id = :id';

    $stm = $dbConnection->prepare($sql);
    $stm->bindParam(':id', $bookmarkID, \PDO::PARAM_INT);

    if (!$stm->execute()) {
        return false;
    }

    $bookmark = [];

    while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
        $bookmark = $row;
    }

    return $bookmark;
}

function getTitle($url)
{
    try {
        $data = @file_get_contents($url);
        $code = getHttpCode($http_response_header);

        if ($code === 404) {
            return '404 Not Found';
        }
    } catch (\Exception $exception) {
        return null;
    }

    if (preg_match('/<title[^>]*>(.*?)<\/title>/ims', $data, $matches)) {
        return mb_check_encoding($matches[1], 'UTF-8') ? $matches[1] : utf8_encode($matches[1]);
    }

    return null;
}

function getHttpCode($http_response_header)
{
    if (is_array($http_response_header)) {
        $parts = explode(' ', $http_response_header[0]);
        if (count($parts) > 1) //HTTP/1.0 <code> <text>
            return intval($parts[1]); //Get code
    }
    return 0;
}

function updateBookmarkTitle($bookmarkID, $title)
{
    $dbConnection = $GLOBALS['dbConnection'];

    $sql = 'UPDATE bookmarks SET title = :title WHERE id = :id';

    $stm = $dbConnection->prepare($sql);
    $stm->bindParam(':id', $bookmarkID, \PDO::PARAM_INT);
    $stm->bindParam(':title', $title, \PDO::PARAM_STR);

    if (!$stm->execute()) {
        echo "fail\n";
        return false;
    }

    echo "success\n";
    return true;
}