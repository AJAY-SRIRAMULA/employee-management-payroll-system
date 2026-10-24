<?php
session_start();
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Only allow report types that this application can support from its current tables.
$allowedReports = ['employees', 'payroll', 'attendance', 'leaves'];
$reportType = $_GET['report'] ?? 'payroll';
if (!in_array($reportType, $allowedReports, true)) {
    $reportType = 'payroll';
}

$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$employeeId = trim($_GET['employee_id'] ?? '');
$departmentId = trim($_GET['department_id'] ?? '');
$status = trim($_GET['status'] ?? '');
$errorMessage = '';
$rows = [];

function validReportDate($value)
{
    if ($value === '') {
        return true;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function runReportQuery($conn, $sql, $types, $params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    if ($types !== '') {
        $bindValues = [$types];
        foreach ($params as $key => &$value) {
            $bindValues[] = &$value;
        }
        if (!call_user_func_array([$stmt, 'bind_param'], $bindValues)) {
            $stmt->close();
            return false;
        }
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $result = $stmt->get_result();
    $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : false;
    $stmt->close();
    return $data;
}

function csvSafeValue($value)
{
    $value = (string) $value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
}

// Load filter options from the existing database tables.
$employees = $conn->query("SELECT id, employee_code, full_name FROM employees ORDER BY full_name ASC");
$departments = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name ASC");
if (!$employees || !$departments) {
    $errorMessage = 'The report filters could not be loaded. Please try again later.';
}
$employeeOptions = $employees ? $employees->fetch_all(MYSQLI_ASSOC) : [];
$departmentOptions = $departments ? $departments->fetch_all(MYSQLI_ASSOC) : [];

if (!validReportDate($startDate) || !validReportDate($endDate)) {
    $errorMessage = 'Please enter valid dates.';
} elseif ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    $errorMessage = 'The start date cannot be after the end date.';
}

if ($employeeId !== '' && (!ctype_digit($employeeId) || (int) $employeeId < 1)) {
    $errorMessage = 'Please select a valid employee.';
} elseif ($employeeId !== '') {
    $checkEmployee = $conn->prepare('SELECT id FROM employees WHERE id = ?');
    if (!$checkEmployee) {
        $errorMessage = 'The employee filter could not be validated.';
    } else {
        $employeeNumber = (int) $employeeId;
        $checkEmployee->bind_param('i', $employeeNumber);
        $checkEmployee->execute();
        $employeeExists = $checkEmployee->get_result()->num_rows > 0;
        $checkEmployee->close();
        if (!$employeeExists) {
            $errorMessage = 'The selected employee does not exist.';
        }
    }
}

if ($departmentId !== '' && (!ctype_digit($departmentId) || (int) $departmentId < 1)) {
    $errorMessage = 'Please select a valid department.';
} elseif ($departmentId !== '') {
    $checkDepartment = $conn->prepare('SELECT id FROM departments WHERE id = ?');
    if (!$checkDepartment) {
        $errorMessage = 'The department filter could not be validated.';
    } else {
        $departmentNumber = (int) $departmentId;
        $checkDepartment->bind_param('i', $departmentNumber);
        $checkDepartment->execute();
        $departmentExists = $checkDepartment->get_result()->num_rows > 0;
        $checkDepartment->close();
        if (!$departmentExists) {
            $errorMessage = 'The selected department does not exist.';
        }
    }
}

$validStatuses = [
    'employees' => ['ACTIVE', 'INACTIVE'],
    'payroll' => ['Draft', 'Finalized', 'Paid'],
    'attendance' => ['Present', 'Absent', 'Late', 'Half Day'],
    'leaves' => ['Pending', 'Approved', 'Rejected']
];
if ($status !== '' && !in_array($status, $validStatuses[$reportType], true)) {
    $errorMessage = 'Please select a valid status for this report.';
}

if ($errorMessage === '' && $reportType === 'payroll') {
    // Payroll is stored on the first day of each month, so filter by whole months.
    if ($startDate !== '') {
        $startDate = date('Y-m-01', strtotime($startDate));
    }
    if ($endDate !== '') {
        $endDate = date('Y-m-t', strtotime($endDate));
    }
}

if ($errorMessage === '') {
    $where = [];
    $types = '';
    $params = [];

    if ($reportType === 'employees') {
        $sql = "SELECT employees.id, employees.employee_code, employees.full_name,
                       employees.email, employees.phone, employees.designation,
                       employees.joining_date, employees.status,
                       departments.department_name
                FROM employees
                JOIN departments ON departments.id = employees.department_id";
        if ($status !== '') {
            $where[] = 'employees.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        if ($departmentId !== '') {
            $where[] = 'employees.department_id = ?';
            $types .= 'i';
            $params[] = (int) $departmentId;
        }
        if ($startDate !== '') {
            $where[] = 'employees.joining_date >= ?';
            $types .= 's';
            $params[] = $startDate;
        }
        if ($endDate !== '') {
            $where[] = 'employees.joining_date <= ?';
            $types .= 's';
            $params[] = $endDate;
        }
        $sql .= $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql .= ' ORDER BY employees.full_name ASC';
    } elseif ($reportType === 'payroll') {
        $sql = "SELECT payroll.id, payroll.employee_id, employees.employee_code,
                       employees.full_name, payroll.payroll_month, payroll.basic_salary,
                       payroll.allowances, payroll.overtime_hours, payroll.overtime_pay,
                       payroll.unpaid_deduction, payroll.other_deductions,
                       payroll.net_salary, payroll.status
                FROM payroll
                JOIN employees ON employees.id = payroll.employee_id";
        if ($startDate !== '') {
            $where[] = 'payroll.payroll_month >= ?';
            $types .= 's';
            $params[] = $startDate;
        }
        if ($endDate !== '') {
            $where[] = 'payroll.payroll_month <= ?';
            $types .= 's';
            $params[] = $endDate;
        }
        if ($employeeId !== '') {
            $where[] = 'payroll.employee_id = ?';
            $types .= 'i';
            $params[] = (int) $employeeId;
        }
        if ($status !== '') {
            $where[] = 'payroll.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        $sql .= $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql .= ' ORDER BY payroll.payroll_month DESC, employees.full_name ASC';
    } elseif ($reportType === 'attendance') {
        $sql = "SELECT attendance.id, employees.employee_code, employees.full_name,
                       attendance.attendance_date, attendance.status,
                       attendance.check_in, attendance.check_out,
                       attendance.overtime_hours, attendance.remarks
                FROM attendance
                JOIN employees ON employees.id = attendance.employee_id";
        if ($startDate !== '') {
            $where[] = 'attendance.attendance_date >= ?';
            $types .= 's';
            $params[] = $startDate;
        }
        if ($endDate !== '') {
            $where[] = 'attendance.attendance_date <= ?';
            $types .= 's';
            $params[] = $endDate;
        }
        if ($employeeId !== '') {
            $where[] = 'attendance.employee_id = ?';
            $types .= 'i';
            $params[] = (int) $employeeId;
        }
        if ($status !== '') {
            $where[] = 'attendance.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        $sql .= $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql .= ' ORDER BY attendance.attendance_date DESC, employees.full_name ASC';
    } else {
        $sql = "SELECT leave_requests.id, employees.employee_code, employees.full_name,
                       leave_requests.leave_type, leave_requests.start_date,
                       leave_requests.end_date, leave_requests.reason,
                       leave_requests.status, leave_requests.created_at
                FROM leave_requests
                JOIN employees ON employees.id = leave_requests.employee_id";
        if ($startDate !== '') {
            // Include requests that are still active on or after the selected start date.
            $where[] = 'leave_requests.end_date >= ?';
            $types .= 's';
            $params[] = $startDate;
        }
        if ($endDate !== '') {
            // Include requests that began on or before the selected end date.
            $where[] = 'leave_requests.start_date <= ?';
            $types .= 's';
            $params[] = $endDate;
        }
        if ($employeeId !== '') {
            $where[] = 'leave_requests.employee_id = ?';
            $types .= 'i';
            $params[] = (int) $employeeId;
        }
        if ($status !== '') {
            $where[] = 'leave_requests.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        $sql .= $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql .= ' ORDER BY leave_requests.start_date DESC, employees.full_name ASC';
    }

    $queryResult = runReportQuery($conn, $sql, $types, $params);
    if ($queryResult === false) {
        $errorMessage = 'The report could not be generated. Please check the filters and try again.';
    } else {
        $rows = $queryResult;
    }
}

// Export the currently filtered report. No additional data is queried for export.
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $errorMessage === '') {
    $filename = 'employee-payroll-' . $reportType . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    if ($reportType === 'employees') {
        fputcsv($output, ['Employee Code', 'Employee Name', 'Email', 'Phone', 'Department', 'Designation', 'Joining Date', 'Status']);
        foreach ($rows as $row) {
            fputcsv($output, array_map('csvSafeValue', [$row['employee_code'], $row['full_name'], $row['email'], $row['phone'], $row['department_name'], $row['designation'], $row['joining_date'], $row['status']]));
        }
    } elseif ($reportType === 'payroll') {
        fputcsv($output, ['Employee Code', 'Employee Name', 'Payroll Month', 'Basic Salary', 'Allowances', 'Overtime Hours', 'Overtime Pay', 'Unpaid Deduction', 'Other Deductions', 'Net Salary', 'Status']);
        foreach ($rows as $row) {
            fputcsv($output, array_map('csvSafeValue', [$row['employee_code'], $row['full_name'], $row['payroll_month'], $row['basic_salary'], $row['allowances'], $row['overtime_hours'], $row['overtime_pay'], $row['unpaid_deduction'], $row['other_deductions'], $row['net_salary'], $row['status']]));
        }
    } elseif ($reportType === 'attendance') {
        fputcsv($output, ['Employee Code', 'Employee Name', 'Attendance Date', 'Status', 'Check In', 'Check Out', 'Overtime Hours', 'Remarks']);
        foreach ($rows as $row) {
            fputcsv($output, array_map('csvSafeValue', [$row['employee_code'], $row['full_name'], $row['attendance_date'], $row['status'], $row['check_in'], $row['check_out'], $row['overtime_hours'], $row['remarks']]));
        }
    } else {
        fputcsv($output, ['Employee Code', 'Employee Name', 'Leave Type', 'Start Date', 'End Date', 'Reason', 'Status', 'Submitted At']);
        foreach ($rows as $row) {
            fputcsv($output, array_map('csvSafeValue', [$row['employee_code'], $row['full_name'], $row['leave_type'], $row['start_date'], $row['end_date'], $row['reason'], $row['status'], $row['created_at']]));
        }
    }
    fclose($output);
    exit;
}

$summary = [];
$monthlyBreakdown = [];
if ($reportType === 'employees') {
    $summary = ['records' => count($rows), 'active' => 0, 'inactive' => 0];
    foreach ($rows as $row) {
        if ($row['status'] === 'ACTIVE') {
            $summary['active']++;
        } else {
            $summary['inactive']++;
        }
    }
} elseif ($reportType === 'payroll') {
    $summary = ['records' => count($rows), 'gross' => 0.0, 'deductions' => 0.0, 'net' => 0.0, 'paid' => 0];
    foreach ($rows as $row) {
        $gross = (float) $row['basic_salary'] + (float) $row['allowances'] + (float) $row['overtime_pay'];
        $deductions = (float) $row['unpaid_deduction'] + (float) $row['other_deductions'];
        $summary['gross'] += $gross;
        $summary['deductions'] += $deductions;
        $summary['net'] += (float) $row['net_salary'];
        if ($row['status'] === 'Paid') {
            $summary['paid']++;
        }

        $monthKey = date('Y-m', strtotime($row['payroll_month']));
        if (!isset($monthlyBreakdown[$monthKey])) {
            $monthlyBreakdown[$monthKey] = [
                'month' => $monthKey,
                'records' => 0,
                'gross' => 0.0,
                'deductions' => 0.0,
                'net' => 0.0,
                'paid' => 0
            ];
        }
        $monthlyBreakdown[$monthKey]['records']++;
        $monthlyBreakdown[$monthKey]['gross'] += $gross;
        $monthlyBreakdown[$monthKey]['deductions'] += $deductions;
        $monthlyBreakdown[$monthKey]['net'] += (float) $row['net_salary'];
        if ($row['status'] === 'Paid') {
            $monthlyBreakdown[$monthKey]['paid']++;
        }
    }
} elseif ($reportType === 'attendance') {
    $summary = ['records' => count($rows), 'Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half Day' => 0];
    foreach ($rows as $row) {
        if (isset($summary[$row['status']])) {
            $summary[$row['status']]++;
        }
    }
} else {
    $summary = ['records' => count($rows), 'Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
    foreach ($rows as $row) {
        if (isset($summary[$row['status']])) {
            $summary[$row['status']]++;
        }
    }
}

$exportParams = $_GET;
unset($exportParams['export']);
$exportUrl = 'index.php?' . http_build_query(array_merge($exportParams, ['export' => 'csv']));
function reportEscape($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Employee Payroll System</title>
    <link rel="stylesheet" href="../assets/css/reports.css">
</head>
<body>
<div class="reports-shell">
    <header class="reports-header">
        <div>
            <p class="eyebrow">WORKFORCE INTELLIGENCE</p>
            <h1>Reports &amp; Insights</h1>
            <p class="header-copy">Review employee records, attendance, leave activity, and payroll totals using live database data.</p>
        </div>
        <div class="header-actions">
            <a class="button button-quiet" href="../dashboard.php">← Dashboard</a>
            <?php if ($errorMessage === ''): ?>
                <a class="button button-accent" href="<?= reportEscape($exportUrl) ?>">Export CSV ↓</a>
            <?php endif; ?>
        </div>
    </header>

    <nav class="report-tabs" aria-label="Report types">
        <a class="<?= $reportType === 'employees' ? 'active' : '' ?>" href="index.php?report=employees">Employees</a>
        <a class="<?= $reportType === 'payroll' ? 'active' : '' ?>" href="index.php?report=payroll">Payroll</a>
        <a class="<?= $reportType === 'attendance' ? 'active' : '' ?>" href="index.php?report=attendance">Attendance</a>
        <a class="<?= $reportType === 'leaves' ? 'active' : '' ?>" href="index.php?report=leaves">Leave Requests</a>
    </nav>

    <section class="filter-panel">
        <div class="section-heading">
            <div>
                <h2>Build a report</h2>
                <p>Choose the fields you want to review, then apply filters.</p>
            </div>
            <span class="live-label"><span></span> Live records</span>
        </div>
        <form method="GET" action="index.php" class="filter-form">
            <input type="hidden" name="report" value="<?= reportEscape($reportType) ?>">
            <div class="field">
                <label for="start_date"><?= $reportType === 'employees' ? 'Joined from' : ($reportType === 'payroll' ? 'Payroll month from' : 'Date from') ?></label>
                <input type="date" id="start_date" name="start_date" value="<?= reportEscape($startDate) ?>">
            </div>
            <div class="field">
                <label for="end_date"><?= $reportType === 'employees' ? 'Joined through' : ($reportType === 'payroll' ? 'Payroll month through' : 'Date through') ?></label>
                <input type="date" id="end_date" name="end_date" value="<?= reportEscape($endDate) ?>">
            </div>
            <?php if ($reportType !== 'employees'): ?>
                <div class="field">
                    <label for="employee_id">Employee</label>
                    <select id="employee_id" name="employee_id">
                        <option value="">All employees</option>
                        <?php foreach ($employeeOptions as $employee): ?>
                            <option value="<?= (int) $employee['id'] ?>" <?= (string) $employee['id'] === $employeeId ? 'selected' : '' ?>><?= reportEscape($employee['employee_code'] . ' — ' . $employee['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($reportType === 'employees'): ?>
                <div class="field">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id">
                        <option value="">All departments</option>
                        <?php foreach ($departmentOptions as $department): ?>
                            <option value="<?= (int) $department['id'] ?>" <?= (string) $department['id'] === $departmentId ? 'selected' : '' ?>><?= reportEscape($department['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($validStatuses[$reportType] as $optionStatus): ?>
                        <option value="<?= reportEscape($optionStatus) ?>" <?= $status === $optionStatus ? 'selected' : '' ?>><?= reportEscape(ucfirst($optionStatus)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button class="button button-primary" type="submit">Apply filters</button>
                <a class="reset-link" href="index.php?report=<?= reportEscape($reportType) ?>">Reset</a>
            </div>
        </form>
        <?php if ($errorMessage !== ''): ?>
            <p class="message message-error" role="alert"><?= reportEscape($errorMessage) ?></p>
        <?php endif; ?>
    </section>

    <?php if ($errorMessage === ''): ?>
        <section class="summary-grid" aria-label="Report summary">
            <?php if ($reportType === 'employees'): ?>
                <article class="summary-item summary-main"><span>Employees in report</span><strong><?= number_format($summary['records']) ?></strong><small>Matching records</small></article>
                <article class="summary-item"><span>Active</span><strong><?= number_format($summary['active']) ?></strong><small>Active employees</small></article>
                <article class="summary-item"><span>Inactive</span><strong><?= number_format($summary['inactive']) ?></strong><small>Inactive employees</small></article>
            <?php elseif ($reportType === 'payroll'): ?>
                <article class="summary-item summary-main"><span>Net payroll total</span><strong>₹<?= number_format($summary['net'], 2) ?></strong><small><?= number_format($summary['records']) ?> payroll records</small></article>
                <article class="summary-item"><span>Gross earnings</span><strong>₹<?= number_format($summary['gross'], 2) ?></strong><small>Basic + allowances + overtime</small></article>
                <article class="summary-item"><span>Total deductions</span><strong>₹<?= number_format($summary['deductions'], 2) ?></strong><small>Unpaid + other deductions</small></article>
                <article class="summary-item"><span>Marked paid</span><strong><?= number_format($summary['paid']) ?></strong><small>Paid payroll records</small></article>
            <?php elseif ($reportType === 'attendance'): ?>
                <article class="summary-item summary-main"><span>Attendance entries</span><strong><?= number_format($summary['records']) ?></strong><small>Matching records</small></article>
                <article class="summary-item"><span>Present</span><strong><?= number_format($summary['Present']) ?></strong><small>Present entries</small></article>
                <article class="summary-item"><span>Absent</span><strong><?= number_format($summary['Absent']) ?></strong><small>Absent entries</small></article>
                <article class="summary-item"><span>Late / Half day</span><strong><?= number_format($summary['Late'] + $summary['Half Day']) ?></strong><small>Combined entries</small></article>
            <?php else: ?>
                <article class="summary-item summary-main"><span>Leave requests</span><strong><?= number_format($summary['records']) ?></strong><small>Matching requests</small></article>
                <article class="summary-item"><span>Pending</span><strong><?= number_format($summary['Pending']) ?></strong><small>Awaiting a decision</small></article>
                <article class="summary-item"><span>Approved</span><strong><?= number_format($summary['Approved']) ?></strong><small>Approved requests</small></article>
                <article class="summary-item"><span>Rejected</span><strong><?= number_format($summary['Rejected']) ?></strong><small>Rejected requests</small></article>
            <?php endif; ?>
        </section>

        <?php if ($reportType === 'payroll' && $monthlyBreakdown): ?>
            <section class="results-panel monthly-panel">
                <div class="section-heading results-heading">
                    <div>
                        <h2>Monthly payroll summary</h2>
                        <p>Totals grouped by payroll month from the records above.</p>
                    </div>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Payroll month</th><th>Records</th><th>Gross earnings</th><th>Deductions</th><th>Net payroll</th><th>Paid records</th></tr></thead>
                        <tbody>
                        <?php foreach ($monthlyBreakdown as $monthSummary): ?>
                            <tr>
                                <td><strong><?= reportEscape(date('M Y', strtotime($monthSummary['month'] . '-01'))) ?></strong></td>
                                <td><?= number_format($monthSummary['records']) ?></td>
                                <td>₹<?= number_format($monthSummary['gross'], 2) ?></td>
                                <td>₹<?= number_format($monthSummary['deductions'], 2) ?></td>
                                <td class="money-strong">₹<?= number_format($monthSummary['net'], 2) ?></td>
                                <td><?= number_format($monthSummary['paid']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <section class="results-panel">
            <div class="section-heading results-heading">
                <div>
                    <h2><?php
                        echo $reportType === 'employees' ? 'Employee directory' : ($reportType === 'payroll' ? 'Payroll register' : ($reportType === 'attendance' ? 'Attendance register' : 'Leave request register'));
                    ?></h2>
                    <p><?= number_format(count($rows)) ?> <?= count($rows) === 1 ? 'record' : 'records' ?> match the selected filters.</p>
                </div>
                <button type="button" class="button button-quiet print-button" onclick="window.print()">Print report</button>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                    <?php if ($reportType === 'employees'): ?>
                        <tr><th>Employee</th><th>Department</th><th>Contact</th><th>Designation</th><th>Joining date</th><th>Status</th></tr>
                    <?php elseif ($reportType === 'payroll'): ?>
                        <tr><th>Employee</th><th>Payroll month</th><th>Basic salary</th><th>Allowances</th><th>Overtime</th><th>Deductions</th><th>Net salary</th><th>Status</th></tr>
                    <?php elseif ($reportType === 'attendance'): ?>
                        <tr><th>Employee</th><th>Date</th><th>Status</th><th>Check in</th><th>Check out</th><th>Overtime hours</th><th>Remarks</th></tr>
                    <?php else: ?>
                        <tr><th>Employee</th><th>Leave type</th><th>Period</th><th>Reason</th><th>Status</th><th>Submitted</th></tr>
                    <?php endif; ?>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8" class="empty-state"><span class="empty-mark">⌕</span><strong>No matching records</strong><small>Try changing the filters or resetting them to view more data.</small></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php if ($reportType === 'employees'): ?>
                                <tr><td><strong><?= reportEscape($row['full_name']) ?></strong><small class="sub-cell"><?= reportEscape($row['employee_code']) ?></small></td><td><?= reportEscape($row['department_name']) ?></td><td><?= reportEscape($row['email']) ?><small class="sub-cell"><?= reportEscape($row['phone']) ?></small></td><td><?= reportEscape($row['designation']) ?></td><td><?= reportEscape($row['joining_date']) ?></td><td><span class="status-pill <?= $row['status'] === 'ACTIVE' ? 'status-good' : 'status-muted' ?>"><?= reportEscape($row['status']) ?></span></td></tr>
                            <?php elseif ($reportType === 'payroll'): ?>
                                <tr><td><strong><?= reportEscape($row['full_name']) ?></strong><small class="sub-cell"><?= reportEscape($row['employee_code']) ?></small></td><td><?= reportEscape(date('M Y', strtotime($row['payroll_month']))) ?></td><td>₹<?= number_format((float) $row['basic_salary'], 2) ?></td><td>₹<?= number_format((float) $row['allowances'], 2) ?></td><td>₹<?= number_format((float) $row['overtime_pay'], 2) ?><small class="sub-cell"><?= number_format((float) $row['overtime_hours'], 2) ?> hrs</small></td><td>₹<?= number_format((float) $row['unpaid_deduction'] + (float) $row['other_deductions'], 2) ?></td><td class="money-strong">₹<?= number_format((float) $row['net_salary'], 2) ?></td><td><span class="status-pill status-<?= strtolower(reportEscape($row['status'])) ?>"><?= reportEscape($row['status']) ?></span></td></tr>
                            <?php elseif ($reportType === 'attendance'): ?>
                                <tr><td><strong><?= reportEscape($row['full_name']) ?></strong><small class="sub-cell"><?= reportEscape($row['employee_code']) ?></small></td><td><?= reportEscape($row['attendance_date']) ?></td><td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', reportEscape($row['status']))) ?>"><?= reportEscape($row['status']) ?></span></td><td><?= reportEscape($row['check_in'] ?: '—') ?></td><td><?= reportEscape($row['check_out'] ?: '—') ?></td><td><?= number_format((float) $row['overtime_hours'], 2) ?></td><td><?= reportEscape($row['remarks'] ?: '—') ?></td></tr>
                            <?php else: ?>
                                <tr><td><strong><?= reportEscape($row['full_name']) ?></strong><small class="sub-cell"><?= reportEscape($row['employee_code']) ?></small></td><td><?= reportEscape($row['leave_type']) ?></td><td><?= reportEscape($row['start_date']) ?><small class="sub-cell">to <?= reportEscape($row['end_date']) ?></small></td><td class="reason-cell"><?= reportEscape($row['reason']) ?></td><td><span class="status-pill status-<?= strtolower(reportEscape($row['status'])) ?>"><?= reportEscape($row['status']) ?></span></td><td><?= reportEscape($row['created_at']) ?></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="report-footnote">Report generated from the current Employee Management &amp; Payroll System database.</p>
        </section>
    <?php endif; ?>
    <footer>Employee Management &amp; Payroll System <span>•</span> Reports &amp; Insights</footer>
</div>
</body>
</html>
