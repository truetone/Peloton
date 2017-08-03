<?php
/**
 * @author Tony Thomas <thoma127@umn.edu>
 * @license GPL 3.0
 * @package peloton
 *
 * The Base App for maintaining routes and templates
 *
 * This is a base class that sets up a lot of functionality that is common
 * across sites. Do not add site-specific code here. Create a new class to
 * extend this class instead.
 *
 */
namespace Peloton;

require_once __DIR__ . '/../Base.php';
require_once __DIR__ . '/BaseResponse.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Klein;
use UMN\CEHD\Dict;

/**
 * Peloton class
 *
 * This class requires paths to config files formatted as YAML.
 *
 * Instantiate the app by passing in an array containing paths to said YAML
 * files.
 *
 * @example routes/routes.php
 */
class Peloton extends \Peloton\Base\BaseClass
{
    /**
     * Class properties
     */

    /**
     * Array of file paths
     *
     * @type array
     */
    protected $file_paths;

    /**
     * Dict object containing routes
     *
     * @type Dict
     */
    protected $_routes;

    /**
     * Dict object containing redirects in lowercase format
     *
     * @type Dict
     */
    protected $_redirects;

    /**
     * Dict object containing redirects in camelCase for PascalCase format
     *
     * @type Dict
     */
    public $_redirects_camelcase;

    /**
     * Route handler
     *
     * @type Klein
     */
    public $router;

    /**
     * App config info
     *
     * @type Dict
     */
    public $config;

    /**
     * Navigation items
     *
     * @type array
     */
    public $nav_items;

    /**
     * Current copyright year
     *
     * @type string
     */
    public $copyright_yr;

    /**
     * Minified styles to inject into template head for page speed
     *
     * @type string
     */
    public $inline_styles;

    /**
     * Minified SVG styles to inject into inline SVGs
     *
     * @type string
     */
    public $inline_svg_styles;

    /**
     * Template engine
     *
     * @type Twig
     */
    public $template;

    /**
     * Response class
     *
     * @type Klein\Response
     */
    public $response;

    /**
     * Methods
     */

    /**
     * Constructor
     *
     * Create a new BaseApp instance configured by data in a series of YAML
     * files
     *
     * @param mixed[] $file_paths - array of file paths
     * @todo can some of this instantiation be lazily loaded?
     */
    function __construct($file_paths)
    {
        $twig_filters = [
            "hyphenate",
            "stripStyleAttributes",
        ];

        $this->router = new Klein\Klein();
        $this->file_paths = $file_paths;
        $this->config = $this->_setConfig();
        $this->nav_items = $this->_setNav();
        $this->copyright_yr = date("Y");
        $this->inline_styles = array_key_exists("inline_styles", $this->file_paths) ? file_get_contents($this->file_paths["inline_styles"]) : null;
        $this->inline_svg_styles = array_key_exists("inline_svg_styles", $this->file_paths) ? file_get_contents($this->file_paths["inline_svg_styles"]) : null;

        $twig = $this->bootstrapTwig($this->config['debug']);
        $twig->addGlobal('config', $this->config);
        $twig->addGlobal('nav_items', $this->nav_items);
        $twig->addGlobal('inline_styles', $this->inline_styles);
        $twig->addGlobal('inline_svg_styles', $this->inline_svg_styles);

        foreach ($twig_filters as $filter_method)
        {
            // @xxx Will change in version 2 of Twig
            $filter = new \Twig_SimpleFilter($filter_method, [$this, $filter_method]);
            $twig->addFilter($filter);
        }
        $this->template = $twig;

        $this->response = new \CEHD\App\Response\BaseResponse();
        $this->_routes =  new Dict\Dict(
            $this->loadYaml($this->file_paths["routes"]));

        // load the redirects from the yaml file provided or set and empty Dict object
        $this->_redirects = array_key_exists("redirects", $this->file_paths) ? $this->_setFromYAML($this->file_paths["redirects"]) : $this->_loadDict([]);
        $this->_redirects_camelcase = array_key_exists("redirects_camelcase", $this->file_paths) ? $this->_setFromYAML($this->file_paths["redirects_camelcase"]) : $this->_loadDict([]);

        $this->router->respond('GET', null, [$this, 'genericResponseHandler']);

        // Finally, we get to the actual routes
        foreach($this->_routes as $route => $route_info)
        {
            $this->router->respond(
                'GET', $route, [$this, 'renderStaticTemplate']);
        }
    }

