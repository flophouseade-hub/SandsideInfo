<?php
$thisPageID = 86;
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');

// Restrict to fullAdmin only
if (!isset($_SESSION['currentUserLogOnStatus']) || $_SESSION['currentUserLogOnStatus'] !== 'fullAdmin') {
    header("Location: ../Pages/accessDeniedPage.php");
    exit();
}

$feedbackMessage = "";

//------------------------------------------------------------------------------------------------------
// Handle student deletion
//------------------------------------------------------------------------------------------------------
if (isset($_GET['deleteStudentID']) && is_numeric($_GET['deleteStudentID'])) {
    $studentToDelete = (int)$_GET['deleteStudentID'];
    
    $connection = connectToDatabase();
    if (!$connection) {
        $feedbackMessage = "<p style='color: red;'>ERROR: Could not connect to database.</p>";
    } else {
        $deleteQuery = "DELETE FROM students_tb WHERE StudentID = ?";
        $stmt = $connection->prepare($deleteQuery);
        $stmt->bind_param("i", $studentToDelete);
        
        if ($stmt->execute()) {
            $feedbackMessage = "<p style='color: green;'>Student deleted successfully.</p>";
        } else {
            $feedbackMessage = "<p style='color: red;'>Error deleting student: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8') . "</p>";
        }
        $stmt->close();
        $connection->close();
    }
}

