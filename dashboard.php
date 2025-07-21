<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Debug: Log session data
error_log("dashboard.php - Session data: " . print_r($_SESSION, true));

// Check for headers sent
if (headers_sent($file, $line)) {
    error_log("Headers sent in dashboard.php at $file:$line");
}

// Check if user is logged in and is a worker
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'worker') {
    error_log("Session check failed in dashboard.php: user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') . ", user_type=" . (isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'not set'));
    ob_end_clean();
    echo "<p class='error'>Please log in as a worker to access the dashboard.</p>";
    echo "<script>setTimeout(() => { window.location.href='login.php'; }, 2000);</script>";
    exit();
}

include 'db.php';
$worker_id = $_SESSION['user_id'];

// Check if total_earnings column exists
$sql_check_column = "SHOW COLUMNS FROM users LIKE 'total_earnings'";
$result_check = $conn->query($sql_check_column);
$has_total_earnings = $result_check->num_rows > 0;

// Calculate total earnings
if ($has_total_earnings) {
    $sql_earnings = "SELECT total_earnings FROM users WHERE id = ?";
    $stmt_earnings = $conn->prepare($sql_earnings);
    if ($stmt_earnings === false) {
        error_log("Database error in dashboard.php: Unable to prepare earnings query - " . $conn->error);
        ob_end_clean();
        echo "<p class='error'>Database error: Unable to prepare earnings query.</p>";
        exit();
    }
    $stmt_earnings->bind_param("i", $worker_id);
    $stmt_earnings->execute();
    $earnings = $stmt_earnings->get_result()->fetch_assoc()['total_earnings'] ?? 0;
    $stmt_earnings->close();
} else {
    $sql_earnings = "SELECT SUM(t.payment) as total_earnings FROM tasks t JOIN task_assignments ta ON t.id = ta.task_id WHERE ta.worker_id = ? AND ta.status = 'completed'";
    $stmt_earnings = $conn->prepare($sql_earnings);
    if ($stmt_earnings === false) {
        error_log("Database error in dashboard.php: Unable to prepare fallback earnings query - " . $conn->error);
        ob_end_clean();
        echo "<p class='error'>Database error: Unable to prepare fallback earnings query.</p>";
        exit();
    }
    $stmt_earnings->bind_param("i", $worker_id);
    $stmt_earnings->execute();
    $earnings = $stmt_earnings->get_result()->fetch_assoc()['total_earnings'] ?? 0;
    $stmt_earnings->close();
}

// Calculate potential earnings from accepted tasks
$sql_potential = "SELECT SUM(t.payment) as potential_earnings FROM tasks t JOIN task_assignments ta ON t.id = ta.task_id WHERE ta.worker_id = ? AND ta.status = 'assigned'";
$stmt_potential = $conn->prepare($sql_potential);
if ($stmt_potential === false) {
    error_log("Database error in dashboard.php: Unable to prepare potential earnings query - " . $conn->error);
    $potential_earnings = 0;
} else {
    $stmt_potential->bind_param("i", $worker_id);
    $stmt_potential->execute();
    $potential_earnings = $stmt_potential->get_result()->fetch_assoc()['potential_earnings'] ?? 0;
    $stmt_potential->close();
}

// Handle withdrawal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['withdraw'])) {
    $method = $_POST['method'];
    $sql_withdraw = "INSERT INTO withdrawals (worker_id, amount, method, status) VALUES (?, ?, ?, 'pending')";
    $stmt_withdraw = $conn->prepare($sql_withdraw);
    if ($stmt_withdraw === false) {
        error_log("Database error in dashboard.php: Unable to prepare withdrawal query - " . $conn->error);
        echo "<p class='error'>Database error: Unable to prepare withdrawal query.</p>";
    } else {
        $stmt_withdraw->bind_param("ids", $worker_id, $earnings, $method);
        if ($stmt_withdraw->execute()) {
            if ($has_total_earnings) {
                $sql_reset_earnings = "UPDATE users SET total_earnings = 0 WHERE id = ?";
                $stmt_reset = $conn->prepare($sql_reset_earnings);
                $stmt_reset->bind_param("i", $worker_id);
                $stmt_reset->execute();
                $stmt_reset->close();
            }
            echo "<p style='color: green; text-align: center;'>Withdrawal request submitted!</p>";
        } else {
            error_log("Withdrawal error: " . $conn->error);
            echo "<p class='error'>Error: " . $conn->error . "</p>";
        }
        $stmt_withdraw->close();
    }
}

