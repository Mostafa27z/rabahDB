-- Database initialization script
-- This runs only on first container creation (empty volume)

CREATE DATABASE IF NOT EXISTS `tamleekdb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant privileges to the application user
GRANT ALL PRIVILEGES ON `tamleekdb`.* TO 'tamleek_user'@'%';
FLUSH PRIVILEGES;
