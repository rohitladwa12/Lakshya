-- Create Resume Analysis Cache Table
CREATE TABLE IF NOT EXISTS resume_analysis_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    resume_hash VARCHAR(64) NOT NULL, -- SHA256 of the resume text
    analysis_json LONGTEXT NOT NULL,  -- The structured analysis result
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_hash (user_id, resume_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
