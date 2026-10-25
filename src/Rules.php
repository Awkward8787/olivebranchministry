<?php

declare(strict_types=1);

namespace Obm;

use InvalidArgumentException;

/**
 * Server-side form rules. Money and dates go through Money and Periods.
 */
final class Rules
{
    /** @var list<string> */
    public const GIFT_METHODS = ['cash', 'check', 'online', 'other'];

    /**
     * @param array<string, string> $input
     * @param list<int> $fundIds
     * @param list<int> $personIds
     * @return array{errors:array<string, string>, data:array<string, mixed>}
     */
    public static function gift(array $input, array $fundIds, array $personIds): array
    {
        $errors = [];
        $cents = null;
        try {
            $cents = Money::parseCents($input['amount'] ?? '');
            if ($cents <= 0) {
                $errors['amount'] = 'Enter an amount greater than zero.';
            }
        } catch (InvalidArgumentException $e) {
            $errors['amount'] = $e->getMessage();
        }

        $date = trim($input['date'] ?? '');
        if (!Periods::isDate($date)) {
            $errors['date'] = 'Enter a real date.';
        }

        $method = $input['method'] ?? '';
        if (!in_array($method, self::GIFT_METHODS, true)) {
            $errors['method'] = 'Choose cash, check, online, or other.';
        }

        $reference = trim($input['reference'] ?? '');
        if (strlen($reference) > 80) {
            $errors['reference'] = 'Reference must be 80 characters or fewer.';
        }
        if ($method === 'check' && $reference === '') {
            $errors['reference'] = 'Enter the check number.';
        }

        $fundId = self::id($input['fund_id'] ?? '');
        if ($fundId === null || !in_array($fundId, $fundIds, true)) {
            $errors['fund_id'] = 'Choose a fund.';
        }

        $personRaw = trim($input['person_id'] ?? '');
        $personId = null;
        if ($personRaw !== '') {
            $personId = self::id($personRaw);
            if ($personId === null || !in_array($personId, $personIds, true)) {
                $errors['person_id'] = 'Choose a person or leave the gift anonymous.';
            }
        }

        $note = trim($input['note'] ?? '');
        if (strlen($note) > 500) {
            $errors['note'] = 'Note must be 500 characters or fewer.';
        }
        $goods = trim($input['goods_or_services'] ?? '');
        if (strlen($goods) > 500) {
            $errors['goods_or_services'] = 'That note must be 500 characters or fewer.';
        }
        $goodsValueRaw = trim($input['goods_value'] ?? '');
        $goodsValue = null;
        if ($goods !== '' && $goodsValueRaw === '') {
            $errors['goods_value'] = 'Enter a good-faith estimate when goods or services were provided.';
        } elseif ($goods === '' && $goodsValueRaw !== '') {
            $errors['goods_value'] = 'Leave the estimate blank when no goods or services were provided.';
        } elseif ($goodsValueRaw !== '') {
            try {
                $goodsValue = Money::parseCents($goodsValueRaw);
            } catch (InvalidArgumentException $e) {
                $errors['goods_value'] = $e->getMessage();
            }
        }

        return [
            'errors' => $errors,
            'data' => [
                'cents' => $cents,
                'date' => $date,
                'method' => $method,
                'reference' => $reference === '' ? null : $reference,
                'fund_id' => $fundId,
                'person_id' => $personId,
                'note' => $note === '' ? null : $note,
                'goods_or_services' => $goods === '' ? null : $goods,
                'goods_value_cents' => $goodsValue,
            ],
        ];
    }

