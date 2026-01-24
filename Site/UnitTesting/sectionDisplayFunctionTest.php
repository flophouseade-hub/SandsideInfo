<?php

use PHPUnit\Framework\TestCase;

require_once "/../phpCode/sectionDisplayFunctions.php";

class ContentParserTest extends TestCase
{
	public function test_plain_text_is_returned_unchanged()
	{
		$input = "<imageL 13,150,100,0,1/><p>This is a test paragraph.</p>";

		$output = insertPageSectionOneColumn($input, "Test Title", 3, $showEditButton = 1);

		$this->assertSame($input, $output);
	}
}

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../sectionDisplayFunctions.php";

class InsertPageSectionOneColumnTest extends TestCase
{
	public function test_imageL_tag_is_converted_to_image_html()
	{
		$input = "<imageL 12,100,100,0,0/>";

		$output = insertPageSectionOneColumn($input, "Test Title", 3, 1);

		// We assert *behaviour*, not exact formatting
		$this->assertStringContainsString('<figure class="insertedImage"', $output);
		$this->assertStringContainsString("<img", $output);
		$this->assertStringContainsString('width="100"', $output);
		$this->assertStringContainsString('height="100"', $output);
		$this->assertStringContainsString("realphone.jfif", $output);
	}
}

?>
