---
title: Add FlowExecution log and move FlowExecution after BusinessProcess
issue: NEXT-00000
flag: V6_7_0_0
author: Benedikt Brunner
author_email: benedikt.brunner@pickware.de
author_github: Benedikt-Brunner
---
# Core
* Added `FlowExecution`-Entity to log all executions of a flow and provide insights into failing flows
___
# Next Major Version Changes
## Move flow execution after business process
* The execution of flows will be moved after the business process. Flows are collected during the request and executed on the `KernelTerminate` event. This eliminates failing business processes due to misconfigured flows.
