<?php
function insertPageSectionOneColumn($contentString, $title, $sectionID)
{
	//content string is stored with html entties converted so convert them back if we need to
	$contentString = decodeSectionContent($contentString);
	//$contentString = html_entity_decode($contentString);
	// Find the occurences of image references and replace them with the image HTML
	$displayString = $contentString;
	$count = 0;
	$errorMessage = "";
	//-------------------------------------------------------------------
	// Loop to replace all image references
	//-------------------------------------------------------------------
	while (strpos($displayString, "<imageL") !== false) {
		$count++;
		$returnArray = replaceImageRefInContentString($displayString, $sectionID);
		$displayString = $returnArray[0];
		$errorMessage = $returnArray[1];
		if ($count > 10) {
			break;
		} // Prevent infinite loop in case of error
	}
	//-------------------------------------------------------------------
	// Now find all the local page references and replace them with links
	//-------------------------------------------------------------------
	$count = 0;
	while (strpos($displayString, "<pageL") !== false) {
		$count++;
		$refArray = findRefStringPositionsInContentString($displayString, "<pageL", "/>");
		if ($refArray === false) {
			break;
		}
		$pageRefStartPos = $refArray[0];
		$pageRefLength = $refArray[1];
		$pageRefString = $refArray[2];
		$pageRefID = trim($pageRefString);
		// is there a comma to show link text?
		$commaPos = strpos($pageRefID, ",");
		if ($commaPos !== false) {
			$linkText = trim(substr($pageRefID, $commaPos + 1));
			$pageRefID = trim(substr($pageRefID, 0, $commaPos));
		} else {
			$linkText = $_SESSION["pagesOnSite"][$pageRefID]["PageName"];
		}
		// Check that this page does exist in the data
		if (isset($_SESSION["pagesOnSite"][$pageRefID]) == false) {
			$pageLinkString = "<span style=\"color: red;\"><strong>Invalid Page Link Reference ID: $pageRefID</strong></span>";
			$pageLocationLink = "#";
		} else {
			$pageLocationLink = $_SESSION["pagesOnSite"][$pageRefID]["PageLink"];
		}
		$pageLinkString = "<a href=\"$pageLocationLink\" >$linkText</a>";
		$displayString = substr_replace($displayString, $pageLinkString, $pageRefStartPos, $pageRefLength);
	}
	//-------------------------------------------------------------------
	// Find all the external link references and replace them with links
	//-------------------------------------------------------------------
	$count = 0;
	while (strpos($displayString, "<linkE") !== false) {
		$count++;
		$refArray = findRefStringPositionsInContentString($displayString, "<linkE", "/>");
		if ($refArray === false) {
			break;
		}
		$extRefStartPos = $refArray[0];
		$extRefLength = $refArray[1];
		$extRefString = $refArray[2];
		$extRefURL = trim($extRefString);
		// is there a comma to show link text?
		$commaPos = strpos($extRefURL, ",");
		if ($commaPos !== false) {
			$linkText = trim(substr($extRefURL, $commaPos + 1));
			$extRefURL = trim(substr($extRefURL, 0, $commaPos));
		} else {
			$linkText = $extRefURL;
		}
		if (validateURL($extRefURL) === true) {
			$extLinkString = "<a href=\"$extRefURL\" target=\"_blank\" rel=\"noopener noreferrer\">$linkText</a>";
		} else {
			$extLinkString = "<span style=\"color: red;\"><strong>Invalid External Link URL: $extRefURL</strong></span>";
		}
		$displayString = substr_replace($displayString, $extLinkString, $extRefStartPos, $extRefLength);
		if ($count > 10) {
			break;
		} // Prevent infinite loop in case of error
	}
	//-------------------------------------------------------------------
	// Find all the local file link references and replace them with links
	//-------------------------------------------------------------------
	$count = 0;
	while (strpos($displayString, "<linkL") !== false) {
		$count++;
		$refArray = findRefStringPositionsInContentString($displayString, "<linkL", "/>");
		if ($refArray === false) {
			break;
		}
		$fileRefStartPos = $refArray[0];
		$fileRefLength = $refArray[1];
		$fileRefString = $refArray[2];
		$fileRefLink = trim($fileRefString);
		// is there a comma to show link text?
		$commaPos = strpos($fileRefLink, ",");
		if ($commaPos !== false) {
			$linkText = trim(substr($fileRefLink, $commaPos + 1));
			$fileRefLink = trim(substr($fileRefLink, 0, $commaPos));
		} else {
			$linkText = $_SESSION["resourceLibrary"][$fileRefLink]["LRName"];
		}
		// Check that this link does exist in the data
		if (isset($_SESSION["resourceLibrary"][$fileRefLink]) == false) {
			$fileLinkString = "<span style=\"color: red;\"><strong>Invalid File Link Reference ID: $fileRefLink</strong></span>";
		} else {
			$fileLocationLink = $_SESSION["resourceLibrary"][$fileRefLink]["LRLink"];
		}
		$fileLinkString = "<a href=\"$fileLocationLink\" target=\"_blank\" rel=\"noopener noreferrer\">$linkText</a>";
		$displayString = substr_replace($displayString, $fileLinkString, $fileRefStartPos, $fileRefLength);
		if ($count > 10) {
			break;
		} // Prevent infinite loop in case of error
	}
	//-------------------------------------------------------------------
	// Find all the youtube video references and replace them with embedded videos
	//-------------------------------------------------------------------
	$count = 0;
	while (strpos($displayString, "<videoY") !== false) {
		$count++;
		$refArray = findRefStringPositionsInContentString($displayString, "<videoY", "/>");
		if ($refArray === false) {
			break;
		}
		$videoRefStartPos = $refArray[0];
		$videoRefLength = $refArray[1];
		$videoRefString = $refArray[2];
		$videoID = trim($videoRefString);
		// Is there a comma to show video width width as a percentage?
		$commaPos = strpos($videoID, ",");
		if ($commaPos !== false) {
			$videoWidth = trim(substr($videoID, $commaPos + 1));
			$videoID = trim(substr($videoID, 0, $commaPos));
		} else {
			$videoWidth = "100%";
		}
		$videoEmbedString = "
    <div class=\"videoContainer\">
      <iframe width=\"$videoWidth\" style=\"aspect-ratio: 16/9;\" src=\"https://www.youtube.com/embed/$videoID\" title=\"YouTube video player\" frameborder=\"0\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share\" allowfullscreen></iframe>
    </div>
    ";
		$displayString = substr_replace($displayString, $videoEmbedString, $videoRefStartPos, $videoRefLength);
		if ($count > 10) {
			break;
		} // Prevent infinite loop in case of error
	}
	//-------------------------------------------------------------------
	// End of replacements
	//-------------------------------------------------------------------
	// Get section colour and check if it should use page colour
	$_SESSION["sectionDB"][$sectionID]["SectionColour"] =
		$_SESSION["sectionDB"][$sectionID]["SectionColour"] ?? "#b3b3b3";

	$useSameAsPage = false;
	if (
		isset($_SESSION["sectionDB"][$sectionID]["SectionColourSameAsPage"]) &&
		$_SESSION["sectionDB"][$sectionID]["SectionColourSameAsPage"] == 1
	) {
		$useSameAsPage = true;
		$colourForSection = "SAME_AS_PAGE";
	} elseif (isset($_SESSION["sectionDB"][$sectionID]["SectionColour"])) {
		$colourForSection = $_SESSION["sectionDB"][$sectionID]["SectionColour"];
		// Ensure it has # prefix (for backwards compatibility if some old data doesn't have it)
		if (!empty($colourForSection) && $colourForSection[0] !== "#") {
			$colourForSection = "#" . $colourForSection;
		}
	} else {
		$colourForSection = "#b3b3b3";
	}
	if ($sectionID == 1002) {
		//temporary fix for unnumbered section getting wrong style
		$sectionStyle = "ColumnFrames";
	}

	// Get section style and route to appropriate display function
	$sectionStyle = $_SESSION["sectionDB"][$sectionID]["SectionStyle"] ?? "SpaceOnLeft";

	switch ($sectionStyle) {
		case "ColumnFrames":
			printColumnFramesSection($displayString, $errorMessage, $title, $sectionID);
			break;
		case "CentredSideAccent":
			printCentredSideAccentSection($displayString, $errorMessage, $title, $sectionID);
			break;
		case "AlternateBoxes":
			printAlternateBoxesSection($displayString, $errorMessage, $title, $sectionID);
			break;
		case "BoxesAndShadows":
			printBoxesAndShadowsSection($displayString, $errorMessage, $title, $sectionID);
			break;
		case "RawAndBasic":
			printRawAndBasicSection($displayString, $errorMessage, $title, $sectionID);
			break;
		case "SpaceOnLeft":
		default:
			printSpaceOnLeftSection($displayString, $errorMessage, $title, $sectionID);
			break;
	}

	return;
}

