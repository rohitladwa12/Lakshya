<?php
/**
 * Student – Resume Builder (FlowCV-style)
 * Split-panel: Left = editor, Right = live preview
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);
requireFeature('feature_resume_builder', 'Resume Builder');

$userId   = getUserId();
$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Builder – Lakshya</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --maroon: #800000;
            --maroon-dark: #5b1f1f;
            --maroon-light: #fff5f5;
            --maroon-glass: rgba(128, 0, 0, 0.04);
            --gold: #D4AF37;
            --bg: #f8fafc;
            --white: #ffffff;
            --border: rgba(0, 0, 0, 0.06);
            --text: #0f172a;
            --muted: #64748b;
            --success: #10b981;
            --panel-width: 440px;
            --topbar-height: 64px;
            --glass-bg: rgba(255, 255, 255, 0.75);
            --radius-xl: 16px;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 10px 15px -3px rgba(0,0,0,0.02), 0 4px 6px -2px rgba(0,0,0,0.01);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--bg); 
            color: var(--text); 
            height: 100vh; 
            overflow: hidden; 
            margin: 0;
            padding-top: 0 !important; /* Force reset navbar.php padding */
        }

        /* ── TOP BAR ─────────────────────────────────────────────── */
        /* ── LAYOUT ──────────────────────────────────────────────── */
        .builder-layout {
            display: grid;
            grid-template-columns: var(--panel-width) 1fr;
            height: 100vh;
            /* Using calc for total stacked header height (72px + 58px = 130px) */
            padding-top: calc(72px + var(--topbar-height));
            box-sizing: border-box;
        }

        /* ── TOP BAR ─────────────────────────────────────────────── */
        .topbar {
            top: 72px; /* Explicit match for var(--nav-height) from navbar.php */
            height: var(--topbar-height);
            background: rgba(128, 0, 0, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 0 24px;
            position: fixed;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            left: 0; right: 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .topbar-brand { color: white; font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-shrink: 0; letter-spacing: -0.02em; }
        .topbar-brand i { font-size: 1.2rem; color: var(--gold); }
        .topbar-sep { width: 1px; height: 28px; background: rgba(255,255,255,0.15); margin: 0 4px; }

        /* Template switcher */
        .template-switcher { display: flex; gap: 8px; margin-right: auto; margin-left: 12px; background: rgba(0,0,0,0.1); padding: 4px; border-radius: 14px; }
        .tpl-btn {
            padding: 6px 16px;
            border-radius: 10px;
            border: none;
            color: rgba(255,255,255,0.7);
            background: transparent;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
        }
        .tpl-btn:hover { color: white; }
        .tpl-btn.active { background: white; color: var(--maroon); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

        /* Action buttons */
        .topbar-actions { display: flex; gap: 12px; }
        .tb-btn {
            padding: 8px 18px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
        }
        .tb-btn:active { transform: scale(0.96); }
        .tb-btn-save { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.1); }
        .tb-btn-save:hover { background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.25); }
        .tb-btn-save.saved { background: var(--success); border-color: var(--success); }
        .tb-btn-pdf { background: var(--gold); color: #222; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3); }
        .tb-btn-pdf:hover { background: #e5be3e; transform: translateY(-1px); }
        .tb-btn-back { background: transparent; color: rgba(255,255,255,0.7); border: none; font-size: 0.85rem; padding: 8px 12px; }
        .tb-btn-back:hover { color: white; }

        /* ── LEFT PANEL (editor) ─────────────────────────────────── */
        .editor-panel {
            background: #fff;
            border-right: 1px solid var(--border);
            overflow-y: auto;
            height: calc(100vh - var(--nav-height) - var(--topbar-height));
            padding: 12px;
        }

        .editor-panel::-webkit-scrollbar { width: 6px; }
        .editor-panel::-webkit-scrollbar-track { background: transparent; }
        .editor-panel::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .editor-panel::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

        /* Sections */
        .section-block {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            margin-bottom: 12px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }
        .section-block.open {
            box-shadow: var(--shadow-md);
            border-color: rgba(128, 0, 0, 0.15);
        }
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            cursor: pointer;
            user-select: none;
            background: #fff;
            transition: background 0.2s;
        }
        .section-header:hover { background: #fafafa; }
        .section-header-left { display: flex; align-items: center; gap: 14px; }
        .section-header-left .main-icon { 
            color: var(--maroon); 
            font-size: 1.1rem; 
            width: 40px; height: 40px;
            background: var(--maroon-glass);
            display: flex; align-items: center; justify-content: center;
            border-radius: 12px;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        .section-block.open .section-header-left .main-icon {
            background: var(--maroon);
            color: white;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
        }
        .section-header-left span { font-weight: 700; font-size: 0.95rem; color: #334155; }
        
        /* Header Utility Buttons */
        .header-util-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 800;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }
        .header-util-btn i { font-size: 0.75rem; }
        .header-util-btn:hover { transform: translateY(-1px); }
        .header-util-btn:active { transform: scale(0.96); }

        .btn-maroon-soft {
            background: var(--maroon-glass);
            color: var(--maroon);
        }
        .btn-maroon-soft:hover {
            background: var(--maroon);
            color: white;
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.15);
        }
        
        .btn-success-soft {
            background: rgba(16, 185, 129, 0.08);
            color: var(--success);
        }
        .btn-success-soft:hover {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
        }
        .section-chevron { color: #94a3b8; font-size: 0.85rem; transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .section-block.open .section-chevron { transform: rotate(180deg); color: var(--maroon); }

        .section-body {
            padding: 0 20px 24px;
            display: none;
            animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .section-block.open .section-body { display: block; }

        /* Form fields */
        .field-group { margin-bottom: 20px; }
        .field-group label { 
            display: block; 
            font-size: 0.72rem; 
            font-weight: 800; 
            color: #64748b; 
            margin-bottom: 8px; 
            text-transform: uppercase; 
            letter-spacing: 0.08em; 
            font-family: 'Inter', sans-serif;
        }
        .field-group input,
        .field-group textarea,
        .field-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #f1f5f9;
            border-radius: var(--radius-lg);
            font-size: 0.9rem;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            background: #f8fafc;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .field-group input:hover,
        .field-group textarea:hover {
            border-color: #e2e8f0;
            background: #fff;
        }
        .field-group input:focus,
        .field-group textarea:focus {
            outline: none;
            border-color: var(--maroon);
            background: white;
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.08);
        }
        .field-group textarea { resize: vertical; min-height: 100px; line-height: 1.6; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* Tags (Skills) */
        .tags-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 10px;
            border: 1.5px solid #f1f5f9;
            border-radius: var(--radius-lg);
            min-height: 52px;
            cursor: text;
            background: #f8fafc;
            transition: all 0.25s;
        }
        .tags-wrap:focus-within { 
            border-color: var(--maroon); 
            background: #fff;
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.08);
        }
        .tag {
            background: #fff;
            color: #334155;
            padding: 6px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .tag-x { cursor: pointer; font-size: 0.8rem; color: #94a3b8; transition: color 0.2s; }
        .tag-x:hover { color: #ef4444; }
        .tag-input-el {
            border: none;
            outline: none;
            font-size: 0.9rem;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            flex: 1;
            min-width: 120px;
            color: #1e293b;
            background: transparent;
        }

        /* Dynamic list entries */
        .entry-card {
            background: #fff;
            border: 1.5px solid #f1f5f9;
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 16px;
            position: relative;
            transition: all 0.3s;
        }
        .entry-card:hover {
            border-color: #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
        }
        .entry-card-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--maroon);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .entry-remove {
            background: rgba(239, 68, 68, 0.05);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.1);
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .entry-remove:hover { background: #ef4444; color: white; border-color: #ef4444; }
        
        .entry-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .entry-move {
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #f1f5f9;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .entry-move:hover {
            background: #fff;
            color: var(--maroon);
            border-color: #e2e8f0;
        }

        /* Skill Groups */
        .tech-group-row {
            background: #f8fafc;
            border: 1.5px solid #f1f5f9;
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s;
        }
        .tech-group-row:focus-within {
            background: #fff;
            border-color: var(--maroon);
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.05);
        }
        .tech-group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            gap: 12px;
        }
        .tech-group-cat-input {
            border: none !important;
            background: transparent !important;
            font-weight: 800 !important;
            font-size: 0.85rem !important;
            color: #334155 !important;
            padding: 4px 0 !important;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            flex: 1;
        }
        .tech-group-cat-input:focus { outline: none !important; color: var(--maroon) !important; }
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .add-entry-btn {
            width: 100%;
            padding: 12px;
            border: 2px dashed #e2e8f0;
            border-radius: var(--radius-lg);
            background: rgba(128, 0, 0, 0.02);
            color: var(--maroon);
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
            margin-top: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .add-entry-btn:hover { 
            border-color: var(--maroon); 
            background: var(--maroon-glass);
            transform: translateY(-1px);
        }
        .add-entry-btn i { font-size: 0.8rem; }

        .checkbox-label { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            font-size: 0.85rem; 
            font-weight: 600;
            color: #475569; 
            cursor: pointer; 
            margin-top: 6px; 
            user-select: none;
        }
        .checkbox-label input { 
            width: 18px; height: 18px; 
            border-radius: 6px;
            accent-color: var(--maroon);
        }

        /* ── RIGHT PANEL (preview) ───────────────────────────────── */
        .preview-panel {
            background: #e5e7eb;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px 20px;
            height: calc(100vh - var(--nav-height) - var(--topbar-height));
        }
        .preview-panel::-webkit-scrollbar { width: 6px; }
        .preview-panel::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 4px; }

        /* A4 paper */
        .resume-paper {
            width: 210mm;
            min-height: 297mm;
            height: auto;
            flex-shrink: 0;
            background: white;
            box-shadow: 0 8px 40px rgba(0,0,0,0.18);
            border-radius: 3px;
            padding: 12mm 16mm 8mm;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            color: #000;
            line-height: 1.5;
            position: relative;
        }

        /* Page Limit Enforcement */
        .page-limit-line {
            position: absolute;
            left: 0; right: 0;
            border-top: 1.5px dashed rgba(239, 68, 68, 0.4);
            pointer-events: none;
            z-index: 10;
        }
        #overflowWarning {
            position: fixed;
            top: 140px;
            right: 30px;
            background: #ef4444;
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.4);
            display: none;
            z-index: 200;
            align-items: center;
            gap: 8px;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        #overflowWarning.show { display: flex; animation: shake 0.4s ease-in-out; }



        /* ── AUTOSAVE TOAST ──────────────────────────────────────── */
        #saveToast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1f2937;
            color: white;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 999;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        #saveToast.show { display: flex; animation: toastIn 0.3s ease; }
        @keyframes toastIn { from { transform: translateY(20px); opacity:0; } to { transform: translateY(0); opacity:1; } }

        /* ── ONBOARDING MODAL ────────────────────────────────────── */
        .onboarding-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.4s ease;
        }
        .onboarding-card {
            background: white;
            width: 600px;
            border-radius: 24px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }
        .onboarding-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 6px;
            background: linear-gradient(90deg, var(--maroon), var(--gold));
        }
        .onboarding-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #111;
            margin-bottom: 16px;
        }
        .onboarding-text {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 32px;
            font-size: 1.05rem;
        }
        .onboarding-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .option-box {
            padding: 24px;
            border: 2px solid #f3f4f6;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .option-box:hover {
            border-color: var(--maroon);
            background: #fffafa;
            transform: translateY(-5px);
        }
        .option-box i {
            font-size: 2.5rem;
            color: var(--maroon);
        }
        .option-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: #111;
        }
        .option-desc {
            font-size: 0.85rem;
            color: #6b7280;
        }
        .onboarding-close {
            margin-top: 24px;
            display: inline-block;
            font-size: 0.9rem;
            color: #9ca3af;
            cursor: pointer;
            text-decoration: underline;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* ── PRINT CSS ───────────────────────────────────────────── */
        @media print {
            .topbar, .editor-panel, .preview-panel, .page-limit-line, .page-limit-label, #overflowWarning { display: none !important; }
            body { overflow: visible; background: white; height: auto; }
            #printFrame { display: block !important; }
        }
    </style>
</head>
<body>
<?php include_once 'includes/navbar.php'; ?>

<!-- ONBOARDING MODAL -->
<div id="onboardingModal" class="onboarding-overlay" style="display: none;">
    <div class="onboarding-card">
        <div class="onboarding-title">Welcome to Lakshya Resume Builder</div>
        <div class="onboarding-text">
            If you don't have a resume created yet, continue with building it here. Once generated, it will be used for all internships and jobs you apply for.
        </div>
        
        <div class="onboarding-options" style="display: flex; justify-content: center;">
            <div class="option-box" onclick="closeOnboarding()" style="width: 100%; max-width: 300px;">
                <i class="fas fa-magic"></i>
                <div class="option-title">Build Now</div>
                <div class="option-desc">Create from scratch using our AI templates</div>
            </div>
            <!-- Upload option temporarily hidden
            <div class="option-box" onclick="document.getElementById('externalPdfInput').click()">
                <i class="fas fa-file-upload"></i>
                <div class="option-title">Upload PDF</div>
                <div class="option-desc">I already have a formal resume (PDF). This uploaded file will be used for all internships and jobs automatically.</div>
            </div>
            -->
        </div>
    </div>
</div>

<!-- Hidden File Input for External Upload -->
<input type="file" id="externalPdfInput" style="display:none;" accept=".pdf" onchange="uploadExistingPdf(this)">

<!-- TOP BAR -->
<div class="topbar">
    <button class="tb-btn tb-btn-back" onclick="window.location.href='dashboard.php'">
        <i class="fas fa-arrow-left"></i> Dashboard
    </button>
    <div class="topbar-sep"></div>
    <div class="topbar-brand"><i class="fas fa-file-alt"></i> Resume Builder</div>
    <div class="topbar-sep"></div>

    <div class="template-switcher">
        <button class="tpl-btn active" data-tpl="professional_ats" onclick="switchTemplate(this)">ATS Classic</button>
        <button class="tpl-btn" data-tpl="modern_creative" onclick="switchTemplate(this)">Modern</button>
        <button class="tpl-btn" data-tpl="minimal_clean" onclick="switchTemplate(this)">Minimal</button>
    </div>

    <div class="topbar-actions">
        <button class="tb-btn tb-btn-save" id="btn-sync-portfolio" onclick="syncPortfolio()" title="Import new items from your profile portfolio">
            <i class="fas fa-sync-alt"></i> Sync Portfolio
        </button>
        <div class="topbar-sep" style="height: 32px;"></div>
        <button class="tb-btn tb-btn-save" id="saveBtn" onclick="saveResume(false)">
            <i class="fas fa-save"></i> Save
        </button>
        <button class="tb-btn tb-btn-pdf" onclick="printResume()">
            <i class="fas fa-download"></i> PDF
        </button>
    </div>
</div>

<!-- MAIN LAYOUT -->
<div class="builder-layout">

    <!-- ── LEFT: EDITOR ── -->
    <div class="editor-panel" id="editorPanel">

        <!-- Personal Info -->
        <div class="section-block open" id="sec-personal">
            <div class="section-header" onclick="toggleSection('sec-personal')">
                <div class="section-header-left">
                    <i class="fas fa-user main-icon"></i>
                    <span>Personal Information</span>
                </div>
                <i class="fas fa-chevron-down section-chevron"></i>
            </div>
            <div class="section-body">
                <div class="field-row">
                    <div class="field-group">
                        <label>Full Name *</label>
                        <input type="text" id="f_full_name" placeholder="Your Name" oninput="schedulePreview()">
                    </div>
                    <div class="field-group">
                        <label>Email *</label>
                        <input type="email" id="f_email" placeholder="you@email.com" oninput="schedulePreview()">
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-group">
                        <label>Phone</label>
                        <input type="text" id="f_phone" placeholder="+91 98765 43210" oninput="schedulePreview()">
                    </div>
                    <div class="field-group">
                        <label>Gender</label>
                        <select id="f_gender" onchange="schedulePreview()">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="field-group">
                    <label>Permanent Address</label>
                    <textarea id="f_address" rows="2" placeholder="House No, Street, Area, Pin Code..." oninput="schedulePreview()"></textarea>
                </div>
                <div class="field-row">
                    <div class="field-group">
                        <label>City / State</label>
                        <input type="text" id="f_location" placeholder="City, State" oninput="schedulePreview()">
                    </div>
                    <div class="field-group">
                        <label>LinkedIn URL</label>
                        <input type="text" id="f_linkedin" placeholder="linkedin.com/in/you" oninput="schedulePreview()">
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-group">
                        <label>GitHub URL</label>
                        <input type="text" id="f_github" placeholder="github.com/you" oninput="schedulePreview()">
                    </div>
                    <div class="field-group">
                        <label>Portfolio / Other Link</label>
                        <input type="text" id="f_portfolio" placeholder="yourportfolio.com" oninput="schedulePreview()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="section-block open" id="sec-summary">
            <div class="section-header" onclick="toggleSection('sec-summary')">
                <div class="section-header-left">
                    <i class="fas fa-align-left main-icon"></i>
                    <span>Professional Summary</span>
                </div>
                <i class="fas fa-chevron-down section-chevron"></i>
            </div>
            <div class="section-body">
                <div class="field-group">
                    <textarea id="f_summary" rows="4" placeholder="A concise 2-3 line summary of your strengths and goals..." oninput="schedulePreview()"></textarea>
                </div>
            </div>
        </div>

        <!-- Education -->
        <div class="section-block open" id="sec-education">
            <div class="section-header" onclick="toggleSection('sec-education')">
                <div class="section-header-left">
                    <i class="fas fa-graduation-cap main-icon"></i>
                    <span>Education</span>
                </div>
                <i class="fas fa-chevron-down section-chevron"></i>
            </div>
            <div class="section-body">
                <div id="education-list"></div>
                <button class="add-entry-btn" onclick="addEntry('education')">
                    <i class="fas fa-plus"></i> Add Education
                </button>
            </div>
        </div>

        <!-- Experience -->
        <div class="section-block" id="sec-experience">
            <div class="section-header" onclick="toggleSection('sec-experience')">
                <div class="section-header-left">
                    <i class="fas fa-briefcase main-icon"></i>
                    <span>Work Experience</span>
                </div>
                <i class="fas fa-chevron-down section-chevron"></i>
            </div>
            <div class="section-body">
                <div id="experience-list"></div>
                <button class="add-entry-btn" onclick="addEntry('experience')">
                    <i class="fas fa-plus"></i> Add Experience
                </button>
            </div>
        </div>

        <!-- Projects -->
        <div class="section-block open" id="sec-projects">
            <div class="section-header" onclick="toggleSection('sec-projects')">
                <div class="section-header-left">
                    <i class="fas fa-code main-icon"></i>
                    <span>Projects</span>
                    <a href="javascript:void(0)" onclick="event.stopPropagation(); syncPortfolio();" class="header-util-btn btn-maroon-soft" style="margin-left: auto;">
                        <i class="fas fa-sync-alt"></i> Import Portfolio
                    </a>
                </div>
                <i class="fas fa-chevron-down section-chevron"></i>
            </div>
            <div class="section-body">
                <div id="projects-list"></div>
                <button class="add-entry-btn" onclick="addEntry('projects')">
                    <i class="fas fa-plus"></i> Add Project
                </button>
            </div>
        </div>

        <!-- Skills -->
        <div class="section-block open" id="sec-skills">
            <div class="section-header" onclick="toggleSection('sec-skills')">
                <div class="section-header-left">
                    <i class="fas fa-tools main-icon"></i>
                    <span>Skills</span>
                    <div style="display: flex; gap: 8px; margin-left: auto; align-items: center;">
                        <a href="javascript:void(0)" onclick="event.stopPropagation(); syncPortfolio();" class="header-util-btn btn-maroon-soft">
                            <i class="fas fa-sync-alt"></i> Import Portfolio
                        </a>
                        <a href="javascript:void(0)" onclick="event.stopPropagation(); importSkillsFromProfile();" class="header-util-btn btn-success-soft">
                            <i class="fas fa-sync-alt"></i> Profile Sync
                        </a>
                    </div>
                </div>
                <i class="fas fa-chevron-down section-chevron"></i>
            </div>
            <div class="section-body">
                <div id="tech-groups-list"></div>
                <button class="add-entry-btn" onclick="addSkillGroup()" style="border-style: solid; border-width: 1px; margin-bottom: 20px;">
                    <i class="fas fa-layer-group"></i> Add Skill Category
                </button>

                <div class="field-group">
                    <label>Soft Skills</label>
                    <div class="tags-wrap" id="tags-soft" onclick="this.querySelector('.tag-input-el').focus()">
                        <input class="tag-input-el" placeholder="Type skill, press Enter" onkeydown="handleTagKey(event,'soft')">
                    </div>
                </div>
                <div class="field-group">
                    <label>Languages</label>
                    <div class="tags-wrap" id="tags-languages" onclick="this.querySelector('.tag-input-el').focus()">
                        <input class="tag-input-el" placeholder="Type language, press Enter" onkeydown="handleTagKey(event,'languages')">
                    </div>
                </div>
            </div>
        </div>

        <!-- Certifications -->
        <div class="section-block" id="sec-certifications">
            <div class="section-header" onclick="toggleSection('sec-certifications')">
                <div class="section-header-left">
                    <i class="fas fa-certificate main-icon"></i>
                    <span>Certifications</span>
                </div>
                <i class="fas fa-chevron-down section-chevron"></i>
            </div>
            <div class="section-body">
                <div id="certifications-list"></div>
                <button class="add-entry-btn" onclick="addEntry('certifications')">
                    <i class="fas fa-plus"></i> Add Certification
                </button>
            </div>
        </div>

        <!-- Achievements -->
        <div class="section-block" id="sec-achievements">
            <div class="section-header" onclick="toggleSection('sec-achievements')">
                <div class="section-header-left">
                    <i class="fas fa-trophy main-icon"></i>
                    <span>Achievements</span>
                </div>
                <i class="fas fa-chevron-down section-chevron"></i>
            </div>
            <div class="section-body">
                <div id="achievements-list"></div>
                <button class="add-entry-btn" onclick="addEntry('achievements')">
                    <i class="fas fa-plus"></i> Add Achievement
                </button>
            </div>
        </div>

    </div><!-- /editor-panel -->

    <!-- ── RIGHT: PREVIEW ── -->
    <div class="preview-panel">
        <div id="overflowWarning"><i class="fas fa-exclamation-triangle"></i> RESUME EXCEEDS 1 PAGE</div>
        <div id="resumePreview" class="resume-paper">
            <div style="text-align:center; padding: 60px 0; color: #9ca3af;">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
                <p style="margin-top:12px; font-size:0.9rem;">Loading your resume...</p>
            </div>
        </div>
    </div>

</div><!-- /builder-layout -->

<!-- TOAST -->
<div id="saveToast"><i class="fas fa-check-circle" style="color:#34d399;"></i> <span id="toastMsg">Saved</span></div>

<!-- PRINT FRAME (hidden, used for PDF) -->
<script>
    const HANDLER_URL = 'resume_builder_handler.php';
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" crossorigin="anonymous"></script>
<script src="resume_builder.js?v=<?php echo time(); ?>"></script>
</body>
</html>

