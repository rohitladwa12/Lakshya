<?php
/**
 * Student - Browse Jobs
 * Simple page to browse and apply for jobs
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();

// Load models
$jobModel = new JobPosting();
$applicationModel = new JobApplication();

// Get jobs for this student
$jobs = $jobModel->getJobsForStudent($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Jobs - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --secondary-gold: #f7f3b7;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --medium-gray: #e0e0e0;
            --dark-gray: #333333;
            --gradient: linear-gradient(135deg, var(--primary-gold), var(--secondary-gold));
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light-gray);
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #666;
            font-size: 16px;
        }
        
        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .job-card {
            background: var(--white);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(128, 0, 0, 0.1);
        }
        
        .job-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .company-logo {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--primary-maroon);
        }
        
        .job-info {
            flex: 1;
        }
        
        .job-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }
        
        .company-name {
            color: #666;
            font-size: 14px;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .meta-tag {
            background: var(--light-gray);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: #555;
        }
        
        .job-salary {
            color: var(--primary-maroon);
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .job-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .job-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--medium-gray);
        }
        
        .deadline {
            font-size: 13px;
            color: #999;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
            color: var(--white);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128, 0, 0, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-maroon);
            border: 2px solid var(--primary-maroon);
        }

        .btn-outline:hover {
            background: var(--primary-maroon);
            color: white;
        }
        
        .btn-applied {
            background: #28a745;
            color: white;
            cursor: default;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-eligible {
            background: #e6f4ea;
            color: #1e7e34;
            border: 1px solid #c3e6cb;
        }

        .badge-ineligible {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .ineligibility-text {
            color: #d9534f;
            font-size: 12px;
            margin-top: 5px;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        
        .empty-state h3 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #666;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2>💼 Browse Job Opportunities</h2>
            <p>Find jobs that match your profile and skills</p>
        </div>
        
        <?php if (empty($jobs)): ?>
            <div class="empty-state">
                <h3>No Jobs Available</h3>
                <p>There are currently no job opportunities matching your profile. Check back later!</p>
            </div>
        <?php else: ?>
            <div class="jobs-grid">
                <?php foreach ($jobs as $job): ?>
                    <div class="job-card" onclick="window.location.href='job_details.php?id=<?php echo $job['id']; ?>'">
                        <div class="job-header">
                            <div class="company-logo">
                                <?php echo strtoupper(substr($job['company_name'], 0, 2)); ?>
                            </div>
                            <div class="job-info">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <?php if ($job['is_eligible']): ?>
                                        <span class="badge badge-eligible">Eligible</span>
                                    <?php else: ?>
                                        <span class="badge badge-ineligible">Not Eligible</span>
                                    <?php endif; ?>
                                </div>
                                <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></div>
                            </div>
                        </div>
                        
                        <div class="job-meta">
                            <span class="meta-tag">📍 <?php echo htmlspecialchars($job['location']); ?></span>
                            <span class="meta-tag">💼 <?php echo htmlspecialchars($job['job_type']); ?></span>
                            <span class="meta-tag">🏢 <?php echo htmlspecialchars($job['work_mode']); ?></span>
                        </div>
                        
                        <div class="job-salary">
                            ₹<?php echo number_format($job['salary_min']); ?> - ₹<?php echo number_format($job['salary_max']); ?> /year
                        </div>
                        
                        <div class="job-description">
                            <?php echo htmlspecialchars($job['description']); ?>
                        </div>

                        <?php if (!$job['is_eligible']): ?>
                            <div class="ineligibility-text">
                                ⚠️ <?php echo implode(', ', $job['ineligibility_reasons']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="job-footer">
                            <div class="deadline">
                                Deadline: <?php echo date('d M Y', strtotime($job['application_deadline'])); ?>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <a href="company_ai_prep.php?job_id=<?php echo $job['id']; ?>" class="btn btn-outline" onclick="event.stopPropagation()">
                                    Practice
                                </a>
                                <?php if ($job['has_applied']): ?>
                                    <button class="btn btn-applied" disabled>✓ Applied</button>
                                <?php elseif (!$job['is_eligible']): ?>
                                    <button class="btn btn-primary" style="opacity: 0.5; cursor: not-allowed;" disabled>
                                        Ineligible
                                    </button>
                                <?php else: ?>
                                    <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-primary" onclick="event.stopPropagation()">
                                        Apply Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