    /**
     * A void or adjustment. The reason is required. The amount is a signed
     * cent delta. A void reverses whatever net remains.
     *
     * @param array<string, string> $input
     * @return array{errors:array<string, string>, data:array<string, mixed>}
     */
    public static function giftEntry(array $input, int $currentNet): array
    {
        $errors = [];
        $currentNet = Money::assertCents($currentNet);
        $kind = $input['kind'] ?? '';
        if (!in_array($kind, ['adjustment', 'void'], true)) {
            $errors['kind'] = 'Choose an adjustment or a void.';
        }
        $reason = trim($input['reason'] ?? '');
        if ($reason === '') {
            $errors['reason'] = 'Enter a reason.';
        } elseif (strlen($reason) > 500) {
            $errors['reason'] = 'Reason must be 500 characters or fewer.';
        }
        $signed = null;
        if ($kind === 'void') {
            if ($currentNet <= 0) {
                $errors['kind'] = 'This gift is already zero.';
            } else {
                $signed = -$currentNet;
            }
        } elseif ($kind === 'adjustment') {
            $cents = null;
            try {
                $cents = Money::parseCents($input['amount'] ?? '');
                if ($cents <= 0) {
                    $errors['amount'] = 'Enter an amount greater than zero.';
                }
            } catch (InvalidArgumentException $e) {
                $errors['amount'] = $e->getMessage();
            }
            $direction = $input['direction'] ?? '';
            if (!in_array($direction, ['increase', 'decrease'], true)) {
                $errors['direction'] = 'Choose increase or decrease.';
            }
            if ($cents !== null && $cents > 0 && $errors === []) {
                $signed = $direction === 'decrease' ? -$cents : $cents;
                if ($signed < 0 && -$signed > $currentNet) {
                    $errors['amount'] = 'An adjustment cannot reduce a gift below zero.';
                }
            }
        }

        return [
            'errors' => $errors,
            'data' => [
                'kind' => $kind,
                'amount_cents' => $signed,
                'reason' => $reason,
            ],
        ];
    }

