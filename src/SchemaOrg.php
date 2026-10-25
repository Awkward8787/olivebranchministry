<?php

declare(strict_types=1);

namespace Obm;

/**
 * Church JSON-LD. Geo is omitted because coordinates were not verified.
 * Weekly opening hours come from service rows. A service that is not weekly
 * (the fourth Sunday evening service) stays in the description only.
 */
final class SchemaOrg
{
    /**
     * @param list<array<string, mixed>> $services
     * @return array<string, mixed>
     */
    public static function church(array $services, string $pageUrl, string $imageUrl = ''): array
    {
        $specs = [];
        $sentences = [];
        foreach ($services as $service) {
            $sentences[] = ScheduleFormat::sentence($service);
            if (!(int) ($service['weekly'] ?? 0)) {
                continue;
            }
            $day = ScheduleFormat::weekday((int) $service['weekday']);
            $spec = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/' . $day,
                'opens' => substr((string) $service['start_time'], 0, 5),
                'description' => (string) $service['name'],
            ];
            if (!empty($service['end_time'])) {
                $spec['closes'] = substr((string) $service['end_time'], 0, 5);
            }
            $specs[] = $spec;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Church',
            'name' => Church::NAME,
            'description' => implode(' ', $sentences),
            'url' => $pageUrl,
            'telephone' => Church::PHONE_E164,
            'email' => Church::EMAIL,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => Church::STREET,
                'addressLocality' => Church::CITY,
                'addressRegion' => Church::REGION,
                'postalCode' => Church::POSTAL,
                'addressCountry' => 'US',
            ],
            'sameAs' => [Church::FACEBOOK],
            'openingHoursSpecification' => $specs,
        ];
        if ($imageUrl !== '') {
            $data['image'] = $imageUrl;
        }

        return $data;
    }
}
