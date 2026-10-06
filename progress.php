<?php
require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
requireLogin();

$currentUser = getCurrentUser();
$pageTitle = 'My Progress';

// Enrolled courses with progress
$stmt = $pdo->prepare("
    SELECT c.id AS course_id, c.title, c.category, c.badge_tag, c.level, e.enrolled_at,
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS total_lessons,
           (SELECT COUNT(*) FROM lesson_progress WHERE user_id = ? AND course_id = c.id) AS completed_lessons
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.user_id = ?
    ORDER BY e.enrolled_at DESC
");
$stmt->execute([$currentUser['id'], $currentUser['id']]);
$enrolledCourses = $stmt->fetchAll();

// Quiz attempts history
$quizStmt = $pdo->prepare("
    SELECT qr.*, q.title AS quiz_title, c.title AS course_title
    FROM quiz_results qr
    JOIN quizzes q ON qr.quiz_id = q.id
    JOIN courses c ON qr.course_id = c.id
    WHERE qr.user_id = ?
    ORDER BY qr.taken_at DESC
");
$quizStmt->execute([$currentUser['id']]);
$quizResults = $quizStmt->fetchAll();

// Calculate overall metrics
$totalEnrolled = count($enrolledCourses);
$totalLessonsCompleted = 0;
$completedCoursesCount = 0;

foreach ($enrolledCourses as &$ec) {
    $total = (int)$ec['total_lessons'];
    $done = (int)$ec['completed_lessons'];
    $totalLessonsCompleted += $done;
    $ec['percent'] = $total > 0 ? round(($done / $total) * 100) : 0;
    if ($total > 0 && $done >= $total) {
        $completedCoursesCount++;
    }
}
unset($ec);

$quizzesPassed = 0;
foreach ($quizResults as $qr) {
    if ($qr['total_questions'] > 0 && ($qr['score'] / $qr['total_questions']) >= 0.7) {
        $quizzesPassed++;
    }
}

require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 30px;">
    <p class="small-heading">STUDENT LEARNING DASHBOARD</p>
    <h1 style="font-size: 28px; color: #111827;">My Learning & Progress</h1>
</div>

<!-- Stat Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $totalEnrolled ?></div>
        <div class="stat-label">Enrolled Courses</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $totalLessonsCompleted ?></div>
        <div class="stat-label">Lessons Completed</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $completedCoursesCount ?></div>
        <div class="stat-label">Courses Finished</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= count($quizResults) ?></div>
        <div class="stat-label">Quizzes Taken</div>
    </div>
</div>

<!-- Enrolled Courses Progress -->
<div style="margin-bottom: 45px;">
    <div class="section-heading" style="margin-bottom: 20px;">
        <h2 style="font-size: 20px;">Enrolled Courses</h2>
        <a href="courses.php" class="view-all">Browse More Courses &rarr;</a>
    </div>

    <?php if (empty($enrolledCourses)): ?>
        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 40px; text-align: center;">
            <p style="color: #6b7280; font-size: 15px; margin-bottom: 15px;">You have not enrolled in any courses yet.</p>
            <a href="courses.php" class="primary-btn btn-primary btn-sm">Explore Courses</a>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <?php foreach ($enrolledCourses as $course): ?>
                <div class="progress-card">
                    <div class="progress-course">
                        <div class="course-icon"></div>
                        <div>
                            <span><?= htmlspecialchars($course['category']) ?></span>
                            <h3><?= htmlspecialchars($course['title']) ?></h3>
                        </div>
                    </div>

                    <div class="progress-details">
                        <div class="progress-text">
                            <span>Progress</span>
                            <strong><?= $course['percent'] ?>%</strong>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $course['percent'] ?>%;"></div>
                        </div>
                        <p><?= $course['completed_lessons'] ?> of <?= $course['total_lessons'] ?> lessons completed</p>
                    </div>

                    <a href="course_view.php?id=<?= $course['course_id'] ?>" class="continue-btn">
                        Continue &rarr;
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Quiz History -->
<div>
    <div class="section-heading" style="margin-bottom: 20px;">
        <h2 style="font-size: 20px;">Quiz Performance & History</h2>
        <a href="quizzes.php" class="view-all">All Quizzes &rarr;</a>
    </div>

    <?php if (empty($quizResults)): ?>
        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 30px; text-align: center;">
            <p style="color: #6b7280; font-size: 14px; margin-bottom: 12px;">You haven't taken any quizzes yet.</p>
            <a href="quizzes.php" class="secondary-btn btn-secondary btn-sm">Take a Quiz</a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Quiz Name</th>
                        <th>Course</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Status</th>
                        <th>Date Taken</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizResults as $res): ?>
                        <?php 
                            $pct = $res['total_questions'] > 0 ? round(($res['score'] / $res['total_questions']) * 100) : 0;
                            $passed = $pct >= 70;
                        ?>
                        <tr>
                            <td style="font-weight: 600;"><?= htmlspecialchars($res['quiz_title']) ?></td>
                            <td><?= htmlspecialchars($res['course_title']) ?></td>
                            <td><?= $res['score'] ?> / <?= $res['total_questions'] ?></td>
                            <td><strong><?= $pct ?>%</strong></td>
                            <td>
                                <span style="font-size: 12px; font-weight: bold; color: <?= $passed ? '#16a34a' : '#ef4444' ?>;">
                                    <?= $passed ? '&#10003; Passed' : 'Needs Practice' ?>
                                </span>
                            </td>
                            <td style="color: #6b7280; font-size: 13px;"><?= date('M d, Y', strtotime($res['taken_at'])) ?></td>
                            <td>
                                <a href="quiz_take.php?quiz_id=<?= $res['quiz_id'] ?>" class="secondary-btn btn-secondary btn-sm">
                                    Retake
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
