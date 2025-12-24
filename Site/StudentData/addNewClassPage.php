<?php
$thisPageID = 2; // You may want to create a new page entry
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');

// Restrict to fullAdmin only
if (!isset($_SESSION['currentUserLogOnStatus']) || $_SESSION['currentUserLogOnStatus'] !== 'fullAdmin') {
    header("Location: ../Pages/accessDeniedPage.php");
    exit();
}

$feedbackMessage = "";

//------------------------------------------------------------------------------------------------------
// Handle form submission
//------------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['addClass'])) {
    $className = trim($_POST['className']);
    $classColour = trim($_POST['classColour']);
    $classOrder = (int)$_POST['classOrder'];
    
    $inputOK = true;
    
    // Validate class name
    if (empty($className)) {
        $inputOK = false;
        $feedbackMessage .= "<p style='color: red;'>Class name is required.</p>";
    }
    
    // Validate color (basic check for hex code)
    if (!empty($classColour) && !preg_match('/^#?[0-9A-Fa-f]{6}$/', $classColour)) {
        $inputOK = false;
        $feedbackMessage .= "<p style='color: red;'>Invalid color code. Use hex format (e.g., #FF5733 or FF5733).</p>";
    }
    
    // Ensure color has # prefix
    if (!empty($classColour) && $classColour[0] !== '#') {
        $classColour = '#' . $classColour;
    }
    
    if ($inputOK) {
        $connection = connectToDatabase();
        if (!$connection) {
            $feedbackMessage = "<p style='color: red;'>ERROR: Could not connect to database.</p>";
        } else {
            // Insert new class
            $insertQuery = "INSERT INTO classes (classname, colour, classOrder) VALUES (?, ?, ?)";
            $stmt = $connection->prepare($insertQuery);
            $stmt->bind_param("ssi", $className, $classColour, $classOrder);
            
            if ($stmt->execute()) {
                header("Location: listAllClassesPage.php");
                exit();
            } else {
                $feedbackMessage = "<p style='color: red;'>Error adding class: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8') . "</p>";
            }
            $stmt->close();
            $connection->close();
        }
    }
}

//------------------------------------------------------------------------------------------------------
// Get next class order number
//------------------------------------------------------------------------------------------------------
$connection = connectToDatabase();
if (!$connection) {
    die("ERROR: Could not connect to database");
}

$query = "SELECT COUNT(*) as count FROM classes";
$result = mysqli_query($connection, $query);
$row = mysqli_fetch_assoc($result);
$nextOrder = $row['count'] + 1;

$connection->close();

