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
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StreamSocketClientTcpTest extends TestCase
{
    private StreamSocketClient $socketClient;
    private mixed $serverSocket;

    private string $host = "127.0.0.1";

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
    }

    public function tearDown(): void
    {
        unset($this->socketClient);
        if ($this->serverSocket !== null) {
            fclose($this->serverSocket);
            $this->serverSocket = null;
        }
    }

    public function testWrite(): void
    {
        $testData = "Hello World!";
        $numBytes = $this->socketClient->write($testData);

        self::assertEquals(strlen($testData), $numBytes);

        $connection = stream_socket_accept($this->serverSocket);
        $readData = fread($connection, $numBytes);

        self::assertEquals($testData, $readData);
    }

    public function testBadWrite(): void
    {
        self::expectException(RuntimeException::class);

        $this->socketClient->write("Hello ");
        fclose($this->serverSocket);
        $this->serverSocket = null;
        $this->socketClient->write("world!");
    }

    public function testMultiWrite(): void
    {
        stream_set_timeout($this->serverSocket, 0, 100);

        $testData = "First thing in the morning should be to check,";
        $numBytes = $this->socketClient->write($testData);
        self::assertEquals(strlen($testData), $numBytes);

        $serverConnection = stream_socket_accept($this->serverSocket);
        $readData = fread($serverConnection, $numBytes);
        self::assertEquals($testData, $readData);

        $testData = "if we can write multiple times on the same socket";
        $numBytes = $this->socketClient->write($testData);
        self::assertEquals(strlen($testData), $numBytes);

        $readData = fread($serverConnection, $numBytes);
        self::assertEquals($testData, $readData);

        fclose($serverConnection);
    }

    public function testRead(): void
    {
        $testData = "Hello Reader :)";
        $numBytes = $this->socketClient->write($testData);
        self::assertEquals(strlen($testData), $numBytes);

        stream_set_timeout($this->serverSocket, 0, 100);

        $connection = stream_socket_accept($this->serverSocket);
        stream_copy_to_stream($connection, $connection, strlen($testData));
        fclose($connection);

        $readData = $this->socketClient->read($numBytes);
        self::assertEquals($testData, $readData);
    }

    public function testReadContents(): void
    {
        $testData = str_repeat("0123456789", mt_rand(1, 10));
        $numBytes = $this->socketClient->write($testData);
        self::assertEquals(strlen($testData), $numBytes);

        stream_set_timeout($this->serverSocket, 0, 100);

        $connection = stream_socket_accept($this->serverSocket);
        stream_copy_to_stream($connection, $connection, strlen($testData));
        fclose($connection);

        $readData = $this->socketClient->read(1024);
        self::assertEquals($testData, $readData);
    }

    public function testCloseWithoutConnectionWrite(): void
    {
        $this->socketClient->close();
        self::assertTrue($this->socketClient->isClosed());

        $this->socketClient->write("abcd");
        self::assertFalse($this->socketClient->isClosed());
        $client = stream_socket_accept($this->serverSocket);
        self::assertEquals("abcd", fread($client, 4));
    }

    public function testCloseWrite(): void
    {
        $this->socketClient->write("abcd");
        self::assertFalse($this->socketClient->isClosed());
        $client = stream_socket_accept($this->serverSocket);
        self::assertEquals("abcd", fread($client, 4));

        $this->socketClient->close();
        self::assertTrue($this->socketClient->isClosed());

        $this->socketClient->write("efgh");
        $client2 = stream_socket_accept($this->serverSocket);
        self::assertEquals("efgh", fread($client2, 4));
    }
}
