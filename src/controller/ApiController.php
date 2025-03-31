<?php

namespace App\controller;

use App\enum\BookmarkStatus;
use App\enum\JobTypes;
use App\enum\Sources;
use App\exception\CustomException;
use App\model\BookmarkModel;
use App\model\HighlightModel;
use App\model\TagModel;
use App\util\ArrayUtil;
use App\util\lang;
use App\rabbitmq\AmqpJobPublisher;
use App\util\Typesense;
use App\util\URL;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class ApiController extends Controller
{
    private $bookmarkModel;
    private $tagModel;
    private $highlightModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->tagModel = new TagModel($container);
        $this->highlightModel = new HighlightModel($container);
    }

    public function addBookmark(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $this->addBookmarkFlow($params['bookmark'], $params['title']);

        $resource = [
            "message" => "Successfully added bookmark",
        ];

        return $this->response(StatusCode::HTTP_CREATED, $resource);
    }

    private function addBookmarkFlow($bookmark, $title, $note = null)
    {
        $bookmarkCreatedBefore = true;

        if (!$bookmark) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Bookmark cannot be empty!');
        }

        $bookmark = URL::clearQueryParams($bookmark, ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'mc_cid', 'mc_eid', 'ref', 'ref_', 'refid', 'r', 'refsrc', 'ref_source', 'ref_source_', 'ref_sourceid', 'ref_sourceid_', 'refsrc', 'triedRedirect']);
        $bookmark = trim($bookmark);

        $bookmarkExist = $this->bookmarkModel->getParentBookmarkByBookmark($bookmark);

        if (!$bookmarkExist) {
            $bookmarkID = $this->bookmarkModel->create($bookmark);
            $bookmarkCreatedBefore = false;
        } else {
            $bookmarkID = $bookmarkExist['id'];
        }

        $bookmarkAddedToReadingList = $this->bookmarkModel->getChildBookmarkById($bookmarkID, $_SESSION['userInfos']['user_id']);

        if ($bookmarkCreatedBefore && $bookmarkAddedToReadingList) {
            $this->bookmarkModel->updateUpdatedAt($bookmarkID);
            $this->bookmarkModel->updateIsDeletedStatus($bookmarkID, BookmarkModel::NOT_DELETED);
            return $bookmarkID;
        }

        if ($title) {
            $this->bookmarkModel->updateParentBookmarkTitleByID($bookmarkID, $title);
        } else {
            $rabbitmq = new AmqpJobPublisher();
            $rabbitmq->publishJob(JobTypes::GET_PARENT_BOOKMARK_TITLE, [
                'id' => $bookmarkID,
                'retry_count' => 0,
                'user_id' => $_SESSION['userInfos']['user_id']
            ]);
        }

        $this->bookmarkModel->addOwnership($bookmarkID, $_SESSION['userInfos']['user_id'], $note);

        return $bookmarkID;
    }

    public function addHighlight(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = ArrayUtil::trimArrayElements((array)$request->getParsedBody());

        if (!isset($params['highlight']) || !$params['highlight']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::HIGHLIGHT_CANNOT_BE_NULL);
        }

        if (str_word_count($params['highlight']) < 2) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::HIGHLIGHT_MUST_BE_LONGER);
        }

        $now = time();
        $highlightDetails['highlight'] = $params['highlight'];
        $highlightDetails['blogPath'] = 'general/uncategorized';
        $highlightDetails['created'] = $now;
        $highlightDetails['updated'] = $now;
        $highlightDetails['is_secret'] = 1;
        $highlightDetails['is_encrypted'] = 0;
        $highlightDetails['bookmark_id'] = $this->addBookmarkFlow($params['bookmark'], $params['title']);
        $bookmarkDetail = $this->bookmarkModel->getChildBookmarkById($highlightDetails['bookmark_id'], $_SESSION['userInfos']['user_id']);
        $highlightDetails['author'] = $params['title'];
        $highlightDetails['source'] = 'Bookmark Highlight';

        $highlightExist = $this->highlightModel->searchHighlight($params['highlight']);

        if ($highlightExist) {
            foreach ($highlightExist as $highlight) {
                $this->highlightModel->updateUpdatedFieldByHighlightId($highlight['id']);
            }
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::HIGHLIGHT_ADDED_BEFORE);
        }

        $highlightId = $this->highlightModel->create($highlightDetails);

        $typesenseClient = new Typesense('highlights');
        $document = [
            'id' => (string)$highlightId,
            'highlight' => $params['highlight'],
            'is_deleted' => 0,
            'author' => $highlightDetails['author'],
            'source' => $highlightDetails['source'],
            'created' => (int)$now,
            'updated' => (int)$now,
            'is_encrypted' => 0,
            'is_secret' => 0,
            'blog_path' => '',
            'user_id' => (int)$_SESSION['userInfos']['user_id'],
        ];
        $typesenseClient->indexDocument($document);

        $this->tagModel->updateSourceTags($params['tags'], $highlightId, Sources::HIGHLIGHT->value);

        if ($bookmarkDetail['status'] != 2) {
            $this->bookmarkModel->updateStartedDate($bookmarkDetail['id'], time());
            $this->bookmarkModel->updateBookmarkStatus($bookmarkDetail['id'], BookmarkStatus::STARTED->value);
        }

        $resource = [
            "message" => lang\En::HIGHLIGHT_SUCCESSFULLY_ADDED
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

}