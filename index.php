<?php
require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
$currentUser = getCurrentUser();

$pageTitle = 'Online Learning Platform';

// Fetch popular courses with lesson counts
$stmt = $pdo->query("
    SELECT c.*, COUNT(l.id) AS lesson_count 
    FROM courses c 
    LEFT JOIN lessons l ON c.id = l.course_id 
    GROUP BY c.id 
    ORDER BY c.id ASC 
    LIMIT 3
");
$popularCourses = $stmt->fetchAll();

// Fetch progress for logged in student
$activeProgress = null;
if ($currentUser && $currentUser['role'] === 'student') {
    $stmtProg = $pdo->prepare("
        SELECT c.id AS course_id, c.title, c.category, c.badge_tag,
               (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS total_lessons,
               (SELECT COUNT(*) FROM lesson_progress WHERE user_id = ? AND course_id = c.id) AS completed_lessons
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
        LIMIT 1
    ");
    $stmtProg->execute([$currentUser['id'], $currentUser['id']]);
    $activeProgress = $stmtProg->fetch();
    if ($activeProgress && $activeProgress['total_lessons'] > 0) {
        $activeProgress['percent'] = round(($activeProgress['completed_lessons'] / $activeProgress['total_lessons']) * 100);
    } else {
        $activeProgress['percent'] = 0;
    }
}

require_once __DIR__ . '/header.php';
?>

<!-- Hero Section -->
<section class="hero" style="margin-top: -25px;">
    <div class="container hero-content">
        <div class="hero-text">
            <p class="small-heading">ONLINE LEARNING PLATFORM</p>
            <h1>
                Learn new skills.<br>
                Build your future.
            </h1>
            <p>
                Learn from expert instructors, track your progress,
                and test your knowledge through interactive quizzes.
            </p>
            <div class="hero-buttons">
                <a href="courses.php" class="primary-btn">
                    Explore Courses
                </a>
                <a href="progress.php" class="secondary-btn">
                    View Progress
                </a>
            </div>
        </div>

        <div class="hero-card">
            <div class="play-icon">&#9654;</div>
            <h3>Learn at your own pace</h3>
            <p>
                Access courses anytime, track your completed lessons, and pick up right where you left off.
            </p>
        </div>
    </div>
</section>

<!-- Courses Section -->
<section class="section" id="courses">
    <div class="container">
        <div class="section-heading">
            <div>
                <p class="small-heading">EXPLORE</p>
                <h2>Popular Courses</h2>
            </div>
            <a href="courses.php" class="view-all">View All &rarr;</a>
        </div>

        <div class="course-grid">
            <?php foreach ($popularCourses as $course): ?>
                <?php 
                    $badgeClass = htmlspecialchars($course['badge_color'] ?: 'web');
                ?>
                <div class="course-card">
                    <div class="course-image <?= $badgeClass ?>">
                        <?= htmlspecialchars($course['badge_tag']) ?>
                    </div>
                    <div class="course-content">
                        <span class="category"><?= htmlspecialchars($course['category']) ?></span>
                        <h3><?= htmlspecialchars($course['title']) ?></h3>
                        <p><?= htmlspecialchars($course['description']) ?></p>
                        <div class="course-info">
                            <span><?= (int)$course['lesson_count'] ?> Lessons</span>
                            <span><?= htmlspecialchars($course['level']) ?></span>
                        </div>
                        <a href="course_view.php?id=<?= $course['id'] ?>" class="course-btn">
                            View Course
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Progress Section -->
<section class="progress-section" id="progress">
    <div class="container">
        <div class="section-heading">
            <div>
                <p class="small-heading">STUDENT DASHBOARD</p>
                <h2>Continue Learning</h2>
            </div>
        </div>

        <?php if ($activeProgress): ?>
            <div class="progress-card">
                <div class="progress-course">
                    <div class="course-icon"><?= htmlspecialchars($activeProgress['badge_tag'] ?: 'CR') ?></div>
                    <div>
                        <span><?= htmlspecialchars($activeProgress['category']) ?></span>
                        <h3><?= htmlspecialchars($activeProgress['title']) ?></h3>
                    </div>
                </div>

                <div class="progress-details">
                    <div class="progress-text">
                        <span>Progress</span>
                        <strong><?= $activeProgress['percent'] ?>%</strong>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $activeProgress['percent'] ?>%;"></div>
                    </div>
                    <p><?= $activeProgress['completed_lessons'] ?> of <?= $activeProgress['total_lessons'] ?> lessons completed</p>
                </div>

                <a href="course_view.php?id=<?= $activeProgress['course_id'] ?>" class="continue-btn">
                    Continue &rarr;
                </a>
            </div>
        <?php else: ?>
            <div class="progress-card">
                <div class="progress-course">
                    <div class="course-icon">WEB</div>
                    <div>
                        <span>Web Development</span>
                        <h3>Complete Web Development</h3>
                    </div>
                </div>

                <div class="progress-details">
                    <div class="progress-text">
                        <span>Progress</span>
                        <strong>50%</strong>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 50%;"></div>
                    </div>
                    <p>2 of 4 lessons completed</p>
                </div>

                <?php if ($currentUser): ?>
                    <a href="courses.php" class="continue-btn">Browse Courses &rarr;</a>
                <?php else: ?>
                    <a href="login.php" class="continue-btn">Sign In to Resume &rarr;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Quiz Section -->
<section class="section" id="quizzes">
    <div class="container">
        <div class="quiz-box">
            <div>
                <p class="small-heading">TEST YOUR KNOWLEDGE</p>
                <h2>Ready for a quiz?</h2>
                <p>
                    Complete quizzes after each course to check your understanding and track your performance.
                </p>
            </div>
            <a href="quizzes.php" class="primary-btn">
                Take a Quiz
            </a>
        </div>
    </div>
</section>

<!-- Instructor Section -->
<section class="instructor-section" id="instructor">
    <div class="container instructor-content">
        <div>
            <p class="small-heading" style="color: #60a5fa;">FOR INSTRUCTORS</p>
            <h2>Share your knowledge</h2>
            <p>
                Create courses, upload lessons, add quizzes and help students learn new skills.
            </p>
        </div>
        <a href="instructor_dashboard.php" class="secondary-btn">
            <?= ($currentUser && $currentUser['role'] === 'instructor') ? 'Open Instructor Portal' : 'Instructor Portal' ?>
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
