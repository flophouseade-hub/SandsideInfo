<?php
//list all the users with links to edit them in the editPageDetailsPage.php page

// Start a seesion if one is not already started
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}
$thisPageID = 28;
include('../phpCode/includeFunctions.php');
//die("Here");
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
// No need for a databse call for the page data as the data is in the session array
include('../phpCode/pagesAndImagesArrays.php');

// Get the page details for this page from the array:
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'];
$pageDescription = $_SESSION['pagesOnSite'][$thisPageID]['PageDescription'];
$pageImageRef = $_SESSION['pagesOnSite'][$thisPageID]['PageImageIDRef'];
$pageType = $_SESSION['pagesOnSite'][$thisPageID]['PageType'];
$pageContentRefs = $_SESSION['pagesOnSite'][$thisPageID]['PageContentRefs'];
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'];
$pageColour = $_SESSION['pagesOnSite'][$thisPageID]['PageColour'];
$pageLocalMenu = $_SESSION['pagesOnSite'][$thisPageID]['PageLocalMenu'];

if (accessLevelCheck($pageAccess) == false) {
  die("Access denied");
}

// Get sort and filter parameters
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'lastName';
$filterLogOnStatus = isset($_GET['filterLogOnStatus']) ? $_GET['filterLogOnStatus'] : '';
$filterSchoolStatus = isset($_GET['filterSchoolStatus']) ? $_GET['filterSchoolStatus'] : '';
$filterClass = isset($_GET['filterClass']) ? (int)$_GET['filterClass'] : 0;

// Get the user details from users_tb table with class names
// Connect to the database
$connection = getDatabaseConnection();
if (!$connection) {
  die("ERROR: Could not connect to the database: " . mysqli_connect_error());
}

// Get filter options
$logOnStatuses = mysqli_query($connection, "SELECT DISTINCT LogOnStatus FROM users_tb WHERE LogOnStatus IS NOT NULL AND LogOnStatus != '' ORDER BY LogOnStatus");
$schoolStatuses = mysqli_query($connection, "SELECT DISTINCT SchoolStatus FROM users_tb WHERE SchoolStatus IS NOT NULL AND SchoolStatus != '' ORDER BY SchoolStatus");
$classes = mysqli_query($connection, "SELECT ClassID, classname FROM classes ORDER BY classOrder, classname");

$logOnStatusOptions = array();
while ($row = mysqli_fetch_assoc($logOnStatuses)) {
  $logOnStatusOptions[] = $row['LogOnStatus'];
}

$schoolStatusOptions = array();
while ($row = mysqli_fetch_assoc($schoolStatuses)) {
  $schoolStatusOptions[] = $row['SchoolStatus'];
}

$classOptions = array();
while ($row = mysqli_fetch_assoc($classes)) {
  $classOptions[] = $row;
}

// Query with JOIN to get class names
$usersQuery = "SELECT u.*, c.classname, c.classOrder 
               FROM users_tb u 
               LEFT JOIN classes c ON u.AssociatedClassID = c.ClassID 
               WHERE 1=1";

// Add filter conditions
if (!empty($filterLogOnStatus)) {
  $usersQuery .= " AND u.LogOnStatus = '" . mysqli_real_escape_string($connection, $filterLogOnStatus) . "'";
}
if (!empty($filterSchoolStatus)) {
  $usersQuery .= " AND u.SchoolStatus = '" . mysqli_real_escape_string($connection, $filterSchoolStatus) . "'";
}
if ($filterClass > 0) {
  $usersQuery .= " AND u.AssociatedClassID = " . $filterClass;
}

// Add ORDER BY based on sort parameter
switch ($sortBy) {
  case 'firstName':
    $usersQuery .= " ORDER BY u.FirstName, u.LastName";
    break;
  case 'lastName':
    $usersQuery .= " ORDER BY u.LastName, u.FirstName";
    break;
  case 'email':
    $usersQuery .= " ORDER BY u.Email";
    break;
  case 'logOnStatus':
    $usersQuery .= " ORDER BY u.LogOnStatus, u.LastName, u.FirstName";
    break;
  case 'schoolStatus':
    $usersQuery .= " ORDER BY u.SchoolStatus, u.LastName, u.FirstName";
    break;
  case 'className':
    $usersQuery .= " ORDER BY c.classOrder, c.classname, u.LastName, u.FirstName";
    break;
  default:
    $usersQuery .= " ORDER BY u.LastName, u.FirstName";
}

$usersResult = $connection->query($usersQuery);
if (!$usersResult) {
  die("ERROR: Could not execute query: " . $connection->error);
}
$users = $usersResult->fetch_all(MYSQLI_ASSOC);
$connection->close();

print('<link rel="stylesheet" href="../styleSheets/listAllTableStyles.css">');
// Print out the page:
// Print out the page:
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Build sort URLs with current filters
$filterParams = '';
if (!empty($filterLogOnStatus)) $filterParams .= '&filterLogOnStatus=' . urlencode($filterLogOnStatus);
if (!empty($filterSchoolStatus)) $filterParams .= '&filterSchoolStatus=' . urlencode($filterSchoolStatus);
if ($filterClass > 0) $filterParams .= '&filterClass=' . $filterClass;

