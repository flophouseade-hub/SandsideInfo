<?php
$thisPageID = 104; 
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Check if user is logged in and has admin rights
if (!isset($_SESSION['currentUserID']) || $_SESSION['currentUserLogOnStatus'] !== 'fullAdmin') {
    header("Location: ../Pages/accessDeniedPage.php");
    exit();
}

// Get filter parameters
$filterUserID = $_GET['userID'] ?? null;
$filterEmail = $_GET['email'] ?? null;
$filterStatus = $_GET['status'] ?? null;
$filterDays = $_GET['days'] ?? 30; // Default to last 30 days
$limit = $_GET['limit'] ?? 100; // Default to 100 records

// Print page header
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
insertPageTitleAndClass("Login Activity Log", "blockMenuPageTitle", 0);

// Add CSS for the table
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');
print('<style>
.login-log-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.login-log-table th {
    background-color: #2c3e50;
    color: white;
    padding: 12px;
    text-align: left;
    font-weight: bold;
}
.login-log-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #ddd;
}
.login-log-table tr:hover {
    background-color: #f5f5f5;
}
.status-success {
    color: #27ae60;
    font-weight: bold;
}
.status-failed {
    color: #e74c3c;
    font-weight: bold;
}
.filter-form {
    background: #f8f9fa;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
}
.filter-form label {
    margin-right: 10px;
    font-weight: bold;
}
.filter-form input, .filter-form select {
    margin-right: 15px;
    padding: 5px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.filter-form button {
    padding: 8px 20px;
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
.filter-form button:hover {
    background-color: #2980b9;
}
.stats-box {
    background: #ecf0f1;
    padding: 15px;
    margin: 20px 0;
    border-radius: 8px;
    display: flex;
    justify-content: space-around;
}
.stat-item {
    text-align: center;
}
.stat-value {
    font-size: 2em;
    font-weight: bold;
    color: #2c3e50;
}
.stat-label {
    color: #7f8c8d;
    margin-top: 5px;
}
</style>');

// Display filter form
print('<div class="filter-form" style="width: 90%; margin: 0 auto 20px auto;">
    <form method="GET" action="">
        <label>User ID:</label>
        <input type="number" name="userID" value="' . htmlspecialchars($filterUserID ?? '', ENT_QUOTES) . '" placeholder="User ID">
        
        <label>Email:</label>
        <input type="text" name="email" value="' . htmlspecialchars($filterEmail ?? '', ENT_QUOTES) . '" placeholder="email@example.com">
        
        <label>Status:</label>
        <select name="status">
            <option value="">All</option>
            <option value="success"' . ($filterStatus === 'success' ? ' selected' : '') . '>Success</option>
            <option value="failed"' . ($filterStatus === 'failed' ? ' selected' : '') . '>Failed</option>
        </select>
        
        <label>Last Days:</label>
        <input type="number" name="days" value="' . htmlspecialchars($filterDays, ENT_QUOTES) . '" min="1" max="365" style="width: 80px;">
        
        <label>Limit:</label>
        <input type="number" name="limit" value="' . htmlspecialchars($limit, ENT_QUOTES) . '" min="10" max="1000" step="10" style="width: 80px;">
        
        <button type="submit">Apply Filters</button>
        <button type="button" onclick="window.location.href=\'viewLoginLog.php\'">Clear</button>
    </form>
</div>');

// Connect to database
$connection = connectToDatabase();

// Build the WHERE clause based on filters
$whereConditions = [];
$params = [];
$paramTypes = '';

if ($filterUserID) {
    $whereConditions[] = "LoginLog.UserID = ?";
    $params[] = $filterUserID;
    $paramTypes .= 'i';
}

if ($filterEmail) {
    $whereConditions[] = "LoginLog.Email LIKE ?";
    $params[] = '%' . $filterEmail . '%';
    $paramTypes .= 's';
}

if ($filterStatus) {
    $whereConditions[] = "LoginLog.LoginStatus = ?";
    $params[] = $filterStatus;
    $paramTypes .= 's';
}

// Add date filter
$whereConditions[] = "LoginLog.LoginTime >= DATE_SUB(NOW(), INTERVAL ? DAY)";
$params[] = $filterDays;
$paramTypes .= 'i';

$whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total_logins,
    SUM(CASE WHEN LoginStatus = 'success' THEN 1 ELSE 0 END) as successful_logins,
    SUM(CASE WHEN LoginStatus = 'failed' THEN 1 ELSE 0 END) as failed_logins,
    COUNT(DISTINCT UserID) as unique_users
FROM LoginLog $whereClause";

$stmtStats = $connection->prepare($statsQuery);
if ($paramTypes) {
    $stmtStats->bind_param($paramTypes, ...$params);
}
$stmtStats->execute();
$statsResult = $stmtStats->get_result();
$stats = $statsResult->fetch_assoc();
$stmtStats->close();

// Display statistics
print('<div class="stats-box" style="width: 90%; margin: 0 auto 20px auto;">
    <div class="stat-item">
        <div class="stat-value">' . $stats['total_logins'] . '</div>
        <div class="stat-label">Total Logins</div>
    </div>
    <div class="stat-item">
        <div class="stat-value" style="color: #27ae60;">' . $stats['successful_logins'] . '</div>
        <div class="stat-label">Successful</div>
    </div>
    <div class="stat-item">
        <div class="stat-value" style="color: #e74c3c;">' . $stats['failed_logins'] . '</div>
        <div class="stat-label">Failed</div>
    </div>
    <div class="stat-item">
        <div class="stat-value">' . $stats['unique_users'] . '</div>
        <div class="stat-label">Unique Users</div>
    </div>
</div>');

// Fetch login log data with user information
$query = "SELECT 
    LoginLog.LogID,
    LoginLog.UserID,
    CONCAT(UsersDB.FirstName, ' ', UsersDB.LastName) as UserName,
    LoginLog.Email,
    LoginLog.LoginTime,
    LoginLog.IPAddress,
    LoginLog.LoginStatus,
    LoginLog.FailReason
FROM LoginLog
LEFT JOIN UsersDB ON LoginLog.UserID = UsersDB.UsersID
$whereClause
ORDER BY LoginLog.LoginTime DESC
LIMIT ?";

$params[] = $limit;
$paramTypes .= 'i';

$stmt = $connection->prepare($query);
if ($paramTypes) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Display the table
print('<div class="section1" style="width: 90%; margin: 0 auto 40px auto;">');
print('<h3>Recent Login Activity (showing ' . $result->num_rows . ' records)</h3>');

if ($result->num_rows > 0) {
    print('<table class="login-log-table">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>User</th>
                <th>Email</th>
                <th>Status</th>
                <th>Reason</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>');
    
    while ($row = $result->fetch_assoc()) {
        $statusClass = $row['LoginStatus'] === 'success' ? 'status-success' : 'status-failed';
        $userName = $row['UserName'] ?: 'Unknown User';
        $failReason = $row['FailReason'] ? htmlspecialchars($row['FailReason']) : '-';
        
        print('<tr>
            <td>' . date('Y-m-d H:i:s', strtotime($row['LoginTime'])) . '</td>
            <td>' . htmlspecialchars($userName) . ' (ID: ' . $row['UserID'] . ')</td>
            <td>' . htmlspecialchars($row['Email']) . '</td>
            <td class="' . $statusClass . '">' . ucfirst($row['LoginStatus']) . '</td>
            <td>' . $failReason . '</td>
            <td>' . htmlspecialchars($row['IPAddress'] ?: 'N/A') . '</td>
        </tr>');
    }
    
    print('</tbody></table>');
} else {
    print('<p>No login records found for the selected criteria.</p>');
}

print('</div>');

$stmt->close();
$connection->close();

insertPageFooter($thisPageID);
?>
