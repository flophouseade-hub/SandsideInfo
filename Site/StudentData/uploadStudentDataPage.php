<?php
$thisPageID = 85; 
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Restrict to fullAdmin only
if (!isset($_SESSION['currentUserLogOnStatus']) || $_SESSION['currentUserLogOnStatus'] !== 'fullAdmin') {
    header("Location: ../Pages/accessDeniedPage.php");
    exit();
}

$feedbackMessage = "";
$previewData = null;
$uploadStats = null;

//------------------------------------------------------------------------------------------------------
// Handle final import confirmation
//------------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmImport'])) {
    $csvData = json_decode($_POST['csvData'], true);
    
    if (!$csvData) {
        $feedbackMessage = "<p style='color: red;'>Error: No data to import.</p>";
    } else {
        $connection = connectToDatabase();
        if (!$connection) {
            die("ERROR: Could not connect to database");
        }
        
        // Start transaction
        mysqli_begin_transaction($connection);
        
        try {
            // Step 1: Delete all existing students_tb
            $deletestudents_tb = "DELETE FROM students_tb";
            mysqli_query($connection, $deletestudents_tb);
            $students_tbDeleted = mysqli_affected_rows($connection);
            
            // Step 2: Get all existing classes
            $existingClasses = array();
            $classQuery = "SELECT ClassID, classname FROM classes";
            $classResult = mysqli_query($connection, $classQuery);
            while ($row = mysqli_fetch_assoc($classResult)) {
                $existingClasses[$row['classname']] = $row['ClassID'];
            }
            
            // Step 3: Collect all unique classes from CSV
            $csvClasses = array();
            foreach ($csvData as $row) {
                $className = trim($row['Class']);
                if (!empty($className)) {
                    $csvClasses[$className] = true;
                }
            }
            
            // Step 4: Create new classes that don't exist
            $classesCreated = 0;
            foreach ($csvClasses as $className => $dummy) {
                if (!isset($existingClasses[$className])) {
                    $insertClass = "INSERT INTO classes (classname, colour, classOrder) VALUES (?, '#FFFFFF', 999)";
                    $stmt = $connection->prepare($insertClass);
                    $stmt->bind_param("s", $className);
                    $stmt->execute();
                    $existingClasses[$className] = $connection->insert_id;
                    $classesCreated++;
                    $stmt->close();
                }
            }
            
            // Step 5: Delete classes that are no longer needed
            $classesToDelete = array();
            foreach ($existingClasses as $className => $classID) {
                if (!isset($csvClasses[$className])) {
                    $classesToDelete[] = $classID;
                }
            }
            
            $classesDeleted = 0;
            if (count($classesToDelete) > 0) {
                $deleteClasses = "DELETE FROM classes WHERE ClassID IN (" . implode(',', $classesToDelete) . ")";
                mysqli_query($connection, $deleteClasses);
                $classesDeleted = mysqli_affected_rows($connection);
            }
            
            // Step 6: Insert all students_tb
            $insertStmt = $connection->prepare("INSERT INTO students_tb (FirstName, LastName, UPN, Sex, ClassID) VALUES (?, ?, ?, ?, ?)");
            $students_tbInserted = 0;
            $errors = array();
            
            foreach ($csvData as $index => $row) {
                $firstName = trim($row['FirstName']);
                $lastName = trim($row['LastName']);
                $upn = trim($row['UPN']);
                $sex = trim($row['Sex']);
                $className = trim($row['Class']);
                
                if (empty($className)) {
                    $errors[] = "Row " . ($index + 2) . ": No class specified";
                    continue;
                }
                
                $classID = $existingClasses[$className];
                
                $insertStmt->bind_param("ssssi", $firstName, $lastName, $upn, $sex, $classID);
                if ($insertStmt->execute()) {
                    $students_tbInserted++;
                } else {
                    $errors[] = "Row " . ($index + 2) . ": " . $insertStmt->error;
                }
            }
            
            $insertStmt->close();
            
            // Commit transaction
            mysqli_commit($connection);
            
            $uploadStats = array(
                'students_tbDeleted' => $students_tbDeleted,
                'students_tbInserted' => $students_tbInserted,
                'classesCreated' => $classesCreated,
                'classesDeleted' => $classesDeleted,
                'errors' => $errors
            );
            
            $feedbackMessage = "<p style='color: green;'><strong>Import completed successfully!</strong></p>";
            
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $feedbackMessage = "<p style='color: red;'>Error during import: " . $e->getMessage() . "</p>";
        }
        
        $connection->close();
    }
}

