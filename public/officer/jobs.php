<?php
/**
 * Job Management Page - Placement Officer
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$pageId = 'officer_jobs';

// Handle POST State (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a "view applications" redirect
    if (isset($_POST['view_apps'])) {
        SessionFilterHelper::setFilters('officer_applications', ['job_id' => $_POST['view_apps']]);
        header("Location: applications.php");
        exit;
    }

    SessionFilterHelper::handlePostToSession($pageId, $_POST);
    header("Location: jobs.php");
    exit;
}

// Retrieve from Session
$filters = SessionFilterHelper::getFilters($pageId);
$jobModel = new JobPosting();
$companyModel = new Company();

// Handle Search/Filters
$query = $filters['q'] ?? '';
$statusFilter = $filters['status'] ?? '';

if ($query) {
    $jobs = $jobModel->search($query);
} else {
    // Basic list for dashboard
    $sql = "SELECT jp.*, c.name as company_name 
            FROM job_postings jp 
            JOIN companies c ON jp.company_id = c.id";
    
    $params = [];
    if ($statusFilter) {
        $sql .= " WHERE jp.status = ?";
        $params[] = $statusFilter;
    }
    
    $sql .= " ORDER BY jp.posted_date DESC";
    $stmt = $jobModel->getDB()->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();
}

$companies = $companyModel->getActiveCompanies();

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --brand: #800000;
            --brand-light: #fff5f5;
            --brand-gradient: linear-gradient(135deg, #800000 0%, #a52a2a 100%);
            --glass: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.3);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: var(--text-dark);
            margin: 0;
            padding-top: 80px; /* Adjusted for new 70px navbar */
            line-height: 1.6;
        }

        .o-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .o-head {
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .o-head h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }

        /* Filter Glass */
        .filter-glass {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .search-container {
            flex: 1;
            position: relative;
        }

        .search-container i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .search-input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .search-input:focus {
            border-color: var(--brand);
            outline: none;
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.05);
        }

        .status-select {
            padding: 12px 15px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            font-size: 14px;
            font-weight: 600;
            background: #fff;
            min-width: 150px;
        }

        /* Table Design */
        .table-card {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }

        .modern-table th {
            text-align: left;
            padding: 18px 24px;
            background: #f8fafc;
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #e2e8f0;
        }

        .modern-table td {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .modern-table tr:hover td { background: rgba(128, 0, 0, 0.01); }

        .job-title { font-weight: 700; color: var(--text-dark); margin-bottom: 4px; }
        .comp-name { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .bg-active { background: #ecfdf5; color: #059669; }
        .bg-closed { background: #fef2f2; color: #dc2626; }
        .bg-draft { background: #f1f5f9; color: #475569; }

        /* Buttons */
        .btn-action {
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            box-sizing: border-box;
        }

        .btn-primary { background: var(--brand-gradient); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(128, 0, 0, 0.2); }
        .btn-view { background: var(--brand-light); color: var(--brand); font-size: 16px; padding: 0; width: 40px; height: 40px; justify-content: center; align-items: center; border-radius: 10px; box-sizing: border-box; }
        .btn-view:hover { background: var(--brand); color: white; }

        /* Modal Glass */
        #jobModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-glass {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            padding: 40px;
        }

        .modal-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            padding: 5px;
            background: #f1f5f9;
            border-radius: 15px;
        }

        .tab-btn {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background: transparent;
            color: var(--text-muted);
        }

        .tab-btn.active {
            background: white;
            color: var(--brand);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: var(--brand);
            outline: none;
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.05);
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 10px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .spoc-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="o-page">
        <div class="o-head">
            <div>
                <h1>Job Management</h1>
                <p style="color: var(--text-muted); font-size: 14px; margin-top: 5px;">Manage opportunities and hiring pipelines</p>
            </div>
            <button class="btn-action btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Post New Job
            </button>
        </div>

        <?php if ($msg = Session::flash('success')): ?>
            <div style="background: #e3fcef; color: #00875a; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                ✅ <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($msg = Session::flash('error')): ?>
            <div style="background: #ffebe6; color: #bf2600; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                ⚠️ <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="filter-glass">
            <form method="POST" style="display: flex; gap: 15px; flex: 1;">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search by title, company, or location..." class="search-input">
                </div>
                <select name="status" class="status-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $statusFilter == 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Closed" <?php echo $statusFilter == 'Closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="Draft" <?php echo $statusFilter == 'Draft' ? 'selected' : ''; ?>>Draft</option>
                </select>
            </form>
        </div>

        <div class="table-card">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Opportunity</th>
                        <th>Academic Year</th>
                        <th>Location</th>
                        <th>Requirements</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td>
                            <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                            <div class="comp-name"><i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?></div>
                        </td>
                        <td style="font-weight: 600; color: var(--text-dark);">
                            <?php echo htmlspecialchars($job['academic_year'] ?? 'N/A'); ?>
                        </td>
                        <td style="color: var(--text-muted); font-weight: 500;">
                            <i class="fas fa-map-marker-alt" style="font-size: 11px;"></i> <?php echo htmlspecialchars($job['location']); ?>
                        </td>
                        <td>
                            <div style="font-size: 13px; font-weight: 600;">SGPA: <?php echo $job['min_cgpa'] ?: 'Any'; ?>+</div>
                        </td>
                        <td>
                            <div style="font-size: 13px; color: #dc2626; font-weight: 700;">
                                <?php echo date('M d, Y', strtotime($job['application_deadline'])); ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo strtolower($job['status']); ?>">
                                <?php echo $job['status']; ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <form id="form_apps_<?php echo $job['id']; ?>" method="POST" style="display: none;">
                                <input type="hidden" name="view_apps" value="<?php echo $job['id']; ?>">
                            </form>
                            <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                <button class="btn-action btn-view" title="Edit Job" onclick='editJob(<?php echo json_encode($job, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php
                                $shareUrl = APP_URL . '/student/job_details.php?code=' . encryptJobId($job['id']);
                                $branches = json_decode($job['eligible_branches'] ?? '[]', true) ?: [];
                                $branchesStr = !empty($branches) ? implode(', ', $branches) : 'All Branches';

                                $salaryStr = 'Not Specified';
                                if (!empty($job['salary_min']) && !empty($job['salary_max'])) {
                                    $salaryStr = '₹' . number_format($job['salary_min'] / 100000, 1) . 'L – ₹' . number_format($job['salary_max'] / 100000, 1) . 'L per year';
                                }

                                $deadlineStr = date('M d, Y', strtotime($job['application_deadline']));

                                $waMessage = "*📢 New Placement Opportunity!*\n\n"
                                           . "*Company:* " . ($job['company_name'] ?? 'Company') . "\n"
                                           . "*Role:* " . $job['title'] . "\n"
                                           . "*Location:* " . $job['location'] . "\n"
                                           . "*Salary:* " . $salaryStr . "\n"
                                           . "*Min SGPA:* " . ($job['min_cgpa'] ?: 'Any') . "+\n"
                                           . "*Eligible Branches:* " . $branchesStr . "\n"
                                           . "*Deadline:* " . $deadlineStr . "\n\n"
                                           . "*Apply here:* " . $shareUrl . "\n\n"
                                           . "_Lakshya Placement Portal_";

                                $waUrl = "https://api.whatsapp.com/send?text=" . urlencode($waMessage);
                                ?>
                                <a href="<?php echo $waUrl; ?>" target="_blank" class="btn-action btn-view" title="Share on WhatsApp" style="background: #e8fbee; color: #128c7e;">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <a href="job_attendance.php?job_id=<?php echo $job['id']; ?>" class="btn-action btn-view" title="Take Attendance" style="background: #fdf2f8; color: #db2777;">
                                    <i class="fas fa-user-check"></i>
                                </a>
                                <button type="submit" form="form_apps_<?php echo $job['id']; ?>" class="btn-action btn-view" title="Applications" style="background: #eff6ff; color: #2563eb;">
                                    <i class="fas fa-users"></i>
                                </button>
                                <?php if ($job['status'] !== 'Closed'): ?>
                                <button class="btn-action btn-view" title="Close" style="background: #fff1f2; color: #e11d48;" onclick="closeJob(<?php echo $job['id']; ?>)">
                                    <i class="fas fa-lock"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; if (empty($jobs)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 60px; color: var(--text-muted);">
                            <i class="fas fa-briefcase" style="font-size: 48px; opacity: 0.1; margin-bottom: 20px; display: block;"></i>
                            No job postings found matching your criteria.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Job Modal -->
    <div id="jobModal">
        <div class="modal-glass">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
                <div>
                    <h2 style="font-weight: 800; color: var(--brand); margin: 0;" id="modalTitle">Post Opportunity</h2>
                    <p style="color: var(--text-muted); font-size: 13px;">Fill in the details for the new job posting</p>
                </div>
                <button type="button" class="btn-action btn-view" onclick="closeModal()" style="font-size: 24px; background: transparent;">&times;</button>
            </div>
            
            <div class="modal-tabs">
                <button type="button" class="tab-btn active" id="tab-company-details" onclick="showTab('company-details')">1. Company</button>
                <button type="button" class="tab-btn" id="tab-job-details" onclick="showTab('job-details')">2. Opportunity</button>
                <button type="button" class="tab-btn" id="tab-spoc-details" onclick="showTab('spoc-details')">3. Contacts</button>
                <button type="button" class="tab-btn" id="tab-custom-questions" onclick="showTab('custom-questions')">4. Custom</button>
            </div>

            <form id="jobForm" method="POST" action="job_handler" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="job_id" id="jobId">

                <!-- Tab 1: Company Details -->
                <div id="company-details" class="tab-content active">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Official Company Name</label>
                            <input type="text" name="company_name" id="companyName" class="form-control" placeholder="Required">
                        </div>
                        <div class="form-group">
                            <label>Industry Vertical</label>
                            <input type="text" name="company_sector" id="companySector" class="form-control" placeholder="e.g. SaaS / AI">
                        </div>
                        <div class="form-group">
                            <label>Headquarters</label>
                            <input type="text" name="company_district" id="companyDistrict" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Corporate Website</label>
                            <input type="url" name="company_website" id="companyWebsite" class="form-control" placeholder="https://">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Brief Company Pitch</label>
                            <textarea name="company_description" id="companyDescription" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Company Logo / Image</label>
                            <input type="file" name="company_logo" id="companyLogo" class="form-control" accept="image/*">
                            <div id="companyLogoPreviewContainer" style="margin-top: 10px; display: none; align-items: center; gap: 10px;">
                                <img id="companyLogoPreview" src="" alt="Company Logo" style="max-height: 50px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                <span style="font-size: 12px; color: var(--text-muted);">Current Logo</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Job Details -->
                <div id="job-details" class="tab-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Academic Year</label>
                            <input type="text" name="academic_year" id="academicYear" class="form-control" required placeholder="e.g. 2025-26">
                        </div>
                        <div class="form-group">
                            <label>Link to Company</label>
                            <input type="text" id="companySearch" class="form-control" list="existingCompanies" placeholder="Search saved companies..." oninput="onCompanySelect(this.value)">
                            <datalist id="existingCompanies">
                                <?php foreach ($companies as $company): ?>
                                <option value="<?php echo htmlspecialchars($company['name']); ?>" data-id="<?php echo $company['id']; ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" name="company_id" id="companyId">
                        </div>
                        <div class="form-group">
                            <label>Opportunity Title</label>
                            <input type="text" name="title" id="title" class="form-control" required placeholder="e.g. Associate SDE">
                        </div>
                        <div class="form-group">
                            <label>Principal Location</label>
                            <input type="text" name="location" id="location" class="form-control" required placeholder="e.g. Remote / Bangalore">
                        </div>
                        <div class="form-group">
                            <label>Engagement Type</label>
                            <select name="job_type" id="jobType" class="form-control">
                                <option value="Full-Time">Full-Time</option>
                                <option value="Internship">Internship</option>
                                <option value="Contract">Contract</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Work Mode</label>
                            <select name="work_mode" id="workMode" class="form-control">
                                <option value="On-Site">On-Site</option>
                                <option value="Remote">Remote</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Salary Range (Annual)</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" name="salary_min" id="salaryMin" class="form-control" placeholder="Min">
                                <input type="number" name="salary_max" id="salaryMax" class="form-control" placeholder="Max">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Minimum SGPA</label>
                            <input type="number" step="0.01" min="0" max="10" name="min_cgpa" id="minCgpa" class="form-control" required placeholder="e.g. 7.5">
                        </div>
                        <div class="form-group">
                            <label>Application Deadline</label>
                            <input type="date" name="application_deadline" id="deadline" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Job Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3" placeholder="Describe the role and what the candidate will work on..."></textarea>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Requirements / Qualifications</label>
                            <textarea name="requirements" id="requirements" class="form-control" rows="2" placeholder="e.g. Strong DSA, proficiency in Python..."></textarea>
                        </div>
                    </div>

                    <!-- Eligible Years -->
                    <div style="margin-top:20px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;">
                        <label style="display:block;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px;">Eligible Year of Study</label>
                        <div class="checkbox-group">
                            <label class="checkbox-item"><input type="checkbox" name="eligible_years[]" value="1" class="year-check"> 1st Year</label>
                            <label class="checkbox-item"><input type="checkbox" name="eligible_years[]" value="2" class="year-check"> 2nd Year</label>
                            <label class="checkbox-item"><input type="checkbox" name="eligible_years[]" value="3" class="year-check"> 3rd Year</label>
                            <label class="checkbox-item"><input type="checkbox" name="eligible_years[]" value="4" class="year-check"> 4th Year (Final)</label>
                        </div>
                    </div>

                    <!-- Branch Selector (flat 4-col row, outside form-grid) -->
                    <div style="margin-top:20px;padding:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;">
                        <label style="display:block;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:14px;">Eligible Branches</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end;">
                            <div>
                                <label style="font-size:11px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px;">1. Institution</label>
                                <select id="selInstitution" class="form-control" onchange="onInstChange()">
                                    <option value="GMIT">GMIT</option>
                                    <option value="GMU">GMU</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:11px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px;">2. Course Group</label>
                                <select id="selCourse" class="form-control" onchange="onCourseChange()">
                                    <option value="">Select Course</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:11px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px;">3. Sub-branch (Ctrl+click multi)</label>
                                <select id="selSubBranch" class="form-control" multiple style="height:110px;">
                                    <option value="">Select Course First</option>
                                </select>
                            </div>
                            <div style="padding-bottom:2px;">
                                <button type="button" class="btn-action btn-primary" style="width:100%;" onclick="addSelectedBranches()">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            </div>
                        </div>
                        <div style="margin-top:14px;min-height:36px;">
                            <div id="selectedBranchesTags" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
                            <div id="hiddenBranchesInputs"></div>
                            <p id="noBranchHint" style="font-size:12px;color:var(--text-muted);margin:6px 0 0;">No branches added yet — all students will be eligible.</p>
                        </div>
                    </div>

                </div>

                <!-- Tab 3: SPOC Details -->
                <div id="spoc-details" class="tab-content">
                    <div id="spocList"></div>
                    <button type="button" class="btn-action" style="width: 100%; border: 2px dashed #e2e8f0; color: var(--text-muted); margin-top: 20px;" onclick="addSpocRow()">
                        <i class="fas fa-plus"></i> Add Point of Contact
                    </button>
                </div>

                <!-- Tab 4: Custom Questions -->
                <div id="custom-questions" class="tab-content">
                    <div id="customQuestionsList"></div>
                    <button type="button" class="btn-action" style="width: 100%; border: 2px dashed #e2e8f0; color: var(--text-muted); margin-top: 20px;" onclick="addCustomQuestionRow()">
                        <i class="fas fa-plus"></i> Add Custom Field
                    </button>
                </div>

                <div style="margin-top: 40px; display: flex; justify-content: flex-end; gap: 12px; padding-top: 25px; border-top: 1px solid #f1f5f9;">
                    <button type="button" class="btn-action" style="background: #f1f5f9; color: var(--text-muted);" onclick="closeModal()">Cancel</button>
                    <button type="submit" id="submitBtn" class="btn-action btn-primary">
                        <span id="btnText">Save Opportunity</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('jobModal');
        const form = document.getElementById('jobForm');

        const HIERARCHY = {
            'GMIT': {
                'BE': ['CSE', 'AIML', 'ISE', 'ECE', 'EEE', 'MECH', 'CIVIL'],
                'MBA': ['MBA'],
                'MCA': ['MCA']
            },
            'GMU': {
                'BTECH': ['CSE', 'CSE-AIML', 'ISE', 'ECE', 'EEE', 'MECH', 'CIVIL', 'BT', 'AIDS', 'CSBS', 'DS', 'CSE-DS', 'IOT', 'CSE-IOT'],
                'MBA': ['MBA', 'MBA-ADV', 'MBA-AM', 'MBA-IB', 'MBA-IE', 'MBA-INTNL', 'MBA-PF'],
                'BBA': ['BBA', 'BBA-AI&BA', 'BBA-AM', 'BBA-B&F', 'BBA-BA', 'BBA-DM&E-COM', 'BBA-DMSM', 'BBA-GM', 'BBA-HM', 'BBA-HRM', 'BBA-IE', 'BBA-LSCM', 'BBA-MS', 'BBA-TH&EM'],
                'BCA': ['BCA', 'BCA-AIDA', 'BCA-CS', 'BCA-CY', 'BCA-DS', 'BCA-GENERAL'],
                'MCOM': ['MCOM', 'MCOM-ATFA', 'MCom-AFDB', 'MCom-FAE'],
                'MCA': ['MCA', 'MCA-AIDA', 'MCA-CY', 'MCA-DS'],
                'BCOM': ['BCOM', 'BCOM-A&T', 'BCOM-AF', 'BCOM-AI', 'BCOM-AT', 'BCOM-DA&BI', 'BCOM-F&A', 'BCOM-G'],
                'BSC': ['BSC', 'BSC-B&TE', 'BSC-BT', 'BSC-BZ', 'BSC-C,B', 'BSC-C,CS', 'BSC-C,Z', 'BSC-CB', 'BSC-CCS', 'BSC-CZ', 'BSC-E,SC', 'BSC-FS&T', 'BSC-FST', 'BSC-IM', 'BSC-M,CS', 'BSC-M,P', 'BSC-MB', 'BSC-MCS', 'BSC-P,C', 'BSC-PC', 'BSC-PM', 'BSC-S,CS', 'BSC-SCS'],
                'LLB': ['LLB', 'LLB-BBA', 'LLB-BCOM'],
                'MSC': ['MSC', 'MSC-AIDA', 'MSC-C', 'MSC-CY', 'MSC-DS', 'MSC-FT', 'MSC-M', 'MSC-P'],
                'MTECH': ['MTECH', 'MTECH-AE&ITS', 'MTECH-AIHC', 'MTECH-B&GT', 'MTECH-CASE', 'MTECH-DE', 'MTECH-DLDA', 'MTECH-PD&M', 'MTECH-S&GA', 'MTECH-SES&SE', 'MTECH-ST', 'MTech-IS&IOT'],
                'PHD': ['PHD', 'PhD-AIM', 'PhD-BOT', 'PhD-BT', 'PhD-CA', 'PhD-CHE', 'PhD-COM', 'PhD-CSE', 'PhD-CV', 'PhD-ECE', 'PhD-EEE', 'PhD-ISE', 'PhD-MAT', 'PhD-ME', 'PhD-MS', 'PhD-PHY', 'PhD-RA', 'PhD-ZOO']
            }
        };

        let selectedBranches = []; // Array of objects: { inst: 'GMIT', branch: 'CSE' }

        function onInstChange() {
            const inst = document.getElementById('selInstitution').value;
            const courseSelect = document.getElementById('selCourse');
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            
            if (HIERARCHY[inst]) {
                Object.keys(HIERARCHY[inst]).forEach(course => {
                    courseSelect.insertAdjacentHTML('beforeend', `<option value="${course}">${course}</option>`);
                });
            }
            onCourseChange();
        }

        function onCourseChange() {
            const inst = document.getElementById('selInstitution').value;
            const course = document.getElementById('selCourse').value;
            const subSelect = document.getElementById('selSubBranch');
            subSelect.innerHTML = '';
            
            if (HIERARCHY[inst] && HIERARCHY[inst][course]) {
                // Option to add whole course/parent branch
                subSelect.insertAdjacentHTML('beforeend', `<option value="${course}">${course} (All sub-branches)</option>`);
                
                HIERARCHY[inst][course].forEach(sub => {
                    if (sub !== course) {
                        subSelect.insertAdjacentHTML('beforeend', `<option value="${sub}">${sub}</option>`);
                    }
                });
            } else {
                subSelect.innerHTML = '<option value="">Select Course First</option>';
            }
        }

        function renderTags() {
            const tagsDiv = document.getElementById('selectedBranchesTags');
            const inputsDiv = document.getElementById('hiddenBranchesInputs');
            tagsDiv.innerHTML = '';
            inputsDiv.innerHTML = '';
            
            selectedBranches.forEach((item, idx) => {
                tagsDiv.insertAdjacentHTML('beforeend', `
                    <span style="background: #e2e8f0; color: #1e293b; padding: 4px 10px; border-radius: 16px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; font-weight: 500;">
                        <strong>[${item.inst}]</strong> ${item.branch}
                        <span style="cursor: pointer; font-weight: bold; color: #ef4444;" onclick="removeBranch(${idx})">&times;</span>
                    </span>
                `);
                inputsDiv.insertAdjacentHTML('beforeend', `
                    <input type="hidden" name="eligible_branches[]" value="${item.branch}">
                `);
            });
            const hint = document.getElementById('noBranchHint');
            if (hint) hint.style.display = selectedBranches.length ? 'none' : 'block';
        }

        function addSelectedBranches() {
            const inst = document.getElementById('selInstitution').value;
            const subSelect = document.getElementById('selSubBranch');
            const selectedOptions = Array.from(subSelect.selectedOptions).map(opt => opt.value).filter(val => val !== "");
            
            selectedOptions.forEach(val => {
                const exists = selectedBranches.some(item => item.inst === inst && item.branch === val);
                if (!exists) {
                    selectedBranches.push({ inst: inst, branch: val });
                }
            });
            
            renderTags();
        }

        function removeBranch(idx) {
            selectedBranches.splice(idx, 1);
            renderTags();
        }

        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        }

        function addSpocRow(data = {}) {
            const container = document.getElementById('spocList');
            const div = document.createElement('div');
            div.className = 'spoc-row';
            div.innerHTML = `
                <input type="text" name="spoc_name[]" value="${data.name || ''}" class="form-control" placeholder="Name">
                <input type="text" name="spoc_designation[]" value="${data.designation || ''}" class="form-control" placeholder="Role">
                <input type="email" name="spoc_email[]" value="${data.email || ''}" class="form-control" placeholder="Email">
                <input type="text" name="spoc_phone[]" value="${data.phone || ''}" class="form-control" placeholder="Phone">
                <button type="button" class="btn-action" style="color: #ef4444; padding: 10px;" onclick="this.parentElement.remove()">&times;</button>
            `;
            container.appendChild(div);
        }

        function addCustomQuestionRow(data = {}) {
            const container = document.getElementById('customQuestionsList');
            const div = document.createElement('div');
            div.className = 'spoc-row';
            div.style.gridTemplateColumns = '2fr 1fr 1fr auto';
            const types = ['text', 'number', 'file', 'yesno'];
            let options = types.map(t => `<option value="${t}" ${data.type === t ? 'selected' : ''}>${t.toUpperCase()}</option>`).join('');
            
            div.innerHTML = `
                <input type="text" name="custom_q_text[]" value="${data.label || ''}" class="form-control" placeholder="Question Label" required>
                <select name="custom_q_type[]" class="form-control">${options}</select>
                <label class="checkbox-item" style="justify-content: center;">
                    <input type="checkbox" name="custom_q_required_visible[]" ${data.required ? 'checked' : ''} onchange="this.nextElementSibling.value = this.checked ? '1' : '0'">
                    <input type="hidden" name="custom_q_required[]" value="${data.required ? '1' : '0'}">
                    Required
                </label>
                <button type="button" class="btn-action" style="color: #ef4444; padding: 10px;" onclick="this.parentElement.remove()">&times;</button>
            `;
            container.appendChild(div);
        }

        function openModal() {
            document.getElementById('modalTitle').innerText = 'Post Opportunity';
            document.getElementById('formAction').value = 'create';
            form.reset();
            document.getElementById('companyLogoPreviewContainer').style.display = 'none';
            document.getElementById('companyLogoPreview').src = '';
            document.getElementById('companyId').value = '';
            document.getElementById('companySearch').value = '';
            document.getElementById('academicYear').value = '';
            document.getElementById('spocList').innerHTML = '';
            document.getElementById('customQuestionsList').innerHTML = '';
            modal.style.display = 'flex';
            showTab('company-details');
            
            selectedBranches = [];
            renderTags();
            
            document.getElementById('selInstitution').value = '<?php echo $_SESSION['user']['institution'] ?? 'GMIT'; ?>';
            onInstChange();
            
            addSpocRow();
        }

        function closeModal() { modal.style.display = 'none'; }

        async function editJob(job) {
            document.getElementById('modalTitle').innerText = 'Edit Opportunity';
            document.getElementById('formAction').value = 'update';
            document.getElementById('jobId').value = job.id;
            
            document.getElementById('academicYear').value = job.academic_year || '';
            document.getElementById('title').value = job.title;
            document.getElementById('location').value = job.location;
            document.getElementById('jobType').value = job.job_type;
            document.getElementById('workMode').value = job.work_mode || 'On-Site';
            document.getElementById('salaryMin').value = job.salary_min || '';
            document.getElementById('salaryMax').value = job.salary_max || '';
            document.getElementById('minCgpa').value = job.min_cgpa;
            document.getElementById('deadline').value = job.application_deadline;
            document.getElementById('description').value = job.description || '';
            document.getElementById('requirements').value = job.requirements || '';
            
            document.getElementById('companyId').value = job.company_id;
            document.getElementById('companyName').value = job.company_name;
            document.getElementById('companySearch').value = job.company_name;

            await loadCompanyData(job.company_id);

            try {
                const rawBranches = JSON.parse(job.eligible_branches || '[]');
                selectedBranches = [];
                rawBranches.forEach(branch => {
                    let inst = 'GMIT';
                    for (const [iName, courses] of Object.entries(HIERARCHY)) {
                        let found = false;
                        for (const [cName, branches] of Object.entries(courses)) {
                            if (branches.includes(branch) || cName === branch) {
                                inst = iName;
                                found = true;
                                break;
                            }
                        }
                        if (found) break;
                    }
                    selectedBranches.push({ inst: inst, branch: branch });
                });
            } catch(e) {
                selectedBranches = [];
            }
            renderTags();
            
            document.getElementById('selInstitution').value = '<?php echo $_SESSION['user']['institution'] ?? 'GMIT'; ?>';
            onInstChange();

            try {
                const years = JSON.parse(job.eligible_years || '[]');
                document.querySelectorAll('.year-check').forEach(cb => cb.checked = years.map(String).includes(cb.value));
            } catch(e) {}

            try {
                const customFields = JSON.parse(job.custom_fields || '[]');
                document.getElementById('customQuestionsList').innerHTML = '';
                customFields.forEach(f => addCustomQuestionRow(f));
            } catch(e) {}

            modal.style.display = 'flex';
            showTab('company-details');
        }

        async function loadCompanyData(companyId) {
            if (!companyId) return;
            try {
                const res = await fetch(`../api/get_company.php?id=${companyId}`);
                const data = await res.json();
                if (data.success) {
                    const c = data.company;
                    document.getElementById('companyName').value = c.name;
                    document.getElementById('companySector').value = c.sector || '';
                    document.getElementById('companyWebsite').value = c.website || '';
                    document.getElementById('companyDistrict').value = c.district || '';
                    document.getElementById('companyDescription').value = c.description || '';
                    const previewContainer = document.getElementById('companyLogoPreviewContainer');
                    const previewImg = document.getElementById('companyLogoPreview');
                    if (c.logo_url) {
                        const baseUrl = '<?php echo APP_URL; ?>/uploads/company_images/';
                        previewImg.src = c.logo_url.startsWith('http') ? c.logo_url : baseUrl + c.logo_url;
                        previewContainer.style.display = 'flex';
                    } else {
                        previewContainer.style.display = 'none';
                    }
                    document.getElementById('spocList').innerHTML = '';
                    if (data.spocs) data.spocs.forEach(s => addSpocRow(s));
                }
            } catch (err) {
                console.error("Failed to load company data:", err);
            }
        }

        async function closeJob(id) {
            if (confirm('Are you sure you want to close this job posting?')) {
                const res = await fetch('job_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'close', job_id: id })
                });
                const data = await res.json();
                if (data.success) location.reload();
            }
        }

        function onCompanySelect(name) {
            const datalist = document.getElementById('existingCompanies');
            const opt = Array.from(datalist.options).find(o => o.value === name);
            if (opt) {
                const id = opt.dataset.id;
                document.getElementById('companyId').value = id;
                loadCompanyData(id);
            } else {
                document.getElementById('companyId').value = '';
            }
        }

        document.getElementById('companyName').addEventListener('input', function() {
            document.getElementById('companySearch').value = this.value;
        });

        window.onclick = e => { if (e.target === modal) closeModal(); }
    </script>
</body>
</html>

