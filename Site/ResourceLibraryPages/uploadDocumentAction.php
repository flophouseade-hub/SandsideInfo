<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include('../phpCode/includeFunctions.php');

// Check if user is logged in and has upload permissions
if (!isset($_SESSION['currentUserID'])) {
    header("Location: ../LoginOrOut/loginPage.php");
    exit;
}

if ($_SESSION['currentUserLogOnStatus'] != "fullAdmin" && $_SESSION['currentUserLogOnStatus'] != "pageEditor") {
    $errorMsg = urlencode("You do not have permission to upload resources.");
    header("Location: ../Pages/accessDeniedPage.php?message=$errorMsg");
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['uploadResourceButton'])) {
    $errorMsg = urlencode("Invalid form submission");
    header("Location: uploadDocumentToSitePage.php?error=" . $errorMsg);
    exit;
}

// Get form data
$resourceName = $_POST['fvResourceName'] ?? "";
$resourceGroup = $_POST['fvResourceGroup'] ?? "";
$resourceDescription = $_POST['fvResourceDescription'] ?? "";
$resourceType = $_POST['fvResourceType'] ?? "";

// Validate inputs
$inputError = false;
$errorMessages = [];

if (empty($resourceName) || strlen($resourceName) < 3) {
    $inputError = true;
    $errorMessages[] = "Resource Name must be at least 3 characters long.";
}

if (empty($resourceType)) {
    $inputError = true;
    $errorMessages[] = "Resource Type is required.";
}

// Check if file was uploaded
if (!isset($_FILES['fileToUpload']) || $_FILES['fileToUpload']['error'] === UPLOAD_ERR_NO_FILE) {
    $inputError = true;
    $errorMessages[] = "Please select a file to upload.";
}

// If validation errors, redirect back with error messages
if ($inputError) {
    $errorMsg = urlencode(implode(" ", $errorMessages));
    header("Location: uploadDocumentToSitePage.php?error=" . $errorMsg);
    exit;
}

// File upload handling
$file = $_FILES['fileToUpload'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = urlencode("Error during upload. Error code: " . $file['error']);
    header("Location: uploadDocumentToSitePage.php?error=" . $errorMsg);
    exit;
}

// Allowed file extensions
$allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'];

// Max file size (20 MB)
$maxFileSize = 20 * 1024 * 1024;

// Validate file size
if ($file['size'] > $maxFileSize) {
    $errorMsg = urlencode("File is too large. Maximum allowed size is 20 MB.");
    header("Location: uploadDocumentToSitePage.php?error=" . $errorMsg);
    exit;
}

// Get file extension
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate file extension
if (!in_array($fileExtension, $allowedExtensions)) {
    $errorMsg = urlencode("Invalid file type. Allowed types: " . implode(", ", $allowedExtensions));
    header("Location: uploadDocumentToSitePage.php?error=" . $errorMsg);
    exit;
}

// Directory where uploaded files will be stored
$targetDir = "../uploadedDocuments/";

// Create directory if it doesn't exist
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// Generate a unique file name to avoid overwriting
$newFileName = uniqid("resource_", true) . "." . $fileExtension;
$targetFilePath = $targetDir . $newFileName;

// Move uploaded file to target directory
if (!move_uploaded_file($file['tmp_name'], $targetFilePath)) {
    $errorMsg = urlencode("Error saving the uploaded file.");
    header("Location: uploadDocumentToSitePage.php?error=" . $errorMsg);
    exit;
}

// File uploaded successfully - now insert into database
$connection = connectToDatabase();

// Create the URL path for the resource
$resourceURL = "uploadedDocuments/" . $newFileName;

$uploadBy= $_SESSION['currentUserFirstName'] . " " . $_SESSION['currentUserLastName'];

// LRLocal = 1 because file is stored locally on this server
$insertQuery = "INSERT INTO resource_library_tb (LRName, LRDescription, LRType, LRLink, LRGroup, LRLocal, LRUploadedWhen, LRUploadedBy) VALUES (?, ?, ?, ?, ?, 1, NOW(), ?)";
$stmt = $connection->prepare($insertQuery);
$stmt->bind_param("ssssss", 
    $resourceName,
    $resourceDescription,
    $resourceType,
    $resourceURL,
    $resourceGroup,
    $uploadBy
);

if ($stmt->execute()) {
    $newResourceID = $connection->insert_id;
    $stmt->close();
    $connection->close();
    
    // Redirect to edit page for the new resource
    header("Location: editAResourcePage.php?resourceID=" . $newResourceID);
    exit;
} else {
    $errorMsg = urlencode("Could not save resource to database: " . $stmt->error);
    $stmt->close();
    $connection->close();
    
    // Delete the uploaded file since database insert failed
    if (file_exists($targetFilePath)) {
        unlink($targetFilePath);
    }
    
    header("Location: ../Pages/errorLandingPage.php?error=database&message=" . $errorMsg);
    exit;
}
?>