//------------------------------------------------------------------------------------------------------
// Handle file upload and preview
//------------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csvFile']) && !isset($_POST['confirmImport'])) {
    $file = $_FILES['csvFile'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $feedbackMessage = "<p style='color: red;'>Error uploading file.</p>";
    } else {
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($fileExtension !== 'csv') {
            $feedbackMessage = "<p style='color: red;'>Only CSV files are allowed.</p>";
        } else {
            $csvData = array();
            $duplicateUPNs = array();
            $upnTracker = array();
            
            if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
                $header = fgetcsv($handle);
                
                // Expected headers
                $expectedHeaders = array('First Name', 'Last Name', 'UPN', 'Sex', 'Registration form(s) this academic year');
                
                $rowNum = 1;
                while (($data = fgetcsv($handle)) !== false) {
                    $rowNum++;
                    
                    if (count($data) < 5) continue;
                    
                    $firstName = trim($data[0]);
                    $lastName = trim($data[1]);
                    $upn = trim($data[2]);
                    $sex = trim($data[3]);
                    $class = trim($data[4]);
                    
                    // Check for duplicate UPN
                    if (!empty($upn)) {
                        if (isset($upnTracker[$upn])) {
                            $duplicateUPNs[] = array(
                                'upn' => $upn,
                                'rows' => array($upnTracker[$upn], $rowNum),
                                'names' => array($upnTracker[$upn . '_name'], "$firstName $lastName")
                            );
                        } else {
                            $upnTracker[$upn] = $rowNum;
                            $upnTracker[$upn . '_name'] = "$firstName $lastName";
                        }
                    }
                    
                    $csvData[] = array(
                        'FirstName' => $firstName,
                        'LastName' => $lastName,
                        'UPN' => $upn,
                        'Sex' => $sex,
                        'Class' => $class
                    );
                }
                fclose($handle);
                
                $previewData = array(
                    'data' => $csvData,
                    'duplicates' => $duplicateUPNs,
                    'total' => count($csvData)
                );
            }
        }
    }
}

// Display the page
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

insertPageTitleAndClass("Upload Student Data", "blockMenuPageTitle", $thisPageID);

print("<div class='formPageWrapper'>");

// Display feedback message
if (!empty($feedbackMessage)) {
    print("<div class='formMessageBox'>$feedbackMessage</div>");
}

// Display upload stats if available
if ($uploadStats) {
    print("<div style='background-color: #d4edda; padding: 20px; margin: 20px 0; border-radius: 4px; border: 1px solid #c3e6cb;'>");
    print("<h3>Import Summary</h3>");
    print("<ul>");
    print("<li><strong>students_tb deleted:</strong> " . $uploadStats['students_tbDeleted'] . "</li>");
    print("<li><strong>students_tb inserted:</strong> " . $uploadStats['students_tbInserted'] . "</li>");
    print("<li><strong>Classes created:</strong> " . $uploadStats['classesCreated'] . "</li>");
    print("<li><strong>Classes deleted:</strong> " . $uploadStats['classesDeleted'] . "</li>");
    print("</ul>");
    
    if (count($uploadStats['errors']) > 0) {
        print("<h4 style='color: #721c24;'>Errors:</h4>");
        print("<ul style='color: #721c24;'>");
        foreach ($uploadStats['errors'] as $error) {
            print("<li>$error</li>");
        }
        print("</ul>");
    }
    
    print("<div style='display: flex; gap: 10px; margin-top: 15px;'>");
    print("<a href='classListPage.php' class='formButtonPrimary'>View Class Lists</a>");
    print("<a href='managestudents_tbPage.php' class='formButtonSecondary'>Manage students_tb</a>");
    print("<a href='manageClassesPage.php' class='formButtonSecondary'>Manage Classes</a>");
    print("</div>");
    print("</div>");
}