// Handle task rating
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rate_task'])) {
    $task_id = $_POST['task_id'];
    $rating = $_POST['rating'];
    $review = $_POST['review'];
    $sql_rate = "UPDATE task_assignments SET rating = ?, review = ? WHERE task_id = ? AND worker_id = ?";
    $stmt_rate = $conn->prepare($sql_rate);
    if ($stmt_rate === false) {
        error_log("Database error in dashboard.php: Unable to prepare rating query - " . $conn->error);
        echo "<p class='error'>Database error: Unable to prepare rating query.</p>";
    } else {
        $stmt_rate->bind_param("isii", $rating, $review, $task_id, $worker_id);
        if ($stmt_rate->execute()) {
            echo "<p style='color: green; text-align: center;'>Rating submitted!</p>";
        } else {
            error_log("Rating error: " . $conn->error);
            echo "<p class='error'>Error: " . $conn->error . "</p>";
        }
        $stmt_rate->close();
    }
}

// Handle task acceptance
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accept_task'])) {
    $task_id = $_POST['task_id'];
    $sql_accept = "INSERT INTO task_assignments (task_id, worker_id, status) VALUES (?, ?, 'assigned')";
    $stmt_accept = $conn->prepare($sql_accept);
    if ($stmt_accept === false) {
        error_log("Database error in dashboard.php: Unable to prepare task acceptance query - " . $conn->error);
        echo "<p class='error'>Database error: Unable to prepare task acceptance query.</p>";
    } else {
        $stmt_accept->bind_param("ii", $task_id, $worker_id);
        if ($stmt_accept->execute()) {
            echo "<p style='color: green; text-align: center;'>Task accepted successfully!</p>";
        } else {
            error_log("Task acceptance error: " . $conn->error);
            echo "<p class='error'>Error: " . $conn->error . "</p>";
        }
        $stmt_accept->close();
    }
}

