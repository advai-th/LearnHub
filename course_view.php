<?php
require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
$currentUser = getCurrentUser();

$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT c.*, u.name AS instructor_name, u.email AS instructor_email
    FROM courses c
    JOIN users u ON c.instructor_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    $_SESSION['flash_error'] = 'Course not found.';
    header('Location: courses.php');
    exit;
}

$pageTitle = $course['title'];

// Check enrollment
$isEnrolled = false;
if ($currentUser) {
    $enStmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
    $enStmt->execute([$currentUser['id'], $courseId]);
    $isEnrolled = (bool)$enStmt->fetch();
}

// Handle enrollment action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll') {
    requireLogin();
    if (!$isEnrolled) {
        $insStmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id) VALUES (?, ?)");
        $insStmt->execute([$currentUser['id'], $courseId]);
        $_SESSION['flash_success'] = 'You have enrolled in ' . htmlspecialchars($course['title']) . '!';
        header("Location: course_view.php?id={$courseId}");
        exit;
    }
}

// Fetch lessons
$lessStmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_num ASC, id ASC");
$lessStmt->execute([$courseId]);
$lessons = $lessStmt->fetchAll();

// Fetch completed lesson IDs for current user
$completedLessonIds = [];
if ($currentUser && $isEnrolled) {
    $progStmt = $pdo->prepare("SELECT lesson_id FROM lesson_progress WHERE user_id = ? AND course_id = ?");
    $progStmt->execute([$currentUser['id'], $courseId]);
    $completedLessonIds = $progStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Check if course has a quiz
$quizStmt = $pdo->prepare("SELECT id, title FROM quizzes WHERE course_id = ? LIMIT 1");
$quizStmt->execute([$courseId]);
$quiz = $quizStmt->fetch();

$totalLessons = count($lessons);
$completedCount = count($completedLessonIds);
$percentComplete = $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100) : 0;

// Find first uncompleted lesson
$nextLessonId = null;
foreach ($lessons as $l) {
    if (!in_array($l['id'], $completedLessonIds)) {
        $nextLessonId = $l['id'];
        break;
    }
}
if (!$nextLessonId && $totalLessons > 0) {
    $nextLessonId = $lessons[0]['id'];
}

require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 25px;">
    <a href="courses.php" style="color: #6b7280; font-size: 14px;">&larr; Back to all courses</a>
</div>

