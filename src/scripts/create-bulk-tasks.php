<?php

// Static
require __DIR__ . '/app.php';

/** @var \Psr\Container\ContainerInterface $container */

// Dynamic
use App\enum\LogTypes;
use App\model\BoardModel;
use App\model\TaskModel;
use App\model\ActivityModel;
use App\util\CLI;

$taskModel = new TaskModel($container);
$activityModel = new ActivityModel($container);
$boardModel = new BoardModel($container);

$boardUID = null; // Replace with your actual board UID
$eisenhowerStatus = null;
$bodyHid = null;
$_SESSION['userInfos']['user_id'] = null; // Replace with the actual user ID

$tasks = [

];

// Script Logic
$board = $boardModel->getBoardByUid($boardUID);

foreach ($tasks as $taskTitle) {
    $task = $taskModel->createTask($taskTitle, $board['id'], $bodyHid, $eisenhowerStatus);
    $activityModel->logCreateNewTask($board['title'], $task['title'], $task['id']);
    CLI::printLog('Task created: ' . $task['title'], LogTypes::INFO);
}