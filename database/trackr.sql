CREATE TABLE `author`
(
    `id`     int                                                           NOT NULL AUTO_INCREMENT,
    `author` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `NAME_UNIQUE` (`author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `boards`
(
    `id`          int                                                           NOT NULL AUTO_INCREMENT,
    `uid`         varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
    `title`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `status`      int                                     DEFAULT '0',
    `created_at`  varchar(20) COLLATE utf8mb4_unicode_ci                        NOT NULL,
    `updated_at`  varchar(20) COLLATE utf8mb4_unicode_ci                        NOT NULL,
    `user_id`     int                                                           NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `book_activity_logs`
(
    `id`        int                                                           NOT NULL AUTO_INCREMENT,
    `path_id`   int DEFAULT NULL,
    `book_id`   int DEFAULT NULL,
    `activity`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `timestamp` int                                                           NOT NULL,
    `user_id`   int DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `book_authors`
(
    `id`        int NOT NULL AUTO_INCREMENT,
    `author_id` int NOT NULL,
    `book_id`   int NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_author_id_book_id` (`author_id`,`book_id`),
    KEY         `idx_book_id` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `book_trackings`
(
    `id`          int                                                          NOT NULL AUTO_INCREMENT,
    `book_id`     int                                                          NOT NULL,
    `path_id`     varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `record_date` int                                                          NOT NULL,
    `amount`      int                                                          NOT NULL,
    `user_id`     int                                                          NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bookmarks`
(
    `id`          int                                                            NOT NULL AUTO_INCREMENT,
    `uid`         varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci   NOT NULL,
    `bookmark`    varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `site_name`   varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `title`       varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `note`        varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `site_type`   varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `thumbnail`   varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `keyword`     varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `status`      int                                                            DEFAULT '0',
    `created`     int                                                            NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bookmarks_ownership`
(
    `id`              int NOT NULL AUTO_INCREMENT,
    `bookmark_id`     int NOT NULL,
    `user_id`         int NOT NULL,
    `site_name`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `title`           varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `description`     mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `note`            varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `site_type`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `thumbnail`       varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `status`          int NOT NULL                                                   DEFAULT '0',
    `created`         int NOT NULL,
    `updated_at`      int                                                            DEFAULT NULL,
    `started`         int                                                            DEFAULT NULL,
    `done`            int                                                            DEFAULT NULL,
    `deleted_at`      int                                                            DEFAULT NULL,
    `is_deleted`      int                                                            DEFAULT '0',
    `is_title_edited` int                                                            DEFAULT '0',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `books`
(
    `id`               int                                                           NOT NULL AUTO_INCREMENT,
    `uid`              varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
    `title`            varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `subtitle`         varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `publisher`        varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `pdf`              int                                                           DEFAULT '0',
    `epub`             int                                                           DEFAULT '0',
    `added_date`       int                                                           DEFAULT NULL,
    `page_count`       int                                                           DEFAULT '0',
    `published_date`   varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `description`      mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `isbn`             varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `thumbnail`        varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `thumbnail_small`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `info_link`        varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `ebook_version`    int                                                           DEFAULT '0',
    `ebook_page_count` int                                                           DEFAULT '0',
    `is_complete_book` int                                                           DEFAULT '1',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `books_finished`
(
    `id`               int NOT NULL AUTO_INCREMENT,
    `book_id`          int NOT NULL,
    `path_id`          int NOT NULL,
    `start_date`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `finish_date`      varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `rate`             int                                                           DEFAULT NULL,
    `user_id`          int NOT NULL,
    `is_complete_book` int                                                           DEFAULT '1',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `books_ownership`
(
    `id`         int NOT NULL AUTO_INCREMENT,
    `book_id`    int NOT NULL,
    `user_id`    int NOT NULL,
    `note`       mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `created_at` int NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_idx_bookid_user_id` (`book_id`,`user_id`),
    KEY          `idx_book_id` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories`
(
    `id`            int                                                           NOT NULL AUTO_INCREMENT,
    `name`          varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `defaultStatus` int                                                           NOT NULL DEFAULT '0',
    `created`       int                                                           NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `NAME_UNIQUE` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chain_links`
(
    `id`         int                                                          NOT NULL AUTO_INCREMENT,
    `chain_id`   int                                                          NOT NULL,
    `value`      varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT '0',
    `link_date`  varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `note`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` int                                                          NOT NULL,
    `updated_at` int                                                          NOT NULL,
    `user_id`    int                                                          NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_idx_chainid_linkdate` (`chain_id`,`link_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chains`
(
    `id`           int                                                          NOT NULL AUTO_INCREMENT,
    `uid`          varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `name`         varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci         DEFAULT NULL,
    `type`         tinyint(1) DEFAULT '1',
    `constant`     tinyint                                                               DEFAULT '0',
    `created_at`   int                                                          NOT NULL,
    `finished_at`  int                                                                   DEFAULT NULL,
    `user_id`      int                                                                   DEFAULT NULL,
    `status`       tinyint(1) DEFAULT '0',
    `show_in_logs` tinyint                                                      NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uid_UNIQUE` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `daily_reading_amounts`
(
    `id`      int   NOT NULL AUTO_INCREMENT,
    `amount`  float NOT NULL,
    `date`    int   NOT NULL,
    `path_id` int   NOT NULL,
    `user_id` int   NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `date_trackings`
(
    `id`      int                                                           NOT NULL AUTO_INCREMENT,
    `uid`     varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
    `name`    varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `date`    varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
    `created` int                                                           NOT NULL,
    `user_id` int DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `favorites`
(
    `id`         int NOT NULL AUTO_INCREMENT,
    `type`       int NOT NULL,
    `source_id`  int NOT NULL,
    `user_id`    int NOT NULL,
    `created_at` int NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_idx_type_sourceid` (`type`,`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `highlight_versions`
(
    `id`            int NOT NULL AUTO_INCREMENT,
    `highlight_id`  int NOT NULL,
    `old_highlight` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at`    int NOT NULL,
    `user_id`       int NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `highlights`
(
    `id`           int NOT NULL AUTO_INCREMENT,
    `title`        varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `highlight`    longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `author`       varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `source`       varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `page`         int                                                            DEFAULT NULL,
    `location`     varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci   DEFAULT NULL,
    `link`         int                                                            DEFAULT NULL,
    `book_id`      int                                                            DEFAULT NULL,
    `blog_path`    varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `type`         int                                                            DEFAULT '0',
    `is_secret`    int                                                            DEFAULT '1',
    `is_encrypted` int                                                            DEFAULT '0',
    `created`      int NOT NULL,
    `updated`      int                                                            DEFAULT NULL,
    `is_deleted`   int                                                            DEFAULT '0',
    `deleted_at`   int                                                            DEFAULT NULL,
    `read_count`   int NOT NULL                                                   DEFAULT '0',
    `user_id`      int NOT NULL,
    PRIMARY KEY (`id`),
    FULLTEXT KEY `ft_idx_highlight` (`highlight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `images`
(
    `id`         int                                                          NOT NULL AUTO_INCREMENT,
    `sha1`       varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `filename`   varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` int                                                          NOT NULL,
    `user_id`    int                                                          NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `logs`
(
    `id`      int                                                          NOT NULL AUTO_INCREMENT,
    `date`    varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `log_hid` int DEFAULT NULL,
    `user_id` int                                                          NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `path_books`
(
    `id`      int NOT NULL AUTO_INCREMENT,
    `path_id` int NOT NULL,
    `book_id` int NOT NULL,
    `status`  int NOT NULL,
    `created` int NOT NULL,
    `updated` int DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_path_id_book_id` (`path_id`,`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `paths`
(
    `id`      int                                                          NOT NULL AUTO_INCREMENT,
    `uid`     varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `name`    varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `start`   int                                                          NOT NULL,
    `finish`  int                                                          NOT NULL,
    `status`  int DEFAULT '0',
    `user_id` int                                                          NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publishers`
(
    `id`         int NOT NULL AUTO_INCREMENT,
    `name`       varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` int                                                           DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sub_highlights`
(
    `id`               int NOT NULL AUTO_INCREMENT,
    `highlight_id`     int NOT NULL,
    `sub_highlight_id` int NOT NULL,
    `created`          int NOT NULL,
    `updated`          int DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE `tag_relationships`
(
    `id`         int NOT NULL AUTO_INCREMENT,
    `source_id`  int NOT NULL,
    `tag_id`     int NOT NULL,
    `type`       int DEFAULT NULL,
    `created`    int NOT NULL,
    `is_deleted` int DEFAULT '0',
    `deleted_at` int DEFAULT NULL,
    `user_id`    int DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY          `idx_source_id` (`source_id`),
    KEY          `idx_tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tags`
(
    `id`      int                                                           NOT NULL AUTO_INCREMENT,
    `tag`     varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created` int                                                           NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tasks`
(
    `id`                int                                                          NOT NULL AUTO_INCREMENT,
    `uid`               varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `title`             varchar(255) COLLATE utf8mb4_unicode_ci                               DEFAULT NULL,
    `board_id`          int                                                          NOT NULL,
    `description_hid`   int                                                                   DEFAULT NULL,
    `eisenhower_status` int                                                          NOT NULL DEFAULT '0',
    `status`            int                                                                   DEFAULT '0',
    `created_at`        varchar(20) COLLATE utf8mb4_unicode_ci                       NOT NULL,
    `updated_at`        varchar(20) COLLATE utf8mb4_unicode_ci                       NOT NULL,
    `user_id`           int                                                          NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users`
(
    `id`             int                                                           NOT NULL AUTO_INCREMENT,
    `username`       varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
    `password`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created`        int                                                           NOT NULL,
    `encryption_key` tinyblob                                                      NOT NULL,
    `api_token`      varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username_UNIQUE` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
