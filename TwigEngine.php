<?php

namespace Statamic\Addons\Twig;

use Illuminate\View\Engines\EngineInterface;
use Statamic\API\Str;
use Statamic\DataStore;

/**
 * Laravel/Statamic implementation for the Twig templating engine.
 */
class TwigEngine implements EngineInterface
{
    /**
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * @var \Statamic\DataStore
     */
    private $dataStore;

    /**
     * @param \Twig_Environment $twig
     * @param \Statamic\DataStore $dataStore
     */
    public function __construct(\Twig_Environment $twig, DataStore $dataStore)
    {
        $this->twig = $twig;
        $this->dataStore = $dataStore;
    }

    /**
     * {@inheritdoc}
     */
    public function get($path, array $data = [])
    {
        $templatePath =  $this->resolveTemplatePath($path);

        return $this->twig->render($templatePath, $this->dataStore->getAll());
    }

    /**
     * Resolve relative twig template path from a given absolute path.
     *
     * @param string $absolutePath
     *
     * @return string
     */
    private function resolveTemplatePath($absolutePath) {
        foreach ($this->twig->getLoader()->getPaths() as $path) {
            if (strpos($absolutePath, $path) !== false) {
                return Str::substr($absolutePath, Str::length($path));
            }
        }

        return $absolutePath;
    }
}
