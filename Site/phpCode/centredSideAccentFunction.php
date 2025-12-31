<?php
function printCentredSideAccentSection($sectionContent, $errorMessage, $title, $sectionID)
{
  // Get some data from the session variables:
  $sectionColour = $_SESSION['sectionDB'][$sectionID]['SectionColour'] ?? '#b3b3b3';
  $sectionColourSameAsPage = $_SESSION['sectionDB'][$sectionID]['SectionColourSameAsPage'] ?? 0;
  $sectionShowTitle = $_SESSION['sectionDB'][$sectionID]['SectionShowTitle'] ?? 1;
  // Centered content with colored accent bar on the side

  $currentContent = $errorMessage . $sectionContent;
  $divClass = "sectionID" . $sectionID . "ColourDiv";

  //have seperage div for each section to apply colour variables
  print("<div class=\"$divClass\">");

  // Only generate local color variations if not using page colors
  if (!$sectionColourSameAsPage) {
    $colourCombo = generateColorVariations($sectionColour, 85);
    // Start output
    print("<style>\n
    .$divClass {
      --chosen-color: $sectionColour;
    --chosen-color-lighter: {$colourCombo['lighter']};
    --chosen-color-Comp1: {$colourCombo['splitComp1']};
    --chosen-color-Comp2: {$colourCombo['splitComp2']};
    --chosen-color-Comp2-lighter: {$colourCombo['splitComp2Lighter']};
    --chosen-color-Comp1-lighter: {$colourCombo['splitComp1Lighter']};
    </style>\n ");
  }
   // Add CSS for boxes and shadows style
  print("
<style>
  
.$divClass .centredAccentSection {
	margin: 20px 0px auto;
	max-width: 1200px;
}

.$divClass .centredAccentSection .centredAccentContainer {
	display: flex;
	align-items: stretch;
	/* background-color: #fafafa; */
	border-radius: 4px;
	overflow: hidden;
	/* box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); */
}

.$divClass .centredAccentBar {
	width: 2px;
	flex-shrink: 0;
}

.$divClass .centredAccentContent {
	flex: 1;
	padding: 0px 0px;
}

.$divClass h1.centredAccentTitle {
    font-size: 24px;
    font-weight: 300;
    margin-top: 10px;
    margin-bottom: 15px;
    padding: 15px 20px;
    background: linear-gradient(to right, var(--chosen-color-Comp1-lighter) 0%, rgba(0,0,0,0.02) 100%);
    border-left: 2px solid var(--chosen-color);
    border-radius: 0 4px 4px 0;
    position: relative;
    text-align: left;
	color: var(--chosen-color);
}
.$divClass h1.centredAccentTitle a {
    text-decoration: none;
    font-weight: 300;
}

.$divClass .centredAccentContent hr {
    border: none;
    height: 2px;
    background-color: var(--chosen-color);
    margin: 20px 0px;
}

.$divClass .centredAccentText h2 {
	padding: 12px 18px;
	background: linear-gradient(to right, var(--chosen-color-Comp1-lighter) 50%, rgba(0, 0, 0, 0.01) 100%);
	border-left: 2px solid 	var(--chosen-color);
	border-radius: 2px;
	position: relative;
	text-align: left;
}
.$divClass .centredAccentText h3 {
	color: var(--chosen-color-Comp1)
}

.$divClass .centredAccentText h4 {
	color: var(--chosen-color-Comp1)
}

.$divClass .centredAccentText ul,
.$divClass .centredAccentText ol {
    margin: 15px 0;
    padding: 15px 20px 15px 40px;
    background: linear-gradient(to right, var(--chosen-color-lighter) 0%, rgba(0,0,0,0.01) 100%);
    border-left: 2px solid var(--chosen-color);
    border-radius: 0 4px 4px 0;
}

.$divClass .centredAccentText ul li,
.$divClass .centredAccentText ol li {
    margin-bottom: 8px;
    line-height: 1.6;
}

.$divClass .centredAccentText ul li:last-child,
.$divClass .centredAccentText ol li:last-child {
    margin-bottom: 0;
}

/* Mobile devices */
@media only screen and (min-width: 285px) and (max-width: 480px) {
    .$divClass .centredAccentSection {
        margin: 20px 10px;
        max-width: 100%;
    }

    .$divClass .centredAccentContent {
        padding: 20px 15px;
    }

    .$divClass .centredAccentTitle {
        font-size: 20px;
    }

    .$divClass .centredAccentText h2 {
        font-size: 20px;
        padding: 10px 12px;
        margin-top: 15px;
        border-left-width: 4px;
        
    }

    .$divClass .centredAccentText ul,
    .$divClass .centredAccentText ol {
        padding: 12px 15px 12px 35px;
        border-left-width: 3px;
    }
}

/* Tablets */
@media only screen and (min-width: 481px) and (max-width: 1024px) {
    .$divClass .centredAccentSection {
        margin: 30px 20px;
        max-width: 750px;
    }

    .$divClass .centredAccentContent {
        padding: 25px 30px;
    }

    .$divClass .centredAccentTitle {
        font-size: 22px;
    }

    .$divClass .centredAccentText h2 {
        font-size: 22px;
        padding: 11px 16px;
        border-left-width: 4px;
    }

    .$divClass .centredAccentText ul,
    .$divClass .centredAccentText ol {
        padding: 14px 18px 14px 38px;
        border-left-width: 2px;
    }
}

  </style>\n");
  // Note: If SAME_AS_PAGE, the section will use root color variables from page header

  // Add CSS to hide title if SectionShowTitle is false
  if (!$sectionShowTitle) {
    print("<style>\n  .$divClass .centredAccentTitle,\n  .$divClass .centredAccentContent hr:first-child { display: none; }\n</style>\n");
  }

  // Add floating edit button for editors/admins
  $editButton = '';
  if (accessLevelCheck("pageEditor") === true) {
    print("<style>\n  .$divClass .sectionEditButton {\n    position: absolute;\n    top: 5px;\n    left: 5px;\n    background-color: rgba(25, 118, 210, 0.7);\n    color: white;\n    border: none;\n    border-radius: 4px;\n    padding: 6px 10px;\n    font-size: 12px;\n    cursor: pointer;\n    text-decoration: none;\n    display: inline-block;\n    z-index: 100;\n    opacity: 0;\n    transition: opacity 0.3s ease;\n  }\n  .$divClass .sectionEditButton:hover {\n    background-color: rgba(25, 118, 210, 1);\n    opacity: 1;\n  }\n  .$divClass .centredAccentContainer:hover .sectionEditButton {\n    opacity: 1;\n  }\n  .$divClass .centredAccentContainer {\n    position: relative;\n  }\n</style>\n");
    $editButton = "<a href=\"../PagesAndSections/editSectionDetailsPage.php?editSectionID=$sectionID\" class=\"sectionEditButton\" title=\"Edit Section\">âœï¸ Edit</a>";
  }

  printf("
  <section class=\"mainContent\">
    <section class=\"centredAccentSection\">
      <div class=\"centredAccentContainer\">
        %s
        <div class=\"centredAccentBar\" ></div>
        <div class=\"centredAccentContent\">
        <hr>
          <h1 class=\"centredAccentTitle\" >%s</h1>
          <div class=\"centredAccentText\" >%s</div>
        </div>
      </div>
    </section>
  </section>
  ", $editButton, $title, $currentContent);

  print("<div style=\"clear: both;\"></div>\n");
  print("</div>\n");
  return;
}
?>