    /**
     * @param array<string, string> $input
     * @param list<int> $serviceIds
     * @return array{errors:array<string, string>, data:array<string, mixed>}
     */
    public static function attendance(array $input, array $serviceIds): array
    {
        $errors = [];
        $date = trim($input['date'] ?? '');
        if (!Periods::isDate($date)) {
            $errors['date'] = 'Enter a real date.';
        }
        $countRaw = trim($input['count'] ?? '');
        $count = null;
        if (preg_match('/^\d{1,7}$/', $countRaw) !== 1) {
            $errors['count'] = 'Enter a whole number, zero or more.';
        } else {
            $count = (int) $countRaw;
        }
        $label = trim($input['label'] ?? '');
        if ($label === '' || strlen($label) > 120) {
            $errors['label'] = 'Enter the service name (120 characters or fewer).';
        }
        $serviceRaw = trim($input['service_id'] ?? '');
        $serviceId = null;
        if ($serviceRaw !== '') {
            $serviceId = self::id($serviceRaw);
            if ($serviceId === null || !in_array($serviceId, $serviceIds, true)) {
                $errors['service_id'] = 'Choose a service from the list.';
            }
        }
        $notes = trim($input['notes'] ?? '');
        if (strlen($notes) > 500) {
            $errors['notes'] = 'Note must be 500 characters or fewer.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'date' => $date,
                'count' => $count,
                'label' => $label,
                'service_id' => $serviceId,
                'notes' => $notes === '' ? null : $notes,
            ],
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array{errors:array<string, string>, data:array<string, mixed>}
     */
    public static function person(array $input): array
    {
        $errors = [];
        $first = trim($input['first_name'] ?? '');
        $last = trim($input['last_name'] ?? '');
        if ($first === '' || strlen($first) > 80) {
            $errors['first_name'] = 'Enter a first name (80 characters or fewer).';
        }
        if ($last === '' || strlen($last) > 80) {
            $errors['last_name'] = 'Enter a last name (80 characters or fewer).';
        }
        $email = trim($input['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email or leave it blank.';
        }
        if (strlen($email) > 190) {
            $errors['email'] = 'Email must be 190 characters or fewer.';
        }
        $phone = trim($input['phone'] ?? '');
        if (strlen($phone) > 40) {
            $errors['phone'] = 'Phone must be 40 characters or fewer.';
        }
        $kind = $input['kind'] ?? '';
        if (!in_array($kind, ['member', 'visitor'], true)) {
            $errors['kind'] = 'Choose member or visitor.';
        }
        $status = $input['follow_up_status'] ?? '';
        if (!in_array($status, ['new', 'contacted'], true)) {
            $errors['follow_up_status'] = 'Choose new or contacted.';
        }
        $follow = trim($input['follow_up_on'] ?? '');
        if ($follow !== '' && !Periods::isDate($follow)) {
            $errors['follow_up_on'] = 'Enter a real follow-up date or leave it blank.';
        }
        $notes = trim($input['notes'] ?? '');
        if (strlen($notes) > 2000) {
            $errors['notes'] = 'Notes must be 2,000 characters or fewer.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email === '' ? null : $email,
                'phone' => $phone === '' ? null : $phone,
                'kind' => $kind,
                'follow_up_status' => $status,
                'follow_up_on' => $follow === '' ? null : $follow,
                'notes' => $notes === '' ? null : $notes,
            ],
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array{errors:array<string, string>, data:array<string, mixed>}
     */
    public static function contact(array $input): array
    {
        $errors = [];
        $name = trim($input['name'] ?? '');
        if ($name === '' || strlen($name) > 120) {
            $errors['name'] = 'Enter your name (120 characters or fewer).';
        }
        $email = trim($input['email'] ?? '');
        if ($email === '' || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        $phone = trim($input['phone'] ?? '');
        if (strlen($phone) > 40) {
            $errors['phone'] = 'Phone must be 40 characters or fewer.';
        }
        $message = trim($input['message'] ?? '');
        if ($message === '' || strlen($message) > 5000) {
            $errors['message'] = 'Enter a message (5,000 characters or fewer).';
        }

        return [
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'email' => $email,
                'phone' => $phone === '' ? null : $phone,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array{errors:array<string, string>, data:array<string, mixed>}
     */
    public static function prayer(array $input): array
    {
        $errors = [];
        $name = trim($input['name'] ?? '');
        if ($name === '' || strlen($name) > 120) {
            $errors['name'] = 'Enter your name (120 characters or fewer).';
        }
        $email = trim($input['email'] ?? '');
        if ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $errors['email'] = 'Enter a valid email or leave it blank.';
        }
        $phone = trim($input['phone'] ?? '');
        if (strlen($phone) > 40) {
            $errors['phone'] = 'Phone must be 40 characters or fewer.';
        }
        $request = trim($input['request'] ?? '');
        if ($request === '' || strlen($request) > 5000) {
            $errors['request'] = 'Enter the prayer request (5,000 characters or fewer).';
        }

        return [
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'email' => $email === '' ? null : $email,
                'phone' => $phone === '' ? null : $phone,
                'request' => $request,
            ],
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array{errors:array<string, string>, data:array<string, mixed>}
     */
    public static function service(array $input): array
    {
        $errors = [];
        $name = trim($input['name'] ?? '');
        if ($name === '' || strlen($name) > 120) {
            $errors['name'] = 'Enter the service name.';
        }
        $weekday = $input['weekday'] ?? '';
        if (preg_match('/^[0-6]$/', $weekday) !== 1) {
            $errors['weekday'] = 'Choose a day.';
        }
        $start = self::time($input['start_time'] ?? '');
        if ($start === null) {
            $errors['start_time'] = 'Enter a start time.';
        }
        $endRaw = trim($input['end_time'] ?? '');
        $end = null;
        if ($endRaw !== '') {
            $end = self::time($endRaw);
            if ($end === null) {
                $errors['end_time'] = 'Enter a real end time or leave it blank.';
            } elseif ($start !== null && $end <= $start) {
                $errors['end_time'] = 'End time must be after the start time.';
            }
        }
        $note = trim($input['note'] ?? '');
        if (strlen($note) > 180) {
            $errors['note'] = 'Note must be 180 characters or fewer.';
        }
        $sortRaw = trim($input['sort_order'] ?? '0');
        $sort = null;
        if (preg_match('/^-?\d{1,4}$/', $sortRaw) !== 1) {
            $errors['sort_order'] = 'Enter a whole-number sort order.';
        } else {
            $sort = (int) $sortRaw;
        }
        $active = ($input['active'] ?? '') === '1' ? 1 : 0;
        $weekly = ($input['weekly'] ?? '') === '1' ? 1 : 0;

        return [
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'weekday' => $weekday === '' ? null : (int) $weekday,
                'start_time' => $start,
                'end_time' => $end,
                'note' => $note === '' ? null : $note,
                'sort_order' => $sort,
                'active' => $active,
                'weekly' => $weekly,
            ],
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array{errors:array<string, string>, data:array<string, mixed>}
     */
    public static function event(array $input): array
    {
        $errors = [];
        $title = trim($input['title'] ?? '');
        if ($title === '' || strlen($title) > 180) {
            $errors['title'] = 'Enter a title (180 characters or fewer).';
        }
        $summary = trim($input['summary'] ?? '');
        if ($summary === '' || strlen($summary) > 5000) {
            $errors['summary'] = 'Enter a summary (5,000 characters or fewer).';
        }
        $starts = Periods::tryDateTime($input['starts_at'] ?? '');
        if ($starts === null) {
            $errors['starts_at'] = 'Enter a real start date and time. The 2:00 AM spring-forward hour is not a real time.';
        }
        $endRaw = trim($input['ends_at'] ?? '');
        $ends = null;
        if ($endRaw !== '') {
            $ends = Periods::tryDateTime($endRaw);
            if ($ends === null) {
                $errors['ends_at'] = 'Enter a real end date and time or leave it blank.';
            } elseif ($starts !== null && $ends <= $starts) {
                $errors['ends_at'] = 'End must be after the start.';
            }
        }
        $location = trim($input['location'] ?? '');
        if (strlen($location) > 180) {
            $errors['location'] = 'Location must be 180 characters or fewer.';
        }
        $published = ($input['published'] ?? '') === '1' ? 1 : 0;

        return [
            'errors' => $errors,
            'data' => [
                'title' => $title,
                'summary' => $summary,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'location' => $location === '' ? null : $location,
                'published' => $published,
            ],
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array{errors:array<string, string>, data:array<string, mixed>}
     */
    public static function user(array $input, bool $passwordRequired): array
    {
        $errors = [];
        $name = trim($input['name'] ?? '');
        if ($name === '' || strlen($name) > 120) {
            $errors['name'] = 'Enter a name.';
        }
        $email = strtolower(trim($input['email'] ?? ''));
        if ($email === '' || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email.';
        }
        $role = $input['role'] ?? '';
        if (!in_array($role, ['admin', 'staff'], true)) {
            $errors['role'] = 'Choose admin or staff.';
        }
        $password = (string) ($input['password'] ?? '');
        if (str_contains($password, "\0")) {
            $errors['password'] = 'That password cannot be used.';
        } elseif ($password === '') {
            if ($passwordRequired) {
                $errors['password'] = 'Enter a password of 12 to 72 characters.';
            }
        } elseif (strlen($password) < 12 || strlen($password) > 72) {
            $errors['password'] = 'Use 12 to 72 characters. The whole password is stored in the hash.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'password' => $password === '' ? null : $password,
            ],
        ];
    }

    public static function keepsAnAdmin(int $adminCount, string $currentRole, string $newRole): bool
    {
        if ($currentRole === 'admin' && $newRole !== 'admin' && $adminCount <= 1) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, string> $input
     * @return array{errors:array<string, string>, data:array<string, mixed>}
     */
    public static function fund(array $input): array
    {
        $errors = [];
        $name = trim($input['name'] ?? '');
        if ($name === '' || strlen($name) > 80) {
            $errors['name'] = 'Enter a fund name (80 characters or fewer).';
        }
        $sortRaw = trim($input['sort_order'] ?? '0');
        $sort = 0;
        if (preg_match('/^-?\d{1,4}$/', $sortRaw) !== 1) {
            $errors['sort_order'] = 'Enter a whole-number sort order.';
        } else {
            $sort = (int) $sortRaw;
        }
        $active = ($input['active'] ?? '') === '1' ? 1 : 0;
        $org = $input['receiving_org'] ?? GiftPolicy::CHURCH;
        if (!in_array($org, [GiftPolicy::CHURCH, GiftPolicy::UNCONFIRMED], true)) {
            $errors['receiving_org'] = 'Choose who receives this fund.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'sort_order' => $sort,
                'active' => $active,
                'receiving_org' => $org === GiftPolicy::CHURCH ? GiftPolicy::CHURCH : GiftPolicy::UNCONFIRMED,
            ],
        ];
    }

    private static function id(string $value): ?int
    {
        if (preg_match('/^[1-9]\d{0,8}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private static function time(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $m) !== 1) {
            return null;
        }
        $hour = (int) $m[1];
        $minute = (int) $m[2];
        $second = isset($m[3]) ? (int) $m[3] : 0;
        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }
}
