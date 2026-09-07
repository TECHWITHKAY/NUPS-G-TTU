-- ============================================================
-- NUPS-G Church Website Database Schema (PostgreSQL)
-- ============================================================
-- This file is 100% PostgreSQL compatible. To import on Render or
-- locally run:  psql "$DATABASE_URL" -f database.sql
-- or:           psql -h localhost -U postgres -d nupsg_church -f database.sql
-- ============================================================

-- Users table (members)
CREATE TABLE IF NOT EXISTS users (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role VARCHAR(20) DEFAULT 'member',
    profile_image VARCHAR(255) DEFAULT 'default.png',
    total_points INT DEFAULT 0,
    daily_quiz_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_users_role CHECK (role IN ('member', 'admin'))
);

-- Executives table
CREATE TABLE IF NOT EXISTS executives (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    position VARCHAR(100) NOT NULL,
    bio TEXT,
    image VARCHAR(255) DEFAULT 'default.png',
    category VARCHAR(20) DEFAULT 'executive',
    order_num INT DEFAULT 0,
    email VARCHAR(255),
    phone VARCHAR(20),
    whatsapp VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Events table
CREATE TABLE IF NOT EXISTS events (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    event_date TIMESTAMP NOT NULL,
    location VARCHAR(255),
    image VARCHAR(255) DEFAULT 'event-default.jpg',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_created_by FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
);

-- Blogs table (with media_type for image/video support)
CREATE TABLE IF NOT EXISTS blogs (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(255) DEFAULT 'blog-default.jpg',
    media_type VARCHAR(20) DEFAULT 'image',
    post_type VARCHAR(20) DEFAULT 'article',
    scripture_ref VARCHAR(120),
    preacher VARCHAR(120),
    audio_file VARCHAR(255),
    audio_url TEXT,
    play_count INT DEFAULT 0,
    author_id INT,
    status VARCHAR(20) DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_blogs_media_type CHECK (media_type IN ('image', 'video')),
    CONSTRAINT chk_blogs_post_type CHECK (post_type IN ('article', 'sermon')),
    CONSTRAINT chk_blogs_status CHECK (status IN ('published', 'draft')),
    CONSTRAINT fk_blogs_author FOREIGN KEY (author_id)
        REFERENCES users(id) ON DELETE SET NULL
);

-- Albums (gallery folders, one per programme/event)
CREATE TABLE IF NOT EXISTS albums (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    cover_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Media table (for photos, videos, etc.)
CREATE TABLE IF NOT EXISTS media (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title VARCHAR(200),
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(20) DEFAULT 'image',
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT,
    album_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_media_file_type CHECK (file_type IN ('image', 'video', 'document')),
    CONSTRAINT fk_media_uploaded_by FOREIGN KEY (uploaded_by)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_media_album FOREIGN KEY (album_id)
        REFERENCES albums(id) ON DELETE SET NULL
);

-- Upgrade path for databases created before albums existed
ALTER TABLE media ADD COLUMN IF NOT EXISTS album_id INT;
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_media_album') THEN
        ALTER TABLE media ADD CONSTRAINT fk_media_album
            FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE SET NULL;
    END IF;
END
$$;

-- Member Resources (document downloads library, managed by admins)
CREATE TABLE IF NOT EXISTS resources (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    description TEXT,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size BIGINT DEFAULT 0,
    downloads INT DEFAULT 0,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resources_uploaded_by FOREIGN KEY (uploaded_by)
        REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_resources_category ON resources(category);
CREATE INDEX IF NOT EXISTS idx_resources_created ON resources(created_at);

-- Quiz questions table (with quiz_date for daily quizzes)
CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    question TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_answer CHAR(1) NOT NULL,
    points INT DEFAULT 10,
    quiz_date DATE DEFAULT NULL,
    last_used_on DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Upgrade path: add the last_used_on column to databases created before
-- the question-bank feature existed.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'quiz_questions'
        AND column_name = 'last_used_on'
    ) THEN
        ALTER TABLE quiz_questions ADD COLUMN last_used_on DATE DEFAULT NULL;
    END IF;
END $$;

-- Quiz settings table (for quiz schedule management)
CREATE TABLE IF NOT EXISTS quiz_settings (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    quiz_start TIMESTAMP,
    quiz_duration_hours INT DEFAULT 24,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Quiz results / Leaderboard table.
-- One row per member per quiz day: a member plays the daily quiz ONCE,
-- and ranking is by score, with faster time breaking ties.
CREATE TABLE IF NOT EXISTS quiz_results (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id INT NOT NULL,
    quiz_date DATE NOT NULL DEFAULT CURRENT_DATE,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    time_taken INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_quiz_results_user_day UNIQUE (user_id, quiz_date),
    CONSTRAINT fk_quiz_results_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
);

-- Upgrade path for databases created before the once-per-day rule
ALTER TABLE quiz_results ADD COLUMN IF NOT EXISTS quiz_date DATE;
UPDATE quiz_results SET quiz_date = created_at::date WHERE quiz_date IS NULL;
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name = 'quiz_results' AND column_name = 'quiz_date' AND is_nullable = 'YES') THEN
        ALTER TABLE quiz_results ALTER COLUMN quiz_date SET NOT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_quiz_results_user_day') THEN
        ALTER TABLE quiz_results ADD CONSTRAINT uq_quiz_results_user_day UNIQUE (user_id, quiz_date);
    END IF;
END
$$;

-- Social links table
CREATE TABLE IF NOT EXISTS social_links (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    platform VARCHAR(50) NOT NULL UNIQUE,
    url VARCHAR(255) NOT NULL,
    icon VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Donations table
CREATE TABLE IF NOT EXISTS donations (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    donor_name VARCHAR(200) NOT NULL,
    donor_email VARCHAR(255) NOT NULL,
    donor_phone VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    message TEXT,
    payment_reference VARCHAR(100) UNIQUE,
    payment_status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_donations_status CHECK (payment_status IN ('pending', 'success', 'failed'))
);

-- Monthly quiz champions (one row per finished month)
CREATE TABLE IF NOT EXISTS monthly_winners (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    month CHAR(7) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    total_points INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_monthly_winners_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
);

-- Newsletter subscribers
CREATE TABLE IF NOT EXISTS subscribers (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    token VARCHAR(64) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===========================================
-- TRIGGERS: emulate MySQL ON UPDATE CURRENT_TIMESTAMP
-- ===========================================

-- Generic function that refreshes the updated_at column on every UPDATE
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_blogs_updated_at ON blogs;
CREATE TRIGGER trg_blogs_updated_at
    BEFORE UPDATE ON blogs
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ===========================================
-- INDEXES FOR BETTER PERFORMANCE
-- ===========================================

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_events_event_date ON events(event_date);
CREATE INDEX IF NOT EXISTS idx_blogs_status ON blogs(status);
CREATE INDEX IF NOT EXISTS idx_blogs_author ON blogs(author_id);
CREATE INDEX IF NOT EXISTS idx_quiz_questions_quiz_date ON quiz_questions(quiz_date);
CREATE INDEX IF NOT EXISTS idx_quiz_results_user ON quiz_results(user_id);
CREATE INDEX IF NOT EXISTS idx_donations_payment_ref ON donations(payment_reference);
CREATE INDEX IF NOT EXISTS idx_donations_status ON donations(payment_status);

-- ===========================================
-- SECURITY TABLES
-- ===========================================

-- Login attempts for rate limiting
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(255) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_resets (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_password_resets_token UNIQUE (token),
    CONSTRAINT uq_password_resets_email UNIQUE (email)
);

-- Email verification tokens for email changes
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id INT NOT NULL,
    new_email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT uq_email_verifications_token UNIQUE (token)
);

-- Member reviews (admin-approved before they go public)
CREATE TABLE IF NOT EXISTS reviews (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id INT NOT NULL,
    rating INT NOT NULL DEFAULT 5 CHECK (rating BETWEEN 1 AND 5),
    content TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT uq_reviews_user_content UNIQUE (user_id, content)
);
CREATE INDEX IF NOT EXISTS idx_reviews_status ON reviews(status, created_at);

-- Admin audit log
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_audit_log_admin FOREIGN KEY (admin_id)
        REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_admin_audit_log_admin ON admin_audit_log(admin_id, created_at);
CREATE INDEX IF NOT EXISTS idx_admin_audit_log_action ON admin_audit_log(action, created_at);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address, attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_email ON login_attempts(email, attempted_at);
CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token);
CREATE INDEX IF NOT EXISTS idx_email_verifications_token ON email_verifications(token);

-- ===========================================
-- SAMPLE DATA
-- ===========================================

-- Insert default admin user (development only - change password immediately)
-- ON CONFLICT DO NOTHING replaces MySQL's INSERT IGNORE (email is UNIQUE)
INSERT INTO users (first_name, last_name, email, password, role, total_points)
VALUES ('Admin', 'User', 'sethsefa18@gmail.com', '$2y$10$NEqhDFBkttuXQrqAKvBZF..JSnOhiFwv6Tl1dzFF3t7hf2Ejtdp1G', 'admin', 0)
ON CONFLICT (email) DO NOTHING;

-- Insert default social links (platform is UNIQUE)
INSERT INTO social_links (platform, url, icon) VALUES
('Facebook', 'https://facebook.com/nupsgchurch', 'fab fa-facebook'),
('Tiktok', 'https://www.tiktok.com/@nupsg.ttu', 'fab fa-tiktok'),
('Instagram', 'https://instagram.com/nupsgchurch', 'fab fa-instagram'),
('YouTube', 'https://youtube.com/nupsgchurch', 'fab fa-youtube')
ON CONFLICT (platform) DO NOTHING;
