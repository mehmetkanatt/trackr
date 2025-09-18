<?php

namespace App\model;

use App\util\UID;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class BoardModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function getBoards()
    {
        $boards = [];

        $sql = 'SELECT * FROM boards WHERE user_id = :user_id ORDER BY updated_at DESC';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $boards[] = $row;
        }

        return $boards;
    }

    public function getBoardByUid($boardUid)
    {
        $boards = [];

        $sql = 'SELECT * FROM boards WHERE user_id = :user_id AND uid = :uid';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':uid', $boardUid, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $boards = $row;
        }

        return $boards;
    }

    public function createBoard($title, $description, $status = 0)
    {
        // status: 0 = active, 1 = archived, 2 = deleted
        $uid = UID::generate();
        $now = date('Y-m-d H:i:s');
        $title = trim($title);

        $sql = 'INSERT INTO boards (uid, title, description, status, user_id, created_at, updated_at)
                VALUES (:uid, :title, :description, :status, :user_id, :created_at, :updated_at)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $uid, \PDO::PARAM_STR);
        $stm->bindParam(':title', $title, \PDO::PARAM_STR);
        $stm->bindParam(':description', $description, \PDO::PARAM_STR);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':created_at', $now, \PDO::PARAM_STR);
        $stm->bindParam(':updated_at', $now, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function update($date, $highlightId)
    {
        $sql = 'UPDATE logs
                SET highlight_id = :highlight_id
                WHERE user_id = :user_id AND date = :date';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':date', $date, \PDO::PARAM_STR);
        $stm->bindParam(':highlight_id', $highlightId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

}