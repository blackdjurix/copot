<?php

namespace Copot\Core;

final class NativeSmtpTransportDriver implements SmtpTransportDriver
{
    public function deliver(
        EmailMessage $message,
        EmailSenderIdentity $sender,
        EmailTransportConfiguration $transport,
        string $credential
    ): void {
        $socket = null;

        try {
            $scheme = $transport->security() === 'ssl' ? 'ssl://' : 'tcp://';
            $errorNumber = 0;
            $errorMessage = '';
            $socket = @stream_socket_client(
                $scheme . $transport->host() . ':' . $transport->port(),
                $errorNumber,
                $errorMessage,
                10,
                STREAM_CLIENT_CONNECT
            );

            if (!is_resource($socket)) {
                throw new \RuntimeException('SMTP connection failed.');
            }

            stream_set_timeout($socket, 10);
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO localhost', 250);

            if ($transport->security() === 'tls') {
                $this->command($socket, 'STARTTLS', 220);
                if (@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                    throw new \RuntimeException('SMTP TLS negotiation failed.');
                }
                $this->command($socket, 'EHLO localhost', 250);
            }

            if ($transport->username() !== null) {
                $this->command($socket, 'AUTH LOGIN', 334);
                $this->command($socket, base64_encode($transport->username()), 334);
                $this->command($socket, base64_encode($credential), 235);
            }

            $this->command($socket, 'MAIL FROM:<' . $sender->email() . '>', 250);
            $this->command($socket, 'RCPT TO:<' . $message->recipient() . '>', 250);
            $this->command($socket, 'DATA', 354);
            $this->writeMessage($socket, $message, $sender);
            $this->expect($socket, 250);
            @fwrite($socket, "QUIT\r\n");
        } catch (EmailTransportException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new EmailTransportException('SMTP transport operation failed.');
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    private function command($socket, string $command, int $expected): void
    {
        if (@fwrite($socket, $command . "\r\n") === false) {
            throw new EmailTransportException('SMTP transport operation failed.');
        }
        $this->expect($socket, $expected);
    }

    private function expect($socket, int $expected): void
    {
        $line = @fgets($socket);
        if (!is_string($line) || !preg_match('/^(\d{3})[ -]/', $line, $matches) || (int) $matches[1] !== $expected) {
            throw new EmailTransportException('SMTP transport operation failed.');
        }

        while (isset($line[3]) && $line[3] === '-') {
            $line = @fgets($socket);
            if (!is_string($line)) {
                throw new EmailTransportException('SMTP transport operation failed.');
            }
        }
    }

    private function writeMessage($socket, EmailMessage $message, EmailSenderIdentity $sender): void
    {
        $senderName = addcslashes($sender->name(), "\\\"");
        $body = preg_replace('/\r\n|\r|\n/', "\r\n", $message->body());
        $body = is_string($body) ? preg_replace('/^\./m', '..', $body) : false;
        $payload = "From: \"{$senderName}\" <{$sender->email()}>\r\n"
            . "To: <{$message->recipient()}>\r\n"
            . 'Subject: ' . $message->subject() . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "\r\n"
            . (is_string($body) ? $body : '')
            . "\r\n.\r\n";

        $offset = 0;
        while ($offset < strlen($payload)) {
            $written = @fwrite($socket, substr($payload, $offset));
            if (!is_int($written) || $written < 1) {
                throw new EmailTransportException('SMTP transport operation failed.');
            }
            $offset += $written;
        }
    }
}
