<?php
function validateURL($url)
{
  $url = trim($url);
  if (filter_var($url, FILTER_VALIDATE_URL)) {
    return true;
  } else {
    return false;
  }
}

function validateSingleNumberOnly($inputString)
{
  $inputString = trim($inputString);
  if (preg_match('/^[0-9]+$/', $inputString)) {
    return true;
  } else {
    return false;
  }
}

function validateLettersNumbersSpacesAndPunctuation($inputString)
{
  $inputString = trim($inputString);
  if (preg_match('/^[\p{L}\p{N}\s\.,;:!?"\'\-\(\)\[\]@#\$%&*\/\\\]+$/u', $inputString)) {
    return true;
  } else {
    return "Input contains invalid characters. Only letters, numbers, spaces, and common punctuation are allowed.";
  }
}

function validateBasicTextInput($inputString)
{
  $inputString = trim($inputString);
  if (mb_strlen($inputString) < 2) {
    return "Input must be at least 2 characters long.";
  } else {
    return true;
  }
}

function validateLettersAndSpacesOnly($inputString)
{
  $inputString = trim($inputString);
  if (preg_match('/^[a-zA-Z\s]+$/u', $inputString)) {
    return true;
  } else {
    return "Input must contain only letters and spaces.";
  }
}

function validateNumbersAndCommasOnly($inputString)
{
  // Allow empty or null values
  if (is_null($inputString) || trim($inputString) === '') {
    return true;
  }
  
  $inputString = trim($inputString);
  if (preg_match('/^[0-9,]+$/', $inputString)) {
    return true;
  } else {
    return false;
  }
}

function validateFirstName($firstName)
{
  //$firstName = trim($firstName);
  if (strlen($firstName) < 2) {
    return "First Name must be at least 2 characters long.";
  } else if (!preg_match('/^[a-zA-Z\s]+$/u', $firstName)) {
    return "First Name must contain only letters and spaces.";
  } else {
    return true;
  }
}

function validateLastName($lastName)
{
  $lastName = trim($lastName);
  if (mb_strlen($lastName) < 2) {
    return "Last Name must be at least 2 characters long.";
  } else if (!preg_match('/^[a-zA-Z\s]+$/u', $lastName)) {
    return "Last Name must contain only letters and spaces.";
  } else {
    return true;
  }
}

function validatePassword($password)
{
  if (mb_strlen($password) < 8) {
    return "Password must be at least 8 characters long.";
  } else {
    return true;
  }
}

function validateEmail($email)
{
  $email = trim($email);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return "Invalid email format.";
  } else {
    return true;
  }
}

function validateSectionIDList($sectionIDString)
{
  // First check if the input is empty
  $sectionIDString = trim($sectionIDString);
  if (mb_strlen($sectionIDString) == 0) {
    return true; // Empty string is valid (no sections)
  }

  // Check that the string only contains numbers and commas
  if (!preg_match('/^[0-9,\s]+$/', $sectionIDString)) {
    return "Section ID list must contain only numbers and commas.";
  }

  // Split the string into individual IDs
  $sectionIDArray = explode(",", $sectionIDString);

  // Connect to the database
  $connection = connectToDatabase();

  $invalidSections = array();

  foreach ($sectionIDArray as $sectionID) {
    $sectionID = trim($sectionID);

    // Skip empty values (from trailing commas or double commas)
    if (mb_strlen($sectionID) == 0) {
      continue;
    }

    // Check if it's a valid number
    if (!is_numeric($sectionID)) {
      $invalidSections[] = "Invalid: '$sectionID'";
      continue;
    }

    // Query the database to check if this section exists
    $query = "SELECT SectionID FROM SectionDB WHERE SectionID = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $sectionID);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
      $invalidSections[] = $sectionID;
    }

    $stmt->close();
  }

  $connection->close();

  // Return error message if any invalid sections found
  if (count($invalidSections) > 0) {
    $invalidList = implode(", ", $invalidSections);
    return "The following Section IDs do not exist in the database: $invalidList";
  }

  return true;
}

