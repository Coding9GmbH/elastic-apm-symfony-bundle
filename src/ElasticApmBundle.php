<?php

namespace ElasticApmBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ElasticApmBundle extends Bundle
{
    /**
     * @var callable[]
     */
    private array $shutdownCallbacks = [];

    public function boot(): void
    {
        parent::boot();
        
        // Register PHP error handlers for deprecations and warnings
        if ($this->container->hasParameter('elastic_apm.track_deprecations') 
            && $this->container->getParameter('elastic_apm.track_deprecations')) {
            $this->registerDeprecationHandler();
        }
        
        if ($this->container->hasParameter('elastic_apm.track_warnings') 
            && $this->container->getParameter('elastic_apm.track_warnings')) {
            $this->registerWarningHandler();
        }
    }

    public function shutdown(): void
    {
        parent::shutdown();
        
        // Execute shutdown callbacks
        foreach ($this->shutdownCallbacks as $callback) {
            $callback();
        }
        
        $this->shutdownCallbacks = [];
    }

    private function registerDeprecationHandler(): void
    {
        $previousHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$previousHandler) {
            if ($errno === E_USER_DEPRECATED || $errno === E_DEPRECATED) {
                if ($this->container->has('elastic_apm.interactor')) {
                    $interactor = $this->container->get('elastic_apm.interactor');
                    $interactor->captureError('Deprecation', $errstr, $errfile, $errline);
                }
            }
            
            // Call previous handler if exists
            if ($previousHandler) {
                return $previousHandler($errno, $errstr, $errfile, $errline);
            }
            
            return false;
        });
        
        // Store callback to restore previous handler on shutdown
        $this->shutdownCallbacks[] = function() use ($previousHandler) {
            restore_error_handler();
        };
    }

    private function registerWarningHandler(): void
    {
        $previousHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$previousHandler) {
            if ($errno === E_WARNING || $errno === E_USER_WARNING) {
                if ($this->container->has('elastic_apm.interactor')) {
                    $interactor = $this->container->get('elastic_apm.interactor');
                    $interactor->captureError('Warning', $errstr, $errfile, $errline);
                }
            }
            
            // Call previous handler if exists
            if ($previousHandler) {
                return $previousHandler($errno, $errstr, $errfile, $errline);
            }
            
            return false;
        });
        
        // Store callback to restore previous handler on shutdown
        $this->shutdownCallbacks[] = function() use ($previousHandler) {
            restore_error_handler();
        };
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}