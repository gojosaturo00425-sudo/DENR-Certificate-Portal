<?php
declare(strict_types=1);

/**
 * Count the request date as day one when it is a working day.
 * Working days here are Monday through Thursday; Friday, Saturday, and Sunday are excluded.
 * @return array{elapsed:int,remaining:int,overdue:int,is_workday:bool}
 */
function certificate_review_clock(string $requestedAt, ?DateTimeImmutable $today = null): array
{
    $portalTimezone = new DateTimeZone('Asia/Manila');
    $start = (new DateTimeImmutable($requestedAt, $portalTimezone))->setTimezone($portalTimezone)->setTime(0, 0);
    $today = ($today ?? new DateTimeImmutable('today', $portalTimezone))->setTimezone($portalTimezone)->setTime(0, 0);
    $elapsed = 0;
    if ($start <= $today) {
        for ($date = $start; $date <= $today; $date = $date->modify('+1 day')) {
            if ((int)$date->format('N') <= 4) {
                $elapsed++;
            }
        }
    }
    $isWorkday = (int)$today->format('N') <= 4;
    return [
        'elapsed' => $elapsed,
        'remaining' => max(0, 5 - $elapsed),
        'overdue' => max(0, $elapsed - 5),
        'is_workday' => $isWorkday,
    ];
}

/** Create at most one daily reminder per pending certificate and reviewer. */
function create_certificate_sla_reminders(PDO $pdo, array $reviewerIds, ?DateTimeImmutable $today = null): int
{
    $today = ($today ?? new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->setTimezone(new DateTimeZone('Asia/Manila'))->setTime(0, 0);
    if ((int)$today->format('N') > 4 || !$reviewerIds) {
        return 0;
    }

    $pending = $pdo->query("SELECT c.certificate_no,c.created_at,e.first_name,e.last_name,COALESCE(c.certificate_type,t.title,'Training Certificate') request_title FROM certificates c JOIN employees e ON e.id=c.employee_id LEFT JOIN trainings t ON t.id=c.training_id WHERE c.status='PENDING'")->fetchAll();
    $alreadySent = $pdo->prepare("SELECT 1 FROM notifications WHERE user_id=? AND type='certificate_sla' AND created_at>=CURDATE() AND created_at<CURDATE()+INTERVAL 1 DAY AND message LIKE ? LIMIT 1");
    $notify = $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?, 'Certificate request review reminder', ?, 'certificate_sla')");
    $created = 0;

    foreach ($pending as $request) {
        $clock = certificate_review_clock($request['created_at'], $today);
        if ($clock['elapsed'] === 0) continue;
        $employeeName = trim($request['first_name'] . ' ' . $request['last_name']);
        if ($clock['overdue'] > 0) {
            $status = 'OVERDUE by ' . $clock['overdue'] . ' working day(s)';
        } elseif ($clock['remaining'] === 0) {
            $status = 'due today (the fifth working day)';
        } else {
            $status = $clock['remaining'] . ' working day(s) remaining';
        }
        $message = $request['certificate_no'] . ' for ' . $employeeName . ' - ' . $request['request_title'] . ' is ' . $status . '. Please review the pending request.';
        foreach ($reviewerIds as $reviewerId) {
            $alreadySent->execute([(int)$reviewerId, '%' . $request['certificate_no'] . '%']);
            if ($alreadySent->fetchColumn()) continue;
            $notify->execute([(int)$reviewerId, $message]);
            $created++;
        }
    }
    return $created;
}
