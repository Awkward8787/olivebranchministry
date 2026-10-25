<?php

declare(strict_types=1);

namespace Obm;

use PDOException;

final class AdminController
{
    public function __construct(
        private Config $config,
        private Repository $repo,
        private Auth $auth,
    ) {
    }

    public function dispatch(string $method, string $path): void
    {
        if ($path === '/admin/login') {
            if ($method === 'POST') {
                $this->login();
                return;
            }
            if ($method === 'GET') {
                $this->loginForm();
                return;
            }
            $this->missing(null);
            return;
        }
        if ($path === '/admin/logout' && $method === 'POST') {
            Csrf::verify(Http::input('csrf'));
            $this->auth->logout();
            redirect_to('/admin/login');
        }

        $user = $this->auth->requireUser();

        if ($method === 'GET' && ($path === '/admin' || $path === '/admin/dashboard')) {
            $this->dashboard($user);
            return;
        }
        if ($path === '/admin/inbox') {
            $method === 'POST' ? $this->markInbox($user) : $this->inbox($user);
            return;
        }
        if ($path === '/admin/people' || $path === '/admin/people/new') {
            $this->people($user, $method, $path === '/admin/people/new' ? 0 : null);
            return;
        }
        if (preg_match('#^/admin/people/([1-9]\d{0,8})$#', $path, $m) === 1) {
            $this->people($user, $method, (int) $m[1]);
            return;
        }
        if ($path === '/admin/giving' || $path === '/admin/giving/new') {
            $this->giving($user, $method, $path === '/admin/giving/new' ? 0 : null);
            return;
        }
        if (preg_match('#^/admin/giving/([1-9]\d{0,8})/delete$#', $path, $m) === 1) {
            $this->deleteGift($user, $method, (int) $m[1]);
            return;
        }
        if (preg_match('#^/admin/giving/([1-9]\d{0,8})$#', $path, $m) === 1) {
            $this->giving($user, $method, (int) $m[1]);
            return;
        }
        if ($path === '/admin/statements' && $method === 'GET') {
            $this->statement($user);
            return;
        }
        if ($path === '/admin/attendance' || $path === '/admin/attendance/new') {
            $this->attendance($user, $method, $path === '/admin/attendance/new' ? 0 : null);
            return;
        }
        if (preg_match('#^/admin/attendance/([1-9]\d{0,8})$#', $path, $m) === 1) {
            $this->attendance($user, $method, (int) $m[1]);
            return;
        }
        if ($path === '/admin/events' || $path === '/admin/events/new') {
            $this->events($user, $method, $path === '/admin/events/new' ? 0 : null);
            return;
        }
        if (preg_match('#^/admin/events/([1-9]\d{0,8})$#', $path, $m) === 1) {
            $this->events($user, $method, (int) $m[1]);
            return;
        }
        if ($path === '/admin/services' || $path === '/admin/services/new') {
            $this->services($user, $method, $path === '/admin/services/new' ? 0 : null);
            return;
        }
        if (preg_match('#^/admin/services/([1-9]\d{0,8})$#', $path, $m) === 1) {
            $this->services($user, $method, (int) $m[1]);
            return;
        }
        if ($path === '/admin/funds' || $path === '/admin/funds/new') {
            $this->funds($user, $method, $path === '/admin/funds/new' ? 0 : null);
            return;
        }
        if (preg_match('#^/admin/funds/([1-9]\d{0,8})$#', $path, $m) === 1) {
            $this->funds($user, $method, (int) $m[1]);
            return;
        }
        if ($path === '/admin/users' || $path === '/admin/users/new') {
            $this->users($user, $method, $path === '/admin/users/new' ? 0 : null);
            return;
        }
        if (preg_match('#^/admin/users/([1-9]\d{0,8})$#', $path, $m) === 1) {
            $this->users($user, $method, (int) $m[1]);
            return;
        }

        $this->missing($user);
    }

