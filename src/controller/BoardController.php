<?php

namespace App\controller;

use App\enum\EisenhowerStatus;
use App\enum\EisenhowerStatusColor;
use App\enum\TaskStatus;
use App\exception\CustomException;
use App\model\BoardModel;
use App\model\TaskModel;
use App\model\TagModel;
use App\model\HighlightModel;
use App\util\lang;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;
use App\model\ActivityModel;

class BoardController extends Controller
{
    private $boardModel;
    private $taskModel;
    private $activityModel;
    private $tagModel;
    private $highlightModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->boardModel = new BoardModel($container);
        $this->taskModel = new TaskModel($container);
        $this->activityModel = new ActivityModel($container);
        $this->tagModel = new TagModel($container);
        $this->highlightModel = new HighlightModel($container);

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

        $boardName = trim($params['title']);
        $description = trim($params['description']) ?? '';

        $boardId = $this->boardModel->createBoard($boardName, $description);

        $this->activityModel->logCreateNewBoard($boardName, $boardId);

        $data = [
            'message' => lang\En::BOARD_SUCCESSFULLY_CREATED,
        ];

        return $this->response(StatusCode::HTTP_CREATED, $data);
    }

    public function getTask(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $boardUid = $args['boardUID'];
        $taskUid = $args['taskUID'];
        $tags['raw_tags'] = [];

        $board = $this->boardModel->getBoardByUid($boardUid);

        if (empty($board)) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::BOARD_NOT_FOUND);
        }

        $task = $this->taskModel->getTaskByTaskUidAndBoardId($taskUid, $board['id']);

        if (empty($task)) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::TASK_NOT_FOUND);
        }

        if ($task['body_hid']) {
            $body = $this->highlightModel->getHighlightById($task['body_hid']);
            $task['body'] = $body;
            $tags = $body['tags'];
        }

        $data = [
            'data' => [
                'globalTags' => $this->tagModel->getGlobalTagsWithSelection($tags['raw_tags']),
                'task' => $task,
            ],
        ];

        return $this->response(StatusCode::HTTP_OK, $data);
    }

    public function updateTaskBody(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = (array)$request->getParsedBody();
        $body = $params['body'];
        $tags = $params['tags'];

        $boardUid = $args['boardUID'];
        $taskUid = $args['taskUID'];

        if (empty($body)) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::TASK_BODY_CANNOT_BE_NULL);
        }

        $board = $this->boardModel->getBoardByUid($boardUid);

        if (empty($board)) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::BOARD_NOT_FOUND);
        }

        $task = $this->taskModel->getTaskByTaskUidAndBoardId($taskUid, $board['id']);

        if (empty($task)) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::TASK_NOT_FOUND);
        }

        $highlightParams = [
            'highlight' => $body,
            'book' => null,
            'source' => 'Task Management',
            'type' => 3,
            'tags' => $tags,
        ];

        if ($task['body_hid']) {
            $this->highlightModel->updateOperations($task['body_hid'], $highlightParams);
        } else {
            $highlightId = $this->highlightModel->createOperations($highlightParams);
            $this->taskModel->updateTaskBody($task['id'], $highlightId);
        }

        $data = [
            'message' => lang\En::TASK_BODY_SUCCESSFULLY_UPDATED,
        ];

        return $this->response(StatusCode::HTTP_OK, $data);
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

        $this->activityModel->logCreateNewTask($board['title'], $task['title'], $task['id']);

        $eisenhowerStatusName = EisenhowerStatus::from($task['eisenhower_status'])->name; // get case name from value

        $data = [
            'taskUid' => $task['uid'],
            'eisenhowerStatusColor' => EisenhowerStatusColor::valueFromName($eisenhowerStatusName),
            'eisenhowerStatusName' => EisenhowerStatus::from($task['eisenhower_status'])->capitalizedStatusName(),
            'message' => lang\En::TASK_SUCCESSFULLY_CREATED,
        ];

        return $this->response(StatusCode::HTTP_CREATED, $data);
    }

    public function updateTaskStatus(ServerRequestInterface $request, ResponseInterface $response, $args)
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

        if ($params['to'] !== $params['from']) {
            $this->activityModel->logTaskStatus($board['title'], $task['title'], $task['id'], $params['from'], $params['to']);
        }

        $data = [
            'message' => lang\En::TASK_STATUS_SUCCESSFULLY_UPDATED,
        ];

        return $this->response(StatusCode::HTTP_CREATED, $data);
    }

}
