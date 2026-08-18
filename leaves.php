<?php

/**
 * leaves.php
 * GET /leaves.php?employee_id={id}
 *
 * Returns all leave requests for a given employee.
 * Status codes: 200, 400, 404, 500
 */

require_once __DIR__ . '/config.php';

// ── CORS & content-type headers ──────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Only allow GET ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

// ── Validate `employee_id` query parameter ────────────────────
$rawId = $_GET['employee_id'] ?? '';

if ($rawId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameter: employee_id']);
    exit;
}

$employeeId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($employeeId === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter employee_id must be a positive integer.']);
    exit;
}

// ── Query the database ────────────────────────────────────────
try {
    $pdo = getDB();

    // First check the employee exists
    $empStmt = $pdo->prepare('SELECT id, name FROM employees WHERE id = :id');
    $empStmt->execute([':id' => $employeeId]);
    $employee = $empStmt->fetch();

    if ($employee === false) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Employee with id {$employeeId} not found."]);
        exit;
    }

    // Fetch all leave requests for this employee, newest first
    $leaveStmt = $pdo->prepare(
        'SELECT id, employee_id, leave_type, start_date, end_date, status, created_at
         FROM leave_requests
         WHERE employee_id = :employee_id
         ORDER BY created_at DESC'
    );
    $leaveStmt->execute([':employee_id' => $employeeId]);
    $leaves = $leaveStmt->fetchAll(); // returns [] when no rows

    // Cast integer fields
    foreach ($leaves as &$leave) {
        $leave['id']          = (int) $leave['id'];
        $leave['employee_id'] = (int) $leave['employee_id'];
    }
    unset($leave); // break the reference

    http_response_code(200);
    echo json_encode([
        'success'      => true,
        'employee_id'  => (int) $employee['id'],
        'employee_name'=> $employee['name'],
        'total'        => count($leaves),
        'leaves'       => $leaves,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}
