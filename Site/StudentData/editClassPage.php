<?php
$thisPageID = 91; 
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');

// Restrict to fullAdmin only
if (!isset($_SESSION['currentUserLogOnStatus']) || $_SESSION['currentUserLogOnStatus'] !== 'fullAdmin') {
    header("Location: ../Pages/accessDeniedPage.php");
    exit();
}

$feedbackMessage = "";
$classData = null;

// Get class ID from URL
if (!isset($_GET['editClassID']) || !is_numeric($_GET['editClassID'])) {
    header("Location: listAllClassesPage.php");
    exit();
}

$editClassID = (int)$_GET['editClassID'];

//------------------------------------------------------------------------------------------------------
// Handle form submission
//------------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['updateClass'])) {
    $className = trim($_POST['className']);
    $classColour = trim($_POST['classColour']);
    $classOrder = (int)$_POST['classOrder'];
    $classID = (int)$_POST['classID'];
    
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
            // Update existing class
            $updateQuery = "UPDATE classes SET classname = ?, colour = ?, classOrder = ? WHERE ClassID = ?";
            $stmt = $connection->prepare($updateQuery);
            $stmt->bind_param("ssii", $className, $classColour, $classOrder, $classID);
            
            if ($stmt->execute()) {
                header("Location: listAllClassesPage.php");
                exit();
            } else {
                $feedbackMessage = "<p style='color: red;'>Error updating class: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8') . "</p>";
            }
            $stmt->close();
            $connection->close();
        }
    }
}

//------------------------------------------------------------------------------------------------------
// Get class data
//------------------------------------------------------------------------------------------------------
$connection = connectToDatabase();
if (!$connection) {
    die("ERROR: Could not connect to database");
}

$query = "SELECT * FROM classes WHERE ClassID = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $editClassID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $classData = $result->fetch_assoc();
} else {
    $stmt->close();
    $connection->close();
    header("Location: listAllClassesPage.php");
    exit();
}

$stmt->close();
$connection->close();

insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');
//------------------------------------------------------------------------------------------------------
// Start HTML output
//------------------------------------------------------------------------------------------------------
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Class</title>
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
        <h2>Edit Class: <?php echo htmlspecialchars($classData['classname'], ENT_QUOTES, 'UTF-8'); ?></h2>
        
        <?php echo $feedbackMessage; ?>
        
        <form method="POST" action="editClassPage.php?editClassID=<?php echo $editClassID; ?>">
            <input type="hidden" name="classID" value="<?php echo $editClassID; ?>">
            
            <div class="formField">
                <label for="className">Class Name *</label>
                <input type="text" id="className" name="className" class="formInput" 
                       value="<?php echo isset($_POST['className']) ? htmlspecialchars($_POST['className'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($classData['classname'], ENT_QUOTES, 'UTF-8'); ?>" 
                       placeholder="e.g., Year 1, Reception, Nursery" required>
                <span class="formInputHelper">The name of the class/form group</span>
            </div>
            
            <div class="formField">
                <label for="classColourText">Class Colour</label>
                <div class="colorInputWrapper">
                    <?php 
                    $currentColour = isset($_POST['classColour']) ? $_POST['classColour'] : $classData['colour'];
                    // Ensure hash prefix for display
                    if (strpos($currentColour, '#') !== 0) {
                        $currentColour = '#' . $currentColour;
                    }
                    $currentColourDisplay = $currentColour; // Display with hash
                    ?>
                    <input type="text" id="classColourText" name="classColour" class="formInput" 
                           value="<?php echo htmlspecialchars($currentColourDisplay, ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="#FF5733">
                    <input type="color" id="classColourPicker" value="<?php echo htmlspecialchars($currentColour, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <span class="formInputHelper">Hex color code (with or without #)</span>
            </div>
            
            <div class="formField">
                <label for="classOrder">Display Order</label>
                <input type="number" id="classOrder" name="classOrder" class="formInput" 
                       value="<?php echo isset($_POST['classOrder']) ? (int)$_POST['classOrder'] : $classData['classorder']; ?>" 
                       min="1" required>
                <span class="formInputHelper">Lower numbers appear first</span>
            </div>
            
            <div class="formButtonContainer">
                <button type="submit" name="updateClass" class="formButtonPrimary">Update Class</button>
                <a href="listAllClassesPage.php" class="formButtonSecondary">Cancel</a>
            </div>
            
        </form>
        
    </div>
</div>

<script>
document.getElementById('classColourPicker').addEventListener('change', function() {
    document.getElementById('classColourText').value = this.value;
});
document.getElementById('classColourText').addEventListener('input', function() {
    var value = this.value.replace('#', '');
    if (value.length === 6 && /^[0-9A-Fa-f]{6}$/.test(value)) {
        document.getElementById('classColourPicker').value = '#' + value;
    }
    // Keep the hash in the text input
    if (this.value.length > 0 && this.value[0] !== '#') {
        this.value = '#' + this.value;
    }
});
</script>

<?php
insertPageFooter($thisPageID);
?>