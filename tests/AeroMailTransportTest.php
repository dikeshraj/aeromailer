<?php

use PHPUnit\Framework\TestCase;
use DikeshRajGiri\AeroMailer\Transport\AeroMailTransport;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class AeroMailTransportTest extends TestCase
{
    public function testSendSuccess()
    {
        $client = $this->createMock(ClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(202);
        $response->method('getBody')->willReturn('ok');
        $response->method('getHeaderLine')->willReturn('');

        $client->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('POST'),
                $this->stringContains('/send'),
                $this->callback(function ($options) {
                    // assert json body has expected keys
                    $this->assertArrayHasKey('json', $options);
                    $json = $options['json'];
                    $this->assertArrayHasKey('subject', $json);
                    $this->assertArrayHasKey('recipients', $json);
                    return true;
                })
            )
            ->willReturn($response);

        $transport = new AeroMailTransport(
            'testkey',
            'https://api.example.test',
            $client,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LoggerInterface::class),
            [
                'send_path' => '/send',
                'auth' => ['type' => 'bearer']
            ]
        );

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('Hello')
            ->text('Hi');

        $sentMessage = new \Symfony\Component\Mailer\SentMessage($email);

        // should not throw
        $transport->send($sentMessage);
    }
}
