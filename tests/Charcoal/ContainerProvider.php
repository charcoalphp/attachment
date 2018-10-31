<?php

namespace Charcoal\Tests;

use PDO;

// From Mockery
use Mockery;

// From PSR-3
use Psr\Log\NullLogger;

// From Slim
use Slim\Http\Uri;

// From 'tedivm/stash'
use Stash\Pool;
use Stash\Driver\Ephemeral;

// From 'zendframework/zend-permissions-acl'
use Zend\Permissions\Acl\Acl;

// From Pimple
use Pimple\Container;

// From 'league/climate'
use League\CLImate\CLImate;
use League\CLImate\Util\System\Linux;
use League\CLImate\Util\Output;
use League\CLImate\Util\Reader\Stdin;
use League\CLImate\Util\UtilFactory;

// From 'charcoal-factory'
use Charcoal\Factory\GenericFactory as Factory;

// From 'charcoal-app'
use Charcoal\App\AppConfig;
use Charcoal\App\Template\WidgetBuilder;

// From 'charcoal-core'
use Charcoal\Model\Service\MetadataLoader;
use Charcoal\Source\DatabaseSource;

// From 'charcoal-user'
use Charcoal\User\Authenticator;
use Charcoal\User\Authorizer;

// From 'charcoal-ui'
use Charcoal\Ui\Dashboard\DashboardBuilder;
use Charcoal\Ui\Dashboard\DashboardInterface;
use Charcoal\Ui\Layout\LayoutBuilder;
use Charcoal\Ui\Layout\LayoutFactory;

// From 'charcoal-email'
use Charcoal\Email\Email;
use Charcoal\Email\EmailConfig;

// From 'charcoal-view'
use Charcoal\View\GenericView;
use Charcoal\View\Mustache\MustacheEngine;
use Charcoal\View\Mustache\MustacheLoader;

// From 'charcoal-translator'
use Charcoal\Translator\LocalesManager;
use Charcoal\Translator\Translator;

// From 'charcoal-admin'
use Charcoal\Admin\Config as AdminConfig;

/**
 *
 */
class ContainerProvider
{
    /**
     * Register the unit tests required services.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerBaseServices(Container $container)
    {
        $this->registerConfig($container);
        $this->registerDatabase($container);
        $this->registerLogger($container);
        $this->registerCache($container);
    }

    /**
     * Register the admin services.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerAdminServices(Container $container)
    {
        $this->registerBaseUrl($container);
        $this->registerAdminConfig($container);
        $this->registerAuthenticator($container);
        $this->registerAuthorizer($container);
    }

    /**
     * Setup the application's base URI.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerBaseUrl(Container $container)
    {
        $container['base-url'] = function () {
            $baseUrl = Uri::createFromString('')->withUserInfo('');

            /** Fix the base path */
            $path = $baseUrl->getPath();
            if ($path) {
                $baseUrl = $baseUrl->withBasePath($path)->withPath('');
            }