//------------------------------------------------------------------------------------------------------
// Start HTML output
//------------------------------------------------------------------------------------------------------
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Class</title>
    <link rel="stylesheet" href="../styleSheets/headerAndFooterStyles.css">
    <link rel="stylesheet" href="../styleSheets/formPageFormatting.css">
    <style>
        .colorInputWrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .colorInputWrapper input[type="text"] {
            flex: 1;
        }
        .colorInputWrapper input[type="color"] {
            width: 50px;
            height: 38px;
            cursor: pointer;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="formPageWrapper">
    <div class="formContainer">
        <h2>Add New Class</h2>
        
        <?php echo $feedbackMessage; ?>
        
        <form method="POST" action="addNewClassPage.php">
            
            <div class="formField">
                <label for="className">Class Name *</label>
                <input type="text" id="className" name="className" class="formInput" 
                       value="<?php echo isset($_POST['className']) ? htmlspecialchars($_POST['className'], ENT_QUOTES, 'UTF-8') : ''; ?>" 
                       placeholder="e.g., Year 1, Reception, Nursery" required>
                <span class="formInputHelper">The name of the class/form group</span>
            </div>
            
            <div class="formField">
                <label for="classColourText">Class Colour</label>
                <div class="colorInputWrapper">
                    <input type="text" id="classColourText" name="classColour" class="formInput" 
                           value="<?php echo isset($_POST['classColour']) ? htmlspecialchars($_POST['classColour'], ENT_QUOTES, 'UTF-8') : 'FFFFFF'; ?>" 
                           placeholder="FF5733">
                    <input type="color" id="classColourPicker" value="#<?php echo isset($_POST['classColour']) ? ltrim($_POST['classColour'], '#') : 'FFFFFF'; ?>">
                </div>
                <span class="formInputHelper">Hex color code (with or without #)</span>
            </div>
            
            <div class="formField">
                <label for="classOrder">Display Order</label>
                <input type="number" id="classOrder" name="classOrder" class="formInput" 
                       value="<?php echo isset($_POST['classOrder']) ? (int)$_POST['classOrder'] : $nextOrder; ?>" 
                       min="1" required>
                <span class="formInputHelper">Lower numbers appear first</span>
            </div>
            
            <div class="formButtonContainer">
                <button type="submit" name="addClass" class="formButtonPrimary">Add Class</button>
                <a href="listAllClassesPage.php" class="formButtonSecondary">Cancel</a>
            </div>
            
        </form>
        
    </div>
</div>

<script>
document.getElementById('classColourPicker').addEventListener('change', function() {
    document.getElementById('classColourText').value = this.value.substring(1);
});
document.getElementById('classColourText').addEventListener('input', function() {
    var value = this.value.replace('#', '');
    if (value.length === 6 && /^[0-9A-Fa-f]{6}$/.test(value)) {
        document.getElementById('classColourPicker').value = '#' + value;
    }
});
</script>

</body>
</html>
    }
}
<?php
//------------------------------------------------------------------------------------------------------
// Handle add/edit form submission
//------------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['saveClass'])) {
    $className = trim($_POST['className']);
    $classColour = trim($_POST['classColour']);
    $classOrder = (int)$_POST['classOrder'];
    $classID = isset($_POST['classID']) ? (int)$_POST['classID'] : 0;
    
    $inputOK = true;
    
    // Validate class name
    if (empty($className)) {
        $inputOK = false;
        $feedbackMessage .= "<p style='color: red;'>Class name is required.</p>";
    }
    
    // Validate color (basic check for hex code)
    if (!empty($classColour) && !preg_match('/^#?[0-9A-Fa-f]{6}$/', $classColour)) {
        $inputOK = false;
        $feedbackMessage .= "<p style='color: red;'>Invalid color code. Use hex format (e.g., #FF5733 or FF5733).</p>";
    }
    
    // Ensure color has # prefix
    if (!empty($classColour) && $classColour[0] !== '#') {
        $classColour = '#' . $classColour;
    }
    
    if ($inputOK) {
        $connection = connectToDatabase();
        if (!$connection) {
            $feedbackMessage = "<p style='color: red;'>ERROR: Could not connect to database.</p>";
        } else {
            if ($classID > 0) {
                // Update existing class
                $updateQuery = "UPDATE classes SET classname = ?, colour = ?, classOrder = ? WHERE ClassID = ?";
                $stmt = $connection->prepare($updateQuery);
                $stmt->bind_param("ssii", $className, $classColour, $classOrder, $classID);
                
                if ($stmt->execute()) {
                    $feedbackMessage = "<p style='color: green;'>Class updated successfully.</p>";
                } else {
                    $feedbackMessage = "<p style='color: red;'>Error updating class: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8') . "</p>";
                }
                $stmt->close();
            } else {
                // Insert new class
                $insertQuery = "INSERT INTO classes (classname, colour, classOrder) VALUES (?, ?, ?)";
                $stmt = $connection->prepare($insertQuery);
                $stmt->bind_param("ssi", $className, $classColour, $classOrder);
                
                if ($stmt->execute()) {
                    $feedbackMessage = "<p style='color: green;'>Class added successfully.</p>";
                } else {
                    $feedbackMessage = "<p style='color: red;'>Error adding class: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8') . "</p>";
                }
                $stmt->close();
            }
            $connection->close();
        }
    }
}

