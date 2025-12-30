<?php

// Include validation functions
require_once('validationFunctions.php');
// Include section display functions
require_once('sectionDisplayFunctions.php');
require_once('boxesAndShadowsFunction.php');
require_once('centredSideAccentFunction.php');
require_once('columnFramesFunction.php');
require_once('alternateBoxesFunction.php');
require_once('spaceOnLeftFunction.php');
require_once('insertFunctions.php');


// Error logging function
function logError($errorType, $errorMessage, $fileName = '', $lineNumber = 0, $userId = null) {
    global $conn;
    
    // Log to file
    $logFile = __DIR__ . '/../error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $userInfo = $userId ? " [User: $userId]" : " [Not logged in]";
    $fileInfo = $fileName ? " in $fileName:$lineNumber" : "";
    $logEntry = "[$timestamp]$userInfo [$errorType]$fileInfo - $errorMessage\n";
    error_log($logEntry, 3, $logFile);
    
    // Optional: Log to database for easier querying
    if ($conn && !$conn->connect_error) {
        $stmt = $conn->prepare("INSERT INTO ErrorLog (ErrorTime, ErrorType, ErrorMessage, FileName, LineNumber, UserID) VALUES (NOW(), ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssii", $errorType, $errorMessage, $fileName, $lineNumber, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
    if ($errorType === 'ERROR' || $errorType === 'DATABASE') {
        mail('admin@yoursite.com', 'Site Error', $logEntry);
    }
}

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING'
    ];
    
    $type = $errorTypes[$errno] ?? 'UNKNOWN';
    $userId = $_SESSION['userID'] ?? null;
    
    logError($type, $errstr, $errfile, $errline, $userId);
    
    // Don't show errors to users in production
    return true;
}

set_error_handler("customErrorHandler");

function generateSelectOptionsFromArray($optionsArray, $selectedOption)
{
  $optionsString = "";
  foreach ($optionsArray as $optionValue => $optionLabel) {
    if ($optionValue == $selectedOption) {
      $optionsString .= "<option value=\"$optionValue\" selected=\"selected\">$optionLabel</option>\n";
    } else {
      $optionsString .= "<option value=\"$optionValue\">$optionLabel</option>\n";
    }
  }
  //$optionsString .= "</select>\n";
  return $optionsString;
}

function findRefStringPositionsInContentString($contentString, $startRefString, $endRefString)
{
  $startPos = strpos($contentString, $startRefString);
  if ($startPos === false) {
    return false;
  } else {
    $returnArray[0] = $startPos;
    $endPos = strpos($contentString, $endRefString, $startPos + strlen($startRefString));
    if ($endPos === false) {
      return false;
    } else {
      // Position of the start reference string
      $returnArray[0] = $startPos;
      // Lenght of the full reference including start and end strings
      $returnArray[1] = $endPos + strlen($endRefString) - $startPos;
      // The string between the start and end reference strings
      $returnArray[2] = substr($contentString, $startPos + strlen($startRefString), $endPos - strlen($startRefString) - $startPos);
      return $returnArray;
    }
  }
}

function printDebugMessage($message)
{
  echo "<!-- DEBUG: $message -->";
}

function connectToDatabase()
{
  $connection = getDatabaseConnection();
  if (!$connection) {
    $errorMsg = urlencode("Database connection failed: " . mysqli_connect_error());
    header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
    exit;
  }
  return $connection;
}

function printArrayForDebugging($arrayToPrint)
{
  echo "<pre>";
  print_r($arrayToPrint);
  echo "</pre>";
}

function sanitizeInput($inputString)
{
  $inputString = trim($inputString);
  $inputString = stripslashes($inputString);
  $inputString = htmlspecialchars($inputString, ENT_QUOTES, 'UTF-8');
  return $inputString;
}

/**
 * Log user login attempts to the LoginLog table
 * 
 * @param int $userID The user's ID
 * @param string $email The user's email address
 * @param string $status 'success' or 'failed'
 * @param string|null $failReason Optional reason for failed login
 * @return bool True if logged successfully, false otherwise
 */
