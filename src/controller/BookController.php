<?php

namespace App\controller;

use App\model\ActivityModel;
use App\util\lang;
use App\enum\BookStatus;
use App\enum\PathStatus;
use App\enum\Sources;
use App\exception\CustomException;
use App\model\BookModel;
use App\model\TagModel;
use App\rabbitmq\AmqpJobPublisher;
use App\util\RequestUtil;
use App\util\Typesense;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class BookController extends Controller
{
    private $bookModel;
    private $tagModel;
    private $activityModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookModel = new BookModel($container);
        $this->tagModel = new TagModel($container);
        $this->activityModel = new ActivityModel($container);
    }

    public function booksPathInside(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $queryParams = $request->getQueryParams();
        $active = false;

        if (isset($queryParams['status'])) {
            $active = $queryParams['status'] === 'active';
        }

        $path = $this->bookModel->getPathByUid($args['pathUID']);
        $pathId = $path['id'];
        $books = $this->bookModel->getBooksPathInside($pathId, $active);

        $data = [
            'pageTitle' => $path['name'] . "'s Books | trackr",
            'books' => $books,
            'activeBooks' => 'active',
            'showBooksDropdownList' => 'show',
        ];

        return $this->view->render($response, 'books/index.mustache', $data);
    }

    public function paths(ServerRequestInterface $request, ResponseInterface $response)
    {
        $paths = $this->bookModel->getBookPaths();

        $data = [
            'pageTitle' => 'Paths | trackr',
            'bookPaths' => $paths,
            'activeBooks' => 'active',
            'showBooksDropdownList' => 'show',
        ];

        return $this->view->render($response, 'books/paths.mustache', $data);
    }

    public function allBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $authors = $this->bookModel->getAuthors();
        $publishers = $this->bookModel->getPublishers();
        $books = $this->bookModel->getAllBooks();
        $paths = $this->bookModel->getPathsList(PathStatus::ACTIVE->value);
        $tags = $this->tagModel->getSourceTagsByType(Sources::BOOK->value);

        $data = [
            'pageTitle' => 'All Books | trackr',
            'authors' => $authors,
            'books' => $books,
            'publishers' => $publishers,
            'paths' => $paths,
            'tags' => $tags,
            'activeBooks' => 'active',
            'showBooksDropdownList' => 'show',
        ];

        return $this->view->render($response, 'books/all.mustache', $data);
    }

    public function myBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $books = $this->bookModel->getMyBooks();
        $paths = $this->bookModel->getPathsList(PathStatus::ACTIVE->value);

        $data = [
            'pageTitle' => 'My Books | trackr',
            'books' => $books,
            'paths' => $paths,
            'activeBooks' => 'active',
            'showBooksDropdownList' => 'show',
        ];

        return $this->view->render($response, 'books/my.mustache', $data);
    }

    public function finishedBooks(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $books = $this->bookModel->finishedBooks();

        $data = [
            'pageTitle' => 'Finished Books | trackr',
            'books' => $books,
            'activeBooks' => 'active',
            'finishedBooksCount' => count($books),
            'showBooksDropdownList' => 'show',
        ];

        return $this->view->render($response, 'books/finished.mustache', $data);
    }

    public function getHighlights(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookUid = $args['bookUID'];
        $book = $this->bookModel->getBookByGivenColumn('uid', $bookUid);
        $bookId = $book['id'];
        $bookName = $book['author'] . ' - ' . $book['title'];

        $highlights = $this->bookModel->getHighlights($bookId);

        $tags = $this->tagModel->getTagsBySourceId($bookId, Sources::BOOK->value);

        $_SESSION['books']['highlights']['bookID'] = $bookId;

        $data = [
            'pageTitle' => "$bookName's Highlights | trackr",
            'highlights' => $highlights,
            'activeBooks' => 'active',
            'bookUID' => $bookUid,
            'tags' => $tags['imploded_comma'],
            'showBooksDropdownList' => 'show',
        ];

        return $this->view->render($response, 'books/highlights.mustache', $data);
    }

    public function readingHistory(ServerRequestInterface $request, ResponseInterface $response)
    {
        $readingHistory = $this->bookModel->getReadingHistory();

        $data = [
            'pageTitle' => 'Reading History | trackr',
            'readingHistory' => $readingHistory,
            'activeBooks' => 'active',
            'showBooksDropdownList' => 'show',
        ];

        return $this->view->render($response, 'books/reading-history.mustache', $data);
    }

    public function addProgress(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        if (!isset($params['amount']) || !$params['amount']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::BOOK_AMOUNT_CANNOT_BE_NULL);
        }

        $pathDetail = $this->bookModel->getPathByUid($params['pathUID']);
        $pathId = $pathDetail['id'];

        $bookDetail = $this->bookModel->getBookByUid($args['bookUID']);
        $bookId = $bookDetail['id'];

        $pathBookDetail = $this->bookModel->getBookDetailByBookIdAndPathId($bookId, $pathId);
        $authorAndBook = $bookDetail['author'] . ' - ' . $bookDetail['title'];
        $oldStatus = (int)$pathBookDetail['status'];

        $pathBookDetail = $this->bookModel->getBookDetailByBookIdAndPathId($bookId, $pathDetail['id']);
        $readAmount = $this->bookModel->getReadAmount($bookId, $pathDetail['id']);

        if ($pathDetail['status']) {
            $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
            $resource['message'] = lang\En::BOOK_CANNOT_ADD_PROGRESS_TO_EXPIRED_PATH;
        } else {
            if ($oldStatus == BookStatus::DONE->value) {
                $resource['message'] = lang\En::BOOK_CANNOT_ADD_PROGRESS_TO_DONE_BOOK;
                $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
            } else {

                if (($pathBookDetail['page_count'] - $readAmount) - $params['amount'] < 0) {
                    $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
                    $resource['message'] = lang\En::BOOK_CANNOT_ADD_PROGRESS_MORE_THAN_REMAINING_AMOUNT;
                } else {
                    if ($params['amount'] > 0) {
                        $recordTime = $params['readYesterday'] ? strtotime("today 1 sec ago") : time();
                        $bookTrackingId = $this->bookModel->insertProgressRecord($bookId, $pathId, $params['amount'], $recordTime);

                        if ($oldStatus !== BookStatus::STARTED->value) {
                            $this->bookModel->changePathBookStatus($pathId, $bookId, BookStatus::STARTED->value);
                            $this->activityModel->logBookReadingStatus($pathDetail['name'], $authorAndBook, $oldStatus, BookStatus::STARTED->value, $pathBookDetail['id']);
                        }

                        $resource['responseCode'] = StatusCode::HTTP_OK;
                        $resource['message'] = "Success!";
                        $authorAndBook = $bookDetail['author'] . ' - ' . $bookDetail['title'];
                        $this->activityModel->logReadingProgress($pathDetail['name'], $authorAndBook, $params['amount'], $bookTrackingId);
                    } else {
                        $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
                        $resource['message'] = "Amount must be positive";
                    }
                }

            }
        }

        unset($_SESSION['books']['daily_reading_amount_inserted']);
        unset($_SESSION['books']['readingAverage']);
        return $this->response($resource['responseCode'], $resource);
    }

    public function createAuthor(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        // @deprecated
        $params = $request->getParsedBody();

        if (!isset($params['author']) || !$params['author']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::BOOK_AUTHOR_CANNOT_BE_NULL);
        }

        $this->bookModel->createAuthorOperations($params['author']);

        $resource['responseCode'] = StatusCode::HTTP_CREATED;
        $resource['message'] = lang\En::BOOK_AUTHOR_CREATED_SUCCESSFULLY;

        return $this->response($resource['responseCode'], $resource);
    }

    public function changeStatus(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        if (!isset($args['bookUID']) || !isset($params['pathUID']) || !isset($params['status'])) {
            $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
            $resource['message'] = lang\En::MISSING_REQUIRED_FIELDS;

            return $this->response($resource['responseCode'], $resource);
        }

        $pathDetail = $this->bookModel->getPathByUid($params['pathUID']);
        $pathId = $pathDetail['id'];
        $bookDetail = $this->bookModel->getBookByUid($args['bookUID']);
        $bookId = $bookDetail['id'];

        $pathBookDetail = $this->bookModel->getBookDetailByBookIdAndPathId($bookId, $pathId);
        $authorAndBook = $bookDetail['author'] . ' - ' . $bookDetail['title'];
        $oldStatus = $pathBookDetail['status'];
        $newStatus = $params['status'];

        if ($oldStatus === BookStatus::PRIORITIZED->value) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Already prioritized!");
        }

        if ((int)$newStatus === BookStatus::PRIORITIZED->value && $oldStatus !== BookStatus::NEW->value) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Book status is not 'New'!");
        }

        if (!in_array((int)$newStatus, BookStatus::toArray())) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Unknown book status");
        }

        $this->bookModel->changePathBookStatus($pathId, $bookId, $newStatus);
        $this->activityModel->logBookReadingStatus($pathDetail['name'], $authorAndBook, $oldStatus, $newStatus, $pathBookDetail['id']);

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Changed status successfully";

        return $this->response($resource['responseCode'], $resource);
    }

    public function addToLibrary(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $book = $this->bookModel->getBookByUid($args['bookUID']);
        $bookId = $book['id'];

        if (!$bookId) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::BOOK_NOT_FOUND);
        }

        $authorAndBook = $book['author'] . ' - ' . $book['title'];
        $ownershipId = $this->bookModel->addToLibrary($bookId);
        $_SESSION['badgeCounts']['myBookCount'] += 1;

        $this->activityModel->logAddBookToLibrary($authorAndBook, $ownershipId);

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Success";

        return $this->response($resource['responseCode'], $resource);
    }

    public function addBookToPath(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        $path = $this->bookModel->getPathByUid($params['pathUID']);
        $pathId = $path['id'];
        $book = $this->bookModel->getBookByUid($args['bookUID']);
        $bookId = $book['id'];
        $authorAndBook = $book['author'] . ' - ' . $book['title'];

        if ($path['status']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "You can't add book to expired paths!");
        }

        $pathBookId = $this->bookModel->addBookToPath($pathId, $bookId);
        $this->activityModel->logAddBookToPath($path['name'], $authorAndBook, $pathBookId);

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Success";

        unset($_SESSION['books']['daily_reading_amount_inserted']);

        return $this->response($resource['responseCode'], $resource);
    }

    public function extendPathFinish(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $pathId = $this->bookModel->getPathIdByUid($args['pathUID']);

        $pathDetail = $this->bookModel->getPathById($pathId);
        $extendedFinishDate = strtotime($pathDetail['finish']) + 864000; // 10 days
        $this->bookModel->extendFinishDate($pathId, $extendedFinishDate);

        $this->activityModel->logExtendPathFinishDate($pathDetail['name'], 10, $pathId);

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Success";

        return $this->response($resource['responseCode'], $resource);
    }

    public function saveBook(ServerRequestInterface $request, ResponseInterface $response)
    {
        //$rabbitmq = new AmqpJobPublisher();
        $params = $request->getParsedBody();

        $params['published_date'] = null;
        $params['description'] = null;
        $params['thumbnail'] = null;
        $params['thumbnail_small'] = null;
        $params['subtitle'] = null;

        if (isset($params['isbn']) && $params['isbn'] && isset($params['useAPI']) && $params['useAPI']) {

            $params['isbn'] = trim(str_replace("-", "", $params['isbn']));
            $params['is_complete_book'] = 1;
            $params['ebook_version'] = 0;
            $params['ebook_page_count'] = 0;

            $bookDetail = $this->bookModel->getBookByISBN($params['isbn']);

            if ($bookDetail) {
                throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST,
                    "Book already exist: " . htmlspecialchars($bookDetail['title']));
            }

            $url = 'https://www.googleapis.com/books/v1/volumes?q=isbn:' . $params['isbn'];
            $bookResponse = RequestUtil::makeHttpRequest($url, RequestUtil::HTTP_GET, [], []);

            if (!$bookResponse['totalItems']) {
//                $rabbitmq->publishJob(JobTypes::SCRAPE_BOOK_ON_IDEFIX, [
//                    'isbn' => $params['isbn'],
//                    'retry_count' => 0,
//                    'user_id' => $_SESSION['userInfos']['user_id']
//                ]);
                throw CustomException::clientError(StatusCode::HTTP_NOT_FOUND, "Book not found");
            }

            $params['bookTitle'] = $bookResponse['items'][0]['volumeInfo']['title'];
            $params['subtitle'] = $bookResponse['items'][0]['volumeInfo']['subtitle'] ?? null;

            $publisher = trim($bookResponse['items'][0]['volumeInfo']['publisher']);
            if ($publisher) {
                $publisherDetails = $this->bookModel->getPublisher($publisher);
                $params['publisher'] = !$publisherDetails ? $this->bookModel->insertPublisher($publisher) : $publisherDetails['id'];
            }

            $params['pdf'] = $bookResponse['items'][0]['accessInfo']['epub']['isAvailable'] ? 1 : 0;
            $params['epub'] = $bookResponse['items'][0]['accessInfo']['pdf']['isAvailable'] ? 1 : 0;
            $params['notes'] = null;
            $params['own'] = 0;
            $params['pageCount'] = $bookResponse['items'][0]['volumeInfo']['pageCount'];
            $params['published_date'] = $bookResponse['items'][0]['volumeInfo']['publishedDate'];
            $params['thumbnail'] = $bookResponse['items'][0]['volumeInfo']['imageLinks']['thumbnail'] ?: null;
            $params['thumbnail_small'] = $bookResponse['items'][0]['volumeInfo']['imageLinks']['smallThumbnail'] ?: null;
            $params['info_link'] = $bookResponse['items'][0]['volumeInfo']['infoLink'] ?: null;

            if ($bookResponse['items'][0]['volumeInfo']['description']) {
                $params['description'] = $bookResponse['items'][0]['volumeInfo']['description'];
            } elseif ($bookResponse['items'][0]['searchInfo']['textSnippet']) {
                $params['description'] = $bookResponse['items'][0]['searchInfo']['textSnippet'];
            } else {
                $params['description'] = null;
            }

            $params['tags'] = '';

            if (!$bookResponse['items'][0]['volumeInfo']['authors']) {
                $bookResponse['items'][0]['volumeInfo']['authors'] = ['###'];
                //throw CustomException::clientError(StatusCode::HTTP_INTERNAL_SERVER_ERROR, 'Author cannot be null!');
            }

            foreach ($bookResponse['items'][0]['volumeInfo']['authors'] as $author) {
                $params['authors'][] = $this->bookModel->insertAuthorByChecking($author);
            }

        }

        if (isset($params['publisher']) && !is_numeric($params['publisher'])) {
            $publisherDetails = $this->bookModel->getPublisher($params['publisher']);
            $params['publisher'] = !$publisherDetails ? $this->bookModel->insertPublisher($params['publisher']) : $publisherDetails['id'];
        }

        if (!$params['bookTitle']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Title cannot be null');
        }

        $bookId = $this->bookModel->saveBook($params);
        $authors = $params['authors'];

        if ($params['tags']) {
            $this->tagModel->updateSourceTags($params['tags'], $bookId, Sources::BOOK->value);
        }

        foreach ($authors as $author) {

            $authorId = $author;

            if (!is_numeric($author)) {
                $authorId = $this->bookModel->insertAuthorByChecking($author);
            }

            $this->bookModel->insertBookAuthor($bookId, $authorId);
        }

        $authorAndBook = implode(', ', $authors) . ' - ' . $params['bookTitle'];

        if ($params['own']) {
            $ownershipId = $this->bookModel->addToLibrary($bookId, $params['notes']);
            $_SESSION['badgeCounts']['myBookCount'] += 1;
            $this->activityModel->logAddBookToLibrary($authorAndBook, $ownershipId, $params['notes']);
        }

        $_SESSION['badgeCounts']['allBookCount'] += 1;
        unset($_SESSION['books']['list']);

        $this->activityModel->logCreateNewBook($authorAndBook, $bookId);

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Successfully created new book!";

        return $this->response($resource['responseCode'], $resource);
    }

    public function createPath(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        if (!isset($params['pathName']) || !$params['pathName'] || !isset($params['pathFinish'])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST,
                'Path Name or Path Finish Date cannot be null');
        }

        $pathID = $this->bookModel->createPath($params['pathName'], $params['pathFinish']);

        $this->activityModel->logCreateNewPath($params['pathName'], $pathID);

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Success";

        return $this->response($resource['responseCode'], $resource);
    }

    public function removeBookFromPath(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $path = $this->bookModel->getPathByUid($args['pathUID']);
        $pathId = $path['id'];
        $book = $this->bookModel->getBookByUid($params['bookUID']);
        $bookId = $book['id'];
        $authorAndBook = $book['author'] . ' - ' . $book['title'];

        $bookDetail = $this->bookModel->getBookDetailByBookIdAndPathId($bookId, $pathId);
        $currentStatus = $bookDetail['status'];

        if ($currentStatus === BookStatus::NEW->value) {
            $this->bookModel->deleteBookTrackingsByPath($bookId, $pathId);
            $this->bookModel->deleteBookFromPath($bookId, $pathId);

            $this->activityModel->logRemoveBookFromPath($path['name'], $authorAndBook, null);

            unset($_SESSION['books']['daily_reading_amount_inserted']);
            $resource['message'] = "Successfully removed.";
            $resource['responseCode'] = StatusCode::HTTP_OK;
        } else {
            $resource['message'] = "You can remove only 'Not Started' books from paths!";
            $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
        }

        return $this->response($resource['responseCode'], $resource);
    }

    public function rateBook(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $bookUid = $args['bookUID'];
        $book = $this->bookModel->getBookByUid($bookUid);
        $bookId = $book['id'];
        $authorAndBook = $book['author'] . ' - ' . $book['title'];
        $rate = (int)$params['rate'];

        if (!$bookId) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::BOOK_NOT_FOUND);
        }

        if (!isset($params['rate']) || $rate < 1 || $rate > 5) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Rate is invalid');
        }

        $booksFinishedDetails = $this->bookModel->finishedBookByID($bookId);
        $closureId = $booksFinishedDetails['id'];

        $this->bookModel->rateBook($bookId, $rate);
        $this->activityModel->logRateBook($booksFinishedDetails['pathName'], $authorAndBook, $rate, $closureId);

        $resource['message'] = "Successfully rated!";
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

    public function getReadingHistory(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookUID = $args['bookUID'];
        $bookId = $this->bookModel->getBookIdByUid($bookUID);

        $resource['data'] = $this->bookModel->getReadingHistory($bookId);
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

    public function addHighlight(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $bookUid = $args['bookUID'];
        $bookId = $this->bookModel->getBookIdByUid($bookUid);
        $bookDetail = $this->bookModel->getBookById($bookId);

        if (!$params['highlight']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Highlight cannot be null!");
        }

        if (!isset($_SESSION['books']['highlights']['bookID']) || $bookId != $_SESSION['books']['highlights']['bookID']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST,
                "Inconsistency! You're trying to add highlight for different book!");
        }

        $highlightDetail['book_id'] = $bookId;
        $highlightDetail['highlight'] = $params['highlight'];
        $highlightDetail['page'] = $params['page'];
        $highlightDetail['location'] = $params['location'];
        $highlightDetail['blogPath'] = $params['blogPath'];
        $highlightDetail['author'] = $bookDetail['author'];
        $highlightDetail['source'] = $bookDetail['title'];
        $highlightDetail['type'] = 1;

        $highlightId = $this->bookModel->addHighlight($highlightDetail);

        $this->tagModel->updateSourceTags($params['tags'], $highlightId, Sources::HIGHLIGHT->value);

        unset($_SESSION['highlights']['minMaxID']);
        unset($_SESSION['books']['highlights']['bookID']);

        $resource['message'] = "Successfully added highlight";
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

    public function getPathsGraphicData(ServerRequestInterface $request, ResponseInterface $response)
    {
        $graphicDatas = $this->bookModel->getPathsGraphicData(30);

        $resource['data'] = $graphicDatas;
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

    public function getBookGraphicData(ServerRequestInterface $request, ResponseInterface $response)
    {
        $graphicDatas = $this->bookModel->getBooksGraphicData(30);

        $resource['data'] = $graphicDatas;
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

    public function getLibraries(ServerRequestInterface $request, ResponseInterface $response)
    {
        $books = [];
        $queryParams = $request->getQueryParams();
        $typesenseClient = new Typesense('libraries');

        // $books = $this->bookModel->getLibraries();

        if (isset($queryParams['search']) && $queryParams['search']) {
            $searchParameters = [
                'q' => $queryParams['search'],
                'query_by' => 'title'
            ];

            $searchResult = $typesenseClient->searchDocuments($searchParameters);
        }

        if (isset($queryParams['raw']) && $queryParams['raw']) {
            echo "<pre>";
            print_r($searchResult);
            die;
        }
      
        foreach ($searchResult['hits'] as $result) {
            $books[] = [
                'title' => $result['document']['title'] . " ({$result['document']['size']})",
                'author' => $result['document']['library'],
                'info_link' => $result['document']['url'],
                'ebook' => true,
                'page_count' => 'n/a',
            ];
        }

        $data = [
            'pageTitle' => 'Libraries | trackr',
            'books' => $books
        ];

        return $this->view->render($response, 'books/all.mustache', $data);
    }

}