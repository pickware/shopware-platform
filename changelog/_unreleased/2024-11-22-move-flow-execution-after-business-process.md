---
title: Move FlowExecution after BusinessProcess
issue: #5744
flag: V6_7_0_0
author: Benedikt Brunner
author_email: benedikt.brunner@pickware.de
author_github: Benedikt-Brunner
---
# Core
* Deprecated Flow execution during business process
___
# Next Major Version Changes
## Move flow execution after business process
* The execution of flows will be moved after the business process. Flows are collected during the request and executed on the `KernelTerminate` event. This eliminates failing business processes due to misconfigured flows.
