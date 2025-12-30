<?php
$thisPageID = 70;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Check access level - only pageEditor and fullAdmin can assign courses
if (accessLevelCheck("pageEditor") == false) {
	die("Access denied. You must be a page editor or administrator to assign courses.");
}

// Initialize variables
$courseForThisPageID = 0;
$courseName = "";
$courseDescription = "";
$courseColour = "";
$feedbackMessage = "";
$inputError = false;

// Get the course ID from the URL parameter
$courseForThisPageID = $_GET["courseID"] ?? 0;

if (!validatePositiveInteger($courseForThisPageID)) {
	die("Invalid Course ID. Please contact the administrator.");
}

// -----------------------------------------------
// Handle form submission for assigning/unassigning course to users
// -----------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["assignCourseButton"])) {
	$selectedUsers = $_POST["selectedUsers"] ?? [];

	// Reset POST variables
	$_POST = [];

	// Connect to database
	$connection = connectToDatabase();
	if (!$connection) {
		die("ERROR: Could not connect to database: " . mysqli_connect_error());
	}

	mysqli_begin_transaction($connection);

	try {
		// Get all tasks for this course
		$tasksQuery = "SELECT CTTaskID FROM Coursetasks_tb WHERE CTCourseID = ? ORDER BY CTTaskOrder";
		$stmtTasks = $connection->prepare($tasksQuery);
		$stmtTasks->bind_param("i", $courseForThisPageID);
		$stmtTasks->execute();
		$resultTasks = $stmtTasks->get_result();

		$taskIDs = [];
		while ($rowTask = $resultTasks->fetch_assoc()) {
			$taskIDs[] = $rowTask["CTTaskID"];
		}
		$stmtTasks->close();

		if (count($taskIDs) === 0) {
			throw new Exception("This course has no tasks assigned. Please add tasks before assigning to users.");
		}

		// Get all users who currently have this course
		$placeholders = implode(",", array_fill(0, count($taskIDs), "?"));
		$queryCurrentUsers = "SELECT DISTINCT UTUsersID FROM user_tasks_tb WHERE UserSetTaskID IN ($placeholders) AND UserSetCourseID = ?";
		$stmtCurrentUsers = $connection->prepare($queryCurrentUsers);
		$types = str_repeat("i", count($taskIDs)) . "i";
		$params = array_merge($taskIDs, [$courseForThisPageID]);
		$stmtCurrentUsers->bind_param($types, ...$params);
		$stmtCurrentUsers->execute();
		$resultCurrentUsers = $stmtCurrentUsers->get_result();

		$currentUsersWithCourse = [];
		while ($rowCurrentUser = $resultCurrentUsers->fetch_assoc()) {
			$currentUsersWithCourse[] = $rowCurrentUser["UTUsersID"];
		}
		$stmtCurrentUsers->close();

		// Determine who to assign and who to unassign
		$usersToAssign = array_diff($selectedUsers, $currentUsersWithCourse);
		$usersToUnassign = array_diff($currentUsersWithCourse, $selectedUsers);

		$assignedCount = 0;
		$unassignedCount = 0;
		$skippedCount = 0;

		// Get current timestamp for new assignments
		$currentTimestamp = date("Y-m-d H:i:s");

		// ASSIGN: Add tasks for newly selected users
		if (count($usersToAssign) > 0) {
			$insertQuery =
				"INSERT INTO user_tasks_tb (UTUsersID, UserSetTaskID, UserSetCourseID, UserSetCourseDate, UserSetTaskComplete) VALUES (?, ?, ?, ?, 0)";
			$stmtInsert = $connection->prepare($insertQuery);

			if (!$stmtInsert) {
				throw new Exception("Failed to prepare insert statement: " . $connection->error);
			}

			foreach ($usersToAssign as $userId) {
				if (!validatePositiveInteger($userId)) {
					continue;
				}

				foreach ($taskIDs as $taskId) {
					// Check if this user-task combination already exists
					$checkQuery =
						"SELECT COUNT(*) as RecordCount FROM user_tasks_tb WHERE UTUsersID = ? AND UserSetTaskID = ?";
					$stmtCheck = $connection->prepare($checkQuery);
					$stmtCheck->bind_param("ii", $userId, $taskId);
					$stmtCheck->execute();
					$resultCheck = $stmtCheck->get_result();
					$rowCheck = $resultCheck->fetch_assoc();
					$stmtCheck->close();

					if ($rowCheck["RecordCount"] == 0) {
						$stmtInsert->bind_param("iiis", $userId, $taskId, $courseForThisPageID, $currentTimestamp);
						if ($stmtInsert->execute()) {
							$assignedCount++;
						}
					} else {
						$skippedCount++;
					}
				}
			}

			$stmtInsert->close();
		}

		// UNASSIGN: Remove tasks for unchecked users
		if (count($usersToUnassign) > 0) {
			$deleteQuery = "DELETE FROM user_tasks_tb WHERE UTUsersID = ? AND UserSetCourseID = ? AND UserSetTaskID IN ($placeholders)";
			$stmtDelete = $connection->prepare($deleteQuery);

			if (!$stmtDelete) {
				throw new Exception("Failed to prepare delete statement: " . $connection->error);
			}

			foreach ($usersToUnassign as $userId) {
				if (!validatePositiveInteger($userId)) {
					continue;
				}

				// Build parameters: userId, courseId, then all taskIds
				$deleteParams = array_merge([$userId, $courseForThisPageID], $taskIDs);
				$deleteTypes = "ii" . str_repeat("i", count($taskIDs));
				$stmtDelete->bind_param($deleteTypes, ...$deleteParams);

				if ($stmtDelete->execute()) {
					$unassignedCount += $stmtDelete->affected_rows;
				}
			}

			$stmtDelete->close();
		}

		mysqli_commit($connection);

		// Build feedback message
		$feedbackParts = [];

		if ($assignedCount > 0) {
			$userCount = count($usersToAssign);
			$feedbackParts[] = "<p style=\"color: green; font-weight: bold;\">✓ Assigned course to $userCount user(s). Created $assignedCount task assignment(s).</p>";
		}

		if ($unassignedCount > 0) {
			$userCount = count($usersToUnassign);
			$feedbackParts[] = "<p style=\"color: orange; font-weight: bold;\">✗ Unassigned course from $userCount user(s). Removed $unassignedCount task assignment(s).</p>";
		}

		if ($skippedCount > 0) {
			$feedbackParts[] = "<p style=\"color: #666;\">Note: $skippedCount task(s) were already assigned and were skipped.</p>";
		}

		if (count($feedbackParts) > 0) {
			$feedbackMessage = implode("", $feedbackParts);
		} else {
			$feedbackMessage = "<p style=\"color: #666; font-weight: bold;\">No changes were made.</p>";
		}
	} catch (Exception $e) {
		mysqli_rollback($connection);
		$feedbackMessage =
			"<p style=\"color: red; font-weight: bold;\">Error: " .
			htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8") .
			"</p>";
		$inputError = true;
	}

	$connection->close();
}

