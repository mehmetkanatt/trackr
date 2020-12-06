<?php

namespace App\model;

use Psr\Container\ContainerInterface;
use App\exception\CustomException;

class TodoModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function todoList()
    {
        $books = $this->getBooks();
        $bookmarks = $this->getBookmarks();
        $videos = $this->getVideos();

        return array_merge($books, $bookmarks, $videos);

    }

    public function getTodos()
    {
        $list = [];

        $sql = "SELECT * FROM todos ORDER BY description DESC, id DESC";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['todoType'] = '<span class="badge badge-warning">todo</span>';
            $row['description'] = str_replace("\n", '<br>', $row['description']);
            $row['todoName'] = $row['todo'];

            if ($row['status'] == 0 || $row['status'] == "") {
                $row['status'] = '<span class="badge badge-secondary">to do</span>';
            } elseif ($row['status'] == 1) {
                $row['status'] = '<span class="badge badge-warning">in progress</span>';
            } elseif ($row['status'] == 2) {
                $row['status'] = '<span class="badge badge-success">done</span>';
            } else {
                $row['status'] = '<span class="badge badge-dark">list out</span>';
            }

            if (!$row['started']) {
                $row['startAction'] = true;
            } elseif (!$row['done']) {
                $row['doneAction'] = true;
            } else {
                $row['complete'] = true;
            }

            if (!$row['description']) {
                unset($row['description']);
            }

            $list[] = $row;
        }

        return $list;
    }

    public function getTodo($todoId)
    {
        $todo = [];

        $sql = "SELECT id, todo, description, created, started, done, status 
                FROM todos
                WHERE id = :id";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $todoId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $todo = $row;
        }

        return $todo;
    }

    public function getBooks()
    {
        $list = [];

        $sql = "SELECT b.id AS typeTableId,
                CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ') 
                        FROM book_authors ba 
                        INNER JOIN author a ON ba.author_id = a.id
                        WHERE ba.book_id = b.id), ' - ', b.title) AS todoName, pb.status as status
                FROM books b 
                LEFT JOIN books_finished bf ON b.id = bf.book_id
                LEFT JOIN path_books pb ON b.ID = pb.book_id
                GROUP BY b.id
                ORDER BY pb.status ASC";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['todoType'] = '<span class="badge badge-info">book</span>';

            if ($row['status'] == 0 || $row['status'] == "") {
                $row['status'] = '<span class="badge badge-secondary">to do</span>';
            } elseif ($row['status'] == 1) {
                $row['status'] = '<span class="badge badge-warning">in progress</span>';
            } elseif ($row['status'] == 2) {
                $row['status'] = '<span class="badge badge-success">done</span>';
            } else {
                $row['status'] = '<span class="badge badge-dark">list out</span>';
            }

            $list[] = $row;
        }

        return $list;
    }

    public function getBookmarks()
    {
        $list = [];

        $sql = 'SELECT b.id AS typeTableId, b.bookmark, b.title, b.note, b.status, b.categoryId, c.name AS categoryName
                FROM bookmarks b
                INNER JOIN categories c ON b.categoryID = c.id
                ORDER BY b.id DESC';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $text = $row['title'];

            if (strlen($row['title']) > 150) {
                $text = substr($row['title'], 0, 150);
                $text .= '...';
            }

            if ($row['title'] == "" | $row['title'] == null) {
                $text = $row['bookmark'];
            }

            $href = '<a href="' . $row['bookmark'] . '" target="_blank">' . $text . '</a>';
            $row['todoName'] = $href;

            $row['todoName'] .= "<span class=\"badge badge-dark float-right\">{$row['categoryName']}</span>";
            $row['todoType'] = '<span class="badge badge-primary">bookmark</span>';

            if ($row['status'] == 0) {
                $row['status'] = '<span class="badge badge-secondary">to do</span>';
            } elseif ($row['status'] == 1) {
                $row['status'] = '<span class="badge badge-warning">in progress</span>';
            } else {
                $row['status'] = '<span class="badge badge-success">done</span>';
                $row['rowStatusClass'] = 'table-success';
            }

            $list[] = $row;
        }

        return $list;
    }

    public function getVideos()
    {
        $list = [];

        $sql = "SELECT v.id AS typeTableId,
                       CONCAT(v.title,' (',v.length, ') ', ' <span class=\"badge badge-dark float-right\">', c.NAME, '</span>') AS todoName,
                       v.status
                FROM videos v INNER JOIN categories c ON v.category_id = c.id";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            if ($row['status'] == 0) {
                $row['status'] = '<span class="badge badge-secondary">to do</span>';
            } elseif ($row['status'] == 1) {
                $row['status'] = '<span class="badge badge-warning">in progress</span>';
            } elseif ($row['status'] == 2) {
                $row['status'] = '<span class="badge badge-success">done</span>';
            }

            $row['todoType'] = '<span class="badge badge-primary">video</span>';
            $list[] = $row;
        }

        return $list;

    }

    public function create($todo, $description)
    {
        $date = time();

        $sql = 'INSERT INTO todos (todo, description, created)
                VALUES(:todo, :description, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':todo', $todo, \PDO::PARAM_STR);
        $stm->bindParam(':description', $description, \PDO::PARAM_STR);
        $stm->bindParam(':created', $date, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateStartedDate($id)
    {
        $now = time();
        $status = 1;

        $sql = 'UPDATE todos 
                SET status = :status, started = :started 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':started', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateTodo($id, $todo)
    {
        $sql = 'UPDATE todos 
                SET todo = :title, description = :description
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':title', $todo['title'], \PDO::PARAM_STR);
        $stm->bindParam(':description', $todo['description'], \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateDoneDate($id)
    {
        $now = time();
        $status = 2;

        $sql = 'UPDATE todos 
                SET status = :status, done = :done 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':done', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }
}