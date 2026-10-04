<?php
require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
$currentUser = getCurrentUser();

$pageTitle = 'Explore Courses';

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

// Fetch distinct categories
$catStmt = $pdo->query("SELECT DISTINCT category FROM courses ORDER BY category ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// Build query
$sql = "
    SELECT c.*, u.name AS instructor_name, COUNT(l.id) AS lesson_count
    FROM courses c
    LEFT JOIN users u ON c.instructor_id = u.id
    LEFT JOIN lessons l ON c.id = l.course_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($category)) {
    $sql .= " AND c.category = ?";
    $params[] = $category;
}

$sql .= " GROUP BY c.id ORDER BY c.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Enrolled course ids for current user
$enrolledCourseIds = [];
if ($currentUser) {
    $enStmt = $pdo->prepare("SELECT course_id FROM enrollments WHERE user_id = ?");
    $enStmt->execute([$currentUser['id']]);
    $enrolledCourseIds = $enStmt->fetchAll(PDO::FETCH_COLUMN);
}

require_once __DIR__ . '/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <p class="small-heading">BROWSE CATALOG</p>
        <h2 style="font-size: 28px; color: #111827;">All Available Courses</h2>
    </div>
    <?php if ($currentUser && $currentUser['role'] === 'instructor'): ?>
        <a href="instructor_add_course.php" class="primary-btn btn-primary btn-sm">
            + Upload New Course
        </a>
    <?php endif; ?>
</div>

<!-- Search & Filter Bar -->
<form action="courses.php" method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="Search courses by keyword..." class="form-input" value="<?= htmlspecialchars($search) ?>">
    <select name="category" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="secondary-btn btn-secondary btn-sm" style="padding: 10px 18px;">
        Filter
    </button>
    <?php if (!empty($search) || !empty($category)): ?>
        <a href="courses.php" class="secondary-btn btn-secondary btn-sm" style="padding: 10px 14px;">
            Clear
        </a>
    <?php endif; ?>
</form>

<?php if (empty($courses)): ?>
    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 40px; text-align: center;">
        <h3 style="color: #4b5563; margin-bottom: 10px;">No courses found</h3>
        <p style="color: #9ca3af; font-size: 14px;">Try changing your search terms or filter.</p>
    </div>
<?php else: ?>
    <div class="course-grid">
        <?php foreach ($courses as $course): ?>
            <?php 
                $isEnrolled = in_array($course['id'], $enrolledCourseIds);
                $badgeClass = htmlspecialchars($course['badge_color'] ?: 'web');
            ?>
            <div class="course-card">
                <div class="course-image <?= $badgeClass ?>">
                    <?= htmlspecialchars($course['badge_tag']) ?>
                </div>

                <div class="course-content">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="category"><?= htmlspecialchars($course['category']) ?></span>
                        <?php if ($isEnrolled): ?>
                            <span style="font-size: 11px; background: #ecfdf5; color: #065f46; padding: 2px 8px; border-radius: 12px; font-weight: 600;">Enrolled</span>
                        <?php endif; ?>
                    </div>

                    <h3><?= htmlspecialchars($course['title']) ?></h3>
                    <p><?= htmlspecialchars($course['description']) ?></p>

                    <div class="course-info">
                        <span><?= (int)$course['lesson_count'] ?> Lessons</span>
                        <span><?= htmlspecialchars($course['level']) ?></span>
                    </div>

                    <a href="course_view.php?id=<?= $course['id'] ?>" class="course-btn">
                        <?= $isEnrolled ? 'Continue Course &rarr;' : 'View Course &rarr;' ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
