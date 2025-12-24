-- Query Examples for LoginLog Table

-- View all recent logins (last 24 hours)
SELECT 
    ll.LogID,
    ll.UserID,
    CONCAT(u.FirstName, ' ', u.LastName) as UserName,
    ll.Email,
    ll.LoginTime,
    ll.LoginStatus,
    ll.FailReason,
    ll.IPAddress
FROM LoginLog ll
LEFT JOIN UsersDB u ON ll.UserID = u.UsersID
WHERE ll.LoginTime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY ll.LoginTime DESC;

-- Count logins by status (today)
SELECT 
    LoginStatus,
    COUNT(*) as Count
FROM LoginLog
WHERE DATE(LoginTime) = CURDATE()
GROUP BY LoginStatus;

-- Find users with multiple failed login attempts (last hour)
SELECT 
    Email,
    COUNT(*) as FailedAttempts,
    MAX(LoginTime) as LastAttempt
FROM LoginLog
WHERE LoginStatus = 'failed'
  AND LoginTime >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY Email
HAVING COUNT(*) >= 3
ORDER BY FailedAttempts DESC;

-- View login activity by hour (today)
SELECT 
    HOUR(LoginTime) as Hour,
    COUNT(*) as TotalLogins,
    SUM(CASE WHEN LoginStatus = 'success' THEN 1 ELSE 0 END) as Successful,
    SUM(CASE WHEN LoginStatus = 'failed' THEN 1 ELSE 0 END) as Failed
FROM LoginLog
WHERE DATE(LoginTime) = CURDATE()
GROUP BY HOUR(LoginTime)
ORDER BY Hour;

-- Most active users (last 7 days)
SELECT 
    ll.UserID,
    CONCAT(u.FirstName, ' ', u.LastName) as UserName,
    ll.Email,
    COUNT(*) as LoginCount,
    MAX(ll.LoginTime) as LastLogin
FROM LoginLog ll
LEFT JOIN UsersDB u ON ll.UserID = u.UsersID
WHERE ll.LoginStatus = 'success'
  AND ll.LoginTime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY ll.UserID, ll.Email
ORDER BY LoginCount DESC
LIMIT 10;

-- View all failed login reasons
SELECT 
    FailReason,
    COUNT(*) as Count,
    MAX(LoginTime) as LastOccurrence
FROM LoginLog
WHERE LoginStatus = 'failed'
  AND FailReason IS NOT NULL
GROUP BY FailReason
ORDER BY Count DESC;

-- Cleanup old logs (older than 90 days) - USE WITH CAUTION
-- DELETE FROM LoginLog WHERE LoginTime < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- View table structure
DESCRIBE LoginLog;

-- Check indexes
SHOW INDEX FROM LoginLog;
