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
$all        = $model->getAllPlacedStudents('sl_no ASC');
$total      = count($all);
$totalPages = max(1, ceil($total / $perPage));
$paginated  = array_slice($all, ($page - 1) * $perPage, $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placed Students – <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --maroon: #7C0000;
            --maroon-dark: #5A0000;
            --maroon-light: #9B1B1B;
            --gold: #B08D2C;
            --page-bg: #F8F9FA;
            --card-bg: #FFFFFF;
            --border-color: #E5E7EB;
            --text-primary: #111827;
            --text-secondary: #4B5563;
            --text-muted: #9CA3AF;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--page-bg);
            color: var(--text-primary);
            margin: 0;
            padding-top: 0;
            min-height: 100vh;
        }

        .o-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 40px;
        }

        /* Header */
        .o-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            background: var(--card-bg);
            padding: 24px 30px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .o-head h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            color: var(--maroon);
            letter-spacing: -0.02em;
        }

        .o-head p { color: var(--text-secondary); margin: 4px 0 0 0; font-size: 13.5px; }

        /* Stats */
        .o-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .o-stat {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 20px 24px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            transition: transform 0.15s ease;
        }

        .o-stat:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }

        .o-stat__lbl { font-size: 11.5px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; }
        .o-stat__val { font-size: 26px; font-weight: 800; color: var(--maroon); }
        .o-stat--green .o-stat__val { color: #059669; }

        /* Card / Section Container */
        .o-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .o-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .o-card-head h3 { font-size: 17px; font-weight: 700; margin: 0; color: var(--text-primary); }

        /* Upload Zone */
        .o-upload {
            border: 2px dashed #D1D5DB;
            border-radius: var(--radius-lg);
            padding: 36px 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #F9FAFB;
        }

        .o-upload:hover, .o-upload.drag-over {
            border-color: var(--maroon);
            background: #FEF2F2;
        }

        .o-upload i { font-size: 38px; color: var(--maroon); margin-bottom: 12px; }

        /* Table */
        .o-table-wrap { overflow-x: auto; }
        .o-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .o-table th { padding: 12px 16px; text-align: left; font-size: 11.5px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; background: #F9FAFB; border-bottom: 1px solid var(--border-color); letter-spacing: 0.04em; }
        .o-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); font-size: 13.5px; }
        .o-table tr:hover td { background: #FDFEFE; }
        .o-table tr:last-child td { border-bottom: none; }

        /* Buttons & Inputs */
        .o-btn {
            padding: 8px 18px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            transition: all 0.15s ease;
        }

        .o-btn--green { background: #059669; color: white; }
        .o-btn--green:hover { background: #047857; }
        .o-btn--brand { background: var(--maroon); color: white; }
        .o-btn--brand:hover { background: var(--maroon-dark); }
        .o-btn--ghost { background: transparent; color: #DC2626; border: 1px solid #FECACA; }
        .o-btn--ghost:hover { background: #FEF2F2; }

        .o-input {
            padding: 8px 14px;
            border: 1px solid #D1D5DB;
            border-radius: var(--radius-sm);
            outline: none;
            font-size: 13px;
            color: var(--text-primary);
        }

        .o-input:focus { border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(124, 0, 0, 0.12); }

        .o-badge { padding: 3px 8px; border-radius: 9999px; font-weight: 600; font-size: 11px; }
        .o-badge--green { background: #ECFDF5; color: #047857; border: 1px solid #D1FAE5; }

        .o-pager { display: flex; justify-content: center; gap: 8px; margin-top: 24px; }
        .o-pg { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); background: white; text-decoration: none; font-weight: 600; font-size: 13px; color: var(--text-secondary); border: 1px solid var(--border-color); transition: all 0.15s; }
        .o-pg:hover { border-color: var(--maroon); color: var(--maroon); background: #FDF2F2; }
        .o-pg.active { background: var(--maroon); color: white; border-color: var(--maroon); }

        @media (max-width: 768px) {
            .o-page { padding: 20px; }
            .o-head { flex-direction: column; align-items: flex-start; gap: 16px; }
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
                        <th>Name</th>
                        <th>Contact No</th>
                        <th>Mail ID</th>
                        <th>USN</th>
                        <th>YOP</th>
                        <th>Qualification</th>
                        <th>Specialisation</th>
                        <th>Company Name</th>
                        <th>Designation</th>
                        <th>CTC in Lakhs</th>
                        <th>Gender</th>
                        <th>College Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginated as $s): ?>
                    <tr>
                        <td style="font-weight:700; color:var(--text-dark);"><?php echo htmlspecialchars($s['name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($s['contact_no'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($s['mail_id'] ?? '-'); ?></td>
                        <td style="font-family:monospace;font-size:13px; font-weight:700; color:var(--brand);"><?php echo htmlspecialchars($s['usn'] ?? '-'); ?></td>
                        <td style="font-size:13px; font-weight:700;"><?php echo $s['yop'] ?? '-'; ?></td>
                        <td><?php echo htmlspecialchars($s['qualification'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($s['specialisation'] ?? '-'); ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($s['company_name'] ?? '-'); ?></td>
                        <td style="font-size:13px;color:var(--text-muted); font-weight:500;"><?php echo htmlspecialchars($s['designation'] ?? '-'); ?></td>
                        <td><span class="o-badge o-badge--green"><?php echo !empty($s['ctc_in_lakhs']) ? htmlspecialchars($s['ctc_in_lakhs']) : '-'; ?></span></td>
                        <td><?php echo htmlspecialchars($s['gender'] ?? '-'); ?></td>
                        <td style="font-size:12px;color:var(--text-muted); font-weight:600;"><?php echo htmlspecialchars($s['college_name'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; if (empty($paginated)): ?>
                    <tr><td colspan="12" style="text-align:center; padding:60px; color:var(--text-muted);">
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
