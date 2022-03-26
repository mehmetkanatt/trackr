<?php

namespace App\controller;

use App\model\TagModel;
use Slim\Http\StatusCode;
use App\model\BookmarkModel;
use App\model\HighlightModel;
use App\exception\CustomException;
use Psr\Container\ContainerInterface;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\ServerRequestInterface;

class HighlightController extends Controller
{
    private $highlightModel;
    private $bookmarkModel;
    private $tagModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->highlightModel = new HighlightModel($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->tagModel = new TagModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $queryString = $request->getQueryParams();

        if (isset($queryString['tag']) && $queryString['tag']) {
            $highlights = $this->highlightModel->getHighlightsByTag($queryString['tag']);
        } else {
            $highlights = $this->highlightModel->getHighlights(300);
        }

        $tags = $this->tagModel->getHighlightTagsAsHTML($queryString['tag']);

        $data = [
            'title' => 'Highlights | trackr',
            'tag' => htmlentities($queryString['tag']),
            'headerTags' => $tags,
            'highlights' => $highlights,
            'activeHighlights' => 'active'
        ];

        return $this->view->render($response, 'highlights.mustache', $data);
    }

    public function details(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];
        
        $detail = $this->highlightModel->getHighlightByID($highlightID);
        $subHighlights = $this->highlightModel->getSubHighlightsByHighlightID($highlightID);
        $nextID = $this->highlightModel->getNextHighlight($highlightID);
        $previousID = $this->highlightModel->getPreviousHighlight($highlightID);

        $data = [
            'title' => 'Highlight Details | trackr',
            'detail' => $detail,
            'subHighlights' => $subHighlights,
            'activeHighlights' => 'active',
            'nextID' => $nextID,
            'previousID' => $previousID,
        ];

        return $this->view->render($response, 'highlight-details.mustache', $data);
    }

    public function all(ServerRequestInterface $request, ResponseInterface $response)
    {
        $highlights = $this->highlightModel->getHighlights(100);

        $data = [
            'highlights' => $highlights
        ];

        return $this->view->render($response, 'highlights-all.mustache', $data);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];
        $params = $request->getParsedBody();

        if ($params['link']) {
            if ($params['link'] !== $_SESSION['update']['highlight']['link']) {
                $bookmarkExist = $this->bookmarkModel->getBookmarkByBookmark($params['link']);
                if ($bookmarkExist) {
                    $params['link'] = $bookmarkExist['id'];
                } else {
                    $bookmarkId = $this->bookmarkModel->createOperations($params['link'], null, 6665);
                    $params['link'] = $bookmarkId;
                }
            } else {
                $params['link'] = $_SESSION['update']['highlight']['linkID'];
            }
        } else {
            $params['link'] = null;
        }

        $this->tagModel->deleteTagsByHighlightID($highlightID);
        $this->tagModel->updateHighlightTags($params['tags'], $highlightID);
        $this->highlightModel->update($highlightID, $params);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        if(!$params['highlight']){
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Highlight cannot be null!");
        }

        $highlightExist = $this->highlightModel->searchHighlight(trim($params['highlight']));

        if($highlightExist){
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Highlight added before.!");
        }

        if ($params['link']) {
            $bookmarkExist = $this->bookmarkModel->getBookmarkByBookmark($params['link']);
            if ($bookmarkExist) {
                $params['link'] = $bookmarkExist['id'];
            } else {
                $bookmarkId = $this->bookmarkModel->createOperations($params['link'], null, 6665);
                $params['link'] = $bookmarkId;
            }
        } else {
            $params['link'] = null;
        }

        $highlightId = $this->highlightModel->create($params);

        if (strpos($params['tags'], ',') !== false) {
            $tags = explode(',', $params['tags']);

            foreach ($tags as $tag) {
                $this->tagModel->insertTagByChecking($highlightId, trim($tag));
            }

        } else {
            $this->tagModel->insertTagByChecking($highlightId, trim($params['tags']));
        }

        $_SESSION['badgeCounts']['highlightsCount'] += 1;

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function createSub(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $highlightID = $args['id'];

        if ($params['link']) {
            $bookmarkExist = $this->bookmarkModel->getBookmarkByBookmark($params['link']);
            if ($bookmarkExist) {
                $params['link'] = $bookmarkExist['id'];
            } else {
                $bookmarkId = $this->bookmarkModel->createOperations($params['link'], null, 6665);
                $params['link'] = $bookmarkId;
            }
        }

        $subHighlightID = $this->highlightModel->create($params);

        if (strpos($params['tags'], ',') !== false) {
            $tags = explode(',', $params['tags']);

            foreach ($tags as $tag) {
                $this->tagModel->insertTagByChecking($subHighlightID, trim($tag));
            }

        } else {
            $this->tagModel->insertTagByChecking($subHighlightID, trim($params['tags']));
        }

        $this->highlightModel->createSubHighlight($highlightID, $subHighlightID);
        $_SESSION['badgeCounts']['highlightsCount'] += 1;

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $highlightID = $args['id'];

        $this->highlightModel->deleteHighlight($highlightID);
        $this->highlightModel->deleteHighlightTagsByHighlightID($highlightID);
        $this->highlightModel->deleteSubHighlightByHighlightID($highlightID);
        
        $_SESSION['badgeCounts']['highlightsCount'] -= 1;

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }
}