<?php

namespace App\model;

use App\enum\EisenhowerStatus;
use App\enum\EisenhowerStatusColor;
use App\enum\Sources;
use App\enum\TaskStatus;
use App\util\TimeUtil;
use App\util\UID;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class TaskModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;
    private $tagModel;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
        $this->tagModel = new TagModel($container);
    }

    public function getTaskByUid($uid)
    {
        $task = [];

        $sql = 'SELECT *
                FROM tasks 
                WHERE uid = :uid AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':uid', $uid, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $task = $row;
        }

        return $task;
    }

    public function getTaskByTaskUidAndBoardId($taskUid, $boardId)
    {
        $task = [];

        $sql = 'SELECT *
                FROM tasks 
                WHERE uid = :uid AND board_id = :board_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':uid', $taskUid, \PDO::PARAM_STR);
        $stm->bindParam(':board_id', $boardId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $task = $row;
        }

        return $task;
    }

    public function getTasksByBoardId($boardId)
    {
        $tasks = [];

        $sql = 'SELECT *
                FROM tasks 
                WHERE board_id = :board_id AND user_id = :user_id 
                ORDER BY updated_at ASC';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':board_id', $boardId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $tasks[] = $row;
        }

        return $tasks;
    }

    public function getTasksByBoardIdAndStatus($boardId)
    {
        $tasks = [];

        $sql = 'SELECT *
                FROM tasks
                WHERE board_id = :board_id AND user_id = :user_id 
                ORDER BY updated_at DESC';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':board_id', $boardId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        foreach (TaskStatus::cases() as $status) {
            $tasks[$status->value]['tasks'] = [];
            $tasks[$status->value]['statusName'] = $status->capitalizedStatusName();
            $tasks[$status->value]['statusValue'] = $status->value;
            $tasks[$status->value]['columnDivId'] = strtolower($status->name) . '-column';
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['relativeUpdatedAt'] = TimeUtil::relativeTime($row['updated_at']);

            if ($row['body_hid']) {
                $row['existBody'] = true;
                $tags = $this->tagModel->getTagsBySourceId($row['body_hid'], Sources::HIGHLIGHT->value);
                $row['tags'] = $tags['raw_tags'];
            }

            $eisenhowerStatusName = EisenhowerStatus::from($row['eisenhower_status'])->name; // get case name from value
            $row['eisenhowerStatusColor'] = EisenhowerStatusColor::valueFromName($eisenhowerStatusName);
            $row['eisenhowerStatusName'] = EisenhowerStatus::from($row['eisenhower_status'])->capitalizedStatusName();

            $tasks[$row['status']]['tasks'][] = $row;

        }

        // sort tasks by status order
        ksort($tasks);

        return $tasks;
    }

    public function createTask($title, $boardId, $bodyHid = null, $eisenhowerStatus = 0, $priority = 0, $status = 0)
    {
        $uid = UID::generate();
        $now = date('Y-m-d H:i:s');
        $title = trim($title);

        $sql = 'INSERT INTO tasks (uid, title, board_id, body_hid, eisenhower_status, status, user_id, created_at, updated_at)
                VALUES (:uid, :title, :board_id, :body_hid, :eisenhower_status, :status, :user_id, :created_at, :updated_at)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $uid, \PDO::PARAM_STR);
        $stm->bindParam(':title', $title, \PDO::PARAM_STR);
        $stm->bindParam(':board_id', $boardId, \PDO::PARAM_INT);
        $stm->bindParam(':body_hid', $bodyHid, \PDO::PARAM_STR);
        $stm->bindParam(':eisenhower_status', $eisenhowerStatus, \PDO::PARAM_INT);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':created_at', $now, \PDO::PARAM_STR);
        $stm->bindParam(':updated_at', $now, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return [
            'id' => $this->dbConnection->lastInsertId(),
            'uid' => $uid,
            'title' => $title,
            'board_id' => $boardId,
            'body_hid' => $bodyHid,
            'eisenhower_status' => $eisenhowerStatus,
            'status' => $status,
            'user_id' => $_SESSION['userInfos']['user_id'],
            'created_at' => $now,
            'updated_at' => $now
        ];
    }

    public function updateTaskStatus($taskId, $newStatus)
    {
        $now = date('Y-m-d H:i:s');

        $sql = 'UPDATE tasks
                SET status = :status, updated_at = :updated_at
                WHERE id = :task_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':status', $newStatus, \PDO::PARAM_INT);
        $stm->bindParam(':updated_at', $now, \PDO::PARAM_STR);
        $stm->bindParam(':task_id', $taskId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateTaskBody($taskId, $bodyHid)
    {
        $now = date('Y-m-d H:i:s');

        $sql = 'UPDATE tasks
                SET body_hid = :body_hid, updated_at = :updated_at
                WHERE id = :task_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':body_hid', $bodyHid, \PDO::PARAM_INT);
        $stm->bindParam(':updated_at', $now, \PDO::PARAM_STR);
        $stm->bindParam(':task_id', $taskId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

}