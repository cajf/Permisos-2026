CREATE TABLE IF NOT EXISTS departments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(180) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) COLLATE utf8mb4_0900_ai_ci NOT NULL UNIQUE,
 code VARCHAR(180) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS job_grades (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL, grade VARCHAR(180) NOT NULL,
 UNIQUE KEY uq_job_grade (job_id,grade),
 CONSTRAINT fk_grade_job FOREIGN KEY (job_id) REFERENCES jobs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL, identification VARCHAR(180) NOT NULL UNIQUE,
 username VARCHAR(80) COLLATE utf8mb4_0900_as_ci NOT NULL UNIQUE,
 email VARCHAR(254) NOT NULL, password VARCHAR(255) NOT NULL, job_title VARCHAR(255) NOT NULL,
 code VARCHAR(180) NOT NULL DEFAULT '', grade VARCHAR(180) NOT NULL DEFAULT '',
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
CREATE TABLE IF NOT EXISTS settings (
 `key` VARCHAR(128) NOT NULL PRIMARY KEY, `value` LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS categories (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 family VARCHAR(40) NOT NULL, name VARCHAR(255) NOT NULL UNIQUE, slug VARCHAR(80) NOT NULL UNIQUE,
 supports LONGTEXT NOT NULL, supports_required TINYINT NOT NULL DEFAULT 1,
 max_minutes INT NULL, policy_confirmed TINYINT NOT NULL DEFAULT 0, notes LONGTEXT NOT NULL DEFAULT (''),
 CHECK(supports_required IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS holidays (
 date DATE NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS schedules (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL,
 start_date DATE NOT NULL, end_date DATE NOT NULL, periods LONGTEXT NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
 CONSTRAINT fk_schedules_creator FOREIGN KEY (created_by) REFERENCES users(id),
 CHECK(end_date>=start_date), CHECK(JSON_VALID(periods))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS acts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
 number VARCHAR(120) NOT NULL, issued_on DATE NOT NULL, expires_on DATE NOT NULL, granted_minutes INT NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'pending', reviewed_by BIGINT UNSIGNED NULL, reviewed_at DATETIME NULL,
 comment LONGTEXT NOT NULL DEFAULT (''), created_at DATETIME NOT NULL,
 UNIQUE KEY uq_acts_owner_number(user_id,number),
 CONSTRAINT fk_acts_owner FOREIGN KEY (user_id) REFERENCES users(id),
 CONSTRAINT fk_acts_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id),
 CHECK(granted_minutes>0), CHECK(expires_on>=issued_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'pending', justification LONGTEXT NOT NULL,
 exception_reason LONGTEXT NOT NULL DEFAULT (''), relationship VARCHAR(180) NOT NULL DEFAULT '',
 total_minutes INT NOT NULL, snapshot LONGTEXT NOT NULL, revision INT NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 cancellation_reason LONGTEXT NULL, cancelled_at DATETIME NULL, route_note VARCHAR(255) NOT NULL DEFAULT '',
 department_id BIGINT UNSIGNED NULL, birthday_year INT NULL, cancellation_assignee_id BIGINT UNSIGNED NULL,
 CONSTRAINT fk_requests_owner FOREIGN KEY (user_id) REFERENCES users(id),
 CONSTRAINT fk_requests_category FOREIGN KEY (category_id) REFERENCES categories(id),
 CONSTRAINT fk_requests_department FOREIGN KEY (department_id) REFERENCES departments(id),
 CONSTRAINT fk_requests_cancel_reviewer FOREIGN KEY (cancellation_assignee_id) REFERENCES users(id),
 KEY idx_request_user(user_id,status), CHECK(total_minutes>0), CHECK(JSON_VALID(snapshot))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS request_slots (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL, date DATE NOT NULL,
 start_time VARCHAR(5) NOT NULL, end_time VARCHAR(5) NOT NULL, minutes INT NOT NULL,
 segments LONGTEXT NOT NULL, semester VARCHAR(6) NOT NULL, start_at DATETIME NULL, end_at DATETIME NULL,
 is_full_day TINYINT NOT NULL DEFAULT 0,
 CONSTRAINT fk_slots_request FOREIGN KEY (request_id) REFERENCES requests(id),
 KEY idx_slot_dates(date,semester), CHECK(minutes>0), CHECK(JSON_VALID(segments))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS designations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, role VARCHAR(30) NOT NULL,
 department_id BIGINT UNSIGNED NULL, user_id BIGINT UNSIGNED NOT NULL, alternate_id BIGINT UNSIGNED NULL,
 start_date DATE NOT NULL, end_date DATE NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 CONSTRAINT fk_designations_department FOREIGN KEY (department_id) REFERENCES departments(id),
 CONSTRAINT fk_designations_principal FOREIGN KEY (user_id) REFERENCES users(id),
 CONSTRAINT fk_designations_alternate FOREIGN KEY (alternate_id) REFERENCES users(id),
 CONSTRAINT fk_designations_creator FOREIGN KEY (created_by) REFERENCES users(id),
 UNIQUE KEY idx_designation_start(role,(COALESCE(department_id,0)),start_date),
 CHECK(role IN ('manager','subsecretary')),
 CHECK((role='manager' AND department_id IS NOT NULL) OR (role='subsecretary' AND department_id IS NULL)),
 CHECK(end_date IS NULL OR end_date>=start_date), CHECK(alternate_id IS NULL OR alternate_id!=user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS approvals (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL, revision INT NOT NULL,
 step INT NOT NULL, role VARCHAR(30) NOT NULL, assigned_user_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'waiting', acted_by BIGINT UNSIGNED NULL, acted_at DATETIME NULL,
 comment LONGTEXT NULL, designation_id BIGINT UNSIGNED NULL,
 UNIQUE KEY uq_approval_step(request_id,revision,step), KEY idx_approval_assignee(assigned_user_id,status),
 CONSTRAINT fk_approvals_request FOREIGN KEY (request_id) REFERENCES requests(id),
 CONSTRAINT fk_approvals_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id),
 CONSTRAINT fk_approvals_actor FOREIGN KEY (acted_by) REFERENCES users(id),
 CONSTRAINT fk_approvals_designation FOREIGN KEY (designation_id) REFERENCES designations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS allocations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL, act_id BIGINT UNSIGNED NOT NULL,
 minutes INT NOT NULL, state VARCHAR(30) NOT NULL DEFAULT 'reserved', returned_minutes INT NOT NULL DEFAULT 0,
 UNIQUE KEY uq_allocation_act(request_id,act_id), KEY idx_allocation_act(act_id,state),
 CONSTRAINT fk_allocations_request FOREIGN KEY (request_id) REFERENCES requests(id),
 CONSTRAINT fk_allocations_act FOREIGN KEY (act_id) REFERENCES acts(id),
 CHECK(minutes>0), CHECK(returned_minutes>=0 AND returned_minutes<=minutes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS attachments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
 request_id BIGINT UNSIGNED NULL, act_id BIGINT UNSIGNED NULL,
 original_name VARCHAR(180) NOT NULL, stored_name VARCHAR(80) NOT NULL UNIQUE,
 mime VARCHAR(100) NOT NULL, size BIGINT UNSIGNED NOT NULL, sha256 CHAR(64) NOT NULL, created_at DATETIME NOT NULL,
 CONSTRAINT fk_attachments_owner FOREIGN KEY (user_id) REFERENCES users(id),
 CONSTRAINT fk_attachments_request FOREIGN KEY (request_id) REFERENCES requests(id),
 CONSTRAINT fk_attachments_act FOREIGN KEY (act_id) REFERENCES acts(id),
 CHECK((request_id IS NOT NULL) != (act_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS attachment_contents (
 attachment_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, content_base64 LONGTEXT NOT NULL,
 CONSTRAINT fk_attachment_contents_file FOREIGN KEY (attachment_id) REFERENCES attachments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, actor_id BIGINT UNSIGNED NOT NULL, request_id BIGINT UNSIGNED NULL,
 action VARCHAR(255) NOT NULL, detail LONGTEXT NOT NULL, created_at DATETIME NOT NULL,
 CONSTRAINT fk_events_actor FOREIGN KEY (actor_id) REFERENCES users(id),
 CONSTRAINT fk_events_request FOREIGN KEY (request_id) REFERENCES requests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS outbox (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, recipient VARCHAR(254) NOT NULL, subject VARCHAR(255) NOT NULL,
 body LONGTEXT NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'pending', attempts INT NOT NULL DEFAULT 0,
 last_error LONGTEXT NULL, created_at DATETIME NOT NULL, sent_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS balance_snapshots (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
 department_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, data LONGTEXT NOT NULL,
 CONSTRAINT fk_balances_owner FOREIGN KEY (user_id) REFERENCES users(id),
 CONSTRAINT fk_balances_department FOREIGN KEY (department_id) REFERENCES departments(id),
 KEY idx_balance_cutoff(user_id,created_at,id), CHECK(JSON_VALID(data))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE TABLE IF NOT EXISTS app_locks (
 id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
INSERT INTO app_locks(id) VALUES(1) ON DUPLICATE KEY UPDATE id=id;
