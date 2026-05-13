-- PostgreSQL Schema for Learning Platform

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    avatar TEXT,
    bio TEXT,
    is_banned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_points INT DEFAULT 0
);

-- Levels table
CREATE TABLE IF NOT EXISTS levels (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    language VARCHAR(20) NOT NULL CHECK (language IN ('javascript', 'php')),
    difficulty VARCHAR(20) NOT NULL CHECK (difficulty IN ('beginner', 'intermediate', 'advanced', 'senior')),
    order_index INT NOT NULL,
    type VARCHAR(50) NOT NULL CHECK (type IN ('code_challenge', 'page_recreation')),
    description TEXT NOT NULL,
    expected_output TEXT,
    image_path TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(difficulty, order_index, language)
);

-- Questions table (hints and detailed prompts)
CREATE TABLE IF NOT EXISTS questions (
    id SERIAL PRIMARY KEY,
    level_id INT NOT NULL REFERENCES levels(id) ON DELETE CASCADE,
    prompt TEXT NOT NULL,
    hint TEXT,
    expected_output TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Progress table
CREATE TABLE IF NOT EXISTS user_progress (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    level_id INT NOT NULL REFERENCES levels(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'in_progress' CHECK (status IN ('in_progress', 'completed', 'abandoned')),
    tries INT DEFAULT 0,
    time_spent_seconds INT DEFAULT 0,
    points_earned INT DEFAULT 0,
    hint_used BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, level_id)
);

-- Achievements table
CREATE TABLE IF NOT EXISTS achievements (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    icon TEXT,
    condition_key VARCHAR(100) NOT NULL,
    condition_value VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Achievements table
CREATE TABLE IF NOT EXISTS user_achievements (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    achievement_id INT NOT NULL REFERENCES achievements(id) ON DELETE CASCADE,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, achievement_id)
);

-- Submission logs table (for tracking submissions and debugging)
CREATE TABLE IF NOT EXISTS submissions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    level_id INT NOT NULL REFERENCES levels(id) ON DELETE CASCADE,
    code_submitted TEXT NOT NULL,
    output_produced TEXT,
    expected_output TEXT,
    time_spent_seconds INT,
    tries INT,
    passed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Leaderboard View (excludes admins and banned users)
CREATE OR REPLACE VIEW leaderboard AS
SELECT 
    u.id,
    u.username,
    u.avatar,
    u.total_points,
    COUNT(DISTINCT CASE WHEN up.status = 'completed' THEN up.level_id END) as levels_completed,
    RANK() OVER (ORDER BY u.total_points DESC) as rank
FROM users u
LEFT JOIN user_progress up ON u.id = up.user_id
WHERE u.is_banned = FALSE AND u.role = 'user'
GROUP BY u.id, u.username, u.avatar, u.total_points
ORDER BY u.total_points DESC;

-- Add review_status to submissions for page_recreation manual grading
ALTER TABLE submissions ADD COLUMN IF NOT EXISTS review_status VARCHAR(20) DEFAULT 'auto' CHECK (review_status IN ('auto', 'pending', 'reviewed'));

CREATE INDEX IF NOT EXISTS idx_user_progress_user_id ON user_progress(user_id);
CREATE INDEX IF NOT EXISTS idx_user_progress_level_id ON user_progress(level_id);
CREATE INDEX IF NOT EXISTS idx_user_progress_status ON user_progress(status);
CREATE INDEX IF NOT EXISTS idx_levels_difficulty_language ON levels(difficulty, language);
CREATE INDEX IF NOT EXISTS idx_submissions_user_id ON submissions(user_id);
CREATE INDEX IF NOT EXISTS idx_submissions_created_at ON submissions(created_at);
CREATE INDEX IF NOT EXISTS idx_user_achievements_user_id ON user_achievements(user_id);
CREATE INDEX IF NOT EXISTS idx_questions_level_id ON questions(level_id);
