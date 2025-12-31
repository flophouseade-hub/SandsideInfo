<?php
// AJAX handler for saving task order in Coursetasks_tb
// Don't include pageStarterPHP as it may output HTML
session_start();
include('../phpCode/includeFunctions.php');

// Set JSON response header first
header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!isset($_SESSION['currentUserID']) || accessLevelCheck("pageEditor") == false) {
  echo json_encode(['success' => false, 'error' => 'Access denied']);
  exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['courseId']) || !isset($data['taskOrder'])) {
  echo json_encode(['success' => false, 'error' => 'Invalid input data']);
  exit;
}

$courseId = $data['courseId'];
$taskOrder = $data['taskOrder'];

// Validate course ID
if (!validatePositiveInteger($courseId)) {
  echo json_encode(['success' => false, 'error' => 'Invalid course ID']);
  exit;
}

// Validate task order array
if (!is_array($taskOrder) || count($taskOrder) === 0) {
  echo json_encode(['success' => false, 'error' => 'Invalid task order data']);
  exit;
}

// Connect to database
$connection = connectToDatabase();
if (!$connection) {
  echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . mysqli_connect_error()]);
  exit;
}

// Begin transaction
mysqli_begin_transaction($connection);

try {
  // Prepare update statement - using prepared statements as per coding guidelines
  $updateQuery = "UPDATE Coursetasks_tb SET CTTaskOrder = ? WHERE CTCourseID = ? AND CTTaskID = ?";
  $stmt = $connection->prepare($updateQuery);
  
  if (!$stmt) {
    throw new Exception('Failed to prepare statement: ' . $connection->error);
  }
  
  $successCount = 0;
  
  foreach ($taskOrder as $task) {
    if (!isset($task['taskId']) || !isset($task['order'])) {
      throw new Exception('Invalid task data structure');
    }
    
    $taskId = $task['taskId'];
    $order = $task['order'];
    
    // Validate task ID and order
    if (!validatePositiveInteger($taskId) || !validatePositiveInteger($order)) {
      throw new Exception('Invalid task ID or order value');
    }
    
    $stmt->bind_param('iii', $order, $courseId, $taskId);
    
    if (!$stmt->execute()) {
      throw new Exception('Failed to update task order: ' . $stmt->error);
    }
    
    $successCount++;
  }
  
  $stmt->close();
  
  // Commit transaction
  mysqli_commit($connection);
  
  echo json_encode([
    'success' => true, 
    'message' => 'Task order updated successfully',
    'tasksUpdated' => $successCount
  ]);
  
} catch (Exception $e) {
  // Rollback transaction on error
  mysqli_rollback($connection);
  
  echo json_encode([
    'success' => false, 
    'error' => $e->getMessage()
  ]);
}

$connection->close();
?>