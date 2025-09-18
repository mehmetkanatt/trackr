<?php

namespace App\enum;

enum ActivityLogSources: string
{
    case HIGHLIGHTS = 'highlights';
    case BOOKMARKS = 'bookmarks';
    case BOOKS = 'books';
    case BOOK_TRACKINGS = 'book_trackings';
    case BOOKS_FINISHED = 'books_finished';
    case BOOKS_OWNERSHIP = 'books_ownership';
    case TASKS = 'tasks';
    case PATHS = 'paths';
    case PATH_BOOKS = 'path_books';
    case AUTHOR = 'author';
}