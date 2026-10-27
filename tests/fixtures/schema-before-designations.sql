PRAGMA foreign_keys = ON;
CREATE TABLE IF NOT EXISTS departments (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY, name TEXT NOT NULL, identification TEXT NOT NULL UNIQUE,
 username TEXT NOT NULL UNIQUE COLLATE NOCASE, email TEXT NOT NULL, password TEXT NOT NULL,
 job_title TEXT NOT NULL, code TEXT NOT NULL DEFAULT '', grade TEXT NOT NULL DEFAULT '',
 appointment_type TEXT NOT NULL DEFAULT 'Carrera administrativa',
 department_id INTEGER NOT NULL REFERENCES departments(id), manager_id INTEGER REFERENCES users(id),
 deputy_manager_id INTEGER REFERENCES users(id), high_rank TEXT NOT NULL DEFAULT '',
 roles TEXT NOT NULL DEFAULT '[]', active INTEGER NOT NULL DEFAULT 1,
 force_password INTEGER NOT NULL DEFAULT 1, failures INTEGER NOT NULL DEFAULT 0, locked_until TEXT
);
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS categories (
 id INTEGER PRIMARY KEY, family TEXT NOT NULL, name TEXT NOT NULL UNIQUE, slug TEXT NOT NULL UNIQUE,
 supports TEXT NOT NULL, supports_required INTEGER NOT NULL DEFAULT 1 CHECK(supports_required IN (0,1)),
 max_minutes INTEGER, policy_confirmed INTEGER NOT NULL DEFAULT 0,
 notes TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS holidays (date TEXT PRIMARY KEY, name TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS schedules (
 id INTEGER PRIMARY KEY, name TEXT NOT NULL, start_date TEXT NOT NULL, end_date TEXT NOT NULL,
 periods TEXT NOT NULL, created_by INTEGER NOT NULL REFERENCES users(id), CHECK(end_date>=start_date)
);
CREATE TABLE IF NOT EXISTS acts (
 id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id), number TEXT NOT NULL,
 issued_on TEXT NOT NULL, expires_on TEXT NOT NULL, granted_minutes INTEGER NOT NULL CHECK(granted_minutes>0),
 status TEXT NOT NULL DEFAULT 'pending', reviewed_by INTEGER REFERENCES users(id), reviewed_at TEXT,
 comment TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, UNIQUE(user_id,number), CHECK(expires_on>=issued_on)
);
CREATE TABLE IF NOT EXISTS requests (
 id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id), category_id INTEGER NOT NULL REFERENCES categories(id),
 status TEXT NOT NULL DEFAULT 'pending', justification TEXT NOT NULL, exception_reason TEXT NOT NULL DEFAULT '',
 relationship TEXT NOT NULL DEFAULT '', total_minutes INTEGER NOT NULL CHECK(total_minutes>0),
 snapshot TEXT NOT NULL, revision INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
 cancellation_reason TEXT, cancelled_at TEXT, route_note TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS request_slots (
 id INTEGER PRIMARY KEY, request_id INTEGER NOT NULL REFERENCES requests(id), date TEXT NOT NULL,
 start_time TEXT NOT NULL, end_time TEXT NOT NULL, minutes INTEGER NOT NULL CHECK(minutes>0), segments TEXT NOT NULL,
 semester TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS approvals (
 id INTEGER PRIMARY KEY, request_id INTEGER NOT NULL REFERENCES requests(id), revision INTEGER NOT NULL,
 step INTEGER NOT NULL, role TEXT NOT NULL, assigned_user_id INTEGER NOT NULL REFERENCES users(id),
 status TEXT NOT NULL DEFAULT 'waiting', acted_by INTEGER REFERENCES users(id), acted_at TEXT, comment TEXT,
 UNIQUE(request_id,revision,step)
);
CREATE TABLE IF NOT EXISTS allocations (
 id INTEGER PRIMARY KEY, request_id INTEGER NOT NULL REFERENCES requests(id), act_id INTEGER NOT NULL REFERENCES acts(id),
 minutes INTEGER NOT NULL CHECK(minutes>0), state TEXT NOT NULL DEFAULT 'reserved', returned_minutes INTEGER NOT NULL DEFAULT 0,
 UNIQUE(request_id,act_id)
);
CREATE TABLE IF NOT EXISTS attachments (
 id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id), request_id INTEGER REFERENCES requests(id),
 act_id INTEGER REFERENCES acts(id), original_name TEXT NOT NULL, stored_name TEXT NOT NULL UNIQUE,
 mime TEXT NOT NULL, size INTEGER NOT NULL, sha256 TEXT NOT NULL, created_at TEXT NOT NULL,
 CHECK ((request_id IS NOT NULL) != (act_id IS NOT NULL))
);
CREATE TABLE IF NOT EXISTS events (
 id INTEGER PRIMARY KEY, actor_id INTEGER NOT NULL REFERENCES users(id), request_id INTEGER REFERENCES requests(id),
 action TEXT NOT NULL, detail TEXT NOT NULL, created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS outbox (
 id INTEGER PRIMARY KEY, recipient TEXT NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0, last_error TEXT,
 created_at TEXT NOT NULL, sent_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_request_user ON requests(user_id,status);
CREATE INDEX IF NOT EXISTS idx_approval_assignee ON approvals(assigned_user_id,status);
CREATE INDEX IF NOT EXISTS idx_slot_dates ON request_slots(date,semester);
CREATE INDEX IF NOT EXISTS idx_allocation_act ON allocations(act_id,state);
