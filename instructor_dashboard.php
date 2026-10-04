<?php
require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
requireInstructor();

$currentUser = getCurrentUser();
$pageTitle = 'Instructor Dashboard';

// Handle course deletion if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_course') {
    $courseIdToDelete = (int)($_POST['course_id'] ?? 0);
    $checkStmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
    $checkStmt->execute([$courseIdToDelete, $currentUser['id']]);
    if ($checkStmt->fetch()) {
        // Delete course & associated records
        $pdo->prepare("DELETE FROM lesson_progress WHERE course_id = ?")->execute([$courseIdToDelete]);
        $pdo->prepare("DELETE FROM enrollments WHERE course_id = ?")->execute([$courseIdToDelete]);
        $pdo->prepare("DELETE FROM lessons WHERE course_id = ?")->execute([$courseIdToDelete]);
        
        $qIds = $pdo->prepare("SELECT id FROM quizzes WHERE course_id = ?");
        $qIds->execute([$courseIdToDelete]);
        $quizIds = $qIds->fetchAll(PDO::FETCH_COLUMN);
        foreach ($quizIds as $qid) {
            $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?")->execute([$qid]);
            $pdo->prepare("DELETE FROM quiz_results WHERE quiz_id = ?")->execute([$qid]);
        }
        $pdo->prepare("DELETE FROM quizzes WHERE course_id = ?")->execute([$courseIdToDelete]);
        $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$courseIdToDelete]);

        $_SESSION['flash_success'] = 'Course deleted successfully.';
        header('Location: instructor_dashboard.php');
        exit;
    }
}

// Fetch instructor's courses
$stmt = $pdo->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS lesson_count,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) AS student_count,
           (SELECT id FROM quizzes WHERE course_id = c.id LIMIT 1) AS quiz_id
    FROM courses c
    WHERE c.instructor_id = ?
    ORDER BY c.id DESC
");
$stmt->execute([$currentUser['id']]);
$courses = $stmt->fetchAll();

// Calculate total metrics
$totalCourses = count($courses);
$totalStudents = 0;
$totalLessons = 0;
foreach ($courses as $c) {
    $totalStudents += (int)$c['student_count'];
    $totalLessons += (int)$c['lesson_count'];
}

require_once __DIR__ . '/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <p class="small-heading">INSTRUCTOR PORTAL</p>
        <h1 style="font-size: 28px; color: #111827;">Course Management</h1>
    </div>
    <a href="instructor_add_course.php" class="primary-btn btn-primary btn-sm">
        + Upload New Course
    </a>
</div>

<!-- Stat Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $totalCourses ?></div>
        <div class="stat-label">Uploaded Courses</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $totalStudents ?></div>
        <div class="stat-label">Total Enrollments</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $totalLessons ?></div>
        <div class="stat-label">Published Lessons</div>
    </div>
</div>

<!-- Courses Table -->
<div class="section-heading" style="margin-bottom: 15px;">
    <h2 style="font-size: 20px;">My Courses</h2>
</div>

<?php if (empty($courses)): ?>
    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 40px; text-align: center;">
        <h3 style="color: #4b5563; margin-bottom: 8px;">No courses created yet</h3>
        <p style="color: #9ca3af; font-size: 14px; margin-bottom: 20px;">Create your first course to start uploading lessons and quizzes.</p>
        <a href="instructor_add_course.php" class="primary-btn btn-primary btn-sm">+ Upload Course</a>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Course Title</th>
                    <th>Category</th>
                    <th>Level</th>
                    <th>Lessons</th>
                    <th>Enrolled Students</th>
                    <th>Quiz</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): ?>
                    <tr>
                        <td style="font-weight: 600;">
                            <a href="course_view.php?id=<?= $c['id'] ?>" style="color: #111827;">
                                <?= htmlspecialchars($c['title']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($c['category']) ?></td>
                        <td><?= htmlspecialchars($c['level']) ?></td>
                        <td>
                            <strong><?= $c['lesson_count'] ?></strong>
                        </td>
                        <td>
                            <strong><?= $c['student_count'] ?></strong>
                        </td>
                        <td>
                            <?php if ($c['quiz_id']): ?>
                                <span style="color: #16a34a; font-size: 12px; font-weight: bold;">&#10003; Active</span>
                            <?php else: ?>
                                <span style="color: #9ca3af; font-size: 12px;">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <a href="instructor_manage_lessons.php?course_id=<?= $c['id'] ?>" class="secondary-btn btn-secondary btn-sm" title="Add / edit lessons">
                                    Lessons (<?= $c['lesson_count'] ?>)
                                </a>
                                <a href="instructor_manage_quiz.php?course_id=<?= $c['id'] ?>" class="secondary-btn btn-secondary btn-sm" title="Manage quiz">
                                    Quiz
                                </a>
                                <form action="instructor_dashboard.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this course and all its lessons?');" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_course">
                                    <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn-danger btn-sm" style="padding: 6px 10px;">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
