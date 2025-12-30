# Login Log Feature

This feature tracks all login attempts (successful and failed) to the site.

## Components Added

### 1. Database Table
**File:** `Site/phpCode/database/create_login_log_table.sql`

Creates the `LoginLog` table with the following columns:
- `LogID` - Auto-incrementing primary key
- `UserID` - ID of the user attempting to log in (0 for unknown users)
- `Email` - Email address used in login attempt
- `LoginTime` - Timestamp of the login attempt
- `IPAddress` - IP address of the client
- `UserAgent` - Browser/client information
- `LoginStatus` - 'success' or 'failed'
- `FailReason` - Reason for failed login (if applicable)

### 2. Logging Function
**File:** `Site/phpCode/includeFunctions.php`

Added `logUserLogin()` function that:
- Records login attempts to the database
- Captures IP address and user agent
- Tracks both successful and failed attempts
- Records failure reasons for debugging

### 3. Updated Login Page
**File:** `Site/LoginOrOut/loginPage.php`

Modified to log:
- Successful logins
- Failed logins (wrong password)
- Failed logins (email not found)

### 4. Admin View Page
**File:** `Site/LoginOrOut/viewLoginLog.php`

A comprehensive admin page that displays:
- Statistics (total logins, successful, failed, unique users)
- Filterable login history
- Search by user ID, email, status
- Date range filtering
- Detailed login information including IP addresses

## Installation

### Step 1: Create the Database Table

Run the SQL file to create the table:

```bash
# Using MySQL command line
mysql -u your_username -p your_database_name < Site/phpCode/database/create_login_log_table.sql

# Or using phpMyAdmin
# Import the SQL file through the interface
```

Alternatively, you can run the SQL directly:

```sql
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
```

### Step 2: Test the Logging

1. Try logging in with valid credentials - should log a successful login
2. Try logging in with wrong password - should log a failed login
3. Try logging in with unknown email - should log a failed login

### Step 3: View the Logs

Access the login log viewer at:
```
https://yoursite.com/LoginOrOut/viewLoginLog.php
```

**Note:** Only users with 'Admin' status can access this page.

## Usage

### Viewing Login History

The admin page provides:
- **Statistics Dashboard**: Overview of login activity
- **Filters**: Search by user ID, email, status, date range
- **Detailed Records**: View individual login attempts with timestamps and IP addresses

### Filter Options

- **User ID**: Filter by specific user
- **Email**: Search for logins by email address (partial match)
- **Status**: Show only successful or failed logins
- **Last Days**: How many days of history to show (default: 30)
- **Limit**: Maximum number of records to display (default: 100)

## Security Considerations

1. **Access Control**: The log viewer is restricted to Admin users only
2. **IP Logging**: IP addresses are stored for security auditing
3. **Password Safety**: Failed login attempts do not log actual passwords
4. **Data Retention**: Consider implementing a cleanup policy for old logs

## Optional Enhancements

### Cleanup Old Logs

You might want to periodically clean up old login logs. Add to a scheduled task:

```sql
-- Delete logs older than 90 days
DELETE FROM LoginLog WHERE LoginTime < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Add Email Alerts

Modify `logUserLogin()` to send alerts for multiple failed attempts:

```php
// Check for recent failed attempts
$recentFailures = "SELECT COUNT(*) as count FROM LoginLog 
                   WHERE Email = ? 
                   AND LoginStatus = 'failed' 
                   AND LoginTime > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
```

### Export Functionality

Add CSV export option to the viewer page for reporting.

## Troubleshooting

### Table Creation Fails
- Check that UsersDB table exists (required for foreign key)
- Verify database user has CREATE TABLE permissions

### Logs Not Recording
- Check that `logUserLogin()` function is imported in `includeFunctions.php`
- Verify database connection is working
- Check PHP error logs for detailed error messages

### Cannot Access Viewer Page
- Ensure you're logged in as an Admin user
- Check that `$_SESSION['currentUserLogOnStatus']` equals 'Admin'

## Files Modified/Created

1. **Created**: `Site/phpCode/database/create_login_log_table.sql`
2. **Modified**: `Site/phpCode/includeFunctions.php` - Added `logUserLogin()` function
3. **Modified**: `Site/LoginOrOut/loginPage.php` - Added logging calls
4. **Created**: `Site/LoginOrOut/viewLoginLog.php` - Admin viewer page
5. **Created**: `LOGIN_LOG_README.md` - This documentation