// Show preview if data was uploaded
if ($previewData) {
    print("<div class='formInfoBox' style='background-color: #fff3cd; border-color: #ffc107;'>");
    print("<h3>Preview: " . $previewData['total'] . " students_tb found</h3>");
    
    if (count($previewData['duplicates']) > 0) {
        print("<div style='background-color: #f8d7da; padding: 15px; margin: 15px 0; border-radius: 4px; border: 1px solid #f5c6cb;'>");
        print("<h4 style='color: #721c24;'>⚠ Duplicate UPNs Found:</h4>");
        print("<ul style='color: #721c24;'>");
        foreach ($previewData['duplicates'] as $dup) {
            print("<li>UPN <strong>" . htmlspecialchars($dup['upn'], ENT_QUOTES, 'UTF-8') . "</strong> appears on rows " . 
                  implode(', ', $dup['rows']) . " (" . htmlspecialchars(implode(' and ', $dup['names']), ENT_QUOTES, 'UTF-8') . ")</li>");
        }
        print("</ul>");
        print("<p><strong>Warning:</strong> All instances will be imported. Please review your data.</p>");
        print("</div>");
    }
    
    print("<p><strong>⚠ WARNING:</strong> Clicking 'Confirm Import' will:</p>");
    print("<ul>");
    print("<li>Delete ALL existing students_tb from the database</li>");
    print("<li>Create any new classes found in the CSV</li>");
    print("<li>Delete any classes not in the CSV</li>");
    print("<li>Import all " . $previewData['total'] . " students_tb from the CSV</li>");
    print("</ul>");
    print("</div>");
    
    // Display preview table
    print("<div style='max-height: 400px; overflow-y: auto; margin: 20px 0;'>");
    print("<table class='sectionsTable' style='width: 100%;'>");
    print("<thead><tr>");
    print("<th>First Name</th><th>Last Name</th><th>UPN</th><th>Sex</th><th>Class</th>");
    print("</tr></thead>");
    print("<tbody>");
    
    $displayLimit = 50;
    foreach (array_slice($previewData['data'], 0, $displayLimit) as $row) {
        print("<tr>");
        print("<td>" . htmlspecialchars($row['FirstName'], ENT_QUOTES, 'UTF-8') . "</td>");
        print("<td>" . htmlspecialchars($row['LastName'], ENT_QUOTES, 'UTF-8') . "</td>");
        print("<td>" . htmlspecialchars($row['UPN'], ENT_QUOTES, 'UTF-8') . "</td>");
        print("<td>" . htmlspecialchars($row['Sex'], ENT_QUOTES, 'UTF-8') . "</td>");
        print("<td>" . htmlspecialchars($row['Class'], ENT_QUOTES, 'UTF-8') . "</td>");
        print("</tr>");
    }
    
    if (count($previewData['data']) > $displayLimit) {
        print("<tr><td colspan='5' style='text-align: center; font-style: italic; color: #666;'>");
        print("... and " . (count($previewData['data']) - $displayLimit) . " more rows");
        print("</td></tr>");
    }
    
    print("</tbody></table>");
    print("</div>");
    
    // Confirm import form
    print("<form method='POST' action='uploadStudentDataPage.php'>");
    print("<input type='hidden' name='csvData' value='" . htmlspecialchars(json_encode($previewData['data']), ENT_QUOTES, 'UTF-8') . "'>");
    print("<div class='formButtonContainer'>");
    print("<button type='submit' name='confirmImport' class='formButtonPrimary' style='background-color: #d32f2f;' onclick=\"return confirm('This will DELETE all existing students_tb and classes. Are you absolutely sure?');\">Confirm Import</button>");
    print("<a href='uploadStudentDataPage.php' class='formButtonSecondary'>Cancel</a>");
    print("</div>");
    print("</form>");
    
} else {
    // Show upload form
    print("<div class='formInfoBox'>");
    print("<h3>Upload Student CSV File</h3>");
    print("<p>Upload a CSV file with student data to replace all existing students_tb in the database.</p>");
    print("<p><strong>Required CSV columns (in this order):</strong></p>");
    print("<ol>");
    print("<li>First Name</li>");
    print("<li>Last Name</li>");
    print("<li>UPN (Unique Pupil Number)</li>");
    print("<li>Sex</li>");
    print("<li>Registration form(s) this academic year (Class name)</li>");
    print("</ol>");
    print("</div>");
    
    print("<form method='POST' action='uploadStudentDataPage.php' enctype='multipart/form-data'>");
    print("<div class='formContainer'>");
    print("<div class='formField'>");
    print("<label>Select CSV File</label>");
    print("<input type='file' name='csvFile' accept='.csv' class='formInput' required>");
    print("<span class='formInputHelper'>Only CSV files are accepted</span>");
    print("</div>");
    
    print("<div class='formButtonContainer'>");
    print("<button type='submit' class='formButtonPrimary'>Upload and Preview</button>");
    print("<a href='managestudents_tbPage.php' class='formButtonSecondary'>Manage students_tb</a>");
    print("<a href='manageClassesPage.php' class='formButtonSecondary'>Manage Classes</a>");
    print("</div>");
    print("</div>");
    print("</form>");
}

print("</div>"); // Close formPageWrapper

insertPageFooter($thisPageID);
?>
