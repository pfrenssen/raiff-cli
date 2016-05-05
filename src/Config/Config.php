<?php

namespace RaiffCli\Config;

use Symfony\Component\Yaml\Parser;

/**
 * Represents a single configuration file.
 */
class Config
{
    /**
     * The parsed configuration.
     *
     * @var array
     *   An array of configuration, keyed by type.
     */
    protected $config = [];

    /**
     * The path of the configuration file.
     *
     * @var string
     */
    protected $path;

    /**
     * The Yaml parser.
     *
     * @var \Symfony\Component\Yaml\Parser
     */
    protected $parser;

    /**
     * Config constructor.
     *
     * @param \Symfony\Component\Yaml\Parser $parser
     *   The Yaml parser.
     * @param string $filename
     *   The name of the config file to parse.
     */
    public function __construct(Parser $parser, $filename)
    {
        $this->parser = $parser;
        $this->path = __DIR__ . "/../../config/$filename";

        // The optional '.dist' file contains default values. Load this first,
        // then override the default values with the actual ones.
        $this->parse($this->path . '.dist');
        $this->parse($this->path);
    }

    /**
     * Parses the file at the given path, and stores the result locally.
     *
     * @param string $path
     *   The path to the configuration file to parse.
     */
    protected function parse($path)
    {
        if (file_exists($path) && $config = $this->parser->parse(file_get_contents($path))) {
            $this->config = array_replace_recursive($this->config, $config);
        }
    }

    /**
     * Returns a configuration value.
     *
     * @param string $key
     *   The configuration key. Separate hierarchical keys with a period.
     * @return array|mixed
     *   The requested configuration.
     *
     * @throws \InvalidArgumentException
     *   Thrown when the configuration element with the given key does not
     *   exist.
     */
    public function get($key)
    {
        $config = $this->config;
        foreach (explode('.', $key) as $element) {
            if (!empty($config[$element])) {
                $config = $config[$element];
            }
            else {
                throw new \InvalidArgumentException("There is no configuration item with key '$key'.");
            }
        }
        return $config;
    }

    /**
     * Returns the full configuration array.
     *
     * @return array
     *   The full configuration array.
     */
    public function getConfig()
    {
        return $this->config;
    }

}