    // Handles *all* requests
    // Adds a trailing slash if it's missing
    // preserves query params if they exist
    // runs on *every* request
    public function genericResponseHandler(
        $request, $response, $service, $app)
    {
        // split the uri into the url and the query params
        $uri_components = explode("?", $request->uri());


        // the first element in the new array will be the uri
        $uri = $uri_components[0];

        if (count($uri_components) > 1)
        {
            // preserve the query string
            $query_str = "?" . $uri_components[1];
        }
        else
        {
            $query_str = "";
        }

        // Don't do this for services
        if (!strpos($uri, "services"))
        {
            // grab the last character in the uri to see if it's a slash
            $last = substr($uri, -1);

            // grab the last 4 characters to see if it's "html". Anything ending in
            // .html is likely to need a specific redirect
            $file_extension = substr($uri, -4);

            if ($last !== "/" && !in_array($file_extension, ["html", ".xml"]))
            {
                // put it all back together and redirect
                $redirect_uri = $uri . "/" . $query_str;
                $response->redirect($redirect_uri, 301)->send();
            }
        }

        $path_components = $this->pathToArray($uri);
        // If there is something like default.html in the path, it'll be the
        // last element.
        $potential_html_filename = array_pop($path_components);

        if (in_array($potential_html_filename, ["default.html", "index.html"]))
        {
            // put the uri back together minus the html filename.
            $new_uri = "/" . implode("/", $path_components) . "/";
            $response->redirect($new_uri, 301)->send();
        }

        // Most redirects will match a key that is the lowercase version of
        // the URI.
        $redirect_route = $this->_redirects->get(
            strtolower($request->uri()));

        if ($redirect_route)
        {
            $response->redirect($redirect_route, 301)->send();
        }
        else
        {
            // Some redirects need to be checked using camelCase or
            // PascalCase routes because their lowercase versions match
            // existing routes
            $redirect_route = $this->_redirects_camelcase->get(
                $request->uri());
            if ($redirect_route)
            {
                $response->redirect($redirect_route, 301)->send();
            }
        }
    }

    /**
     * Loads array data into a Dict instance
     *
     * @param array $data
     * @return Dict
     */
    protected function _loadDict($data)
    {
        return new Dict\Dict($data);
    }

    /**
     * Returns data from YAML files as a Dict or optionally as an array
     *
     * @param string $file_path - a valid path to a YAML file
     * @param boolean $as_dict - whether to return a dict (default) or array
     * @return Dict or array
     */
    protected function _setFromYAML($file_path, $as_dict=true)
    {
        try
        {
            $data_array = $this->loadYaml($file_path);
            if ($as_dict)
            {
                return $this->_loadDict($data_array);
            }

            return $data_array;
        }
        catch (Exception $e)
        {
            echo "Problem loading YAML file: " .
                "File path: " . $file_path . "\n" .
                $e->getMessage() . "\n";
        }

        return [];
    }

    /**
     * Merges several configs into one and then builds various config
     * properties
     *
     * @todo Creating a defined Config class would be more performant
     *
     * @return array
     */
    protected function _setConfig()
    {
        // merge the contents of the yaml configs
        $config =  new Dict\Dict(
            $this->bootstrapConfig($this->file_paths["configs"])
        );

        // build a few more in
        $config->asset_base = $this->_setSubBasePath(
            $config, "base_url", "/assets");
        $config->nunjucks_view_base = $this->_setSubBasePath(
            $config, "asset_base", "/dist/views");
        $config->css_base = $this->_setSubBasePath(
            $config, "asset_base", "/dist/css");
        $config->js_base = $this->_setSubBasePath(
            $config, "asset_base", "/dist/js");
        $config->nav_open = false;

        // Check for main nav state
        if (array_key_exists('cehd-navOpen', $_COOKIE)) {
            $config->nav_open = $_COOKIE['cehd-navOpen'];
        }
        // convert the config back to an array
        return $config->toArray();
    }

    /**
     * A convenience method for setting up various paths in the config
     *
     * @param Dict $config_obj
     * @param string $base_key - the base path to which we want to append
     * @param string $new_sub_path - the new path to append
     * @return string - of combined base path and new sub path or new sub path
     *         of base path doesn't exist
     */
    protected function _setSubBasePath($config_obj, $base_key, $new_sub_path)
    {
        return $config_obj->get($base_key) ? $config_obj->$base_key . $new_sub_path : $new_sub_path;
    }

    /**
     * Sets navigation items from YAML
     *
     * @return array
     */
    protected function _setNav()
    {
        return $this->loadYaml($this->file_paths["nav"]);
    }

    /**
     * Callback method for loading templates based on routes. If the request
     * URI matches a key in the routes property, the corresponding template
     * is rendered. Klein automatically populates the params for all callbacks.
     *
     * @param Klein $request object
     * @param Klein $response object
     * @param Klein $service object
     * @param Klein $app object - In this case, an instance of this app
     * @return string - rendered template if the URI matches a route key or
     *         null if not
     */
    public function renderStaticTemplate($request, $response, $service, $app)
    {
        $route_info = $this->_routes->get($request->pathname());
        if ($this->_routeIsLive($route_info))
        {
            echo $this->template->render(
                $route_info["template"],
                $route_info["template_data"]
            );
        };
    }

    /**
     * Evaluates whether or or not a route should go live or return a 404;
     * Routes will always be live if not in production. When in production it
     * will only go live if the route info has publish: true;
     *
     * @param mixed - route info array from the routes.yml file, null if
     *        $this->_routes->get doesn't find a match
     * @return boolean
     */
    protected function _routeIsLive($route_info)
    {
        return $route_info && ($route_info["publish"] || !$this->config["is_prod"]);
    }

    protected function _renderTemplate($template_path, $template_data)
    {
        return $this->template->render($template_path, $template_data);
    }

    /**
     * Takes an array of human-readable names and gives them a key with the
     * hyphenated version of the name
     *
     * @param array - names
     * @param boolean - sort
     * @return array
     */
    public function nameToHyphenatedKey($names, $sort=true)
    {
        $new_names = [];
        foreach ($names as $idx => $name)
        {
            $hyphenated = $this->helpers->hyphenate($name);
            $new_names[$hyphenated] = $name;
        }

        if ($sort)
        {
            sort($new_names);
            return $new_names;
        }

        return $new_names;
    }
}
