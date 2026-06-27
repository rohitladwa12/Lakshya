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
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
        }

        /* ── Card ────────────────────────────────────────── */
        .job-card {
            background: var(--surface);
            border-radius: 18px;
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
        .job-card.ineligible { opacity: 0.7; }
        .job-card.ineligible:hover { opacity: 1; }

        /* ── Card Banner ─────────────────────────────────── */
        .card-banner {
            height: 150px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .card-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='30'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
        }
        .card-banner .banner-logo {
            position: relative;
            z-index: 1;
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            font-size: 28px;
            font-weight: 800;
            color: var(--brand);
            letter-spacing: -1px;
            overflow: hidden;
        }
        .card-banner .banner-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .card-banner .banner-status {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .banner-status.active { background: #dcfce7; color: #15803d; }
        .banner-status.ended { background: #fee2e2; color: #b91c1c; }
        .banner-status.applied { background: #dbeafe; color: #1d4ed8; }
        .banner-status.ineligible { background: #fef3c7; color: #92400e; }

        /* ── Card Body ───────────────────────────────────── */
        .card-body {
            padding: 16px 18px 10px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .type-badge {
            display: inline-block;
            background: #fef08a;
            color: #854d0e;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 10px;
            align-self: flex-start;
        }
        .c-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.3;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .c-company {
            font-size: 13px;
            color: var(--text-mid);
            font-weight: 500;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .c-company i { font-size: 11px; color: var(--text-muted); }

        /* Meta info row */
        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 10px 0 12px;
            font-size: 12px;
            color: var(--text-mid);
        }
        .meta-row span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .meta-row i { font-size: 11px; color: var(--text-muted); }

        /* Salary highlight */
        .salary-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        .salary-val {
            font-size: 16px;
            font-weight: 800;
            color: var(--brand);
        }
        .salary-sub {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* ── Status Box ──────────────────────────────────── */
        .job-status-box {
            border-radius: 12px;
            padding: 12px 14px;
            margin: auto 0 10px;
            border: 1px solid var(--border);
        }
        .job-status-box .status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .status-header .lbl {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
        }
        .status-header .status-tag {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 6px;
        }
        .status-tag.active { background: #dcfce7; color: #15803d; }
        .status-tag.ended { background: #fee2e2; color: #b91c1c; }
        .status-tag.applied { background: #dbeafe; color: #1d4ed8; }
        .status-tag.restricted { background: #fef3c7; color: #92400e; }

        .stat-columns {
            display: flex;
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }
        .stat-col {
            flex: 1;
            text-align: center;
            padding: 10px 8px;
            background: #f8fafc;
        }
        .stat-col + .stat-col {
            border-left: 1px solid var(--border);
        }
        .stat-col .col-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .stat-col .col-value {
            font-size: 18px;
            font-weight: 800;
        }
        .col-value.text-brand { color: var(--brand); }
        .col-value.text-green { color: #059669; }

        /* ── Card Footer / Action ────────────────────────── */
        .card-footer {
            padding: 12px 18px;
            border-top: 1px solid var(--border);
            background: #fafbfc;
        }
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: var(--brand-grad);
            color: #fff;
        }
        .btn-primary:hover {
            box-shadow: 0 6px 18px rgba(128, 0, 0, 0.3);
            transform: translateY(-1px);
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
            border: 1px solid var(--border);
            cursor: not-allowed;
        }

        /* Ineligibility reasons */
        .inelig-reasons {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 11px;
            color: #92400e;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        .inelig-reasons i { color: #d97706; margin-right: 4px; }

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
        $endedCount = 0;
        $eligibleCount = 0;
        $appliedCount = 0;
        foreach ($jobs as $j) {
            $dl = (int) ceil((strtotime($j['application_deadline']) - time()) / 86400);
            $ended = ($j['status'] === 'Closed' || $dl < 0);
            if ($ended) $endedCount++;
            if ($j['is_eligible'] && !$ended) $eligibleCount++;
            if ($j['has_applied']) $appliedCount++;
        }
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
            <button class="filter-btn" onclick="filterCards('ended', this)">
                <i class="fas fa-calendar-times" style="font-size:11px;color:#64748b;"></i>
                Ended <span class="filter-count" style="background:#64748b;"><?php echo $endedCount; ?></span>
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
                    $initials = strtoupper(substr($job['company_name'], 0, 2));
                    $hasSalary = !empty($job['salary_min']) && !empty($job['salary_max']);

                    $cardClass = 'job-card';
                    $isEnded = ($job['status'] === 'Closed' || $daysLeft < 0);
                    if ($job['has_applied'])
                        $cardClass .= ' applied';
                    elseif ($isEnded)
                        $cardClass .= ' ineligible';
                    elseif (!$job['is_eligible'])
                        $cardClass .= ' ineligible';

                    $dataAttrStr = '';
                    if ($isEnded)
                        $dataAttrStr .= ' ended';
                    if ($job['has_applied'])
                        $dataAttrStr .= ' applied';
                    elseif ($job['is_eligible'] && !$isEnded)
                        $dataAttrStr .= ' eligible';
                    else
                        $dataAttrStr .= ' ineligible';
                    
                    $dataAttr = 'data-filter="'.trim($dataAttrStr).'"';

                    // Status info
                    if ($job['has_applied']) {
                        $statusText = 'Applied';
                        $statusClass = 'applied';
                    } elseif ($isEnded) {
                        $statusText = 'Ended';
                        $statusClass = 'ended';
                    } elseif ($job['is_eligible']) {
                        $statusText = 'Active';
                        $statusClass = 'active';
                    } else {
                        $statusText = 'Restricted';
                        $statusClass = 'ineligible';
                    }

                    $deadlineFormatted = date('d M Y', strtotime($job['application_deadline']));
                    $salaryDisplay = $hasSalary ? '₹' . number_format($job['salary_min'] / 100000, 1) . 'L – ₹' . number_format($job['salary_max'] / 100000, 1) . 'L' : 'N/A';
                    $cgpaDisplay = (!empty($job['min_cgpa']) && $job['min_cgpa'] > 0) ? $job['min_cgpa'] . '+' : 'Any';
                    ?>
                    <div class="<?php echo $cardClass; ?>" <?php echo $dataAttr; ?>
                        onclick="window.location.href='job_details.php?id=<?php echo $job['id']; ?>'">

                        <!-- Banner with Logo -->
                        <div class="card-banner">
                            <div class="banner-logo">
                                <?php if (!empty($job['company_logo'])): ?>
                                    <img src="<?php echo (strpos($job['company_logo'], 'http') === 0) ? htmlspecialchars($job['company_logo']) : APP_URL . '/uploads/company_images/' . htmlspecialchars($job['company_logo']); ?>" alt="Logo">
                                <?php else: ?>
                                    <?php echo $initials; ?>
                                <?php endif; ?>
                            </div>
                            <span class="banner-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </div>

                        <div class="card-body">
                            <!-- Type Badge -->
                            <span class="type-badge"><?php echo htmlspecialchars($job['job_type'] ?: 'Full-Time'); ?></span>

                            <!-- Title -->
                            <div class="c-title"><?php echo htmlspecialchars($job['title']); ?></div>

                            <!-- Company -->
                            <div class="c-company">
                                <i class="fas fa-building"></i>
                                <?php echo htmlspecialchars($job['company_name']); ?>
                            </div>

                            <!-- Meta Info Row -->
                            <div class="meta-row">
                                <?php if ($hasSalary): ?>
                                    <span><i class="fas fa-indian-rupee-sign"></i> <?php echo $salaryDisplay; ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['location'])): ?>
                                    <span><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['work_mode'])): ?>
                                    <span><i class="fas fa-laptop-house"></i> <?php echo htmlspecialchars($job['work_mode']); ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Ineligibility reasons -->
                            <?php if (!$job['is_eligible'] && !empty($job['ineligibility_reasons'])): ?>
                                <div class="inelig-reasons">
                                    <i class="fas fa-triangle-exclamation"></i>
                                    <?php echo htmlspecialchars(implode(' · ', array_slice($job['ineligibility_reasons'], 0, 2))); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Status Box -->
                            <div class="job-status-box">
                                <div class="status-header">
                                    <span class="lbl">Job Status</span>
                                    <span class="status-tag <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </div>
                                <div class="stat-columns">
                                    <div class="stat-col">
                                        <div class="col-label">Min SGPA</div>
                                        <div class="col-value text-brand"><?php echo $cgpaDisplay; ?></div>
                                    </div>
                                    <div class="stat-col">
                                        <div class="col-label">Deadline</div>
                                        <div class="col-value text-green" style="font-size:13px;"><?php echo $deadlineFormatted; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Action -->
                        <div class="card-footer" onclick="event.stopPropagation()">
                            <?php if ($job['has_applied']): ?>
                                <button class="btn btn-applied" disabled>
                                    <i class="fas fa-check"></i> Applied Successfully
                                </button>
                            <?php elseif ($isEnded): ?>
                                <button class="btn btn-locked" disabled>
                                    <i class="fas fa-calendar-times"></i> Application Closed
                                </button>
                            <?php elseif (!$job['is_eligible']): ?>
                                <button class="btn btn-locked" disabled>
                                    <i class="fas fa-lock"></i> Not Eligible
                                </button>
                            <?php else: ?>
                                <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-primary"
                                    onclick="event.stopPropagation()">
                                    <i class="fas fa-arrow-right"></i> Apply Now
                                </a>
                            <?php endif; ?>
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
                    const filters = (card.dataset.filter || '').split(' ');
                    card.style.display = filters.includes(filter) ? '' : 'none';
                }
            });
        }
    </script>
</body>

</html>