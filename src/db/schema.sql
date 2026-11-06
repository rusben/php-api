-- ============================================================================
--  php-api · Esquema de base de datos (MySQL 5.7+ / MariaDB 10.3+)
--
--  Uso:   mysql -u root -p < src/db/schema.sql
--
--  Es idempotente (CREATE ... IF NOT EXISTS): si ya tenías la tabla `User` de la
--  versión anterior, puedes ejecutarlo igualmente; solo creará `Profile` y `Post`.
--
--  Relaciones:
--    User 1 ──── 0..1 Profile   (Profile.user_id es UNIQUE -> relación 1:1)
--    User 1 ──── 0..N Post      (Post.user_id no es único  -> relación 1:N)
--  Al borrar un usuario se borran en cascada su perfil y sus posts.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `api`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `api`;

-- ----------------------------------------------------------------------------
--  User
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `User` (
  `id`      INT          NOT NULL AUTO_INCREMENT,
  `name`    VARCHAR(100) NOT NULL,
  `surname` VARCHAR(150) NOT NULL,
  `email`   VARCHAR(320) NOT NULL,
  `dni`     VARCHAR(9)   NOT NULL,
  `phone`   VARCHAR(20)  NOT NULL,
  `born`    DATE         NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_email` (`email`),
  UNIQUE KEY `uq_user_dni` (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  Profile  (1:1 con User)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Profile` (
  `id`          INT           NOT NULL AUTO_INCREMENT,
  `user_id`     INT           NOT NULL,
  `address`     VARCHAR(255)  NULL,
  `city`        VARCHAR(100)  NULL,
  `postal_code` VARCHAR(10)   NULL,
  `country`     VARCHAR(100)  NULL,
  `bio`         VARCHAR(1000) NULL,
  `website`     VARCHAR(255)  NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_profile_user` (`user_id`),
  CONSTRAINT `fk_profile_user`
    FOREIGN KEY (`user_id`) REFERENCES `User` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  Post  (N:1 con User, es decir, 1:N desde User)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Post` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `user_id`    INT          NOT NULL,
  `title`      VARCHAR(150) NOT NULL,
  `content`    TEXT         NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_user` (`user_id`),
  CONSTRAINT `fk_post_user`
    FOREIGN KEY (`user_id`) REFERENCES `User` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
