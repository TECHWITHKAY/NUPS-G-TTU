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
    author_id INT,
    status VARCHAR(20) DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_blogs_media_type CHECK (media_type IN ('image', 'video')),
    CONSTRAINT chk_blogs_status CHECK (status IN ('published', 'draft')),
    CONSTRAINT fk_blogs_author FOREIGN KEY (author_id)
        REFERENCES users(id) ON DELETE SET NULL
);

-- Media table (for photos, videos, etc.)
CREATE TABLE IF NOT EXISTS media (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title VARCHAR(200),
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(20) DEFAULT 'image',
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_media_file_type CHECK (file_type IN ('image', 'video', 'document')),
    CONSTRAINT fk_media_uploaded_by FOREIGN KEY (uploaded_by)
        REFERENCES users(id) ON DELETE SET NULL
);

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Quiz settings table (for quiz schedule management)
CREATE TABLE IF NOT EXISTS quiz_settings (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    quiz_start TIMESTAMP,
    quiz_duration_hours INT DEFAULT 24,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Quiz results / Leaderboard table
CREATE TABLE IF NOT EXISTS quiz_results (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id INT NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    time_taken INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quiz_results_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
);

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
-- SAMPLE DATA
-- ===========================================

-- Insert default admin user (password: admin123)
-- ON CONFLICT DO NOTHING replaces MySQL's INSERT IGNORE (email is UNIQUE)
INSERT INTO users (first_name, last_name, email, password, role, total_points)
VALUES ('Admin', 'User', 'admin@nupsgchurch.com', '$2y$10$NEqhDFBkttuXQrqAKvBZF..JSnOhiFwv6Tl1dzFF3t7hf2Ejtdp1G', 'admin', 0)
ON CONFLICT (email) DO NOTHING;

-- Insert default social links (platform is UNIQUE)
INSERT INTO social_links (platform, url, icon) VALUES
('Facebook', 'https://facebook.com/nupsgchurch', 'fab fa-facebook'),
('Tiktok', 'https://tiktok.com/nupsgchurch', 'fab fa-tiktok'),
('Instagram', 'https://instagram.com/nupsgchurch', 'fab fa-instagram'),
('YouTube', 'https://youtube.com/nupsgchurch', 'fab fa-youtube')
ON CONFLICT (platform) DO NOTHING;