function logUserLogin($userID, $email, $status = 'success', $failReason = null)
{
  $connection = connectToDatabase();
  
  // Get user's IP address
  $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
  
  // Get user agent string
  $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
  
  // Prepare the insert statement
  $stmt = $connection->prepare(
    'INSERT INTO LoginLog (UserID, Email, LoginTime, IPAddress, UserAgent, LoginStatus, FailReason) 
     VALUES (?, ?, NOW(), ?, ?, ?, ?)'
  );
  
  if ($stmt) {
    $stmt->bind_param('isssss', $userID, $email, $ipAddress, $userAgent, $status, $failReason);
    $success = $stmt->execute();
    $stmt->close();
    $connection->close();
    return $success;
  }
  
  $connection->close();
  return false;
}



function accessLevelCheck($requiredAccessLevel)
{
  // Check if the array exists otherwise will throw error in LogONPage
  if (isset($_SESSION['currentUserLogOnStatus'])) {
    $userAccessLevel = $_SESSION['currentUserLogOnStatus'];
  }
  if (!isset($userAccessLevel) || $userAccessLevel == "") {
    $userAccessLevel = "notLoggedIn";
  }
  $accessOK = false;
  switch ($requiredAccessLevel) {
    case "pageEditor":
      if ($userAccessLevel == "pageEditor" || $userAccessLevel == "fullAdmin") {
        $accessOK = true;
        break;
      }
      //$accessOK = true;
      break;
    case "staff":
      if ($userAccessLevel == "staff" || $userAccessLevel == "fullAdmin" || $userAccessLevel == "pageEditor") {
        $accessOK = true;
      }
      break;
    case "fullAdmin":
      if ($userAccessLevel == "fullAdmin") {
        $accessOK = true;
      }
      break;
    case "none":
      $accessOK = true;
      break;
    case "notLoggedIn":
      $accessOK = true;
      break;
    case "allUsers":
      $accessOK = true;
      break;
    default:
      $accessOK = false;
      $errorMsg = urlencode("Unknown access level: $requiredAccessLevel");
      header("Location: ../Pages/errorLandingPage.php?error=validation&message=$errorMsg");
      exit;
  }
  return $accessOK;
}


/* function insertImageIntoPageSection($locationLink, $imageHeight, $imageWidth, $caption, $roundedCorners)
{
  $styleString = "width: $imageWidth; height: $imageHeight; object-fit: cover;  margin-top: 10px  ";
  if ($roundedCorners == true) {
    $styleString = $styleString . "; border-radius: 50%;";
  }
  print("
    <div class=\"insertedImage\">
        <img src=\"$locationLink\"  alt=\"$caption\" style=\"$styleString\"/>
    <p>$caption</p>
</div>
    ");
  return;
} */
/* 
function insertImagefromDBIntoPageSection($libraryImageID, $imageHeight, $imageWidth, $roundedCorners)
{
  try {
    $con = connectToDatabase();
    $libraryImageID = -3; //errror checking
    
    // Validate image ID
    if (!is_numeric($libraryImageID) || $libraryImageID <= 0) {
      throw new Exception("Invalid image ID: $libraryImageID");
    }

    // Use prepared statement to prevent SQL injection
    $stmt = $con->prepare("SELECT ImageLink, ImageCaption, ImageAltText FROM ImageLibrary WHERE ImageID = ?");
    if (!$stmt) {
      throw new Exception("Prepare failed: " . $con->error);
    }
    
    $stmt->bind_param("i", $libraryImageID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
      throw new Exception("Image not found: ID $libraryImageID");
    }
    
    $row = $result->fetch_assoc();
    $locationLink = $row['ImageLink'];
    $caption = $row['ImageCaption'];
    $altText = $row['ImageAltText'];
    
    $stmt->close();
    mysqli_close($con);

    $styleString = "width: $imageWidth; height: $imageHeight; object-fit: cover;  margin-top: 10px  ";
    if ($roundedCorners == true) {
      $styleString = $styleString . "; border-radius: 50%;";
    }
    $returnString = "
    <div class=\"insertedImage\">
        <img src=\"$locationLink\"  alt=\"$altText\" style=\"$styleString\"/>
    <p>$caption</p>
</div>
    ";
    return $returnString;
    
  } catch (Exception $e) {
    // Log the error with context
    logError('DATABASE', $e->getMessage(), __FILE__, __LINE__, $_SESSION['userID'] ?? null);
    
    // Close connection if it exists
    if (isset($con) && $con) {
      mysqli_close($con);
    }
    
    // Redirect to error page
    $errorMsg = urlencode("Failed to load image: " . $e->getMessage());
    header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
    exit;
  }
}
 */
