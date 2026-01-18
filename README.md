# Generic Queueing Consumer

Generic queueing service consumer for background processing.

This service consumes messages from a queue and executes jobs **purely based on configuration**.  
The consumer itself is fully generic: it does not track jobs in code, does not contain job-specific logic, and does not require redeploys when new jobs are added.

Jobs are defined via YAML configuration and dispatched by **another container/service** using the Symfony Messenger component.

---

## How it works (high level)

- A **producer** (separate container/service) dispatches a `GenericMessage(jobName, payload)` via e.g. Symfony Messenger.
- This consumer:
  - loads job definitions from `config/jobs/*.yaml`
  - validates the payload against the job definition
  - builds an HTTP request (method, query params, JSON body)
  - executes the request
  - checks the expected success status code
- Jobs are **configuration-only** and **gitignored**.

---

## Job configuration

Jobs are defined in YAML under `config/jobs/`.

See detailed documentation here:  
- **[`docs/Jobs.md`](docs/Jobs.md)**

## Routing configuration

Jobs can be routed to different consumer instances based on routing keys.

See detailed documentation here:  
- **[`docs/Queue routing.md`](docs/Queue%20routing.md)**

---

## Installation

### 1. Start containers
```bash
docker-compose up -d --build
```

### 2. Install PHP dependencies
```bash
docker/composer install
```