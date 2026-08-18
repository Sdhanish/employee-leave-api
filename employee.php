<?php

/**
 * employee.php
 * GET /employee.php?id={id}
 *
 * Returns employee details and leave balances.
 * Status codes: 200, 400, 404, 500
 */

require_once __DIR__ . '/config.php';

// ── CORS & content-type headers ──────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle pre-flight OPTIONS request
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

// ── Validate the `id` query parameter ────────────────────────
$rawId = $_GET['id'] ?? '';

if ($rawId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameter: id']);
    exit;
}

// Cast and range-check: must be a positive integer
$employeeId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($employeeId === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter id must be a positive integer.']);
    exit;
}

// ── Query the database ────────────────────────────────────────
try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        'SELECT id, name, annual_leave_balance, sick_leave_balance, casual_leave_balance
         FROM employees
         WHERE id = :id'
    );
    $stmt->execute([':id' => $employeeId]);

    $employee = $stmt->fetch(); // returns array or false

    if ($employee === false) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Employee with id {$employeeId} not found."]);
        exit;
    }

    // Cast balance fields to integers for clean JSON output
    $employee['id']                   = (int) $employee['id'];
    $employee['annual_leave_balance'] = (int) $employee['annual_leave_balance'];
    $employee['sick_leave_balance']   = (int) $employee['sick_leave_balance'];
    $employee['casual_leave_balance'] = (int) $employee['casual_leave_balance'];

    http_response_code(200);
    echo json_encode([
        'success'  => true,
        'employee' => $employee,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}