/* function insertImageStringByRefID($refID, $imageHeight, $imageWidth, $roundedCorners)
{
  $locationLink = $_SESSION['pagesOnSite'][$refID]['ImageLink'];
  $caption = $_SESSION['pagesOnSite'][$refID]['ImageDescription'];
  $altText = $_SESSION['pagesOnSite'][$refID]['ImageAltText'];

  $styleString = "width: $imageWidth; height: $imageHeight; object-fit: cover;  margin-top: 10px  ";
  if ($roundedCorners == true) {
    $styleString = $styleString . "; border-radius: 50%;";
  }
  $returnString = "
    <div class=\"insertedImage\">
        <img src=\"$locationLink\"  alt=\"$altText\" style=\"$styleString\"/>
    <p>$caption</p>
</div>";
  return $returnString;
} */

function replaceImageRefInContentString($sectionString, $editSectionID)
{
  $contentString = $sectionString;
  $refArray = findRefStringPositionsInContentString($contentString, "<imageL", "/>");
  // imageCode is the <image
  if ($refArray === false) {
    $errorMsg = urlencode("Image reference format error in section content");
    header("Location: ../Pages/errorLandingPage.php?error=validation&message=$errorMsg");
    exit;
  }
  $imageCodeStartPos = $refArray[0];
  $imageCodeLength = $refArray[1];
  $imageRefString = $refArray[2];
  //printArrayForDebugging($refArray);
  $imageCodeString = substr($contentString, $imageCodeStartPos, $imageCodeLength);
  $errorMessage = "";
  $imageRefArray = decodeImageCodeString($imageRefString);
  if (mb_strlen($imageRefString) < 6) {
    $errorMessage = "<p style=\"color: red;\"><strong>There is a problem with your image reference: $imageCodeString</strong></p>";
  } elseif (!isset($imageRefArray[2]) || mb_strlen($imageRefArray[2]) == 0 || !is_numeric($imageRefArray[2])) {
    $errorMessage = "<p style=\"color: red;\"><strong>There is a problem with your image reference - not enough parameters: $imageCodeString</strong></p>";
  } elseif (!isset($imageRefArray[3]) || mb_strlen($imageRefArray[3]) == 0 || !is_numeric($imageRefArray[3])) {
    $errorMessage = "<p style=\"color: red;\"><strong>There is no rounded corners value in your image reference: $imageCodeString</strong></p>";
  } else {
    // When there are no errors:
    $imageRefArray = decodeImageCodeString($imageRefString);
    if (isset($imageRefArray[0])) {
      $imageIDRef = $imageRefArray[0];
      $imageWidth = $imageRefArray[1];
      $imageHeight = $imageRefArray[2];
      $imageRounded = $imageRefArray[3];
      //echo("ImageIDRef: $imageIDRef Width: $imageWidth Height: $imageHeight Rounded: $imageRounded<br>");
    }
    if (isset($imageRounded) && $imageRounded > 0) {
      //$styleString = $styleString . " border-radius: $imageRounded%; ";
      $imageClassCircular = "Circular";
    } else {
      $imageClassCircular = "";
    }
    if (isset($_SESSION['imageLibrary'][$imageIDRef]) == false) {
      //die("Image ID $imageIDRef not found in image library");
      $caption = "Required Image Not Found";
      $locationLink = "../uploadedImages/Question Marks.jpg";
      $description = "Required Image not found";
    } else {
      $caption = $_SESSION['imageLibrary'][$imageIDRef]['ImageCaption'];
      $locationLink = $_SESSION['imageLibrary'][$imageIDRef]['ImageLink'];
      $description = $_SESSION['imageLibrary'][$imageIDRef]['ImageDescription'];
    }

    $imageString = "
    <figure class=\"insertedImage$imageClassCircular\" />
        <img  src=\"../$locationLink\"  alt=\"$description\" width=\"$imageWidth\" height=\"$imageHeight\"/>
    <figcaption>$caption</figcaption>
</figure>";
    $sectionString = substr_replace($sectionString, $imageString, $imageCodeStartPos, $imageCodeLength);
  }
  $returnArray[0] = $sectionString;
  $returnArray[1] = $errorMessage;

  return $returnArray;
}

