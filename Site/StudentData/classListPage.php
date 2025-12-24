<?php
$thisPageID = 2;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Get the data from the database
$con = connectToDatabase();
if (!$con) {
    die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Get the page details for this page from the array
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "Class Lists";
$pageDescription = $_SESSION['pagesOnSite'][$thisPageID]['PageDescription'] ?? "";

insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

print("<link href=\"../styleSheets/classListStyles.css\"rel=\"stylesheet\" type=\"text/css\">");

print("<div class=\"classBoxWrapper\">");

if (!empty($pageDescription)) {
    print("<div class=\"classListDescription\">
            <p>$pageDescription</p>
        </div>");
}

print("<div class=\"classListContainer\">");

// Get all classes ordered by classOrder
$query = "SELECT * FROM classes WHERE 1 ORDER BY classOrder";
$resultClasses = mysqli_query($con, $query);

while ($row = mysqli_fetch_assoc($resultClasses)) {
    $classID = $row['ClassID'];
    $colorCode = $row['colour'];
    $className = htmlspecialchars($row['classname'], ENT_QUOTES, 'UTF-8');

    // Get students for this class using prepared statement
    $stmtStudents = $con->prepare("SELECT FirstName, LastName FROM Students WHERE ClassID = ? ORDER BY LastName, FirstName");
    $stmtStudents->bind_param('i', $classID);
    $stmtStudents->execute();
    $resultStudents = $stmtStudents->get_result();

    $arrayStudents = array();
    while ($rowStudent = $resultStudents->fetch_assoc()) {
        $arrayStudents[] = array(
            'FirstName' => $rowStudent['FirstName'],
            'LastName' => $rowStudent['LastName']
        );
    }
    $stmtStudents->close();

    // Get staff for this class using prepared statement
    $stmtStaff = $con->prepare("SELECT FirstName, LastName, SchoolStatus FROM UsersDB WHERE AssociatedClassID = ? ORDER BY SchoolStatus DESC, LastName, FirstName");
    $stmtStaff->bind_param('i', $classID);
    $stmtStaff->execute();
    $resultStaff = $stmtStaff->get_result();

    $arrayStaff = array();
    while ($rowStaff = $resultStaff->fetch_assoc()) {
        $arrayStaff[] = array(
            'FirstName' => $rowStaff['FirstName'],
            'LastName' => $rowStaff['LastName'],
            'Status' => $rowStaff['SchoolStatus']
        );
    }
    $stmtStaff->close();

    $noOfStudents = count($arrayStudents);
    $noOfStaff = count($arrayStaff);
    $noOfRows = max($noOfStudents, $noOfStaff);

    echo "<div class=\"classBox\">";
    echo "<div class=\"classBoxHeader\">";
    echo "<h3>$className <small>$noOfStudents</small></h3>";
    
    // Display teacher names under the class name
    foreach ($arrayStaff as $staff) {
        if ($staff['Status'] == "Teacher") {
            $teacherName = htmlspecialchars($staff['FirstName'] . " " . $staff['LastName'], ENT_QUOTES, 'UTF-8');
            echo "<p><strong>$teacherName</strong></p>";
        }
    }
    
    echo "</div>"; // Close classBoxHeader
    echo "<div class=\"classBoxBody\">";
    echo "<table bgcolor=\"" . htmlspecialchars($colorCode, ENT_QUOTES, 'UTF-8') . "\" border=\"1\">";

    for ($rowNum = 0; $rowNum < $noOfRows; $rowNum++) {
        echo '<tr>';

        // Student column
        if (isset($arrayStudents[$rowNum])) {
            $studentName = htmlspecialchars($arrayStudents[$rowNum]['FirstName'] . " " . $arrayStudents[$rowNum]['LastName'], ENT_QUOTES, 'UTF-8');
            echo "<td>$studentName</td>";
        } else {
            echo "<td>&nbsp;</td>";
        }

        // Staff column
        if (isset($arrayStaff[$rowNum])) {
            $staffName = htmlspecialchars($arrayStaff[$rowNum]['FirstName'] . " " . $arrayStaff[$rowNum]['LastName'], ENT_QUOTES, 'UTF-8');
            $status = $arrayStaff[$rowNum]['Status'];

            if ($status == "TA") {
                echo "<td><em>$staffName</em></td>";
            } else {
                echo "<td>$staffName</td>";
            }
        } else {
            echo "<td>&nbsp;</td>";
        }

        echo '</tr>';
    }

    echo "</table>";
    echo "</div>"; // Close classBox
    echo "</div>"; // Close classBoxBody
}

echo "</div>"; // Close gallery
echo "</div>"; // Close classBoxWrapper

print("<div style=\"margin-bottom: 30px;\"></div>"); // Add spacing at bottom

echo "</div>"; // Close mainContent

mysqli_close($con);

insertPageFooter($thisPageID);
?>
