<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroTask - Home</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
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
            margin: 10px;
        }
        .btn:hover {
            background-color: #2980b9;
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
        footer {
            text-align: center;
            padding: 20px;
            background-color: #2c3e50;
            color: white;
            position: fixed;
            width: 100%;
            bottom: 0;
        }
        @media (max-width: 768px) {
            .task-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Welcome to MicroTask</h1>
        <p>Earn money by completing small tasks or post tasks to get work done!</p>
    </header>
    <div class="container">
        <h2>Get Started</h2>
        <button class="btn" onclick="window.location.href='register.php?type=worker'">Join as Worker</button>
        <button class="btn" onclick="window.location.href='register.php?type=requester'">Join as Requester</button>
        <button class="btn" onclick="window.location.href='login.php'">Login</button>
        
        <h2>Featured Tasks</h2>
        <div class="task-grid">
            <?php
            include 'db.php';
            $sql = "SELECT * FROM tasks WHERE status = 'open' LIMIT 4";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<div class='task-card'>";
                    echo "<h3>" . htmlspecialchars($row['title']) . "</h3>";
                    echo "<p>" . htmlspecialchars($row['description']) . "</p>";
                    echo "<p><strong>Payment:</strong> $" . htmlspecialchars($row['payment']) . "</p>";
                    echo "<button class='btn' onclick=\"window.location.href='task_details.php?id=" . $row['id'] . "'\">View Task</button>";
                    echo "</div>";
                }
            } else {
                echo "<p>No tasks available.</p>";
            }
            $conn->close();
            ?>
        </div>
    </div>
    <footer>
        <p>&copy; 2025 MicroTask. All rights reserved.</p>
    </footer>
</body>
</html>
