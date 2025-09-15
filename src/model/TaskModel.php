<?php

namespace App\model;

use App\enum\EisenhowerStatus;
use App\enum\EisenhowerStatusColor;
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

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
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
            $tasks[$status->value]['statusName'] = ucfirst(strtolower($status->name));
            $tasks[$status->value]['statusValue'] = $status->value;
            $tasks[$status->value]['columnDivId'] = strtolower($status->name) . '-column';
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['relativeUpdatedAt'] = TimeUtil::relativeTime($row['updated_at']);
            $row['existBody'] = !empty($row['description_hid']);

            switch ($row['eisenhower_status']) {
                case EisenhowerStatus::DO->value:
                    $row['eisenhowerStatusColor'] = EisenhowerStatusColor::DO->value;
                    $row['eisenhowerStatusName'] = 'Do';
                    break;
                case EisenhowerStatus::SCHEDULE->value:
                    $row['eisenhowerStatusColor'] = EisenhowerStatusColor::SCHEDULE->value;
                    $row['eisenhowerStatusName'] = 'Schedule';
                    break;
                case EisenhowerStatus::DELEGATE->value:
                    $row['eisenhowerStatusColor'] = EisenhowerStatusColor::DELEGATE->value;
                    $row['eisenhowerStatusName'] = 'Delegate';
                    break;
                case EisenhowerStatus::ELIMINATE->value:
                    $row['eisenhowerStatusColor'] = EisenhowerStatusColor::ELIMINATE->value;
                    $row['eisenhowerStatusName'] = 'Eliminate';
                    break;
                default:
                    error_log("Unknown eisenhower status: " . $row['eisenhower_status']);
            }

            switch ($row['status']) {
                case TaskStatus::BACKLOG->value:
                    echo "Backlog task found: " . $row['title'] . "\n";
                    $tasks[TaskStatus::BACKLOG->value]['tasks'][] = $row;
                    break;
                case TaskStatus::TODO->value:
                    $tasks[TaskStatus::TODO->value]['tasks'][] = $row;
                    break;
                case TaskStatus::INPROGRESS->value:
                    $tasks[TaskStatus::INPROGRESS->value]['tasks'][] = $row;
                    break;
                case TaskStatus::DONE->value:
                    $tasks[TaskStatus::DONE->value]['tasks'][] = $row;
                    break;
                case TaskStatus::CANCELED->value:
                    $tasks[TaskStatus::CANCELED->value]['tasks'][] = $row;
                    break;
                default:
                    echo "Unknown task status: " . $row['status'] . "\n";
                    error_log("Unknown task status: " . $row['status']);
            }

        }

        // sort tasks by status order
        ksort($tasks);

        return $tasks;
    }

    public function createTask($title, $boardId, $descriptionHid = null, $eisenhowerStatus = 0, $priority = 0, $status = 0)
    {
        $uid = UID::generate();
        $now = date('Y-m-d H:i:s');
        $title = trim($title);

        $sql = 'INSERT INTO tasks (uid, title, board_id, description_hid, eisenhower_status, status, user_id, created_at, updated_at)
                VALUES (:uid, :title, :board_id, :description_hid, :eisenhower_status, :status, :user_id, :created_at, :updated_at)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $uid, \PDO::PARAM_STR);
        $stm->bindParam(':title', $title, \PDO::PARAM_STR);
        $stm->bindParam(':board_id', $boardId, \PDO::PARAM_INT);
        $stm->bindParam(':description_hid', $descriptionHid, \PDO::PARAM_STR);
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
            'description_hid' => $descriptionHid,
            'priority' => $priority,
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

}