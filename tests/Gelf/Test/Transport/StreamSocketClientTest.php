<?php
declare(strict_types=1);

/*
 * This file is part of the php-gelf package.
 *
 * (c) Benjamin Zikarsky <http://benjamin-zikarsky.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gelf\Test\Transport;

use Gelf\Transport\StreamSocketClient;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StreamSocketClientTest extends TestCase
{
    private StreamSocketClient $socketClient;
    private mixed $serverSocket;

    private string $host = "127.0.0.1";
    private int $port;

    public function setUp(): void
    {
        $host = $this->host;
        $this->serverSocket = stream_socket_server("tcp://$host:0");

        if (!$this->serverSocket) {
            throw new RuntimeException("Failed to create test-server-socket");
        }

        $socketName = stream_socket_get_name($this->serverSocket, remote: false);
        [, $port] = explode(":", $socketName);

        $this->socketClient = new StreamSocketClient('tcp', $host, (int)$port);
        $this->port = (int)$port;
    }

    public function tearDown(): void
    {
        unset($this->socketClient);
        if ($this->serverSocket !== null) {
            fclose($this->serverSocket);
            $this->serverSocket = null;
        }
    }

    public function testInvalidConstructorArguments(): void
    {
        self::expectException(RuntimeException::class);

        $client = new StreamSocketClient("not-a-scheme", "not-a-host", -1);
        $client->getSocket();
    }

    public function testGetSocket(): void
    {
        self::assertIsResource($this->socketClient->getSocket());
    }

    public function testStreamContext(): void
    {
        $testName = '127.0.0.1:12345';
        $context = [
            'socket' => [
                'bindto' => $testName
            ]
        ];

        $client = new StreamSocketClient("tcp", $this->host, $this->port, $context);
        self::assertEquals($context, $client->getContext());

        self::assertEquals($testName, stream_socket_get_name($client->getSocket(), false));
        self::assertNotEquals($testName, stream_socket_get_name($this->socketClient->getSocket(), false));
    }

    public function testUpdateStreamContext(): void
    {
        $testName = '127.0.0.1:12345';
        $context = [
            'socket' => [
                'bindto' => $testName
            ]
        ];

        self::assertEquals([], $this->socketClient->getContext());
        self::assertNotEquals($testName, stream_socket_get_name($this->socketClient->getSocket(), false));
        $this->socketClient->close();

        $this->socketClient->setContext($context);
        self::assertEquals($context, $this->socketClient->getContext());

        self::assertEquals($testName, stream_socket_get_name($this->socketClient->getSocket(), false));
    }

    public function testSetContextFailsAfterConnect(): void
    {
        self::expectException(LogicException::class);

        $this->socketClient->getSocket();
        $this->socketClient->setContext(["foo" => "bar"]);
    }

    public function testSetConnectTimeoutFailsAfterConnect(): void
    {
        self::expectException(LogicException::class);

        $this->socketClient->getSocket();
        $this->socketClient->setConnectTimeout(1);
    }

    public function testConnectTimeout(): void
    {
        $this->socketClient->setConnectTimeout(1);
        self::assertEquals(1, $this->socketClient->getConnectTimeout());
    }
}
