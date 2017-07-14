<?php
/**
 * @author Tony Thomas <thoma127@umn.edu>
 * @license GPL 3.0
 * @package CEHD App
 *
 * Extends Klein::Response
 *
 * This holds methods that are convenient for responses
 *
 */
namespace CEHD\App\Response;

require_once __DIR__ . '/../../../vendor/autoload.php';

/**
 * Custom Response class with extra methods
 */
class BaseResponse extends \Klein\Response
{
    /**
     * Class methods
     */

    /**
     * Converts XML to JSON
     *
     * @param string $xml_string - A valid XML string
     * @deprecated  - Use BaseClass::toJSON instead
     */
    public function xmlToJSON($xml_string)
    {
        trigger_error("BaseResponse::xmlToJSON is deprecated and will soon go away. Use BaseClass::toJSON instead.");
        $xml = simplexml_load_string($xml_string);
        return json_encode($xml);
    }

    /**
     * Converts a string to a simplxml object
     *
     * @param string $xml_string - A valid XML string
     * @deprecated  - Use XML::xmlStrToObject instead
     */
    public function xmlStrToObject($xml_string)
    {
        trigger_error("BaseResponse::xmlStrToObject is deprecated and will soon go away. Use XML::xmlStrToObject instead.");
        return simplexml_load_string(
            $xml_string,
            null,
            LIBXML_NOCDATA);
    }

    /**
     * Sends a JSON response with the correct headers
     *
     * @param string $json - A JSON string
     */
    public function sendJSONResponse($json)
    {
        $this->header('Content-Type', 'application/json');
        $this->body($json);
        $this->send();
    }
}
