<?php

namespace App\model;

use App\util\markdown\Markdown;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class LogModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;
    private $highlightModel;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
        $this->highlightModel = new HighlightModel($container);
    }

    public function getLogs($limit = 30)
    {
        $markdownClient = new Markdown();
        $logs = [];

        $sql = 'SELECT l.id, l.date, l.highlight_id, l.user_id, h.highlight AS log
                FROM logs l
                INNER JOIN highlights h ON l.highlight_id = h.id
                WHERE l.user_id = :user_id ORDER BY l.id DESC LIMIT :limit';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':limit', $limit, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['log'] = $markdownClient->convert($row['log']);
            $row['versionCount'] = $this->highlightModel->getVersionsCountById($row['highlight_id']);
            $logs[] = $row;
        }

        return $logs;
    }

    public function getLog($date)
    {
        $log = [];

        $sql = 'SELECT l.id, l.date, l.highlight_id, l.user_id, h.highlight AS log
                FROM logs l
                INNER JOIN highlights h ON l.highlight_id = h.id
                WHERE l.date = :date AND l.user_id = :user_id ORDER BY l.id DESC LIMIT 1';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':date', $date, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $log = $row;
        }

        return $log;
    }

    public function insert($date, $highlightId = null)
    {
        $sql = 'INSERT INTO logs (date, highlight_id, user_id)
                VALUES (:date, :highlight_id, :user_id)';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':date', $date, \PDO::PARAM_STR);
        $stm->bindParam(':highlight_id', $highlightId, \PDO::PARAM_INT);
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

    public function saveOldVersion($logId, $oldLog)
    {
        $now = time();

        $sql = 'INSERT INTO log_versions (log_id, old, created_at) 
                VALUES (:log_id, :old_log, :created_at)';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':log_id', $logId, \PDO::PARAM_INT);
        $stm->bindParam(':old_log', $oldLog, \PDO::PARAM_STR);
        $stm->bindParam(':created_at', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

}