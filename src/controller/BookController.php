<?php

namespace App\controller;

use App\model\BookModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class BookController extends Controller
{
    private $bookModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookModel = new BookModel($container);
    }

    public function paths(ServerRequestInterface $request, ResponseInterface $response)
    {
        $averageData = $this->bookModel->readingAverage();
        $paths = $this->bookModel->getBookPaths();

        $data = [
            "bookPaths" => $paths,
            'readingAverage' => round($averageData['average'], 3),
            'readingTotal' => $averageData['total'],
            'dayDiff' => $averageData['diff'],
            'activeBookPaths' => 'active'
        ];

        return $this->view->render($response, 'paths.mustache', $data);
    }

    public function allBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $authors = $this->bookModel->getAuthorsKeyValue();
        $subject = $this->bookModel->getSubjectsKeyValue();
        $books = $this->bookModel->getAllBooks();

        $data = [
            'subjects' => $subject,
            'authors' => $authors,
            'books' => $books,
            'activeAllBooks' => 'active'
        ];

        return $this->view->render($response, 'all-books.mustache', $data);
    }
}