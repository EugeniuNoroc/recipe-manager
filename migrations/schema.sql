-- Recipe Manager — Database Schema
-- Engine: InnoDB, charset: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────
-- users
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    username   VARCHAR(50)      NOT NULL UNIQUE,
    email      VARCHAR(255)     NOT NULL UNIQUE,
    password   VARCHAR(255)     NOT NULL,
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- categories
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name VARCHAR(100)  NOT NULL UNIQUE,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- tags
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tags (
    id   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name VARCHAR(100)  NOT NULL UNIQUE,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- recipes
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS recipes (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED     DEFAULT NULL,
    title        VARCHAR(255)     NOT NULL,
    author       VARCHAR(100)     NOT NULL,
    prep_time    SMALLINT UNSIGNED NOT NULL COMMENT 'minutes',
    category_id  INT UNSIGNED     NOT NULL,
    difficulty   ENUM('Легко','Средне','Сложно') NOT NULL DEFAULT 'Средне',
    ingredients  TEXT             NOT NULL,
    instructions TEXT             NOT NULL,
    created_at   DATE             NOT NULL,
    updated_at   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_category (category_id),
    INDEX idx_difficulty (difficulty),
    INDEX idx_created (created_at),
    CONSTRAINT fk_recipe_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- recipe_tags (many-to-many)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS recipe_tags (
    recipe_id INT UNSIGNED NOT NULL,
    tag_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (recipe_id, tag_id),
    CONSTRAINT fk_rt_recipe
        FOREIGN KEY (recipe_id) REFERENCES recipes (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_rt_tag
        FOREIGN KEY (tag_id) REFERENCES tags (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
