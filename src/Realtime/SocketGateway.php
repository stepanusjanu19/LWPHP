<?php

namespace Kei\Lwphp\Realtime;

/**
 * Interface SocketGateway
 *
 * Defines the contract for broadcasting realtime events to connected WebSocket clients.
 */
interface SocketGateway
{
    /**
     * Broadcasts an event to a specific channel.
     *
     * @param string $channel The channel or topic name (e.g. "chat-room-1")
     * @param string $event The name of the event (e.g. "user.joined")
     * @param array $payload The JSON-serializable payload data
     */
    public function broadcast(string $channel, string $event, array $payload = []): void;

    /**
     * Sends an event directly to a specific user or connection ID.
     *
     * @param string|int $identifier The User ID or Connection ID
     * @param string $event The name of the event
     * @param array $payload The event payload
     */
    public function emit(string|int $identifier, string $event, array $payload = []): void;

    /**
     * Authenticates a user trying to join a private or presence channel.
     *
     * @param string $channel The private channel name
     * @param string $socketId The connecting client's socket ID
     * @param int|string|null $userId Optional user ID requesting access
     * @return bool True if authorized, false otherwise
     */
    public function authorize(string $channel, string $socketId, int|string $userId = null): bool;
}
