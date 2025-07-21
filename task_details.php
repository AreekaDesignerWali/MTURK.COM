<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details - MicroTask</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #2c3e50;
        }
        .btn {
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .error, .success {
            text-align: center;
            margin: 10px 0;
        }
        .error {
            color: red;
        }
        .success {
            color: green;
        }
        .form-group {
            margin-bottom: 15px;
        }
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        @media (max-width: 768px) {
            .container {
                margin: 20px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Task Details</h2>
        <?php
        session_start();
        include 'db.php';
        if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'worker') {
            echo "<script>window.location.href='login.php';</script>";
            exit();
        }
        if (!isset($_GET['id'])) {
            echo "<p class='error'>No task ID provided.</p>";
            exit();
        }
        $task_id = $_GET['id'];
        $sql = "SELECT * FROM tasks WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $task = $result->fetch_assoc();
            echo "<h3>" . htmlspecialchars($task['title']) . "</h3>";
            echo "<p>" . htmlspecialchars($task['description']) . "</p>";
            echo "<p><strong>Category:</strong> " . htmlspecialchars($task['category']) . "</p>";
            echo "<p><strong>Payment:</strong> $" . htmlspecialchars($task['payment']) . "</p>";
            echo "<p><strong>Deadline:</strong> " . htmlspecialchars($task['deadline']) . "</p>";
            
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply'])) {
                $worker_id = $_SESSION['user_id'];
                $sql_check = "SELECT * FROM task_assignments WHERE task_id = ? AND worker_id = ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("ii", $task_id, $worker_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($result_check->num_rows == 0) {
                    $sql_assign = "INSERT INTO task_assignments (task_id, worker_id, status) VALUES (?, ?, 'accepted')";
                    $stmt_assign = $conn->prepare($sql_assign);
                    $stmt_assign->bind_param("ii", $task_id, $worker_id);
                    if ($stmt_assign->execute()) {
                        echo "<p class='success'>Task accepted successfully!</p>";
                        echo "<script>setTimeout(() => { window.location.href='dashboard.php'; }, 2000);</script>";
                    } else {
                        echo "<p class='error'>Error: " . $conn->error . "</p>";
                    }
                    $stmt_assign->close();
                } else {
                    echo "<p class='error'>You have already applied for this task.</p>";
                }
                $stmt_check->close();
            }
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_work'])) {
                $worker_id = $_SESSION['user_id'];
                $submission = $_POST['submission'];
                $sql_update = "UPDATE task_assignments SET status = 'submitted', submission = ? WHERE task_id = ? AND worker_id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("sii", $submission, $task_id, $worker_id);
                if ($stmt_update->execute()) {
                    echo "<p class='success'>Work submitted successfully!</p>";
                    echo "<script>setTimeout(() => { window.location.href='dashboard.php'; }, 2000);</script>";
                } else {
                    echo "<p class='error'>Error: " . $conn->error . "</p>";
                }
                $stmt_update->close();
            }
            $sql_check = "SELECT * FROM task_assignments WHERE task_id = ? AND worker_id = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("ii", $task_id, $_SESSION['user_id']);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows == 0) {
                echo "<form method='POST'>";
                echo "<button type='submit' name='apply' class='btn'>Apply for Task</button>";
                echo "</form>";
            } elseif ($result_check->fetch_assoc()['status'] == 'accepted') {
                echo "<form method='POST'>";
                echo "<div class='form-group'>";
                echo "<label for='submission'>Submit Your Work</label>";
                echo "<textarea id='submission' name='submission' rows='5' required></textarea>";
                echo "</div>";
                echo "<button type='submit' name='submit_work' class='btn'>Submit Work</button>";
                echo "</form>";
            } else {
                echo "<p class='success'>Work already submitted.</p>";
            }
            $stmt_check->close();
        } else {
            echo "<p class='error'>Task not found.</p>";
        }
        $stmt->close();
        $conn->close();
        ?>
        <p style="text-align: center;"><a href="task_marketplace.php" class="btn">Back to Marketplace</a></p>
    </div>
</body>
</html>