// Handle task status update (Done/Not Done)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $task_id = $_POST['task_id'];
    $status = $_POST['status'];
    
    if ($status === 'completed' && $has_total_earnings) {
        // Get task payment
        $sql_payment = "SELECT payment FROM tasks WHERE id = ?";
        $stmt_payment = $conn->prepare($sql_payment);
        $stmt_payment->bind_param("i", $task_id);
        $stmt_payment->execute();
        $payment = $stmt_payment->get_result()->fetch_assoc()['payment'];
        $stmt_payment->close();

        // Update task status
        $sql_update = "UPDATE task_assignments SET status = ? WHERE task_id = ? AND worker_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update === false) {
            error_log("Database error in dashboard.php: Unable to prepare status update query - " . $conn->error);
            echo "<p class='error'>Database error: Unable to prepare status update query.</p>";
        } else {
            $stmt_update->bind_param("sii", $status, $task_id, $worker_id);
            if ($stmt_update->execute()) {
                // Credit payment to worker's earnings
                $sql_credit = "UPDATE users SET total_earnings = total_earnings + ? WHERE id = ?";
                $stmt_credit = $conn->prepare($sql_credit);
                $stmt_credit->bind_param("di", $payment, $worker_id);
                if ($stmt_credit->execute()) {
                    echo "<p style='color: green; text-align: center;'>Task marked as $status! Payment of $$payment credited.</p>";
                } else {
                    error_log("Credit error: " . $conn->error);
                    echo "<p class='error'>Error crediting payment: " . $conn->error . "</p>";
                }
                $stmt_credit->close();
            } else {
                error_log("Status update error: " . $conn->error);
                echo "<p class='error'>Error: " . $conn->error . "</p>";
            }
            $stmt_update->close();
        }
    } else {
        // Update to 'not_done' or 'completed' without crediting if total_earnings column is missing
        $sql_update = "UPDATE task_assignments SET status = ? WHERE task_id = ? AND worker_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update === false) {
            error_log("Database error in dashboard.php: Unable to prepare status update query - " . $conn->error);
            echo "<p class='error'>Database error: Unable to prepare status update query.</p>";
        } else {
            $stmt_update->bind_param("sii", $status, $task_id, $worker_id);
            if ($stmt_update->execute()) {
                echo "<p style='color: green; text-align: center;'>Task marked as $status!</p>";
            } else {
                error_log("Status update error: " . $conn->error);
                echo "<p class='error'>Error: " . $conn->error . "</p>";
            }
            $stmt_update->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Dashboard - MicroTask</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(to bottom, #3498db, #f4f4f4);
            min-height: 100vh;
        }
        .container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            animation: fadeIn 1s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h2, h3 {
            text-align: center;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .btn {
            padding: 10px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin: 5px;
        }
        .btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        .btn-danger {
            background-color: #e74c3c;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #2c3e50;
            color: white;
        }
        .summary {
            margin: 20px 0;
            text-align: center;
            font-size: 18px;
            color: #34495e;
        }
        .form-group {
            margin-bottom: 20px;
        }
        select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 15px;
            font-size: 14px;
        }
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            table {
                font-size: 14px;
            }
            .btn {
                font-size: 12px;
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Worker Dashboard</h2>
        <div class="summary">Total Earnings: $<?php echo number_format($earnings, 2); ?></div>
        <div class="summary">Potential Earnings from Accepted Tasks: $<?php echo number_format($potential_earnings, 2); ?></div>
        <?php if (!$has_total_earnings): ?>
            <p class="error">Note: Earnings are calculated from completed tasks. Add 'total_earnings' column to the 'users' table for better tracking.</p>
        <?php endif; ?>
        <p style="text-align: center;">
            <a href="task_marketplace.php" class="btn">Browse Tasks</a>
            <a href="logout.php" class="btn">Logout</a>
        </p>
        <div class="form-group">
            <form method="POST">
                <label for="method">Withdraw Earnings</label>
                <select id="method" name="method">
                    <option value="paypal">PayPal</option>
                    <option value="bank">Bank Transfer</option>
                </select>
                <button type="submit" name="withdraw" class="btn">Request Withdrawal</button>
            </form>
        </div>
        <h3>All Available Tasks</h3>
        <table>
            <tr>
                <th>Task Title</th>
                <th>Description</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php
            $sql = "SELECT t.id, t.title, t.description, t.payment, ta.status, ta.rating 
                    FROM tasks t 
                    LEFT JOIN task_assignments ta ON t.id = ta.task_id AND ta.worker_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                error_log("Database error in dashboard.php: Unable to prepare task query - " . $conn->error);
                echo "<p class='error'>Database error: Unable to prepare task query.</p>";
                ob_end_flush();
                exit();
            }
            $stmt->bind_param("i", $worker_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                    echo "<td>$" . htmlspecialchars($row['payment']) . "</td>";
                    echo "<td>" . ($row['status'] ? htmlspecialchars($row['status']) : 'Available') . "</td>";
                    echo "<td>";
                    if ($row['status'] == 'completed' && !$row['rating']) {
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='task_id' value='" . $row['id'] . "'>";
                        echo "<select name='rating' required>";
                        echo "<option value='1'>1 Star</option>";
                        echo "<option value='2'>2 Stars</option>";
                        echo "<option value='3'>3 Stars</option>";
                        echo "<option value='4'>4 Stars</option>";
                        echo "<option value='5'>5 Stars</option>";
                        echo "</select>";
                        echo "<textarea name='review' placeholder='Your review' rows='2'></textarea>";
                        echo "<button type='submit' name='rate_task' class='btn'>Rate Task</button>";
                        echo "</form>";
                    } elseif ($row['rating']) {
                        echo "Rated: " . $row['rating'] . " Stars";
                    } elseif ($row['status'] == 'assigned') {
                        echo "<p>Payment: $" . htmlspecialchars($row['payment']) . "</p>";
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='task_id' value='" . $row['id'] . "'>";
                        echo "<select name='status' required>";
                        echo "<option value='completed'>Done</option>";
                        echo "<option value='not_done'>Not Done</option>";
                        echo "</select>";
                        echo "<button type='submit' name='update_status' class='btn'>Update Status</button>";
                        echo "</form>";
                    } elseif (!$row['status']) {
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='task_id' value='" . $row['id'] . "'>";
                        echo "<button type='submit' name='accept_task' class='btn'>Accept Task</button>";
                        echo "</form>";
                    } else {
                        echo "-";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5'>No tasks available.</td></tr>";
            }
            $stmt->close();
            $conn->close();
            ob_end_flush();
            ?>
        </table>
    </div>
</body>
</html>
