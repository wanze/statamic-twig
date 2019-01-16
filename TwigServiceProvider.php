<?php

namespace Statamic\Addons\Twig;

use Statamic\Config\Settings;
use Statamic\Extend\ServiceProvider;

/**
 * Register twig as additional template engine.
 */
class TwigServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->app['view']->addExtension('html.twig', 'twig', function () {
            return new TwigEngine($this->getTwig(), $this->app['Statamic\DataStore']);
        });
    }

    /**
     * @return \Twig_Environment
     */
    private function getTwig()
    {
        return (new TwigFactory(
            $this->app->make(Settings::class),
            $this->getConfig(),
            $this->app->make('request'),
            $this->app->make('events'),
            realpath(root_path()),
            cache_path('twig')
        ))->create();
    }
}
