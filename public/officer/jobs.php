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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --sidebar-width: 260px;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }

        /* Reuse Sidebar & Header Styles from dashboard.php */
        /* (Simplified version for this page) */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
            color: var(--white);
            height: 100vh;
            position: fixed;
            left: 0;
            padding: 30px 20px;
        }

        .sidebar-logo { font-size: 22px; font-weight: bold; color: var(--primary-gold); margin-bottom: 40px; text-align: center; }

        .nav-link {
            display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: rgba(255,255,255,0.8);
            text-decoration: none; border-radius: 10px; transition: var(--transition);
        }

        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: var(--primary-gold); }
        .nav-link.active { background: var(--primary-gold); color: var(--primary-maroon); font-weight: bold; }

        .main-content { margin-left: 0; flex: 1; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }

        /* Page Specific Styles */
        .actions-bar {
            display: flex;
            justify-content: space-between;
            background: var(--white);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .btn-add {
            background: var(--primary-maroon);
            color: var(--white);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-add:hover { background: var(--primary-dark); transform: scale(1.02); }

        .search-box {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 300px;
        }

        .jobs-table-card {
            background: var(--white);
            border-radius: 16px;
            padding: 0;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 20px; background: #fafafa; border-bottom: 2px solid #eee; color: #666; font-size: 13px; }
        td { padding: 18px 20px; border-bottom: 1px solid #f0f0f0; }

        .job-title-cell { font-weight: 600; color: var(--primary-maroon); }
        .company-cell { color: #555; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge-Active { background: #e3fcef; color: #00875a; }
        .badge-Closed { background: #ffebe6; color: #bf2600; }
        .badge-Draft { background: #f4f5f7; color: #42526e; }

        .action-btns { display: flex; gap: 10px; }
        .btn-icon { border: none; background: none; cursor: pointer; font-size: 18px; color: #888; transition: var(--transition); }
        .btn-icon:hover { color: var(--primary-maroon); transform: scale(1.2); }

        /* Modal Styles */
        #jobModal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--white);
            width: 800px;
            padding: 35px;
            border-radius: 24px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(128, 0, 0, 0.25);
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary-maroon);
            margin: 0;
        }

        /* Modern Tabs/Stepper */
        .modal-tabs {
            display: flex;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 12px;
            margin-bottom: 30px;
            gap: 5px;
        }

        .tab-btn {
            flex: 1;
            padding: 12px;
            cursor: pointer;
            border: none;
            background: none;
            border-radius: 10px;
            font-weight: 600;
            color: #666;
            font-size: 14px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .tab-btn i { font-size: 18px; }

        .tab-btn.active {
            background: var(--white);
            color: var(--primary-maroon);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .tab-btn.completed { color: #00875a; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 5px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #344054; 
            font-size: 14px;
        }
        
        .form-control { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1.5px solid #eaecf0; 
            border-radius: 10px; 
            font-size: 14px;
            transition: var(--transition);
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.1);
        }

        textarea.form-control { height: 120px; resize: vertical; line-height: 1.6; }

        /* Custom Checkboxes */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 12px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 12px;
            border: 1px solid #eaecf0;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #475467;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: var(--transition);
        }

        .checkbox-item:hover { background: #fff; }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-maroon);
            cursor: pointer;
        }

        .modal-footer {
            margin-top: 35px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 25px;
            border-top: 1px solid #eaecf0;
        }

        .btn-cancel {
            background: #fff;
            border: 1px solid #d0d5dd;
            color: #344054;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-cancel:hover { background: #f9fafb; }

        .btn-add {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            background: #9ca3af;
        }

        /* SPOC List Improved */
        .spoc-row {
            display: grid;
            grid-template-columns: 2fr 2fr 2fr 2fr 40px;
            gap: 12px;
            margin-bottom: 12px;
            background: #fff;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #eaecf0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .btn-remove-spoc {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f04438;
            background: #fef3f2;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 20px;
            transition: var(--transition);
        }

        .btn-remove-spoc:hover { background: #fee4e2; }

        .btn-add-spoc {
            background: #fff;
            color: var(--primary-maroon);
            border: 1.5px dashed var(--primary-maroon);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            margin-top: 10px;
            transition: var(--transition);
            width: 100%;
        }

        .btn-add-spoc:hover { background: rgba(128,0,0,0.05); }

        .tab-content { display: none; animation: fadeIn 0.4s ease-out; }
        .tab-content.active { display: block; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; display: flex; flex-direction: column; min-height: 100vh; }
        
        .main-content {
            /* Layout handled by navbar.php */
        }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h2>Manage Job Postings</h2>
            <button class="btn-add" onclick="openModal()">+ Post New Job</button>
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

        <div class="actions-bar">
            <form method="POST" style="display: flex; gap: 15px;">
                <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search jobs, companies..." class="search-box">
                <select name="status" class="search-box" style="width: 150px;" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="Active" <?php echo $statusFilter == 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Closed" <?php echo $statusFilter == 'Closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="Draft" <?php echo $statusFilter == 'Draft' ? 'selected' : ''; ?>>Draft</option>
                </select>
            </form>
        </div>

        <div class="jobs-table-card">
            <table>
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Company</th>
                        <th>Location</th>
                        <th>SGPA Req.</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td class="job-title-cell"><?php echo htmlspecialchars($job['title']); ?></td>
                        <td class="company-cell"><?php echo htmlspecialchars($job['company_name']); ?></td>
                        <td><?php echo htmlspecialchars($job['location']); ?></td>
                        <td><?php echo $job['min_cgpa'] ?: 'N/A'; ?></td>
                        <td style="color: #ed213a;"><?php echo date('M d, Y', strtotime($job['application_deadline'])); ?></td>
                        <td>
                            <span class="status-badge badge-<?php echo $job['status']; ?>"><?php echo $job['status']; ?></span>
                        </td>
                        <td class="action-btns">
                            <button class="btn-icon" title="Edit" onclick='editJob(<?php echo json_encode($job, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>✏️</button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="view_apps" value="<?php echo $job['id']; ?>">
                                <button type="submit" class="btn-icon" title="View Applications">👥</button>
                            </form>
                            <?php if ($job['status'] !== 'Closed'): ?>
                            <button class="btn-icon" title="Close Job" onclick="closeJob(<?php echo $job['id']; ?>)">🔒</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; if (empty($jobs)): ?>
                    <tr><td colspan="7" style="text-align: center; color: #999; padding: 40px;">No job postings found match your criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Job Modal -->
    <div id="jobModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Post New Job</h3>
                <button type="button" class="btn-icon" onclick="closeModal()" style="font-size: 24px;">&times;</button>
            </div>
            
            <div class="modal-tabs">
                <button type="button" class="tab-btn active" id="tab-job-details" onclick="showTab('job-details')">
                    <span>1.</span> Job Details
                </button>
                <button type="button" class="tab-btn" id="tab-company-details" onclick="showTab('company-details')">
                    <span>2.</span> Company Info
                </button>
                <button type="button" class="tab-btn" id="tab-spoc-details" onclick="showTab('spoc-details')">
                    <span>3.</span> SPOCs
                </button>
                <button type="button" class="tab-btn" id="tab-custom-questions" onclick="showTab('custom-questions')">
                    <span>4.</span> Custom Questions
                </button>
            </div>

            <form id="jobForm" method="POST" action="job_handler" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="job_id" id="jobId">

                <!-- Tab 1: Job Details -->
                <div id="job-details" class="tab-content active">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Existing Company (Type to Search)</label>
                            <input type="text" id="companySearch" class="form-control" list="existingCompanies" placeholder="Enter company name..." oninput="onCompanySelect(this.value)">
                            <datalist id="existingCompanies">
                                <?php foreach ($companies as $company): ?>
                                <option value="<?php echo htmlspecialchars($company['name']); ?>" data-id="<?php echo $company['id']; ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" name="company_id" id="companyId">
                        </div>
                        <div class="form-group">
                            <label>Job Title / Role</label>
                            <input type="text" name="title" id="title" class="form-control" required placeholder="e.g. Software Engineer">
                        </div>
                        <div class="form-group">
                            <label>Job Location</label>
                            <input type="text" name="location" id="location" class="form-control" required placeholder="e.g. Pune, Bangalore">
                        </div>
                        <div class="form-group">
                            <label>Job Type</label>
                            <select name="job_type" id="jobType" class="form-control">
                                <option value="Full-Time">Full-Time</option>
                                <option value="Part-Time">Part-Time</option>
                                <option value="Contract">Contract</option>
                                <option value="Internship">Internship</option>
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
                            <label>Salary Min (Annual)</label>
                            <input type="number" name="salary_min" id="salaryMin" class="form-control" placeholder="e.g. 500000">
                        </div>
                        <div class="form-group">
                            <label>Salary Max (Annual)</label>
                            <input type="number" name="salary_max" id="salaryMax" class="form-control" placeholder="e.g. 800000">
                        </div>
                        <div class="form-group">
                            <label>Currency</label>
                            <input type="text" name="currency" id="currency" class="form-control" value="INR">
                        </div>
                        <div class="form-group">
                            <label>Minimum SGPA</label>
                            <input type="number" step="0.1" name="min_cgpa" id="minCgpa" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Application Deadline</label>
                            <input type="date" name="application_deadline" id="deadline" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Eligible Courses</label>
                            <div class="checkbox-group">
                                <label class="checkbox-item"><input type="checkbox" name="eligible_courses[]" value="BTECH" class="course-check" onchange="updateBranchOptions()"> BTECH</label>
                                <label class="checkbox-item"><input type="checkbox" name="eligible_courses[]" value="BE" class="course-check" onchange="updateBranchOptions()"> BE</label>
                                <label class="checkbox-item"><input type="checkbox" name="eligible_courses[]" value="MCOM" class="course-check" onchange="updateBranchOptions()"> MCOM</label>
                                <label class="checkbox-item"><input type="checkbox" name="eligible_courses[]" value="BCOM" class="course-check" onchange="updateBranchOptions()"> BCOM</label>
                                <label class="checkbox-item"><input type="checkbox" name="eligible_courses[]" value="MBA" class="course-check" onchange="updateBranchOptions()"> MBA</label>
                                <label class="checkbox-item"><input type="checkbox" name="eligible_courses[]" value="MCA" class="course-check" onchange="updateBranchOptions()"> MCA</label>
                                <label class="checkbox-item"><input type="checkbox" name="eligible_courses[]" value="BCA" class="course-check" onchange="updateBranchOptions()"> BCA</label>
                            </div>
                        </div>

                        <!-- Dynamic Branch Selection -->
                        <div class="form-group" style="grid-column: span 2;" id="branchSelectionContainer" style="display: none;">
                            <label>Eligible Branches (Select Course first)</label>
                            <div class="checkbox-group" id="branchCheckboxes">
                                <p style="color: #666; font-size: 13px; padding: 10px;">Please select courses above to see available branches.</p>
                            </div>
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label>Eligible Years</label>
                            <div class="checkbox-group">
                                <label class="checkbox-item"><input type="checkbox" name="eligible_years[]" value="1" class="year-check"> 1st Year</label>
                                <label class="checkbox-item"><input type="checkbox" name="eligible_years[]" value="2" class="year-check"> 2nd Year</label>
                                <label class="checkbox-item"><input type="checkbox" name="eligible_years[]" value="3" class="year-check"> 3rd Year</label>
                                <label class="checkbox-item"><input type="checkbox" name="eligible_years[]" value="4" class="year-check"> 4th Year</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Job Description</label>
                        <textarea name="description" id="description" class="form-control" required placeholder="General overview of the role..."></textarea>
                    </div>
                    <!-- ... (keeping rest of form) ... -->
                    <div class="form-group">
                        <label>Requirements</label>
                        <textarea name="requirements" id="requirements" class="form-control" placeholder="Skills, qualifications, experience..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Responsibilities</label>
                        <textarea name="responsibilities" id="responsibilities" class="form-control" placeholder="Daily tasks, what they will do..."></textarea>
                    </div>
                </div>

                <!-- ... (keeping other tabs) ... -->
                
                <!-- Tab 2: Company Details -->
                <div id="company-details" class="tab-content">
                    <!-- ... (content same as original) ... -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Company Name</label>
                            <input type="text" name="company_name" id="companyName" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Sector / Industry</label>
                            <input type="text" name="company_sector" id="companySector" class="form-control" placeholder="e.g. IT, FinTech">
                        </div>
                        <div class="form-group">
                            <label>Website Link</label>
                            <input type="url" name="company_website" id="companyWebsite" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>District</label>
                            <input type="text" name="company_district" id="companyDistrict" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>State</label>
                            <input type="text" name="company_state" id="companyState" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Country</label>
                            <input type="text" name="company_country" id="companyCountry" class="form-control" value="India">
                        </div>
                        <div class="form-group">
                            <label>Company Logo</label>
                            <input type="file" name="company_logo" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label>Company Document (PDF)</label>
                            <input type="file" name="company_doc" class="form-control" accept=".pdf,.doc,.docx">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>About Company (Description)</label>
                        <textarea name="company_description" id="companyDescription" class="form-control"></textarea>
                    </div>
                </div>

                <!-- Tab 3: SPOC Details -->
                <div id="spoc-details" class="tab-content">
                    <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Add one or more Single Point of Contacts (SPOC) for this company.</p>
                    <div id="spocList">
                        <!-- SPOC rows will be added here -->
                    </div>
                    <button type="button" class="btn-add-spoc" onclick="addSpocRow()">+ Add SPOC</button>
                </div>

                <!-- Tab 4: Custom Questions -->
                <div id="custom-questions" class="tab-content">
                    <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Add extra questions for students to answer when applying.</p>
                    <div id="customQuestionsList">
                        <!-- Custom Questions rows will be added here -->
                    </div>
                    <button type="button" class="btn-add-spoc" onclick="addCustomQuestionRow()">+ Add Question</button>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" id="submitBtn" class="btn-add">
                        <span id="btnText">Save Job Posting</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('jobModal');
        const form = document.getElementById('jobForm');

        // Course -> Branches Mapping
        const COURSE_BRANCHES = {
            'BTECH': ['CSE', 'AIML', 'ISE', 'ECE', 'EEE', 'MECH', 'CIVIL', 'BT', 'AIDS', 'CSBS'],
            'BE': ['CSE', 'AIML', 'ISE', 'ECE', 'EEE', 'MECH', 'CIVIL'],
            'MCOM': ['Finance', 'Accounting', 'Banking'],
            'BCOM': ['General', 'Computers', 'Finance'],
            'MBA': ['Finance', 'Marketing', 'HR', 'Operations', 'Business Analytics'],
            'MCA': ['MCA'],
            'BCA': ['BCA']
        };

        function updateBranchOptions() {
            const container = document.getElementById('branchCheckboxes');
            const selectedCourses = Array.from(document.querySelectorAll('.course-check:checked')).map(cb => cb.value);
            
            // Get currently checked branches to preserve selection if possibe
            const checkedBranches = Array.from(document.querySelectorAll('.branch-check:checked')).map(cb => cb.value);
            
            container.innerHTML = '';
            
            if (selectedCourses.length === 0) {
                container.innerHTML = '<p style="color: #666; font-size: 13px; padding: 10px;">Please select courses above to see available branches.</p>';
                return;
            }

            const uniqueBranches = new Set();
            
            selectedCourses.forEach(course => {
                const branches = COURSE_BRANCHES[course] || [];
                branches.forEach(b => uniqueBranches.add(b));
            });

            if (uniqueBranches.size === 0) {
                 container.innerHTML = '<p style="color: #666; font-size: 13px; padding: 10px;">No specific branches available for selected courses.</p>';
                 return;
            }

            // sort alphabetically
            const sortedBranches = Array.from(uniqueBranches).sort();
            
            sortedBranches.forEach(branch => {
                const isChecked = checkedBranches.includes(branch) ? 'checked' : '';
                const html = `
                    <label class="checkbox-item">
                        <input type="checkbox" name="eligible_branches[]" value="${branch}" class="branch-check" ${isChecked}> 
                        ${branch}
                    </label>
                `;
                container.insertAdjacentHTML('beforeend', html);
            });
        }

        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            document.getElementById(tabId).classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
            
            // Mark previous tabs as completed (optional visual cue)
            const tabs = ['job-details', 'company-details', 'spoc-details', 'custom-questions'];
            const currentIndex = tabs.indexOf(tabId);
            tabs.forEach((id, index) => {
                const btn = document.getElementById('tab-' + id);
                if (index < currentIndex) {
                    btn.classList.add('completed');
                } else {
                    btn.classList.remove('completed');
                }
            });
        }

        function addSpocRow(data = {}) {
            const container = document.getElementById('spocList');
            const row = document.createElement('div');
            row.className = 'spoc-row';
            row.innerHTML = `
                <input type="text" name="spoc_name[]" value="${data.name || ''}" class="form-control" placeholder="Name" required>
                <input type="text" name="spoc_designation[]" value="${data.designation || ''}" class="form-control" placeholder="Designation">
                <input type="email" name="spoc_email[]" value="${data.email || ''}" class="form-control" placeholder="Email">
                <input type="text" name="spoc_phone[]" value="${data.phone || ''}" class="form-control" placeholder="Phone">
                <button type="button" class="btn-remove-spoc" onclick="this.parentElement.remove()">×</button>
            `;
            container.appendChild(row);
        }

        function addCustomQuestionRow(data = {}) {
            const container = document.getElementById('customQuestionsList');
            const row = document.createElement('div');
            row.className = 'spoc-row'; // Reusing spoc-row style for consistency
            row.style.gridTemplateColumns = "3fr 1fr 1fr 40px"; 
            
            const isText = (!data.type || data.type === 'text') ? 'selected' : '';
            const isNumber = (data.type === 'number') ? 'selected' : '';
            const isFile = (data.type === 'file') ? 'selected' : '';
            const isYesNo = (data.type === 'yesno') ? 'selected' : '';
            
            const isRequired = (data.required === '1' || data.required === true || data.required === 'true') ? 'checked' : '';

            row.innerHTML = `
                <input type="text" name="custom_q_text[]" value="${data.label || ''}" class="form-control" placeholder="Question Text (e.g. Why do you want to join?)" required>
                <select name="custom_q_type[]" class="form-control">
                    <option value="text" ${isText}>Text</option>
                    <option value="number" ${isNumber}>Number</option>
                    <option value="file" ${isFile}>File Upload</option>
                    <option value="yesno" ${isYesNo}>Yes/No</option>
                </select>
                <div style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="custom_q_required_visible[]" ${isRequired} onchange="this.nextElementSibling.value = this.checked ? '1' : '0'">
                    <input type="hidden" name="custom_q_required[]" value="${isRequired ? '1' : '0'}">
                    <label>Required</label>
                </div>
                <button type="button" class="btn-remove-spoc" onclick="this.parentElement.remove()">×</button>
            `;
            container.appendChild(row);
        }

        function onCompanySelect(name) {
            const datalist = document.getElementById('existingCompanies');
            const options = datalist.options;
            let foundId = '';
            for (let i = 0; i < options.length; i++) {
                if (options[i].value === name) {
                    foundId = options[i].getAttribute('data-id');
                    break;
                }
            }
            
            document.getElementById('companyId').value = foundId;
            if (foundId) {
                loadCompanyData(foundId);
            } else {
                // If they cleared it or typed something new
                loadCompanyData('');
                document.getElementById('companyName').value = name; // Pre-fill name in Tab 2
            }
        }

        async function loadCompanyData(companyId) {
            if (!companyId) {
                document.getElementById('companyName').value = '';
                document.getElementById('companySector').value = '';
                document.getElementById('companyWebsite').value = '';
                document.getElementById('companyDistrict').value = '';
                document.getElementById('companyState').value = '';
                document.getElementById('companyDescription').value = '';
                document.getElementById('spocList').innerHTML = '';
                return;
            }
            
            const res = await fetch(`../../api/get_company.php?id=${companyId}`);
            const data = await res.json();
            if (data.success) {
                const c = data.company;
                document.getElementById('companyName').value = c.name;
                document.getElementById('companySector').value = c.sector || '';
                document.getElementById('companyWebsite').value = c.website || '';
                document.getElementById('companyDistrict').value = c.district || '';
                document.getElementById('companyState').value = c.state || '';
                document.getElementById('companyDescription').value = c.description || '';
                
                document.getElementById('spocList').innerHTML = '';
                if (data.spocs) data.spocs.forEach(s => addSpocRow(s));
            }
        }

        function openModal() {
            modal.style.display = 'flex';
            document.getElementById('modalTitle').innerText = 'Post New Job';
            document.getElementById('formAction').value = 'create';
            form.reset();
            document.getElementById('companyId').value = '';
            document.getElementById('companySearch').value = '';
            document.getElementById('spocList').innerHTML = '';
            document.getElementById('customQuestionsList').innerHTML = '';
            
            // Reset branch options
            updateBranchOptions();

            addSpocRow(); 
            showTab('job-details');
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        async function editJob(job) {
            modal.style.display = 'flex';
            document.getElementById('modalTitle').innerText = 'Edit Job Posting';
            document.getElementById('formAction').value = 'update';
            document.getElementById('jobId').value = job.id;
            
            document.getElementById('companyId').value = job.company_id;
            document.getElementById('companySearch').value = job.company_name;
            await loadCompanyData(job.company_id);
            
            document.getElementById('title').value = job.title;
            document.getElementById('location').value = job.location;
            document.getElementById('jobType').value = job.job_type;
            document.getElementById('workMode').value = job.work_mode || 'On-Site';
            document.getElementById('salaryMin').value = job.salary_min || '';
            document.getElementById('salaryMax').value = job.salary_max || '';
            document.getElementById('currency').value = job.currency || 'INR';
            document.getElementById('minCgpa').value = job.min_cgpa;
            document.getElementById('deadline').value = job.application_deadline;
            document.getElementById('description').value = job.description;
            document.getElementById('requirements').value = job.requirements || '';
            document.getElementById('responsibilities').value = job.responsibilities || '';

            // Handle Checkboxes
            document.querySelectorAll('.course-check').forEach(c => c.checked = false);
            const courses = JSON.parse(job.eligible_courses || '[]');
            courses.forEach(course => {
                const cb = document.querySelector(`.course-check[value="${course}"]`);
                if (cb) cb.checked = true;
            });

            // Update branch options based on selected courses
            updateBranchOptions();

            // Pre-select branches after they are rendered
            const branches = JSON.parse(job.eligible_branches || '[]');
            branches.forEach(branch => {
                // Must wait for updateBranchOptions? it's synchronous so it should be fine.
                const cb = document.querySelector(`.branch-check[value="${branch}"]`);
                if (cb) cb.checked = true;
            });

            document.querySelectorAll('.year-check').forEach(c => c.checked = false);
            const y = JSON.parse(JSON.stringify(job.eligible_years || '[]')); // Handle potential double encoding or just safety
            let years = [];
            try { years = typeof y === 'string' ? JSON.parse(y) : y; } catch(e) { years = []; }
            
            years.forEach(year => {
                const cb = document.querySelector(`.year-check[value="${year}"]`);
                if (cb) cb.checked = true;
            });
            
            // Handle Custom Fields
            document.getElementById('customQuestionsList').innerHTML = '';
            let customFields = [];
            try {
                customFields = job.custom_fields ? JSON.parse(job.custom_fields) : [];
            } catch(e) {
                console.error("Error parsing custom fields", e);
                customFields = [];
            }
            
            if (customFields.length > 0) {
                customFields.forEach(field => addCustomQuestionRow(field));
            } else {
                 // Should we add an empty one? No, let them add if they want.
            }

            showTab('job-details');
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

        // Submission handle
        form.onsubmit = function() {
            const btn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            btn.disabled = true;
            btnText.innerText = 'Saving...';
        };

        window.onclick = function(event) { if (event.target == modal) closeModal(); }
    </script>
</body>
</html>
