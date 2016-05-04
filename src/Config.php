<?php

namespace RaiffCli;

use Symfony\Component\Yaml\Parser;

class Config
{
    protected $config = [];
    protected $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
        $this->parse(__DIR__ . '/../config.yml.dist');
        $this->parse(__DIR__ . '/../config.yml');
    }

    protected function parse($filename)
    {
        if (file_exists($filename) && $config = $this->parser->parse(file_get_contents($filename))) {
            $this->config = array_replace_recursive($this->config, $config);
        }
    }

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

    public function getConfig()
    {
        return $this->config;
    }

}
