<?php

namespace App\model;

use App\enum\ActivityLogSources;
use App\enum\BookmarkStatus;
use App\enum\BookStatus;
use App\enum\TaskStatus;
use App\util\TimeUtil;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class ActivityModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;
    private $bookmarkModel;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
        $this->bookmarkModel = new BookmarkModel($container);
    }

    public function logReadingProgress($pathName, $authorAndBook, $amount, $sourceId, $createdAt = null)
    {
        // Old: addActivityLog($pathDetails['id'], $bookId, "read {$params['amount']} page(s)")
        $source = ActivityLogSources::BOOK_TRACKINGS->value;
        $activity = "[$pathName] Read '$authorAndBook' $amount page" . ($amount > 1 ? 's' : '');

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logBookReadingStatus($pathName, $authorAndBook, $oldStatus, $newStatus, $sourceId, $createdAt = null)
    {
        // Old: addActivityLog($pathId, $bookId, "changed book status from {$details['status']} to {$params['status']}");
        $source = ActivityLogSources::PATH_BOOKS->value;
        $oldStatus = BookStatus::from($oldStatus)->capitalizedStatusName();
        $newStatus = BookStatus::from($newStatus)->capitalizedStatusName();
        $activity = "[$pathName] Changed book status of '$authorAndBook' from $oldStatus to $newStatus";

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logAddBookToLibrary($authorAndBook, $sourceId, $note = null, $createdAt = null)
    {
        // Old: addActivityLog(null, $bookId, "added to library");
        $source = ActivityLogSources::BOOKS_OWNERSHIP->value;
        $activity = "Added '$authorAndBook' to library." . ($note ? " (Note: $note)" : '');

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logCreateNewBook($authorAndBook, $sourceId, $createdAt = null)
    {
        // Old: addActivityLog(null, $bookId, 'created new book');
        $source = ActivityLogSources::BOOKS->value;
        $activity = "Created new book '$authorAndBook'";

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logCreateNewPath($pathName, $sourceId, $createdAt = null)
    {
        // Old: addActivityLog($pathID, null, 'created new path');
        $source = ActivityLogSources::PATHS->value;
        $activity = "Created new path '$pathName'";

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logAddBookToPath($pathName, $authorAndBook, $sourceId, $createdAt = null)
    {
        // Old: addActivityLog($pathId, $bookId, "added to path");
        $source = ActivityLogSources::PATH_BOOKS->value;
        $activity = "[$pathName] Added '$authorAndBook' to path";

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logRemoveBookFromPath($pathName, $authorAndBook, $sourceId, $createdAt = null)
    {
        // Old: addActivityLog($pathId, $bookId, 'removed from path');
        $source = ActivityLogSources::PATH_BOOKS->value;
        $activity = "[$pathName] Removed '$authorAndBook' from path";

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logExtendPathFinishDate($pathName, $dayCount, $sourceId, $createdAt = null)
    {
        // Old: addActivityLog($pathId, null, "extend path finish date");
        $source = ActivityLogSources::PATHS->value;
        $activity = "Extended '$pathName' path finish date $dayCount more day" . ($dayCount > 1 ? 's' : '');

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logRateBook($pathName, $authorAndBook, $rate, $sourceId, $createdAt = null)
    {
        // Old: addActivityLog($booksFinishedDetails['pathName'], $booksFinishedDetails['book_id'], "rated {$params['rate']}");
        $source = ActivityLogSources::BOOKS_FINISHED->value;
        $activity = "[$pathName] Rated '$authorAndBook' $rate star" . ($rate > 1 ? 's' : '');

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logCreateNewAuthor($author, $sourceId, $createdAt = null)
    {
        // Old: addActivityLog(null, null, "add new author: $author");
        $source = ActivityLogSources::AUTHOR->value;
        $activity = "Created new author '$author'";

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logCreateNewBookmark($sourceId, $createdAt = null)
    {
        $source = ActivityLogSources::BOOKMARKS->value;
        $activity = "Created new bookmark: :link";

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logBookmarkStatus($sourceId, $oldStatus, $newStatus, $createdAt = null)
    {
        $source = ActivityLogSources::BOOKMARKS->value;
        $oldStatus = BookmarkStatus::from($oldStatus)->capitalizedStatusName();
        $newStatus = BookmarkStatus::from($newStatus)->capitalizedStatusName();
        $activity = "Changed :link status from '$oldStatus' to '$newStatus'";

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logTaskStatus($boardName, $taskName, $sourceId, $oldStatus, $newStatus, $createdAt = null)
    {
        $source = ActivityLogSources::TASKS->value;
        $oldStatus = TaskStatus::from($oldStatus)->capitalizedStatusName();
        $newStatus = TaskStatus::from($newStatus)->capitalizedStatusName();
        $activity = "[$boardName] Changed '$taskName' task status from '$oldStatus' to '$newStatus'";

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logCreateNewTask($boardName, $taskName, $sourceId, $createdAt = null)
    {
        $source = ActivityLogSources::TASKS->value;
        $activity = "[$boardName] Created new task '$taskName'";

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    public function logCreateNewBoard($boardName, $sourceId, $createdAt = null)
    {
        $source = ActivityLogSources::TASKS->value;
        $activity = "Created new board '$boardName'";

        return $this->create($source, $sourceId, $activity, $createdAt);
    }

    private function create(string $source, int $sourceId, string $activity, string $createdAt = null, int $type = 0)
    {
        $now = $createdAt ?? date('Y-m-d H:i:s');

        $sql = 'INSERT INTO activity_logs (source, source_id, activity, created_at, user_id, type)
                VALUES (:source, :source_id, :activity, :created_at, :user_id, :type)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':source', $source, \PDO::PARAM_STR);
        $stm->bindParam(':source_id', $sourceId, \PDO::PARAM_INT);
        $stm->bindParam(':activity', $activity, \PDO::PARAM_STR);
        $stm->bindParam(':created_at', $now, \PDO::PARAM_STR);
        $stm->bindParam(':type', $type, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function getActivities()
    {
        $activities = [];

        $sql = 'SELECT *
                FROM activity_logs 
                WHERE user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $activities[] = $this->prepareActivityLogForDisplay($row);
        }

        return $activities;
    }

    public function getActivitiesFilterByDate($from, $to = null)
    {
        $to = $to ?? date('Y-m-d H:i:s', time());
        $activities = [];

        $sql = 'SELECT *
                FROM activity_logs 
                WHERE user_id = :user_id AND created_at >= :dateStart AND created_at <= :dateEnd
                ORDER BY id DESC';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':dateStart', $from, \PDO::PARAM_STR);
        $stm->bindParam(':dateEnd', $to, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $activities[] = $this->prepareActivityLogForDisplay($row);
        }

        return $activities;
    }

    private function prepareActivityLogForDisplay($activityLog) {
        $activityLog['relativeCreatedAt'] = TimeUtil::relativeTime($activityLog['created_at']);

        if ($activityLog['source'] === ActivityLogSources::BOOKMARKS->value) {
            $bookmark = $this->bookmarkModel->getChildBookmarkById($activityLog['source_id'], $_SESSION['userInfos']['user_id']);
            if ($bookmark) {
                $activityLog['activity'] = str_replace(':link', "<a href=\"{$bookmark['bookmark']}\" target=\"_blank\" rel=\"noopener noreferrer\">{$bookmark['title']}</a>", $activityLog['activity']);
            } else {
                $activityLog['activity'] = str_replace(':link', 'Link Not Found', $activityLog['activity']);
            }
        }
        return $activityLog;
    }
}