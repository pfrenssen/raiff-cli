<?php

namespace RaiffCli\Config;

use Symfony\Component\Yaml\Parser;

/**
 * Configuration management service.
 */
class ConfigManager {

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
     */
    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Returns a Config object for the given config file.
     *
     * @param string $filename
     *   The filename of the config object to return, e.g. 'config.yml'.
     * @return \RaiffCli\Config\Config
     *   The configuration object.
     */
    public function get($filename)
    {
        return new Config($this->parser, $filename);
    }

}
