<?php
/**
 * CEHD Site Config
 *
 * YAML files are parses into a single configuration array. We also make some
 * attempt to determine environment here.
 *
 * @author Tony Thomas <thoma127@umn.edu>
 * @license GPL 3.0
 */

namespace CEHD\App\Config;
require_once __DIR__ . '/../../../vendor/autoload.php';

/**
 * The Config class
 *
 * Contains private methods to load multiple config YAML files and parse them
 * into one array.
 *
 * @package CEHD\App\Config
 */
class Config
{
    /**
     * Reads the global SERVER_NAME to determine whether or not we're in a
     * local dev, test for production environment
     *
     * @return string
     */
    private function _get_environment()
    {
        $server_name = $_SERVER['SERVER_NAME'];
        $possible_environments = [
            "www" => "production",
            "scotch" => "development",
            "backstage" => "test",
            "drydock" => "test",
        ];

        foreach ($possible_environments as $env_substr => $env_name)
        {
            if (strpos($server_name, $env_substr) !== false)
            {
                return $env_name;
            }
        }

        // default to test for local environments that use a local ip address
        return "test";
    }

    /**
     * Convenience method for checking if the current environment is production
     *
     * @return boolean
     * @uses Config::_get_environment to determine server environment
     */
    private function _is_prod()
    {
        return $this->_get_environment() === "production";
    }

    /**
     * Convenience method for checking if the current environment is test
     *
     * @return boolean
     * @uses Config::_get_environment to determine server environment
     */
    private function _is_test()
    {
        return $this->_get_environment() === "test";
    }

    /**
     * Convenience method for checking if the current environment is development
     *
     * @return boolean
     * @uses Config::_get_environment to determine server environment
     */
    private function _is_dev()
    {
        return $this->_get_environment() === "development";
    }

    /**
     * Loads YAML files using the provide path
     *
     * @param string $file_path the path to a YAML file
     * @return Array with YAML values or false if the file doesn't exist
     * @uses \Symfony\Component\Yaml\Yaml
     */
    private function _load_from_yaml($file_path)
    {
        if (file_exists($file_path))
        {
            $yaml = new \Symfony\Component\Yaml\Yaml;
            return $yaml::parse(file_get_contents($file_path));
        }
        return false;
    }

    /**
     * Loads multiple YAML files from an array of file paths
     *
     * @param Array of file paths to YAML config files
     * @return Array of config key value pairs, merged from all the individual files
     * @uses Config::_get_environment to determine serve environment
     * @uses Config::_is_prod to set the is_prod array element value
     * @uses Config::_is_test to set the is_test array element value
     * @uses Config::_is_dev to set the is_dev array element value
     * @uses Config::_load_from_yaml to parse YAML into an array
     */
    public function load($config_files)
    {
        $config = [];
        $environment_config = [
            "environment" => $this->_get_environment(),
            "is_prod" => $this->_is_prod(),
            "is_test" => $this->_is_test(),
            "is_dev" => $this->_is_dev(),
        ];

        foreach ($config_files as $key => $config_file)
        {
            $config = array_merge($config, $this->_load_from_yaml($config_file));
        }

        $config = array_merge($config, $environment_config);
        return $config;
    }
}
