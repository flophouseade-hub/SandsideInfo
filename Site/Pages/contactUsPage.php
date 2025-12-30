<?php
$thisPageID = 37;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// reCAPTCHA keys
define("RECAPTCHA_SECRET_KEY", "6LcFIiEsAAAAANuCDXOoTmKwfmFSKl8jEbe-_MbE");
define("RECAPTCHA_SITE_KEY", "6LcFIiEsAAAAAEYnXkjcCxiBlcEWOJFQk3TnoIqY");

// -----------------------------------------------
// Run this section if the form has been submitted
// -----------------------------------------------
$inputError = false;
$errorMessage = "";
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["contactUsButton"])) {
	// Get the form data
	$firstName = $_POST["fvUserFirstName"] ?? "";
	$email = $_POST["fvUserEmail"] ?? "";
	$messageContent = $_POST["fvUserMessage"] ?? "";
	$recaptchaResponse = $_POST["g-recaptcha-response"] ?? "";

	// Reset POST to prevent resubmission
	$_POST = [];

	// Verify reCAPTCHA
	if (empty($recaptchaResponse)) {
		$errorMessage .= "Please complete the reCAPTCHA verification.<br/>";
		$inputError = true;
	} else {
		// Verify with Google
		$verifyURL = "https://www.google.com/recaptcha/api/siteverify";
		$data = [
			"secret" => RECAPTCHA_SECRET_KEY,
			"response" => $recaptchaResponse,
			"remoteip" => $_SERVER["REMOTE_ADDR"],
		];

		$options = [
			"http" => [
				"header" => "Content-type: application/x-www-form-urlencoded\r\n",
				"method" => "POST",
				"content" => http_build_query($data),
			],
		];

		$context = stream_context_create($options);
		$verify = file_get_contents($verifyURL, false, $context);
		$captchaSuccess = json_decode($verify);

		if ($captchaSuccess->success == false) {
			$errorMessage .= "reCAPTCHA verification failed. Please try again.<br/>";
			$inputError = true;
		}
	}

	// Validate other inputs
	if (strlen($firstName) <= 2) {
		$errorMessage .= "First Name must be at least 3 characters long.<br/>";
		$inputError = true;
	}

	if (strlen($messageContent) <= 8) {
		$errorMessage .= "Message must be at least 9 characters long.<br/>";
		$inputError = true;
	}

	if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
		$errorMessage .= "There is a problem with your email address.<br/>";
		$inputError = true;
	}

	// If no input errors, send the emails
	if ($inputError === false) {
		// Sanitize inputs for email
		$firstName = htmlspecialchars($firstName, ENT_QUOTES, "UTF-8");
		$email = filter_var($email, FILTER_SANITIZE_EMAIL);
		$messageContent = htmlspecialchars($messageContent, ENT_QUOTES, "UTF-8");

		// Send email to the user
		$to = $email;
		$subject = "Message You Sent to Sandside Lodge Staff Site";
		$message = "Hello $firstName,\n\n";
		$message .= "This is confirmation that we have received your message. We will reply as soon as possible.\n\n";
		$message .= "The message you sent was:\n\n";
		$message .= "$messageContent\n\n";
		$message .= "Best regards,\n\nSandside Lodge Staff Site";
		$headers = "From: noreply@sandside.info";

		$userEmailSent = mail($to, $subject, $message, $headers);

		// Send email to site admin
		$to = "ict@sandside.org.uk";
		$subject = "Contact Us Message from Sandside Lodge Staff Site";
		$message = "You have received a new message from the Contact Us form.\n\n";
		$message .= "Name: $firstName\n";
		$message .= "Email: $email\n\n";
		$message .= "Message:\n$messageContent\n\n";
		$message .= "Sent from IP: " . $_SERVER["REMOTE_ADDR"] . "\n";
		$headers = "From: noreply@sandside.info";

		$adminEmailSent = mail($to, $subject, $message, $headers);

		if ($userEmailSent && $adminEmailSent) {
			$successMessage = "<p style='color: green; font-weight: bold;'>✓ Thank you, $firstName! Your message has been sent successfully.</p>";
			// Clear form fields on success
			$firstName = "";
			$email = "";
			$messageContent = "";
		} else {
			$errorMessage .= "There was a problem sending your message. Please try again or contact us directly.<br/>";
			$inputError = true;
		}
	}
}

