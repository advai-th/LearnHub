<?php
require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
$currentUser = getCurrentUser();

$pageTitle = 'Quizzes';

// Fetch all quizzes with course info and question counts
$sql = "
    SELECT q.*, c.title AS course_title, c.category, c.badge_tag,
           COUNT(qq.id) AS question_count
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    LEFT JOIN quiz_questions qq ON q.id = qq.quiz_id
    GROUP BY q.id
    ORDER BY q.id ASC
";
$quizzes = $pdo->query($sql)->fetchAll();

// Fetch student's best score per quiz if logged in
$userScores = [];
if ($currentUser) {
    $scStmt = $pdo->prepare("SELECT quiz_id, MAX(score) as max_score, total_questions FROM quiz_results WHERE user_id = ? GROUP BY quiz_id, total_questions");
    $scStmt->execute([$currentUser['id']]);
    while ($row = $scStmt->fetch()) {
        $userScores[$row['quiz_id']] = $row;
    }
}

require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 30px;">
    <p class="small-heading">ASSESSMENT & CERTIFICATION</p>
    <h1 style="font-size: 28px; color: #111827;">Available Quizzes</h1>
    <p style="color: #6b7280; font-size: 15px; margin-top: 6px;">Test your understanding of course concepts and track your knowledge.</p>
</div>

<?php if (empty($quizzes)): ?>
    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 40px; text-align: center;">
        <h3 style="color: #4b5563;">No quizzes available yet.</h3>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px;">
        <?php foreach ($quizzes as $quiz): ?>
            <?php 
                $hasScore = isset($userScores[$quiz['id']]);
                $scoreData = $hasScore ? $userScores[$quiz['id']] : null;
            ?>
            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 24px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <span class="category" style="font-size: 12px;"><?= htmlspecialchars($quiz['category']) ?></span>
                    <h3 style="font-size: 18px; color: #111827; margin: 6px 0 10px;"><?= htmlspecialchars($quiz['title']) ?></h3>
                    <p style="color: #6b7280; font-size: 13px; margin-bottom: 16px; line-height: 1.5;">
                        <?= htmlspecialchars($quiz['description'] ?: 'Complete this quiz to test your mastery of ' . $quiz['course_title']) ?>
                    </p>

                    <div style="font-size: 12px; color: #9ca3af; margin-bottom: 20px;">
                        Course: <strong><?= htmlspecialchars($quiz['course_title']) ?></strong> &bull; <?= (int)$quiz['question_count'] ?> Questions
                    </div>
                </div>

                <div>
                    <?php if ($hasScore): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; background: #f8fafc; padding: 8px 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #e5e7eb;">
                            <span style="color: #6b7280;">Best Score:</span>
                            <strong style="color: #16a34a;"><?= $scoreData['max_score'] ?> / <?= $scoreData['total_questions'] ?></strong>
                        </div>
                    <?php endif; ?>

                    <a href="quiz_take.php?quiz_id=<?= $quiz['id'] ?>" class="primary-btn btn-primary" style="display: block; text-align: center; padding: 10px;">
                        <?= $hasScore ? 'Retake Quiz &rarr;' : 'Start Quiz &rarr;' ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
