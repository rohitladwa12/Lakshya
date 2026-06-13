<?php
/**
 * Student - Browse Jobs
 */
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();

$jobModel = new JobPosting();
$applicationModel = new JobApplication();

$jobs = $jobModel->getJobsForStudent($userId);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Jobs - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #800000;
            --brand-dark: #5b1f1f;
            --brand-grad: linear-gradient(135deg, #800000 0%, #a52a2a 100%);
            --brand-light: #fff5f5;
            --green: #059669;
            --green-light: #ecfdf5;
            --amber: #d97706;
            --amber-light: #fffbeb;
            --blue: #2563eb;
            --blue-light: #eff6ff;
            --text-dark: #0f172a;
            --text-mid: #475569;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --surface: #ffffff;
            --bg: #f1f5f9;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 12px 32px rgba(128, 0, 0, 0.12);
            --radius: 16px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text-dark);
            padding-top: 80px;
            min-height: 100vh;
        }

        /* ── Page Shell ─────────────────────────────────── */
        .page-wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 36px 24px 60px;
        }

        /* ── Hero Header ─────────────────────────────────── */
        .page-hero {
            background: var(--brand-grad);
            border-radius: 24px;
            padding: 36px 40px;
            margin-bottom: 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }

        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='30'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
        }

        .page-hero-text {
            position: relative;
        }

        .page-hero-text h1 {
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .page-hero-text p {
            color: rgba(255, 255, 255, 0.75);
            font-size: 15px;
        }

        .page-hero-stat {
            position: relative;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 20px 28px;
            text-align: center;
            color: #fff;
            min-width: 140px;
        }

        .page-hero-stat .stat-num {
            font-size: 32px;
            font-weight: 800;
            line-height: 1;
        }

        .page-hero-stat .stat-lbl {
            font-size: 12px;
            opacity: 0.75;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── Filter Strip ─────────────────────────────────── */
        .filter-strip {
            display: flex;
            gap: 10px;
            margin-bottom: 28px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-btn {
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--text-mid);
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn.active,
        .filter-btn:hover {
            border-color: var(--brand);
            background: var(--brand-light);
            color: var(--brand);
        }

        .filter-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--brand);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            margin-left: 4px;
        }

        /* ── Grid ────────────────────────────────────────── */
        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 20px;
        }

        /* ── Card ────────────────────────────────────────── */
        .job-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: box-shadow 0.25s, transform 0.25s;
            cursor: pointer;
            position: relative;
        }

        .job-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-4px);
        }

        .job-card.ineligible {
            opacity: 0.75;
        }

        .job-card.ineligible:hover {
            opacity: 1;
        }

        /* Accent stripe on left */
        .job-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--brand-grad);
            border-radius: 4px 0 0 4px;
        }

        .job-card.ineligible::before {
            background: #cbd5e1;
        }

        .job-card.applied::before {
            background: linear-gradient(135deg, #059669, #10b981);
        }

        .card-body {
            padding: 22px 22px 16px 26px;
            flex: 1;
        }

        /* Top row: logo + title + badge */
        .card-top {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }

        .co-avatar {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--brand-light);
            border: 1.5px solid rgba(128, 0, 0, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
            color: var(--brand);
            letter-spacing: -1px;
        }

        .card-title-block {
            flex: 1;
            min-width: 0;
        }

        .card-title-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
        }

        .c-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .c-company {
            font-size: 13px;
            color: var(--text-mid);
            font-weight: 500;
            margin-top: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Eligibility badge */
        .elig-badge {
            flex-shrink: 0;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }

        .elig-badge.yes {
            background: var(--green-light);
            color: var(--green);
            border: 1px solid #a7f3d0;
        }

        .elig-badge.no {
            background: var(--amber-light);
            color: var(--amber);
            border: 1px solid #fde68a;
        }

        .elig-badge.done {
            background: var(--blue-light);
            color: var(--blue);
            border: 1px solid #bfdbfe;
        }

        /* Meta chips */
        .chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-bottom: 14px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
            background: var(--bg);
            color: var(--text-mid);
            border: 1px solid var(--border);
        }

        .chip i {
            font-size: 10px;
            color: var(--text-muted);
        }

        /* Salary */
        .salary-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .salary-val {
            font-size: 18px;
            font-weight: 800;
            color: var(--brand);
        }

        .salary-sub {
            font-size: 12px;
            color: var(--text-muted);
        }

        .salary-dot {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--text-muted);
        }

        /* SGPA requirement chip */
        .req-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            background: var(--brand-light);
            color: var(--brand);
            border: 1px solid rgba(128, 0, 0, 0.15);
        }

        /* Description snippet */
        .c-desc {
            font-size: 13px;
            color: var(--text-mid);
            line-height: 1.65;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 12px;
        }

        /* Ineligibility reasons */
        .inelig-reasons {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 12px;
            color: #92400e;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .inelig-reasons i {
            color: #d97706;
            margin-right: 4px;
        }

        /* Card footer */
        .card-footer {
            border-top: 1px solid var(--border);
            padding: 14px 22px 14px 26px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            background: #fafbfc;
        }

        .deadline-text {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .deadline-text .days-left {
            display: inline-block;
            font-weight: 700;
            margin-left: 4px;
        }

        .days-left.urgent {
            color: #dc2626;
        }

        .days-left.soon {
            color: #d97706;
        }

        .days-left.ok {
            color: var(--green);
        }

        .action-row {
            display: flex;
            gap: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--brand-grad);
            color: #fff;
        }

        .btn-primary:hover {
            box-shadow: 0 6px 18px rgba(128, 0, 0, 0.3);
            transform: translateY(-1px);
        }

        .btn-ghost {
            background: var(--bg);
            color: var(--text-mid);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover {
            background: var(--border);
            color: var(--text-dark);
        }

        .btn-applied {
            background: var(--green-light);
            color: var(--green);
            border: 1px solid #a7f3d0;
            cursor: default;
        }

        .btn-locked {
            background: #f1f5f9;
            color: var(--text-muted);
            cursor: not-allowed;
        }

        /* ── Empty state ─────────────────────────────────── */
        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 80px 20px;
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }

        .empty-icon {
            font-size: 56px;
            margin-bottom: 18px;
            opacity: 0.25;
        }

        .empty-state h3 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--text-muted);
        }

        /* ── Responsive ─────────────────────────────────── */
        @media (max-width: 640px) {
            .page-hero {
                flex-direction: column;
                padding: 24px;
            }

            .jobs-grid {
                grid-template-columns: 1fr;
            }

            .page-hero-stat {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="page-wrap">

        <!-- Hero Header -->
        <div class="page-hero">
            <div class="page-hero-text">
                <h1><i class="fas fa-briefcase" style="margin-right:10px;opacity:.85;"></i>Job Opportunities</h1>
                <p>Curated roles matched to your profile — apply, prepare, and get placed.</p>
            </div>
            <div class="page-hero-stat">
                <div class="stat-num"><?php echo count($jobs); ?></div>
                <div class="stat-lbl">Open Roles</div>
            </div>
        </div>

        <!-- Filter Strip -->
        <?php
        $eligibleCount = count(array_filter($jobs, fn($j) => $j['is_eligible']));
        $appliedCount = count(array_filter($jobs, fn($j) => $j['has_applied']));
        ?>
        <div class="filter-strip">
            <button class="filter-btn active" onclick="filterCards('all', this)">
                All <span class="filter-count"><?php echo count($jobs); ?></span>
            </button>
            <button class="filter-btn" onclick="filterCards('eligible', this)">
                <i class="fas fa-check-circle" style="font-size:11px;color:#059669;"></i>
                Eligible <span class="filter-count" style="background:#059669;"><?php echo $eligibleCount; ?></span>
            </button>
            <button class="filter-btn" onclick="filterCards('applied', this)">
                <i class="fas fa-paper-plane" style="font-size:11px;color:#2563eb;"></i>
                Applied <span class="filter-count" style="background:#2563eb;"><?php echo $appliedCount; ?></span>
            </button>
        </div>

        <!-- Jobs Grid -->
        <div class="jobs-grid" id="jobsGrid">
            <?php if (empty($jobs)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-briefcase"></i></div>
                    <h3>No Opportunities Right Now</h3>
                    <p>New jobs will appear here as placement officers post them. Check back soon!</p>
                </div>
            <?php else: ?>
                <?php foreach ($jobs as $job):
                    $daysLeft = (int) ceil((strtotime($job['application_deadline']) - time()) / 86400);
                    $urgency = $daysLeft <= 3 ? 'urgent' : ($daysLeft <= 7 ? 'soon' : 'ok');
                    $initials = strtoupper(substr($job['company_name'], 0, 2));
                    $hasSalary = !empty($job['salary_min']) && !empty($job['salary_max']);

                    $cardClass = 'job-card';
                    if ($job['has_applied'])
                        $cardClass .= ' applied';
                    elseif (!$job['is_eligible'])
                        $cardClass .= ' ineligible';

                    $dataAttr = '';
                    if ($job['has_applied'])
                        $dataAttr = 'data-filter="applied eligible"';
                    elseif ($job['is_eligible'])
                        $dataAttr = 'data-filter="eligible"';
                    else
                        $dataAttr = 'data-filter="ineligible"';
                    ?>
                    <div class="<?php echo $cardClass; ?>" <?php echo $dataAttr; ?>
                        onclick="window.location.href='job_details.php?id=<?php echo $job['id']; ?>'">

                        <div class="card-body">
                            <div class="card-top">
                                <div class="co-avatar"
                                    style="overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                    <?php if (!empty($job['company_logo'])): ?>
                                        <img src="<?php echo (strpos($job['company_logo'], 'http') === 0) ? htmlspecialchars($job['company_logo']) : APP_URL . '/uploads/company_images/' . htmlspecialchars($job['company_logo']); ?>"
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo $initials; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="card-title-block">
                                    <div class="card-title-row">
                                        <div class="c-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                        <?php if ($job['has_applied']): ?>
                                            <span class="elig-badge done"><i class="fas fa-check"></i> Applied</span>
                                        <?php elseif ($job['is_eligible']): ?>
                                            <span class="elig-badge yes"><i class="fas fa-circle-check"></i> Eligible</span>
                                        <?php else: ?>
                                            <span class="elig-badge no"><i class="fas fa-lock"></i> Restricted</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="c-company"><i class="fas fa-building"
                                            style="font-size:10px;margin-right:4px;"></i><?php echo htmlspecialchars($job['company_name']); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Meta Chips -->
                            <div class="chip-row">
                                <?php if (!empty($job['location'])): ?>
                                    <span class="chip"><i class="fas fa-location-dot"></i>
                                        <?php echo htmlspecialchars($job['location']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['job_type'])): ?>
                                    <span class="chip"><i class="fas fa-briefcase"></i>
                                        <?php echo htmlspecialchars($job['job_type']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['work_mode'])): ?>
                                    <span class="chip"><i class="fas fa-laptop-house"></i>
                                        <?php echo htmlspecialchars($job['work_mode']); ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Salary + SGPA -->
                            <div class="salary-row">
                                <?php if ($hasSalary): ?>
                                    <span class="salary-val">₹<?php echo number_format($job['salary_min'] / 100000, 1); ?>L –
                                        ₹<?php echo number_format($job['salary_max'] / 100000, 1); ?>L</span>
                                    <span class="salary-sub">per year</span>
                                    <div class="salary-dot"></div>
                                <?php endif; ?>
                                <?php if (!empty($job['min_cgpa']) && $job['min_cgpa'] > 0): ?>
                                    <span class="req-chip"><i class="fas fa-graduation-cap"></i> Min SGPA
                                        <?php echo $job['min_cgpa']; ?>+</span>
                                <?php endif; ?>
                            </div>

                            <!-- Description -->
                            <?php if (!empty($job['description'])): ?>
                                <div class="c-desc"><?php echo htmlspecialchars($job['description']); ?></div>
                            <?php endif; ?>

                            <!-- Ineligibility reasons -->
                            <?php if (!$job['is_eligible'] && !empty($job['ineligibility_reasons'])): ?>
                                <div class="inelig-reasons">
                                    <i class="fas fa-triangle-exclamation"></i>
                                    <?php echo htmlspecialchars(implode(' · ', array_slice($job['ineligibility_reasons'], 0, 2))); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Footer -->
                        <div class="card-footer" onclick="event.stopPropagation()">
                            <div class="deadline-text">
                                <i class="far fa-clock" style="margin-right:4px;"></i>
                                <?php echo date('d M Y', strtotime($job['application_deadline'])); ?>
                                <?php if ($daysLeft >= 0): ?>
                                    <span class="days-left <?php echo $urgency; ?>">
                                        (<?php echo $daysLeft === 0 ? 'Today!' : $daysLeft . 'd left'; ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="days-left urgent">(Closed)</span>
                                <?php endif; ?>
                            </div>
                            <div class="action-row">
                                <!--<a href="company_ai_prep.php?job_id=<?php echo $job['id']; ?>" class="btn btn-ghost" onclick="event.stopPropagation()">
                                <i class="fas fa-robot"></i> Prep
                            </a> -->
                                <?php if ($job['has_applied']): ?>
                                    <button class="btn btn-applied" disabled>
                                        <i class="fas fa-check"></i> Applied
                                    </button>
                                <?php elseif (!$job['is_eligible']): ?>
                                    <button class="btn btn-locked" disabled>
                                        <i class="fas fa-lock"></i> Ineligible
                                    </button>
                                <?php else: ?>
                                    <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-primary"
                                        onclick="event.stopPropagation()">
                                        <i class="fas fa-arrow-right"></i> Apply Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterCards(filter, btn) {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            document.querySelectorAll('#jobsGrid .job-card').forEach(card => {
                if (filter === 'all') {
                    card.style.display = '';
                } else {
                    const f = card.dataset.filter || '';
                    card.style.display = f.includes(filter) ? '' : 'none';
                }
            });
        }
    </script>
</body>

</html>