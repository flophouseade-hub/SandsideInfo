<?php
// Enable error reporting for debugging
  error_reporting(E_ALL);
  ini_set('display_errors', 1);

$target_dir = "../uploadedImages/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$uploadOk = 1;
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

// Check if image file is a actual image or fake image
if(isset($_POST["submit"])) {
  $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
  if($check !== false) {
    //echo "File is an image - " . $check["mime"] . ".";
    $uploadOk = 1;
  } else {
    echo "File is not an image.";
    $uploadOk = 0;
  }
}

// Check if file already exists
if (file_exists($target_file)) {
  echo "Sorry, file already exists.";
  $uploadOk = 0;
}

// Check file size
if ($_FILES["fileToUpload"]["size"] > 500000) {
  echo "Sorry, your file is too large.";
  $uploadOk = 0;
}

// Allow certain file formats
if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
&& $imageFileType != "gif" ) {
  echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
  $uploadOk = 0;
}

// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
  echo "Sorry, your file was not uploaded.";
// if everything is ok, try to upload file
} else {
  if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
    //echo "The file ". htmlspecialchars( basename( $_FILES["fileToUpload"]["name"])). " has been uploaded.";
  } else {
    echo "Sorry, there was an error uploading your file.";
  }
}
  
    $newImageCaption = $_POST['fvImageCaption'];
    $newImageDescription = $_POST['fvImageDescription']; 
    $linkToNewImage = "../uploadedImages/".basename( $_FILES["fileToUpload"]["name"]);

// Clear POST data to prevent resubmission on refresh
    $_POST = array(); 
// Connect to the database
    $connection = getDatabaseConnection();
    if (!$connection) {
        die("ERROR: Could not connect to the database: " . mysqli_connect_error());
    } 
// Update the image details in the database
    $updateQuery = "INSERT INTO image_library_tb (ImageLink, ImageCaption, ImageDescription) VALUES( ?,?,?)";
    $stmt = $connection->prepare($updateQuery); 
    $stmt->bind_param("sss", $linkToNewImage, $newImageCaption, $newImageDescription); 
   
// Execute the update and provide feedback in the form of an alert
    if ($stmt->execute()) {
        $lastImage = $connection->insert_id;
        if (!$lastImage) {
            $lastImage = count($_SESSION['image_library_tb']) + 1 ;
        }
        // Success message
        //echo "<script>alert('Image details updated successfully.'); window.location.href = 'editImageDetails.php?imageID=$imageForThisPageID';</script>";
        //exit;
    } else {
        // Error message
        echo "<script>alert('Error updating image details: " . $stmt->error . "'); window.location.href = 'editImageDetails.php?imageID=$imageForThisPageID';</script>";
        exit;
    }  
    $stmt->close();
    header("Location: editImageDetails.php?imageID=$lastImage");
    exit();

?>