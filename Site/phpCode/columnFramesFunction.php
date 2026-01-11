<?php
function printColumnFramesSection($sectionContent, $errorMessage, $title, $sectionID, $showEditButton = 1)
{
	// Card grid layout with h1/h2 headings creating new cards
	// Get some data from the session variables:
	$sectionColour = $_SESSION["sectionDB"][$sectionID]["SectionColour"] ?? "#b3b3b3";
	$sectionColourSameAsPage = $_SESSION["sectionDB"][$sectionID]["SectionColourSameAsPage"] ?? 0;
	$sectionShowTitle = $_SESSION["sectionDB"][$sectionID]["SectionShowTitle"] ?? 1;
	$divClass = "sectionID" . $sectionID . "ColourDiv";

	// Have separate div for each section to apply colour variables
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
  </style>\n";
	}

	// Always print structural CSS regardless of color settings
	print "<style>\n
  .$divClass .columnFramesCardGrid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
  }

  /* Limit to 3 columns for medium screens */
  @media (min-width: 900px) {
    .$divClass .columnFramesCardGrid {
    grid-template-columns: repeat(auto-fit, minmax(max(280px, calc(33.333% - 14px)), 1fr));
  }


  /* Allow 4 columns on very wide screens */
  @media (min-width: 1400px) {
    .$divClass .columnFramesCardGrid {
    grid-template-columns: repeat(auto-fit, minmax(max(280px, calc(25% - 15px)), 1fr));
  }
  }

  .$divClass .columnFramesCard {
    background-color: var(--chosen-color-lighter);
    border-top: 2px solid var(--chosen-color);
    border-bottom: 1px solid var(--chosen-color) ;
    border-radius: 0px;
    padding: 0;
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .$divClass .columnFramesCard .columnFramesCardHeader {
    padding: 15px;
    background-color: var(--chosen-color-Comp1-lighter);
    color: var(--chosen-color) ;
    border-bottom: 2px solid var(--chosen-color);
  }
  .$divClass .columnFramesCard .columnFramesCardHeader h1 {
    text-align: center;
    margin: 0;
    font-size: 24px;
    font-weight: 300;
  }

  .$divClass .columnFramesTitleBar h1.sectionTitle {
    font-family: Roboto, sans-serif;
    font-weight: 400;
    font-size: 22px;
    margin: 6px 0px;
    color: var(--chosen-color);
    text-transform: uppercase;
    text-align: center;
    border-top: 2px solid var(--chosen-color);
    padding-top: 15px;
  }

  .$divClass .columnFramesTitleBar h1.sectionTitle a{
  text-decoration: none;
  }

  .$divClass .columnFramesCard .columnFramesCardBody aside,
  .$divClass .columnFramesCard .columnFramesCardBody aside.leftAside {
    width: 80%;
    margin: auto;
    font-size: 12px;
    font-family: 'Open Sans', sans-serif;
    }

  .$divClass .columnFramesCard .columnFramesCardBody aside h3,
  .$divClass .columnFramesCard .columnFramesCardBody aside h4,
  .$divClass .columnFramesCard .columnFramesCardBody aside.leftAside h3,
  .$divClass .columnFramesCard .columnFramesCardBody aside.leftAside h4 {
    font-size: 16px;
  }

  .$divClass .columnFramesCard .columnFramesCardBody aside ul li,
  .$divClass .columnFramesCard .columnFramesCardBody aside ol li,
  .$divClass .columnFramesCard .columnFramesCardBody aside.leftAside ul li,
  .$divClass .columnFramesCard .columnFramesCardBody aside.leftAside ol li{
    font-size: 14px;
  }

  .$divClass .columnFramesCard .columnFramesCardBody h3 {
    margin: 0 0 10px 0;
    font-size: 20px;
    font-weight: 400;
    text-align: center;
    border-top: 1px solid var(--chosen-color-Comp1);
    padding-top: 6px;
    border-bottom: 1px solid var(--chosen-color-Comp1);
    padding-bottom: 6px;
  }
  .$divClass .columnFramesCard .columnFramesCardBody h4 {
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 400;
    text-align: center;
    border-bottom: 1px solid var(--chosen-color-Comp1);
    padding-bottom: 6px;
  }

  .$divClass .columnFramesCardBody {
    font-size: 14px;
    font-family: 'Open Sans', sans-serif;
    padding: 15px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    background-color: var(--chosen-color-lighter);
  }
  .$divClass .columnFramesCardBody p {
    font-size: 14px;
    font-family: 'Open Sans', sans-serif;
    padding: 0 0 0 10px;
    background-color: var(--chosen-color-lighter);
  }
  .$divClass .columnFramesCardBody figure.insertedImage{
    margin-right: 0px;
  }
  .$divClass .columnFramesCardBody img{
    margin: auto;
    max-width: 100%;
  }
  </style>\n";

	// Add CSS to hide title if SectionShowTitle is false
	if (!$sectionShowTitle) {
		print "<style>\n  .$divClass .columnFramesTitleBar { display: none; }\n</style>\n";
	}

	// Add floating edit button for editors/admins
	if (accessLevelCheck("pageEditor") === true && $showEditButton) {
		$editButton = insertFloatingEditButton($sectionID);
	} else {
		$editButton = "";
	}

	// Add CSS to hide title if SectionShowTitle is false
	if (!$sectionShowTitle) {
		print "<style>\n  .$divClass .columnFramesTitleBar { display: none; }\n</style>\n";
	}

	// Parse content for h1 and h2 headings to create cards
	$sections = [];
	$allContent = $errorMessage . $sectionContent;

	// Split content by h1 and h2 tags
	$parts = preg_split("/(<h[12]>.*?<\/h[12]>)/is", $allContent, -1, PREG_SPLIT_DELIM_CAPTURE);

	// Process the parts
	$beforeFirstHeading = "";
	for ($i = 0; $i < count($parts); $i++) {
		if (preg_match("/<h[12]>(.*?)<\/h[12]>/is", $parts[$i], $matches)) {
			// This is an h1 or h2 tag
			$headingContent = $matches[1];
			// Get content after this heading (if exists)
			$afterHeadingContent = isset($parts[$i + 1]) ? $parts[$i + 1] : "";
			$i++; // Skip the next part as we've already used it

			$sections[] = [
				"type" => "heading",
				"heading_content" => $headingContent,
				"content" => $afterHeadingContent,
			];
		} elseif ($i == 0) {
			// Content before first heading
			$beforeFirstHeading = $parts[$i];
		}
	}

	// Display section title
	printf(
		"
  <section class=\"mainContent\">
    <section class=\"columnFramesWrapper\">
      %s
      <div class=\"columnFramesTitleBar\">
        <h1 class=\"sectionTitle\">%s</h1>
      </div>",
		$editButton,
		$title,
	);

	// If no headings found, display content in single card
	if (empty($sections)) {
		print "<div class=\"columnFramesCardGrid\">
          <div class=\"columnFramesCard\">
            <div class=\"columnFramesCardBody\">
              $beforeFirstHeading
            </div>
          </div>
        </div>";
	} else {
		// Display cards grid
		print "<div class=\"columnFramesCardGrid\">";

		// Display content before first heading if any
		if (!empty(trim($beforeFirstHeading))) {
			print "<div class=\"columnFramesCard\">
              <div class=\"columnFramesCardBody\">
                $beforeFirstHeading
              </div>
            </div>";
		}

		foreach ($sections as $section) {
			$headingText = htmlspecialchars(strip_tags($section["heading_content"]), ENT_QUOTES, "UTF-8");
			$cardContent = $section["content"];

			print "<div class=\"columnFramesCard\">
              <div class=\"columnFramesCardHeader\">
                <h1>$headingText</h1>
              </div>
              <div class=\"columnFramesCardBody\">
                $cardContent
              </div>
            </div>";
		}

		print "</div>";
	}

	print "
    </section>
  </section>
  <div style=\"clear: both;\"></div>";
	print "</div>";
	return;
}
