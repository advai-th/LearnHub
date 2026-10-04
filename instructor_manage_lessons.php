<?php
require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
requireInstructor();

$currentUser = getCurrentUser();
$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Verify course ownership
$stmtCourse = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
$stmtCourse->execute([$courseId, $currentUser['id']]);
$course = $stmtCourse->fetch();

if (!$course) {
    $_SESSION['flash_error'] = 'Course not found or unauthorized.';
    header('Location: instructor_dashboard.php');
    exit;
}

$pageTitle = 'Manage Lessons - ' . $course['title'];

// Handle Add Lesson
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_lesson') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $orderNum = (int)($_POST['order_num'] ?? 1);

    if (!empty($title) && !empty($content)) {
        $insStmt = $pdo->prepare("INSERT INTO lessons (course_id, title, content, order_num) VALUES (?, ?, ?, ?)");
        $insStmt->execute([$courseId, $title, $content, $orderNum]);
        $_SESSION['flash_success'] = 'Lesson added successfully!';
        header("Location: instructor_manage_lessons.php?course_id={$courseId}");
        exit;
    } else {
        $error = 'Please fill in both the lesson title and content.';
    }
}

// Handle Delete Lesson
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_lesson') {
    $lessonIdToDelete = (int)($_POST['lesson_id'] ?? 0);
    // Delete progress for this lesson first
    $pdo->prepare("DELETE FROM lesson_progress WHERE lesson_id = ?")->execute([$lessonIdToDelete]);
    $delStmt = $pdo->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?");
    $delStmt->execute([$lessonIdToDelete, $courseId]);
    $_SESSION['flash_success'] = 'Lesson deleted.';
    header("Location: instructor_manage_lessons.php?course_id={$courseId}");
    exit;
}

// Fetch all lessons
$stmtLessons = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_num ASC, id ASC");
$stmtLessons->execute([$courseId]);
$lessons = $stmtLessons->fetchAll();
$nextOrderNum = count($lessons) + 1;

require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <a href="instructor_dashboard.php" style="color: #6b7280; font-size: 14px;">&larr; Back to Instructor Dashboard</a>
        <h1 style="font-size: 26px; color: #111827; margin-top: 6px;">Manage Lessons: <?= htmlspecialchars($course['title']) ?></h1>
    </div>

    <div style="display: flex; gap: 10px;">
        <a href="course_view.php?id=<?= $course['id'] ?>" class="secondary-btn btn-secondary btn-sm" target="_blank">
            Preview Course &#8599;
        </a>
        <a href="instructor_manage_quiz.php?course_id=<?= $course['id'] ?>" class="primary-btn btn-primary btn-sm">
            Manage Quiz &rarr;
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
    <!-- Left: Existing Lessons List -->
    <div>
        <h3 style="font-size: 18px; color: #111827; margin-bottom: 15px;">Existing Lessons (<?= count($lessons) ?>)</h3>

        <?php if (empty($lessons)): ?>
            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 25px; color: #6b7280; font-size: 14px;">
                No lessons yet. Use the form on the right to upload your first lesson.
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($lessons as $idx => $l): ?>
                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 18px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span style="font-size: 11px; background: #f3f4f6; color: #4b5563; padding: 2px 6px; border-radius: 4px; font-weight: bold;">
                                    Lesson #<?= $l['order_num'] ?>
                                </span>
                                <h4 style="font-size: 16px; color: #111827; margin: 6px 0;"><?= htmlspecialchars($l['title']) ?></h4>
                                <p style="font-size: 13px; color: #6b7280; max-height: 48px; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars(substr($l['content'], 0, 120)) ?>...
                                </p>
                            </div>

                            <form action="instructor_manage_lessons.php?course_id=<?= $courseId ?>" method="POST" onsubmit="return confirm('Delete this lesson?');" style="margin: 0;">
                                <input type="hidden" name="action" value="delete_lesson">
                                <input type="hidden" name="lesson_id" value="<?= $l['id'] ?>">
                                <button type="submit" class="btn-danger btn-sm" style="padding: 4px 8px; font-size: 12px;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Add Lesson Form -->
    <div class="form-card" style="margin: 0; max-width: 100%;">
        <h3 style="font-size: 18px; color: #111827; margin-bottom: 6px;">Add New Lesson</h3>
        <p style="color: #6b7280; font-size: 13px; margin-bottom: 20px;">Upload study material, code examples, or notes for this lesson.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="instructor_manage_lessons.php?course_id=<?= $courseId ?>" method="POST">
            <input type="hidden" name="action" value="add_lesson">

            <div class="form-row">
                <div class="form-group" style="flex: 3;">
                    <label class="form-label" for="title">Lesson Title *</label>
                    <input type="text" name="title" id="title" class="form-input" required placeholder="e.g. Asynchronous JavaScript & Promises">
                </div>

                <div class="form-group" style="flex: 1;">
                    <label class="form-label" for="order_num">Order #</label>
                    <input type="number" name="order_num" id="order_num" class="form-input" value="<?= $nextOrderNum ?>" min="1">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="content">Lesson Notes / Content *</label>
                <textarea name="content" id="content" class="form-textarea" style="min-height: 200px;" required placeholder="Write or paste your lesson explanation, tutorials, bullet points, or instructions here..."></textarea>
            </div>

            <button type="submit" class="primary-btn btn-primary" style="width: 100%; padding: 11px;">
                + Add Lesson
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
