<?php

namespace App\controller;

use App\enum\Sources;
use App\model\BookModel;
use App\model\TagModel;
use App\util\ArrayUtil;
use App\util\lang;
use App\util\Typesense;
use App\util\ValidatorUtil;
use Jfcherng\Diff\DiffHelper;
use Slim\Http\StatusCode;
use App\util\EncryptionUtil;
use App\util\VersionDiffUtil;
use App\model\HighlightModel;
use App\exception\CustomException;
use Psr\Container\ContainerInterface;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\ServerRequestInterface;

class HighlightController extends Controller
{
    private $highlightModel;
    private $tagModel;
    private $bookModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->highlightModel = new HighlightModel($container);
        $this->tagModel = new TagModel($container);
        $this->bookModel = new BookModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $queryString = $request->getQueryParams();

        $data = [
            'pageTitle' => 'Highlights | trackr',
            'activeHighlights' => 'active',
            'showHighlightsDropdownList' => 'show',
        ];

        if (isset($queryString['tag'])) {
            $tagQueryString = htmlspecialchars($queryString['tag'], ENT_QUOTES | ENT_HTML401, "UTF-8");
            $highlights = $this->highlightModel->getHighlightsByTag($queryString['tag'], $_ENV['HIGHLIGHT_LIMIT']);
            $typesenseResult = $this->highlightModel->searchHighlightTypesense('#' . $queryString['tag']);
            $highlights = array_merge($highlights, $typesenseResult);
            $data['pageTitle'] = "Highlights #$tagQueryString | trackr";
        } elseif (isset($queryString['author'])) {
            $highlights = $this->highlightModel->getHighlightsByGivenField('author', $queryString['author'],
                $_ENV['HIGHLIGHT_LIMIT']);
            $data['pageTitle'] = "{$queryString['author']}'s Highlights | trackr";
        } elseif (isset($queryString['source'])) {
            $highlights = $this->highlightModel->getHighlightsByGivenField('source', $queryString['source'],
                $_ENV['HIGHLIGHT_LIMIT']);
            $data['pageTitle'] = "{$queryString['source']}'s Highlights | trackr";
        } elseif (isset($queryString['id'])) {

            if (ValidatorUtil::isInteger($queryString['id'])) {
                $data['pageTitle'] = "Highlights #{$queryString['id']} | trackr";
            }

            $highlights = $this->highlightModel->getHighlightsByGivenField('id', $queryString['id'], $_ENV['HIGHLIGHT_LIMIT']);
            $highlights[0]['expandable'] = false;
            $highlights[0]['expandableClass'] = '';
        } elseif (isset($queryString['bookUID'])) {
            $book = $this->bookModel->getBookByGivenColumn('uid', $queryString['bookUID']);
            $bookId = $book['id'];
            $bookName = $book['author'] . ' - ' . $book['title'];
            $highlights = $this->highlightModel->getHighlightsByGivenField('book_id', $bookId);
            $data['pageTitle'] = "$bookName's Highlights | trackr";
        } elseif (isset($queryString['type']) && !isset($queryString['search'])) {
            $type = $queryString['type'];
            if ($type === 'public') {
                $highlights = $this->highlightModel->getHighlightsByGivenField('is_secret', 0);
            } elseif ($type === 'private') {
                $highlights = $this->highlightModel->getHighlightsByGivenField('is_secret', 1);
            } elseif ($type === 'book') {
                $highlights = $this->highlightModel->getHighlightsByGivenField('type', 1);
            } elseif ($type === 'favorites') {
                $highlights = $this->highlightModel->getFavorites();
            }

        } elseif (isset($queryString['search']) && isset($queryString['type'])) {
            $searchParam = trim($queryString['search']);

            if (isset($queryString['type']) && ValidatorUtil::validateIntegerByConstraints($queryString['type'], 0, 0)) {
                $highlights = $this->highlightModel->searchHighlightTypesense($searchParam);
            }

            if (isset($queryString['type']) && ValidatorUtil::validateIntegerByConstraints($queryString['type'], 1, 1)) {
                $highlights = $this->highlightModel->searchHighlightMySQL($searchParam);
            }

            $data['searchParam'] = htmlspecialchars($searchParam, ENT_QUOTES | ENT_HTML401, "UTF-8");
        } else {
            $highlights = $this->highlightModel->getHighlights($_ENV['HIGHLIGHT_LIMIT']);
        }

