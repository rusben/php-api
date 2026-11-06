-- ============================================================================
--  php-api · Datos de ejemplo (opcional)
--
--  Uso:   mysql -u root -p api < src/db/seed.sql
--
--  Los DNI son válidos (la API comprueba la letra de control).
--  El usuario 3 no tiene perfil, para ver el caso "relación 1:1 opcional".
-- ============================================================================

INSERT IGNORE INTO `User` (`id`, `name`, `surname`, `email`, `dni`, `phone`, `born`) VALUES
  (1, 'Ana',   'García López',   'ana.garcia@example.com',   '12345678Z', '612345678',     '1990-04-12'),
  (2, 'Luis',  'Martín Pérez',   'luis.martin@example.com',  '23456789D', '+34 698 765 432', '1985-11-30'),
  (3, 'Marta', 'Sánchez Ruiz',   'marta.sanchez@example.com','34567890V', '644556677',     '1998-07-05');

INSERT IGNORE INTO `Profile` (`id`, `user_id`, `address`, `city`, `postal_code`, `country`, `bio`, `website`) VALUES
  (1, 1, 'Calle Mayor 1',        'Madrid',    '28013', 'España', 'Desarrolladora backend y amante del café.', 'https://ana.example.com'),
  (2, 2, 'Avenida del Mar 25',   'Valencia',  '46001', 'España', 'Profesor de programación.',                  NULL);

INSERT IGNORE INTO `Post` (`id`, `user_id`, `title`, `content`) VALUES
  (1, 1, 'Mi primera API en PHP',   'Hoy he montado una API REST con PDO y MySQL sin usar ningún framework.'),
  (2, 1, 'Relaciones 1:1 y 1:N',    'Un usuario tiene un perfil (1:1) y puede escribir muchos posts (1:N).'),
  (3, 2, 'Apuntes de clase',        'Resumen de la sesión sobre HTTP, verbos y códigos de estado.');
