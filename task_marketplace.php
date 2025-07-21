<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Marketplace - MicroTask</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #2c3e50;
        }
        .task-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .task-card {
            background-color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .task-card h3 {
            margin: 0 0 10px;
        }
        .btn {
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        @media (max-width: 768px) {
            .task-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Task Marketplace</h2>
        <?php
        session_start();
        include 'db.php';
        if (!isset($_SESSION['user_id'])) {
            echo "<script>window.location.href='login.php';</script>";
            exit();
        }
        ?>
        <p style="text-align: center;">
            <a href="<?php echo $_SESSION['user_type'] == 'requester' ? 'post_task.php' : 'dashboard.php'; ?>" class="btn">Go to <?php echo $_SESSION['user_type'] == 'requester' ? 'Post Task' : 'Dashboard'; ?></a>
            <a href="logout.php" class="btn">Logout</a>
        </p>
        <div class="task-grid">
            <?php
            $sql = "SELECT * FROM tasks WHERE status = 'open'";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<div class='task-card'>";
                    echo "<h3>" . htmlspecialchars($row['title']) . "</h3>";
                    echo "<p>" . htmlspecialchars($row['description']) . "</p>";
                    echo "<p><strong>Category:</strong> " . htmlspecialchars($row['category']) . "</p>";
                    echo "<p><strong>Payment:</strong> $" . htmlspecialchars($row['payment']) . "</p>";
                    echo "<p><strong>Deadline:</strong> " . htmlspecialchars($row['deadline']) . "</p>";
                    if ($_SESSION['user_type'] == 'worker') {
                        echo "<button class='btn' onclick=\"window.location.href='task_details.php?id=" . $row['id'] . "'\">Apply for Task</button>";
                    }
                    echo "</div>";
                }
            } else {
                echo "<p>No tasks available.</p>";
            }
            $conn->close();
            ?>
        </div>
    </div>
</body>
</html>