function decodeImageCodeString($imageRefString)
{
  $imageRefString = preg_replace('/\s+/', '', $imageRefString);
  $imageCodeArray = explode(",", $imageRefString);
  return $imageCodeArray;
}


/**
 * Generate JavaScript color manipulation functions
 * Returns a string containing all color helper functions for client-side use
 */
function generateColorManipulationJS()
{
  return "
<script>
/**
 * Get contrasting text color (black or white) for a given background color
 * @param {string} hexColor - Hex color code (e.g., '#FF5733')
 * @returns {string} '#000000' for light backgrounds, '#FFFFFF' for dark backgrounds
 */
function getContrastColor(hexColor) {
    // Convert hex to RGB
    var r = parseInt(hexColor.substr(1,2), 16);
    var g = parseInt(hexColor.substr(3,2), 16);
    var b = parseInt(hexColor.substr(5,2), 16);
    
    // Calculate luminance
    var luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    
    // Return black or white based on luminance
    return luminance > 0.5 ? '#000000' : '#FFFFFF';
}

/**
 * Get a lighter version of a color
 * @param {string} hexColor - Hex color code (e.g., '#FF5733')
 * @param {number} percent - Amount to lighten (0-100, where 100 = pure white)
 * @returns {string} Lightened hex color
 */
function getLighterColor(hexColor, percent) {
    // Remove # if present
    var hex = hexColor.replace('#', '');
    
    // Convert to RGB
    var r = parseInt(hex.substr(0,2), 16);
    var g = parseInt(hex.substr(2,2), 16);
    var b = parseInt(hex.substr(4,2), 16);
    
    // Lighten by moving towards white (255, 255, 255)
    var amount = percent / 100;
    r = Math.round(r + (255 - r) * amount);
    g = Math.round(g + (255 - g) * amount);
    b = Math.round(b + (255 - b) * amount);
    
    // Convert back to hex
    var rHex = r.toString(16).padStart(2, '0');
    var gHex = g.toString(16).padStart(2, '0');
    var bHex = b.toString(16).padStart(2, '0');
    
    return '#' + rHex + gHex + bHex;
}

/**
 * Get a darker version of a color
 * @param {string} hexColor - Hex color code (e.g., '#FF5733')
 * @param {number} percent - Amount to darken (0-100, where 100 = pure black)
 * @returns {string} Darkened hex color
 */
function getDarkerColor(hexColor, percent) {
    // Remove # if present
    var hex = hexColor.replace('#', '');
    
    // Convert to RGB
    var r = parseInt(hex.substr(0,2), 16);
    var g = parseInt(hex.substr(2,2), 16);
    var b = parseInt(hex.substr(4,2), 16);
    
    // Darken by moving towards black (0, 0, 0)
    var amount = percent / 100;
    r = Math.round(r * (1 - amount));
    g = Math.round(g * (1 - amount));
    b = Math.round(b * (1 - amount));
    
    // Convert back to hex
    var rHex = r.toString(16).padStart(2, '0');
    var gHex = g.toString(16).padStart(2, '0');
    var bHex = b.toString(16).padStart(2, '0');
    
    return '#' + rHex + gHex + bHex;
}

/**
 * Get the complementary color (opposite on color wheel)
 * @param {string} hexColor - Hex color code (e.g., '#FF5733')
 * @returns {string} Complementary hex color
 */
function getComplementaryColor(hexColor) {
    // Remove # if present
    var hex = hexColor.replace('#', '');
    
    // Convert to RGB
    var r = parseInt(hex.substr(0,2), 16);
    var g = parseInt(hex.substr(2,2), 16);
    var b = parseInt(hex.substr(4,2), 16);
    
    // Find complementary by inverting RGB values
    var compR = (255 - r).toString(16).padStart(2, '0');
    var compG = (255 - g).toString(16).padStart(2, '0');
    var compB = (255 - b).toString(16).padStart(2, '0');
    
    return '#' + compR + compG + compB;
}

/**
 * Get analogous colors (±30° on color wheel)
 * @param {string} hexColor - Hex color code (e.g., '#FF5733')
 * @returns {Array} Array of two hex colors [analogous1, analogous2]
 */
function getAnalogousColors(hexColor) {
    // Remove # if present
    var hex = hexColor.replace('#', '');
    
    // Convert RGB to HSL
    var r = parseInt(hex.substr(0,2), 16) / 255;
    var g = parseInt(hex.substr(2,2), 16) / 255;
    var b = parseInt(hex.substr(4,2), 16) / 255;
    
    var max = Math.max(r, g, b);
    var min = Math.min(r, g, b);
    var h, s, l = (max + min) / 2;
    
    if (max === min) {
        h = s = 0; // achromatic
    } else {
        var d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        
        switch (max) {
            case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
            case g: h = ((b - r) / d + 2) / 6; break;
            case b: h = ((r - g) / d + 4) / 6; break;
        }
    }
    
    // Get analogous colors by shifting hue ±30 degrees
    var analogous1 = hslToHex((h + 30/360) % 1, s, l);
    var analogous2 = hslToHex((h - 30/360 + 1) % 1, s, l);
    
    return [analogous1, analogous2];
}

/**
 * Convert HSL to Hex color
 * @param {number} h - Hue (0-1)
 * @param {number} s - Saturation (0-1)
 * @param {number} l - Lightness (0-1)
 * @returns {string} Hex color code
 */
function hslToHex(h, s, l) {
    var r, g, b;
    
    if (s === 0) {
        r = g = b = l; // achromatic
    } else {
        var hue2rgb = function(p, q, t) {
            if (t < 0) t += 1;
            if (t > 1) t -= 1;
            if (t < 1/6) return p + (q - p) * 6 * t;
            if (t < 1/2) return q;
            if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
            return p;
        };
        
        var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        var p = 2 * l - q;
        
        r = hue2rgb(p, q, h + 1/3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1/3);
    }
    
    var hexR = Math.round(r * 255).toString(16).padStart(2, '0');
    var hexG = Math.round(g * 255).toString(16).padStart(2, '0');
    var hexB = Math.round(b * 255).toString(16).padStart(2, '0');
    
    return '#' + hexR + hexG + hexB;
}
</script>
";
}

