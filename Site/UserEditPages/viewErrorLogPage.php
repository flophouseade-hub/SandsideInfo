<?php
// View error log - admin only page to monitor site errors

$thisPageID = 103; 
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

if (accessLevelCheck($pageAccess) == false) {
  header("Location: ../Pages/accessDeniedPage.php");
  exit();
}

// Get sort and filter parameters
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'time';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';
$filterType = isset($_GET['filterType']) ? $_GET['filterType'] : '';
$filterUser = isset($_GET['filterUser']) ? (int)$_GET['filterUser'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

// Connect to the database
$connection = getDatabaseConnection();
if (!$connection) {
  die("ERROR: Could not connect to the database: " . mysqli_connect_error());
}

// Get filter options
$errorTypes = mysqli_query($connection, "SELECT DISTINCT ErrorType FROM ErrorLog WHERE ErrorType IS NOT NULL AND ErrorType != '' ORDER BY ErrorType");
$errorUsers = mysqli_query($connection, "SELECT DISTINCT e.UserID, u.FirstName, u.LastName 
                                          FROM ErrorLog e 
                                          LEFT JOIN UsersDB u ON e.UserID = u.UsersID 
                                          WHERE e.UserID IS NOT NULL 
                                          ORDER BY u.LastName, u.FirstName");

$errorTypeOptions = array();
while ($row = mysqli_fetch_assoc($errorTypes)) {
  $errorTypeOptions[] = $row['ErrorType'];
}

$errorUserOptions = array();
while ($row = mysqli_fetch_assoc($errorUsers)) {
  $errorUserOptions[] = $row;
}

// Build query with JOINs
$errorsQuery = "SELECT e.*, u.FirstName, u.LastName 
                FROM ErrorLog e 
                LEFT JOIN UsersDB u ON e.UserID = u.UsersID 
                WHERE 1=1";

// Add filter conditions
if (!empty($filterType)) {
  $errorsQuery .= " AND e.ErrorType = '" . mysqli_real_escape_string($connection, $filterType) . "'";
}
if ($filterUser > 0) {
  $errorsQuery .= " AND e.UserID = " . $filterUser;
}

// Add ORDER BY based on sort parameter
switch ($sortBy) {
  case 'time':
    $errorsQuery .= " ORDER BY e.ErrorTime " . ($sortOrder === 'ASC' ? 'ASC' : 'DESC');
    break;
  case 'type':
    $errorsQuery .= " ORDER BY e.ErrorType " . ($sortOrder === 'ASC' ? 'ASC' : 'DESC') . ", e.ErrorTime DESC";
    break;
  case 'file':
    $errorsQuery .= " ORDER BY e.FileName " . ($sortOrder === 'ASC' ? 'ASC' : 'DESC') . ", e.ErrorTime DESC";
    break;
  case 'user':
    $errorsQuery .= " ORDER BY u.LastName " . ($sortOrder === 'ASC' ? 'ASC' : 'DESC') . ", u.FirstName, e.ErrorTime DESC";
    break;
  default:
    $errorsQuery .= " ORDER BY e.ErrorTime DESC";
}

// Add limit
$errorsQuery .= " LIMIT " . $limit;

$errorsResult = $connection->query($errorsQuery);
if (!$errorsResult) {
  die("ERROR: Could not execute query: " . $connection->error);
}
$errors = $errorsResult->fetch_all(MYSQLI_ASSOC);

// Get total error count
$countQuery = "SELECT COUNT(*) as total FROM ErrorLog WHERE 1=1";
if (!empty($filterType)) {
  $countQuery .= " AND ErrorType = '" . mysqli_real_escape_string($connection, $filterType) . "'";
}
if ($filterUser > 0) {
  $countQuery .= " AND UserID = " . $filterUser;
}
$countResult = $connection->query($countQuery);
$totalErrors = $countResult->fetch_assoc()['total'];

$connection->close();

print('<link rel="stylesheet" href="../styleSheets/listAllTableStyles.css">');
print('<style>
  .errorMessage {
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
    font-family: monospace;
    font-size: 12px;
  }
  .errorMessage:hover {
    white-space: normal;
    overflow: visible;
  }
  .errorStats {
    background: #f8f9fa;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    border-left: 4px solid #cc0000;
  }
  .errorStats strong {
    color: #cc0000;
  }
  .limitSelector {
    margin-left: 10px;
    padding: 4px 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
  }
</style>');

// Print out the page
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Show error statistics
print("<div class='errorStats' style='margin: auto;  width: 80%;margin-top: 20px; margin-bottom: 20px;'>");
print("<strong>Total Errors:</strong> $totalErrors | ");
print("<strong>Showing:</strong> " . min($limit, count($errors)) . " most recent");
print(" | <strong>Limit:</strong> ");
print("<select class='limitSelector' onchange=\"location.href='?limit=' + this.value\">");
foreach ([50, 100, 200, 500] as $limitOption) {
  $selected = ($limit === $limitOption) ? 'selected' : '';
  print("<option value='$limitOption' $selected>$limitOption</option>");
}
print("</select>");
print("</div>");

// Build filter parameters for sort URLs
$filterParams = '';
if (!empty($filterType)) $filterParams .= '&filterType=' . urlencode($filterType);
if ($filterUser > 0) $filterParams .= '&filterUser=' . $filterUser;
if ($limit !== 100) $filterParams .= '&limit=' . $limit;

// Toggle sort order function
$toggleOrder = ($sortOrder === 'ASC') ? 'DESC' : 'ASC';

print("<div class=\"listAllTable\"><table> <thead>
  <tr>
    <th><a href='?sortBy=time&sortOrder={$toggleOrder}{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Time</a></th>
    <th><a href='?sortBy=type&sortOrder={$toggleOrder}{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Type</a></th>
    <th style='text-align: left;'>Error Message</th>
    <th><a href='?sortBy=file&sortOrder={$toggleOrder}{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>File</a></th>
    <th>Line</th>
    <th><a href='?sortBy=user&sortOrder={$toggleOrder}{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>User</a></th>
  </tr>
  <tr>
    <td></td>
    <td>
      <select name='filterType' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
        <option value=''>-- All --</option>");
        foreach ($errorTypeOptions as $type) {
          $selected = ($type === $filterType) ? 'selected' : '';
          $typeEsc = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
          print("<option value='$typeEsc' $selected>$typeEsc</option>");
        }
        print("</select>
    </td>
    <td></td>
    <td></td>
    <td></td>
    <td>
      <select name='filterUser' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
        <option value='0'>-- All --</option>");
        foreach ($errorUserOptions as $user) {
          $selected = ($user['UserID'] == $filterUser) ? 'selected' : '';
          $userName = htmlspecialchars($user['LastName'] . ', ' . $user['FirstName'], ENT_QUOTES, 'UTF-8');
          print("<option value='{$user['UserID']}' $selected>$userName</option>");
        }
        print("</select>
    </td>
  </tr>
  <tr>
    <td colspan='6' style='text-align: left; padding-left: 9px;'>");
      if (!empty($filterType) || $filterUser > 0) {
        $sortParam = ($sortBy !== 'time' || $sortOrder !== 'DESC') ? '?sortBy=' . $sortBy . '&sortOrder=' . $sortOrder : '?';
        $limitParam = ($limit !== 100) ? '&limit=' . $limit : '';
        print("<button type='button' onclick=\"location.href='viewErrorLogPage.php{$sortParam}{$limitParam}'\" style='padding: 4px 12px; background-color: #666; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: 500;'>Clear Filters</button>");
      }
      print("</td>
  </tr>
</thead>");

print("<form id='filterForm' method='GET' action='viewErrorLogPage.php' style='display: none;'>");
if ($sortBy !== 'time') {
  print("<input type='hidden' name='sortBy' value='$sortBy'>");
}
if ($sortOrder !== 'DESC') {
  print("<input type='hidden' name='sortOrder' value='$sortOrder'>");
}
if ($limit !== 100) {
  print("<input type='hidden' name='limit' value='$limit'>");
}
print("</form>");

print("<tbody>");
if (count($errors) === 0) {
  print("<tr><td colspan='6' style='text-align: center; padding: 20px; color: #666;'>No errors found</td></tr>");
} else {
  foreach ($errors as $error) {
    print("<tr>");
    
    // Time column
    $errorTime = htmlspecialchars($error['ErrorTime'], ENT_QUOTES, 'UTF-8');
    print("<td>$errorTime</td>");
    
    // Type column with color coding
    $errorType = htmlspecialchars($error['ErrorType'], ENT_QUOTES, 'UTF-8');
    $typeColor = '#666';
    if ($errorType === 'ERROR' || $errorType === 'DATABASE') $typeColor = '#cc0000';
    elseif ($errorType === 'WARNING') $typeColor = '#ff8800';
    print("<td style='color: $typeColor; font-weight: bold;'>$errorType</td>");
    
    // Error message column with hover expansion
    $errorMsg = htmlspecialchars($error['ErrorMessage'], ENT_QUOTES, 'UTF-8');
    print("<td style='text-align: left;'><div class='errorMessage' title='Click to expand'>$errorMsg</div></td>");
    
    // File and line columns
    $fileName = htmlspecialchars($error['FileName'] ? basename($error['FileName']) : '-', ENT_QUOTES, 'UTF-8');
    $lineNumber = $error['LineNumber'] ? $error['LineNumber'] : '-';
    print("<td>$fileName</td>");
    print("<td>$lineNumber</td>");
    
    // User column
    $userName = '-';
    if ($error['FirstName'] && $error['LastName']) {
      $userName = htmlspecialchars($error['FirstName'] . ' ' . $error['LastName'], ENT_QUOTES, 'UTF-8');
    }
    print("<td>$userName</td>");
    
    print("</tr>");
  }
}
print("</tbody></table></div>");

insertPageFooter($thisPageID);
?>