        $books = $_SESSION['books']['list'] ?? $this->bookModel->getAuthorBookList();

        $data['filterTags'] = $this->tagModel->getSourceTagsByType(Sources::HIGHLIGHT->value, $queryString['tag']);
        $data['globalTags'] = $this->tagModel->getGlobalTagsWithSelection([$queryString['tag']]);
        $data['highlights'] = $highlights;
        $data['books'] = $books;
        $data['authors'] = $this->highlightModel->getHighlightAuthors();
        $data['sources'] = $this->highlightModel->getHighlightSources();

        return $this->view->render($response, 'highlights/index.mustache', $data);
    }

    public function details(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];

        $detail = $this->highlightModel->getHighlightByID($highlightID);
        $subHighlights = $this->highlightModel->getSubHighlightsByHighlightID($highlightID);
        $nextID = $this->highlightModel->getNextHighlight($highlightID);
        $previousID = $this->highlightModel->getPreviousHighlight($highlightID);
        //$this->highlightModel->updateUpdatedFieldByHighlightId($highlightID);
        $books = $this->bookModel->getAuthorBookList();
        $detail['authors'] = $this->highlightModel->getHighlightAuthors($detail['author']);
        $detail['sources'] = $this->highlightModel->getHighlightSources($detail['source']);

        $detail['globalTagsWithSelection'] = $this->tagModel->getGlobalTagsWithSelection($detail['tags']['raw_tags']);

        foreach ($books as $key => $book) {
            if ($book['id'] === $detail['book_id']) {
                $books[$key]['selected'] = 'selected';
                break;
            }
        }

        $data = [
            'pageTitle' => 'Highlight Details | trackr',
            'detail' => $detail,
            'subHighlights' => $subHighlights,
            'activeHighlights' => 'active',
            'nextID' => $nextID,
            'previousID' => $previousID,
            'books' => $books,
            'showHighlightsDropdownList' => 'show',
        ];

        if (ValidatorUtil::isInteger($highlightID)) {
            $data['pageTitle'] = "Highlights #$highlightID Details | trackr";
        }

        return $this->view->render($response, 'highlights/details.mustache', $data);
    }

    public function versions(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];
        $versionDiffs = [];

        $versions = $this->highlightModel->getVersionsById($highlightID);
        $versionCount = count($versions) + 1;
        $currentHighlight = $this->highlightModel->getHighlightByID($highlightID);

        $newString = $currentHighlight['highlight'];

        $latestDiff = DiffHelper::calculate(
            $newString,
            $newString,
            'Inline',
            VersionDiffUtil::highlightsDiffOptions(),
            VersionDiffUtil::highlightsRendererOptions(),
        );

        $versionDiffs[] = ['diff' => $latestDiff, 'created_at' => "#$versionCount Latest"];

        foreach ($versions as $version) {
            $versionCount--;
            $new = $newString;
            $old = $version['old_highlight'];
            $sideBySideResult = DiffHelper::calculate(
                $old,
                $new,
                'Inline',
                VersionDiffUtil::highlightsDiffOptions(),
                VersionDiffUtil::highlightsRendererOptions(),
            );

            $versionDiffs[] = ['diff' => $sideBySideResult, 'created_at' => "#$versionCount {$version['created_at']}"];
            $newString = $version['old_highlight'];
        }

        $resource['data']['versionDiffs'] = $versionDiffs;
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

    public function all(ServerRequestInterface $request, ResponseInterface $response)
    {
        $highlights = $this->highlightModel->getHighlights(100);

        $data = [
            'highlights' => $highlights
        ];

        return $this->view->render($response, 'highlights/all.mustache', $data);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];
        $params = (array)$request->getParsedBody();

        $this->highlightModel->updateOperations($highlightID, $params);

        $resource = [
            "message" => lang\En::HIGHLIGHT_SUCCESSFULLY_UPDATED
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = (array)$request->getParsedBody();

        $this->highlightModel->createOperations($params);

        $resource = [
            "message" => lang\En::SUCCESS
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function createSub(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $resource = [];
        $params = ArrayUtil::trimArrayElements((array)$request->getParsedBody());
        $highlightID = $args['id'];
        $doIndex = false;
        $now = time();

        if (!isset($params['highlight']) || !$params['highlight']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::HIGHLIGHT_CANNOT_BE_NULL);
        }

        if (str_word_count($params['highlight']) < 2) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::HIGHLIGHT_MUST_BE_LONGER);
        }

        $parentHighlightDetails = $this->highlightModel->getHighlightByID($highlightID);

        if (!$parentHighlightDetails) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::HIGHLIGHT_PARENT_NOT_FOUND);
        }

        if (isset($params['is_encrypted']) && $params['is_encrypted'] === 'Yes') {
            $params['is_encrypted'] = 1;
            $params['highlight'] = EncryptionUtil::encrypt($params['highlight']);
        } else {
            $doIndex = true;
            $params['is_encrypted'] = 0;
        }

        $params['updated'] = $now;
        $params['created'] = $now;
        $params['blog_path'] = $parentHighlightDetails['blog_path'];
        $params['is_secret'] = 1;

        $subHighlightID = $this->highlightModel->create($params);

        if ($params['tags']) {
            $this->tagModel->updateSourceTags($params['tags'], $subHighlightID, Sources::HIGHLIGHT->value);
        }

        $this->highlightModel->createSubHighlight($highlightID, $subHighlightID);
        $_SESSION['badgeCounts']['highlightsCount'] += 1;

        if ($doIndex) {
            $typesenseClient = new Typesense('highlights');
            $document = [
                'id' => (string)$subHighlightID,
                'highlight' => $params['highlight'],
                'is_deleted' => 0,
                'author' => $params['author'] ?: $_SESSION['userInfos']['username'],
                'source' => $params['source'] ?: '',
                'created' => (int)$now,
                'updated' => (int)$now,
                'is_encrypted' => 0,
                'is_secret' => (int)$params['is_secret'],
                'blog_path' => $params['blogPath'] ?? '',
                'user_id' => (int)$_SESSION['userInfos']['user_id'],
            ];
            $typesenseClient->indexDocument($document);
        }

        $resource['message'] = lang\En::HIGHLIGHT_SUB_SUCCESSFULLY_ADDED;

        unset($_SESSION['highlights']['minMaxID']);
        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];

        $this->highlightModel->deleteHighlight($highlightID);
        $this->tagModel->updateIsDeletedStatusBySourceId(Sources::HIGHLIGHT->value, $highlightID,
            HighlightModel::NOT_DELETED);

        $typesenseClient = new Typesense('highlights');

        $searchParameters = [
            'q' => '*',
            'filter_by' => "id:=$highlightID && user_id:={$_SESSION['userInfos']['user_id']}",
            'fields' => 'id,user_id'
        ];
        $typesenseSearchResult = $typesenseClient->searchDocuments($searchParameters);

        if ($typesenseSearchResult['found']) {
            $document = [
                'is_deleted' => 1,
            ];
            $typesenseClient->updateDocument((string)$highlightID, $document);
        }

        $_SESSION['badgeCounts']['highlightsCount'] -= 1;

        $resource = [
            "message" => lang\En::HIGHLIGHT_DELETED_SUCCESSFULLY,
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function search(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $highlights = [];

        // OLD
        // $results = $this->highlightModel->searchHighlightMySQL($params['searchParam']);

        $searchParam = trim($params['searchParam']);

        if ($searchParam) {
            $highlights = $this->highlightModel->searchHighlightTypesense($searchParam);
        }

        $resource = [
            "highlights" => $highlights
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function get(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];
        $resource = $this->highlightModel->getHighlightByID($highlightID);

        if (!$resource) {
            $resource['highlight'] = lang\En::HIGHLIGHT_NOT_FOUND;
        }

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

}
