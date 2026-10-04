<?php
// db.php - Database connection and auto-initialization

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

function getDatabaseConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = '127.0.0.1';
    $dbName = 'learnhub_db';
    $ports = [3307, 3306]; // XAMPP MariaDB port first, fallback to 3306
    $passwords = ['', 'root'];

    $connectedPdo = null;
    $workingPort = null;
    $workingPass = null;

    // First attempt to connect to server without database
    foreach ($ports as $port) {
        foreach ($passwords as $pwd) {
            try {
                $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                $testPdo = new PDO($dsn, 'root', $pwd, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 2
                ]);
                $connectedPdo = $testPdo;
                $workingPort = $port;
                $workingPass = $pwd;
                break 2;
            } catch (Exception $e) {
                // Try next
            }
        }
    }

    if (!$connectedPdo) {
        die("Could not connect to MySQL server. Please ensure MySQL is running in XAMPP.");
    }

    // Ensure database exists
    $connectedPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Connect to database
    $pdo = new PDO("mysql:host={$host};port={$workingPort};dbname={$dbName};charset=utf8mb4", 'root', $workingPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Initialize tables and default seed data
    initializeTables($pdo);

    return $pdo;
}

function initializeTables(PDO $pdo) {
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('student', 'instructor') NOT NULL DEFAULT 'student',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Courses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `courses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `instructor_id` INT NOT NULL,
        `title` VARCHAR(200) NOT NULL,
        `category` VARCHAR(100) NOT NULL,
        `level` VARCHAR(50) NOT NULL DEFAULT 'Beginner',
        `badge_tag` VARCHAR(20) NOT NULL DEFAULT 'COURSE',
        `badge_color` VARCHAR(20) NOT NULL DEFAULT 'web',
        `description` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`instructor_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Lessons table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `lessons` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `course_id` INT NOT NULL,
        `title` VARCHAR(200) NOT NULL,
        `content` MEDIUMTEXT NOT NULL,
        `order_num` INT NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`course_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Enrollments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `enrollments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_enrollment` (`user_id`, `course_id`),
        INDEX (`user_id`),
        INDEX (`course_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Lesson Progress table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `lesson_progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `lesson_id` INT NOT NULL,
        `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_progress` (`user_id`, `lesson_id`),
        INDEX (`user_id`, `course_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Quizzes table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `quizzes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `course_id` INT NOT NULL,
        `title` VARCHAR(200) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`course_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Quiz Questions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `quiz_questions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `quiz_id` INT NOT NULL,
        `question` TEXT NOT NULL,
        `option_a` VARCHAR(255) NOT NULL,
        `option_b` VARCHAR(255) NOT NULL,
        `option_c` VARCHAR(255) NOT NULL,
        `option_d` VARCHAR(255) NOT NULL,
        `correct_option` CHAR(1) NOT NULL,
        INDEX (`quiz_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Quiz Results table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `quiz_results` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `quiz_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `score` INT NOT NULL,
        `total_questions` INT NOT NULL,
        `taken_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`quiz_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Seed sample data if users table is empty
    $userCount = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    if ($userCount == 0) {
        seedInitialData($pdo);
    }
}

function seedInitialData(PDO $pdo) {
    // 1. Seed Users (Instructor and Student)
    $passwordHash = password_hash('password123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Dr. Sarah Chen', 'instructor@learnhub.com', $passwordHash, 'instructor']);
    $instructorId = $pdo->lastInsertId();

    $stmt->execute(['Alex Morgan', 'student@learnhub.com', $passwordHash, 'student']);
    $studentId = $pdo->lastInsertId();

    // 2. Seed Courses
    $stmtCourse = $pdo->prepare("INSERT INTO `courses` (`instructor_id`, `title`, `category`, `level`, `badge_tag`, `badge_color`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Course 1
    $stmtCourse->execute([
        $instructorId,
        'Complete Web Development',
        'Web Development',
        'Beginner',
        'WEB',
        'web',
        'Learn HTML, CSS, JavaScript and build modern, responsive real-world websites.'
    ]);
    $course1Id = $pdo->lastInsertId();

    // Course 2
    $stmtCourse->execute([
        $instructorId,
        'Python Programming',
        'Programming',
        'Beginner',
        'PYTHON',
        'python',
        'Master Python fundamentals, control structures, and build practical programs.'
    ]);
    $course2Id = $pdo->lastInsertId();

    // Course 3
    $stmtCourse->execute([
        $instructorId,
        'SQL & Database Fundamentals',
        'Database',
        'Intermediate',
        'DATABASE',
        'database',
        'Understand relational schemas, SQL queries, indexing, and data modeling.'
    ]);
    $course3Id = $pdo->lastInsertId();

    // 3. Seed Lessons for Course 1 (Web Development)
    $stmtLesson = $pdo->prepare("INSERT INTO `lessons` (`course_id`, `title`, `content`, `order_num`) VALUES (?, ?, ?, ?)");
    $stmtLesson->execute([
        $course1Id,
        'Introduction to HTML & Web Structure',
        "HTML (HyperText Markup Language) is the backbone of the World Wide Web.\n\nKey Concepts:\n- HTML Elements and Tags: Every webpage consists of nested elements like <html>, <head>, <body>, <header>, <main>, and <footer>.\n- Document Flow: Block-level elements (like <div>, <p>, <h1>) take up the full width, while inline elements (like <span>, <a>) flow with text.\n- Semantic Markup: Using meaningful tags like <article>, <section>, and <nav> improves accessibility and search engine indexing.",
        1
    ]);
    $c1Lesson1Id = $pdo->lastInsertId();

    $stmtLesson->execute([
        $course1Id,
        'CSS Fundamentals & Box Model',
        "CSS (Cascading Style Sheets) controls presentation, typography, and visual layout.\n\nKey Concepts:\n- Box Model: Every HTML element is represented as a rectangular box comprising Content, Padding, Border, and Margin.\n- Box-sizing: Setting `box-sizing: border-box` makes width calculations predictable.\n- Selectors & Specificity: ID selectors, Class selectors, and Element selectors carry different weight in the cascade.\n- Flexbox: Modern layout module providing one-dimensional layout capability for flexible, aligned items.",
        2
    ]);
    $c1Lesson2Id = $pdo->lastInsertId();

    $stmtLesson->execute([
        $course1Id,
        'JavaScript Basics & DOM Manipulation',
        "JavaScript provides dynamic behavior and user interactivity.\n\nKey Concepts:\n- Variables: `let` and `const` for block-scoped values.\n- Functions: First-class citizens that can be passed as callbacks.\n- DOM (Document Object Model): Tree structure representing the HTML document. Methods like `document.querySelector()` and `addEventListener()` let you respond to clicks, inputs, and events.",
        3
    ]);

    $stmtLesson->execute([
        $course1Id,
        'Building a Responsive Interface',
        "Responsive web design allows websites to adjust cleanly across mobile, tablet, and desktop devices.\n\nKey Concepts:\n- Viewport meta tag: `<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">`.\n- Media Queries: `@media (max-width: 768px)` for conditional styling.\n- Fluid grids and CSS Grid layouts for multi-device responsiveness.",
        4
    ]);

    // Seed Lessons for Course 2 (Python)
    $stmtLesson->execute([
        $course2Id,
        'Python Syntax and Data Types',
        "Python is renowned for clear, readable syntax and indentation-based structure.\n\nCore Data Types:\n- Integers and Floats for numerical computation.\n- Strings with rich formatting support (`f\"{variable}\"`).\n- Lists: ordered, mutable collections `[1, 2, 3]`.\n- Dictionaries: key-value stores `{'name': 'Alex'}`.",
        1
    ]);
    $stmtLesson->execute([
        $course2Id,
        'Control Flow: Conditionals and Loops',
        "Direct program execution with `if`, `elif`, and `else` branches.\n\nIteration:\n- `for item in items:` loop over sequences.\n- `while condition:` loop until condition evaluates to false.\n- Comprehensions for concise list creation.",
        2
    ]);
    $stmtLesson->execute([
        $course2Id,
        'Functions, Modules, and Scope',
        "Functions encapsulate reusable logic with the `def` keyword.\n\nKey Concepts:\n- Arguments, default parameters, and keyword arguments.\n- Scope: Local vs Global variables.\n- Importing built-in standard libraries like `math`, `os`, and `sys`.",
        3
    ]);

    // Seed Lessons for Course 3 (SQL)
    $stmtLesson->execute([
        $course3Id,
        'Relational Database Concepts',
        "Relational databases organize structured data into tables consisting of rows and columns.\n\nCore Concepts:\n- Primary Keys: Uniquely identify each record in a table.\n- Foreign Keys: Establish relationships between tables.\n- ACID properties: Atomicity, Consistency, Isolation, Durability.",
        1
    ]);
    $stmtLesson->execute([
        $course3Id,
        'SELECT Queries, Filtering, and Sorting',
        "SQL queries retrieve specific columns and records from tables.\n\nCommands:\n- `SELECT column1, column2 FROM table_name;`\n- `WHERE condition` filters matching records.\n- `ORDER BY column ASC/DESC` controls sorting order.\n- `LIMIT` restricts result size.",
        2
    ]);
    $stmtLesson->execute([
        $course3Id,
        'JOINs and Aggregations',
        "Combining data across multiple tables using relations.\n\nKey Concepts:\n- `INNER JOIN`: returns rows that have matching values in both tables.\n- `LEFT JOIN`: returns all rows from left table and matched rows from right.\n- Aggregate functions: `COUNT()`, `SUM()`, `AVG()`, `GROUP BY` and `HAVING`.",
        3
    ]);

    // 4. Seed Quizzes
    $stmtQuiz = $pdo->prepare("INSERT INTO `quizzes` (`course_id`, `title`, `description`) VALUES (?, ?, ?)");
    $stmtQuiz->execute([
        $course1Id,
        'Web Development Quiz',
        'Test your understanding of HTML structure, CSS box model, and JavaScript fundamentals.'
    ]);
    $quiz1Id = $pdo->lastInsertId();

    $stmtQuiz->execute([
        $course2Id,
        'Python Basics Quiz',
        'Test your knowledge of Python syntax, data structures, and functions.'
    ]);
    $quiz2Id = $pdo->lastInsertId();

    $stmtQuiz->execute([
        $course3Id,
        'SQL Fundamentals Quiz',
        'Test your skills in database design, SELECT queries, and JOIN operations.'
    ]);
    $quiz3Id = $pdo->lastInsertId();

    // 5. Seed Questions for Quiz 1
    $stmtQ = $pdo->prepare("INSERT INTO `quiz_questions` (`quiz_id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtQ->execute([
        $quiz1Id,
        'What does HTML stand for?',
        'Hyper Text Markup Language',
        'High Tech Modern Language',
        'Hyperlink and Text Management Language',
        'Home Tool Markup Language',
        'a'
    ]);
    $stmtQ->execute([
        $quiz1Id,
        'Which CSS property controls the space inside an element border?',
        'margin',
        'padding',
        'border-spacing',
        'gap',
        'b'
    ]);
    $stmtQ->execute([
        $quiz1Id,
        'Which JavaScript keyword declares a block-scoped constant variable?',
        'var',
        'let',
        'const',
        'def',
        'c'
    ]);
    $stmtQ->execute([
        $quiz1Id,
        'Which HTML tag is used for the largest heading?',
        '<h6>',
        '<head>',
        '<heading>',
        '<h1>',
        'd'
    ]);

    // Questions for Quiz 2 (Python)
    $stmtQ->execute([
        $quiz2Id,
        'Which character is used for comments in Python?',
        '//',
        '#',
        '/*',
        '--',
        'b'
    ]);
    $stmtQ->execute([
        $quiz2Id,
        'What is the correct syntax to output text in Python?',
        'echo "Hello"',
        'Console.WriteLine("Hello")',
        'print("Hello")',
        'System.out.println("Hello")',
        'c'
    ]);
    $stmtQ->execute([
        $quiz2Id,
        'Which data type is ordered, mutable, and written with square brackets?',
        'Tuple',
        'Dictionary',
        'Set',
        'List',
        'd'
    ]);

    // Questions for Quiz 3 (SQL)
    $stmtQ->execute([
        $quiz3Id,
        'Which SQL clause is used to filter records?',
        'ORDER BY',
        'WHERE',
        'GROUP BY',
        'FILTER',
        'b'
    ]);
    $stmtQ->execute([
        $quiz3Id,
        'Which command removes all rows from a table without logging individual row deletions?',
        'DELETE',
        'REMOVE',
        'TRUNCATE',
        'DROP',
        'c'
    ]);
    $stmtQ->execute([
        $quiz3Id,
        'What type of join returns all rows from the left table and matched rows from the right table?',
        'INNER JOIN',
        'LEFT JOIN',
        'RIGHT JOIN',
        'FULL JOIN',
        'b'
    ]);

    // 6. Seed enrollment and partial progress for sample student
    $stmtEnroll = $pdo->prepare("INSERT INTO `enrollments` (`user_id`, `course_id`) VALUES (?, ?)");
    $stmtEnroll->execute([$studentId, $course1Id]);
    $stmtEnroll->execute([$studentId, $course3Id]);

    // Student completed lesson 1 & 2 of Course 1
    $stmtProg = $pdo->prepare("INSERT INTO `lesson_progress` (`user_id`, `course_id`, `lesson_id`) VALUES (?, ?, ?)");
    $stmtProg->execute([$studentId, $course1Id, $c1Lesson1Id]);
    $stmtProg->execute([$studentId, $course1Id, $c1Lesson2Id]);

    // Student took Web Dev Quiz with score 3/4
    $stmtResult = $pdo->prepare("INSERT INTO `quiz_results` (`user_id`, `quiz_id`, `course_id`, `score`, `total_questions`) VALUES (?, ?, ?, ?, ?)");
    $stmtResult->execute([$studentId, $quiz1Id, $course1Id, 3, 4]);
}

// Helper auth functions
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role']
    ];
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['flash_error'] = 'Please log in to continue.';
        header('Location: login.php');
        exit;
    }
}

function requireInstructor() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'instructor') {
        $_SESSION['flash_error'] = 'Access denied. Instructor privileges required.';
        header('Location: index.php');
        exit;
    }
}
