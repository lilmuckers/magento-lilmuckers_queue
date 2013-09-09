## Changelog

### 0.2.4 - 2013-09-09
 * Added support for Gearman
 * Added adapter abstract handles for queue systems that run callbacks directly

### 0.2.3 - 2013-09-08
 * Added support for Amazon SQS
 * Added a way to send a manual queue item to the backend

### 0.2.2 - 2013-09-07
 * Fixed an issue with custom queue handler instantiation
 * Fixed an issue with the queue watching with **beanstalkd**

### 0.2.1
 * Fixed a performance bug within the worker launcher

### 0.2.0
 * Rewrite to use dynamic `config.xml` specified workers and queues
 * `EcomDev_PhpUnit` test included
 * Changed to an adapter based arcitechture

### 0.1.0
 * Initial proof of concept using hardcoded workers