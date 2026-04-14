<?php
/**
 * Learning Modules Management - Admin View
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/LearningChapter.php';
require_once __DIR__ . '/../../src/Models/LearningModule.php';

// Require admin role
requireRole(ROLE_ADMIN);

$fullName = getFullName();
$chapterModel = new LearningChapter();
$moduleModel = new LearningModule();

// Fetch data
$chapters = $chapterModel->all('display_order ASC');
$allModules = $moduleModel->all('chapter_id ASC, display_order ASC');

// Group modules by chapter
$chapterModules = [];
foreach ($allModules as $m) {
    $chapterModules[$m['chapter_id']][] = $m;
}

// Handle Quick Actions (Converted to POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'active' && isset($_POST['type']) && isset($_POST['id'])) {
    $id = $_POST['id'];
    $type = $_POST['type'];
    if ($type === 'chapter') {
        $item = $chapterModel->find($id);
        if ($item) $chapterModel->update($id, ['is_active' => !$item['is_active']]);
    } else {
        $item = $moduleModel->find($id);
        if ($item) $moduleModel->update($id, ['is_active' => !$item['is_active']]);
    }
    header("Location: learning.php?success=Status updated");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Management - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --accent-blue: #4318ff;
            --bg-color: #f4f7fe;
            --white: #ffffff;
            --text-dark: #2b3674;
            --text-muted: #a3aed1;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --shadow: 0 20px 40px rgba(0,0,0,0.05);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg-color); display: flex; min-height: 100vh; color: var(--text-dark); }

        .main-content { flex: 1; padding: 40px; width: 100%; max-width: 1400px; margin: 0 auto; }

        .glass-header {
            background: var(--glass-bg); backdrop-filter: blur(10px);
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; padding: 25px 35px; border-radius: 30px;
            box-shadow: var(--shadow); border: 1px solid rgba(255,255,255,0.3);
        }

        .header-title h1 {
            font-size: 26px; font-weight: 800; letter-spacing: -1px;
            background: linear-gradient(135deg, var(--primary-maroon), var(--accent-blue));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        .btn-add { background: linear-gradient(135deg, var(--primary-maroon), var(--primary-dark)); color: white; padding: 12px 24px; border-radius: 15px; text-decoration: none; font-weight: 700; box-shadow: 0 10px 20px rgba(128,0,0,0.2); display: flex; align-items: center; gap: 8px; }

        /* Chapter List */
        .chapter-card {
            background: var(--white); border-radius: 25px; padding: 30px; margin-bottom: 30px; box-shadow: var(--shadow);
            border: 1px solid transparent; transition: var(--transition);
        }
        .chapter-card:hover { border-color: var(--primary-gold); }

        .chapter-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #F4F7FE; }
        .chapter-info h2 { font-size: 20px; font-weight: 800; color: var(--text-dark); }
        .chapter-info p { font-size: 14px; color: var(--text-muted); margin-top: 5px; }

        .module-list { display: grid; gap: 15px; }
        .module-item {
            background: #F8FAFF; border-radius: 15px; padding: 15px 20px;
            display: flex; justify-content: space-between; align-items: center;
            border: 1px solid #E0E5F2; transition: var(--transition);
        }
        .module-item:hover { transform: translateX(5px); background: #FFF; border-color: var(--accent-blue); }

        .module-main { display: flex; align-items: center; gap: 15px; }
        .module-icon { width: 40px; height: 40px; border-radius: 10px; background: #E9EDFE; color: #4318FF; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .module-name { font-weight: 700; font-size: 15px; }
        .module-meta { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        .badge { padding: 4px 10px; border-radius: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .badge-active { background: #E2F9F2; color: #05CD99; }
        .badge-inactive { background: #fee2e2; color: #ef4444; }

        .actions { display: flex; gap: 10px; }
        .btn-action { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; color: var(--text-muted); text-decoration: none; border: 1px solid #E0E5F2; transition: var(--transition); }
        .btn-action:hover { background: var(--bg-color); color: var(--primary-maroon); border-color: var(--primary-maroon); }

        .empty-state { text-align: center; padding: 40px; color: var(--text-muted); font-style: italic; }
        .back-link { color: var(--text-muted); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <header class="glass-header">
            <div class="header-title">
                <h1>Academic Curriculum</h1>
                <p>Organize lessons, video lectures, and study materials</p>
            </div>
            <a href="#" class="btn-add"><i class="fas fa-folder-plus"></i> New Chapter</a>
        </header>

        <?php if (empty($chapters)): ?>
            <div class="panel empty-state">No learning chapters found. Start by creating a new one.</div>
        <?php endif; ?>

        <?php foreach ($chapters as $chapter): ?>
            <div class="chapter-card">
                <div class="chapter-header">
                    <div class="chapter-info">
                        <h2>Chapter <?php echo $chapter['display_order']; ?>: <?php echo htmlspecialchars($chapter['title']); ?></h2>
                        <p><?php echo htmlspecialchars($chapter['description']); ?></p>
                    </div>
                    <div class="actions">
                        <span class="badge <?php echo $chapter['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo $chapter['is_active'] ? 'Public' : 'Hidden'; ?>
                        </span>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="active">
                            <input type="hidden" name="type" value="chapter">
                            <input type="hidden" name="id" value="<?php echo $chapter['id']; ?>">
                            <button type="submit" class="btn-action" title="Toggle Visibility"><i class="fas fa-eye"></i></button>
                        </form>
                        <a href="#" class="btn-action"><i class="fas fa-edit"></i></a>
                        <a href="#" class="btn-action"><i class="fas fa-plus"></i></a>
                    </div>
                </div>

                <div class="module-list">
                    <?php if (empty($chapterModules[$chapter['id']])): ?>
                        <div class="empty-state" style="padding: 15px; font-size: 13px;">No lessons added to this chapter yet.</div>
                    <?php else: ?>
                        <?php foreach ($chapterModules[$chapter['id']] as $mod): ?>
                            <div class="module-item">
                                <div class="module-main">
                                    <div class="module-icon">
                                        <i class="fas <?php echo !empty($mod['video_url']) ? 'fa-play-circle' : 'fa-file-alt'; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="module-name"><?php echo htmlspecialchars($mod['title']); ?></div>
                                        <div class="module-meta">
                                            <?php echo !empty($mod['video_url']) ? 'Video Lecture' : 'PDF Study Guide'; ?> 
                                            <?php if (!$mod['is_active']): ?> • <span style="color: #ef4444; font-weight: 700;">DRAFT</span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="actions">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="active">
                                        <input type="hidden" name="type" value="module">
                                        <input type="hidden" name="id" value="<?php echo $mod['id']; ?>">
                                        <button type="submit" class="btn-action"><i class="fas fa-power-off"></i></button>
                                    </form>
                                    <a href="#" class="btn-action"><i class="fas fa-pen"></i></a>
                                    <a href="#" class="btn-action" style="color: #ef4444;"><i class="fas fa-trash-can"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
