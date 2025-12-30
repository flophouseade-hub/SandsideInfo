-- Create Login Log Table
-- This table tracks when users log into the system

CREATE TABLE IF NOT EXISTS LoginLog (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    Email VARCHAR(255) NOT NULL,
    LoginTime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    IPAddress VARCHAR(45),
    UserAgent TEXT,
    LoginStatus ENUM('success', 'failed') NOT NULL DEFAULT 'success',
    FailReason VARCHAR(255) NULL,
    INDEX idx_userid (UserID),
    INDEX idx_email (Email),
    INDEX idx_logintime (LoginTime),
    FOREIGN KEY (UserID) REFERENCES UsersDB(UsersID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment to table
ALTER TABLE LoginLog COMMENT = 'Logs all user login attempts (successful and failed)';
