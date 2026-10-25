<?php

declare(strict_types=1);

namespace Obm;

final class PublicController
{
    public function __construct(
        private Config $config,
        private ?Repository $repo,
        private Mailer $mailer,
    ) {
    }

    public function dispatch(string $method, string $path): void
    {
        if ($path === '/sitemap.xml' && $method === 'GET') {
            $this->sitemap();
            return;
        }
        if ($path === '/robots.txt' && $method === 'GET') {
            $this->robots();
            return;
        }
        if ($path === '/contact' && $method === 'POST') {
            $this->submit('contact');
            return;
        }
        if ($path === '/prayer' && $method === 'POST') {
            $this->submit('prayer');
            return;
        }
        if ($method !== 'GET') {
            $this->notFound();
            return;
        }

        $services = $this->services();
        $schema = SchemaOrg::church($services, Http::baseUrl($this->config) . '/', Http::baseUrl($this->config) . '/assets/img/banner-1284.jpg');

        switch ($path) {
            case '/':
                $this->show('home', 'Olive Branch Ministries Inc.', 'Olive Branch Ministries Inc. gathers at 1033 1st Street SE, Moultrie, GA. Sunday worship is at 11:00 AM.', '/', $schema, [
                    'services' => $services,
                ]);
                return;
            case '/about':
                $this->show('about', 'About', Church::MISSION, '/about', $schema);
                return;
            case '/leadership':
                $this->show('leadership', 'Leadership', Church::PASTOR_CAPTION, '/leadership', $schema);
                return;
            case '/ministries':
                $related = [];
                foreach ($services as $service) {
                    if (in_array($service['name'], Church::MINISTRY_SERVICE_NAMES, true)) {
                        $related[] = $service;
                    }
                }
                $this->show('ministries', 'Ministries', "Men's Ministry, Youth Ministry, and Prayer & Fasting at Olive Branch Ministries Inc.", '/ministries', $schema, [
                    'services' => $related,
                ]);
                return;
            case '/the-hub':
                $this->show('hub', 'The Hub', 'The Hub fund at Olive Branch Ministries Inc.', '/the-hub', $schema);
                return;
            case '/events':
                if ($this->repo === null) {
                    $this->unavailable();
                    return;
                }
                $today = Clock::now()->format('Y-m-d') . ' 00:00:00';
                $events = $this->repo->upcomingEvents($today);
                $this->show('events', 'Events', 'Upcoming events at Olive Branch Ministries Inc. in Moultrie, Georgia.', '/events', $this->eventSchema($services, $events), [
                    'events' => $events,
                ]);
                return;
            case '/plan-your-visit':
                $query = rawurlencode(Church::oneLineAddress());
                $this->show('visit', 'Plan your visit', 'Service times and the address for Olive Branch Ministries Inc., 1033 1st Street SE, Moultrie, GA 31768.', '/plan-your-visit', $schema, [
                    'services' => $services,
                    'mapLink' => 'https://maps.google.com/?q=' . $query,
                    'mapEmbed' => 'https://maps.google.com/maps?q=' . $query . '&z=16&output=embed',
                ]);
                return;
            case '/give':
                $this->show('give', 'Give', 'Give in person or by mail to Olive Branch Ministries Inc., 1033 1st Street SE, Moultrie, GA 31768.', '/give', $schema, [
                    'onlineUrl' => Links::httpsUrl((string) $this->config->get('giving.online_url', '')),
                ]);
                return;
            case '/contact':
                $this->show('contact', 'Contact', 'Call, email, or write Olive Branch Ministries Inc. Prayer requests are kept for pastors and staff.', '/contact', $schema, [
                    'errors' => [],
                    'form' => [],
                    'csrfField' => Csrf::field(),
                ]);
                return;
            case '/contact/received':
                $this->show('received', 'Message received', 'Your message was received.', '/contact/received', null, ['kind' => 'contact'], 'noindex');
                return;
            case '/prayer/received':
                $this->show('received', 'Prayer request received', 'Your prayer request was received.', '/prayer/received', null, ['kind' => 'prayer'], 'noindex');
                return;
            default:
                $this->notFound();
        }
    }

    /** @param array<string, mixed> $extra */
    private function show(string $template, string $title, string $description, string $path, ?array $jsonLd, array $extra = [], ?string $robots = null, int $status = 200): void
    {
        $base = Http::baseUrl($this->config);
        View::page($template, array_merge($extra, [
            'page' => $template,
            'title' => $title,
            'description' => $description,
            'canonical' => $base . $path,
            'base' => $base,
            'jsonLd' => $jsonLd,
            'robots' => $robots,
            'image' => $base . '/assets/img/banner-1284.jpg',
        ]), $status);
    }

