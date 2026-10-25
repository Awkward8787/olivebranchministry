<?php

declare(strict_types=1);

namespace Obm;

final class Mailer
{
    public function __construct(private Config $config)
    {
    }

    public static function prayerNotice(string $inboxUrl): string
    {
        return "A new prayer request is waiting.\n\nOpen the office inbox: {$inboxUrl}\n";
    }

    public static function acceptableFrom(string $from, ?string $siteHost): bool
    {
        $from = trim($from);
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $domain = strtolower((string) substr(strrchr($from, '@'), 1));
        if ($domain === '' || $domain === 'gmail.com' || $domain === 'googlemail.com') {
            return false;
        }
        if ($siteHost === null || $siteHost === '' || filter_var($siteHost, FILTER_VALIDATE_IP) || strtolower($siteHost) === 'localhost') {
            return true;
        }
        $siteHost = strtolower($siteHost);

        return $domain === $siteHost
            || str_ends_with($siteHost, '.' . $domain)
            || str_ends_with($domain, '.' . $siteHost);
    }

    public function enabled(): bool
    {
        return filter_var($this->recipient(), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function notify(string $subject, string $body, ?string $replyTo = null): bool
    {
        if (!$this->enabled()) {
            return false;
        }
        $from = trim((string) $this->config->get('mail.from', ''));
        if (!self::acceptableFrom($from, $this->siteHost())) {
            error_log('Mailer: mail.from is missing or is not on the site domain. Notification not sent.');

            return false;
        }
        $subject = str_replace(["\r", "\n"], '', $subject);
        $headers = 'From: ' . $from . "\r\nContent-Type: text/plain; charset=UTF-8";
        $replyTo = trim((string) $replyTo);
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false && !str_contains($replyTo, "\n") && !str_contains($replyTo, "\r")) {
            $headers .= "\r\nReply-To: " . $replyTo;
        }
        $ok = mail($this->recipient(), $subject, $body, $headers);
        error_log('Mailer: mail() ' . ($ok ? 'accepted' : 'returned false'));

        return $ok;
    }

    private function recipient(): string
    {
        return trim((string) $this->config->get('mail.notify_to', ''));
    }

    private function siteHost(): ?string
    {
        $url = (string) $this->config->get('app.url', '');
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
