<?php

namespace App\model;

use App\enum\BookStatus;
use App\enum\PathStatus;
use App\enum\StatusColors;
use App\util\TimeUtil;
use App\exception\CustomException;
use App\util\UID;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class BookModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function getStartOfReadings()
    {
        $start = null;

        $sql = 'SELECT record_date 
                FROM book_trackings
                WHERE user_id = :user_id
                ORDER BY id ASC
                LIMIT 1';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $start = $row['record_date'];
        }

        return $start;
    }

    public function getReadingTotal()
    {
        $total = 0;

        $sql = 'SELECT SUM(amount) AS total 
                FROM book_trackings
                WHERE user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $total = $row['total'];
        }

        return $total;
    }

    public function getPathIdByUid($uid)
    {
        $id = 0;

        $sql = 'SELECT id 
                FROM paths
                WHERE uid = :uid AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $uid, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
        }

        return $id;
    }

    public function getBookByISBN($isbn)
    {
        $book = [];

        $sql = 'SELECT *
                FROM books
                WHERE isbn = :isbn';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':isbn', $isbn, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $book = $row;
        }

        return $book;
    }

    public function getBookById($bookId)
    {
        $book = [];

        $sql = "SELECT *,
                       CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ')
                               FROM book_authors ba USE INDEX (idx_book_id)
                                        INNER JOIN author a ON ba.author_id = a.id
                               WHERE ba.book_id = b.id)) AS author
                FROM books b
                WHERE b.id = :id";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $bookId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $book = $row;
        }

        return $book;
    }

    public function getBookByGivenColumn($column, $param)
    {
        $result = [];

        $sql = "SELECT *,
                       CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ')
                               FROM book_authors ba USE INDEX (idx_book_id)
                                        INNER JOIN author a ON ba.author_id = a.id
                               WHERE ba.book_id = b.id)) AS author
                FROM books b
                WHERE b.$column = :param";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':param', $param, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $result = $row;
        }

        return $result;
    }

    public function getAuthorBookList()
    {
        $books = [];

        $sql = "SELECT b.id, b.uid, CONCAT(CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ')
                                      FROM book_authors ba USE INDEX (idx_book_id)
                                               INNER JOIN author a ON ba.author_id = a.id
                                      WHERE ba.book_id = b.id)), ' - ', b.title) AS book
                FROM books b";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $books[] = $row;
        }

        $_SESSION['books']['list'] = $books;
        return $books;
    }

    public function getPublishers()
    {
        $sql = 'SELECT id, name
                FROM publishers';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = $row;
        }

        return $list;
    }

    public function getBookIdByUid($uid)
    {
        $id = 0;

        $sql = 'SELECT id 
                FROM books
                WHERE uid = :uid';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $uid, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
        }

        return $id;
    }

    public function readingAverage()
    {
        $start = $this->getStartOfReadings();
        $start = $start ?? time();
        $from = time();
        $diff = TimeUtil::epochDateDiff($from, $start);
        $diff = $diff ?? 1;
        $total = $this->getReadingTotal();

        $result['average'] = $total / $diff;
        $result['total'] = $total;
        $result['diff'] = $diff;
        $_SESSION['books']['readingAverage'] = $result;

        return $result;
    }

    public function getBookPaths()
    {
        $today = time();
        $list = [];
        $paths = $this->getPathsList();

        foreach ($paths as $path) {

            if ($today > $path['finish_epoch']) {

                if ((int)$path['status'] !== PathStatus::DONE->value) {
                    $this->changePathStatus($path['path_id'], PathStatus::DONE->value);
                }

                $path['status'] = 1;
                $path['expired'] = true;
                $path['today_processed_text'] = 'EXPIRED!';
                $path['ratio'] = 'X';
                $path['ratioBadgeColor'] = 'danger';
                $path['day_diff_text_class'] = 'warning';
                $path['day_diff_text'] = "Done";
                $path['active_book_count'] = $this->getPathBookCountByPathID($path['path_id'], 'active');
                $path['done_book_count'] = $this->getPathBookCountByPathID($path['path_id'], 'done');
                $path['abandoned_book_count'] = $this->getPathBookCountByPathID($path['path_id'], 'abandoned');
                $list[] = $path;
                continue;
            }

            $path['remaining_page'] = $this->getBooksRemainingPageCount($path['path_id']);
            $path['day_diff'] = TimeUtil::epochDateDiff($path['finish_epoch'], $today);
            $path['path_day_count'] = TimeUtil::epochDateDiff($path['finish_epoch'], $path['start_epoch']);
            $path['ratio'] = '%' . round((($path['path_day_count'] - $path['day_diff']) / $path['path_day_count']) * 100);
            $path['ratioBadgeColor'] = 'warning';
            $path['today_processed'] = $this->getBookPathsDailyRemainings($path['path_id']);
            $path['active_book_count'] = $this->getPathBookCountByPathID($path['path_id'], 'active');
            $path['done_book_count'] = $this->getPathBookCountByPathID($path['path_id'], 'done');
            $path['abandoned_book_count'] = $this->getPathBookCountByPathID($path['path_id'], 'abandoned');

            $dailyAmount = $path['remaining_page'] / $path['day_diff'];
            $path['daily_amount'] = ceil($dailyAmount);
            $path['daily_amount_raw'] = round($dailyAmount, 2);

            if (!isset($_SESSION['books']['daily_reading_amount_inserted'])) {
                $this->insertDailyReadingAmount($dailyAmount, $path['path_id']);
            }

            if ($path['day_diff'] <= 3) {
                $path['remaining_day_warning'] = true;
            }

            if ($path['day_diff'] > 1) {
                $path['day_diff_text_class'] = 'primary';
                $path['day_diff_text'] = "{$path['day_diff']} days left";
            } else {
                $path['day_diff_text_class'] = 'danger';
                $path['day_diff_text'] = "Last day!";
            }

            if (!$path['today_processed']) {
                $pages = $path['daily_amount'] > 1 ? 'pages' : 'page';
                $path['today_processed_text'] = "You haven't read today. You have to read {$path['daily_amount']} $pages";
            } else {
                $pages = $path['today_processed'] > 1 ? 'pages' : 'page';
                $tmpDailyAmount = $path['daily_amount'] - $path['today_processed'];

                if ($tmpDailyAmount > 0) {
                    $path['today_processed_text'] = "You read {$path['today_processed']} $pages today. Read $tmpDailyAmount more pages";
                } else {
                    $path['today_processed_text'] = "<span class=\"text-success\">Congrats, you read'em all today! [{$path['today_processed']}/{$path['daily_amount']}] \m/</span>";
                }

            }

            $list[] = $path;
        }

        return $list;
    }

    public function getBooksRemainingPageCount($pathId)
    {
        $total = 0;

        $sql = 'SELECT b.id, b.page_count, b.ebook_version, b.ebook_page_count, (SELECT sum(amount) FROM book_trackings WHERE book_id=pb.book_id AND path_id=:path_id AND user_id = :user_id) AS readAmount
                FROM books b
                INNER JOIN path_books pb ON b.id = pb.book_id
                WHERE pb.path_id = :path_id AND (pb.status IN (0,1,4))';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $readAmount = $row['readAmount']; //$this->getReadAmount($row['id'], $pathId);

            if ((int)$row['ebook_version'] === 1) {
                $row['page_count'] = $row['ebook_page_count'];
            }

            $pageCount = $row['page_count'];
            $diff = $pageCount - $readAmount;

            if ($pageCount != 0) {
                if ($diff > 0) {
                    $total += $diff;
                } else {
                    $this->insertNewReadRecord($pathId, $row['id']);
                    $this->changePathBookStatus($pathId, $row['id'], BookStatus::DONE->value);
                }
            }

        }

        return $total;
    }

    public function getReadAmount($bookId, $pathId)
    {
        $total = 0;

        $sql = 'SELECT sum(amount) AS total 
                FROM book_trackings 
                WHERE book_id=:book_id AND path_id=:path_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $total = $row['total'];
        }

        return $total;
    }

    public function getBookDetailByBookIdAndPathId($bookId, $pathId)
    {
        $detail = [];

        $sql = 'SELECT pb.id, pb.path_id, pb.book_id, pb.status, pb.created, pb.updated, b.page_count, b.ebook_version, b.ebook_page_count
                FROM path_books pb
                INNER JOIN books b ON pb.book_id = b.id
                WHERE pb.book_id=:book_id AND pb.path_id=:path_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            if ($row['ebook_version']) {
                $row['page_count'] = $row['ebook_page_count'];
            }

            $detail = $row;
        }

        return $detail;
    }

    public function insertNewReadRecord($pathId, $bookId)
    {
        $now = date("Y-m-d H:i:s");

        $sql = 'INSERT INTO books_finished (book_id, path_id, start_date, finish_date, user_id, is_complete_book)
                VALUES(:book_id,:path_id,:start_date,:finish_date, :user_id, :is_complete_book)';

        $startDate = $this->findStartDateOfBook($bookId);
        $bookDetails = $this->getBookByGivenColumn('id', $bookId)[0];
        $startDate = date("Y-m-d H:i:s", $startDate);

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':start_date', $startDate, \PDO::PARAM_STR);
        $stm->bindParam(':finish_date', $now, \PDO::PARAM_STR);
        $stm->bindParam(':is_complete_book', $bookDetails['is_complete_book'], \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function insertPublisher($publisherName)
    {
        $now = time();

        $sql = 'INSERT INTO publishers (name, created_at)
                VALUES(:name, :created_at)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':name', $publisherName, \PDO::PARAM_STR);
        $stm->bindParam(':created_at', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function getPublisher($name)
    {
        $publisher = [];

        $sql = 'SELECT * 
                FROM publishers 
                WHERE name = :name';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':name', $name, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $publisher = $row;
        }

        return $publisher;
    }

    public function findStartDateOfBook($bookId)
    {
        $startDate = 0;

        $sql = 'SELECT record_date 
                FROM book_trackings 
                WHERE book_id=:book_id AND user_id = :user_id
                ORDER BY record_date ASC LIMIT 1;';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $startDate = $row['record_date'];
        }

        return $startDate;
    }

    public function changePathBookStatus($pathId, $bookId, $status)
    {
        $now = time();

        $sql = 'UPDATE path_books
                SET status = :status, updated = :updated
                WHERE path_id = :path_id AND book_id = :book_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':updated', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function changePathStatus($pathId, $status)
    {
        $sql = 'UPDATE paths 
                SET status = :status 
                WHERE id = :id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getBookPathsDailyRemainings($pathId)
    {
        $today = date('Y-m-d 00:00:00');
        $today = strtotime($today);
        $tomorrow = $today + 86400;
        $total = 0;

        $sql = 'SELECT sum(bt.amount) AS amount
                FROM book_trackings bt
                INNER JOIN path_books pb ON bt.book_id = pb.book_id
                WHERE pb.path_id = :path_id AND (bt.record_date > :today && bt.record_date < :tomorrow) AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':today', $today, \PDO::PARAM_INT);
        $stm->bindParam(':tomorrow', $tomorrow, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $total = $row['amount'];
        }

        return $total == null ? 0 : $total;
    }

    public function getDailyReadingAmount($date = null)
    {
        $startDay = $date ? date($date . ' 00:00:00') : date('Y-m-d 00:00:00');
        $startDay = strtotime($startDay);
        $finishDay = $startDay + 86400;
        $total = 0;

        $sql = 'SELECT sum(bt.amount) AS amount
                FROM book_trackings bt
                INNER JOIN path_books pb ON bt.book_id = pb.book_id
                WHERE (bt.record_date > :today && bt.record_date < :tomorrow) AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':today', $startDay, \PDO::PARAM_INT);
        $stm->bindParam(':tomorrow', $finishDay, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $total = $row['amount'];
        }

        return $total == null ? 0 : $total;
    }

    public function getAuthors()
    {
        $sql = 'SELECT id,author 
                FROM author';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $list[] = $row;
        }

        return $list;
    }

    public function getAuthorByName($authorName)
    {
        $author = [];

        $sql = 'SELECT id, author 
                FROM author
                WHERE author = :author';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':author', $authorName, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $author = $row;
        }

        return $author;
    }

    public function getAllBooks()
    {
        $sql = "SELECT b.id,
                       b.uid                             AS bookUID,
                       CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ')
                               FROM book_authors ba USE INDEX (idx_book_id)
                                        INNER JOIN author a ON ba.author_id = a.id
                               WHERE ba.book_id = b.id)) AS author,
                       b.title,
                       b.page_count,
                       IF(ISNULL(bo.id), 0, 1)           AS own,
                       bo.created_at,
                       b.info_link,
                       b.thumbnail,
                       b.thumbnail_small,
                       b.pdf,
                       b.epub
                FROM books b
                         LEFT JOIN books_ownership bo ON b.id = bo.book_id AND bo.user_id = :user_id
                ORDER BY b.id DESC";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            if (!$row['own']) {
                $row['add_to_library'] = true;
            }

            $row['created_at'] = date("Y-m-d", $row['created_at']);

            if ($row['pdf'] || $row['epub']) {
                $row['ebook'] = true;
            }

            $list[] = $row;
        }

        return $list;
    }

    public function getAuthorsByBookId($bookId)
    {
        $author = '';

        $sql = "SELECT (SELECT GROUP_CONCAT(a.author SEPARATOR ', ')
                FROM book_authors ba
                         INNER JOIN author a ON ba.author_id = a.id
                WHERE ba.book_id = :book_id) AS author";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $author = $row['author'];
        }

        return $author;
    }

    public function getPathsList($status = null)
    {
        $sql = 'SELECT id AS path_id, uid AS pathUID, name AS path_name, start, finish, status
                FROM paths
                WHERE user_id = :user_id AND id > 0';

        if ($status !== null) {
            $sql .= ' AND status = :status';
        }

        $sql .= ' ORDER BY id DESC';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if ($status !== null) {
            $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        }

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $paths = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['start_epoch'] = $row['start'];
            $row['finish_epoch'] = $row['finish'];
            $row['start'] = date('Y-m-d', $row['start']);
            $row['finish'] = date('Y-m-d', $row['finish']);

            $paths[] = $row;
        }

        return $paths;
    }

    public function getMyBooks()
    {
        $sql = "SELECT b.id,
                       b.uid                             AS bookUID,
                       CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ')
                               FROM book_authors ba USE INDEX (idx_book_id)
                                        INNER JOIN author a ON ba.author_id = a.id
                               WHERE ba.book_id = b.id)) AS author,
                       b.title,
                       b.page_count,
                       bo.created_at,
                       (IFNULL((SELECT true FROM books_finished bf WHERE bf.book_id = b.id AND bf.user_id = :user_id LIMIT 1),
                               false))                   AS is_read
                FROM books_ownership bo USE INDEX (idx_book_id)
                         INNER JOIN books b ON bo.book_id = b.id
                WHERE bo.user_id = :user_id
                ORDER BY bo.created_at DESC";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['created_at'] = date("Y-m-d", $row['created_at']);
            $row['remaining'] = 0;
            $row['read_status'] = $row['is_read'] ? 'success' : 'danger';

            $list[] = $row;
        }

        return $list;
    }

    public function finishedBooks()
    {
        $sql = "SELECT bf.id, b.uid, bf.book_id, b.title, b.page_count, b.ebook_version, b.ebook_page_count, bf.start_date, bf.finish_date, bf.rate, p.name AS pathName,
                        CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ') FROM book_authors ba INNER JOIN author a ON ba.author_id = a.id WHERE ba.book_id = b.id)) AS author
                FROM books_finished bf
                LEFT JOIN books b ON bf.book_id = b.id
                INNER JOIN paths p ON bf.path_id = p.id
                WHERE bf.user_id = :user_id
                ORDER BY finish_date DESC";
        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $books = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['start_date'] = $row['start_date'] ?? 'N/A';
            $row['finish_date'] = $row['finish_date'] ?? 'N/A';

            if ($row['ebook_version'] === 1) {
                $row['page_count'] = $row['ebook_page_count'];
            }

            $books[] = $row;
        }

        return $books;
    }

    public function finishedBookByID($finishedBookId)
    {
        $book = [];

        $sql = "SELECT * 
                FROM books_finished
                WHERE book_id = :book_id AND user_id = :user_id";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $finishedBookId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $book = $row;
        }

        return $book;
    }

    public function getBooksPathInside($pathId, $status = false)
    {
        $sql = "SELECT b.uid AS bookUID,
                       b.title,
                       b.id,
                       b.page_count,
                       b.ebook_version,
                       b.ebook_page_count,
                       b.pdf,
                       b.epub,
                       b.is_complete_book,
                       pb.status AS path_status,
                       pb.path_id,
                       p.uid AS pathUID,
                       CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ')
                               FROM book_authors ba
                                        INNER JOIN author a ON ba.author_id = a.id
                               WHERE ba.book_id = b.id)) AS author,
                       (SELECT sum(amount)
                        FROM book_trackings
                        WHERE book_id = b.id AND path_id = :path_id AND user_id = :user_id) AS readAmount
                FROM books b
                         INNER JOIN path_books pb ON b.id = pb.book_id
                         INNER JOIN paths p ON pb.path_id = p.id
                WHERE pb.path_id = :path_id
                  AND p.user_id = :user_id
                ORDER BY FIELD(pb.status, 1, 4, 0, 2, 3), b.page_count";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            if ($row['path_status'] === BookStatus::DONE->value) {

                if (!$status) {
                    $row['status_label'] = StatusColors::DONE->value;
                    $row['cardBodyBg'] = 'bg-success-light';
                    $row['readStatus'] = '<i class="fe fe-check fe-16"></i>';
                    $row['amount'] = false;
                    $row['remove'] = false;
                    $row['abandone'] = false;
                    $row['history'] = true;
                    $row['prioritize'] = false;
                    $row['showActions'] = false;
                    $row['showReadYesterday'] = false;
                    $list[] = $row;
                }

                continue;
            }

            $readAmount = $row['readAmount'] ?? 0; // $this->getReadAmount($row['id'], $row['path_id'])

            if ($row['ebook_version']) {
                $row['page_count'] = $row['ebook_page_count'];
            }

            $pageCount = $row['page_count'];
            $diff = $pageCount - $readAmount;

            if ($pageCount != 0 && $diff <= 0) {
                $this->insertNewReadRecord($row['path_id'], $row['id']);
                $this->changePathBookStatus($row['path_id'], $row['id'], BookStatus::DONE->value);

                if ($row['is_complete_book']) {
                    $_SESSION['badgeCounts']['finishedBookCount']++;
                }

                continue;
            }

            $row['divId'] = "div-{$row['id']}-" . uniqid();
            $row['status_label'] = StatusColors::NEW->value;
            $row['readStatus'] = "$readAmount / {$row['page_count']}";
            $row['ebook_exist'] = $row['pdf'] || $row['epub'] ? true : false;
            $row['remove'] = true;
            $row['history'] = false;
            $row['abandone'] = false;
            $row['prioritize'] = true;
            $row['amount'] = true;
            $row['showActions'] = true;
            $row['showReadYesterday'] = true;

            if ($row['path_status'] === BookStatus::PRIORITIZED->value) {
                $row['prioritize'] = false;
                $row['status_label'] = StatusColors::PRIORITIZED->value;
            }

            if ($readAmount) {
                $row['status_label'] = StatusColors::STARTED->value;
                $row['prioritize'] = false;
                $row['remove'] = false;
                $row['history'] = true;
                $row['abandone'] = true;
            }

            if ($row['path_status'] === BookStatus::ABANDONED->value) {
                $row['status_label'] = StatusColors::ABANDONED->value;
            }

            $list[] = $row;
        }

        return $list;
    }

    public function insertProgressRecord($bookId, $pathId, $amount, $recordDate)
    {
        $sql = 'INSERT INTO book_trackings (book_id, path_id, record_date, amount, user_id)
                VALUES(:book_id,:path_id,:record_date,:amount, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':record_date', $recordDate, \PDO::PARAM_INT);
        $stm->bindParam(':amount', $amount, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function createAuthor($author)
    {
        $sql = 'INSERT INTO author (author) 
                VALUES(:author)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':author', $author, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function addToLibrary($bookId, $note = null)
    {
        $addedDate = time();

        $sql = 'INSERT INTO books_ownership 
                SET book_id = :book_id, user_id = :user_id, created_at = :created_at';

        if ($note) {
            $sql .= ', note = :note';
        }

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':created_at', $addedDate, \PDO::PARAM_INT);

        if ($note) {
            $stm->bindParam(':note', $note, \PDO::PARAM_STR);
        }

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function addBookToPath($pathId, $bookId)
    {
        $status = 0;
        $now = time();

        $sql = 'INSERT INTO path_books (path_id, book_id, status, created, updated) 
                VALUES(:path_id, :book_id, :status, :created, :updated)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);
        $stm->bindParam(':updated', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()),
                'Could not add book to path!');
        }

        return $this->dbConnection->lastInsertId();
    }

    public function deleteBookTrackings($bookId)
    {
        $sql = 'DELETE FROM book_trackings
                WHERE BOOK_ID = :bookId AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookId', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function deleteBookRecordsFromPaths($bookId)
    {
        $sql = 'DELETE FROM path_books
                WHERE book_id = :bookId';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookId', $bookId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getPathById($pathId)
    {
        $sql = 'SELECT id, name, start, finish, status
                FROM paths 
                WHERE id = :id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $path = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['start'] = date('Y-m-d', $row['start']);
            $row['finish'] = date('Y-m-d', $row['finish']);

            $path = $row;
        }

        return $path;
    }

    public function getPathByUid($pathUid)
    {
        $sql = 'SELECT id, name, start, finish, status
                FROM paths 
                WHERE uid = :uid AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $pathUid, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $path = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['start'] = date('Y-m-d', $row['start']);
            $row['finish'] = date('Y-m-d', $row['finish']);

            $path = $row;
        }

        return $path;
    }

    public function extendFinishDate($pathId, $extendedFinishDate)
    {
        $sql = 'UPDATE paths 
                SET finish = :finish 
                WHERE id = :id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':finish', $extendedFinishDate, \PDO::PARAM_INT);
        $stm->bindParam(':uid', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function saveBook($params)
    {
        $now = time();
        $uid = UID::generate();

        $sql = 'INSERT INTO books (uid, title, subtitle, publisher, pdf, epub, added_date, page_count, published_date, description, isbn, thumbnail, thumbnail_small, info_link, is_complete_book, ebook_version, ebook_page_count)
                VALUES(:uid, :title, :subtitle, :publisher, :pdf, :epub, :added_date, :page_count, :published_date, :description, :isbn, :thumbnail, :thumbnail_small, :info_link, :is_complete_book, :ebook_version, :ebook_page_count)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $uid, \PDO::PARAM_STR);
        $stm->bindParam(':title', $params['bookTitle'], \PDO::PARAM_STR);
        $stm->bindParam(':subtitle', $params['subtitle'], \PDO::PARAM_STR);
        $stm->bindParam(':publisher', $params['publisher'], \PDO::PARAM_STR);
        $stm->bindParam(':pdf', $params['pdf'], \PDO::PARAM_INT);
        $stm->bindParam(':epub', $params['epub'], \PDO::PARAM_INT);
        $stm->bindParam(':added_date', $now, \PDO::PARAM_INT);
        $stm->bindParam(':page_count', $params['pageCount'], \PDO::PARAM_INT);
        $stm->bindParam(':published_date', $params['published_date'], \PDO::PARAM_INT);
        $stm->bindParam(':description', $params['description'], \PDO::PARAM_STR);
        $stm->bindParam(':isbn', $params['isbn'], \PDO::PARAM_STR);
        $stm->bindParam(':thumbnail', $params['thumbnail'], \PDO::PARAM_STR);
        $stm->bindParam(':thumbnail_small', $params['thumbnail_small'], \PDO::PARAM_STR);
        $stm->bindParam(':info_link', $params['info_link'], \PDO::PARAM_STR);
        $stm->bindParam(':is_complete_book', $params['is_complete_book'], \PDO::PARAM_INT);
        $stm->bindParam(':ebook_version', $params['ebook_version'], \PDO::PARAM_INT);
        $stm->bindParam(':ebook_page_count', $params['ebook_page_count'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function insertBookAuthor($bookId, $authorId)
    {
        $sql = 'INSERT INTO book_authors (author_id, book_id) 
                VALUES(:author_id, :book_id)';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':author_id', $authorId, \PDO::PARAM_INT);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function createPath($name, $finish)
    {
        $now = time();
        $finish = strtotime($finish);
        $uid = md5(uniqid(time(), true));

        $sql = 'INSERT INTO paths (uid, name, start, finish, user_id)
                VALUES(:uid, :name, :start, :finish, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $uid, \PDO::PARAM_STR);
        $stm->bindParam(':name', $name, \PDO::PARAM_STR);
        $stm->bindParam(':start', $now, \PDO::PARAM_INT);
        $stm->bindParam(':finish', $finish, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function deleteBookTrackingsByPath($bookId, $pathId)
    {
        $sql = 'DELETE FROM book_trackings
                WHERE BOOK_ID = :bookId AND PATH_ID = :pathId AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookId', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':pathId', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function deleteBookFromPath($bookId, $pathId)
    {
        $sql = 'DELETE FROM path_books
                WHERE book_id = :bookId AND path_id = :pathId';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookId', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':pathId', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getMyBooksCount()
    {
        $myBookCount = 0;

        $sql = "SELECT COUNT(*) AS myBookCount
                FROM books_ownership
                WHERE user_id = :user_id";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $myBookCount = $row['myBookCount'];
        }

        return $myBookCount;
    }

    public function getAllBookCount()
    {
        $allBookCount = 0;

        $sql = "SELECT COUNT(*) AS allBookCount
                FROM books
                WHERE is_complete_book = 1";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $allBookCount = $row['allBookCount'];
        }

        return $allBookCount;
    }

    public function getFinishedBookCount()
    {
        $finishedBookCount = 0;

        $sql = "SELECT COUNT(*) AS finishedBookCount
                FROM books_finished
                WHERE user_id = :user_id AND is_complete_book = 1";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $finishedBookCount = $row['finishedBookCount'];
        }

        return $finishedBookCount;
    }

    public function getPathBookCountByPathID($pathID, $status)
    {
        $bookCount = 0;

        if ($status == 'active') {
            $status = 'pb.status IN (0, 1, 4)';
        } elseif ($status == 'done') {
            $status = 'pb.status = 2';
        } elseif ($status == 'abandoned') {
            $status = 'pb.status = 3';
        }

        $sql = "SELECT count(*) AS path_book_count 
                FROM path_books pb
                INNER JOIN paths p ON pb.path_id = p.id
                WHERE pb.path_id = :pathID AND $status AND p.user_id = :user_id";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':pathID', $pathID, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $bookCount = $row['path_book_count'];
        }

        return $bookCount;
    }

    public function rateBook($finishedBookID, $rating)
    {
        $sql = 'UPDATE books_finished
                SET rate = :rate
                WHERE book_id = :finishedBookID AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':finishedBookID', $finishedBookID, \PDO::PARAM_INT);
        $stm->bindParam(':rate', $rating, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getReadingHistory($bookID = null)
    {
        $history = [];

        $sql = "SELECT p.name AS pathName, bt.record_date, bt.amount, b.title, 
                        CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ')
                               FROM book_authors ba
                                        INNER JOIN author a ON ba.author_id = a.id
                               WHERE ba.book_id = b.id)) AS author
                FROM book_trackings bt
                INNER JOIN paths p ON bt.path_id = p.id
                INNER JOIN books b ON bt.book_id = b.id
                WHERE bt.user_id = :user_id";

        if ($bookID) {
            $sql .= ' AND bt.book_id = :bookID';
        }

        $sql .= ' ORDER BY bt.id DESC';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if ($bookID) {
            $stm->bindParam(':bookID', $bookID, \PDO::PARAM_INT);
        }

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['record_date'] = date('Y-m-d H:i:s', $row['record_date']);
            $history[] = $row;
        }

        return $history;
    }

    public function addActivityLog($pathID, $bookID, $activity)
    {
        $timestamp = time();

        $sql = 'INSERT INTO book_activity_logs (path_id, book_id, activity, timestamp, user_id) 
                VALUES (:path_id, :book_id, :activity, :timestamp, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':path_id', $pathID, \PDO::PARAM_INT);
        $stm->bindParam(':book_id', $bookID, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':activity', $activity, \PDO::PARAM_STR);
        $stm->bindParam(':timestamp', $timestamp, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function createAuthorOperations($rawAuthor)
    {
        $resultIds = [];

        if (strpos($rawAuthor, ',') !== false) {
            $authors = explode(',', $rawAuthor);

            foreach ($authors as $author) {
                $author = trim($author);

                $authorExist = $this->getAuthorByName($author);

                if (!$authorExist) {
                    $resultIds[] = $this->createAuthor($author);
                    $this->addActivityLog(null, null, "add new author: $author");
                } else {
                    $resultIds[] = $authorExist['id'];
                }

            }

        } else {

            $authorExist = $this->getAuthorByName($rawAuthor);

            if (!$authorExist) {
                $author = trim($rawAuthor);
                $resultIds[] = $this->createAuthor($author);
                $this->addActivityLog(null, null, "add new author: $author");
            } else {
                $resultIds[] = $authorExist['id'];
            }
        }

        return $resultIds;
    }

    public function insertAuthorByChecking($authorName)
    {
        $authorName = trim($authorName);

        $authorExist = $this->getAuthorByName($authorName);

        if (!$authorExist) {
            $authorId = $this->createAuthor($authorName);
            $this->addActivityLog(null, null, "add new author: $authorName");
        } else {
            $authorId = $authorExist['id'];
        }

        return $authorId;
    }

    public function getPathsGraphicData($trackingDataDateLimit)
    {
        $fetchAfter = time() - ($trackingDataDateLimit * 86400);
        $paths = $this->getPathsList();
        $result = [];

        foreach ($paths as $path) {

            if ($path['status'] != 0) {
                continue;
            }

            $tmpData = [];
            $preparedData = [];
            $rawData = $this->getBookTrackingsByPathID($path['path_id'], $fetchAfter);
            $tmpData = $this->prepareBookTrackingsGraphicData($rawData, $trackingDataDateLimit);
            $preparedData['name'] = $path['path_name'];
            $preparedData['data'] = $tmpData['amounts'];
            $result['trackings'][] = $preparedData;
        }

        $result['dates'] = TimeUtil::generateDateListKV($trackingDataDateLimit);

        return $result;
    }

    /**
     * @param $pathID
     * @param $fetchAfter
     * @return array
     * @throws CustomException
     *
     * $fetchAfter is timestamp, will be use in where condition to fetch rows after it
     */
    public function getBookTrackingsByPathID($pathID, $fetchAfter)
    {
        $trackings = [];

        $sql = "SELECT FROM_UNIXTIME(record_date, '%Y-%m-%d') AS date, amount
                FROM book_trackings
                WHERE path_id = :pathID AND user_id = :user_id AND record_date > :fetchAfter";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':pathID', $pathID, \PDO::PARAM_INT);
        $stm->bindParam(':fetchAfter', $fetchAfter, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $trackings[] = $row;
        }

        return $trackings;
    }

    public function getHighlights($bookId)
    {
        $list = [];

        $sql = 'SELECT *
                FROM highlights
                WHERE book_id = :book_id AND user_id = :user_id';


        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['highlight'] = str_replace("\n", '<br>', $row['highlight']);

            $list[] = $row;
        }

        return $list;
    }

    public function addHighlight($highlightDetail)
    {
        $now = time();
        $highlight = trim($highlightDetail['highlight']);

        $sql = 'INSERT INTO highlights (highlight, author, source, page, location, blog_path, book_id, created, updated, user_id)
                VALUES(:highlight, :author, :source, :page, :location, :blog_path, :book_id, :created, :updated, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight', $highlight, \PDO::PARAM_STR);
        $stm->bindParam(':author', $highlightDetail['author'], \PDO::PARAM_STR);
        $stm->bindParam(':source', $highlightDetail['source'], \PDO::PARAM_STR);
        $stm->bindParam(':page', $highlightDetail['page'], \PDO::PARAM_INT);
        $stm->bindParam(':location', $highlightDetail['location'], \PDO::PARAM_STR);
        $stm->bindParam(':blog_path', $highlightDetail['blogPath'], \PDO::PARAM_STR);
        $stm->bindParam(':book_id', $highlightDetail['book_id'], \PDO::PARAM_STR);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);
        $stm->bindParam(':updated', $now, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $_SESSION['badgeCounts']['highlightsCount'] += 1;
        return $this->dbConnection->lastInsertId();
    }

    public function insertDailyReadingAmount($amount, $pathId)
    {
        $now = time();

        $sql = 'INSERT INTO daily_reading_amounts (amount, date, path_id, user_id)
                VALUES(:amount, :date, :path_id, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':amount', $amount, \PDO::PARAM_STR);
        $stm->bindParam(':date', $now, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $_SESSION['books']['daily_reading_amount_inserted'] = true;
        return true;
    }

    public function prepareBookTrackingsGraphicData($bookTrackings, $trackingDataDateLimit = 30)
    {
        $dates = TimeUtil::generateDateListArray($trackingDataDateLimit);
        $amountData = [];

        foreach ($bookTrackings as $trackingData) {
            if (isset($dates[$trackingData['date']])) {
                $dates[$trackingData['date']] += $trackingData['amount'];
            }
        }

        foreach ($dates as $date => $amount) {
            $amountData[] = $amount;
        }

        return ['amounts' => $amountData];
    }

    public function getLibraries()
    {
        $books = [];

        $sql = "SELECT l.book, l.size, l.library, ll.link FROM libraries l INNER JOIN library_links ll ON l.library = ll.name";
        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $books[] = $row;
        }

        return $books;
    }
}