<?php
require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
requireLogin();

$currentUser = getCurrentUser();
$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

// Fetch quiz & course
$stmtQuiz = $pdo->prepare("
    SELECT q.*, c.title AS course_title, c.id AS course_id
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    WHERE q.id = ?
");
$stmtQuiz->execute([$quizId]);
$quiz = $stmtQuiz->fetch();

if (!$quiz) {
    $_SESSION['flash_error'] = 'Quiz not found.';
    header('Location: quizzes.php');
    exit;
}

$pageTitle = $quiz['title'];

// Fetch questions
$stmtQ = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC");
$stmtQ->execute([$quizId]);
$questions = $stmtQ->fetchAll();

if (empty($questions)) {
    $_SESSION['flash_error'] = 'No questions found for this quiz yet.';
    header('Location: quizzes.php');
    exit;
}

$totalQuestions = count($questions);
$submitted = false;
$score = 0;
$userAnswers = [];

// Handle Quiz Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_quiz') {
    $submitted = true;
    $userAnswers = $_POST['answers'] ?? [];

    foreach ($questions as $q) {
        $qId = $q['id'];
        $correct = strtolower(trim($q['correct_option']));
        $ans = isset($userAnswers[$qId]) ? strtolower(trim($userAnswers[$qId])) : '';

        if ($ans === $correct) {
            $score++;
        }
    }

    // Save result to database
    $stmtRes = $pdo->prepare("INSERT INTO quiz_results (user_id, quiz_id, course_id, score, total_questions) VALUES (?, ?, ?, ?, ?)");
    $stmtRes->execute([$currentUser['id'], $quizId, $quiz['course_id'], $score, $totalQuestions]);
}

require_once __DIR__ . '/header.php';
?>

<div style="max-width: 760px; margin: 0 auto;">
    <div style="margin-bottom: 25px;">
        <a href="quizzes.php" style="color: #6b7280; font-size: 14px;">&larr; Back to all quizzes</a>
    </div>

    <?php if ($submitted): ?>
        <?php 
            $percent = round(($score / $totalQuestions) * 100);
            $passed = $percent >= 70;
        ?>
        <!-- Result Card -->
        <div class="quiz-result-score">
            <p class="small-heading">QUIZ COMPLETED</p>
            <h2 style="font-size: 26px; color: #111827;"><?= htmlspecialchars($quiz['title']) ?></h2>

            <div class="quiz-score-circle <?= $passed ? 'score-pass' : 'score-fail' ?>">
                <?= $percent ?>%
            </div>

            <p style="font-size: 16px; font-weight: 600; color: #111827; margin-bottom: 8px;">
                You scored <?= $score ?> out of <?= $totalQuestions ?> questions correctly.
            </p>

            <p style="color: #6b7280; font-size: 14px; margin-bottom: 25px;">
                <?= $passed ? 'Congratulations! You passed the quiz.' : 'You scored below 70%. Review the concepts and try again.' ?>
            </p>

            <div style="display: flex; justify-content: center; gap: 15px;">
                <a href="quiz_take.php?quiz_id=<?= $quizId ?>" class="primary-btn btn-primary btn-sm">
                    Retake Quiz
                </a>
                <a href="progress.php" class="secondary-btn btn-secondary btn-sm">
                    View My Progress
                </a>
                <a href="course_view.php?id=<?= $quiz['course_id'] ?>" class="secondary-btn btn-secondary btn-sm">
                    Back to Course
                </a>
            </div>
        </div>

        <!-- Question Review -->
        <h3 style="font-size: 20px; color: #111827; margin-bottom: 20px;">Review Answers</h3>
        <?php foreach ($questions as $idx => $q): ?>
            <?php 
                $selected = $userAnswers[$q['id']] ?? '';
                $correct = strtolower($q['correct_option']);
                $isCorrect = ($selected === $correct);
            ?>
            <div class="quiz-question-card" style="border-left: 4px solid <?= $isCorrect ? '#16a34a' : '#ef4444' ?>;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span style="font-size: 12px; font-weight: 600; color: #6b7280;">Question <?= $idx + 1 ?> of <?= $totalQuestions ?></span>
                    <span style="font-size: 12px; font-weight: bold; color: <?= $isCorrect ? '#16a34a' : '#ef4444' ?>;">
                        <?= $isCorrect ? '&#10003; Correct' : '&#10007; Incorrect' ?>
                    </span>
                </div>

                <div class="quiz-q-title"><?= htmlspecialchars($q['question']) ?></div>

                <div style="font-size: 13px; line-height: 1.8; margin-top: 10px;">
                    <div>Your answer: <strong style="color: <?= $isCorrect ? '#16a34a' : '#ef4444' ?>;"><?= strtoupper($selected ?: 'None') ?>) <?= htmlspecialchars($q['option_' . $selected] ?? 'Not answered') ?></strong></div>
                    <?php if (!$isCorrect): ?>
                        <div style="color: #16a34a;">Correct answer: <strong><?= strtoupper($correct) ?>) <?= htmlspecialchars($q['option_' . $correct]) ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php else: ?>
        <!-- Taking Quiz Form -->
        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 25px; margin-bottom: 25px;">
            <span class="category" style="font-size: 12px;">Course: <?= htmlspecialchars($quiz['course_title']) ?></span>
            <h1 style="font-size: 24px; color: #111827; margin: 6px 0 10px;"><?= htmlspecialchars($quiz['title']) ?></h1>
            <p style="color: #6b7280; font-size: 14px;">Select the best option for each question and submit when finished.</p>
        </div>

        <form action="quiz_take.php?quiz_id=<?= $quizId ?>" method="POST">
            <input type="hidden" name="action" value="submit_quiz">

            <?php foreach ($questions as $idx => $q): ?>
                <div class="quiz-question-card">
                    <div style="font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 8px;">Question <?= $idx + 1 ?> of <?= $totalQuestions ?></div>
                    <div class="quiz-q-title"><?= htmlspecialchars($q['question']) ?></div>

                    <div class="quiz-options">
                        <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
                            <label class="quiz-option-label">
                                <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt ?>" required>
                                <span><strong><?= strtoupper($opt) ?>)</strong> <?= htmlspecialchars($q['option_' . $opt]) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="margin-top: 30px; text-align: right;">
                <button type="submit" class="primary-btn btn-primary" style="padding: 12px 30px; font-size: 15px;">
                    Submit Quiz &rarr;
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
