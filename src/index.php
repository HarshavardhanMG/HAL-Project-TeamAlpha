<!DOCTYPE html>
<html>
<head>
    <title>Internal Assessment Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
            padding-top: 50px;
        }
        .login-options {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }
        .login-card {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            width: 200px;
        }
        .login-card h3 {
            margin-top: 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        .btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Internal Assessment Management System</h1>
        <div class="login-options">
            <div class="login-card">
                <h3>Admin</h3>
                <p>System administrator login</p>
                <a href="admin/index.php" class="btn">Admin Login</a>
            </div>
            <div class="login-card">
                <h3>Teacher</h3>
                <p>Faculty member login</p>
                <a href="teacher/index.php" class="btn">Teacher Login</a>
            </div>
            <div class="login-card">
                <h3>Student</h3>
                <p>Student login</p>
                <a href="student/index.php" class="btn">Student Login</a>
            </div>
        </div>
    </div>
</body>
</html> 