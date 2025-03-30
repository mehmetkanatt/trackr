<?php

namespace App\model;

use App\util\EncryptionUtil;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class AuthModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function login($username, $password)
    {
        $password = hash('sha512', $password);

        $sql = 'SELECT id, username, created, encryption_key
                FROM users
                WHERE username =:username AND password =:password';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':username', $username, \PDO::PARAM_STR);
        $stm->bindParam(':password', $password, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, 'Something went wrong');
        }

        if (!$stm->rowCount()) {
            throw CustomException::clientError(StatusCode::HTTP_UNAUTHORIZED, 'Credentials are incorrect!', 'Credentials are incorrect!');
        }

        session_regenerate_id(true);

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $_SESSION['userInfos']['user_id'] = $row['id'];
            $_SESSION['userInfos']['encryption_key'] = unserialize($row['encryption_key']);
        }

        $_SESSION['userInfos']['username'] = $username;

        return true;
    }

    public function userCreatedBefore($username)
    {
        $sql = 'SELECT id FROM users WHERE username = :username';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':username', $username, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_INTERNAL_SERVER_ERROR, 'Something went wrong');
        }

        if ($stm->rowCount()) {
            return true;
        }

        return false;
    }

    public function register($username, $password)
    {
        $password = hash('sha512', trim($password));
        $username = trim($username);
        $created = time();
        $encryptionKey = serialize(EncryptionUtil::createEncryptionKey());
        $apiToken = bin2hex(random_bytes(32));

        $sql = 'INSERT INTO users (username, password, created, encryption_key, api_token) 
                VALUES(:username, :password, :created, :encryption_key, :api_token)';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':username', $username, \PDO::PARAM_STR);
        $stm->bindParam(':password', $password, \PDO::PARAM_STR);
        $stm->bindParam(':created', $created, \PDO::PARAM_INT);
        $stm->bindParam(':encryption_key', $encryptionKey, \PDO::PARAM_STR);
        $stm->bindParam(':api_token', $apiToken, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getUserByApiToken($apiToken)
    {
        $user = [];

        $sql = 'SELECT id, username, created, encryption_key, api_token
                FROM users
                WHERE api_token = :api_token';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':api_token', $apiToken, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, 'Something went wrong');
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $user = $row;
        }

        return $user;
    }

}