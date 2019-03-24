# CassandraBundle

The CassandraBundle provide a Cassandra EntityManager as a Symfony service.

## Installation

**NOTE :** You need to [install the offical datastax php driver extension](https://github.com/datastax/php-driver)

Install the bundle :

```shell
$ composer require hendrahuang/cassandra-bundle
```

Register the bundle in your kernel :

```php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        new CassandraBundle\CassandraBundle(),
    );
}
```

## Example Usage

Add the `cassandra` section in your configuration file. Here is the minimal configuration required. 

```yaml
# app/config/config.yml

cassandra:
    connections:
        default:
            keyspace: "mykeyspace"
            hosts:
                - 127.0.0.1
                - 127.0.0.2
                - 127.0.0.3
            user: ''
            password: ''
```

```yaml
# app/config/config_prod.yml

cassandra:
    dispatch_events: false
```

Create entity for cassandra schema :

```php
<?php

namespace AppBundle\Entity;

use CassandraBundle\Cassandra\ORM\Mapping as ORM;

/**
 * @ORM\Table(
 *      repositoryClass = "AppBundle\Repository\HotelRepository",
 *      indexes = {"tags"},
 * )
 */
class Hotel
{
    /**
     * @ORM\Column(name="id", type="uuid")
     */
    private $id;

    /**
     * @ORM\Column(name="name", type="text")
     */
    private $name;

    /**
     * @ORM\Column(name="tags", type="set<text>")
     */
    private $tags;

    /**
     * @ORM\Column(name="config", type="map<text, frozen<map<text, text>>>")
     */
    private $config;

    // ...
}
```

Run console command to create cassandra schema

```shell
$ bin/console cassandra:schema:create
```
**NOTE :** You need to manually create your keyspace in cassandra before running the command


You can create repository for custom query :

```php
<?php

namespace AppBundle\Repository;

use CassandraBundle\Cassandra\Utility\Type as CassandraType;

class HotelRepository extends \CassandraBundle\Cassandra\ORM\EntityRepository
{
    public function findByIds($ids = [])
    {
        $em = $this->getEntityManager();
        $cql = sprintf(
            'SELECT * FROM %s WHERE id IN (%s)', 
            $this->getTableName(),
            implode(', ', array_map(function () { return '?'; }, $ids))
        );
        $statement = $em->prepare($cql);
        $arguments = new \Cassandra\ExecutionOptions(['arguments' => array_map(function ($id) { return CassandraType::transformToCassandraType('uuid', $id); }, $ids)]);

        return $this->getResult($statement, $arguments);
    }
}
```

Then you can insert or query data using EntityManager :

```php
        $em = $this->get('cassandra.default_entity_manager');

        $hotel = new \AppBundle\Entity\Hotel();
        $hotel->setId('26fd2706-8baf-433b-82eb-8c7fada847da');
        $hotel->setName('name');
        $hotel->setTags(['wifi', 'AC']);
        $now = new \Datetime();
        $hotel->setConfig([
            'url' => [
                'slug' => 'new-hotel',
            ],
        ]);
        $em->insert($hotel); // Insert entity to cassandra

        $em->flush();

        $repository = $em->getRepository('AppBundle:Hotel');
        $hotels = $repository->findAll(); // Query all hotels
        $hotels = $repository->findByIds(['26fd2706-8baf-433b-82eb-8c7fada847da', '86fd2706-8baf-433b-82eb-8c7fada847da']); // Query hotels by $ids
        $hotel = $repository->find('26fd2706-8baf-433b-82eb-8c7fada847da'); // Query hotel by id
        $hotel->setName('new name');
        $em->update($hotel); // Update entity
        
        $em->flush();
```

Bundle provide a util class for extracting Datetime from a timeuuid string. 

```php
use CassandraBundle\Cassandra\Utility\Type as TypeUtils;

$datetime = TypeUtils::getDateTimeFromTimeuuidString('513a5340-6da0-11e5-815e-93ec150e89fd');

if (is_null($datetime)) {
    // something is wrong with supplied uuid
} else {
    echo $datetime->format(\DateTime::W3C); // 2015-10-08 11:38:22+02:00
}
```

## DataCollector

Datacollector is available when the symfony profiler is enabled. The collector allows you to see the following Cassandra data :

- keyspace
- command name
- command arguments
- execution time
- execution options override (consistency, serial consistency, page size and timeout)

**NOTE :** The time reported in the data collector may not be the real execution time in case you use the async calls : `executeAsync` and `prepareAsync`

## EntityManager

EntityManager is linked to one connection, so one keyspace in Cassandra.
In the bundle, you can map some Entity folders to an entityManager to then create some tables (via the SchemaManager) a specific keyspace.
There is a configuration parameter under ``orm`` called ``entity_managers`` where you can describe each ``entity_manager``.
The entityManager config contains the linked connection and the entity mapping directories.

If the linked connection can't be found, will fallback to default connection

## Configuration reference

```yaml
cassandra:
    dispatch_events: true                 # By default event are triggered on each cassandra command
    connections:
        default:
            persistent_sessions: true     # persistent session connection 
            keyspace: "mykeyspace"        # required keyspace to connect
            load_balancing: "round-robin" # round-robin or dc-aware-round-robin
            dc_options:                   # required if load balancing is set to dc-aware-round-robin
                local_dc_name: "testdc"
                host_per_remote_dc: 3
                remote_dc_for_local_consistency: false
            default_consistency: "one"    # 'one', 'any', 'two', 'three', 'quorum', 'all', 'local_quorum', 'each_quorum', 'serial', 'local_serial', 'local_one'
            default_pagesize: 10000       # -1 to disable pagination
            hosts:                        # required list of ip to contact
                - 127.0.0.1
            port: 9042                    # cassandra port
            token_aware_routing: true     # Enable or disable token aware routing
            user: ""                      # username for authentication
            password: ""                  # password for authentication
            ssl: false                    # set up ssl context
            default_timeout: null         # default is null, must be an integer if set
            timeout:
                connect: 5 
                request: 5 
            retries:
                sync_requests: 0          # Number of retries for synchronous requests. Default is 0, must be an integer if set

        client_name:
            ...
    orm:
        default_entity_manager: default
        entity_managers:
            default:
                connection: default
                mappings:
                    User:
                        dir: "src/UserEntity"
                    Preference:
                        dir: "src/PreferenceEntity"
            
            client_name:
                connection: client_name
                mappings:
                    EntityGroupOne:
                        dir: "src/GroupOneEntity"
            ...
```

## Running the test

Install the composer dev dependencies

```shell
$ composer install --dev
```

Then run the test with [atoum](https://github.com/atoum/atoum) unit test framework