// -----------------------------------------------
// Retrieve course and task data
// -----------------------------------------------
// Connect to database and get course details
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Get course details
$queryCourse = "SELECT CourseName, CourseDescription, CourseColour FROM courses_tb WHERE CourseID = ?";
$stmtCourse = $connection->prepare($queryCourse);
$stmtCourse->bind_param("i", $courseForThisPageID);
$stmtCourse->execute();
$resultCourse = $stmtCourse->get_result();

if ($resultCourse->num_rows === 0) {
	$stmtCourse->close();
	$connection->close();
	die("Course not found. Please contact the administrator.");
}

$rowCourse = $resultCourse->fetch_assoc();
$courseName = $rowCourse["CourseName"];
$courseDescription = $rowCourse["CourseDescription"];
$courseColour = $rowCourse["CourseColour"];

$stmtCourse->close();

// Get all tasks associated with this course from Coursetasks_tb, ordered by CTTaskOrder
$queryTasks = "SELECT CTTaskID, CTTaskOrder FROM Coursetasks_tb WHERE CTCourseID = ? ORDER BY CTTaskOrder, CTTaskID";
$stmtTasks = $connection->prepare($queryTasks);
$stmtTasks->bind_param("i", $courseForThisPageID);
$stmtTasks->execute();
$resultTasks = $stmtTasks->get_result();

