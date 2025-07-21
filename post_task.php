<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Debug: Log session data
error_log("post_task.php - Session data: " . print_r($_SESSION, true));

// Check for any output before session_start
if (headers_sent($file, $line)) {
    error_log("Headers sent in post_task.php at $file:$line");
}

// Check if user is logged in and is a requester
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'requester') {
    error_log("Session check failed in post_task.php: user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') . ", user_type=" . (isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'not set'));
    ob_end_clean();
    echo "<p class='error'>Please log in as a requester to access this page.</p>";
    echo "<script>setTimeout(() => { window.location.href='login.php'; }, 2000);</script>";
    exit();
}

include 'db.php';
$requester_id = $_SESSION['user_id'];

// Handle task posting
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $payment = floatval($_POST['payment']);
    
    if (empty($title) || empty($description) || $payment <= 0) {
        $error = "Please fill in all fields with valid data.";
    } else {
        $sql = "INSERT INTO tasks (title, description, payment, requester_id) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Database error in post_task.php: Unable to prepare task insert - " . $conn->error);
            $error = "Database error: Unable to prepare task insert.";
        } else {
            $stmt->bind_param("ssdi", $title, $description, $payment, $requester_id);
            if ($stmt->execute()) {
                echo "<p style='color: green; text-align: center;'>Task posted successfully!</p>";
            } else {
                error_log("Task insert error: " . $conn->error);
                $error = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a Task - MicroTask</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(to bottom, #3498db, #f4f4f4);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            max-width: 600px;
            margin: 20px;
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
        h2 {
            text-align: center;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-weight: 500;
        }
        input[type="text"], input[type="number"], textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        input[type="text"]:focus, input[type="number"]:focus, textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        .btn {
            padding: 12px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }
        .link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        .link a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            input[type="text"], input[type="number"], textarea {
                font-size: 14px;
            }
            .btn {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Post a New Task</h2>
        <?php if (!empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="post_task.php">
            <div class="form-group">
                <label for="title">Task Title</label>
                <input type="text" id="title" name="title" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label for="payment">Payment ($)</label>
                <input type="number" id="payment" name="payment" step="0.01" min="0" required>
            </div>
            <button type="submit" class="btn">Post Task</button>
        </form>
        <p class="link"><a href="logout.php">Logout</a></p>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>
