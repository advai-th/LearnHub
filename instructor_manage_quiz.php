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

$pageTitle = 'Manage Quiz - ' . $course['title'];

// Find or create quiz record for this course
$stmtQuiz = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ? LIMIT 1");
$stmtQuiz->execute([$courseId]);
$quiz = $stmtQuiz->fetch();

if (!$quiz) {
    $insQuiz = $pdo->prepare("INSERT INTO quizzes (course_id, title, description) VALUES (?, ?, ?)");
    $insQuiz->execute([$courseId, $course['title'] . ' Quiz', 'Assessment quiz covering key concepts in ' . $course['title']]);
    $quizId = $pdo->lastInsertId();
    $stmtQuiz->execute([$courseId]);
    $quiz = $stmtQuiz->fetch();
} else {
    $quizId = $quiz['id'];
}

// Handle Add Question
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_question') {
    $question = trim($_POST['question'] ?? '');
    $optA = trim($_POST['option_a'] ?? '');
    $optB = trim($_POST['option_b'] ?? '');
    $optC = trim($_POST['option_c'] ?? '');
    $optD = trim($_POST['option_d'] ?? '');
    $correct = strtolower(trim($_POST['correct_option'] ?? 'a'));

    if (empty($question) || empty($optA) || empty($optB) || empty($optC) || empty($optD)) {
        $error = 'Please fill in the question and all four options.';
    } else {
        $insQ = $pdo->prepare("
            INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_option)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insQ->execute([$quizId, $question, $optA, $optB, $optC, $optD, $correct]);
        $_SESSION['flash_success'] = 'Question added to quiz!';
        header("Location: instructor_manage_quiz.php?course_id={$courseId}");
        exit;
    }
}

// Handle Delete Question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_question') {
    $qIdToDelete = (int)($_POST['question_id'] ?? 0);
    $pdo->prepare("DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?")->execute([$qIdToDelete, $quizId]);
    $_SESSION['flash_success'] = 'Question deleted.';
    header("Location: instructor_manage_quiz.php?course_id={$courseId}");
    exit;
}

// Fetch all questions for this quiz
$stmtQuestions = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC");
$stmtQuestions->execute([$quizId]);
$questions = $stmtQuestions->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <a href="instructor_dashboard.php" style="color: #6b7280; font-size: 14px;">&larr; Back to Instructor Dashboard</a>
        <h1 style="font-size: 26px; color: #111827; margin-top: 6px;">Manage Quiz: <?= htmlspecialchars($course['title']) ?></h1>
    </div>

    <div style="display: flex; gap: 10px;">
        <a href="instructor_manage_lessons.php?course_id=<?= $course['id'] ?>" class="secondary-btn btn-secondary btn-sm">
            Manage Lessons
        </a>
        <a href="quiz_take.php?quiz_id=<?= $quizId ?>" class="secondary-btn btn-secondary btn-sm" target="_blank">
            Preview Quiz &#8599;
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 30px; align-items: start;">
    <!-- Left: Existing Questions List -->
    <div>
        <h3 style="font-size: 18px; color: #111827; margin-bottom: 15px;">Quiz Questions (<?= count($questions) ?>)</h3>

        <?php if (empty($questions)): ?>
            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 25px; color: #6b7280; font-size: 14px;">
                No questions created yet. Use the form on the right to add questions.
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php foreach ($questions as $idx => $q): ?>
                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 18px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <span style="font-size: 12px; font-weight: bold; color: #6b7280;">Q<?= $idx + 1 ?></span>
                            <form action="instructor_manage_quiz.php?course_id=<?= $courseId ?>" method="POST" onsubmit="return confirm('Delete this question?');" style="margin: 0;">
                                <input type="hidden" name="action" value="delete_question">
                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                <button type="submit" class="btn-danger btn-sm" style="padding: 4px 8px; font-size: 11px;">
                                    Delete
                                </button>
                            </form>
                        </div>

                        <div style="font-weight: 600; color: #111827; font-size: 14px; margin-bottom: 10px;">
                            <?= htmlspecialchars($q['question']) ?>
                        </div>

                        <ul style="list-style: none; font-size: 13px; color: #4b5563; display: flex; flex-direction: column; gap: 4px;">
                            <li style="<?= $q['correct_option'] === 'a' ? 'font-weight: bold; color: #16a34a;' : '' ?>">A) <?= htmlspecialchars($q['option_a']) ?></li>
                            <li style="<?= $q['correct_option'] === 'b' ? 'font-weight: bold; color: #16a34a;' : '' ?>">B) <?= htmlspecialchars($q['option_b']) ?></li>
                            <li style="<?= $q['correct_option'] === 'c' ? 'font-weight: bold; color: #16a34a;' : '' ?>">C) <?= htmlspecialchars($q['option_c']) ?></li>
                            <li style="<?= $q['correct_option'] === 'd' ? 'font-weight: bold; color: #16a34a;' : '' ?>">D) <?= htmlspecialchars($q['option_d']) ?></li>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Add Question Form -->
    <div class="form-card" style="margin: 0; max-width: 100%;">
        <h3 style="font-size: 18px; color: #111827; margin-bottom: 6px;">Add New Question</h3>
        <p style="color: #6b7280; font-size: 13px; margin-bottom: 20px;">Provide the question text, 4 choices, and select the correct option.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="instructor_manage_quiz.php?course_id=<?= $courseId ?>" method="POST">
            <input type="hidden" name="action" value="add_question">

            <div class="form-group">
                <label class="form-label" for="question">Question Prompt *</label>
                <textarea name="question" id="question" class="form-textarea" style="min-height: 80px;" required placeholder="e.g. Which CSS property defines the space between borders and outer elements?"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="option_a">Option A *</label>
                    <input type="text" name="option_a" id="option_a" class="form-input" required placeholder="Option A text">
                </div>
                <div class="form-group">
                    <label class="form-label" for="option_b">Option B *</label>
                    <input type="text" name="option_b" id="option_b" class="form-input" required placeholder="Option B text">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="option_c">Option C *</label>
                    <input type="text" name="option_c" id="option_c" class="form-input" required placeholder="Option C text">
                </div>
                <div class="form-group">
                    <label class="form-label" for="option_d">Option D *</label>
                    <input type="text" name="option_d" id="option_d" class="form-input" required placeholder="Option D text">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="correct_option">Correct Answer *</label>
                <select name="correct_option" id="correct_option" class="form-select">
                    <option value="a">Option A</option>
                    <option value="b">Option B</option>
                    <option value="c">Option C</option>
                    <option value="d">Option D</option>
                </select>
            </div>

            <button type="submit" class="primary-btn btn-primary" style="width: 100%; padding: 11px;">
                + Add Question
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