// Collect task IDs with their order
$taskIDs = [];
$taskOrders = [];
while ($rowTask = $resultTasks->fetch_assoc()) {
	$taskIDs[] = $rowTask["CTTaskID"];
	$taskOrders[$rowTask["CTTaskID"]] = $rowTask["CTTaskOrder"];
}

$stmtTasks->close();

// Get full task details for each task ID
$tasksArray = [];
if (count($taskIDs) > 0) {
	// Build IN clause for query
	$placeholders = implode(",", array_fill(0, count($taskIDs), "?"));
	$queryTaskDetails = "SELECT TaskID, TaskName, TaskDescription, TaskColour FROM tasks_tb WHERE TaskID IN ($placeholders)";
	$stmtTaskDetails = $connection->prepare($queryTaskDetails);

	// Bind parameters dynamically
	$types = str_repeat("i", count($taskIDs));
	$stmtTaskDetails->bind_param($types, ...$taskIDs);
	$stmtTaskDetails->execute();
	$resultTaskDetails = $stmtTaskDetails->get_result();

	// Create associative array for easy lookup
	$taskDetailsMap = [];
	while ($rowTaskDetails = $resultTaskDetails->fetch_assoc()) {
		$taskDetailsMap[$rowTaskDetails["TaskID"]] = $rowTaskDetails;
	}

	// Build tasks array in the correct order
	foreach ($taskIDs as $taskID) {
		if (isset($taskDetailsMap[$taskID])) {
			$tasksArray[] = [
				"TaskID" => $taskID,
				"TaskName" => $taskDetailsMap[$taskID]["TaskName"],
				"TaskDescription" => $taskDetailsMap[$taskID]["TaskDescription"],
				"TaskColour" => $taskDetailsMap[$taskID]["TaskColour"],
				"TaskOrder" => $taskOrders[$taskID],
			];
		}
	}

	$stmtTaskDetails->close();
}

// Get all users from the database
$queryUsers =
	"SELECT UsersID, FirstName, LastName, Email, SchoolStatus FROM users_tb ORDER BY SchoolStatus, FirstName, LastName";
$resultUsers = mysqli_query($connection, $queryUsers);

$usersArray = [];
if ($resultUsers) {
	while ($rowUser = mysqli_fetch_assoc($resultUsers)) {
		$usersArray[] = [
			"UserID" => $rowUser["UsersID"],
			"UserFirstName" => $rowUser["FirstName"],
			"UserLastName" => $rowUser["LastName"],
			"UserEmail" => $rowUser["Email"],
			"UserSchoolStatus" => $rowUser["SchoolStatus"],
		];
	}
}

// Check which users already have tasks from this course assigned
$usersWithCourse = [];
$userTaskCompletionStatus = []; // Track completion status per user

