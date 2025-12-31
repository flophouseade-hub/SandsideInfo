<?php
function printBoxesAndShadowsSection($sectionContent, $errorMessage, $title, $sectionID)
{
	// Modern box layout with colored left borders on headings, similar to course cards
	$sectionColour = $_SESSION["sectionDB"][$sectionID]["SectionColour"] ?? "#808080";
	$sectionColourSameAsPage = $_SESSION["sectionDB"][$sectionID]["SectionColourSameAsPage"] ?? 0;
	$sectionShowTitle = $_SESSION["sectionDB"][$sectionID]["SectionShowTitle"] ?? 1;

	$divClass = "sectionID" . $sectionID . "ColourDiv";

	// Generate color variations if not using page colors
	if (!$sectionColourSameAsPage) {
		$colourCombo = generateColorVariations($sectionColour, 95);
		print "<style>\n
  .$divClass {
    --chosen-color: $sectionColour;
    --chosen-color-lighter: {$colourCombo['lighter']};
    --chosen-color-Comp1: {$colourCombo['splitComp1']};
    --chosen-color-Comp2: {$colourCombo['splitComp2']};
    --chosen-color-Comp2-lighter: {$colourCombo['splitComp2Lighter']};
    --chosen-color-Comp1-lighter: {$colourCombo['splitComp1Lighter']};
    }
    </style>\n ";
	}

	// Add CSS for boxes and shadows style
	print "<style>
/*   .$divClass .boxesAndShadowsSection {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
  } */

  .$divClass .headerBox h2, .$divClass .headerBox h1 {
    font-family: Roboto, sans-serif;
    font-weight: 300;
    font-size: 0.9em;
    margin-top: 6px;
    margin-bottom: 0;
  }
  .$divClass .headerBox h1 {
    font-size: 1.0em;
    letter-spacing: 1px;
  }  
  .$divClass .headerBox h5, .$divClass .headerBox h6{
  font-size: 0.9em;
    font-weight: 400;
    margin-top: 3px;
    margin-bottom: 3px;
    color: var(--chosen-color-Comp1);
  }
    .$divClass .headerBox h6{
    color: var(--chosen-color-Comp2);
  }
.$divClass .contentBox .insertedImage {
    max-width: 100%;
    height: auto;
    margin: 0px 0px 15px 20px;
    padding: 12px;
    background: var(--chosen-color-lighter);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }
  
  .$divClass .boxesAndShadowsTitle {
    font-family: 'Roboto', sans-serif;
    font-weight: 400;
    font-size: 1.6em;
    letter-spacing: 1px;
    margin-bottom: 0px;
    padding: 20px;
    padding-left: 26px;
    background-color: #f5f5f5;
    border-left: 6px solid var(--chosen-color);
    border-radius: 4px;
  }
  
  .$divClass .headerBox {
    margin: 30px 0 0 0;
    padding: 15px 20px;
    padding-left: 26px;
    background-color:var(--chosen-color-Comp1-lighter);
    // background-color: #f8f9fa;
    border-left: 6px solid var(--chosen-color);
    border-radius: 4px 4px 0 0;
    font-size: 1.5em;
    font-weight: 600;
  }
  
  .$divClass .headerBox:first-child {
    margin-top: 0;
  }
  
  .$divClass .headerBox .subheading {
    font-size: 0.7em;
    font-weight: 400;
    margin-top: 8px;
    color: #666;
    display: block;
  }
  
  .$divClass .contentBox {
    background: white;
    padding: 30px;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
  }
  
  .$divClass .contentBox p {
    line-height: 1.6;
    margin: 15px 0;
    color: #333;
  }
  
  .$divClass .contentBox p:first-child {
    margin-top: 0;
  }

  
  .$divClass .contentBox p:last-child {
    margin-bottom: 0;
  }
  
  .$divClass .contentBox ul,
  .$divClass .contentBox ol {
    margin: 15px 0;
    padding: 6px 40px;
    background: var(--chosen-color-lighter);
    border-radius: 8px;
  }
  
  .$divClass .contentBox li {
    margin: 8px 0;
    line-height: 1.6;
  }
  
  .$divClass .contentBox h3,
  .$divClass .contentBox h4,
  .$divClass .contentBox h5 {
    margin: 20px 0 10px 0;
    color: var(--chosen-color);
  }
  .$divClass .contentBox h3, .$divClass .contentBox h4 {
    font-size: 1.1em;
    font-weight: 500;
    color: var(--chosen-color);
  }
    .$divClass .contentBox h3 {
    color: var(--chosen-color-Comp1);
}
  </style>\n";

	// Add CSS to hide title if SectionShowTitle is false
	if (!$sectionShowTitle) {
		print "<style>\n  .$divClass .boxesAndShadowsTitle { display: none; }\n</style>\n";
	}

	// Add floating edit button for editors/admins
	$editButton = "";
	if (accessLevelCheck("pageEditor") === true) {
		print "<style>\n  
    .$divClass .sectionEditButton {\n    
    position: absolute;\n    
    top: 10px;\n    right: 10px;\n    
    background-color: rgba(25, 118, 210, 0.7);\n    
    color: white;\n    border: none;\n    border-radius: 4px;\n    
    padding: 6px 10px;\n    font-size: 12px;\n    cursor: pointer;\n    
    text-decoration: none;\n    
    display: inline-block;\n    
    z-index: 100;\n    
    opacity: 0;\n    transition: opacity 0.3s ease;\n  }\n  
    .$divClass .sectionEditButton:hover {\n    background-color: rgba(25, 118, 210, 1);\n    opacity: 1;\n  }\n  
    .$divClass .boxesAndShadowsSection:hover .sectionEditButton {\n    opacity: 1;\n  }\n  
    .$divClass .boxesAndShadowsSection {\n    position: relative;\n  }\n</style>\n";

		$editButton = "<a href=\"../PagesAndSections/editSectionDetailsPage.php?editSectionID=$sectionID\" class=\"sectionEditButton\" title=\"Edit Section\">âœï¸ Edit</a>";
	}

	// Parse content for h1 and h2 headings to create boxes
	$boxes = [];
	$allContent = $errorMessage . $sectionContent;

	// Split content by h1 and h2 tags
	$parts = preg_split("/(<h[12]>.*?<\/h[12]>)/is", $allContent, -1, PREG_SPLIT_DELIM_CAPTURE);

	// Process the parts
	$beforeFirstHeading = "";
	for ($i = 0; $i < count($parts); $i++) {
		if (preg_match("/<h[12]>(.*?)<\/h[12]>/is", $parts[$i], $matches)) {
			// This is an h1 or h2 tag - keep the full tag
			$headingContent = $parts[$i];
			// Get content after this heading (if exists)
			$afterHeadingContent = isset($parts[$i + 1]) ? $parts[$i + 1] : "";
			$i++; // Skip the next part as we've already used it

			// Check if content starts with h5 or h6 (subheading)
			$subheading = "";
			if (preg_match("/^\s*(<h[56]>.*?<\/h[56]>)/is", $afterHeadingContent, $subMatches)) {
				// Extract the subheading - keep the full tag
				$subheading = $subMatches[1];
				// Remove it from the content
				$afterHeadingContent = preg_replace("/^\s*<h[56]>.*?<\/h[56]>/is", "", $afterHeadingContent, 1);
			}

			$boxes[] = [
				"heading_content" => $headingContent,
				"subheading" => $subheading,
				"content" => $afterHeadingContent,
			];
		} elseif ($i == 0) {
			// Content before first heading
			$beforeFirstHeading = $parts[$i];
		}
	}

	// Start output
	printf(
		"
  <div class=\"mainContent\">
  <div class=\"$divClass\">
  <section class=\"boxesAndShadowsSection\">
    %s
    <h1 class=\"boxesAndShadowsTitle\">%s</h1>
    ",
		$editButton,
		$title,
	);

	// If no headings found, display all content in single box
	if (empty($boxes)) {
		if (!empty(trim($allContent))) {
			print "<div class=\"contentBox\">$allContent</div>";
		}
	} else {
		// Display content before first heading if any
		if (!empty(trim($beforeFirstHeading))) {
			print "<div class=\"contentBox\">$beforeFirstHeading</div>";
		}

		// Display each heading with its content box
		foreach ($boxes as $box) {
			$headingText = $box["heading_content"];
			$subheading = $box["subheading"];
			$boxContent = $box["content"];

			print "<div class=\"headerBox\">";
			print $headingText;
			if (!empty($subheading)) {
				print "<span class=\"subheading\">$subheading</span>";
			}
			print "</div>";

			if (!empty(trim($boxContent))) {
				print "<div class=\"contentBox\">$boxContent</div>";
			}
		}
	}

	print "
  </section>
  <div style=\"clear: both;\"></div>
  </div>
  ";

	print "</div>";
	return;
}
?>
