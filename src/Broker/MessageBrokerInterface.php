<?php

namespace Kei\Lwphp\Broker;

interface MessageBrokerInterface
{
    /**
     * Publishes a message to a specific queue or topic.
     *
     * @param string $queue The name of the queue or topic
     * @param mixed $message The message payload (array, json, object)
     * @param array $options Additional broker-specific options
     */
    public function publish(string $queue, mixed $message, array $options = []): void;

    /**
     * Subscribes to a specific queue and registers a callback to handle incoming messages.
     *
     * @param string $queue The name of the queue or topic
     * @param callable $handler The callback function that processes the message
     */
    public function consume(string $queue, callable $handler): void;

    /**
     * Acknowledges that a particular message was processed successfully.
     * Required for highly durable queues like RabbitMQ or SQS.
     *
     * @param mixed $messageId
     */
    public function ack(mixed $messageId): void;

    /**
     * Rejects a message (nack), optionally requeuing it for later.
     *
     * @param mixed $messageId
     * @param bool $requeue
     */
    public function nack(mixed $messageId, bool $requeue = true): void;
}
