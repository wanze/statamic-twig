<?php

namespace Statamic\Addons\Twig;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Statamic\Config\Settings;

/**
 * Create an instance of the Twig_Environment object in Statamic.
 */
class TwigFactory
{
    /**
     * @var Settings
     */
    private $config;

    /**
     * @var array
     */
    private $addonConfig;

    /**
     * @var Dispatcher
     */
    private $eventDispatcher;

    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var string
     */
    private $cachePath;

    /**
     * @var Request
     */
    private $request;

    /**
     * @param Settings $config
     * @param array $addonConfig
     * @param Request $request
     * @param Dispatcher $eventDispatcher
     * @param string $rootPath
     * @param string $cachePath
     */
    public function __construct(Settings $config, array $addonConfig, Request $request, Dispatcher $eventDispatcher, $rootPath, $cachePath)
    {
        $this->config = $config;
        $this->addonConfig = collect($addonConfig);
        $this->request = $request;
        $this->eventDispatcher = $eventDispatcher;
        $this->rootPath = $rootPath;
        $this->cachePath = $cachePath;
    }

    /**
     * @return \Twig_Environment
     */
    public function create()
    {
        $loader = new \Twig_Loader_Filesystem($this->getTemplatesPath());

        $twig = new \Twig_Environment($loader, [
            'cache' => $this->cachePath,
            'debug' => bool($this->addonConfig->get('debug')),
            'strict_variables' => bool($this->addonConfig->get('strict_variables')),
            'autoescape' => bool($this->addonConfig->get('autoescape')) ? 'name' : false,
        ]);

        // Add the debug extension offering the "dump()" function for variables.
        if (bool($this->addonConfig->get('debug'))) {
            $twig->addExtension(new \Twig_Extension_Debug());
        }

        $twig->addGlobal('request', $this->request);
        $twig->addExtension(new StatamicTwigExtension());

        // Emit "twig.init" event so listeners may customize the twig environment.
        $this->eventDispatcher->fire('twig.init', ['twig' => $twig]);

        return $twig;
    }

    /**
     * @return string
     */
    private function getTemplatesPath()
    {
        return implode(DIRECTORY_SEPARATOR, [
            $this->rootPath,
            $this->config->get('system.filesystems.themes.root'),
            $this->config->get('theming.theme', 'default'),
            'templates',
        ]);
    }
}