function encodeSectionContent($sectionContent)
{
	//$newString = $sectionContent;
	$newString = str_replace("<", "@<", $sectionContent);
	$newString = str_replace(">", "@>", $newString);
	$newString = str_replace(chr(34), "@" . chr(34), $newString);
	$encodedContent = htmlspecialchars($newString, ENT_QUOTES, "UTF-8");
	return $encodedContent;
}

function decodeSectionContent($contentString)
{
	$newString = $contentString;
	if (strpos($contentString, "@&lt;") !== false) {
		$newString = str_replace("@&lt;", "<", $newString);
	}
	if (strpos($newString, "@&gt;") !== false) {
		$newString = str_replace("@&gt;", ">", $newString);
	}
	$needle = "@&quot;";
	if (strpos($newString, $needle) !== false) {
		$newString = str_replace($needle, chr(34), $newString);
	}
	//$newString = html_entity_decode($contentString, ENT_QUOTES, 'UTF-8');
	return $newString;
}
function printRawAndBasicSection($sectionContent, $errorMessage, $title, $sectionID)
{
	// Get some data from the session variables:
	$sectionColour = $_SESSION["sectionDB"][$sectionID]["SectionColour"] ?? "#b3b3b3";
	$sectionColourSameAsPage = $_SESSION["sectionDB"][$sectionID]["SectionColourSameAsPage"] ?? 0;
	$sectionShowTitle = $_SESSION["sectionDB"][$sectionID]["SectionShowTitle"] ?? 1;
	// Raw and basic style - minimal styling
	$divClass = "sectionID" . $sectionID . "ColourDiv";

	//have seperage div for each section to apply colour variables
	//print("<div class=\"$divClass\">");
	// Only generate local color variations if not using page colors
	if (!$sectionColourSameAsPage) {
		$colourCombo = generateColorVariations($sectionColour, 95);
		print "<style>\n  
  .$divClass {
    --chosen-color: $sectionColour;
    --chosen-color-lighter: $colourCombo[lighter];
    --chosen-color-Comp1: $colourCombo[splitComp1];
    --chosen-color-Comp2: $colourCombo[splitComp2];
    --chosen-color-Comp2-lighter: $colourCombo[splitComp2Lighter];
    --chosen-color-Comp1-lighter: $colourCombo[splitComp1Lighter];
    }
    </style>\n ";
	} else {
		// Use root page colors - no need to define local variables
	}
	// Add CSS to hide title if SectionShowTitle is false
	if (!$sectionShowTitle) {
		print "<style>\n  .$divClass .sectionTitle,\n  .$divClass .sectionTitleRule,\n  .$divClass .sectionTitleRule2 { display: none; }\n</style>\n";
	}
	// Add floating edit button for editors/admins
	$editButton = "";
	if (accessLevelCheck("pageEditor") === true) {
		print "<style>\n  
    .$divClass .sectionEditButton {    
      position: absolute;    
      top: 5px;    
      left: 5px;   
      background-color: rgba(25, 118, 210, 0.7);\n    
      color: white;   
      border: none;    
      border-radius: 4px;\n    
      padding: 6px 10px;\n    
      font-size: 12px;\n    
      cursor: pointer;\n    
      text-decoration: none;\n    
      display: inline-block;\n    z-index: 100;\n    
      opacity: 0;\n    transition: opacity 0.3s ease;\n  }\n  
    .$divClass .sectionEditButton:hover {\n    
      background-color: rgba(25, 118, 210, 1);\n    
      opacity: 1;\n  }\n  
    .$divClass:hover .sectionEditButton {\n    
      opacity: 1;\n  }\n  
    .$divClass {\n    
    position: relative;\n  }\n
    </style>\n";

		$editButton = "<a href=\"../PagesAndSections/editSectionDetailsPage.php?editSectionID=$sectionID\" class=\"sectionEditButton\" title=\"Edit Section\">✏️ Edit</a>";
	}
	printf(
		"
  <section class=\"mainContent\">
    <div class=\"$divClass\">
      %s
      <h1 class=\"sectionTitle\" style=\"color: var(--chosen-color)\">%s</h1>
      <div class=\"section1Content\">%s%s</div>
  </section>",
		$editButton,
		$title,
		$errorMessage,
		$sectionContent,
	);

	print "<div style=\"clear: both;\"></div>\n";
	print "</div>";
	return;
}
