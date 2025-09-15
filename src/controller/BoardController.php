<?php

namespace App\controller;

use App\enum\EisenhowerStatus;
use App\enum\TaskStatus;
use App\exception\CustomException;
use App\model\BoardModel;
use App\model\TaskModel;
use App\util\lang;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class BoardController extends Controller
{
    private $boardModel;
    private $taskModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->boardModel = new BoardModel($container);
        $this->taskModel = new TaskModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $boards = $this->boardModel->getBoards();

        $data = [
            'boards' => $boards,
            'pageTitle' => 'Boards | trackr',
            'activeBoards' => 'active',
        ];

        return $this->view->render($response, 'boards/index.mustache', $data);
    }

    public function tasks(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        /*
         * eisenhower_status (0,1,2,3)
         * status (0,1,2,3,4)
         */

        $boardUid = $args['boardUID'];
        $board = $this->boardModel->getBoardByUid($boardUid);

        if (empty($board)) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::BOARD_NOT_FOUND);
        }

        $tasks = $this->taskModel->getTasksByBoardIdAndStatus($board['id']);

        $data = [
            'columns' => $tasks,
            'boardName' => $board['title'],
            'boardUID' => $board['uid'],
            'pageTitle' => $board['title'] . ' Tasks | trackr',
            'activeBoards' => 'active',
        ];

        return $this->view->render($response, 'boards/tasks.mustache', $data);
    }

    public function createBoard(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = (array)$request->getParsedBody();

        if (empty($params['title'])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::BOARD_TITLE_CANNOT_BE_NULL);
        }

        $this->boardModel->createBoard($params['title'], null);

        $data = [
            'message' => lang\En::BOARD_SUCCESSFULLY_CREATED,
        ];

        return $this->response(StatusCode::HTTP_CREATED, $data);
    }

    public function createTask(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $boardUid = $args['boardUID'];
        $params = (array)$request->getParsedBody();

        if (empty($params['title'])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::TASK_TITLE_CANNOT_BE_NULL);
        }

        $board = $this->boardModel->getBoardByUid($boardUid);

        if (empty($board)) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::BOARD_NOT_FOUND);
        }

        $task = $this->taskModel->createTask($params['title'], $board['id'], null, $params['eisenhowerStatus']);

        $data = [
            'taskUid' => $task['uid'],
            'message' => lang\En::TASK_SUCCESSFULLY_CREATED,
        ];

        return $this->response(StatusCode::HTTP_CREATED, $data);
    }

    public function changeTaskStatus(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $boardUid = $args['boardUID'];
        $taskUid = $args['taskUID'];
        $params = (array)$request->getParsedBody();

        if (!isset($params['to'])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::TASK_NEW_STATUS_CANNOT_BE_NULL);
        }

        if (TaskStatus::tryFrom($params['to']) === null) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::TASK_STATUS_INVALID);
        }

        if (TaskStatus::tryFrom($params['from']) === null) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::TASK_STATUS_INVALID);
        }

        $board = $this->boardModel->getBoardByUid($boardUid);

        if (empty($board)) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::BOARD_NOT_FOUND);
        }

        $task = $this->taskModel->getTaskByUid($taskUid);

        if (empty($task)) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::TASK_NOT_FOUND);
        }

        $this->taskModel->updateTaskStatus($task['id'], $params['to']);

        // TODO activity log girilmeli -- from=to olabilir o zaman log girilmemeli

        $data = [
            'message' => lang\En::TASK_STATUS_SUCCESSFULLY_UPDATED,
        ];

        return $this->response(StatusCode::HTTP_CREATED, $data);
    }

}
