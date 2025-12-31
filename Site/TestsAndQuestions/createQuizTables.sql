-- Quiz and Assessment System Database Tables
-- Created: December 28, 2025
-- Run this SQL script to create all necessary tables for the questionnaire/assessment system

-- =====================================================
-- 1. QuestionsDB - Store all quiz questions
-- =====================================================
CREATE TABLE IF NOT EXISTS QuestionsDB (
    QuestionID INT AUTO_INCREMENT PRIMARY KEY,
    QuestionText TEXT NOT NULL,
    QuestionType ENUM('multiple-choice', 'true-false', 'short-answer') NOT NULL DEFAULT 'multiple-choice',
    QuestionGroup VARCHAR(100) DEFAULT NULL COMMENT 'Optional category/topic grouping',
    QuestionPoints INT DEFAULT 1 COMMENT 'Point value for this question',
    QuestionExplanation TEXT DEFAULT NULL COMMENT 'Explanation shown after answering',
    QuestionMadeBy VARCHAR(255) NOT NULL,
    QuestionMadeTime DATETIME NOT NULL,
    QuestionModifiedTime DATETIME DEFAULT NULL,
    QuestionActive TINYINT(1) DEFAULT 1 COMMENT '1=active, 0=archived',
    INDEX idx_group (QuestionGroup),
    INDEX idx_type (QuestionType),
    INDEX idx_active (QuestionActive)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. QuestionOptionsDB - Store answer options for multiple choice questions
-- =====================================================
CREATE TABLE IF NOT EXISTS QuestionOptionsDB (
    OptionID INT AUTO_INCREMENT PRIMARY KEY,
    QuestionID INT NOT NULL,
    OptionText TEXT NOT NULL,
    IsCorrect TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=correct answer, 0=incorrect',
    OptionOrder INT DEFAULT 1 COMMENT 'Display order of options',
    FOREIGN KEY (QuestionID) REFERENCES QuestionsDB(QuestionID) ON DELETE CASCADE,
    INDEX idx_question (QuestionID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. quizzes_tb - Store quiz/assessment definitions
-- =====================================================
CREATE TABLE IF NOT EXISTS quizzes_tb (
    QuizID INT AUTO_INCREMENT PRIMARY KEY,
    QuizName VARCHAR(255) NOT NULL,
    QuizDescription TEXT DEFAULT NULL,
    CourseID INT DEFAULT NULL COMMENT 'Links to CoursesDB - NULL means standalone quiz',
    PassingScore DECIMAL(5,2) NOT NULL DEFAULT 70.00 COMMENT 'Percentage needed to pass (0-100)',
    TimeLimit INT DEFAULT NULL COMMENT 'Time limit in minutes - NULL means no limit',
    AllowRetakes TINYINT(1) DEFAULT 1 COMMENT '1=allow retakes, 0=one attempt only',
    MaxAttempts INT DEFAULT NULL COMMENT 'Max number of attempts - NULL means unlimited',
    ShowCorrectAnswers TINYINT(1) DEFAULT 1 COMMENT '1=show answers after completion, 0=hide',
    RandomizeQuestions TINYINT(1) DEFAULT 0 COMMENT '1=randomize question order',
    RandomizeOptions TINYINT(1) DEFAULT 0 COMMENT '1=randomize option order',
    QuizActive TINYINT(1) DEFAULT 1 COMMENT '1=active, 0=inactive',
    QuizMadeBy VARCHAR(255) NOT NULL,
    QuizMadeTime DATETIME NOT NULL,
    QuizModifiedTime DATETIME DEFAULT NULL,
    INDEX idx_course (CourseID),
    INDEX idx_active (QuizActive)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. QuizQuestionsDB - Links questions to specific quizzes
-- =====================================================
CREATE TABLE IF NOT EXISTS QuizQuestionsDB (
    QuizQuestionID INT AUTO_INCREMENT PRIMARY KEY,
    QuizID INT NOT NULL,
    QuestionID INT NOT NULL,
    QuestionOrder INT DEFAULT 1 COMMENT 'Display order in the quiz',
    FOREIGN KEY (QuizID) REFERENCES quizzes_tb(QuizID) ON DELETE CASCADE,
    FOREIGN KEY (QuestionID) REFERENCES QuestionsDB(QuestionID) ON DELETE CASCADE,
    UNIQUE KEY unique_quiz_question (QuizID, QuestionID),
    INDEX idx_quiz (QuizID),
    INDEX idx_order (QuizID, QuestionOrder)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. quiz_attempts_tb - Track each user's quiz attempts
-- =====================================================
CREATE TABLE IF NOT EXISTS quiz_attempts_tb (
    AttemptID INT AUTO_INCREMENT PRIMARY KEY,
    QuizID INT NOT NULL,
    UserEmail VARCHAR(255) NOT NULL COMMENT 'User taking the quiz',
    AttemptStartTime DATETIME NOT NULL,
    AttemptEndTime DATETIME DEFAULT NULL,
    TimeSpent INT DEFAULT NULL COMMENT 'Time spent in seconds',
    Score DECIMAL(5,2) DEFAULT NULL COMMENT 'Final score as percentage (0-100)',
    PointsEarned INT DEFAULT 0,
    PointsTotal INT DEFAULT 0,
    Passed TINYINT(1) DEFAULT 0 COMMENT '1=passed, 0=failed',
    AttemptStatus ENUM('in-progress', 'completed', 'abandoned') DEFAULT 'in-progress',
    AttemptNumber INT DEFAULT 1 COMMENT 'Which attempt number this is for this user',
    FOREIGN KEY (QuizID) REFERENCES quizzes_tb(QuizID) ON DELETE CASCADE,
    INDEX idx_quiz_user (QuizID, UserEmail),
    INDEX idx_user (UserEmail),
    INDEX idx_status (AttemptStatus),
    INDEX idx_completed (AttemptEndTime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. QuizAnswersDB - Store individual answers for each attempt
-- =====================================================
CREATE TABLE IF NOT EXISTS QuizAnswersDB (
    AnswerID INT AUTO_INCREMENT PRIMARY KEY,
    AttemptID INT NOT NULL,
    QuestionID INT NOT NULL,
    UserAnswer TEXT DEFAULT NULL COMMENT 'User selected answer(s) or text response',
    IsCorrect TINYINT(1) DEFAULT 0 COMMENT '1=correct, 0=incorrect',
    PointsAwarded INT DEFAULT 0,
    AnswerTime DATETIME DEFAULT NULL COMMENT 'When this question was answered',
    FOREIGN KEY (AttemptID) REFERENCES quiz_attempts_tb(AttemptID) ON DELETE CASCADE,
    FOREIGN KEY (QuestionID) REFERENCES QuestionsDB(QuestionID) ON DELETE CASCADE,
    UNIQUE KEY unique_attempt_question (AttemptID, QuestionID),
    INDEX idx_attempt (AttemptID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. CertificatesDB - Track issued certificates
-- =====================================================
CREATE TABLE IF NOT EXISTS CertificatesDB (
    CertificateID INT AUTO_INCREMENT PRIMARY KEY,
    CertificateCode VARCHAR(50) UNIQUE NOT NULL COMMENT 'Unique verification code',
    CourseID INT NOT NULL COMMENT 'Course this certificate is for',
    UserEmail VARCHAR(255) NOT NULL,
    QuizAttemptID INT NOT NULL COMMENT 'The passing quiz attempt',
    IssueDate DATETIME NOT NULL,
    ExpiryDate DATE DEFAULT NULL COMMENT 'Certificate expiry - NULL means no expiry',
    CertificateStatus ENUM('active', 'revoked', 'expired') DEFAULT 'active',
    IssuedBy VARCHAR(255) DEFAULT NULL,
    CertificatePDFPath VARCHAR(500) DEFAULT NULL COMMENT 'Path to generated PDF certificate',
    FOREIGN KEY (QuizAttemptID) REFERENCES quiz_attempts_tb(AttemptID) ON DELETE RESTRICT,
    INDEX idx_user (UserEmail),
    INDEX idx_course (CourseID),
    INDEX idx_code (CertificateCode),
    INDEX idx_status (CertificateStatus)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. QuizFeedbackDB - Optional: Store feedback/reviews from users
-- =====================================================
CREATE TABLE IF NOT EXISTS QuizFeedbackDB (
    FeedbackID INT AUTO_INCREMENT PRIMARY KEY,
    QuizID INT NOT NULL,
    UserEmail VARCHAR(255) NOT NULL,
    Rating INT DEFAULT NULL COMMENT 'Rating 1-5',
    FeedbackText TEXT DEFAULT NULL,
    FeedbackTime DATETIME NOT NULL,
    FOREIGN KEY (QuizID) REFERENCES quizzes_tb(QuizID) ON DELETE CASCADE,
    INDEX idx_quiz (QuizID),
    INDEX idx_user (UserEmail)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Sample Data (Optional - for testing)
-- =====================================================

-- Sample Question 1: Multiple Choice
INSERT INTO QuestionsDB (QuestionText, QuestionType, QuestionGroup, QuestionPoints, QuestionExplanation, QuestionMadeBy, QuestionMadeTime) 
VALUES (
    'What is the capital of France?',
    'multiple-choice',
    'Geography',
    1,
    'Paris is the capital and largest city of France.',
    'admin@example.com',
    NOW()
);

SET @q1_id = LAST_INSERT_ID();

INSERT INTO QuestionOptionsDB (QuestionID, OptionText, IsCorrect, OptionOrder) VALUES
(@q1_id, 'London', 0, 1),
(@q1_id, 'Berlin', 0, 2),
(@q1_id, 'Paris', 1, 3),
(@q1_id, 'Madrid', 0, 4);

-- Sample Question 2: True/False
INSERT INTO QuestionsDB (QuestionText, QuestionType, QuestionGroup, QuestionPoints, QuestionExplanation, QuestionMadeBy, QuestionMadeTime) 
VALUES (
    'The Earth is flat.',
    'true-false',
    'Science',
    1,
    'The Earth is an oblate spheroid, not flat.',
    'admin@example.com',
    NOW()
);

SET @q2_id = LAST_INSERT_ID();

INSERT INTO QuestionOptionsDB (QuestionID, OptionText, IsCorrect, OptionOrder) VALUES
(@q2_id, 'True', 0, 1),
(@q2_id, 'False', 1, 2);

-- Sample Quiz
INSERT INTO quizzes_tb (QuizName, QuizDescription, PassingScore, TimeLimit, AllowRetakes, ShowCorrectAnswers, QuizMadeBy, QuizMadeTime)
VALUES (
    'Sample General Knowledge Quiz',
    'A basic quiz to test general knowledge.',
    70.00,
    30,
    1,
    1,
    'admin@example.com',
    NOW()
);

SET @quiz_id = LAST_INSERT_ID();

-- Link questions to quiz
INSERT INTO QuizQuestionsDB (QuizID, QuestionID, QuestionOrder) VALUES
(@quiz_id, @q1_id, 1),
(@quiz_id, @q2_id, 2);

-- =====================================================
-- Views for easier data access
-- =====================================================

-- View: Quiz with question count
CREATE OR REPLACE VIEW QuizSummaryView AS
SELECT 
    q.QuizID,
    q.QuizName,
    q.QuizDescription,
    q.CourseID,
    q.PassingScore,
    q.TimeLimit,
    q.AllowRetakes,
    q.QuizActive,
    COUNT(qq.QuestionID) as TotalQuestions,
    SUM(qst.QuestionPoints) as TotalPoints
FROM quizzes_tb q
LEFT JOIN QuizQuestionsDB qq ON q.QuizID = qq.QuizID
LEFT JOIN QuestionsDB qst ON qq.QuestionID = qst.QuestionID
GROUP BY q.QuizID;

-- View: User quiz attempts summary
CREATE OR REPLACE VIEW UserQuizAttemptsView AS
SELECT 
    qa.AttemptID,
    qa.QuizID,
    q.QuizName,
    qa.UserEmail,
    qa.AttemptStartTime,
    qa.AttemptEndTime,
    qa.Score,
    qa.Passed,
    qa.AttemptNumber,
    qa.AttemptStatus,
    CONCAT(qa.PointsEarned, '/', qa.PointsTotal) as PointsSummary
FROM quiz_attempts_tb qa
JOIN quizzes_tb q ON qa.QuizID = q.QuizID
ORDER BY qa.AttemptStartTime DESC;

-- =====================================================
-- End of SQL script
-- =====================================================