// Preserve form values if there were errors
$firstNameEntry = htmlspecialchars($firstName ?? "", ENT_QUOTES, "UTF-8");
$emailEntry = htmlspecialchars($email ?? "", ENT_QUOTES, "UTF-8");
$userMessageEntry = htmlspecialchars($messageContent ?? "", ENT_QUOTES, "UTF-8");

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the form formatting CSS
print '<link rel="stylesheet" href="../css/formPageFormatting.css">';

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display feedback messages
if (!empty($successMessage)) {
	print "<div style=\"max-width: 80%; margin: 20px auto;\">$successMessage</div>";
}

if ($inputError === true && !empty($errorMessage)) {
	print "<div style=\"max-width: 80%; margin: 20px auto;\">
        <p style='color: red; font-weight: bold;'>⚠ There were problems with your submission:</p>
        <p style='color: red;'>$errorMessage</p>
    </div>";
}

// Page description
$pageDescription =
	$_SESSION["pagesOnSite"][$thisPageID]["PageDescription"] ??
	"Fill in the form with your details and it will send your message. We're here to help and will respond as soon as possible.";

print "<div style=\"max-width: 900px; margin: 0 auto; padding: 20px;\">";

print "
<div style=\"padding: 20px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 30px;\">
    <p style=\"color: #555; font-size: 15px; line-height: 1.6;\">$pageDescription</p>
</div>

<form action=\"contactUsPage.php\" method=\"POST\">
    <div style=\"padding: 20px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;\">
        <h3 style=\"margin-top: 0;\">Contact Form</h3>
        
        <div style=\"margin-bottom: 20px;\">
            <label style=\"display: block; font-weight: bold; margin-bottom: 5px; color: #333;\">Your Name *</label>
            <input type=\"text\" name=\"fvUserFirstName\" value=\"$firstNameEntry\" 
                   style=\"width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box;\" 
                   placeholder=\"Enter your name\" required>
        </div>
        
        <div style=\"margin-bottom: 20px;\">
            <label style=\"display: block; font-weight: bold; margin-bottom: 5px; color: #333;\">Your Email *</label>
            <input type=\"email\" name=\"fvUserEmail\" value=\"$emailEntry\" 
                   style=\"width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box;\" 
                   placeholder=\"Enter your email address\" required>
        </div>
        
        <div style=\"margin-bottom: 20px;\">
            <label style=\"display: block; font-weight: bold; margin-bottom: 5px; color: #333;\">Your Message *</label>
            <textarea name=\"fvUserMessage\" rows=\"6\" 
                      style=\"width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; resize: vertical;\" 
                      placeholder=\"Enter your message (minimum 9 characters)\" required>$userMessageEntry</textarea>
        </div>
        
        <div style=\"margin-bottom: 20px;\">
            <div class=\"g-recaptcha\" data-sitekey=\"" .
	RECAPTCHA_SITE_KEY .
	"\"></div>
        </div>
        
        <div style=\"text-align: center;\">
            <button type=\"submit\" name=\"contactUsButton\" 
                    style=\"background-color: #4CAF50; color: white; padding: 12px 30px; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer;\">
                Send Your Message
            </button>
        </div>
    </div>
</form>

<div style=\"margin-top: 20px; padding: 15px; background-color: #e8f4f8; border-left: 4px solid #2196F3; border-radius: 4px;\">
    <p style=\"margin: 0; color: #555; font-size: 14px;\">
        <strong>Note:</strong> All fields marked with * are required. We typically respond within 24 hours during term time.
    </p>
</div>

</div>";

// Add reCAPTCHA script
print "<script src=\"https://www.google.com/recaptcha/api.js\" async defer></script>";

insertPageFooter($thisPageID);
?>
