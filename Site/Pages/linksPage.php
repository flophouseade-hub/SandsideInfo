<?php 
  // Start a seesion if one is not already started
  if (session_status() == PHP_SESSION_NONE){
  session_start();
  }
$thisPageID = 5;
include('../phpCode/includeFunctions.php');
include('../phpCode/pagesAndImagesArrays.php');
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the page details for this page from the array:
	$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'];
	$pageDescription= $_SESSION['pagesOnSite'][$thisPageID]['PageDescription'];
	$pageImageRef= $_SESSION['pagesOnSite'][$thisPageID]['PageImageIDRef'];
	$pageType= $_SESSION['pagesOnSite'][$thisPageID]['PageType'];
	$pageContentRefs= $_SESSION['pagesOnSite'][$thisPageID]['PageContentRefs'];
	$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'];
	$pageColour = $_SESSION['pagesOnSite'][$thisPageID]['PageColour'];
	$pageLocalMenu= $_SESSION['pagesOnSite'][$thisPageID]['PageLocalMenu'];
	
if ($_SESSION['currentUserLogOnStatus'] != null) {
	  insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
	  insertPageTitleAndClass($pageName,"blockMenuPageTitle",$thisPageID);
	  include '../HTMLpages/linksPageHTML.php';
	  insertPageFooter($thisPageID);
    }else {
	header("Location:../Pages/loginPage.php");
	exit;
    } ?>