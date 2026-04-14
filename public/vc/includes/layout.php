<?php
/**
 * VC Dashboard - Common Layout Header
 */

function renderVCHeader($title = "VC Analytics Dashboard") {
    $fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo APP_NAME; ?></title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --bg: #f0f2f5;
            --text: #1e293b;
            --text-muted: #64748b;
            --card: #ffffff;
            --border: #e2e8f0;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --navbar-height: 70px;
            --success: #15803d;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding-top: var(--navbar-height);
        }

        main {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .view-title h2 { font-size: 32px; font-weight: 700; color: var(--primary-maroon); }
        .view-title p { color: var(--text-muted); margin-top: 4px; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            padding: 24px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
        }

        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .label { font-size: 11px; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; font-weight: 700; }
        .stat-card .value { font-size: 32px; font-weight: 800; margin: 10px 0; color: var(--primary-maroon); }
        .stat-card .subtext { font-size: 13px; color: var(--text-muted); }

        .table-container { 
            background: var(--card); 
            border: 1px solid var(--border); 
            border-radius: 20px; 
            box-shadow: var(--shadow); 
            overflow: hidden; 
            margin-bottom: 30px;
        }
        
        .table-responsive { overflow-x: auto; }
        
        table { width: 100%; border-collapse: collapse; }
        th { 
            background: #f8fafc; color: var(--text-muted); 
            font-size: 11px; font-weight: 700; text-transform: uppercase; 
            letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0;
            padding: 15px 25px; text-align: left;
        }
        td { padding: 15px 25px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        tr:hover td { background: #f8fafc; }

        .student-name { font-weight: 700; color: var(--primary-maroon); }
        .usn-font { font-family: monospace; font-weight: 600; color: #475569; }
        
        .badge {
            padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
        }
        .badge-gmu { background: #fee2e2; color: #991b1b; }
        .badge-gmit { background: #dcfce7; color: #166534; }

        .btn-view { 
            color: var(--primary-maroon); background: white; 
            padding: 6px 12px; border-radius: 8px; border: 1px solid var(--primary-maroon); 
            font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; 
            text-decoration: none; transition: all 0.2s;
        }
        .btn-view:hover { background: var(--primary-maroon); color: white; }

        /* Multi-page filters */
        .filter-section { background: white; padding: 25px; border-radius: 20px; margin-bottom: 30px; box-shadow: var(--shadow); border: 1px solid var(--border); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .filter-item label { display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; }
        .filter-item input, .filter-item select { width: 100%; padding: 10px 15px; border: 1px solid var(--border); border-radius: 10px; font-size: 14px; outline: none; }
        .filter-item input:focus, .filter-item select:focus { border-color: var(--primary-maroon); }
        
        .btn-primary { background: var(--primary-maroon); color: white; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }

        /* Pagination */
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 30px; padding: 20px; }
        .page-link { 
            padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border); 
            background: white; color: var(--text); text-decoration: none; 
            font-weight: 600; font-size: 14px; transition: all 0.2s;
        }
        .page-link:hover { border-color: var(--primary-maroon); color: var(--primary-maroon); background: #fff5f5; }
        .page-link.active { background: var(--primary-maroon); color: white; border-color: var(--primary-maroon); }
        .page-link.disabled { color: #cbd5e1; cursor: not-allowed; pointer-events: none; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main>
<?php
}

function renderVCFooter() {
?>
    </main>
</body>
</html>
<?php
}