//------------------------------------------------------------------------------------------------------
// Check if editing
//------------------------------------------------------------------------------------------------------
if (isset($_GET['editClassID']) && is_numeric($_GET['editClassID'])) {
    $editMode = true;
    $editClassID = (int)$_GET['editClassID'];
    
    $connection = connectToDatabase();
    if ($connection) {
        $query = "SELECT * FROM classes WHERE ClassID = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("i", $editClassID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $editClassData = $result->fetch_assoc();
        }
        $stmt->close();
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
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

print('<style>
.classesWrapper {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.classesTable {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    background-color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.classesTable thead {
    background-color: #f5f5f5;
    border-bottom: 2px solid #ddd;
}

.classesTable th {
    padding: 15px;
    text-align: left;
    font-weight: 700;
    color: #333;
    font-size: 14px;
    text-transform: uppercase;
}

.classesTable td {
    padding: 12px 15px;
    border-bottom: 1px solid #f0f0f0;
    color: #666;
    vertical-align: middle;
}

.classesTable tbody tr:hover {
    background-color: #fafafa;
}

.colorSwatch {
    display: inline-block;
    width: 60px;
    height: 30px;
    border: 1px solid #ddd;
    border-radius: 4px;
    vertical-align: middle;
}

.actionLinks {
    display: flex;
    gap: 15px;
}

.actionLinks a {
    text-decoration: none;
    font-weight: 600;
}

.editLink {
    color: #1976d2;
}

.deleteLink {
    color: #d32f2f;
}

.addClassForm {
    background-color: #f9f9f9;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #ddd;
}

.colorInputWrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.colorInputWrapper input[type="text"] {
    flex: 1;
}

.colorInputWrapper input[type="color"] {
    width: 50px;
    height: 38px;
    cursor: pointer;
    border: 1px solid #ccc;
    border-radius: 4px;
}
</style>');

insertPageTitleAndClass("Manage Classes", "blockMenuPageTitle", $thisPageID);

print("<div class='classesWrapper'>");

// Display feedback message
if (!empty($feedbackMessage)) {
    $bgColor = (strpos($feedbackMessage, 'color: green') !== false) ? '#d4edda' : '#f8d7da';
    $borderColor = (strpos($feedbackMessage, 'color: green') !== false) ? '#c3e6cb' : '#f5c6cb';
    print("<div style='background-color: $bgColor; padding: 15px; margin: 20px 0; border-radius: 4px; border: 1px solid $borderColor;'>$feedbackMessage</div>");
}

// Add/Edit Class Form
$formTitle = $editMode ? "Edit Class" : "Add New Class";
$formClassName = $editMode ? htmlspecialchars($editClassData['classname'], ENT_QUOTES, 'UTF-8') : '';
$formClassColour = $editMode ? htmlspecialchars($editClassData['colour'], ENT_QUOTES, 'UTF-8') : '#FFFFFF';
$formClassOrder = $editMode ? $editClassData['classOrder'] : (count($classes) + 1);
$formClassID = $editMode ? $editClassData['ClassID'] : 0;

// Remove # for text input display
$formClassColourDisplay = ltrim($formClassColour, '#');

print("<div class='addClassForm'>");
print("<h3>$formTitle</h3>");
print("<form method='POST' action='manageClassesPage.php'>");
if ($editMode) {
    print("<input type='hidden' name='classID' value='$formClassID'>");
}
print("<div class='formContainer'>");

print("<div class='formField'>");
print("<label>Class Name *</label>");
print("<input type='text' name='className' value='$formClassName' class='formInput' placeholder='e.g., Year 1, Reception, Nursery' required>");
print("<span class='formInputHelper'>The name of the class/form group</span>");
print("</div>");

print("<div class='formField'>");
print("<label>Class Colour</label>");
print("<div class='colorInputWrapper'>");
print("<input type='text' name='classColour' id='classColourText' value='$formClassColourDisplay' class='formInput' placeholder='FF5733'>");
print("<input type='color' id='classColourPicker' value='$formClassColour'>");
print("</div>");
print("<span class='formInputHelper'>Hex color code (with or without #)</span>");
print("</div>");

print("<div class='formField'>");
print("<label>Display Order</label>");
print("<input type='number' name='classOrder' value='$formClassOrder' class='formInput' min='1'>");
print("<span class='formInputHelper'>Lower numbers appear first</span>");
print("</div>");

print("<div class='formButtonContainer'>");
print("<button type='submit' name='saveClass' class='formButtonPrimary'>" . ($editMode ? "Update Class" : "Add Class") . "</button>");
if ($editMode) {
    print("<a href='manageClassesPage.php' class='formButtonSecondary'>Cancel Edit</a>");
}
print("<a href='manageStudentsPage.php' class='formButtonSecondary'>Manage Students</a>");
print("</div>");

print("</div>");
print("</form>");
print("</div>");

// JavaScript for color picker sync
print("<script>
document.getElementById('classColourPicker').addEventListener('change', function() {
    document.getElementById('classColourText').value = this.value.substring(1);
});
document.getElementById('classColourText').addEventListener('input', function() {
    var value = this.value;
    if (value.length === 6 && /^[0-9A-Fa-f]{6}$/.test(value)) {
        document.getElementById('classColourPicker').value = '#' + value;
    }
});
</script>");

// Classes Table
print("<h3>All Classes (" . count($classes) . ")</h3>");

if (count($classes) > 0) {
    print("<table class='classesTable'>");
    print("<thead>");
    print("<tr>");
    print("<th>Order</th>");
    print("<th>Class Name</th>");
    print("<th>Colour</th>");
    print("<th>Students</th>");
    print("<th>Actions</th>");
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
        print("<td>$classOrder</td>");
        print("<td><strong>$className</strong></td>");
        print("<td><span class='colorSwatch' style='background-color: $classColour;'></span> $classColour</td>");
        print("<td>$studentCount</td>");
        print("<td>");
        print("<div class='actionLinks'>");
        print("<a href='manageClassesPage.php?editClassID=$classID' class='editLink'>Edit</a>");
        $deleteUrl = "manageClassesPage.php?deleteClassID=$classID";
        print("<a href='$deleteUrl' class='deleteLink' onclick=\"return confirm('Are you sure you want to delete this class? This action cannot be undone.');\">Delete</a>");
        print("</div>");
        print("</td>");
        print("</tr>");
    }
    
    print("</tbody>");
    print("</table>");
} else {
    print("<div style='text-align: center; padding: 40px; color: #999; font-style: italic;'>");
    print("No classes found. Add your first class above.");
    print("</div>");
}

print("</div>"); // Close classesWrapper

insertPageFooter($thisPageID);
?>
