<?php

namespace CassandraBundle\Cassandra;

use CassandraBundle\Cassandra\ORM\EntityManager;
use \Doctrine\Common\Persistence\AbstractManagerRegistry;
use PHPUnit\Runner\Exception;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Config\FileLocator;

class ManagerRegistry extends AbstractManagerRegistry
{
    protected $container;
    protected $config;
    protected $defaultManager;
    protected $services = [];

    /**
     * ManagerRegistry constructor.
     * @param $name
     * @param $connections
     * @param $managers
     * @param $defaultManager
     * @param $entityManager
     * @param $config
     * @param Container $container
     * @throws \Exception
     */
    public function __construct(
        $name, $connections, $managers, $defaultManager, $entityManager, $config, Container $container
    ) {
        $this->config = $config;
        $this->container = $container;
        $proxyInterfaceName = 'Doctrine\Common\Persistence\Proxy';
        $this->registerDefaultManager($defaultManager);
        $defaultConnection = $this->defaultManager->getConnection();

        parent::__construct($name, $connections, $managers, $defaultConnection, $defaultManager, $proxyInterfaceName);
    }

    /**
     * Fetches/creates the given services.
     *
     * A service in this context is connection or a manager instance.
     *
     * @param string $name The name of the service.
     *
     * @return object The instance of the given service.
     */
    protected function getService($name)
    {
        if (!isset($this->services[$name]) && $this->container->has($name)) {
            $this->services[$name] = $this->container->get($name);
        }

        return isset($this->services[$name]) ? $this->services[$name] : $this->defaultManager;
    }

    /**
     * Resets the given services.
     *
     * A service in this context is connection or a manager instance.
     *
     * @param string $name The name of the service.
     *
     * @return void
     */
    protected function resetService($name)
    {
        unset($this->services[$name]);
    }

    /**
     * Resolves a registered namespace alias to the full namespace.
     *
     * This method looks for the alias in all registered object managers.
     *
     * @param string $alias The alias.
     *
     * @return string The full namespace.
     */
    public function getAliasNamespace($alias)
    {
        foreach (array_keys($this->getManagers()) as $name) {
            $manager = $this->getManager($name);

            if ($manager instanceof EntityManager) {
                try {
                    return $manager->getConfiguration()->getEntityNamespace($alias);
                } catch (Exception $ex) {
                    // Probably mapped by another entity manager, or invalid, just ignore this here.
                }
            } else {
                throw new \LogicException(sprintf('Unsupported manager type "%s".', get_class($manager)));
            }
        }

        throw new \RuntimeException(sprintf('The namespace alias "%s" is not known to any manager.', $alias));
    }

    /**
     * {@inheritdoc}
     */
    public function getManagerForClass($class)
    {
        $mapping = [];
        $classPath = '';
        foreach ($this->config['orm']['entity_managers'] as $key => $value) {
            $mapping[current($value['mappings'])['dir']] = $key;
        }
        if (class_exists($class)) {
            $reflector = new \ReflectionClass($class);
            $classPath = substr($reflector->getFileName(), 0, strrpos($reflector->getFileName(), '/'));
        }

        return array_key_exists($class, $mapping) ? $this->getManager($mapping[$classPath]) : $this->defaultManager;
    }

    public function registerDefaultManager($defaultManager)
    {
        if (!$this->container->has($defaultManager)) {
            throw new \Exception(sprintf('Cassandra manager %s not found', $defaultManager));
        }

        $this->defaultManager = $this->services[$defaultManager] = $this->container->get($defaultManager);
    }

}