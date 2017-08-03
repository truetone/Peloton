<?php
/**
 * @author Tony Thomas <thoma127@umn.edu>
 * @license GPL 3.0
 * @package peloton
 * @version 0.1.0
 *
 * The Base class to hold common methods
 */

namespace Peloton\Base;
require_once __DIR__ . "/../../../autoload.php";
require_once __DIR__ . "/config/Config.php";

/**
 * Base class
 */
abstract class BaseClass
{
    /**
     * Class properties
     */

    /**
     * Array of file error messages
     *
     * @type array
     */
    protected $_errors = [];

    /**
     * Clears errors
     */
    protected function _clearErrors()
    {
        $this->_errors = [];
    }

    /**
     * Handles Twig setup
     * @param boolean $debug - debug mode for Twig
     * @return object Twig_Environment
     */
    public function bootstrapTwig($templates_dir, $debug=false)
    {
        $loader = new \Twig_Loader_Filesystem($templates_dir);
        $twig = new \Twig_Environment($loader, [
            "cache" => __DIR__ . "/../../../../cache",
            "auto_reload" => true, // this setting forces the templates to recompile if they are modified
            "debug" => $debug,
            "autoescape" => true,
        ]);

        if ($debug)
        {
            $twig->addExtension(new \Twig_Extension_Debug());
        }
        return $twig;
    }

    /**
     * Handles Guzzle http client setup
     * @param array $params - Guzzle params
     * @return object Guzzle
     */
    public function bootstrapHTTPClient($params)
    {
        return new \GuzzleHttp\Client($params);
    }

    /**
     * Handles Config object setup
     * @param array $yaml_file_paths - an array of paths to yaml files
     * @return object Config
     */
    public function bootstrapConfig($yaml_file_paths)
    {
        $config = new \CEHD\App\Config\Config;
        return $config->load($yaml_file_paths);
    }

    /**
     * Convenience method for hyphenating phrases
     * @param string $str - a short phrase
     * @return string - a hyphenated version of the phrase
     */
    public function hyphenate($str, array $noStrip = [])
    {
        // http://www.mendoweb.be/blog/php-convert-string-to-hyphenated-string/
        // non-alpha and non-numeric characters become hyphens
        $str = preg_replace('/[^a-z0-9' . implode("", $noStrip) . ']+/i', ' ', $str);
        $str = trim($str);
        $str = str_replace(" ", "-", $str);
        $str = strtolower($str);

        return $str;
    }

    /**
     * Converts a file path to an array of its components
     * @param string $path - a path separated by "/"
     * @return array - the path components
     */
    public function pathToArray($path)
    {
        $path = ltrim($path, "/");
        $path = rtrim($path, "/");
        return explode("/", $path);
    }

    /**
     * Gets the last word in a path
     * @param string $path - a path separated by "/"
     * @return string - the last word in the path
     */
    public function getLastFromPath($path)
    {
        $path_array = $this->pathToArray($path);
        return array_pop($path_array);
    }

    /**
     * Strips ".html" off the end of a path
     * @param string $uri - a path separated by "/"
     * @return string - the last word in the path
     */
    public function stripDotHtml($uri, $lowercase=false)
    {
        if (strrpos($uri, ".html") > 0)
        {
            // strip .html (last 5 characters)
            $stripped = substr($uri, 0, -5);

            if ($lowercase)
            {
                $stripped = strtolower($stripped);
            }
            return $stripped;
        }

        return $uri;
    }

    /**
     * Parses a YAML file into an associative array
     * @param string $file_path - a valid file path
     * @return array - an associative array of the data in the YAML file
     */
    public function loadYaml($file_path)
    {
        $yaml = new \Symfony\Component\Yaml\Yaml;
        return $yaml::parse(file_get_contents($file_path));
    }

    /**
     * Tests whether something is iterable, i.e., it's OK to use it in a
     * foreach loop
     * @param mixed $obj - the thing to test
     * @return boolean - whether the thing is iterable
     */
    public function isIterable($obj)
    {
        return is_array($obj) || is_object($obj) || $obj instanceof \Traversable;
    }

