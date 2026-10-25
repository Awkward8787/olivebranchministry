<?php

declare(strict_types=1);

namespace Obm;

final class Mailer
{
    public function __construct(private Config $config)
    {
    }

    public function enabled(): bool
    {
        return filter_var($this->recipient(), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function notify(string $subject, string $body): bool
    {
        if (!$this->enabled()) {
            return false;
        }
        $subject = str_replace(["\r", "\n"], '', $subject);
        $from = trim((string) $this->config->get('mail.from', ''));
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            $from = $this->recipient();
        }
        $headers = 'From: ' . $from . "\r\nContent-Type: text/plain; charset=UTF-8";

        return mail($this->recipient(), $subject, $body, $headers);
    }

    private function recipient(): string
    {
        return trim((string) $this->config->get('mail.notify_to', ''));
    }
}
