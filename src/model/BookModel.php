<?php

namespace App\model;

use App\exception\CustomException;
use App\util\Util;
use Psr\Container\ContainerInterface;

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
                ORDER BY id ASC
                LIMIT 1';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
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
                FROM book_trackings';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $total = $row['total'];
        }

        return $total;
    }

    public function readingAverage()
    {
        $start = $this->getStartOfReadings();
        $from = time();
        $diff = Util::epochDateDiff($from, $start);
        $total = $this->getReadingTotal();

        $result['average'] = $total / $diff;
        $result['total'] = $total;
        $result['diff'] = $diff;

        return $result;
    }

    public function getBookPaths()
    {
        $today = time();
        $list = [];
        $paths = $this->getPathsList();

        foreach ($paths as $path){

            if ($today > $path['finish_epoch']) {
                $this->changePathStatus($path['path_id'], 1);
                continue;
            }

            $path['remaining_page'] = $this->getBooksRemainingPageCount($path['path_id']);
            $path['day_diff'] = Util::epochDateDiff($path['finish_epoch'], $today);
            $path['path_day_count'] = Util::epochDateDiff($path['finish_epoch'], $path['start_epoch']);
            $path['ratio'] = (($path['path_day_count'] - $path['day_diff']) / $path['path_day_count']) * 100;
            $path['today_processed'] = $this->getBookPathsDailyRemainings($path['path_id']);

            $dailyAmount = $path['remaining_page'] / $path['day_diff'];
            $path['daily_amount'] = round($dailyAmount, 2);

            if ($path['day_diff'] <= 3) {
                $path['remaining_day_warning'] = true;
            }

            $list[] = $path;
        }

        return $list;
    }

    public function getBooksRemainingPageCount($pathId)
    {
        $total = 0;

        $sql = 'SELECT b.id, b.page_count
                FROM books b
                INNER JOIN path_books pb ON b.id = pb.book_id
                WHERE pb.path_id = :path_id AND pb.status = 1';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $readAmount = $this->getReadAmount($row['id'], $pathId);
            $pageCount = $row['page_count'];
            $diff = $pageCount - $readAmount;

            if ($pageCount != 0) {
                if ($diff > 0) {
                    $total += $diff;
                } else {
                    $this->insertNewReadRecord($pathId, $row['id']);
                    $this->setBookPathStatus($pathId, $row['id'], self::DONE);
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
                WHERE book_id=:book_id AND path_id=:path_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $total = $row['total'];
        }

        return $total;
    }

    public function insertNewReadRecord($pathId, $bookId)
    {
        $now = date("Y-m-d H:i:s");

        $sql = 'INSERT INTO books_finished (book_id, path_id, start_date, finish_date)
                VALUES(:book_id,:path_id,:start_date,:finish_date)';

        $startDate = $this->findStartDateOfBook($bookId);
        $startDate = date("Y-m-d H:i:s", $startDate);

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':start_date', $startDate, \PDO::PARAM_STR);
        $stm->bindParam(':finish_date', $now, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function findStartDateOfBook($bookId)
    {
        $startDate = 0;

        $sql = 'SELECT record_date 
                FROM book_trackings 
                WHERE book_id=:book_id 
                ORDER BY record_date ASC LIMIT 1;';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $startDate = $row['record_date'];
        }

        return $startDate;
    }

    public function setBookPathStatus($pathId, $bookId, $status)
    {
        $sql = 'UPDATE path_books
                SET status=:status
                WHERE book_id=:book_id AND path_id = :path_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getPathsList()
    {
        $sql = 'SELECT id AS path_id, name AS path_name, start, finish 
                FROM paths ';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
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

    public function changePathStatus($pathId, $status)
    {
        $sql = 'UPDATE paths 
                SET status = :status 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
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
                WHERE pb.path_id = :path_id AND (bt.record_date > :today && bt.record_date < :tomorrow)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':today', $today, \PDO::PARAM_INT);
        $stm->bindParam(':tomorrow', $tomorrow, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $total = $row['amount'];
        }

        return $total == NULL ? 0 : $total;
    }
}