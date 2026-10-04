<?php
require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
requireLogin();

$currentUser = getCurrentUser();
$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$lessonId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;

// Fetch course
$stmtCourse = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmtCourse->execute([$courseId]);
$course = $stmtCourse->fetch();

if (!$course) {
    $_SESSION['flash_error'] = 'Course not found.';
    header('Location: courses.php');
    exit;
}

// Fetch all lessons for course
$stmtLessons = $pdo->prepare("SELECT id, title, order_num FROM lessons WHERE course_id = ? ORDER BY order_num ASC, id ASC");
$stmtLessons->execute([$courseId]);
$allLessons = $stmtLessons->fetchAll();

if (empty($allLessons)) {
    $_SESSION['flash_error'] = 'No lessons found in this course.';
    header("Location: course_view.php?id={$courseId}");
    exit;
}

// If lesson_id not provided or invalid, pick first lesson
if (!$lessonId) {
    $lessonId = $allLessons[0]['id'];
}

// Fetch active lesson details
$stmtLesson = $pdo->prepare("SELECT * FROM lessons WHERE id = ? AND course_id = ?");
$stmtLesson->execute([$lessonId, $courseId]);
$activeLesson = $stmtLesson->fetch();

if (!$activeLesson) {
    $activeLesson = $allLessons[0];
    $lessonId = $activeLesson['id'];
}

$pageTitle = $activeLesson['title'] . ' - ' . $course['title'];

// Check user enrollment (if not instructor of the course)
$isInstructor = ($currentUser['role'] === 'instructor' && $currentUser['id'] == $course['instructor_id']);
$stmtEn = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
$stmtEn->execute([$currentUser['id'], $courseId]);
$isEnrolled = (bool)$stmtEn->fetch();

if (!$isEnrolled && !$isInstructor) {
    // Auto-enroll student if they visit directly
    $autoEn = $pdo->prepare("INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)");
    $autoEn->execute([$currentUser['id'], $courseId]);
    $isEnrolled = true;
}

// Handle "Mark as Complete" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_lesson') {
    $compStmt = $pdo->prepare("INSERT IGNORE INTO lesson_progress (user_id, course_id, lesson_id) VALUES (?, ?, ?)");
    $compStmt->execute([$currentUser['id'], $courseId, $lessonId]);
    
    // Find next lesson
    $nextId = null;
    $foundCurrent = false;
    foreach ($allLessons as $l) {
        if ($foundCurrent) {
            $nextId = $l['id'];
            break;
        }
        if ($l['id'] == $lessonId) {
            $foundCurrent = true;
        }
    }

    if ($nextId) {
        $_SESSION['flash_success'] = 'Lesson completed!';
        header("Location: lesson_view.php?course_id={$courseId}&lesson_id={$nextId}");
        exit;
    } else {
        $_SESSION['flash_success'] = 'Great job! You completed all lessons in this course!';
        header("Location: course_view.php?id={$courseId}");
        exit;
    }
}

// Fetch completed lesson IDs for current user
$completedLessonIds = [];
$progStmt = $pdo->prepare("SELECT lesson_id FROM lesson_progress WHERE user_id = ? AND course_id = ?");
$progStmt->execute([$currentUser['id'], $courseId]);
$completedLessonIds = $progStmt->fetchAll(PDO::FETCH_COLUMN);

// Find previous and next lesson IDs
$prevLessonId = null;
$nextLessonId = null;
$currentIndex = 0;
foreach ($allLessons as $idx => $l) {
    if ($l['id'] == $lessonId) {
        $currentIndex = $idx;
        $prevLessonId = $idx > 0 ? $allLessons[$idx - 1]['id'] : null;
        $nextLessonId = $idx < count($allLessons) - 1 ? $allLessons[$idx + 1]['id'] : null;
        break;
    }
}

$isCurrentLessonCompleted = in_array($lessonId, $completedLessonIds);

// Check if quiz exists
$quizStmt = $pdo->prepare("SELECT id, title FROM quizzes WHERE course_id = ? LIMIT 1");
$quizStmt->execute([$courseId]);
$courseQuiz = $quizStmt->fetch();

require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="course_view.php?id=<?= $course['id'] ?>" style="color: #6b7280; font-size: 14px;">
        &larr; Back to <?= htmlspecialchars($course['title']) ?>
    </a>
    <span style="font-size: 13px; color: #6b7280;">
        Lesson <?= $currentIndex + 1 ?> of <?= count($allLessons) ?>
    </span>
</div>

<div class="lesson-layout">
    <!-- Sidebar: Course Syllabus -->
    <aside class="lesson-sidebar">
        <h4>Lessons</h4>
        <ul class="lesson-nav-list">
            <?php foreach ($allLessons as $idx => $l): ?>
                <?php 
                    $isActive = ($l['id'] == $lessonId);
                    $isDone = in_array($l['id'], $completedLessonIds);
                ?>
                <li class="lesson-nav-item">
                    <a href="lesson_view.php?course_id=<?= $course['id'] ?>&lesson_id=<?= $l['id'] ?>" class="lesson-nav-link <?= $isActive ? 'active' : '' ?> <?= $isDone ? 'completed' : '' ?>">
                        <span><?= $idx + 1 ?>. <?= htmlspecialchars($l['title']) ?></span>
                        <?php if ($isDone): ?>
                            <span>&#10003;</span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($courseQuiz): ?>
            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <h4 style="font-size: 12px; color: #6b7280; text-transform: uppercase;">Assessment</h4>
                <a href="quiz_take.php?quiz_id=<?= $courseQuiz['id'] ?>" class="primary-btn btn-primary btn-sm" style="display: block; text-align: center; margin-top: 10px;">
                    Take Course Quiz &rarr;
                </a>
            </div>
        <?php endif; ?>
    </aside>

    <!-- Main Lesson Reader -->
    <section class="lesson-main">
        <span class="category" style="font-size: 12px;"><?= htmlspecialchars($course['category']) ?></span>
        <h1 style="font-size: 26px; color: #111827; margin: 8px 0 20px;">
            <?= htmlspecialchars($activeLesson['title']) ?>
        </h1>

        <div class="lesson-body">
            <?= nl2br(htmlspecialchars($activeLesson['content'])) ?>
        </div>

        <div class="lesson-footer-nav">
            <div>
                <?php if ($prevLessonId): ?>
                    <a href="lesson_view.php?course_id=<?= $course['id'] ?>&lesson_id=<?= $prevLessonId ?>" class="secondary-btn btn-secondary btn-sm">
                        &larr; Previous Lesson
                    </a>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <form action="lesson_view.php?course_id=<?= $course['id'] ?>&lesson_id=<?= $lessonId ?>" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="complete_lesson">
                    <button type="submit" class="<?= $isCurrentLessonCompleted ? 'secondary-btn btn-secondary btn-sm' : 'primary-btn btn-primary btn-sm' ?>">
                        <?= $isCurrentLessonCompleted ? '&#10003; Completed (Next)' : 'Mark as Complete & Next &rarr;' ?>
                    </button>
                </form>

                <?php if ($nextLessonId): ?>
                    <a href="lesson_view.php?course_id=<?= $course['id'] ?>&lesson_id=<?= $nextLessonId ?>" class="secondary-btn btn-secondary btn-sm">
                        Next &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
