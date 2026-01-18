## Queue routing

Jobs are routed using RabbitMQ topic routing keys.

Each consumer declares:

- a queue name
- one or more binding keys

Only jobs whose routing key matches a binding key are delivered to that queue.

In practice, this means that multiple consumer instances can be run, each receiving only a subset of jobs which allows for per-domain workers.


### Consumer configuration

Set these environment variables per consumer instance:

```env
CONSUMER_QUEUE_NAME=domain_consumer
CONSUMER_BINDING_KEYS='["job.domain.prefix.#"]'
```

Binding keys use topic patterns:

- `*` matches one segment
- `#` matches zero or more segments

Examples:

- `job.domain.#`
- `job.email.send`
- `#` (catch-all)


### Dispatching a job

Jobs must be dispatched with a routing key:

```php
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;

$bus->dispatch(
    new GenericMessage(
        jobName: 'job-comain-prefix-process',
        payload: []
    ),
    [new AmqpStamp('job.domain.prefix.process')]
);
```

The routing key determines which consumer instances receive the job.


### Catch-all consumer

To receive all jobs regardless of routing key:

```env
CONSUMER_QUEUE_NAME=consumer_all_jobs
CONSUMER_BINDING_KEYS='["#"]'
```

---

## Deployment tips

Set env variables `CONSUMER_QUEUE_NAME` and `CONSUMER_BINDING_KEYS` to configure which RabbitMQ queue and binding keys the consumer listens to, if per-domain routing is needed.

Example `.env` to match e.g. `job.domain.prefix.process`:

```env
###> queue-consumer settings ###
CONSUMER_QUEUE_NAME=domain_consumer
CONSUMER_BINDING_KEYS='["job.domain.prefix.#"]'
###< queue-consumer settings ###
```

For a consumer container that listens to **all** jobs, see docker-compose example:
```yaml
# Consumer
consumer:
    container_name: consumer-php-fpm
    restart: unless-stopped
    build: phpdocker/php-fpm
    working_dir: /application
    environment:
        CONSUMER_QUEUE_NAME: consumer_all_jobs
        CONSUMER_BINDING_KEYS: '["#"]'
    volumes:
        - '.:/application'
        - './phpdocker/php-fpm/php-ini-overrides.ini:/etc/php/8.4/fpm/conf.d/99-overrides.ini'
        - './phpdocker/php-fpm/php-ini-overrides.ini:/etc/php/8.4/cli/conf.d/99-overrides.ini'
```