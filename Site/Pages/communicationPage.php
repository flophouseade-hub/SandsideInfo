<?php 
  session_start();
  if ($_SESSION['Email'] != null) {
	  $con = getDatabaseConnection();
	if(!$con){
		die("Connection Error");
	}
	  insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
	  include '../HTMLpages/communicationPageHTML.php';
	   insertPageFooter($thisPageID);
    }else {
	header("Location:../Pages/loginPage.php");
	exit;
    } ?>