    private function submit(string $kind): void
    {
        Csrf::verify(Http::input('csrf'));
        if ($this->repo === null) {
            $this->unavailable();
            return;
        }
        if (!$this->repo->allowSubmission(RateWindow::formBucket($kind, Http::ip()), Clock::now())) {
            $this->show('limited', 'Please wait', 'That form was submitted too many times from this network.', '/contact', null, [], 'noindex', 429);
            return;
        }
        if (trim(Http::input('obm_leave_blank')) !== '') {
            redirect_to($kind === 'prayer' ? '/prayer/received' : '/contact/received');
        }
        $input = [
            'name' => Http::input('name'),
            'email' => Http::input('email'),
            'phone' => Http::input('phone'),
            'message' => Http::input('message'),
            'request' => Http::input('request'),
        ];
        $checked = $kind === 'prayer' ? Rules::prayer($input) : Rules::contact($input);
        if ($checked['errors'] !== []) {
            $this->show('contact', 'Contact', 'Call, email, or write Olive Branch Ministries Inc. Prayer requests are kept for pastors and staff.', '/contact', null, [
                'errors' => $checked['errors'],
                'form' => $input,
                'csrfField' => Csrf::field(),
                'errorKind' => $kind,
            ], null, 422);
            return;
        }
        $now = Clock::now()->format('Y-m-d H:i:s');
        if ($kind === 'prayer') {
            $this->repo->addPrayer($checked['data'], $now);
            $this->notify(
                'Olive Branch Ministries prayer request',
                Mailer::prayerNotice(Http::baseUrl($this->config) . '/admin/inbox'),
            );
            redirect_to('/prayer/received');
        }
        $this->repo->addContact($checked['data'], $now);
        $this->notify(
            'Olive Branch Ministries contact',
            "Name: {$checked['data']['name']}\nEmail: {$checked['data']['email']}\nPhone: " . ($checked['data']['phone'] ?? '') . "\n\n{$checked['data']['message']}\n",
            (string) $checked['data']['email'],
        );
        redirect_to('/contact/received');
    }

    private function notify(string $subject, string $body, ?string $replyTo = null): void
    {
        if (!$this->mailer->enabled()) {
            return;
        }
        $this->mailer->notify($subject, $body, $replyTo);
    }

    /** @return list<array<string, mixed>> */
    private function services(): array
    {
        if ($this->repo === null) {
            return Church::publishedServices();
        }

        return $this->repo->services(true);
    }

    private function unavailable(): void
    {
        $this->show('unavailable', 'Unavailable', 'The church records are unavailable right now.', '/unavailable', null, [], 'noindex', 500);
    }

    private function notFound(): void
    {
        $services = $this->services();
        $this->show('not-found', 'Page not found', 'That page is not on the Olive Branch Ministries site.', '/404', SchemaOrg::church($services, Http::baseUrl($this->config) . '/', ''), [], 'noindex', 404);
    }

    /**
     * @param list<array<string, mixed>> $services
     * @param list<array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private function eventSchema(array $services, array $events): array
    {
        $church = SchemaOrg::church($services, Http::baseUrl($this->config) . '/', Http::baseUrl($this->config) . '/assets/img/banner-1284.jpg');
        unset($church['@context']);
        $graph = [$church];
        foreach ($events as $event) {
            $locationName = trim((string) ($event['location'] ?? ''));
            $place = [
                '@type' => 'Place',
                'name' => $locationName !== '' ? $locationName : Church::NAME,
            ];
            if ($locationName === '') {
                $place['address'] = $church['address'];
            }
            $node = [
                '@type' => 'Event',
                'name' => (string) $event['title'],
                'description' => (string) $event['summary'],
                'startDate' => Periods::isoOffset((string) $event['starts_at']),
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'eventStatus' => 'https://schema.org/EventScheduled',
                'location' => $place,
                'organizer' => [
                    '@type' => 'Church',
                    'name' => Church::NAME,
                    'url' => Http::baseUrl($this->config) . '/',
                ],
            ];
            if (!empty($event['ends_at'])) {
                $node['endDate'] = Periods::isoOffset((string) $event['ends_at']);
            }
            $graph[] = $node;
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    private function sitemap(): void
    {
        $base = Http::baseUrl($this->config);
        $paths = ['/', '/about', '/leadership', '/ministries', '/the-hub', '/events', '/plan-your-visit', '/give', '/contact'];
        header('Content-Type: application/xml; charset=utf-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($paths as $path) {
            echo '  <url><loc>' . e($base . $path) . "</loc></url>\n";
        }
        echo "</urlset>\n";
    }

    private function robots(): void
    {
        $base = Http::baseUrl($this->config);
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: {$base}/sitemap.xml\n";
    }
}
