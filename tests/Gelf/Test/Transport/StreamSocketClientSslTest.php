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

use Gelf\Transport\SslOptions;
use Gelf\Transport\StreamSocketClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StreamSocketClientSslTest extends TestCase
{
    public function testSslConnectionToPlainServerFails(): void
    {
        self::expectException(RuntimeException::class);

        $server = stream_socket_server("tcp://127.0.0.1:0");
        $socketName = stream_socket_get_name($server, remote: false);
        [, $port] = explode(":", $socketName);

        try {
            $client = new StreamSocketClient('ssl', '127.0.0.1', (int)$port);
            $client->getSocket();
        } finally {
            fclose($server);
        }
    }

    public function testSslContextIsPreserved(): void
    {
        $options = new SslOptions();
        $options->setVerifyPeer(false);
        $options->setAllowSelfSigned(true);

        $context = $options->toStreamContext();

        $client = new StreamSocketClient('ssl', '127.0.0.1', 12201, $context);
        self::assertEquals($context, $client->getContext());
    }
}