if (count($taskIDs) > 0 && count($usersArray) > 0) {
	// First, get all users who have ANY task from this course
	$placeholders = implode(",", array_fill(0, count($taskIDs), "?"));
	$queryAssigned = "SELECT DISTINCT UTUsersID FROM user_tasks_tb WHERE UserSetTaskID IN ($placeholders) AND UserSetCourseID = ?";
	$stmtAssigned = $connection->prepare($queryAssigned);

	// Bind task IDs and course ID
	$types = str_repeat("i", count($taskIDs)) . "i";
	$params = array_merge($taskIDs, [$courseForThisPageID]);
	$stmtAssigned->bind_param($types, ...$params);
	$stmtAssigned->execute();
	$resultAssigned = $stmtAssigned->get_result();

	while ($rowAssigned = $resultAssigned->fetch_assoc()) {
		$usersWithCourse[] = $rowAssigned["UTUsersID"];
	}

	$stmtAssigned->close();

	// For each user with the course, get completion details
	if (count($usersWithCourse) > 0) {
		foreach ($usersWithCourse as $userId) {
			$queryCompletion = "SELECT 
                            COUNT(*) as TotalTasks, 
                            SUM(CASE WHEN UserSetTaskComplete = 1 THEN 1 ELSE 0 END) as CompletedTasks 
                          FROM user_tasks_tb 
                          WHERE UTUsersID = ? 
                            AND UserSetCourseID = ? 
                            AND UserSetTaskID IN ($placeholders)";
			$stmtCompletion = $connection->prepare($queryCompletion);

			// Build parameters: userId, courseId, then all taskIds
			$completionParams = array_merge([$userId, $courseForThisPageID], $taskIDs);
			$completionTypes = "ii" . str_repeat("i", count($taskIDs));
			$stmtCompletion->bind_param($completionTypes, ...$completionParams);
			$stmtCompletion->execute();
			$resultCompletion = $stmtCompletion->get_result();
			$rowCompletion = $resultCompletion->fetch_assoc();

			$userTaskCompletionStatus[$userId] = [
				"total" => intval($rowCompletion["TotalTasks"]),
				"completed" => intval($rowCompletion["CompletedTasks"]),
			];

			$stmtCompletion->close();
		}
	}
}

$connection->close();

// Get the page details for this page from the array
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "Update Users on this Course";

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the form formatting CSS
print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

// Add color manipulation JavaScript functions
print generateColorManipulationJS();

// Sanitize values for display
$courseNameSafe = htmlspecialchars($courseName, ENT_QUOTES, "UTF-8");
$courseDescriptionSafe = htmlspecialchars($courseDescription, ENT_QUOTES, "UTF-8");
$courseColourSafe = htmlspecialchars($courseColour, ENT_QUOTES, "UTF-8");

// Set default colour to mid-grey if empty
if (empty($courseColourSafe)) {
	$courseColourSafe = "#808080";
}

