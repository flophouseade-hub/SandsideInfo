<?php
function printSpaceOnLeftSection($sectionContent, $errorMessage, $title, $sectionID)
{
	// Get some data from the session variables:
	$sectionColour = $_SESSION["sectionDB"][$sectionID]["SectionColour"] ?? "#b3b3b3";
	$sectionColourSameAsPage = $_SESSION["sectionDB"][$sectionID]["SectionColourSameAsPage"] ?? 0;
	$sectionShowTitle = $_SESSION["sectionDB"][$sectionID]["SectionShowTitle"] ?? 1;
	// Original style - title on left, content on right with horizontal rules
	$divClass = "sectionID" . $sectionID . "ColourDiv";

	//have seperage div for each section to apply colour variables
	print "<div class=\"$divClass\">";

	// Only generate local color variations if not using page colors
	if (!$sectionColourSameAsPage) {
		$colourCombo = generateColorVariations($sectionColour, 95);
		print "<style>\n
  .$divClass {
    --chosen-color: $sectionColour;
    --chosen-color-lighter: {$colourCombo["lighter"]};
    --chosen-color-Comp1: {$colourCombo["splitComp1"]};
    --chosen-color-Comp2: {$colourCombo["splitComp2"]};
    --chosen-color-Comp2-lighter: {$colourCombo["splitComp2Lighter"]};
    --chosen-color-Comp1-lighter: {$colourCombo["splitComp1Lighter"]};
    }
    </style>\n ";
	} else {
		// Use root page colors - no need to define local variables
	}
	print "<style>\n
  /* -------------------------------------------------- */
/* SpaceLeft Styles */
/* --------------------------------------------------- */

.$divClass  section.section1 {
	margin-top: 40px;
	margin-bottom: 10px;
	margin-right: 0px;
	margin-left: 0px;
}

.$divClass  .section1 .section1Content ul,
.$divClass  .section1 .section1Content ol {
	padding-top: 12px;
	padding-bottom: 12px;
	background-color: var(--chosen-color-Comp1-lighter);
	border-top: 1px solid var(--chosen-color);
	border-bottom: 1px solid var(--chosen-color);
}

.$divClass .section1 h1.sectionTitle {
	font-family: 'Roboto', sans-serif;
	font-size: 18px;
	font-weight: 200;
	letter-spacing: 2px;
	text-transform: uppercase;
	text-align: left;
}

.$divClass  .section1 .section1Content h1{
	background-color: var(--chosen-color-Comp1-lighter);
	padding-top: 15px;
	padding-bottom: 15px;
	font-weight: 400;
	padding-left: 12px;
	margin-top: 25px;
	margin-bottom: 10px;
	text-transform: uppercase;
	font-size: 22px;
	border-color: var(--chosen-color);
	border-left: 0px;
	border-top: 2px ;
	border-bottom: 2px ;
	border-right: 0px ;
}	

.section1 .section1Content h2 {
	padding-top: 6px;
}

.$divClass .section1 .sectionTitle a {
	color: var(--chosen-color);
	font-family: 'Roboto', sans-serif;
	font-size: 24px;
	font-weight: 300;
	text-transform: uppercase;
	text-decoration: none;
}

.$divClass .section1 .sectionTitleRule {
	margin: 0 3% 0 0;
	float: left;
	clear: both;
	height: 2px;
}

.$divClass .section1 .sectionTitleRule2 {
	/* background-color: rgba(208,207,207,1.00); */
	height: 2px;
	padding: 0px;
	margin: 0 1% 0 0;
}

.$divClass  .section1 .section1Content {
	color: rgba(146, 146, 146, 1.00);
	font-size: 16px;
	font-weight: 400;
	line-height: 1.5;
}




.$divClass  .section1 .section1Content button {
	font-size: 14px;
	line-height: 1.3;
	font-weight: 100;
	letter-spacing: 2px;
	color: white;
	background-color: #b3b3b3;
	padding: 15px 32px;
	border: none;
}

.$divClass .section1 .section1Content input.asButton {
	font-size: 14px;
	line-height: 1.3;
	font-weight: 100;
	letter-spacing: 2px;
	color: white;
	background-color: #b3b3b3;
	padding: 15px 32px;
	border: none;
}

.$divClass  .section1 .section1Content span {
	color: rgba(146, 146, 146, 1.00);

}

.$divClass .stretch {
	content: '';
	display: inline-block;
	margin-left: 2%;
	margin-right: 2%;
}

/* Media query for Mobile devices*/
@media only screen and (min-width : 285px) and (max-width : 480px) {

	/* Main content sections */
	.$divClass  .section1 {
		text-align: center;
		margin-bottom: 10%;
	}

	.$divClass .section1 .section1Title {
		text-align: center;
	}

	.$divClass .section1 .sectionTitleRule {
		width: 100%;
	}

	.$divClass .section1 .sectionTitleRule2 {
		display: none;
	}

	.$divClass  .section1 .section1Content {
		display: inline-block;
		text-align: left;
		font-family:   'Open Sans', sans-serif;
		text-align: justify;
	}
}

/* Media Query for Tablets */
@media only screen and (min-width : 481px) and (max-width : 1024px) {

	/* Main content and sections */
	.section1 .section1Title {
		text-align: center;
	}

	.section1 .sectionTitleRule {
		width: 100%;
	}

	.section1 .sectionTitleRule2 {
		display: none;
	}

	.$divClass  .section1 .section1Content {
		display: block;
		margin: 0% 0% 0% 20%;
		font-family:  'Poppins', sans-serif;
		margin-top: 5%;
		margin-bottom: 5%;
	}
}

/* Desktops and laptops  */
@media only screen and (min-width:1025px) {

	/* Main content sections */
	.section1 .sectionTitleRule {
		width: 22.5%;
	}

	.$divClass  .section1 .section1Content {
		display: block;
		margin-top: 0%;
		margin-right: 0%;
		margin-left: 26%;
		margin-bottom: 0%;
		font-family:  'Poppins', sans-serif;
	}
}

/* End of Media Queries */
    
    </style>\n ";
	// Add CSS to hide title if SectionShowTitle is false
	if (!$sectionShowTitle) {
		print "<style>\n  .$divClass .sectionTitle,\n  .$divClass .sectionTitleRule,\n  .$divClass .sectionTitleRule2 { display: none; }\n</style>\n";
	}

	// Add floating edit button for editors/admins
	$editButton = "";
	if (accessLevelCheck("pageEditor") === true) {
		print "<style>\n  .$divClass .sectionEditButton {\n    position: absolute;\n    top: 5px;\n    left: 5px;\n    background-color: rgba(25, 118, 210, 0.7);\n    color: white;\n    border: none;\n    border-radius: 4px;\n    padding: 6px 10px;\n    font-size: 12px;\n    cursor: pointer;\n    text-decoration: none;\n    display: inline-block;\n    z-index: 100;\n    opacity: 0;\n    transition: opacity 0.3s ease;\n  }\n  .$divClass .sectionEditButton:hover {\n    background-color: rgba(25, 118, 210, 1);\n    opacity: 1;\n  }\n  .$divClass .section1:hover .sectionEditButton {\n    opacity: 1;\n  }\n  .$divClass .section1 {\n    position: relative;\n  }\n</style>\n";
		$editButton = "<a href=\"../PagesAndSections/editSectionDetailsPage.php?editSectionID=$sectionID\" class=\"sectionEditButton\" title=\"Edit Section\">âœï¸ Edit</a>";
	}

	printf(
		"
  <section class=\"mainContent\">
    <section class=\"section1\" >
      %s
      <h1 class=\"sectionTitle\" style=\"color: var(--chosen-color)\">%s</h1>
      <hr class=\"sectionTitleRule\" style=\"background-color: var(--chosen-color)\">
      <hr class=\"sectionTitleRule2\" style=\"background-color: var(--chosen-color)\">
      <div class=\"section1Content\">%s%s</div>
    </section>
  </section>
  <div style=\"clear: both;\"></div>
  ",
		$editButton,
		$title,
		$errorMessage,
		$sectionContent,
	);
	print "</div>";
	return;
}
