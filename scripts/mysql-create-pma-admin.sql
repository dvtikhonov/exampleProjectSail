-- Шаблон: пользователь phpMyAdmin с ограниченными правами на прикладные БД.
-- Не выполнять напрямую — подставляется пароль через scripts/vps-phpmyadmin-mysql.sh
--
-- Плейсхолдер __PMA_MYSQL_PASSWORD__ заменяется на значение PMA_MYSQL_PASSWORD из .env VPS.

-- Пользователи: localhost (ручной вход на хосте) и Docker bridge (phpMyAdmin → host.docker.internal).
CREATE USER IF NOT EXISTS 'pma_admin'@'localhost' IDENTIFIED BY '__PMA_MYSQL_PASSWORD__';
CREATE USER IF NOT EXISTS 'pma_admin'@'172.%.%' IDENTIFIED BY '__PMA_MYSQL_PASSWORD__';

ALTER USER 'pma_admin'@'localhost' IDENTIFIED BY '__PMA_MYSQL_PASSWORD__';
ALTER USER 'pma_admin'@'172.%.%' IDENTIFIED BY '__PMA_MYSQL_PASSWORD__';

-- sail_db: main-app, service-a, service-b, service-c
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER,
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW,
      CREATE ROUTINE, ALTER ROUTINE, TRIGGER, EVENT
  ON `sail_db`.* TO 'pma_admin'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER,
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW,
      CREATE ROUTINE, ALTER ROUTINE, TRIGGER, EVENT
  ON `sail_db`.* TO 'pma_admin'@'172.%.%';

-- service_d_db: service-d
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER,
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW,
      CREATE ROUTINE, ALTER ROUTINE, TRIGGER, EVENT
  ON `service_d_db`.* TO 'pma_admin'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER,
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW,
      CREATE ROUTINE, ALTER ROUTINE, TRIGGER, EVENT
  ON `service_d_db`.* TO 'pma_admin'@'172.%.%';

FLUSH PRIVILEGES;
