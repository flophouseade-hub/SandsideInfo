<?php
$thisPageID = 89; 
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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['addStudent'])) {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $upn = trim($_POST['upn']);
    $sex = trim($_POST['sex']);
    $classID = isset($_POST['classID']) && $_POST['classID'] !== '' ? (int)$_POST['classID'] : null;
    
    $errors = array();
    
    // Validation
    if (empty($firstName)) {
        $errors[] = "First Name is required.";
    }
    if (empty($lastName)) {
        $errors[] = "Last Name is required.";
    }
    if (empty($upn)) {
        $errors[] = "UPN is required.";
    }
    if (empty($sex)) {
        $errors[] = "Sex is required.";
    }
    
    if (count($errors) == 0) {
        $connection = connectToDatabase();
        if (!$connection) {
            $feedbackMessage = "<p style='color: red;'>ERROR: Could not connect to database.</p>";
        } else {
            // Check if UPN already exists
            $checkQuery = "SELECT StudentID FROM Students WHERE UPN = ?";
            $stmt = $connection->prepare($checkQuery);
            $stmt->bind_param("s", $upn);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $feedbackMessage = "<p style='color: red;'>ERROR: A student with UPN '$upn' already exists.</p>";
            } else {
                // Insert new student
                $insertQuery = "INSERT INTO Students (FirstName, LastName, UPN, Sex, ClassID) VALUES (?, ?, ?, ?, ?)";
                $stmt = $connection->prepare($insertQuery);
                $stmt->bind_param("ssssi", $firstName, $lastName, $upn, $sex, $classID);
                
                if ($stmt->execute()) {
                    $newStudentID = $stmt->insert_id;
                    header("Location: manageStudentsPage.php");
                    exit();
                } else {
                    $feedbackMessage = "<p style='color: red;'>ERROR: Could not add student. " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8') . "</p>";
                }
            }
            
            $stmt->close();
            $connection->close();
        }
    } else {
        $feedbackMessage = "<p style='color: red;'>" . implode("<br>", $errors) . "</p>";
    }
}

//------------------------------------------------------------------------------------------------------
// Fetch all classes for dropdown
//------------------------------------------------------------------------------------------------------
$connection = connectToDatabase();
if (!$connection) {
    die("ERROR: Could not connect to database");
}

$classQuery = "SELECT ClassID, classname, colour, classOrder FROM classes ORDER BY classOrder, classname";
$classResult = mysqli_query($connection, $classQuery);
$allClasses = array();
while ($row = mysqli_fetch_assoc($classResult)) {
    $allClasses[] = $row;
}

$connection->close();

//------------------------------------------------------------------------------------------------------
// Start HTML output
//------------------------------------------------------------------------------------------------------
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');
?>
<div class="formPageWrapper">
    <div class="formContainer">
        <h2>Add New Student</h2>
        
        <?php echo $feedbackMessage; ?>
        
        <form method="POST" action="addNewStudentPage.php">
            
            <div class="formField">
                <label for="firstName">First Name *</label>
                <input type="text" id="firstName" name="firstName" class="formInput" 
                       value="<?php echo isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName'], ENT_QUOTES, 'UTF-8') : ''; ?>" 
                       required>
            </div>
            
            <div class="formField">
                <label for="lastName">Last Name *</label>
                <input type="text" id="lastName" name="lastName" class="formInput" 
                       value="<?php echo isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName'], ENT_QUOTES, 'UTF-8') : ''; ?>" 
                       required>
            </div>
            
            <div class="formField">
                <label for="upn">UPN (Unique Pupil Number) *</label>
                <input type="text" id="upn" name="upn" class="formInput" 
                       value="<?php echo isset($_POST['upn']) ? htmlspecialchars($_POST['upn'], ENT_QUOTES, 'UTF-8') : ''; ?>" 
                       required>
                <span class="formInputHelper">Must be unique for each student</span>
            </div>
            
            <div class="formField">
                <label for="sex">Sex *</label>
                <select id="sex" name="sex" class="formSelect" required>
                    <option value="">-- Select --</option>
                    <option value="Male" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            
            <div class="formField">
                <label for="classID">Class</label>
                <select id="classID" name="classID" class="formSelect">
                    <option value="">-- No Class --</option>
                    <?php
                    foreach ($allClasses as $class) {
                        $selected = (isset($_POST['classID']) && $_POST['classID'] == $class['ClassID']) ? 'selected' : '';
                        $colorSwatch = "<span style='display:inline-block; width:15px; height:15px; background-color:{$class['colour']}; border:1px solid #999; margin-right:5px; vertical-align:middle;'></span>";
                        print("<option value='{$class['ClassID']}' $selected>$colorSwatch {$class['classname']}</option>");
                    }
                    ?>
                </select>
                <span class="formInputHelper">Optional - can be assigned later</span>
            </div>
            
            <div class="formButtonContainer">
                <button type="submit" name="addStudent" class="formButtonPrimary">Add Student</button>
                <a href="manageStudentsPage.php" class="formButtonSecondary">Cancel</a>
            </div>
            
        </form>
        
    </div>
</div>
<?php
insertPageFooter($thisPageID);
?>