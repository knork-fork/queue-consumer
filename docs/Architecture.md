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