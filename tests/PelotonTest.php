<?php

require_once __DIR__ . '/../peloton/src/peloton/Peloton.php';

use PHPUnit\Framework\TestCase;

class MockClass
{
    public $attribute_1;

    public $attribute_2;
}

class PelotonTest extends TestCase
{
    private $appInstance;

    protected function setUp()
    {
        $appConfigFilePath = __DIR__ . '/fixtures/config/app-config.yml';
        $navFilePath =       __DIR__ . '/fixtures/nav.yml';
        $routesFilePath =    __DIR__ . '/fixtures/routes.yml';
        $this->appInstance = new \Peloton\Peloton([
            "configs" => [
                $appConfigFilePath,
            ],
            "nav" => $navFilePath,
            "routes" => $routesFilePath,
        ]);
    }

    public function testCreation()
    {
        // the router is an instance of Klein
        $this->assertInstanceOf(Klein\Klein::class, $this->appInstance->router);

        // template is an instance of Twig
        $this->assertInstanceOf(\Twig_Environment::class, $this->appInstance->template);

        // response is an instance of \CEHD\App\BaseResponse
        $this->assertInstanceOf(\CEHD\App\Response\BaseResponse::class, $this->appInstance->response);

        // the config is properly set
        $this->assertTrue($this->appInstance->config['debug']);
        $this->assertEquals("/assets", $this->appInstance->config['asset_base']);

        // nav is set
        $this->assertTrue(is_array($this->appInstance->nav_items));
        $this->assertEquals(8 ,count($this->appInstance->nav_items));

        $names = [
            "Home",
            "Programs & Degrees",
            "Current Students",
            "People",
            "Research",
            "Giving",
            "Alumni",
            "About",
        ];

        // the nav has the expected structure
        foreach(range(0, 7) as $i)
        {
            $this->assertEquals($names[$i] ,$this->appInstance->nav_items[$i]["name"]);
        }
    }

    public function testAlternateConfig()
    {
        $appConfigFilePath = __DIR__ . '/fixtures/config/app-config.yml';
        $yaConfigFilePath  = __DIR__ . '/fixtures/config/ya-config.yml';
        $navFilePath       = __DIR__ . '/fixtures/nav.yml';
        $routesFilePath    = __DIR__ . '/fixtures/routes.yml';
        $appInstance = new \Peloton\Peloton([
            "configs" => [
                $appConfigFilePath,
                $yaConfigFilePath,
            ],
            "nav" => $navFilePath,
            "routes" => $routesFilePath,
        ]);

        $this->assertEquals("/new/base/assets", $appInstance->config['asset_base']);
    }

    public function testHyphenate()
    {
        $hyphenated = $this->appInstance->hyphenate("This is a title with parentheses (parenthetical)");
        $expected_result = "this-is-a-title-with-parentheses-parenthetical";
        $this->assertEquals($expected_result, $hyphenated);
    }

    public function testStripDotHtml()
    {
        $stripped = $this->appInstance->stripDotHtml("HtmlFile.html");
        $expected_result = "HtmlFile";
        $this->assertEquals($expected_result, $stripped);

        $lowercase_result = $this->appInstance->stripDotHtml("HtmlFile.html", true);
        $expected_result = "htmlfile";
        $this->assertEquals($expected_result, $lowercase_result);

        $expected_result = "HtmlFilePath/";
        $no_dot_html_result = $this->appInstance->stripDotHtml($expected_result, true);
        $this->assertEquals($expected_result, $no_dot_html_result);
    }

    public function testPathToArray()
    {
        $path = "/edpsych-twig/people/";
        $path_array = $this->appInstance->pathToArray($path);
        $this->assertTrue(is_array($path_array));
        $this->assertEquals("2", count($path_array));
    }

    public function testGetLastFromPath()
    {
        $path = "/edpsych-twig/people/";
        $expected_result = "people";
        $actual_result = $this->appInstance->getLastFromPath($path);
        $this->assertEquals($expected_result, $actual_result);
    }

    public function testIsIterable()
    {
        $xmlStr = file_get_contents(__DIR__ . '/fixtures/xml/person_1.xml');
        $classInstance = new MockClass();

        $classInstance->attribute_1 = "foo";
        $classInstance->attribute_2 = "bar";

        // strings will iterate, but not in a way we expect so we want false
        $this->assertFalse($this->appInstance->isIterable("string"));

        // booleans are not iterable
        $this->assertFalse($this->appInstance->isIterable(false));

        // integers and floats are not iterable
        $this->assertFalse($this->appInstance->isIterable(0));
        $this->assertFalse($this->appInstance->isIterable(1.1));

        // neither is null
        $this->assertFalse($this->appInstance->isIterable(null));

        // arrays and classes will work in a foreach loop
        $this->assertTrue($this->appInstance->isIterable([1, 2, 3]));
        $this->assertTrue($this->appInstance->isIterable($classInstance));
    }