function validatePageIDList($pageIDString)
{
  //printDebugMessage("Validating Page ID List: $pageIDString");
  // First check if the input is empty
  $pageIDString = trim($pageIDString);
  if (mb_strlen($pageIDString) == 0) {
    return true; // Empty string is valid (no pages)
  }

  // Check that the string only contains numbers and commas
  if (!preg_match('/^[0-9,\s]+$/', $pageIDString)) {
    return "Page ID list must contain only numbers and commas.";
  }

  // Split the string into individual IDs
  $pageIDArray = explode(",", $pageIDString);

  // Connect to the database
  $connection = connectToDatabase();

  $invalidPages = array();

  foreach ($pageIDArray as $pageID) {
    $pageID = trim($pageID);
    //printDebugMessage("Validating Page ID: $pageID");

    // Skip empty values (from trailing commas or double commas)
    if (mb_strlen($pageID) == 0) {
      continue;
    }

    // Check if it's a valid number
    if (!is_numeric($pageID)) {
      $invalidPages[] = "Invalid: '$pageID'";
      continue;
    }

    // Query the database to check if this page exists
    $query = "SELECT PageID FROM PagesOnSite WHERE PageID = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $pageID);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
      $invalidPages[] = $pageID;
    }

    $stmt->close();
  }

  $connection->close();

  // Return error message if any invalid pages found
  if (count($invalidPages) > 0) {
    $invalidList = implode(", ", $invalidPages);
    return "The following Page IDs do not exist in the database: $invalidList";
  }

  return true;
}

function validateImageID($imageID)
{
  // First check if the input is empty or null
  if (is_null($imageID) || trim($imageID) === '') {
    return true; // Empty is valid (no image required)
  }

  $imageID = trim($imageID);

  // Check if it's a valid number
  if (!is_numeric($imageID)) {
    return "Image ID must be a number.";
  }

  // Convert to integer
  $imageID = (int)$imageID;

  // Check the database directly (more reliable than session)
  $connection = connectToDatabase();

  if (!$connection) {
    return "Database connection failed - cannot validate Image ID.";
  }

  $query = "SELECT ImageID FROM ImageLibrary WHERE ImageID = ?";
  $stmt = $connection->prepare($query);

  if (!$stmt) {
    $connection->close();
    return "Database query preparation failed.";
  }

  $stmt->bind_param("i", $imageID);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows == 0) {
    $stmt->close();
    $connection->close();
    return "Image ID $imageID does not exist in the Image Library.";
  }

  $stmt->close();
  $connection->close();

  return true;
}