// Add custom styling for course title with colored background
$courseColourForDisplay = $courseColourSafe;
print "
<style>
.courseTitleBlock {
    margin: 20px 0;
    padding: 0;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.courseTitleContent {
    display: flex;
    align-items: stretch;
    min-height: 60px;
}

.courseTitleAccent {
    width: 6px;
    flex-shrink: 0;
}

.courseTitleText {
    flex: 1;
    padding: 15px 20px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.courseTitleText h2 {
    margin: 0 0 5px 0;
    font-size: 24px;
    font-weight: 600;
}

.courseTitleText p {
    margin: 0;
    font-size: 14px;
    opacity: 0.8;
}

.taskTitleBlock {
    margin: 10px 0;
    padding: 0;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.taskTitleContent {
    display: flex;
    align-items: center;
    min-height: 40px;
}

.taskTitleAccent {
    width: 4px;
    flex-shrink: 0;
    min-height: 40px;
}

.taskTitleText {
    flex: 1;
    padding: 10px 15px;
    font-size: 16px;
    font-weight: 500;
}
</style>

<script>
// Set course color and apply to title block
var courseColor = '$courseColourForDisplay';
var lightBg = getLighterColor(courseColor, 85);
var textColor = getContrastColor(lightBg);

document.addEventListener('DOMContentLoaded', function() {
    var titleBlock = document.querySelector('.courseTitleBlock');
    if (titleBlock) {
        titleBlock.style.backgroundColor = lightBg;
    }
    
    var accent = document.querySelector('.courseTitleAccent');
    if (accent) {
        accent.style.backgroundColor = courseColor;
    }
    
    var titleText = document.querySelector('.courseTitleText');
    if (titleText) {
        titleText.style.color = textColor;
    }
    
    // Apply colors to task blocks
    var taskItems = document.querySelectorAll('.task-card');
    taskItems.forEach(function(item) {
        var taskColor = item.getAttribute('data-task-color');
        if (taskColor) {
            var taskLightBg = getLighterColor(taskColor, 85);
            var taskTextColor = getContrastColor(taskLightBg);
            
            var taskContent = item.querySelector('.task-content');
            if (taskContent) {
                taskContent.style.backgroundColor = taskLightBg;
                taskContent.style.color = taskTextColor;
            }
            
            var taskAccent = item.querySelector('.task-accent');
            if (taskAccent) {
                taskAccent.style.backgroundColor = taskColor;
            }
            
            // Update text colors for better contrast
            var heading = item.querySelector('h4');
            if (heading) {
                heading.style.color = taskTextColor;
            }
            
            var para = item.querySelector('p');
            if (para) {
                para.style.color = taskTextColor;
                para.style.opacity = '0.8';
            }
            
            var spans = item.querySelectorAll('span');
            spans.forEach(function(span) {
                span.style.color = taskTextColor;
                span.style.opacity = '0.7';
            });
        }
    });
});
</script>
";

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display feedback message if exists
if (!empty($feedbackMessage)) {
	print "<div class=\"formFeedback\" style=\"max-width: 900px; margin: 0 auto;\">$feedbackMessage</div>";
}

print "<div class=\"formPageWrapper\" style=\"max-width: 900px; margin: 0 auto;\">";

// Custom course title block
print "
<div class=\"courseTitleBlock\">
    <div class=\"courseTitleContent\">
        <div class=\"courseTitleAccent\"></div>
        <div class=\"courseTitleText\">
            <h2>$courseNameSafe</h2>
            <p>$courseDescriptionSafe</p>
        </div>
    </div>
</div>
";

print "<div class=\"formInfoBox\">
    <p>Select users below to assign this course and all its tasks. Users who already have the course will be re-checked automatically.</p>
</div>";

// Build tasks list
$tasksListHTML =
	"
<div class=\"formContainer\">
  <h3>Tasks in this Course (" .
	count($tasksArray) .
	")</h3>";

if (count($tasksArray) > 0) {
	$tasksListHTML .=
		"<p style=\"color: #666; font-size: 14px; margin-bottom: 15px;\"><em>These tasks will be assigned to all selected users:</em></p>";
	$tasksListHTML .= "<div style=\"margin-top: 15px;\">";

	foreach ($tasksArray as $task) {
		$taskID = $task["TaskID"];
		$taskName = htmlspecialchars($task["TaskName"], ENT_QUOTES, "UTF-8");
		$taskDescription = htmlspecialchars($task["TaskDescription"], ENT_QUOTES, "UTF-8");
		$taskColour = htmlspecialchars($task["TaskColour"], ENT_QUOTES, "UTF-8");
		$taskOrder = $task["TaskOrder"];

		// Set default task colour to mid-grey if empty
		if (empty($taskColour)) {
			$taskColour = "#808080";
		}

		$tasksListHTML .= "
    <div class=\"task-card\" data-task-color=\"$taskColour\" style=\"background-color: white; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 10px; padding: 4px;\">
      <div style=\"display: flex; align-items: stretch; border-radius: 2px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05);\">
        <div class=\"task-accent\" style=\"width: 4px; flex-shrink: 0; background-color: $taskColour;\"></div>
        <div class=\"task-content\" style=\"flex: 1; padding: 12px 15px; display: flex; align-items: center; gap: 15px;\">
          <div style=\"color: #999; font-size: 18px; flex-shrink: 0;\">$taskOrder.</div>
          <div style=\"flex: 1; min-width: 0;\">
            <h4 style=\"margin: 0 0 3px 0; font-size: 16px;\">$taskName <span style=\"font-size: 12px; font-weight: normal;\">(Task ID: $taskID)</span></h4>
            <p style=\"margin: 0; font-size: 13px;\">$taskDescription</p>
          </div>
        </div>
      </div>
    </div>";
	}

	$tasksListHTML .= "</div>";
} else {
	$tasksListHTML .= "<p style=\"padding: 20px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;\"><strong>Warning:</strong> This course has no tasks assigned. Please <a href=\"editCourseTasksPage.php?editCourseID=$courseForThisPageID\">add tasks</a> before assigning to users.</p>";
}

$tasksListHTML .= "</div>";

// Build users selection section
$usersListHTML = "
<div class=\"formContainer\">
  <h3>Select Users to Assign This Course</h3>
  <p style=\"color: #666; font-size: 14px; margin-bottom: 15px;\">Check the users you want to assign this course to. All tasks in the course will be assigned to each selected user.</p>
  
  <form action=\"assignCourseToUsersPage.php?courseID=$courseForThisPageID\" method=\"POST\">
    <div class=\"formField\">";

// Separate users into those with and without the course
$usersWithThisCourse = [];
$usersWithoutThisCourse = [];

foreach ($usersArray as $user) {
	if (in_array($user["UserID"], $usersWithCourse)) {
		$usersWithThisCourse[] = $user;
	} else {
		$usersWithoutThisCourse[] = $user;
	}
}

// Display users without the course FIRST
if (count($usersWithoutThisCourse) > 0) {
	$usersListHTML .=
		"
      <h4 style=\"margin-top: 0; margin-bottom: 10px; color: #333;\">Users Not Yet Assigned (" .
		count($usersWithoutThisCourse) .
		")</h4>
      <div style=\"max-height: 300px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background-color: white; border-radius: 4px; margin-bottom: 20px;\">";

	foreach ($usersWithoutThisCourse as $user) {
		$userID = $user["UserID"];
		$userFirstName = htmlspecialchars($user["UserFirstName"], ENT_QUOTES, "UTF-8");
		$userLastName = htmlspecialchars($user["UserLastName"], ENT_QUOTES, "UTF-8");
		$userSchoolStatus = htmlspecialchars($user["UserSchoolStatus"], ENT_QUOTES, "UTF-8");
		$userFullName = "$userFirstName $userLastName";

		$usersListHTML .= "
      <div style=\"padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px; background-color: #fafafa;\">
        <label style=\"display: flex; align-items: center; cursor: pointer;\">
          <input type=\"checkbox\" name=\"selectedUsers[]\" value=\"$userID\" style=\"margin-right: 10px; flex-shrink: 0;\">
          <strong style=\"color: #333; margin-right: 8px;\">$userFullName</strong>
          <span style=\"color: #666; font-size: 12px; margin-right: 8px;\">(ID: $userID)</span>
          <span style=\"color: #555; font-size: 13px;\">- $userSchoolStatus</span>
        </label>
      </div>";
	}

	$usersListHTML .= "</div>";
}

// Display users who already have the course SECOND
if (count($usersWithThisCourse) > 0) {
	$usersListHTML .=
		"
      <h4 style=\"margin-top: 20px; margin-bottom: 10px; color: #333;\">Users Already Assigned (" .
		count($usersWithThisCourse) .
		")</h4>
      <div style=\"max-height: 300px; overflow-y: auto; border: 1px solid #28a745; padding: 10px; background-color: #f8fff9; border-radius: 4px;\">";

	foreach ($usersWithThisCourse as $user) {
		$userID = $user["UserID"];
		$userFirstName = htmlspecialchars($user["UserFirstName"], ENT_QUOTES, "UTF-8");
		$userLastName = htmlspecialchars($user["UserLastName"], ENT_QUOTES, "UTF-8");
		$userSchoolStatus = htmlspecialchars($user["UserSchoolStatus"], ENT_QUOTES, "UTF-8");
		$userFullName = "$userFirstName $userLastName";

		// Check if completion info exists for this user
		if (isset($userTaskCompletionStatus[$userID])) {
			$completionInfo = $userTaskCompletionStatus[$userID];
			$totalTasks = $completionInfo["total"];
			$completedTasks = $completionInfo["completed"];
			$remainingTasks = $totalTasks - $completedTasks;
			$completionPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

			// Determine status display
			if ($completedTasks == $totalTasks) {
				$statusColor = "#28a745";
				$statusIcon = "✓";
				$statusText = "Completed";
				$progressBar = "<div style=\"background-color: #d4edda; height: 8px; border-radius: 4px; margin-top: 5px; overflow: hidden;\">
                          <div style=\"background-color: #28a745; height: 100%; width: 100%;\"></div>
                        </div>";
			} else {
				$statusColor = "#FF9800";
				$statusIcon = "◐";
				$statusText = "In Progress ($remainingTasks remaining)";
				$progressBar = "<div style=\"background-color: #fff3cd; height: 8px; border-radius: 4px; margin-top: 5px; overflow: hidden;\">
                          <div style=\"background-color: #FF9800; height: 100%; width: $completionPercentage%;\"></div>
                        </div>";
			}

			$usersListHTML .= "
        <div style=\"padding: 12px; border: 1px solid #d4edda; border-radius: 4px; margin-bottom: 10px; background-color: white;\">
          <div style=\"display: flex; align-items: start; justify-content: space-between;\">
            <div style=\"flex: 1;\">
              <div style=\"display: flex; align-items: center; margin-bottom: 5px;\">
                <label style=\"display: flex; align-items: center; cursor: pointer; flex: 1;\">
                  <input type=\"checkbox\" name=\"selectedUsers[]\" value=\"$userID\" checked style=\"margin-right: 10px; flex-shrink: 0;\">
                  <strong style=\"color: #333; font-size: 15px;\">$userFullName</strong>
                  <span style=\"color: #666; font-size: 12px; margin-left: 8px;\">(ID: $userID)</span>
                  <span style=\"color: #555; font-size: 13px; margin-left: 8px;\">- $userSchoolStatus</span>
                </label>
                <span style=\"color: $statusColor; font-size: 18px; margin-right: 10px;\">$statusIcon</span>
              </div>
              <div style=\"margin-left: 28px;\">
                <div style=\"display: flex; align-items: center; gap: 15px; font-size: 13px;\">
                  <span style=\"color: $statusColor; font-weight: bold;\">$statusText</span>
                  <span style=\"color: #666;\">$completedTasks / $totalTasks tasks complete ($completionPercentage%)</span>
                </div>
                $progressBar
              </div>
            </div>
          </div>
        </div>";
		}
	}

	$usersListHTML .= "</div>";
}

// If no users exist
if (count($usersArray) === 0) {
	$usersListHTML .=
		"<p style=\"padding: 20px; text-align: center; color: #666;\">No active users found in the system.</p>";
}

$usersListHTML .= "
    </div>";

// Only show button if there are tasks and users
if (count($tasksArray) > 0 && count($usersArray) > 0) {
	$usersListHTML .= "
    <div class=\"formButtonContainer\">
      <button type=\"submit\" name=\"assignCourseButton\" class=\"formButtonPrimary\">Update Users on this Course</button>
    </div>";
}

$usersListHTML .= "
  </form>
</div>";

// Navigation links
$navigationHTML = "
<div class=\"formContainer\">
  <h3>Course Actions</h3>
  <div class=\"formButtonContainer\">
    <a href=\"editCoursePage.php?editCourseID=$courseForThisPageID\" class=\"formButtonPrimary\">Edit Course Details</a>
    <a href=\"editCourseTasksPage.php?editCourseID=$courseForThisPageID\" class=\"formButtonPrimary\" style=\"background-color: #FF9800;\">Manage Course Tasks</a>
    <a href=\"listAllCoursesPage.php\" class=\"formButtonSecondary\">View All Courses</a>
  </div>
</div>";

$pageContent = $tasksListHTML . $usersListHTML . $navigationHTML;

print $pageContent;
print "</div>"; // Close formPageWrapper

insertPageFooter($thisPageID);
?>