//------------------------------------------------------------------------------------------------------
// Get filter and data
//------------------------------------------------------------------------------------------------------
$filterClass = isset($_GET['filterClass']) ? (int)$_GET['filterClass'] : 0;
$filterSex = isset($_GET['filterSex']) ? $_GET['filterSex'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'class';

$connection = connectToDatabase();
if (!$connection) {
    die("ERROR: Could not connect to database");
}

// Get all classes for filter dropdown
$classesQuery = "SELECT ClassID, classname FROM classes ORDER BY classOrder, classname";
$classesResult = mysqli_query($connection, $classesQuery);
$allClasses = array();
while ($row = mysqli_fetch_assoc($classesResult)) {
    $allClasses[] = $row;
}

// Get distinct sex values for filter
$sexQuery = "SELECT DISTINCT Sex FROM students_tb WHERE Sex IS NOT NULL AND Sex != '' ORDER BY Sex";
$sexResult = mysqli_query($connection, $sexQuery);
$sexOptions = array();
while ($row = mysqli_fetch_assoc($sexResult)) {
    $sexOptions[] = $row['Sex'];
}

// Build student query with optional filter
$students_tbQuery = "SELECT s.StudentID, s.FirstName, s.LastName, s.UPN, s.Sex, s.ClassID, c.classname, c.colour, c.classOrder 
                  FROM students_tb s 
                  LEFT JOIN classes c ON s.ClassID = c.ClassID 
                  WHERE 1=1";

if ($filterClass > 0) {
    $students_tbQuery .= " AND s.ClassID = " . $filterClass;
}
if (!empty($filterSex)) {
    $students_tbQuery .= " AND s.Sex = '" . mysqli_real_escape_string($connection, $filterSex) . "'";
}

// Add sorting based on sortBy parameter
switch ($sortBy) {
    case 'firstName':
        $students_tbQuery .= " ORDER BY s.FirstName, s.LastName";
        break;
    case 'lastName':
        $students_tbQuery .= " ORDER BY s.LastName, s.FirstName";
        break;
    case 'sex':
        $students_tbQuery .= " ORDER BY s.Sex, s.LastName, s.FirstName";
        break;
    case 'class':
    default:
        $students_tbQuery .= " ORDER BY c.classOrder, c.classname, s.LastName, s.FirstName";
        break;
}

$students_tbResult = mysqli_query($connection, $students_tbQuery);
$students_tb = array();
while ($row = mysqli_fetch_assoc($students_tbResult)) {
    $students_tb[] = $row;
}

$connection->close();

//------------------------------------------------------------------------------------------------------
// Display page
//------------------------------------------------------------------------------------------------------
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
print('<link rel="stylesheet" href="../styleSheets/listAllTableStyles.css">');

$pageTitle = "All students_tb";
if ($filterClass > 0) {
    foreach ($allClasses as $class) {
        if ($class['ClassID'] == $filterClass) {
            $pageTitle .= " - " . htmlspecialchars($class['classname'], ENT_QUOTES, 'UTF-8');
            break;
        }
    }
}

insertPageTitleAndClass($pageTitle, "blockMenuPageTitle", $thisPageID);

// Display feedback message
if (!empty($feedbackMessage)) {
    $bgColor = (strpos($feedbackMessage, 'color: green') !== false) ? '#d4edda' : '#f8d7da';
    $borderColor = (strpos($feedbackMessage, 'color: green') !== false) ? '#c3e6cb' : '#f5c6cb';
    print("<div style='background-color: $bgColor; padding: 15px; margin: 20px auto; max-width: 95%; border-radius: 4px; border: 1px solid $borderColor;'>$feedbackMessage</div>");
}

// Add Student button above table
print("<div style='margin: 20px auto;width: calc(100% - 20px);max-width: 1200px; text-align: right;'>");
print("<button type='button' onclick=\"location.href='addNewStudentPage.php'\" style='padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;'>+ Add New Student</button>");
print("</div>");

// Build filter parameters for sort links
$filterParams = '';
if ($filterClass > 0) $filterParams .= '&filterClass=' . $filterClass;
if (!empty($filterSex)) $filterParams .= '&filterSex=' . urlencode($filterSex);

// students_tb table
if (count($students_tb) > 0) {
    print("<div id='students_tbReferenceTable' class='listAllTable' style=\"margin: 0 auto;width: calc(100% - 20px);max-width: 1200px;\">");
    print("<table>");
    print("<thead>");
    print("<tr>");
    print("<th style='text-align: left;'>Edit</th>");
    print("<th><a href='?sortBy=firstName{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>First Name</a></th>");
    print("<th><a href='?sortBy=lastName{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Last Name</a></th>");
    print("<th>UPN</th>");
    print("<th><a href='?sortBy=sex{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Sex</a></th>");
    print("<th><a href='?sortBy=class{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Current Class</a></th>");
    print("<th>Delete</th>");
    print("</tr>");
    print("<tr>");
    print("<td colspan='4'></td>");
    print("<td>");
    print("<select name='filterSex' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>");
    print("<option value=''>-- All --</option>");
    foreach ($sexOptions as $sex) {
        $selected = ($sex === $filterSex) ? 'selected' : '';
        $sexEsc = htmlspecialchars($sex, ENT_QUOTES, 'UTF-8');
        print("<option value='$sexEsc' $selected>$sexEsc</option>");
    }
    print("</select>");
    print("</td>");
    print("<td>");
    print("<select name='filterClass' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>");
    print("<option value='0'>-- All --</option>");
    foreach ($allClasses as $class) {
        $selected = ($class['ClassID'] == $filterClass) ? 'selected' : '';
        $className = htmlspecialchars($class['classname'], ENT_QUOTES, 'UTF-8');
        print("<option value='{$class['ClassID']}' $selected>$className</option>");
    }
    print("</select>");
    print("</td>");
    print("<td style='text-align: left;padding-left: 9px;'>");
    if ($filterClass > 0 || !empty($filterSex)) {
        $sortParam = ($sortBy !== 'class') ? '?sortBy=' . $sortBy : '';
        print("<button type='button' onclick=\"location.href='listAllstudents_tbPage.php{$sortParam}'\" style='padding: 4px 12px; background-color: #666; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: 500;'>Clear</button>");
    }
    print("</td>");
    print("</tr>");
    print("</thead>");
    
    print("<form id='filterForm' method='GET' action='listAllstudents_tbPage.php' style='display: none;'>");
    if ($sortBy !== 'class') {
        print("<input type='hidden' name='sortBy' value='$sortBy'>");
    }
    print("</form>");
    
    print("<tbody>");
    
    foreach ($students_tb as $student) {
        $studentID = $student['StudentID'];
        $firstName = htmlspecialchars($student['FirstName'], ENT_QUOTES, 'UTF-8');
        $lastName = htmlspecialchars($student['LastName'], ENT_QUOTES, 'UTF-8');
        $upn = htmlspecialchars($student['UPN'], ENT_QUOTES, 'UTF-8');
        $sex = htmlspecialchars($student['Sex'], ENT_QUOTES, 'UTF-8');
        $className = htmlspecialchars($student['classname'] ?? 'No Class', ENT_QUOTES, 'UTF-8');
        $classColour = htmlspecialchars($student['colour'] ?? '#cccccc', ENT_QUOTES, 'UTF-8');
        
        print("<tr>");
        
        // Edit button column
        print("<td>");
        print("<a href='editStudentPage.php?editStudentID=$studentID' class='listAllTableEditButton'>Edit ID=$studentID</a>");
        print("</td>");
        
        print("<td>$firstName</td>");
        print("<td>$lastName</td>");
        print("<td>$upn</td>");
        print("<td>$sex</td>");
        print("<td><span style='display: inline-block; width: 15px; height: 15px; background-color: $classColour; border: 1px solid #999; margin-right: 5px; vertical-align: middle;'></span>$className</td>");
        
        // Delete button column
        print("<td class='listAllTableCellCenter'>");
        $deleteUrl = "listAllstudents_tbPage.php?deleteStudentID=$studentID";
        if ($filterClass > 0) {
            $deleteUrl .= "&filterClass=$filterClass";
        }
        print("<form method='GET' action='listAllstudents_tbPage.php' class='listAllTableDeleteForm' onsubmit=\"return confirm('Are you sure you want to delete $firstName $lastName? This action cannot be undone.');\">");
        print("<input type='hidden' name='deleteStudentID' value='$studentID'>");
        if ($filterClass > 0) {
            print("<input type='hidden' name='filterClass' value='$filterClass'>");
        }        if ($sortBy !== 'class') {
            print("<input type='hidden' name='sortBy' value='$sortBy'>");
        }        print("<button type='submit' class='listAllTableDeleteButton'>Delete</button>");
        print("</form>");
        print("</td>");
        
        print("</tr>");
    }
    
    print("</tbody>");
    print("</table>");
    print("</div>");
} else {
    print("<div style='text-align: center; padding: 40px; margin: 20px auto; max-width: 95%; background-color: #f5f5f5; border-radius: 4px;'>");
    print("<p style='color: #999; font-style: italic; font-size: 1.1em;'>No students_tb found" . ($filterClass > 0 ? " in this class" : "") . ".</p>");
    print("</div>");
}

// Note box
print("<div class='listAllTableNote'>");
print("<strong>Note:</strong>");
print("<ul>");
print("<li>Click 'Edit' to modify a student's details including name, UPN, sex, and class.</li>");
print("<li>Use the filter above to view students_tb by class.</li>");
print("<li>Click 'Add New Student' to add individual students_tb, or use the Upload CSV option for bulk uploads.</li>");
print("</ul>");
print("</div>");

print("</div>"); // Close students_tbListWrapper

insertPageFooter($thisPageID);
?>