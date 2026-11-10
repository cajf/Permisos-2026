CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL, identification VARCHAR(180) NOT NULL UNIQUE,
 username VARCHAR(80) COLLATE utf8mb4_0900_as_ci NOT NULL UNIQUE,
 email VARCHAR(254) NOT NULL, password VARCHAR(255) NOT NULL, job_title VARCHAR(255) NOT NULL,
 code VARCHAR(180) NOT NULL DEFAULT '', grade VARCHAR(180) NOT NULL DEFAULT '',
 job_category VARCHAR(180) NOT NULL DEFAULT '',
 appointment_type VARCHAR(100) NOT NULL DEFAULT 'Carrera administrativa',
 department_id BIGINT UNSIGNED NOT NULL, manager_id BIGINT UNSIGNED NULL, deputy_manager_id BIGINT UNSIGNED NULL,
 high_rank VARCHAR(80) NOT NULL DEFAULT '', birth_date DATE NULL,
 roles LONGTEXT NOT NULL DEFAULT ('[]'), active TINYINT NOT NULL DEFAULT 1,
 force_password TINYINT NOT NULL DEFAULT 1, failures INT NOT NULL DEFAULT 0, locked_until DATETIME NULL,
 CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id),
 CONSTRAINT fk_users_manager FOREIGN KEY (manager_id) REFERENCES users(id),
 CONSTRAINT fk_users_deputy FOREIGN KEY (deputy_manager_id) REFERENCES users(id),
 CHECK(JSON_VALID(roles)), CHECK(active IN (0,1)), CHECK(force_password IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
