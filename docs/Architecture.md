Within the following architecture, this project fulfills the role of the "Consumer" component:

```
Frontend
   ↓
Business Logic - backend
   ↓
Producer - code within backend
   ↓
Broker - e.g. RabbitMQ
   ↓
Consumer - worker-handler loop
   ↓
Executor - processing containers
```

---

## Job vs message

**Message**
- A technical unit sent through the queue
- Serialized object handled by Symfony Messenger
- Contains data + metadata (routing key, stamps)
- Transport-level concern

**Job**
- A logical unit of work in the application
- Identified by `jobName` and `payload`
- Executed by application code
- Business-level concern

**Relationship**
- A job is wrapped inside a message
- One message typically represents one job
- Routing is applied to the message (via routing key)
- Job contents do not affect routing