print("<div class=\"listAllTable\"><table> <thead>
  <tr>
    <th style='text-align: left;'>Edit</th>
    <th><a href='?sortBy=firstName{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>First Name</a></th>
    <th><a href='?sortBy=lastName{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Last Name</a></th>
    <th><a href='?sortBy=email{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Email</a></th>
    <th><a href='?sortBy=logOnStatus{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>LogOnStatus</a></th>
    <th><a href='?sortBy=schoolStatus{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>School Status</a></th>
    <th><a href='?sortBy=className{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Class</a></th>
    <th>Delete</th>
  </tr>
  <tr>
    <td colspan='4'></td>
    <td>
      <select name='filterLogOnStatus' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
        <option value=''>-- All --</option>");
        foreach ($logOnStatusOptions as $status) {
          $selected = ($status === $filterLogOnStatus) ? 'selected' : '';
          $statusEsc = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
          print("<option value='$statusEsc' $selected>$statusEsc</option>");
        }
        print("</select>
    </td>
    <td>
      <select name='filterSchoolStatus' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
        <option value=''>-- All --</option>");
        foreach ($schoolStatusOptions as $status) {
          $selected = ($status === $filterSchoolStatus) ? 'selected' : '';
          $statusEsc = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
          print("<option value='$statusEsc' $selected>$statusEsc</option>");
        }
        print("</select>
    </td>
    <td>
      <select name='filterClass' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
        <option value='0'>-- All --</option>");
        foreach ($classOptions as $class) {
          $selected = ($class['ClassID'] == $filterClass) ? 'selected' : '';
          $classNameEsc = htmlspecialchars($class['classname'], ENT_QUOTES, 'UTF-8');
          print("<option value='{$class['ClassID']}' $selected>$classNameEsc</option>");
        }
        print("</select>
    </td>
    <td style='text-align: left;padding-left: 9px;'>");
      if (!empty($filterLogOnStatus) || !empty($filterSchoolStatus) || $filterClass > 0) {
        $sortParam = ($sortBy !== 'lastName') ? '?sortBy=' . $sortBy : '';
        print("<button type='button' onclick=\"location.href='listAllUsersPage.php{$sortParam}'\" style='padding: 4px 12px; background-color: #666; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: 500;'>Clear</button>");
      }
      print("</td>
  </tr>
</thead>");

print("<form id='filterForm' method='GET' action='listAllUsersPage.php' style='display: none;'>");
if ($sortBy !== 'lastName') {
  print("<input type='hidden' name='sortBy' value='$sortBy'>");
}
print("</form>");

print("<tbody>");
foreach ($users as $userID => $userDetails) {
  // print out a table row for each user
  print("<tr>");
  $tableUserID = $userDetails['UsersID'];
  $tableUserFirstName = htmlspecialchars($userDetails['FirstName'], ENT_QUOTES, 'UTF-8');
  $tableUserLastName = htmlspecialchars($userDetails['LastName'], ENT_QUOTES, 'UTF-8');
  $tableUserEmail = htmlspecialchars($userDetails['Email'], ENT_QUOTES, 'UTF-8');
  $tableUserLogOnStatus = htmlspecialchars($userDetails['LogOnStatus'], ENT_QUOTES, 'UTF-8');
  $tableUserSchoolStatus = htmlspecialchars($userDetails['SchoolStatus'], ENT_QUOTES, 'UTF-8');
  $tableUserClassName = htmlspecialchars($userDetails['classname'] ?? 'No Class', ENT_QUOTES, 'UTF-8');
  
  // Edit button column
  print("<td>");
  print("<a href=\"../UserEditPages/editUserDetailsPage.php?editUserID=$tableUserID\" class='listAllTableEditButton'>Edit ID=$tableUserID</a>");
  print("</td>");
  print("<td>$tableUserFirstName</td>");
  print("<td>$tableUserLastName</td>");
  print("<td>$tableUserEmail</td>");
  print("<td>$tableUserLogOnStatus</td>");
  print("<td>$tableUserSchoolStatus</td>");
  print("<td>$tableUserClassName</td>");
  
  // Delete button column
  print("<td class='listAllTableCellCenter'>");
  print("<form method='GET' action='../UserEditPages/deleteUserCode.php' class='listAllTableDeleteForm' onsubmit=\"return confirm('Are you sure you want to delete user $tableUserFirstName $tableUserLastName? This action cannot be undone.')\">");
  print("<input type='hidden' name='deleteUserID' value='$tableUserID'>");
  print("<button type='submit' class='listAllTableDeleteButton'>Delete</button>");
  print("</form>");
  print("</td>");
  
  print("</tr>");
}
print("</tbody></table></div>");
insertPageFooter($thisPageID);
?>