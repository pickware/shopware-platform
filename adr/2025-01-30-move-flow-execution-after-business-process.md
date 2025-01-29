---
title: Move flow execution after business process
date: 2025-01-30
area: Core
tags: [flow, flow-action]
---

## Context

Currently, flows are executed during the business process. A business
process is any event that is triggered by the user, like a checkout or
a product update. Flows are used to react to these events and execute user defined actions.

User generated code has to be treated as untrustworthy. An easy example of a failing flow is
a state transition to an invalid state, this flow will always fail. This is a problem because
the business process will fail as well, as the flow failing will mark the database transaction as rollback only.

## Decision

We will move the flow execution after the business process. This way, the business process can never fail
because of a failing flow. Specifically, we ensure that the database transaction of the business process
is always committed before the flow is executed. This way, the flow can never fail the business process.

To ensure that the flow is executed as close to the business process that triggered it as possible, we will
'queue' the flow execution. This means that the flow will be executed after the business process has finished.
Flows are stored in memory and executed as soon as the execution environment signals, that it has
finished a unit of work. These events are as follows:

1. After a controller action has been executed (Web) => `KernelEvents::TERMINATE`
2. After a queued message has been processed (Queue) => `WorkerMessageHandledEvent`
3. After a command has been executed (CLI) => `ConsoleEvents::TERMINATE`

Another option would be to handle flow executions as queue messages. This would entirely remove
flow executions from the runtime of the business process. While this would be a simpler solution,
it would both make debugging more complex and introduce an unpredictable delay between the business
process and the flow execution. While this delay could be mitigated by using a high priority queue,
it could not reliably be kept under a certain threshold. To entirely avoid this delay, we decided
that the flow execution should be handled as close to the business process as possible.

## Consequences

1. Flows can no longer fail the business process.
2. The interface for registering flows will not change.
3. Any plugins that rely on flows being executed during the business process will have to be updated.
4. Total execution time is not expected to increase significantly.
