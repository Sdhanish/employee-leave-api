<?php

/**
 * apply-leave.php
 * POST /apply-leave.php
 *
 * Applies a new leave request for an employee.
 *
 * Expected JSON body:
 * {
 *   "employee_id": 1,
 *   "leave_type":  "annual",
 *   "start_date":  "2026-08-20",
 *   "end_date":    "2026-08-22"
 * }
 *
 * Status codes: 201, 400, 404, 409, 500
 */

require_once __DIR__ . '/config.php';

// ── CORS & content-type headers ──────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Only allow POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// ────────────────────────────────────────────────────────────
// STEP 1: Parse the JSON request body
// ────────────────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody, true); // assoc array or null

if ($body === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON in request body.']);
    exit;
}

// ────────────────────────────────────────────────────────────
// STEP 2: Validate required fields are present
// ────────────────────────────────────────────────────────────
$required = ['employee_id', 'leave_type', 'start_date', 'end_date'];

foreach ($required as $field) {
    // isset catches missing keys; '' catches empty strings
    if (!isset($body[$field]) || $body[$field] === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
        exit;
    }
}

// ────────────────────────────────────────────────────────────
// STEP 3: Validate individual field values
// ────────────────────────────────────────────────────────────

// 3a. employee_id must be a positive integer
$employeeId = filter_var($body['employee_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($employeeId === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'employee_id must be a positive integer.']);
    exit;
}

// 3b. leave_type must be one of the allowed values
$allowedTypes = ['annual', 'sick', 'casual'];
$leaveType    = strtolower(trim((string) $body['leave_type']));
if (!in_array($leaveType, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'leave_type must be one of: ' . implode(', ', $allowedTypes),
    ]);
    exit;
}

// 3c. Date format validation — must be YYYY-MM-DD
$datePattern = '/^\d{4}-\d{2}-\d{2}$/';

$startDateStr = trim((string) $body['start_date']);
$endDateStr   = trim((string) $body['end_date']);

if (!preg_match($datePattern, $startDateStr)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'start_date must be in YYYY-MM-DD format.']);
    exit;
}

if (!preg_match($datePattern, $endDateStr)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'end_date must be in YYYY-MM-DD format.']);
    exit;
}

// 3d. Parse into DateTime objects (also catches invalid dates like 2026-13-01)
$startDate = DateTime::createFromFormat('Y-m-d', $startDateStr);
$endDate   = DateTime::createFromFormat('Y-m-d', $endDateStr);

// createFromFormat can return false OR a date with warnings for invalid calendar dates
$startErrors = DateTime::getLastErrors();
$endErrors   = DateTime::getLastErrors();

if (
    $startDate === false ||
    !empty($startErrors['warning_count']) ||
    !empty($startErrors['error_count'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'start_date is not a valid calendar date.']);
    exit;
}

if (
    $endDate === false ||
    !empty($endErrors['warning_count']) ||
    !empty($endErrors['error_count'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'end_date is not a valid calendar date.']);
    exit;
}

// 3e. start_date must not be after end_date
if ($startDate > $endDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'start_date cannot be after end_date.']);
    exit;
}

// ────────────────────────────────────────────────────────────
// STEP 4: Calculate requested leave days (inclusive)
// e.g. Aug 20 → Aug 22 = 3 days (20, 21, 22)
// ────────────────────────────────────────────────────────────
$diff        = $startDate->diff($endDate);
$requestedDays = $diff->days + 1; // +1 for inclusive count

// ────────────────────────────────────────────────────────────
// STEP 5 onward: Database operations
// ────────────────────────────────────────────────────────────
try {
    $pdo = getDB();

    // ── STEP 5: Find the employee ─────────────────────────────
    $empStmt = $pdo->prepare(
        'SELECT id, name, annual_leave_balance, sick_leave_balance, casual_leave_balance
         FROM employees
         WHERE id = :id'
    );
    $empStmt->execute([':id' => $employeeId]);
    $employee = $empStmt->fetch();

    if ($employee === false) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Employee with id {$employeeId} not found."]);
        exit;
    }

    // ── STEP 6: Check the correct leave balance ───────────────
    // Map leave type to the right balance column name
    $balanceColumn = $leaveType . '_leave_balance'; // e.g. annual_leave_balance
    $currentBalance = (int) $employee[$balanceColumn];

    if ($requestedDays > $currentBalance) {
        http_response_code(400);
        echo json_encode([
            'success'          => false,
            'message'          => "Insufficient {$leaveType} leave balance.",
            'requested_days'   => $requestedDays,
            'available_balance'=> $currentBalance,
        ]);
        exit;
    }

    // ── STEP 7: Overlap check ─────────────────────────────────
    // A new leave [S, E] overlaps an existing leave [S2, E2] when:
    //   S  <= E2  AND  E  >= S2
    // We exclude 'rejected' leaves from the check.
    $overlapStmt = $pdo->prepare(
        'SELECT id FROM leave_requests
         WHERE employee_id = :employee_id
           AND status      != :rejected
           AND start_date  <= :end_date
           AND end_date    >= :start_date
         LIMIT 1'
    );
    $overlapStmt->execute([
        ':employee_id' => $employeeId,
        ':rejected'    => 'rejected',
        ':start_date'  => $startDateStr,
        ':end_date'    => $endDateStr,
    ]);

    if ($overlapStmt->fetch() !== false) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Leave request overlaps with an existing active leave request.',
        ]);
        exit;
    }

    // ── STEP 8: Transaction — insert leave + deduct balance ───
    // Both must succeed or both roll back.
    $pdo->beginTransaction();

    try {
        // Insert the new leave request (status defaults to 'pending')
        $insertStmt = $pdo->prepare(
            'INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, status)
             VALUES (:employee_id, :leave_type, :start_date, :end_date, :status)'
        );
        $insertStmt->execute([
            ':employee_id' => $employeeId,
            ':leave_type'  => $leaveType,
            ':start_date'  => $startDateStr,
            ':end_date'    => $endDateStr,
            ':status'      => 'pending',
        ]);

        $newLeaveId = (int) $pdo->lastInsertId();

        // Deduct balance: use the column name we resolved above.
        // We cannot parameterise column names in PDO, so we white-list it.
        // $balanceColumn is guaranteed to be one of:
        //   annual_leave_balance | sick_leave_balance | casual_leave_balance
        // because $leaveType was validated against $allowedTypes.
        $updateStmt = $pdo->prepare(
            "UPDATE employees
             SET {$balanceColumn} = {$balanceColumn} - :days
             WHERE id = :id"
        );
        $updateStmt->execute([
            ':days' => $requestedDays,
            ':id'   => $employeeId,
        ]);

        $pdo->commit();

        // Return success response
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Leave request submitted successfully.',
            'data'    => [
                'leave_request_id'   => $newLeaveId,
                'employee_id'        => $employeeId,
                'employee_name'      => $employee['name'],
                'leave_type'         => $leaveType,
                'start_date'         => $startDateStr,
                'end_date'           => $endDateStr,
                'days_requested'     => $requestedDays,
                'status'             => 'pending',
                'remaining_balance'  => $currentBalance - $requestedDays,
            ],
        ]);

    } catch (PDOException $innerEx) {
        // Roll back both operations if anything fails
        $pdo->rollBack();
        throw $innerEx; // re-throw to be caught by the outer catch
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}
