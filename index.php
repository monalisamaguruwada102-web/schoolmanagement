<?php
// ============================================
// API: api/index.php
// Handles all AJAX requests from frontend
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'student_fees_db');

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

    // ---- GET ALL STUDENTS (Accounts view) ----
    case 'get_all_students':
        $conn = getConnection();
        $result = $conn->query("SELECT * FROM students ORDER BY created_at DESC");
        $students = [];
        while ($row = $result->fetch_assoc()) $students[] = $row;
        echo json_encode(['success' => true, 'data' => $students]);
        $conn->close();
        break;

    // ---- GET REGISTRY STUDENTS (Fully paid only) ----
    case 'get_registry_students':
        $conn = getConnection();
        $result = $conn->query("SELECT * FROM registry_approved_students ORDER BY full_name ASC");
        $students = [];
        while ($row = $result->fetch_assoc()) $students[] = $row;
        echo json_encode(['success' => true, 'data' => $students, 'count' => count($students)]);
        $conn->close();
        break;

    // ---- ADD NEW STUDENT ----
    case 'add_student':
        $conn        = getConnection();
        $student_id  = $conn->real_escape_string($input['student_id'] ?? '');
        $full_name   = $conn->real_escape_string($input['full_name'] ?? '');
        $course      = $conn->real_escape_string($input['course'] ?? '');
        $total_fees  = floatval($input['total_fees'] ?? 0);
        $fees_paid   = floatval($input['fees_paid'] ?? 0);
        $enroll_date = $conn->real_escape_string($input['enrollment_date'] ?? date('Y-m-d'));

        if (!$student_id || !$full_name || !$course || $total_fees <= 0) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            break;
        }

        $sql = "INSERT INTO students (student_id, full_name, course, total_fees, fees_paid, enrollment_date)
                VALUES ('$student_id','$full_name','$course',$total_fees,$fees_paid,'$enroll_date')";

        if ($conn->query($sql)) {
            if ($fees_paid > 0) {
                $receipt = 'RCP-' . strtoupper(uniqid());
                $conn->query("INSERT INTO payments (student_id, amount, receipt_number) VALUES ('$student_id', $fees_paid, '$receipt')");
            }
            $conn->query("INSERT INTO audit_log (action, student_id, details, performed_by) VALUES ('ADD_STUDENT','$student_id','Student added with fees $fees_paid/$total_fees','Accounts Dept')");
            echo json_encode(['success' => true, 'message' => 'Student added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
        }
        $conn->close();
        break;

    // ---- UPDATE FEES PAID ----
    case 'update_fees':
        $conn           = getConnection();
        $student_id     = $conn->real_escape_string($input['student_id'] ?? '');
        $payment_amount = floatval($input['payment_amount'] ?? 0);

        if (!$student_id || $payment_amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment data.']);
            break;
        }

        $res     = $conn->query("SELECT total_fees, fees_paid FROM students WHERE student_id='$student_id'");
        $student = $res->fetch_assoc();
        if (!$student) { echo json_encode(['success' => false, 'message' => 'Student not found.']); break; }

        $new_paid = $student['fees_paid'] + $payment_amount;
        if ($new_paid > $student['total_fees']) {
            echo json_encode(['success' => false, 'message' => 'Payment exceeds total fees.']);
            break;
        }

        $conn->query("UPDATE students SET fees_paid=$new_paid WHERE student_id='$student_id'");
        $receipt = 'RCP-' . strtoupper(uniqid());
        $conn->query("INSERT INTO payments (student_id, amount, receipt_number) VALUES ('$student_id', $payment_amount, '$receipt')");
        $conn->query("INSERT INTO audit_log (action, student_id, details, performed_by) VALUES ('UPDATE_PAYMENT','$student_id','Payment of $payment_amount received. Receipt: $receipt','Accounts Dept')");
        echo json_encode(['success' => true, 'message' => "Payment recorded. Receipt: $receipt"]);
        $conn->close();
        break;

    // ---- DELETE STUDENT ----
    case 'delete_student':
        $conn       = getConnection();
        $student_id = $conn->real_escape_string($input['student_id'] ?? '');
        $conn->query("DELETE FROM students WHERE student_id='$student_id'");
        echo json_encode(['success' => true, 'message' => 'Student removed.']);
        $conn->close();
        break;

    // ---- GET PAYMENT HISTORY ----
    case 'get_payments':
        $conn       = getConnection();
        $student_id = $conn->real_escape_string($_GET['student_id'] ?? '');
        $result     = $conn->query("SELECT * FROM payments WHERE student_id='$student_id' ORDER BY payment_date DESC");
        $payments   = [];
        while ($row = $result->fetch_assoc()) $payments[] = $row;
        echo json_encode(['success' => true, 'data' => $payments]);
        $conn->close();
        break;

    // ---- DASHBOARD STATS ----
    case 'get_stats':
        $conn        = getConnection();
        $total       = $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'];
        $paid        = $conn->query("SELECT COUNT(*) as c FROM students WHERE payment_status='paid'")->fetch_assoc()['c'];
        $partial     = $conn->query("SELECT COUNT(*) as c FROM students WHERE payment_status='partial'")->fetch_assoc()['c'];
        $unpaid      = $conn->query("SELECT COUNT(*) as c FROM students WHERE payment_status='unpaid'")->fetch_assoc()['c'];
        $collected   = $conn->query("SELECT SUM(fees_paid) as s FROM students")->fetch_assoc()['s'] ?? 0;
        $outstanding = $conn->query("SELECT SUM(balance) as s FROM students")->fetch_assoc()['s'] ?? 0;
        echo json_encode(['success' => true, 'data' => compact('total','paid','partial','unpaid','collected','outstanding')]);
        $conn->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
?>