            return $baseUrl;
        };
    }

    /**
     * Setup the application configset.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerConfig(Container $container)
    {
        $container['config'] = function () {
            return new AppConfig([
                'base_path'  => realpath(__DIR__.'/../../..'),
                'apis'       => [
                    'google' => [
                        'recaptcha' => [
                            'public_key'  => 'foobar',
                            'private_key' => 'bazqux',
                        ]
                    ]
                ]
            ]);
        };
    }

    /**
     * Setup the admin module configset.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerAdminConfig(Container $container)
    {
        $this->registerConfig($container);

        $container['admin/config'] = function () {
            return new AdminConfig();
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerLayoutFactory(Container $container)
    {
        $container['layout/factory'] = function () {
            $layoutFactory = new LayoutFactory();
            return $layoutFactory;
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerLayoutBuilder(Container $container)
    {
        $this->registerLayoutFactory($container);

        $container['layout/builder'] = function (Container $container) {
            $layoutFactory = $container['layout/factory'];
            $layoutBuilder = new LayoutBuilder($layoutFactory, $container);
            return $layoutBuilder;
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerDashboardFactory(Container $container)
    {
        $this->registerLogger($container);
        $this->registerWidgetBuilder($container);
        $this->registerLayoutBuilder($container);

        $container['dashboard/factory'] = function (Container $container) {
            return new Factory([
                'arguments'          => [[
                    'container'      => $container,
                    'logger'         => $container['logger'],
                    'widget_builder' => $container['widget/builder'],
                    'layout_builder' => $container['layout/builder']
                ]],
                'resolver_options' => [
                    'suffix' => 'Dashboard'
                ]
            ]);
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerDashboardBuilder(Container $container)
    {
        $this->registerDashboardFactory($container);

        $container['dashboard/builder'] = function (Container $container) {
            $dashboardFactory = $container['dashboard/factory'];
            $dashboardBuilder = new DashboardBuilder($dashboardFactory, $container);
            return $dashboardBuilder;
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerWidgetFactory(Container $container)
    {
        $this->registerLogger($container);

        $container['widget/factory'] = function (Container $container) {
            return new Factory([
                'resolver_options' => [
                    'suffix' => 'Widget'
                ],
                'arguments' => [[
                    'container' => $container,
                    'logger'    => $container['logger']
                ]]
            ]);
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerWidgetBuilder(Container $container)
    {
        $this->registerWidgetFactory($container);

        $container['widget/builder'] = function (Container $container) {
            return new WidgetBuilder($container['widget/factory'], $container);
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerClimate(Container $container)
    {
        $container['climate/system'] = function () {
            $system = Mockery::mock(Linux::class);
            $system->shouldReceive('hasAnsiSupport')->andReturn(true);
            $system->shouldReceive('width')->andReturn(80);

            return $system;
        };

        $container['climate/output'] = function () {
            $output = Mockery::mock(Output::class);
            $output->shouldReceive('persist')->andReturn($output);
            $output->shouldReceive('sameLine')->andReturn($output);
            $output->shouldReceive('write');

            return $output;
        };

        $container['climate/reader'] = function () {
            $reader = Mockery::mock(Stdin::class);
            $reader->shouldReceive('line')->andReturn('line');
            $reader->shouldReceive('char')->andReturn('char');
            $reader->shouldReceive('multiLine')->andReturn('multiLine');
            return $reader;
        };

        $container['climate/util'] = function (Container $container) {
            return new UtilFactory($container['climate/system']);
        };

        $container['climate'] = function (Container $container) {
            $climate = new CLImate();

            $climate->setOutput($container['climate/output']);
            $climate->setUtil($container['climate/util']);
            $climate->setReader($container['climate/reader']);

            return $climate;
        };
    }

    /**
     * Setup the framework's view renderer.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerView(Container $container)
    {
        $container['view/loader'] = function (Container $container) {
            return new MustacheLoader([
                'logger'    => $container['logger'],
                'base_path' => $container['config']['base_path'],
                'paths'     => [
                    'views'
                ]
            ]);
        };

        $container['view/engine'] = function (Container $container) {
            return new MustacheEngine([
                'logger' => $container['logger'],
                'cache'  => MustacheEngine::DEFAULT_CACHE_PATH,
                'loader' => $container['view/loader']
            ]);
        };

        $container['view'] = function (Container $container) {
            return new GenericView([
                'logger' => $container['logger'],
                'engine' => $container['view/engine']
            ]);
        };
    }

    /**
     * Setup the application's translator service.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerTranslator(Container $container)
    {
        $container['locales/manager'] = function () {
            return new LocalesManager([
                'locales' => [
                    'en' => [ 'locale' => 'en-US' ]
                ]
            ]);
        };

        $container['translator'] = function (Container $container) {
            return new Translator([
                'manager' => $container['locales/manager']
            ]);
        };
    }

    /**
     * Setup the application's logging interface.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerLogger(Container $container)
    {
        $container['logger'] = function () {
            return new NullLogger();
        };
    }

    /**
     * Setup the application's caching interface.
     *
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerCache(Container $container)
    {
        $container['cache'] = function () {
            return new Pool();
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerDatabase(Container $container)
    {
        $container['database'] = function () {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerMetadataLoader(Container $container)
    {
        $this->registerLogger($container);
        $this->registerCache($container);

        $container['metadata/loader'] = function (Container $container) {
            return new MetadataLoader([
                'logger'    => $container['logger'],
                'cache'     => $container['cache'],
                'base_path' => $container['config']['base_path'],
                'paths'     => [
                    'metadata',
                    'vendor/locomotivemtl/charcoal-object/metadata',
                    'vendor/locomotivemtl/charcoal-user/metadata',
                ]
            ]);
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerSourceFactory(Container $container)
    {
        $this->registerLogger($container);
        $this->registerDatabase($container);

        $container['source/factory'] = function (Container $container) {
            return new Factory([
                'map' => [
                    'database' => DatabaseSource::class
                ],
                'arguments'  => [[
                    'logger' => $container['logger'],
                    'pdo'    => $container['database']
                ]]
            ]);
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerPropertyFactory(Container $container)
    {
        $this->registerTranslator($container);
        $this->registerDatabase($container);
        $this->registerLogger($container);

        $container['property/factory'] = function (Container $container) {
            return new Factory([
                'resolver_options' => [
                    'prefix' => '\\Charcoal\\Property\\',
                    'suffix' => 'Property'
                ],
                'arguments' => [[
                    'container'  => $container,
                    'database'   => $container['database'],
                    'translator' => $container['translator'],
                    'logger'     => $container['logger']
                ]]
            ]);
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerPropertyDisplayFactory(Container $container)
    {
        $this->registerDatabase($container);
        $this->registerLogger($container);

        $container['property/display/factory'] = function (Container $container) {
            return new Factory([
                'resolver_options' => [
                    'suffix' => 'Display'
                ],
                'arguments' => [[
                    'container' => $container,
                    'logger'    => $container['logger']
                ]]
            ]);
        };
    }


    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerModelFactory(Container $container)
    {
        $this->registerLogger($container);
        $this->registerTranslator($container);
        $this->registerMetadataLoader($container);
        $this->registerPropertyFactory($container);
        $this->registerSourceFactory($container);

        $container['model/factory'] = function (Container $container) {
            return new Factory([
                'arguments' => [[
                    'container'        => $container,
                    'logger'           => $container['logger'],
                    'metadata_loader'  => $container['metadata/loader'],
                    'property_factory' => $container['property/factory'],
                    'source_factory'   => $container['source/factory']
                ]]
            ]);
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerAcl(Container $container)
    {
        $container['admin/acl'] = function () {
            return new Acl();
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerAuthenticator(Container $container)
    {
        $this->registerLogger($container);
        $this->registerModelFactory($container);

        $container['admin/authenticator'] = function (Container $container) {
            return new Authenticator([
                'logger'        => $container['logger'],
                'user_type'     => 'charcoal/admin/user',
                'user_factory'  => $container['model/factory'],
                'token_type'    => 'charcoal/admin/user/auth-token',
                'token_factory' => $container['model/factory']
            ]);
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerAuthorizer(Container $container)
    {
        $this->registerLogger($container);
        $this->registerAcl($container);

        $container['admin/authorizer'] = function (Container $container) {
            return new Authorizer([
                'logger'    => $container['logger'],
                'acl'       => $container['admin/acl'],
                'resource'  => 'admin'
            ]);
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerCollectionLoader(Container $container)
    {
        $this->registerLogger($container);
        $this->registerModelFactory($container);

        $container['model/collection/loader'] = function (Container $container) {
            return new \Charcoal\Loader\CollectionLoader([
                'logger'  => $container['logger'],
                'factory' => $container['model/factory']
            ]);
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerEmailFactory(Container $container)
    {
        $container['email/factory'] = function () {
            return new Factory([
                'map' => [
                    'email' => Email::class
                ]
            ]);
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerElfinderConfig(Container $container)
    {
        $container['elfinder/config'] = function () {
            return [];
        };
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerActionDependencies(Container $container)
    {
        $this->registerLogger($container);

        $this->registerModelFactory($container);
        $this->registerTranslator($container);

        $this->registerAdminConfig($container);
        $this->registerBaseUrl($container);

        $this->registerAuthenticator($container);
        $this->registerAuthorizer($container);
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerTemplateDependencies(Container $container)
    {
        $this->registerLogger($container);

        $this->registerModelFactory($container);
        $this->registerTranslator($container);

        $this->registerAdminConfig($container);
        $this->registerBaseUrl($container);

        $this->registerAuthenticator($container);
        $this->registerAuthorizer($container);

        $container['menu/builder'] = null;
        $container['menu/item/builder'] = null;
    }

    /**
     * @param  Container $container A DI container.
     * @return void
     */
    public function registerWidgetDependencies(Container $container)
    {
        $this->registerLogger($container);
        $this->registerTranslator($container);
        $this->registerView($container);
        $this->registerAdminConfig($container);
        $this->registerBaseUrl($container);
        $this->registerModelFactory($container);

        $this->registerAuthenticator($container);
        $this->registerAuthorizer($container);
    }
}
