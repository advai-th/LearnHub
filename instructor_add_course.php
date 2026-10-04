<?php
require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
requireInstructor();

$currentUser = getCurrentUser();
$pageTitle = 'Upload Course';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $level = $_POST['level'] ?? 'Beginner';
    $badgeTag = strtoupper(trim($_POST['badge_tag'] ?? 'COURSE'));
    $badgeColor = $_POST['badge_color'] ?? 'web';
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($category) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } else {
        if (strlen($badgeTag) > 10) {
            $badgeTag = substr($badgeTag, 0, 10);
        }

        $stmt = $pdo->prepare("
            INSERT INTO courses (instructor_id, title, category, level, badge_tag, badge_color, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $currentUser['id'],
            $title,
            $category,
            $level,
            $badgeTag ?: 'COURSE',
            $badgeColor,
            $description
        ]);
        $newCourseId = $pdo->lastInsertId();

        $_SESSION['flash_success'] = 'Course created! Now you can upload lessons to this course.';
        header("Location: instructor_manage_lessons.php?course_id={$newCourseId}");
        exit;
    }
}

require_once __DIR__ . '/header.php';
?>

<div style="max-width: 650px; margin: 20px auto;">
    <div style="margin-bottom: 20px;">
        <a href="instructor_dashboard.php" style="color: #6b7280; font-size: 14px;">&larr; Back to Instructor Dashboard</a>
    </div>

    <div class="form-card">
        <p class="small-heading">NEW COURSE</p>
        <h1 style="font-size: 24px; color: #111827; margin-bottom: 8px;">Upload Course</h1>
        <p style="color: #6b7280; font-size: 14px; margin-bottom: 24px;">Provide course details below. You can add lessons and quizzes in the next step.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="instructor_add_course.php" method="POST">
            <div class="form-group">
                <label class="form-label" for="title">Course Title *</label>
                <input type="text" name="title" id="title" class="form-input" required placeholder="e.g. Modern Fullstack React & Node.js" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="category">Category *</label>
                    <input type="text" name="category" id="category" class="form-input" required placeholder="e.g. Web Development, AI, Mobile" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="level">Difficulty Level *</label>
                    <select name="level" id="level" class="form-select">
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Advanced">Advanced</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="badge_tag">Short Badge Tag (e.g. REACT, JS, CLOUD)</label>
                    <input type="text" name="badge_tag" id="badge_tag" class="form-input" placeholder="REACT" maxlength="10" value="<?= htmlspecialchars($_POST['badge_tag'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="badge_color">Card Accent Color</label>
                    <select name="badge_color" id="badge_color" class="form-select">
                        <option value="web">Blue</option>
                        <option value="python">Green</option>
                        <option value="database">Purple</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Course Overview / Description *</label>
                <textarea name="description" id="description" class="form-textarea" required placeholder="Describe what students will learn in this course..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 25px;">
                <a href="instructor_dashboard.php" class="secondary-btn btn-secondary btn-sm" style="padding: 10px 18px;">
                    Cancel
                </a>
                <button type="submit" class="primary-btn btn-primary" style="padding: 10px 24px;">
                    Create Course & Proceed to Lessons &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