<!-- Course Hero Banner -->
<div style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 35px; margin-bottom: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
        <div style="max-width: 700px;">
            <span class="category" style="font-size: 13px;"><?= htmlspecialchars($course['category']) ?> &bull; <?= htmlspecialchars($course['level']) ?></span>
            <h1 style="font-size: 32px; color: #111827; margin: 10px 0 15px;"><?= htmlspecialchars($course['title']) ?></h1>
            <p style="color: #4b5563; font-size: 16px; margin-bottom: 20px; line-height: 1.6;">
                <?= htmlspecialchars($course['description']) ?>
            </p>
            <p style="font-size: 13px; color: #6b7280;">
                Instructor: <strong><?= htmlspecialchars($course['instructor_name']) ?></strong> &bull; <?= $totalLessons ?> Lessons
            </p>
        </div>

        <div style="min-width: 240px; text-align: right;">
            <?php if ($currentUser && $currentUser['role'] === 'instructor' && $currentUser['id'] == $course['instructor_id']): ?>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="instructor_manage_lessons.php?course_id=<?= $course['id'] ?>" class="secondary-btn btn-secondary btn-sm" style="text-align: center; padding: 10px;">
                        Manage Lessons (<?= $totalLessons ?>)
                    </a>
                    <a href="instructor_manage_quiz.php?course_id=<?= $course['id'] ?>" class="secondary-btn btn-secondary btn-sm" style="text-align: center; padding: 10px;">
                        <?= $quiz ? 'Edit Quiz' : '+ Create Quiz' ?>
                    </a>
                </div>
            <?php elseif ($isEnrolled): ?>
                <div style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; text-align: left; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                        <span style="color: #6b7280;">Course Progress</span>
                        <strong style="color: #2563eb;"><?= $percentComplete ?>%</strong>
                    </div>
                    <div class="progress-bar" style="margin-bottom: 8px;">
                        <div class="progress-fill" style="width: <?= $percentComplete ?>%;"></div>
                    </div>
                    <span style="font-size: 12px; color: #9ca3af;"><?= $completedCount ?> of <?= $totalLessons ?> completed</span>
                </div>
                <?php if ($nextLessonId): ?>
                    <a href="lesson_view.php?course_id=<?= $course['id'] ?>&lesson_id=<?= $nextLessonId ?>" class="primary-btn btn-primary" style="display: block; text-align: center;">
                        <?= ($completedCount === 0) ? 'Start Learning' : 'Continue Learning' ?> &rarr;
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <form action="course_view.php?id=<?= $course['id'] ?>" method="POST">
                    <input type="hidden" name="action" value="enroll">
                    <button type="submit" class="primary-btn btn-primary" style="width: 100%; padding: 14px 28px; font-size: 15px;">
                        Enroll in Course
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Course Syllabus / Lessons List -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
    <div>
        <h3 style="font-size: 20px; color: #111827; margin-bottom: 16px;">Course Curriculum</h3>

        <?php if (empty($lessons)): ?>
            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 25px; color: #6b7280; font-size: 14px;">
                No lessons uploaded for this course yet.
            </div>
        <?php else: ?>
            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                <?php foreach ($lessons as $index => $lesson): ?>
                    <?php 
                        $isCompleted = in_array($lesson['id'], $completedLessonIds);
                    ?>
                    <div style="padding: 16px 20px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 13px; font-weight: 600; color: #9ca3af; width: 24px;">
                                <?= $index + 1 ?>.
                            </span>
                            <span style="font-size: 14px; font-weight: 500; color: #111827;">
                                <?= htmlspecialchars($lesson['title']) ?>
                            </span>
                        </div>

                        <div>
                            <?php if ($isCompleted): ?>
                                <span style="color: #16a34a; font-size: 12px; font-weight: bold; margin-right: 12px;">&#10003; Completed</span>
                            <?php endif; ?>

                            <?php if ($isEnrolled || ($currentUser && $currentUser['role'] === 'instructor' && $currentUser['id'] == $course['instructor_id'])): ?>
                                <a href="lesson_view.php?course_id=<?= $course['id'] ?>&lesson_id=<?= $lesson['id'] ?>" class="secondary-btn btn-secondary btn-sm">
                                    Study Lesson &rarr;
                                </a>
                            <?php else: ?>
                                <span style="font-size: 12px; color: #9ca3af;">Enroll to view</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar: Course Quiz & Instructor Info -->
    <div>
        <!-- Quiz Widget -->
        <div style="background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 24px; margin-bottom: 25px;">
            <p class="small-heading" style="margin-bottom: 6px;">ASSESSMENT</p>
            <h4 style="font-size: 17px; color: #111827; margin-bottom: 8px;">Course Quiz</h4>
            <?php if ($quiz): ?>
                <p style="font-size: 13px; color: #4b5563; margin-bottom: 16px;">
                    <?= htmlspecialchars($quiz['title']) ?>
                </p>
                <a href="quiz_take.php?quiz_id=<?= $quiz['id'] ?>" class="primary-btn btn-primary btn-sm" style="display: block; text-align: center;">
                    Take Quiz &rarr;
                </a>
            <?php else: ?>
                <p style="font-size: 13px; color: #6b7280;">
                    No quiz has been added to this course yet.
                </p>
            <?php endif; ?>
        </div>

        <!-- Instructor Widget -->
        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 24px;">
            <h4 style="font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; margin-bottom: 12px;">About the Instructor</h4>
            <div style="font-weight: bold; color: #111827; font-size: 15px; margin-bottom: 4px;">
                <?= htmlspecialchars($course['instructor_name']) ?>
            </div>
            <div style="color: #6b7280; font-size: 13px;">
                <?= htmlspecialchars($course['instructor_email']) ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