/**
 * Generate color variations from a base color
 * @param string $hexColor - Hex color code (with or without #, e.g., '#FF5733' or 'FF5733')
 * @param int $lightenPercent - Amount to lighten (0-100, default 85 for backgrounds)
 * @return array Array with keys: 'lighter', 'splitComp1', 'splitComp2', 'splitComp1Lighter', 'splitComp2Lighter'
 */
function generateColorVariations($hexColor, $lightenPercent = 90)
{
  // Remove # if present and validate
  $hex = ltrim($hexColor, '#');

  // Ensure we have a valid 6-character hex code
  if (strlen($hex) === 3) {
    // Expand shorthand (e.g., 'F53' to 'FF5533')
    $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
  }

  if (!preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
    // Return neutral grey if invalid color
    return array(
      'lighter' => '#f5f5f5',
      'splitComp1' => '#808080',
      'splitComp2' => '#999999',
      'splitComp1Lighter' => '#e0e0e0',
      'splitComp2Lighter' => '#e5e5e5'
    );
  }

  // Convert hex to RGB
  $r = hexdec(substr($hex, 0, 2));
  $g = hexdec(substr($hex, 2, 2));
  $b = hexdec(substr($hex, 4, 2));

  // Generate lighter version
  $amount = $lightenPercent / 100;
  $lightR = round($r + (255 - $r) * $amount);
  $lightG = round($g + (255 - $g) * $amount);
  $lightB = round($b + (255 - $b) * $amount);

  $lighterColor = '#' . str_pad(dechex($lightR), 2, '0', STR_PAD_LEFT)
    . str_pad(dechex($lightG), 2, '0', STR_PAD_LEFT)
    . str_pad(dechex($lightB), 2, '0', STR_PAD_LEFT);

  // Convert RGB to HSL for split-complementary calculation
  $rNorm = $r / 255;
  $gNorm = $g / 255;
  $bNorm = $b / 255;

  $max = max($rNorm, $gNorm, $bNorm);
  $min = min($rNorm, $gNorm, $bNorm);
  $l = ($max + $min) / 2;

  if ($max === $min) {
    $h = $s = 0; // achromatic
  } else {
    $d = $max - $min;
    $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

    if ($max === $rNorm) {
      $h = (($gNorm - $bNorm) / $d + ($gNorm < $bNorm ? 6 : 0)) / 6;
    } elseif ($max === $gNorm) {
      $h = (($bNorm - $rNorm) / $d + 2) / 6;
    } else {
      $h = (($rNorm - $gNorm) / $d + 4) / 6;
    }
  }

  // Calculate split-complementary colors (±150° from original hue)
  $splitComp1Hue = fmod($h + 150 / 360, 1);
  $splitComp2Hue = fmod($h - 150 / 360 + 1, 1);

  $splitComp1 = hslToHexPHP($splitComp1Hue, $s, $l);
  $splitComp2 = hslToHexPHP($splitComp2Hue, $s, $l);

  // Generate lighter versions of split-complementary colors
  // Convert split-comp colors back to RGB to lighten them
  $splitComp1RGB = hexToRGB($splitComp1);
  $splitComp2RGB = hexToRGB($splitComp2);

  $splitComp1LightR = round($splitComp1RGB['r'] + (255 - $splitComp1RGB['r']) * $amount);
  $splitComp1LightG = round($splitComp1RGB['g'] + (255 - $splitComp1RGB['g']) * $amount);
  $splitComp1LightB = round($splitComp1RGB['b'] + (255 - $splitComp1RGB['b']) * $amount);

  $splitComp2LightR = round($splitComp2RGB['r'] + (255 - $splitComp2RGB['r']) * $amount);
  $splitComp2LightG = round($splitComp2RGB['g'] + (255 - $splitComp2RGB['g']) * $amount);
  $splitComp2LightB = round($splitComp2RGB['b'] + (255 - $splitComp2RGB['b']) * $amount);

  $splitComp1Lighter = '#' . str_pad(dechex($splitComp1LightR), 2, '0', STR_PAD_LEFT)
    . str_pad(dechex($splitComp1LightG), 2, '0', STR_PAD_LEFT)
    . str_pad(dechex($splitComp1LightB), 2, '0', STR_PAD_LEFT);

  $splitComp2Lighter = '#' . str_pad(dechex($splitComp2LightR), 2, '0', STR_PAD_LEFT)
    . str_pad(dechex($splitComp2LightG), 2, '0', STR_PAD_LEFT)
    . str_pad(dechex($splitComp2LightB), 2, '0', STR_PAD_LEFT);

  return array(
    'lighter' => $lighterColor,
    'splitComp1' => $splitComp1,
    'splitComp2' => $splitComp2,
    'splitComp1Lighter' => $splitComp1Lighter,
    'splitComp2Lighter' => $splitComp2Lighter
  );
}