    public function testParseXMLErrors()
    {
        $errors = null;
        libxml_use_internal_errors(true);

        $invalidXMLStr = "<xml><open></xml>";

        // produce an XML parsing error
        $doc = simplexml_load_string($invalidXMLStr, null, LIBXML_NOCDATA);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        $results = $this->appInstance->parseXMLErrors($errors);
        $this->assertTrue(is_array($results));
        $this->assertFalse(empty($errors));
        $this->assertEquals(2, count($results));
        $this->assertTrue(strpos($results[0], "Opening and ending tag mismatch") > 0);
    }

    public function testGet()
    {
        $expected = null;
        $this->assertEquals($expected, $this->appInstance->get("does_not_exist"));
        $expected = "default";
        $this->assertEquals($expected, $this->appInstance->get("does_not_exist", "default"));
        $this->assertTrue(is_array($this->appInstance->get("config")));
    }

    public function testBuildHyphenatedKeys()
    {
        $yamlData = $this->appInstance->loadYaml(__DIR__ . '/fixtures/yaml.yml');
        $hyphenatedData = $this->appInstance->buildHyphenatedKeys($yamlData);
        $expected = "Education Policy K-12";
        $expected_key = $expected;
        $expected_hyphenated = $this->appInstance->hyphenate($expected);
        $this->assertArrayHasKey($expected, $yamlData);
        $this->assertArrayHasKey($expected_hyphenated, $hyphenatedData);
        $this->assertEquals($expected, $hyphenatedData[$expected_hyphenated]["keyTitle"]);
    }

    public function testCacheBusterQueryString()
    {
        $query_string = substr($this->appInstance->getCacheBusterQueryString(__DIR__ . "/fixtures/empty.txt"), 0, 3);
        $expected = "?v=";
        $this->assertEquals($expected, $query_string);
    }

    public function testCacheBusterQueryStringTriggersErrorIfNoFile()
    {
        // Test that invalid file path triggers an error
        $this->expectException(PHPUnit_Framework_Error::class);
        $result = $this->appInstance->getCacheBusterQueryString("/does/not/exist.txt");
    }

    public function testCreatHyphenatedKeys()
    {
        $categories = [
            "foo bar",
            "bat baz",
        ];

        $result = $this->appInstance->createHyphenatedKeys($categories);
        $this->assertArrayHasKey("foo-bar", $result);
        $this->assertEquals($categories[0], $result["foo-bar"]);
    }

    public function testArrayContains()
    {
        $categories = [
            "key" => "value"
        ];
        $result = $this->appInstance->arrayContains("key", $categories);

        $this->assertEquals(true, $result);

        $result = $this->appInstance->arrayContains("nokey", $categories);
        $this->assertEquals(false, $result);
    }

    public function testIsDigit()
    {
        $result = $this->appInstance->isDigit("string");
        $expected_result = false;
        $this->assertEquals($expected_result, $result);

        $result = $this->appInstance->isDigit("1940");
        $expected_result = true;
        $this->assertEquals($expected_result, $result);
    }

    public function testToInt()
    {
        $result = $this->appInstance->toInt("4");
        $expected_result = 4;
        $this->assertEquals($expected_result, $result);

        $result = $this->appInstance->toInt("0");
        $expected_result = 0;
        $this->assertEquals($expected_result, $result);

        $this->expectException(\Exception::class);
        $result = $this->appInstance->toInt("string");
        $expected_result = 4;
        $this->assertEquals($expected_result, $result);

        $this->expectException(\Exception::class);
        $result = $this->appInstance->toInt(null);
        $expected_result = 4;
        $this->assertEquals($expected_result, $result);
    }

    public function testInRange()
    {
        $result = $this->appInstance->inRange(1984, 1980, 1989);
        $expected_result = true;
        $this->assertEquals($expected_result, $result);

        $result = $this->appInstance->inRange(1980, 1980, 1989);
        $expected_result = true;
        $this->assertEquals($expected_result, $result);

        $result = $this->appInstance->inRange(1990, 1980, 1989);
        $expected_result = false;
        $this->assertEquals($expected_result, $result);
    }

    public function testToDeca()
    {
        $year = 1966;
        $expected_result = 1960;

        $this->assertEquals($expected_result, $this->appInstance->toDeca($year));
    }

    public function testStripStyleAttributes()
    {
        $original_tag = '<p style="some: style; another: style">';
        $expected_result = "<p >";
        $this->assertEquals($expected_result, $this->appInstance->stripStyleAttributes($original_tag));
    }
}
