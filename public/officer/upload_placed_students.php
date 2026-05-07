<?php
/**
 * Upload Placed Students Data
 */
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_PLACEMENT_OFFICER);

$model  = new CompanyPlacedStudent();
$stats  = $model->getStatistics();

$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 25;
$all        = $model->getAllPlacedStudents();
$total      = count($all);
$totalPages = max(1, ceil($total / $perPage));
$paginated  = array_slice($all, ($page - 1) * $perPage, $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placed Students – <?php echo APP_NAME; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

        :root {
            --brand: #7C0000;
            --brand-light: #A50000;
            --gold: #C9972C;
            --glass: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.3);
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--text-dark);
            margin: 0;
            padding-top: 80px;
            min-height: 100vh;
        }

        .o-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Header */
        .o-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .o-head h1 {
            font-size: 32px;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(to right, var(--brand), var(--brand-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .o-head p { color: var(--text-muted); margin: 4px 0 0 0; font-size: 16px; }

        /* Stats */
        .o-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .o-stat {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            padding: 24px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .o-stat:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(124, 0, 0, 0.1); }

        .o-stat__lbl { font-size: 13px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .o-stat__val { font-size: 28px; font-weight: 800; color: var(--brand); }
        .o-stat--green .o-stat__val { color: #059669; }

        /* Card / Section Container */
        .o-card {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            padding: 32px;
            box-shadow: var(--shadow);
            margin-bottom: 32px;
        }

        .o-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .o-card-head h3 { font-size: 20px; font-weight: 800; margin: 0; color: var(--text-dark); }

        /* Upload Zone */
        .o-upload {
            border: 2px dashed #cbd5e1;
            border-radius: 24px;
            padding: 48px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.3);
        }

        .o-upload:hover, .o-upload.drag-over {
            border-color: var(--brand);
            background: rgba(124, 0, 0, 0.03);
            transform: scale(1.01);
        }

        .o-upload i { font-size: 48px; color: var(--brand); margin-bottom: 16px; opacity: 0.8; }

        /* Table */
        .o-table-wrap { overflow-x: auto; }
        .o-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .o-table th { padding: 12px 20px; text-align: left; font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .o-table td { padding: 18px 20px; background: rgba(255, 255, 255, 0.6); }
        .o-table tr td:first-child { border-radius: 16px 0 0 16px; }
        .o-table tr td:last-child { border-radius: 0 16px 16px 0; }
        .o-table tr:hover td { background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }

        /* Buttons & Inputs */
        .o-btn {
            padding: 12px 24px;
            border-radius: 16px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: none;
            transition: all 0.3s ease;
        }

        .o-btn--green { background: #059669; color: white; }
        .o-btn--green:hover { background: #047857; transform: translateY(-2px); }
        .o-btn--brand { background: var(--brand); color: white; }
        .o-btn--brand:hover { background: var(--brand-dark); transform: translateY(-2px); }
        .o-btn--ghost { background: transparent; color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .o-btn--ghost:hover { background: rgba(239, 68, 68, 0.05); }

        .o-input {
            padding: 12px 20px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            outline: none;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .o-input:focus { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(124, 0, 0, 0.05); }

        .o-badge { padding: 4px 12px; border-radius: 10px; font-weight: 800; font-size: 11px; }
        .o-badge--green { background: #ecfdf5; color: #059669; }

        .o-pager { display: flex; justify-content: center; gap: 10px; margin-top: 30px; }
        .o-pg { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 12px; background: white; text-decoration: none; font-weight: 700; color: var(--text-muted); border: 1px solid #e2e8f0; transition: all 0.2s; }
        .o-pg:hover { border-color: var(--brand); color: var(--brand); }
        .o-pg.active { background: var(--brand); color: white; border-color: var(--brand); }

        @media (max-width: 768px) {
            .o-page { padding: 20px; }
            .o-head { flex-direction: column; align-items: flex-start; gap: 20px; }
        }
    </style>
</head>
<body>
<?php include_once 'includes/navbar.php'; ?>

<div class="o-page">

    <!-- Header -->
    <div class="o-head">
        <div>
            <h1>Placed Students Intelligence</h1>
            <p>Import and audit enterprise-wide placement records</p>
        </div>
        <div class="o-head__actions">
            <button class="o-btn o-btn--brand" onclick="document.getElementById('fileInput').click()">
                <i class="fas fa-file-csv"></i> Import New List
            </button>
        </div>
    </div>

    <!-- Stats Dashboard -->
    <div class="o-stats">
        <div class="o-stat">
            <div class="o-stat__lbl">Grand Total</div>
            <div class="o-stat__val"><?php echo number_format($stats['total_placed']); ?></div>
        </div>
        <div class="o-stat">
            <div class="o-stat__lbl">Partner Companies</div>
            <div class="o-stat__val"><?php echo $stats['total_companies']; ?></div>
        </div>
        <div class="o-stat o-stat--green">
            <div class="o-stat__lbl">Market Avg CTC</div>
            <div class="o-stat__val"><?php echo $stats['average_ctc']; ?> <span style="font-size:14px; opacity:0.6;">LPA</span></div>
        </div>
        <div class="o-stat">
            <div class="o-stat__lbl">Linked Institutions</div>
            <div class="o-stat__val"><?php echo count($stats['by_college']); ?></div>
        </div>
    </div>

    <!-- Smart Import Zone -->
    <div class="o-card" style="border: 1px solid rgba(201, 151, 44, 0.2); background: rgba(255, 255, 255, 0.4);">
        <div class="o-card-head">
            <h3><i class="fas fa-microchip" style="color:var(--gold);margin-right:10px;"></i>Automated Data Ingestion</h3>
        </div>
        <div class="o-card-body">
            <input type="file" id="fileInput" accept=".csv,.xlsx,.xls" style="display:none;" onchange="onFileSelect(this)">
            <div class="o-upload" onclick="document.getElementById('fileInput').click()" id="dropZone">
                <i class="fas fa-cloud-arrow-up"></i>
                <div style="font-size:18px;font-weight:800;color:var(--text-dark);margin-bottom:8px;">Drag & drop your placement report</div>
                <div style="font-size:14px;color:var(--text-muted);max-width:600px;margin:0 auto;">
                    Supports Excel (.xlsx, .xls) and CSV files. <br>
                    <span style="font-weight:600;">System maps:</span> USN, Name, Company, CTC, YOP, Designation, and Institution.
                </div>
                <div id="selectedFile" style="margin-top:20px;padding:10px 20px;border-radius:12px;background:white;display:inline-block;font-weight:700;color:var(--brand);box-shadow:0 4px 12px rgba(0,0,0,0.05);display:none;"></div>
            </div>
            <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-top:24px;">
                <button class="o-btn o-btn--green" id="uploadBtn" onclick="doUpload()" style="display:none; padding:15px 40px; font-size:16px; box-shadow:0 10px 20px rgba(5, 150, 105, 0.2);">
                    <i class="fas fa-bolt"></i> Begin Processing
                </button>
                <div id="uploadMsg" style="font-weight:700;"></div>
            </div>
        </div>
    </div>

    <!-- Data Registry -->
    <div class="o-card">
        <div class="o-card-head">
            <div>
                <h3>Master Placement Registry</h3>
                <p style="font-size:12px; color:var(--text-muted); margin-top:4px;">Displaying <?php echo count($paginated); ?> of <?php echo $total; ?> verified records</p>
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <input type="text" class="o-input" placeholder="Quick search students or companies..." style="width:320px;" onkeyup="filterTable(this.value)">
                <?php if ($total > 0): ?>
                <button class="o-btn o-btn--ghost" onclick="if(confirm('This will wipe the entire placement history. Proceed?')) clearAll()" title="Wipe History">
                    <i class="fas fa-trash-can"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="o-table-wrap">
            <table class="o-table" id="placedTable">
                <thead>
                    <tr>
                        <th width="60">#</th>
                        <th>Student Details</th>
                        <th>USN</th>
                        <th>Organization</th>
                        <th>Role</th>
                        <th>CTC (LPA)</th>
                        <th>Batch</th>
                        <th>Institution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginated as $s): ?>
                    <tr>
                        <td style="color:var(--text-muted);font-weight:700;"><?php echo $s['sl_no']; ?></td>
                        <td>
                            <div style="font-weight:700; color:var(--text-dark);"><?php echo htmlspecialchars($s['name'] ?? '-'); ?></div>
                            <div style="font-size:11px; color:var(--text-muted);"><?php echo htmlspecialchars($s['gender'] ?? '-'); ?> Applicant</div>
                        </td>
                        <td style="font-family:monospace;font-size:13px; font-weight:700; color:var(--brand);"><?php echo htmlspecialchars($s['usn'] ?? '-'); ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($s['company_name'] ?? '-'); ?></td>
                        <td style="font-size:13px;color:var(--text-muted); font-weight:500;"><?php echo htmlspecialchars($s['designation'] ?? '-'); ?></td>
                        <td><span class="o-badge o-badge--green"><?php echo $s['ctc_in_lakhs'] ? number_format((float)$s['ctc_in_lakhs'], 2) : '-'; ?></span></td>
                        <td style="font-size:13px; font-weight:700;"><?php echo $s['yop'] ?? '-'; ?></td>
                        <td style="font-size:12px;color:var(--text-muted); font-weight:600;"><?php echo htmlspecialchars($s['college_name'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; if (empty($paginated)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:60px; color:var(--text-muted);">
                        <i class="fas fa-database" style="font-size:40px; margin-bottom:15px; opacity:0.3; display:block;"></i>
                        No placement records detected.
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="o-pager">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>" class="o-pg"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
            <a href="?page=<?php echo $i; ?>" class="o-pg <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page+1; ?>" class="o-pg"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function onFileSelect(input) {
    if (!input.files[0]) return;
    const el = document.getElementById('selectedFile');
    el.innerHTML = '<i class="fas fa-file-excel"></i> ' + input.files[0].name;
    el.style.display = 'inline-block';
    document.getElementById('uploadBtn').style.display = 'inline-flex';
}

function doUpload() {
    const file = document.getElementById('fileInput').files[0];
    const msg  = document.getElementById('uploadMsg');
    const btn  = document.getElementById('uploadBtn');
    if (!file) return;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';
    
    const fd = new FormData();
    fd.append('file', file);
    fetch('placed_students_handler.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            msg.innerHTML = d.success
                ? `<span style="color:#059669;"><i class="fas fa-check-circle"></i> ${d.message}</span>`
                : `<span style="color:#ef4444;"><i class="fas fa-exclamation-circle"></i> ${d.message}</span>`;
            if (d.success) setTimeout(() => location.reload(), 1500);
            else { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt"></i> Begin Processing'; }
        })
        .catch(e => { 
            msg.innerHTML = `<span style="color:#ef4444;">Error: ${e.message}</span>`;
            btn.disabled = false;
        });
}

function filterTable(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#placedTable tbody tr').forEach(r => {
        if(r.cells.length < 2) return;
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function clearAll() {
    const fd = new FormData();
    fd.append('action', 'clear_all');
    fetch('placed_students_handler.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); });
}

// Drag & drop logic
const zone = document.getElementById('dropZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    document.getElementById('fileInput').files = e.dataTransfer.files;
    onFileSelect(document.getElementById('fileInput'));
});
</script>
</body>
</html>