    /**
     * Creates errors messages from a simplexml_load_string error object
     * @param object $errors - error object from simplexml_load_string
     * @return array - an array of formatted error messages
     */
    public function parseXMLErrors($errors)
    {
        $error_msgs = [];

        foreach($errors as $error)
        {
            $msg = str_repeat('-', $error->column) . "^\n";

            switch ($error->level) {
                case LIBXML_ERR_WARNING:
                    $msg .= "Warning " . $error->code . ": ";
                    break;
                case LIBXML_ERR_ERROR:
                    $msg .= "Error " . $error->code . ": ";
                    break;
                case LIBXML_ERR_FATAL:
                    $msg .= "Fatal Error " . $error->code .": ";
                    break;
            }

            $msg .= trim($error->message) .
                "\n  Line: $error->line" .
                "\n  Column: $error->column";

            if ($error->file) {
                $msg .= "\n  File: $error->file";
            }

            array_push($error_msgs, $msg);
        }

        return $error_msgs;
    }

    /**
     * Gets a property value if the property exists; returns $default otherwise
     * @param string $property_name - the name of the property
     * @param mixed $default - the default value to return if the property doesn't exist
     * @return mixed - property value or default if the property doesn't exist
     */
    public function get($property_name, $default=null)
    {
        if (property_exists($this, $property_name))
        {
           return $this->$property_name;
        }
        return $default;
    }

    /**
     * Takes an associative array that has real-language keys and builds a new
     * array with hyphenated keys based on the original keys
     *
     * This is well-suited for data in YAML files that have natural language
     * keys for a list of objects
     * @param array $original_data
     * @return array
     */
    public function buildHyphenatedKeys($original_data)
    {
        $new_data = [];

        foreach ($original_data as $key => $data)
        {
            $hyphenated = $this->hyphenate($key);
            $new_data[$hyphenated] = $data;
            $new_data[$hyphenated]["keyTitle"] = $key;
        }
        return $new_data;
    }

    /**
     * Returns a Unix timestamp of the date and time a file was modified
     *
     * @param string - $file_path
     * @return mixed - int if file path is valid, otherwise false
     */
    public function getFileModTime($file_path)
    {
        $result = false;

        try
        {
            $result = filemtime($file_path);
        }
        catch (Exception $e)
        {
            echo $e->getMessage();
        }

        return $result;
    }

    /**
     * Returns a query string suitable for cachebusting
     *
     * @param string - $file_path
     * @return string
     */
    public function getCacheBusterQueryString($file_path)
    {
        $mod_time = $this->getFileModTime($file_path);

        if (!$mod_time)
        {
            $msg = "Unable to determine mod time for " . $file_path .
                ". Using current timestamp which will always brake the " .
                "cache. Check to make sure your file_path is correct";
            throw new Exception($msg);
            $mod_time = strtotime("now");
        }
        return "?v=" . $mod_time;
    }

    /**
     * Takes an array with elements that are phrases and creates hyphenated
     * keys based on the phrase
     *
     * @param array - $data
     * @return array
     */
    public function createHyphenatedKeys($data)
    {
        $results = [];
        foreach ($data as $index => $datum)
        {
            $hyphenated = $this->hyphenate($datum);
            $results[$hyphenated] = $datum;
        }
        return $results;
    }

    /**
     * Convenience method to check if an array has a key
     *
     * @param string - $needle
     * @return boolean
     */
    public function arrayContains($needle, $haystack)
    {
       return array_key_exists($needle, $haystack);
    }

    public function isDigit($string)
    {
        return ctype_digit($string);
    }

    public function toInt($string)
    {
        $int = (int)$string;

        if ($int === 0 and $string !== "0")
        {
            throw new \Exception("Received '" . $string . "' which "
                . "can't be converted to an integer.");
        }
        else
        {
            return $int;
        }
    }

    // @todo write some type exception classes and use them here
    public function inRange($num, $min, $max)
    {
        return ($num >= $min && $num <= $max);
    }

    public function toDeca($num)
    {
        return floor($num / 10) * 10;
    }

    public function stripStyleAttributes($tag)
    {
        $pattern = "/style=(\".*?\"|\'.*?\'|[^\"'][^\s]*)/i";
        $replacement = "";
        return preg_replace($pattern, $replacement, $tag);
    }
}