/**
 * Convert hex color to RGB array
 * @param string $hexColor - Hex color code with # prefix
 * @return array Array with keys 'r', 'g', 'b'
 */
function hexToRGB($hexColor)
{
  $hex = ltrim($hexColor, '#');
  return array(
    'r' => hexdec(substr($hex, 0, 2)),
    'g' => hexdec(substr($hex, 2, 2)),
    'b' => hexdec(substr($hex, 4, 2))
  );
}

/**
 * Convert HSL values to hex color
 * @param float $h - Hue (0-1)
 * @param float $s - Saturation (0-1)
 * @param float $l - Lightness (0-1)
 * @return string Hex color code with # prefix
 */
function hslToHexPHP($h, $s, $l)
{
  if ($s === 0) {
    // Achromatic (grey)
    $r = $g = $b = $l;
  } else {
    $hue2rgb = function ($p, $q, $t) {
      if ($t < 0) $t += 1;
      if ($t > 1) $t -= 1;
      if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
      if ($t < 1 / 2) return $q;
      if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
      return $p;
    };

    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;

    $r = $hue2rgb($p, $q, $h + 1 / 3);
    $g = $hue2rgb($p, $q, $h);
    $b = $hue2rgb($p, $q, $h - 1 / 3);
  }

  $hexR = str_pad(dechex(round($r * 255)), 2, '0', STR_PAD_LEFT);
  $hexG = str_pad(dechex(round($g * 255)), 2, '0', STR_PAD_LEFT);
  $hexB = str_pad(dechex(round($b * 255)), 2, '0', STR_PAD_LEFT);

  return '#' . $hexR . $hexG . $hexB;
}