function validateColourCode($colourInput)
{
  // First check if the input is empty
  $colourInput = trim($colourInput);
  if (mb_strlen($colourInput) == 0) {
    return "Colour code cannot be empty.";
  }
  
  // 1. Check for 6-digit hex without # (current project standard)
  if (preg_match('/^[0-9A-Fa-f]{6}$/', $colourInput)) {
    return true;
  }
  
  // 2. Check for 3-digit hex without # (shorthand)
  if (preg_match('/^[0-9A-Fa-f]{3}$/', $colourInput)) {
    return true;
  }
  
  // 3. Check for hex with # prefix
  if (preg_match('/^#[0-9A-Fa-f]{6}$/', $colourInput) || preg_match('/^#[0-9A-Fa-f]{3}$/', $colourInput)) {
    return true;
  }
  
  // 4. Check for rgb() format: rgb(255, 255, 255)
  if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $colourInput, $matches)) {
    // Validate that each value is 0-255
    if ($matches[1] <= 255 && $matches[2] <= 255 && $matches[3] <= 255) {
      return true;
    }
    return "RGB values must be between 0 and 255.";
  }
  
  // 5. Check for rgba() format: rgba(255, 255, 255, 0.5)
  if (preg_match('/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*([0-1]?\.?\d+)\s*\)$/i', $colourInput, $matches)) {
    // Validate RGB values and alpha
    if ($matches[1] <= 255 && $matches[2] <= 255 && $matches[3] <= 255 && $matches[4] <= 1) {
      return true;
    }
    return "RGBA values: RGB must be 0-255, alpha must be 0-1.";
  }
  
  // 6. Check for hsl() format: hsl(360, 100%, 50%)
  if (preg_match('/^hsl\(\s*(\d{1,3})\s*,\s*(\d{1,3})%\s*,\s*(\d{1,3})%\s*\)$/i', $colourInput, $matches)) {
    // Validate: hue 0-360, saturation/lightness 0-100
    if ($matches[1] <= 360 && $matches[2] <= 100 && $matches[3] <= 100) {
      return true;
    }
    return "HSL values: hue 0-360, saturation/lightness 0-100%.";
  }
  
  // 7. Check for hsla() format: hsla(360, 100%, 50%, 0.5)
  if (preg_match('/^hsla\(\s*(\d{1,3})\s*,\s*(\d{1,3})%\s*,\s*(\d{1,3})%\s*,\s*([0-1]?\.?\d+)\s*\)$/i', $colourInput, $matches)) {
    if ($matches[1] <= 360 && $matches[2] <= 100 && $matches[3] <= 100 && $matches[4] <= 1) {
      return true;
    }
    return "HSLA values: hue 0-360, saturation/lightness 0-100%, alpha 0-1.";
  }
  
  // 8. Check for common named colors (CSS standard names)
  $namedColors = array(
    'black', 'silver', 'gray', 'white', 'maroon', 'red', 'purple', 'fuchsia',
    'green', 'lime', 'olive', 'yellow', 'navy', 'blue', 'teal', 'aqua',
    'orange', 'aliceblue', 'antiquewhite', 'aquamarine', 'azure', 'beige',
    'bisque', 'blanchedalmond', 'blueviolet', 'brown', 'burlywood', 'cadetblue',
    'chartreuse', 'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson',
    'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgreen',
    'darkgrey', 'darkkhaki', 'darkmagenta', 'darkolivegreen', 'darkorange',
    'darkorchid', 'darkred', 'darksalmon', 'darkseagreen', 'darkslateblue',
    'darkslategray', 'darkslategrey', 'darkturquoise', 'darkviolet', 'deeppink',
    'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite',
    'forestgreen', 'gainsboro', 'ghostwhite', 'gold', 'goldenrod', 'greenyellow',
    'grey', 'honeydew', 'hotpink', 'indianred', 'indigo', 'ivory', 'khaki',
    'lavender', 'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue',
    'lightcoral', 'lightcyan', 'lightgoldenrodyellow', 'lightgray', 'lightgreen',
    'lightgrey', 'lightpink', 'lightsalmon', 'lightseagreen', 'lightskyblue',
    'lightslategray', 'lightslategrey', 'lightsteelblue', 'lightyellow',
    'limegreen', 'linen', 'magenta', 'mediumaquamarine', 'mediumblue',
    'mediumorchid', 'mediumpurple', 'mediumseagreen', 'mediumslateblue',
    'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'midnightblue',
    'mintcream', 'mistyrose', 'moccasin', 'navajowhite', 'oldlace', 'olivedrab',
    'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise',
    'palevioletred', 'papayawhip', 'peachpuff', 'peru', 'pink', 'plum',
    'powderblue', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown',
    'seagreen', 'seashell', 'sienna', 'skyblue', 'slateblue', 'slategray',
    'slategrey', 'snow', 'springgreen', 'steelblue', 'tan', 'thistle', 'tomato',
    'turquoise', 'violet', 'wheat', 'whitesmoke', 'yellowgreen'
  );
  
  if (in_array(strtolower($colourInput), $namedColors)) {
    return true;
  }
  
  // If none of the formats match, return error
  return "Invalid colour format. Use hex (FF5733), rgb(255,87,51), rgba(255,87,51,0.5), hsl(360,100%,50%), hsla(360,100%,50%,0.5), or named colors (red, blue, etc).";
}

function validatePageGroup($input) {
  // Allow letters (including accented), spaces, and common punctuation (.,;:!?'-/)
  // Returns true if valid, error message string if invalid
  
  if (empty(trim($input))) {
    return true; // PageGroup is optional, empty is okay
  }
  
  $input = trim($input);
  
  // Check length (reasonable limit for a group name)
  if (strlen($input) > 100) {
    return "Page Group must be 100 characters or less";
  }
  
  // Allow letters (including Unicode), spaces, and punctuation: . , ; : ! ? ' - / ( )
  if (!preg_match("/^[\p{L}\s.,;:!?'\-\/()]+$/u", $input)) {
    return "Page Group can only contain letters, spaces, and punctuation (.,;:!?'-/())";
  }
  
  return true;
}

function validatePositiveInteger($input)
{
  // Trim the input
  $input = trim($input);
  
  // Check if empty or null (not allowed)
  if (is_null($input) || $input === '') {
    return "Value cannot be empty.";
  }
  
  // Check if it's numeric
  if (!is_numeric($input)) {
    return "Value must be a positive integer.";
  }
  
  // Convert to integer
  $intValue = (int)$input;
  
  // Check if it's positive (greater than 0)
  if ($intValue <= 0) {
    return "Value must be greater than zero.";
  }
  
  // Check if the string representation matches the integer (no decimals)
  if ((string)$intValue !== $input) {
    return "Value must be a whole number (no decimals).";
  }
  
  return true;
}
?>