    private function loginForm(): void
    {
        $existing = $this->auth->user();
        if ($existing !== null) {
            redirect_to('/admin');
        }
        $this->render('login', 'Sign in', null, 'login', [
            'error' => null,
            'email' => '',
            'timeout' => Http::query('timeout') === '1' || $this->auth->timedOut(),
        ]);
    }

    private function login(): void
    {
        Csrf::verify(Http::input('csrf'));
        $email = Http::input('email');
        $result = $this->auth->attempt($email, Http::input('password'), Http::ip());
        if ($result === 'ok') {
            redirect_to('/admin');
        }
        $this->render('login', 'Sign in', null, 'login', [
            'error' => $result === 'throttled'
                ? 'Too many attempts. Wait fifteen minutes and try again.'
                : 'The email or password is not right.',
            'email' => $email,
            'timeout' => false,
        ], $result === 'throttled' ? 429 : 401);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function dashboard(array $user): void
    {
        $now = Clock::now();
        $month = Periods::currentMonth($now);
        $report = $this->reconcileOrNull($this->repo->ledgerGifts($month->start, $month->end), $month, $this->fundNames());
        $rows = $this->repo->attendanceBetween($month->start, $month->end);
        $counts = [];
        foreach ($rows as $row) {
            $counts[] = $row['count'];
        }
        $attendance = AttendanceStats::summarize($counts);
        $waiting = [];
        foreach ($this->repo->people() as $person) {
            if (FollowUp::awaiting($person, $now->format('Y-m-d'))) {
                $waiting[] = $person;
            }
        }
        $this->render('dashboard', 'Dashboard', $user, 'dashboard', [
            'monthLabel' => $this->rangeLabel($month),
            'report' => $report,
            'attendance' => $attendance,
            'recent' => $this->repo->recentAttendance(8),
            'waiting' => $waiting,
            'unread' => $this->repo->unhandledCounts(),
        ]);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function inbox(array $user): void
    {
        $this->render('inbox', 'Inbox', $user, 'inbox', [
            'contacts' => $this->repo->contacts(),
            'prayers' => $this->repo->prayers(),
        ]);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function markInbox(array $user): void
    {
        Csrf::verify(Http::input('csrf'));
        $type = Http::input('type');
        $id = $this->positiveId(Http::input('id'));
        if ($id === null || !in_array($type, ['contact', 'prayer'], true)) {
            $this->missing($user);
            return;
        }
        $handled = Http::input('handled') === '1';
        $this->repo->setHandled(
            $type,
            $id,
            $handled ? $user['id'] : null,
            $handled ? Clock::now()->format('Y-m-d H:i:s') : null,
        );
        Flash::set($handled ? 'Marked handled.' : 'Reopened.');
        redirect_to('/admin/inbox');
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function people(array $user, string $method, ?int $id): void
    {
        if ($id === null && $method === 'GET') {
            $today = Clock::now()->format('Y-m-d');
            $people = $this->repo->people();
            foreach ($people as &$person) {
                $person['awaiting'] = FollowUp::awaiting($person, $today);
            }
            unset($person);
            $this->render('people', 'People', $user, 'people', [
                'mode' => 'list',
                'people' => $people,
                'form' => [],
                'errors' => [],
            ]);
            return;
        }
        if ($id === null) {
            $this->missing($user);
            return;
        }
        $existing = null;
        if ($id !== null && $id > 0) {
            $existing = $this->repo->person($id);
            if ($existing === null) {
                $this->missing($user);
                return;
            }
        }
        $form = $existing ?? [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'kind' => 'visitor',
            'follow_up_status' => 'new',
            'follow_up_on' => '',
            'notes' => '',
        ];
        $errors = [];
        if ($method === 'POST') {
            Csrf::verify(Http::input('csrf'));
            $posted = $this->posted(['first_name', 'last_name', 'email', 'phone', 'kind', 'follow_up_status', 'follow_up_on', 'notes']);
            $checked = Rules::person($posted);
            $errors = $checked['errors'];
            $form = array_merge($form, $posted);
            if ($errors === []) {
                $saved = $this->repo->savePerson($checked['data'], $id === 0 ? null : $id, Clock::now()->format('Y-m-d H:i:s'));
                Flash::set('Person saved.');
                redirect_to('/admin/people/' . $saved);
            }
        }
        $this->render('people', $id === 0 ? 'New person' : 'Person', $user, 'people', [
            'mode' => 'form',
            'personId' => $id,
            'form' => $form,
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function giving(array $user, string $method, ?int $id): void
    {
        if ($id === null && $method === 'GET') {
            $resolved = $this->rangeFromQuery();
            $report = null;
            $gifts = [];
            if ($resolved['range'] instanceof DateRange) {
                $gifts = $this->repo->ledgerGifts($resolved['range']->start, $resolved['range']->end);
                $report = $this->reconcileOrNull($gifts, $resolved['range'], $this->fundNames());
            }
            $this->render('giving', 'Giving', $user, 'giving', [
                'mode' => 'list',
                'which' => $resolved['which'],
                'from' => Http::query('from'),
                'to' => Http::query('to'),
                'rangeError' => $resolved['error'],
                'rangeLabel' => $resolved['range'] instanceof DateRange ? $this->rangeLabel($resolved['range']) : '',
                'report' => $report,
                'gifts' => $gifts,
                'isAdmin' => $user['role'] === 'admin',
            ]);
            return;
        }
        if ($id === null) {
            $this->missing($user);
            return;
        }
        $existing = null;
        if ($id !== null && $id > 0) {
            $existing = $this->repo->gift($id);
            if ($existing === null) {
                $this->missing($user);
                return;
            }
        }
        $form = [
            'amount' => $existing === null ? '' : Money::toInput($existing['cents']),
            'date' => $existing['date'] ?? Clock::now()->format('Y-m-d'),
            'method' => $existing['method'] ?? 'cash',
            'reference' => $existing['reference'] ?? '',
            'fund_id' => isset($existing['fund_id']) ? (string) $existing['fund_id'] : '',
            'person_id' => isset($existing['donor_id']) && $existing['donor_id'] !== null ? (string) $existing['donor_id'] : '',
            'note' => $existing['note'] ?? '',
            'goods_or_services' => $existing['goods_or_services'] ?? '',
        ];
        $errors = [];
        if ($method === 'POST') {
            Csrf::verify(Http::input('csrf'));
            $form = $this->posted(['amount', 'date', 'method', 'reference', 'fund_id', 'person_id', 'note', 'goods_or_services']);
            $checked = Rules::gift($form, $this->repo->fundIds(false), $this->repo->personIds());
            $errors = $checked['errors'];
            if ($errors === []) {
                $saved = $this->repo->saveGift(
                    $checked['data'],
                    $id === 0 ? null : $id,
                    $user['id'],
                    Clock::now()->format('Y-m-d H:i:s'),
                );
                Flash::set('Gift saved.');
                redirect_to('/admin/giving/' . $saved);
            }
        }
        $this->render('giving', $id === 0 ? 'Record a gift' : 'Gift', $user, 'giving', [
            'mode' => 'form',
            'giftId' => $id,
            'form' => $form,
            'errors' => $errors,
            'funds' => $this->repo->funds(false),
            'people' => $this->repo->people(),
            'isAdmin' => $user['role'] === 'admin',
        ], $errors === [] ? 200 : 422);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function deleteGift(array $user, string $method, int $id): void
    {
        if ($user['role'] !== 'admin') {
            $this->forbid($user);
            return;
        }
        $gift = $this->repo->gift($id);
        if ($gift === null) {
            $this->missing($user);
            return;
        }
        if ($method === 'POST') {
            Csrf::verify(Http::input('csrf'));
            if (Http::input('confirm') !== 'yes') {
                Flash::set('The gift was kept.', 'error');
                redirect_to('/admin/giving/' . $id);
            }
            $this->repo->deleteGift($id);
            Flash::set('Gift deleted.');
            redirect_to('/admin/giving');
        }
        $this->render('gift-delete', 'Delete gift', $user, 'giving', ['gift' => $gift]);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function statement(array $user): void
    {
        $people = $this->repo->people();
        $personId = $this->positiveId(Http::query('person'));
        $yearRaw = Http::query('year');
        $year = preg_match('/^\d{4}$/', $yearRaw) === 1 ? (int) $yearRaw : (int) Clock::now()->format('Y');
        $person = $personId === null ? null : $this->repo->person($personId);
        $statement = null;
        $rangeLabel = '';
        $error = null;
        if ($personId !== null && $person === null) {
            $error = 'That person is not in the directory.';
        } elseif ($person !== null) {
            try {
                $range = Periods::year($year);
                $gifts = $this->repo->ledgerGifts($range->start, $range->end);
                $statement = Statements::forDonor($gifts, (int) $person['id'], $range);
                $rangeLabel = $this->rangeLabel($range);
            } catch (\InvalidArgumentException) {
                $error = 'Enter a real year.';
            }
        }
        $this->render('statement', 'Giving statement', $user, 'giving', [
            'people' => $people,
            'person' => $person,
            'year' => (string) $year,
            'statement' => $statement,
            'rangeLabel' => $rangeLabel,
            'error' => $error,
            'ein' => Statements::statementEin((string) $this->config->get('statement.ein', '')),
            'extra' => trim((string) $this->config->get('statement.extra', '')),
        ]);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function attendance(array $user, string $method, ?int $id): void
    {
        if ($id === null && $method === 'GET') {
            $resolved = $this->rangeFromQuery();
            $rows = [];
            $summary = null;
            $weeks = [];
            $months = [];
            if ($resolved['range'] instanceof DateRange) {
                $rows = $this->repo->attendanceBetween($resolved['range']->start, $resolved['range']->end);
                $counts = [];
                $statRows = [];
                foreach ($rows as $row) {
                    $counts[] = $row['count'];
                    $statRows[] = ['date' => $row['date'], 'count' => $row['count']];
                }
                $summary = AttendanceStats::summarize($counts);
                $weeks = AttendanceStats::byWeek($statRows, $resolved['range']);
                $months = AttendanceStats::byMonth($statRows, $resolved['range']);
            }
            $this->render('attendance', 'Attendance', $user, 'attendance', [
                'mode' => 'list',
                'which' => $resolved['which'],
                'from' => Http::query('from'),
                'to' => Http::query('to'),
                'rangeError' => $resolved['error'],
                'rangeLabel' => $resolved['range'] instanceof DateRange ? $this->rangeLabel($resolved['range']) : '',
                'rows' => $rows,
                'summary' => $summary,
                'weeks' => $weeks,
                'months' => $months,
            ]);
            return;
        }
        if ($id === null) {
            $this->missing($user);
            return;
        }
        $existing = null;
        if ($id !== null && $id > 0) {
            $existing = $this->repo->attendance($id);
            if ($existing === null) {
                $this->missing($user);
                return;
            }
        }
        $form = [
            'date' => $existing['date'] ?? Clock::now()->format('Y-m-d'),
            'count' => isset($existing['count']) ? (string) $existing['count'] : '',
            'label' => $existing['label'] ?? '',
            'service_id' => isset($existing['service_id']) && $existing['service_id'] !== null ? (string) $existing['service_id'] : '',
            'notes' => $existing['notes'] ?? '',
        ];
        $errors = [];
        if ($method === 'POST' && Http::input('delete') === '1') {
            Csrf::verify(Http::input('csrf'));
            if ($id === null || $id === 0) {
                $this->missing($user);
                return;
            }
            $this->repo->deleteAttendance($id);
            Flash::set('Attendance record deleted.');
            redirect_to('/admin/attendance');
        }
        if ($method === 'POST') {
            Csrf::verify(Http::input('csrf'));
            $form = $this->posted(['date', 'count', 'label', 'service_id', 'notes']);
            $serviceIds = [];
            foreach ($this->repo->services(false) as $service) {
                $serviceIds[] = $service['id'];
            }
            $checked = Rules::attendance($form, $serviceIds);
            $errors = $checked['errors'];
            if ($errors === []) {
                $saved = $this->repo->saveAttendance(
                    $checked['data'],
                    $id === 0 ? null : $id,
                    $user['id'],
                    Clock::now()->format('Y-m-d H:i:s'),
                );
                Flash::set('Attendance saved.');
                redirect_to('/admin/attendance/' . $saved);
            }
        }
        $this->render('attendance', $id === 0 ? 'Record attendance' : 'Attendance record', $user, 'attendance', [
            'mode' => 'form',
            'recordId' => $id,
            'form' => $form,
            'errors' => $errors,
            'services' => $this->repo->services(false),
        ], $errors === [] ? 200 : 422);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function events(array $user, string $method, ?int $id): void
    {
        if ($id === null && $method === 'GET') {
            $this->render('events', 'Events', $user, 'events', [
                'mode' => 'list',
                'events' => $this->repo->allEvents(),
            ]);
            return;
        }
        if ($id === null) {
            $this->missing($user);
            return;
        }
        $existing = null;
        if ($id !== null && $id > 0) {
            $existing = $this->repo->event($id);
            if ($existing === null) {
                $this->missing($user);
                return;
            }
        }
        $form = [
            'title' => $existing['title'] ?? '',
            'summary' => $existing['summary'] ?? '',
            'starts_at' => isset($existing['starts_at']) ? $this->localInput((string) $existing['starts_at']) : '',
            'ends_at' => isset($existing['ends_at']) && $existing['ends_at'] !== null ? $this->localInput((string) $existing['ends_at']) : '',
            'location' => $existing['location'] ?? '',
            'published' => isset($existing['published']) ? (string) (int) $existing['published'] : '0',
        ];
        $errors = [];
        if ($method === 'POST' && Http::input('delete') === '1') {
            Csrf::verify(Http::input('csrf'));
            if ($id === null || $id === 0) {
                $this->missing($user);
                return;
            }
            $this->repo->deleteEvent($id);
            Flash::set('Event deleted.');
            redirect_to('/admin/events');
        }
        if ($method === 'POST') {
            Csrf::verify(Http::input('csrf'));
            $form = $this->posted(['title', 'summary', 'starts_at', 'ends_at', 'location', 'published']);
            $checked = Rules::event($form);
            $errors = $checked['errors'];
            if ($errors === []) {
                $saved = $this->repo->saveEvent($checked['data'], $id === 0 ? null : $id, Clock::now()->format('Y-m-d H:i:s'));
                Flash::set($checked['data']['published'] ? 'Event published.' : 'Event saved. It is not on the public site.');
                redirect_to('/admin/events/' . $saved);
            }
        }
        $this->render('events', $id === 0 ? 'New event' : 'Event', $user, 'events', [
            'mode' => 'form',
            'eventId' => $id,
            'form' => $form,
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function services(array $user, string $method, ?int $id): void
    {
        if ($id === null && $method === 'GET') {
            $this->render('services', 'Service times', $user, 'services', [
                'mode' => 'list',
                'services' => $this->repo->services(false),
            ]);
            return;
        }
        if ($id === null) {
            $this->missing($user);
            return;
        }
        $existing = null;
        if ($id !== null && $id > 0) {
            $existing = $this->repo->service($id);
            if ($existing === null) {
                $this->missing($user);
                return;
            }
        }
        $form = [
            'name' => $existing['name'] ?? '',
            'weekday' => isset($existing['weekday']) ? (string) $existing['weekday'] : '0',
            'start_time' => isset($existing['start_time']) ? substr((string) $existing['start_time'], 0, 5) : '',
            'end_time' => isset($existing['end_time']) && $existing['end_time'] !== null ? substr((string) $existing['end_time'], 0, 5) : '',
            'note' => $existing['note'] ?? '',
            'sort_order' => isset($existing['sort_order']) ? (string) $existing['sort_order'] : '60',
            'active' => isset($existing['active']) ? (string) (int) $existing['active'] : '1',
            'weekly' => isset($existing['weekly']) ? (string) (int) $existing['weekly'] : '1',
        ];
        $errors = [];
        if ($method === 'POST') {
            Csrf::verify(Http::input('csrf'));
            $form = $this->posted(['name', 'weekday', 'start_time', 'end_time', 'note', 'sort_order', 'active', 'weekly']);
            $checked = Rules::service($form);
            $errors = $checked['errors'];
            if ($errors === []) {
                $saved = $this->repo->saveService($checked['data'], $id === 0 ? null : $id);
                Flash::set('Service time saved.');
                redirect_to('/admin/services/' . $saved);
            }
        }
        $this->render('services', $id === 0 ? 'New service time' : 'Service time', $user, 'services', [
            'mode' => 'form',
            'serviceId' => $id,
            'form' => $form,
            'errors' => $errors,
            'weekdays' => ScheduleFormat::WEEKDAYS,
        ], $errors === [] ? 200 : 422);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function funds(array $user, string $method, ?int $id): void
    {
        if ($user['role'] !== 'admin') {
            $this->forbid($user);
            return;
        }
        if ($id === null && $method === 'GET') {
            $this->render('funds', 'Funds', $user, 'funds', [
                'mode' => 'list',
                'funds' => $this->repo->funds(false),
            ]);
            return;
        }
        if ($id === null) {
            $this->missing($user);
            return;
        }
        $existing = null;
        if ($id !== null && $id > 0) {
            foreach ($this->repo->funds(false) as $fund) {
                if ((int) $fund['id'] === $id) {
                    $existing = $fund;
                    break;
                }
            }
            if ($existing === null) {
                $this->missing($user);
                return;
            }
        }
        $form = [
            'name' => $existing['name'] ?? '',
            'sort_order' => isset($existing['sort_order']) ? (string) $existing['sort_order'] : '60',
            'active' => isset($existing['active']) ? (string) (int) $existing['active'] : '1',
        ];
        $errors = [];
        if ($method === 'POST') {
            Csrf::verify(Http::input('csrf'));
            $form = $this->posted(['name', 'sort_order', 'active']);
            $checked = Rules::fund($form);
            $errors = $checked['errors'];
            if ($errors === []) {
                try {
                    $saved = $this->repo->saveFund($checked['data'], $id === 0 ? null : $id);
                } catch (PDOException $e) {
                    if (!Repository::isDuplicate($e)) {
                        throw $e;
                    }
                    $errors['name'] = 'That fund name is already in use.';
                    $saved = 0;
                }
                if ($errors === []) {
                    Flash::set('Fund saved.');
                    redirect_to('/admin/funds/' . $saved);
                }
            }
        }
        $this->render('funds', $id === 0 ? 'New fund' : 'Fund', $user, 'funds', [
            'mode' => 'form',
            'fundId' => $id,
            'form' => $form,
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function users(array $user, string $method, ?int $id): void
    {
        if ($user['role'] !== 'admin') {
            $this->forbid($user);
            return;
        }
        if ($id === null && $method === 'GET') {
            $this->render('users', 'Users', $user, 'users', [
                'mode' => 'list',
                'users' => $this->repo->users(),
            ]);
            return;
        }
        if ($id === null) {
            $this->missing($user);
            return;
        }
        $existing = null;
        if ($id !== null && $id > 0) {
            $existing = $this->repo->user($id);
            if ($existing === null) {
                $this->missing($user);
                return;
            }
        }
        $form = [
            'name' => $existing['name'] ?? '',
            'email' => $existing['email'] ?? '',
            'role' => $existing['role'] ?? 'staff',
            'password' => '',
        ];
        $errors = [];
        if ($method === 'POST') {
            Csrf::verify(Http::input('csrf'));
            $form = $this->posted(['name', 'email', 'role', 'password']);
            $checked = Rules::user($form, $id === 0);
            $errors = $checked['errors'];
            if ($existing !== null && !Rules::keepsAnAdmin($this->repo->countAdmins(), (string) $existing['role'], (string) $checked['data']['role'])) {
                $errors['role'] = 'The church needs at least one administrator.';
            }
            if ($errors === []) {
                $payload = $checked['data'];
                $payload['password_hash'] = $payload['password'] === null
                    ? null
                    : password_hash((string) $payload['password'], PASSWORD_DEFAULT);
                try {
                    $saved = $this->repo->saveUser($payload, $id === 0 ? null : $id, Clock::now()->format('Y-m-d H:i:s'));
                } catch (PDOException $e) {
                    if (!Repository::isDuplicate($e)) {
                        throw $e;
                    }
                    $errors['email'] = 'That email is already in use.';
                    $saved = 0;
                }
                if ($errors === []) {
                    Flash::set('User saved.');
                    redirect_to('/admin/users/' . $saved);
                }
            }
        }
        $this->render('users', $id === 0 ? 'New user' : 'User', $user, 'users', [
            'mode' => 'form',
            'editId' => $id,
            'form' => $form,
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    /** @param array{id:int, email:string, role:string, name:string}|null $user */
    private function missing(?array $user): void
    {
        $this->render('not-found', 'Not found', $user, '', [], 404);
    }

    /** @param array{id:int, email:string, role:string, name:string} $user */
    private function forbid(array $user): void
    {
        $this->render('forbidden', 'Not available', $user, '', [], 403);
    }

    /**
     * @param array{id:int, email:string, role:string, name:string}|null $user
     * @param array<string, mixed> $data
     */
    private function render(string $template, string $title, ?array $user, string $section, array $data, int $status = 200): void
    {
        View::admin($template, array_merge($data, [
            'title' => $title,
            'user' => $user,
            'section' => $section,
            'csrfField' => Csrf::field(),
            'flash' => Flash::pull(),
            'demo' => $this->repo->isDemo(),
        ]), $status);
    }

    /** @param list<string> $keys
     * @return array<string, string>
     */
    private function posted(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = Http::input($key);
        }

        return $out;
    }

    private function positiveId(string $value): ?int
    {
        if (preg_match('/^[1-9]\d{0,8}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    /** @return array{which:string, range:?DateRange, error:?string} */
    private function rangeFromQuery(): array
    {
        return RangeQuery::resolve(Http::query('which') ?: 'month', Http::query('from'), Http::query('to'), Clock::now());
    }

    private function rangeLabel(DateRange $range): string
    {
        return ScheduleFormat::longDate($range->start) . ' – ' . ScheduleFormat::longDate($range->end);
    }

    /** @return list<string> */
    private function fundNames(): array
    {
        $names = [];
        foreach ($this->repo->funds(false) as $fund) {
            $names[] = (string) $fund['name'];
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $gifts
     * @param list<string> $funds
     * @return array<string, mixed>|null
     */
    private function reconcileOrNull(array $gifts, DateRange $range, array $funds): ?array
    {
        try {
            return GivingLedger::reconcile($gifts, $range, $funds);
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function localInput(string $stored): string
    {
        return str_replace(' ', 'T', substr($stored, 0, 16));
    }
}
