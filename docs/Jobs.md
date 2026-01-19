# Adding a custom job (YAML config)

Jobs are defined as YAML files under `config/jobs/`. Each job is just configuration: the consumer is generic and reads these files at runtime.

Important: jobs are **not tracked** (directory is ignored in git). The container that **dispatches** jobs is a different container/service; it uses **Symfony Messenger** to enqueue a `GenericMessage(jobName, payload)`. The consumer does not “know” jobs in code; it only validates and executes based on YAML.

---

## Where to put the job

Add a YAML file in:

* `config/jobs/`

You can define multiple jobs per file.

---

## Job structure

`config/jobs/*.yaml` has this shape:

```yaml
jobs:
    <job-name>:
        request:
            method: POST|GET|PUT|DELETE|...
            url: http://some-service:port/path
            query_url_from: [] | [list_of_keys]
            json_body_from: [] | [list_of_keys]
            required: [list_of_required_input_keys]
        log_suffix: some_short_name
        success:
            status_code: 200
```

### Field meanings

#### `jobs.<job-name>`

Unique job identifier. This is the name that the producer will send as `jobName` in the Messenger message.

Use a stable name (kebab-case is recommended, e.g. `dummy-response-retrieval`).

#### `request.method`

HTTP method used when executing the job.

#### `request.url`

Target URL the consumer will call when executing the job.

#### `request.query_url_from`

Which payload keys become URL query parameters.

* `[]` = send no query parameters
* `["a", "b"]` = only include those keys from payload as `?a=...&b=...`

#### `request.json_body_from`

Which payload keys become the JSON request body.

* `[]` = send no JSON body
* `["x", "y"]` = JSON body contains only those keys

#### `request.required`

List of required payload keys. If any are missing, the consumer rejects the message (job is considered invalid).

#### `log_suffix`

Short suffix used for logging (so logs for different jobs can be separated or filtered).

#### `success.status_code`

Expected HTTP status code that means “job succeeded”.

Job is considered failed if the response status code is any other.

---

## Minimal example

```yaml
jobs:
    my-custom-job:
        request:
            method: POST
            url: http://my-service:8080/do-stuff
            query_url_from: ["user_id"]
            json_body_from: ["file_id", "mode"]
            required: ["user_id", "file_id"]
        log_suffix: my_custom_job
        success:
            status_code: 200
        callbacks: null
```

---

## How the job is dispatched

Jobs are dispatched from another container/service (producer) using Symfony Messenger by sending:

* `jobName`: must match the YAML job name
* `payload`: associative array with your input keys

Example shape:

```php
$bus->dispatch(new GenericMessage(
    jobName: 'my-custom-job',
    payload: [
        'user_id' => 123,
        'file_id' => 'abc',
        'mode' => 'fast',
    ]
), [new AmqpStamp('routing.key.for.my.job')]);
```

The consumer remains generic:

* it loads the YAML job config
* validates payload against `required`
* builds query params / JSON body based on `*_from`
* performs the HTTP request
* checks `success.status_code`

No job code is added to the consumer. Only YAML changes.

---

## Callback jobs

Callback jobs can be defined to run on job start, success, or failure, and are run synchronously with the main job as part of the same worker process.
Callback jobs can be chained: each callback job can have its own callbacks.

Most notable use case for callbacks is updating job status in an external system, so that producer or other services can track job progress, without lending ownership or access to the database to the consumer.

Example of a job chain that updates job status with callbacks (producer creates a job entry in the db and passes `job_id` in payload to `main-job`):
```yaml
jobs:
    main-job:
        request:
            method: POST
            url: http://my-service:8080/do-stuff
            query_url_from: []
            json_body_from: ["job_id"]
            required: ["job_id"]
        log_suffix: whatever_domain
        success:
            status_code: 200
        callbacks:
            on_start: main-job-status-doing
            on_success: main-job-status-done
            on_fail: null
    
    main-job-status-doing:
        request:
            method: POST
            url: http://my-service:8080/job-status/set-doing
            query_url_from: []
            json_body_from: ["job_id"]
            required: ["job_id"]
        log_suffix: whatever_domain
        success:
            status_code: 200
        callbacks: null
    
    main-job-status-done:
        request:
            method: POST
            url: http://my-service:8080/job-status/set-done
            query_url_from: []
            json_body_from: ["job_id"]
            required: ["job_id"]
        log_suffix: whatever_domain
        success:
            status_code: 200
        callbacks: null
```

Note the command-style status endpoints - CRUD mutations on a single endpoint are discouraged in favor of explicit commands for state transitions.

Also note order of execution for the above example:
1. `main-job-status-doing` (onStart)
2. `main-job`
3. `main-job-status-done` (onSuccess)

##### OnFail callback

In case of a failure, job handler will automatically add `__error_message` key to the payload, containing the error message from the failed main job. 
If error key is specified in `json_body_from` list it will be passed to the callback job in the payload, allowing the onFail callback to update both the status and the error message in the external system.

OnFail callback **will execute** if **any callback or main job** fails - if for example main job has onStart and onFail callbacks, and onStart also has its own onFail callback, and onStart execution fails, then onStart's onFail will get called first after which main thread's onFail will also get called.

Example of onFail callback job:
```yaml
jobs:
    main-job:
    ...
        callbacks:
            on_fail: main-job-status-failed

    main-job-status-failed:
        request:
            method: POST
            url: http://my-service:8080/job-status/set-failed
            query_url_from: []
            json_body_from: ["job_id", "__error_message"]
            required: ["job_id"]
        log_suffix: whatever_domain
        success:
            status_code: 200
        callbacks: null
```

---

## Deployment tips

Either add this repo as a submodule to your project, or build an image and push it to your registry.

Mount a custom and tracked `config/jobs/` directory with your job YAML files into the container at runtime.