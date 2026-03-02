<?php

namespace Kei\Lwphp\Broker;

interface StreamBrokerInterface
{
    /**
     * Appends a record to an event stream (e.g. Kafka, Redis Streams).
     *
     * @param string $stream The stream identifier
     * @param array $payload The event payload
     * @return string The generated stream ID or offset
     */
    public function append(string $stream, array $payload): string;

    /**
     * Reads continuously from a stream, optionally from a specific offset or Consumer Group.
     *
     * @param string $stream The stream identifier
     * @param string $group The consumer group name (if applicable)
     * @param string $consumer The specific consumer instance ID inside the group
     * @param callable $handler Function to execute on each event
     */
    public function readGroup(string $stream, string $group, string $consumer, callable $handler): void;

    /**
     * Creates a new consumer group for a stream.
     *
     * @param string $stream
     * @param string $group
     */
    public function createGroup(string $stream, string $group): void;
}
