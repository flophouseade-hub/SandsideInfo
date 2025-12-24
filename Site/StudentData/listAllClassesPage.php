<?php
$thisPageID = 87; // You may want to create a new page entry
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');

// Restrict to fullAdmin only
if (!isset($_SESSION['currentUserLogOnStatus']) || $_SESSION['currentUserLogOnStatus'] !== 'fullAdmin') {
    header("Location: ../Pages/accessDeniedPage.php");
    exit();
}

$feedbackMessage = "";

//------------------------------------------------------------------------------------------------------
// Handle class deletion
//------------------------------------------------------------------------------------------------------
if (isset($_GET['deleteClassID']) && is_numeric($_GET['deleteClassID'])) {
    $classToDelete = (int)$_GET['deleteClassID'];
    
    $connection = connectToDatabase();
    if (!$connection) {
        $feedbackMessage = "<p style='color: red;'>ERROR: Could not connect to database.</p>";
    } else {
        // Check if class has students
        $checkQuery = "SELECT COUNT(*) as count FROM Students WHERE ClassID = ?";
        $stmt = $connection->prepare($checkQuery);
        $stmt->bind_param("i", $classToDelete);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['count'] > 0) {
            $feedbackMessage = "<p style='color: red;'>Cannot delete this class. It has " . $row['count'] . " student(s). Please move them to another class first.</p>";
        } else {
            // Delete the class
            $deleteQuery = "DELETE FROM classes WHERE ClassID = ?";
            $stmt = $connection->prepare($deleteQuery);
            $stmt->bind_param("i", $classToDelete);
            
            if ($stmt->execute()) {
                $feedbackMessage = "<p style='color: green;'>Class deleted successfully.</p>";
            } else {
                $feedbackMessage = "<p style='color: red;'>Error deleting class: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8') . "</p>";
            }
            $stmt->close();
        }
        $connection->close();
    }
}

//------------------------------------------------------------------------------------------------------
// Get all classes with student counts
//------------------------------------------------------------------------------------------------------
$connection = connectToDatabase();
if (!$connection) {
    die("ERROR: Could not connect to database");
}

$query = "SELECT c.ClassID, c.classname, c.colour, c.classOrder, 
          COUNT(s.StudentID) as studentCount
          FROM classes c
          LEFT JOIN Students s ON c.ClassID = s.ClassID
          GROUP BY c.ClassID, c.classname, c.colour, c.classOrder
          ORDER BY c.classOrder, c.classname";

$result = mysqli_query($connection, $query);
$classes = array();
while ($row = mysqli_fetch_assoc($result)) {
    $classes[] = $row;
}

$connection->close();

//------------------------------------------------------------------------------------------------------
// Display page
//------------------------------------------------------------------------------------------------------
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
print('<link rel="stylesheet" href="../styleSheets/listAllTableStyles.css">');
print(("<style>
.filterContainer, .listAllTableNote, .listAllTable {
    margin: 20px auto;
    max-width: 1100px;
}


</style>"));

insertPageTitleAndClass("All Classes", "blockMenuPageTitle", $thisPageID);

// Display feedback message
if (!empty($feedbackMessage)) {
    $bgColor = (strpos($feedbackMessage, 'color: green') !== false) ? '#d4edda' : '#f8d7da';
    $borderColor = (strpos($feedbackMessage, 'color: green') !== false) ? '#c3e6cb' : '#f5c6cb';
    print("<div style='background-color: $bgColor; padding: 15px; margin: 20px auto; max-width: 95%; border-radius: 4px; border: 1px solid $borderColor;'>$feedbackMessage</div>");
}

// Add New Class button
print("<div class='filterContainer'>");
print("<button type='button' onclick=\"location.href='addNewClassPage.php'\" class='addPageButton'>+ Add New Class</button>");
print("</div>");

// Classes Table
if (count($classes) > 0) {
    print("<div class='listAllTable'>");
    print("<table>");
    print("<thead>");
    print("<tr>");
    print("<th style='text-align: left;'>Edit</th>");
    print("<th>Order</th>");
    print("<th>Class Name</th>");
    print("<th>Colour</th>");
    print("<th>Students</th>");
    print("<th>Delete</th>");
    print("</tr>");
    print("</thead>");
    print("<tbody>");
    
    foreach ($classes as $class) {
        $classID = $class['ClassID'];
        $className = htmlspecialchars($class['classname'], ENT_QUOTES, 'UTF-8');
        $classColour = htmlspecialchars($class['colour'], ENT_QUOTES, 'UTF-8');
        $classOrder = $class['classOrder'];
        $studentCount = $class['studentCount'];
        
        print("<tr>");
        
        // Edit button column
        print("<td>");
        print("<a href='editClassPage.php?editClassID=$classID' class='listAllTableEditButton'>Edit ID=$classID</a>");
        print("</td>");
        
        print("<td>$classOrder</td>");
        print("<td><strong>$className</strong></td>");
        
        // Ensure hash is displayed
        $displayColour = (strpos($classColour, '#') === 0) ? $classColour : '#' . $classColour;
        print("<td><span style='display:inline-block; width:60px; height:25px; background-color:$classColour; border:1px solid #ddd; border-radius:4px; vertical-align:middle;'></span> $displayColour</td>");
        print("<td>$studentCount</td>");
        
        // Delete button column
        print("<td class='listAllTableCellCenter'>");
        $deleteUrl = "listAllClassesPage.php?deleteClassID=$classID";
        print("<form method='GET' action='listAllClassesPage.php' class='listAllTableDeleteForm' onsubmit=\"return confirm('Are you sure you want to delete this class? This action cannot be undone.');\">");
        print("<input type='hidden' name='deleteClassID' value='$classID'>");
        print("<button type='submit' class='listAllTableDeleteButton'>Delete</button>");
        print("</form>");
        print("</td>");
        
        print("</tr>");
    }
    
    print("</tbody>");
    print("</table>");
    print("</div>");
} else {
    print("<div style='text-align: center; padding: 40px; margin: 20px auto; max-width: 95%; background-color: #f5f5f5; border-radius: 4px;'>");
    print("<p style='color: #999; font-style: italic; font-size: 1.1em;'>No classes found. Click 'Add New Class' to create your first class.</p>");
    print("</div>");
}

// Note box
print("<div class='listAllTableNote'>");
print("<strong>Note:</strong>");
print("<ul>");
print("<li>Classes with students cannot be deleted. Move students to another class first.</li>");
print("<li>The 'Order' determines the display sequence in lists and dropdowns.</li>");
print("<li>Click 'Edit' to modify a class's details.</li>");
print("</ul>");
print("</div>");

insertPageFooter($thisPageID);
?>
