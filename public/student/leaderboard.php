<?php
/**
 * Student Leaderboard - Premium UI with Insight Panel
 */

require_once __DIR__ . '/../../config/bootstrap.php';
use App\Services\LeaderboardService;

requireRole(ROLE_STUDENT);

$myUsn = getUsername();
$myDept = getDepartment();
$myInst = getInstitution();

$pageTitle = "Leaderboard | Lakshya";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <script>
        const originalWarn = console.warn;
        console.warn = function(...args) {
            if (args[0] && typeof args[0] === 'string' && args[0].includes('cdn.tailwindcss.com should not be used')) return;
            originalWarn(...args);
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/Flip.min.js"></script>
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
    <link rel="shortcut icon" href="data:image/x-icon;," type="image/x-icon">

    <style>
        :root {
            --primary: #800000;
            --secondary: #4a0000;
            --accent: #D4AF37;
            --gold: #D4AF37;
            --silver: #94A3B8;
            --bronze: #CD7F32;
            --bg-glass: rgba(255, 255, 255, 0.88);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #faf9f7;
            color: #0f172a;
            overflow-x: hidden;
            position: relative;
        }

        .outfit { font-family: 'Outfit', sans-serif; }

        /* Background Animated Blobs */
        .vibe-container {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
        }
        .blob {
            position: absolute;
            width: 500px; height: 500px;
            filter: blur(90px);
            border-radius: 50%;
            opacity: 0.12;
            animation: move 20s infinite alternate;
        }
        .blob-1 { top: -120px; right: -80px; background: radial-gradient(circle, #800000, #4a0000); animation-duration: 25s; }
        .blob-2 { bottom: -150px; left: -100px; background: radial-gradient(circle, #D4AF37, #a07c10); opacity: 0.10; animation-duration: 30s; }
        .blob-3 { top: 35%; left: 25%; width: 350px; height: 350px; background: radial-gradient(circle, #800000, transparent); opacity: 0.07; animation-duration: 22s; }

        @keyframes move {
            from { transform: translate(0, 0) scale(1); }
            to { transform: translate(80px, 60px) scale(1.12); }
        }

        /* Glass Card */
        .glass-card {
            background: var(--bg-glass);
            backdrop-filter: blur(28px) saturate(200%);
            -webkit-backdrop-filter: blur(28px) saturate(200%);
            border: 1px solid rgba(255,255,255,0.85);
            box-shadow: 0 8px 32px rgba(0,0,0,0.04);
            border-radius: 24px;
        }

        /* Rank Row */
        .rank-row {
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .rank-row.is-me {
            cursor: pointer;
        }
        .rank-row.is-me:hover {
            transform: scale(1.01) translateY(-3px);
            background: rgba(255,255,255,1);
            box-shadow: 0 20px 50px rgba(128,0,0,0.09);
            border-color: rgba(128,0,0,0.18);
        }
        .rank-row.is-me.selected {
            box-shadow: 0 0 0 2px #800000, 0 20px 50px rgba(128,0,0,0.12);
        }

        /* Rank Badges */
        .rank-badge {
            width: 42px; height: 42px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .rank-1 { background: linear-gradient(135deg, #FFD93D, #FF8400); color: #fff; }
        .rank-2 { background: linear-gradient(135deg, #DEE4E7, #94A3B8); color: #fff; }
        .rank-3 { background: linear-gradient(135deg, #FFBB5C, #C63D2F); color: #fff; }

        /* Podium */
        .podium-container {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 20px;
            margin-bottom: 60px;
            perspective: 2000px;
        }
        .podium-step {
            flex: 1;
            max-width: 340px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .podium-card {
            width: 100%;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 1px solid rgba(255,255,255,0.4);
            z-index: 10;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .podium-base {
            width: 100%;
            margin-top: -45px;
            border-radius: 24px 24px 0 0;
            display: flex;
            align-items: flex-end; /* Align to bottom */
            justify-content: center;
            padding-bottom: 10px; /* Push number up slightly from absolute bottom */
            font-family: 'Outfit', sans-serif;
            font-weight: 900;
            font-size: 4rem;
            color: rgba(255,255,255,0.25);
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15), inset 0 2px 10px rgba(255,255,255,0.3);
        }
        .podium-base::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 50%, rgba(0,0,0,0.1) 100%);
            pointer-events: none;
        }
        
        /* Rank Specific Steps */
        .step-1 .podium-base { 
            height: 220px; 
            background: linear-gradient(135deg, #FFD700 0%, #D4AF37 50%, #B8860B 100%); 
            z-index: 5;
            box-shadow: 0 0 40px rgba(212, 175, 55, 0.3), inset 0 0 20px rgba(255,255,255,0.4);
        }
        .step-1 .podium-card {
            transform: translateY(-5px);
            animation: float-1 4s ease-in-out infinite;
        }
        .step-2 .podium-base { 
            height: 160px; 
            background: linear-gradient(135deg, #F8FAFC 0%, #CBD5E1 50%, #94A3B8 100%); 
            z-index: 4;
        }
        .step-2 .podium-card {
            animation: float-2 5s ease-in-out infinite;
        }
        .step-3 .podium-base { 
            height: 120px; 
            background: linear-gradient(135deg, #FFE4B5 0%, #CD7F32 50%, #A0522D 100%); 
            z-index: 3;
        }
        .step-3 .podium-card {
            animation: float-3 6s ease-in-out infinite;
        }

        @keyframes float-1 { 0%, 100% { transform: translateY(-5px); } 50% { transform: translateY(-15px); } }
        @keyframes float-2 { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        @keyframes float-3 { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }

        .podium-card.winner {
            border-color: rgba(212, 175, 55, 0.5);
            background: rgba(255, 255, 255, 0.85);
            box-shadow: 0 25px 70px rgba(212, 175, 55, 0.25);
        }
        .podium-card.is-me.selected { box-shadow: 0 0 0 4px #800000, 0 30px 80px rgba(128,0,0,0.2); }

        @media (max-width: 768px) {
            .podium-container { flex-direction: column; align-items: stretch; gap: 32px; }
            .podium-step { max-width: 100%; }
            .podium-base { height: 24px !important; margin-top: 10px; border-radius: 12px; font-size: 1.5rem; }
            .step-1 .podium-card, .step-2 .podium-card, .step-3 .podium-card { animation: none; }
        }

        /* --- INSIGHT DRAWER --- */
        #insight-drawer {
            position: fixed;
            right: 0;
            top: 60px; /* below navbar */
            bottom: 0;
            width: 440px;
            max-width: 96vw;
            background: #fff;
            box-shadow: -10px 0 60px rgba(0,0,0,0.14);
            z-index: 800;
            transform: translateX(110%);
            transition: transform 0.45s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            scrollbar-width: none;
            border-left: 1px solid rgba(0,0,0,0.06);
        }
        #insight-drawer.open { transform: translateX(0); }
        #insight-drawer::-webkit-scrollbar { display: none; }
        #insight-overlay {
            position: fixed;
            top: 60px; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.18);
            z-index: 799;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.35s ease;
        }
        #insight-overlay.open { opacity: 1; pointer-events: all; }

        .drawer-header-gold { background: linear-gradient(135deg, #800000 0%, #4a0000 100%); }

        .insight-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .suggestion-item {
            display: flex; gap: 14px; align-items: flex-start;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
            animation: fadeSlideIn 0.4s ease both;
        }
        .suggestion-item:last-child { border-bottom: none; }

        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .suggestion-icon {
            width: 40px; height: 40px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 1.1rem;
        }

        /* Gap bar */
        .gap-bar-track {
            background: #f1f5f9; border-radius: 50px; height: 8px; width: 100%;
        }
        .gap-bar-fill {
            height: 8px; border-radius: 50px;
            background: linear-gradient(90deg, #800000, #D4AF37);
            transition: width 1.2s cubic-bezier(0.4,0,0.2,1);
        }

        /* Sticky Rank */
        .sticky-rank {
            position: fixed;
            bottom: 2rem; left: 50%;
            transform: translateX(-50%);
            width: 90%; max-width: 800px;
            z-index: 500;
        }

        /* Shimmer */
        .shimmer {
            background: linear-gradient(90deg, #f8fafc 25%, #f1f5f9 50%, #f8fafc 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Toggle buttons */
        .btn-toggle { position: relative; overflow: hidden; z-index: 1; }
        .btn-toggle::after {
            content: '';
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            z-index: -1; opacity: 0;
            transition: opacity 0.3s ease;
        }
        .btn-toggle.active { color: white; }
        .btn-toggle.active::after { opacity: 1; }

        /* Loader */
        #leaderboard-loader {
            position: fixed; inset: 0;
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(12px);
            z-index: 1000;
            display: none; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.3s ease;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }
        @keyframes bounceIn {
            from { opacity: 0; transform: scale(0.3); }
            to { opacity: 1; transform: scale(1); }
        }
        .loader-icon {
            font-size: 3rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            animation: pulse 1.5s infinite;
        }

        /* Celebration Overlay */
        #celebration-overlay {
            position: fixed; inset: 0;
            pointer-events: none; z-index: 1000;
            display: none; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.4);
            backdrop-filter: blur(8px);
        }

        /* Avatar letter circle */
        .avatar-circle {
            background: linear-gradient(135deg, #800000 0%, #D4AF37 100%);
            color: white;
            font-weight: 900;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.5px;
        }

        /* Crown glow for #1 */
        @keyframes crownGlow {
            0%, 100% { filter: drop-shadow(0 0 6px #D4AF37) drop-shadow(0 0 14px #D4AF37aa); }
            50% { filter: drop-shadow(0 0 12px #D4AF37) drop-shadow(0 0 28px #D4AF37cc); }
        }
        .crown-glow { animation: crownGlow 2.5s ease-in-out infinite; font-size: 1.3rem; }

        /* Stat chip */
        .stat-chip {
            background: #fff8f0;
            border: 1px solid #fde8c0;
            border-radius: 12px;
            padding: 10px 16px;
            text-align: center;
        }

        /* How it Works Modal */
        #info-modal {
            position: fixed; inset: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(12px);
            z-index: 2000;
            display: none; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.4s ease;
            padding: 20px;
        }
        #info-modal.open { display: flex; opacity: 1; }
        .info-card {
            background: rgba(255, 255, 255, 0.95);
            width: 100%; max-width: 600px;
            border-radius: 32px;
            box-shadow: 0 40px 100px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: modalSlideUp 0.5s cubic-bezier(0.19, 1, 0.22, 1);
        }
        @keyframes modalSlideUp {
            from { transform: translateY(40px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .info-header {
            background: #fff;
            padding: 40px 40px 20px;
            position: relative;
            text-align: center;
        }
        .info-body { padding: 0 40px 40px; }
        .scoring-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }
        .scoring-card {
            background: #f8fafc;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s ease;
        }
        .scoring-card:hover { border-color: #80000033; transform: translateY(-2px); }
        .scoring-card.primary { background: #fff1f2; border-color: #fecaca; }
        
        .info-close {
            position: absolute; top: 24px; right: 24px;
            width: 32px; height: 32px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #64748b;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s;
        }
        .info-close:hover { background: #e2e8f0; color: #0f172a; }
    </style>
</head>
<body class="min-h-screen">

    <div class="vibe-container">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>

    <?php include 'includes/navbar.php'; ?>

    <!-- Loader -->
    <div id="leaderboard-loader" style="display:none; align-items:center; justify-content:center;">
        <div style="text-align:center; animation:bounceIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;">
            <div class="loader-icon outfit font-black"><i class="fas fa-crown"></i></div>
            <h2 class="text-xl font-black outfit text-slate-800 mt-3">Summarizing Greatness...</h2>
            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">Syncing Elite Data</p>
        </div>
    </div>

    <main class="container mx-auto px-4 py-8 pb-36">

        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-5">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <span class="crown-glow">👑</span>
                    <h1 class="text-3xl font-black outfit text-slate-900">Elite Leaderboard</h1>
                    <button onclick="openInfoModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:bg-[#800000]/10 hover:text-[#800000] flex items-center justify-center transition-all ml-1 shadow-sm border border-slate-200" title="How it works">
                        <i class="fas fa-info text-xs"></i>
                    </button>
                </div>
                <p class="text-slate-500 font-medium tracking-tight">Real-time performance rankings based on AI Pillars &amp; Portfolio. <span class="text-[#800000] font-semibold">Click on your card for personalised insights.</span></p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-xs text-slate-400 font-bold uppercase tracking-wider hidden md:flex items-center gap-2">
                    <span class="w-2 h-2 bg-green-400 rounded-full inline-block animate-pulse"></span>Live
                </div>
                <div class="flex bg-white p-1.5 rounded-2xl border border-slate-200 shadow-sm">
                    <button onclick="changeView('local')" id="btn-local" class="btn-toggle active view-btn px-7 py-2.5 rounded-xl text-sm font-bold transition-all duration-300">
                        <i class="fas fa-building mr-1.5 text-xs"></i> Department
                    </button>
                    <button onclick="changeView('global')" id="btn-global" class="btn-toggle view-btn px-7 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 text-slate-500 hover:text-slate-700">
                        <i class="fas fa-globe mr-1.5 text-xs"></i> Global
                    </button>
                </div>
            </div>
        </div>

        <!-- Podium -->
        <div id="podium-container" class="podium-container pt-12">
            <!-- Rendered by JS -->
        </div>

        <!-- Table Header -->
        <div class="hidden md:grid grid-cols-12 px-7 py-3 text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">
            <div class="col-span-1">Rank</div>
            <div class="col-span-8">Student</div>
            <div class="col-span-3 text-right">Total Points</div>
        </div>

        <!-- Rankings List -->
        <div id="rankings-list" class="space-y-3">
            <?php for($i=0; $i<5; $i++): ?>
                <div class="glass-card h-20 rounded-2xl shimmer opacity-50"></div>
            <?php endfor; ?>
        </div>
    </main>

    <!-- Sticky My Rank Bar -->
    <div id="my-sticky-rank" class="sticky-rank hidden">
        <div class="glass-card px-6 py-4 rounded-[28px] border border-red-100 shadow-[0_20px_50px_rgba(128,0,0,0.12)] flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div id="my-rank-badges" class="bg-gradient-to-br from-[#800000] to-[#4a0000] text-white w-12 h-12 flex items-center justify-center rounded-2xl font-black text-lg shadow-lg ring-2 ring-[#D4AF37]/40">--</div>
                <div>
                    <h4 class="font-black text-slate-800 outfit text-base">Your Performance</h4>
                    <p class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Keep pushing to climb!</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <div id="my-total-score" class="text-2xl font-black outfit text-transparent bg-clip-text bg-gradient-to-r from-[#800000] to-[#4a0000]">--</div>
                    <div class="text-[10px] uppercase font-black text-[#800000]/60 tracking-[0.2em]">Total Points</div>
                </div>
                <button onclick="openMyInsight()" class="bg-gradient-to-br from-[#800000] to-[#4a0000] text-white px-4 py-2 rounded-xl text-xs font-black flex items-center gap-2 hover:opacity-90 transition shadow-lg">
                    <i class="fas fa-chart-line"></i> My Insight
                </button>
            </div>
        </div>
    </div>

    <!-- Insight Drawer Overlay -->
    <div id="insight-overlay" onclick="closeDrawer()"></div>

    <!-- Insight Drawer -->
    <div id="insight-drawer">
        <!-- Drawer Header (sticky inside drawer scroll) -->
        <div class="drawer-header-gold p-6 text-white sticky top-0 z-20">
            <div class="flex justify-between items-start mb-4">
                <div class="flex items-center gap-3">
                    <div id="drawer-avatar" class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl font-black outfit shadow-lg bg-white/20 backdrop-blur"></div>
                    <div>
                        <p class="text-white/70 text-xs font-bold uppercase tracking-widest">Rank Insights</p>
                        <h2 id="drawer-name" class="text-xl font-black outfit leading-tight"></h2>
                        <p id="drawer-dept" class="text-white/60 text-xs font-semibold uppercase tracking-wider mt-0.5"></p>
                    </div>
                </div>
                <button onclick="closeDrawer()" class="w-9 h-9 rounded-xl bg-white/20 hover:bg-white/30 flex items-center justify-center transition">
                    <i class="fas fa-times text-white text-sm"></i>
                </button>
            </div>
            <!-- Score Row -->
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white/15 rounded-2xl p-3 text-center">
                    <div id="drawer-score" class="text-2xl font-black outfit"></div>
                    <div class="text-[10px] text-white/60 font-bold uppercase tracking-wider mt-0.5">Score</div>
                </div>
                <div class="bg-white/15 rounded-2xl p-3 text-center">
                    <div id="drawer-rank" class="text-2xl font-black outfit"></div>
                    <div class="text-[10px] text-white/60 font-bold uppercase tracking-wider mt-0.5">Rank</div>
                </div>
                <div class="bg-white/15 rounded-2xl p-3 text-center">
                    <div id="drawer-rank-change" class="text-2xl font-black outfit"></div>
                    <div class="text-[10px] text-white/60 font-bold uppercase tracking-wider mt-0.5">Change</div>
                </div>
            </div>
        </div>

        <!-- Drawer Body -->
        <div class="p-6 space-y-6">

            <!-- Gap from #1 -->
            <div id="gap-section" class="hidden">
                <div class="flex justify-between items-center mb-2">
                    <p class="text-sm font-bold text-slate-700"><i class="fas fa-crosshairs text-[#800000] mr-1.5"></i> Gap from #1</p>
                    <span id="gap-label" class="text-xs font-black text-[#800000]"></span>
                </div>
                <div class="gap-bar-track">
                    <div id="gap-bar-fill" class="gap-bar-fill" style="width:0%"></div>
                </div>
                <p id="gap-desc" class="text-xs text-slate-400 mt-1.5 font-medium"></p>
            </div>

            <!-- Motivation / Status Message -->
            <div id="motivation-block" class="rounded-2xl p-4 border">
                <p id="motivation-icon" class="text-2xl mb-2"></p>
                <h3 id="motivation-title" class="font-black text-sm outfit mb-1"></h3>
                <p id="motivation-text" class="text-sm text-slate-600 leading-relaxed"></p>
            </div>

            <!-- Suggestions -->
            <div>
                <h3 class="text-sm font-black text-slate-800 outfit mb-3 flex items-center gap-2">
                    <span class="w-6 h-6 bg-[#800000] text-white rounded-lg flex items-center justify-center text-xs"><i class="fas fa-lightbulb"></i></span>
                    Action Plan
                </h3>
                <div id="suggestions-list"></div>
            </div>
        </div>
    </div>

    <!-- Celebration Overlay -->
    <div id="celebration-overlay">
        <div class="text-center">
            <lottie-player src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f389/lottie.json" background="transparent" speed="1" style="width:300px;height:300px;margin:0 auto;" autoplay></lottie-player>
            <h2 class="text-4xl font-black outfit text-[#800000] mt-[-30px] mb-2 drop-shadow-lg" id="celebration-text">Congratulations!</h2>
            <p class="text-xl text-slate-700 font-semibold" id="celebration-subtext">You've entered the Top 10!</p>
        </div>
    </div>

    <!-- How it Works Modal -->
    <div id="info-modal" onclick="closeInfoModal(event)">
        <div class="info-card" onclick="event.stopPropagation()">
            <div class="info-header text-slate-900">
                <button class="info-close" onclick="closeInfoModal(event)">
                    <i class="fas fa-times"></i>
                </button>
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 bg-[#800000] rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg ring-4 ring-red-50">
                        <i class="fas fa-brain"></i>
                    </div>
                </div>
                <h2 class="text-2xl font-black outfit leading-tight">Leaderboard Scoring</h2>
                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-2">Dynamic Ranking Algorithm</p>
            </div>
            <div class="info-body">
                <div class="scoring-grid">
                    <div class="scoring-card primary">
                        <div class="text-[10px] font-black text-[#800000] uppercase tracking-wider mb-2">AI Pillars</div>
                        <div class="text-3xl font-black outfit text-slate-800">70<span class="text-sm ml-0.5 text-[#800000] opacity-50">%</span></div>
                        <p class="text-[10px] text-slate-500 font-bold mt-2 uppercase">Tech · Apt · HR</p>
                    </div>
                    <div class="scoring-card">
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-wider mb-2">Portfolio</div>
                        <div class="text-3xl font-black outfit text-slate-800">30<span class="text-sm ml-0.5 opacity-30">%</span></div>
                        <p class="text-[10px] text-slate-500 font-bold mt-2 uppercase">Skills · Projects</p>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-3xl p-6 border border-slate-100">
                    <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i class="fas fa-circle-check text-green-500"></i>
                        Scoring & Verification Rules
                    </h4>
                    <ul class="text-[13px] text-slate-600 font-medium space-y-4 leading-relaxed">
                        <li class="flex items-start gap-3">
                            <span class="w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-[10px] shrink-0 mt-0.5"><i class="fas fa-check"></i></span>
                            <span>Each verified <b>Skill</b> adds 2 points (max 50)</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-[10px] shrink-0 mt-0.5"><i class="fas fa-check"></i></span>
                            <span>Each verified <b>Project</b> adds 5 points (max 50)</span>
                        </li>
                        <li class="flex items-start gap-3 p-3 bg-red-50 rounded-2xl border border-red-100">
                            <span class="w-5 h-5 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-[10px] shrink-0 mt-0.5"><i class="fas fa-clock"></i></span>
                            <span class="text-red-700 font-bold">Inactivity Decay: Lose 1 point for every 24 hours of inactivity between assessments. Consistency is key!</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-[10px] shrink-0 mt-0.5"><i class="fas fa-info"></i></span>
                            <span class="text-slate-500 font-semibold italic">Important: Only verified items contribute to your score.</span>
                        </li>
                    </ul>
                </div>
                
                <button onclick="closeInfoModal(event)" class="w-full mt-6 bg-[#800000] text-white py-4 rounded-2xl font-black outfit text-sm hover:opacity-90 transition shadow-lg">
                    Understood
                </button>
            </div>
        </div>
    </div>

    <script>
        function openInfoModal() {
            const modal = document.getElementById('info-modal');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('open'), 10);
        }
        function closeInfoModal() {
            const modal = document.getElementById('info-modal');
            modal.classList.remove('open');
            setTimeout(() => modal.style.display = 'none', 400);
        }

        let currentView = 'local';
        let currentData = [];
        let myUsn = '<?php echo $myUsn; ?>';
        let selectedUsn = null;

        gsap.registerPlugin(Flip);

        /* ===== FETCH ===== */
        async function fetchRankings(showLoader = false) {
            const loader = document.getElementById('leaderboard-loader');
            if (showLoader) {
                loader.style.display = 'flex';
                gsap.to(loader, { opacity: 1, duration: 0.3 });
            }
            try {
                const res = await fetch(`leaderboard_handler.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `view=${currentView}`
                });
                const data = await res.json();
                if (data.success) {
                    updateLeaderboard(data.rankings);
                }
            } catch (err) {
                console.error("Failed to fetch rankings", err);
            } finally {
                if (showLoader) {
                    gsap.to(loader, { opacity: 0, duration: 0.3, delay: 0.5, onComplete: () => loader.style.display = 'none' });
                }
            }
        }

        /* ===== RENDER LEADERBOARD ===== */
        function updateLeaderboard(newList) {
            const listEl = document.getElementById('rankings-list');
            const stickyEl = document.getElementById('my-sticky-rank');

            const state = Flip.getState(".rank-row");
            checkRankChanges(newList);
            currentData = newList;

            if (newList.length === 0) {
                document.getElementById('podium-container').innerHTML = '';
                listEl.innerHTML = `<div class="p-12 text-center glass-card"><div class="w-16 h-16 mx-auto bg-slate-100 rounded-full flex items-center justify-center mb-4 text-slate-400 text-2xl"><i class="fas fa-ghost"></i></div><h3 class="text-lg font-bold text-slate-700">No Rankings Yet</h3><p class="text-sm text-slate-500 mt-1">No students have completed assessments for this view.</p></div>`;
                stickyEl.classList.add('hidden');
                return;
            }

            renderPodium(newList.slice(0, 3));

            const remaining = newList.slice(3, 20);
            listEl.innerHTML = remaining.map(s => renderStudentRow(s)).join('');

            // Animate
            Flip.from(state, {
                duration: 0.8, ease: "power2.inOut", stagger: 0.05,
                onEnter: els => gsap.fromTo(els, { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.6 }),
                onLeave: els => gsap.to(els, { opacity: 0, y: -20, duration: 0.6 })
            });

            // My sticky
            const myData = newList.find(s => s.usn.toLowerCase() === myUsn.toLowerCase());
            if (myData) {
                document.getElementById('my-rank-badges').textContent = `#${myData.rank}`;
                document.getElementById('my-total-score').textContent = myData.total;
                stickyEl.classList.toggle('hidden', myData.rank <= 10);
            }
        }

        /* ===== RENDER STUDENT ROW ===== */
        function renderStudentRow(s) {
            const isMe = s.usn.toLowerCase() === myUsn.toLowerCase();
            const prevRank = parseInt(s.previous_rank) || s.rank;
            let rankIndicator = '';
            if (prevRank > s.rank) {
                rankIndicator = `<div class="flex items-center text-green-500 text-[10px] font-bold"><i class="fas fa-arrow-up mr-0.5 text-[8px]"></i>+${prevRank - s.rank}</div>`;
            } else if (prevRank < s.rank) {
                rankIndicator = `<div class="flex items-center text-red-400 text-[10px] font-bold"><i class="fas fa-arrow-down mr-0.5 text-[8px]"></i>-${s.rank - prevRank}</div>`;
            } else {
                rankIndicator = `<div class="text-slate-300 text-[10px] font-bold">–</div>`;
            }

            return `
                <div data-flip-id="${s.usn}" ${isMe ? `onclick="openInsight('${s.usn}')"` : ''}
                     class="rank-row glass-card p-4 md:px-7 md:py-5 flex md:grid md:grid-cols-12 items-center gap-4 ${isMe ? 'is-me ring-2 ring-[#800000] bg-red-50/30' : ''}">
                    <div class="col-span-1 pr-3 border-r border-slate-100">
                        <div class="flex flex-col items-center gap-1">
                            <span class="rank-badge ${s.rank <= 3 ? 'rank-' + s.rank : 'bg-slate-50 text-slate-400 border border-slate-200'}">${s.rank}</span>
                            ${rankIndicator}
                        </div>
                    </div>
                    <div class="col-span-8 flex items-center gap-4 pl-2">
                        <div class="w-11 h-11 rounded-xl avatar-circle flex-shrink-0 flex items-center justify-center text-base shadow">
                            ${s.name.charAt(0).toUpperCase()}
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-slate-800 text-sm md:text-base flex items-center gap-2 flex-wrap">
                                ${s.name}
                                ${isMe ? '<span class="px-2 py-0.5 bg-[#800000] text-white text-[9px] rounded-full font-black uppercase tracking-wider">You</span>' : ''}
                                ${s.rank === 1 ? '<span class="crown-glow">👑</span>' : ''}
                            </div>
                            <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold mt-0.5 truncate">${s.discipline} · ${s.institution}</div>
                        </div>
                    </div>
                    <div class="col-span-3 text-right">
                        <div class="text-2xl md:text-3xl font-black outfit text-slate-900">${s.total}</div>
                        <div class="text-[10px] text-[#800000] font-black uppercase tracking-widest">Total Points</div>
                    </div>
                </div>
            `;
        }

        /* ===== RENDER PODIUM ===== */
        function renderPodium(top3) {
            const podiumEl = document.getElementById('podium-container');
            if (top3.length === 0) {
                podiumEl.innerHTML = '';
                return;
            }

            // Order for podium: [2nd, 1st, 3rd]
            const podiumOrder = [];
            if (top3[1]) podiumOrder.push(top3[1]); // 2nd
            if (top3[0]) podiumOrder.push(top3[0]); // 1st
            if (top3[2]) podiumOrder.push(top3[2]); // 3rd

            podiumEl.innerHTML = podiumOrder.map(s => {
                const rankNum = s.rank;
                const isWinner = rankNum === 1;
                const badgeClass = rankNum === 1 ? 'rank-1' : (rankNum === 2 ? 'rank-2' : 'rank-3');
                const isMe = s.usn.toLowerCase() === myUsn.toLowerCase();

                return `
                    <div class="podium-step step-${rankNum}" data-rank="${rankNum}">
                        <div ${isMe ? `onclick="openInsight('${s.usn}')"` : ''}
                             class="podium-card glass-card p-6 md:p-8 rounded-[40px] text-center relative ${isWinner ? 'winner' : 'opacity-95'} ${isMe ? 'is-me ring-2 ring-[#800000]' : ''}">
                            
                            ${isWinner ? '<div class="absolute -top-6 left-1/2 -translate-x-1/2 bg-gradient-to-r from-yellow-400 via-amber-500 to-yellow-600 text-white px-6 py-2 rounded-full text-[11px] font-black uppercase tracking-[0.2em] shadow-[0_10px_30px_rgba(212,175,55,0.4)] ring-4 ring-white whitespace-nowrap z-20">CHAMPION <i class="fas fa-fire ml-1"></i></div>' : ''}

                            <div class="relative w-20 h-20 mx-auto mb-6">
                                <div class="absolute inset-0 rounded-2xl rotate-6 bg-slate-100 scale-125 opacity-40"></div>
                                <div class="relative w-full h-full rounded-3xl avatar-circle flex items-center justify-center text-3xl font-black outfit shadow-2xl border-2 border-white/50">
                                    ${s.name.charAt(0).toUpperCase()}
                                </div>
                                <div class="absolute -bottom-2 -right-2 w-10 h-10 ${badgeClass} rounded-2xl flex items-center justify-center text-sm font-black border-4 border-white shadow-xl ring-2 ring-black/5">
                                    ${rankNum}
                                </div>
                            </div>

                            <div class="font-black text-slate-800 text-lg md:text-xl outfit mb-1 truncate px-2">${s.name}</div>
                            <div class="text-[10px] text-slate-400 mb-6 truncate uppercase font-extrabold tracking-[0.15em] opacity-80">${s.discipline}</div>

                            <div class="p-5 rounded-[28px] bg-gradient-to-br from-white to-slate-50/50 border border-slate-100 shadow-inner">
                                <div class="text-4xl font-black outfit text-slate-900 tracking-tighter">${s.total}</div>
                                <div class="text-[10px] text-[#D4AF37] font-black uppercase tracking-[0.25em] mt-1.5 opacity-90">GLOBAL POINTS</div>
                            </div>
                        </div>
                        <div class="podium-base rounded-t-3xl shadow-2xl">
                            ${rankNum}
                        </div>
                    </div>
                `;
            }).join('');

            // GSAP Entrance
            gsap.from(".podium-step", {
                y: 100,
                opacity: 0,
                duration: 1,
                stagger: 0.15,
                ease: "back.out(1.7)",
                delay: 0.2
            });
            
            // Winning card extra pop
            gsap.to(".step-1 .podium-card", {
                scale: 1.05,
                duration: 0.8,
                delay: 1.2,
                ease: "elastic.out(1, 0.5)"
            });
        }

        function openInsight(usn) {
            if (usn.toLowerCase() !== myUsn.toLowerCase()) return;
            const student = currentData.find(s => s.usn.toLowerCase() === usn.toLowerCase());
            if (!student) return;

            selectedUsn = usn;

            // Highlight selected
            document.querySelectorAll('.rank-row, .podium-card').forEach(el => el.classList.remove('selected'));
            document.querySelectorAll(`[data-flip-id="${usn}"]`).forEach(el => el.classList.add('selected'));

            const leader = currentData[0];
            const gapFromTop = leader ? (parseFloat(leader.total) - parseFloat(student.total)).toFixed(1) : 0;
            const isMe = usn.toLowerCase() === myUsn.toLowerCase();
            const isFirst = student.rank === 1;
            const prevRank = parseInt(student.previous_rank) || student.rank;
            const rankChange = prevRank - student.rank;

            // Header
            document.getElementById('drawer-avatar').textContent = student.name.charAt(0).toUpperCase();
            document.getElementById('drawer-name').textContent = student.name;
            document.getElementById('drawer-dept').textContent = `${student.discipline} · ${student.institution}`;
            document.getElementById('drawer-score').textContent = student.total;
            document.getElementById('drawer-rank').textContent = `#${student.rank}`;

            const rankChangeEl = document.getElementById('drawer-rank-change');
            if (rankChange > 0) { rankChangeEl.textContent = `▲${rankChange}`; rankChangeEl.style.color = '#2ecc71'; }
            else if (rankChange < 0) { rankChangeEl.textContent = `▼${Math.abs(rankChange)}`; rankChangeEl.style.color = '#e74c3c'; }
            else { rankChangeEl.textContent = '—'; rankChangeEl.style.color = 'rgba(255,255,255,0.6)'; }

            // Gap bar
            const gapSection = document.getElementById('gap-section');
            const gapBarFill = document.getElementById('gap-bar-fill');
            gapBarFill.style.width = '0%'; // reset before animating
            if (!isFirst) {
                gapSection.classList.remove('hidden');
                const topScore = parseFloat(leader.total);
                const myScore = parseFloat(student.total);
                const pct = Math.min(100, Math.round((myScore / topScore) * 100));
                document.getElementById('gap-label').textContent = `${gapFromTop} pts behind #1`;
                const pronoun = isMe ? 'You are' : `${student.name.split(' ')[0]} is`;
                document.getElementById('gap-desc').textContent = `${pronoun} at ${pct}% of the top performer's score.`;
                setTimeout(() => { gapBarFill.style.width = pct + '%'; }, 150);
            } else {
                gapSection.classList.add('hidden');
            }

            // Motivation
            const motivationBlock = document.getElementById('motivation-block');
            const rival = currentData.find(s => s.rank == student.rank + 1);
            const {icon, title, text, bg, border} = getMotivation(student, isMe, rival);
            document.getElementById('motivation-icon').textContent = icon;
            document.getElementById('motivation-title').textContent = title;
            document.getElementById('motivation-text').textContent = text;
            motivationBlock.style.background = bg;
            motivationBlock.style.borderColor = border;

            // Suggestions
            const suggestions = getSuggestions(student, leader, isMe);
            document.getElementById('suggestions-list').innerHTML = suggestions.map((s, i) => `
                <div class="suggestion-item" style="animation-delay: ${i * 0.08}s">
                    <div class="suggestion-icon" style="background:${s.bg}">${s.icon}</div>
                    <div>
                        <div class="text-sm font-bold text-slate-800">${s.title}</div>
                        <div class="text-xs text-slate-500 mt-0.5 leading-relaxed">${s.desc}</div>
                    </div>
                </div>
            `).join('');

            // Open
            document.getElementById('insight-drawer').classList.add('open');
            document.getElementById('insight-overlay').classList.add('open');
        }

        function openMyInsight() {
            if (myUsn) openInsight(myUsn);
        }

        function closeDrawer() {
            document.getElementById('insight-drawer').classList.remove('open');
            document.getElementById('insight-overlay').classList.remove('open');
            document.querySelectorAll('.rank-row, .podium-card').forEach(el => el.classList.remove('selected'));
            selectedUsn = null;
        }

        /* ===== MOTIVATION CONTENT ===== */
        function getMotivation(student, isMe, rival = null) {
            const rank = student.rank;
            const rivalName = rival ? rival.name.split(' ')[0] : 'others';
            const rivalWarning = rival ? `Be careful, ${rivalName} at #${rival.rank} is catching up fast!` : `Keep pushing to stay ahead of the competition!`;

            if (rank === 1) {
                return {
                    icon: '👑',
                    title: 'The Undisputed Champion!',
                    text: `You are at the top of the leaderboard! But stay sharp — ${rivalWarning} Top contenders are gaining momentum and can easily overtake you if you pause.`,
                    bg: '#fffbeb', border: '#D4AF37'
                };
            } else if (rank === 2) {
                return {
                    icon: '🔥',
                    title: `So Close — #1 is Within Reach!`,
                    text: `You're just one spot away from the top. ${rivalWarning} A focused push on your weakest assessment area can bridge the gap to #1 fast while keeping your lead on #3.`,
                    bg: '#fff7f0', border: '#ff8400'
                };
            } else if (rank === 3) {
                return {
                    icon: '⚡',
                    title: `On the Podium — Keep Fighting!`,
                    text: `You're in the elite top 3! But remember, ${rivalWarning} Sharpen your technical performance to surge toward #1 while securing your podium spot.`,
                    bg: '#fdf4f4', border: '#e67e22'
                };
            } else if (rank <= 10) {
                return {
                    icon: '🚀',
                    title: 'Top 10 — Elite Territory!',
                    text: `You're in the top 10 — that is elite! However, ${rivalWarning} A consistent streak of AI assessments will help you climb into the top 3 and defend your position.`,
                    bg: '#f0fdf4', border: '#86efac'
                };
            } else {
                return {
                    icon: '💡',
                    title: 'Huge Potential — Start the Climb!',
                    text: `Every champion started where you're standing. ${rivalWarning} Complete pending AI assessments and add skills to your portfolio to jump many ranks at once!`,
                    bg: '#f0f9ff', border: '#7dd3fc'
                };
            }
        }

        /* ===== SUGGESTIONS ===== */
        function getSuggestions(student, leader, isMe) {
            const rank = student.rank;
            const gap = leader ? (parseFloat(leader.total) - parseFloat(student.total)).toFixed(1) : 0;

            if (rank === 1) {
                return [
                    { icon: '🧠', bg: '#fff8e1', title: 'Push for a Perfect Score', desc: 'Retake low-scoring AI assessments (Aptitude, Technical) to maximise your total beyond the current ceiling.' },
                    { icon: '🗂️', bg: '#fff0f0', title: 'Add 2+ Verified Portfolio Projects', desc: 'Verified projects with real GitHub links significantly boost your Architect score and widen the gap from rank #2.' },
                    { icon: '🏅', bg: '#f0fff4', title: 'Earn New Certifications', desc: 'Platform certifications add directly to your portfolio points. Target AWS, Google or industry-recognised badges.' },
                    { icon: '🎯', bg: '#f5f3ff', title: 'Complete the Mock AI Interview', desc: 'Full completion of all pillars ensures you hold the highest possible score across the board. Leave no stone unturned.' },
                    { icon: '📣', bg: '#fdf4ff', title: 'Inspire the cohort', desc: 'Being #1 puts you in the spotlight. Share your strategies — your leadership reputation is part of your elite personal brand.' },
                ];
            } else if (rank === 2) {
                return [
                    { icon: '⚔️', bg: '#fff8e1', title: `Close the ${gap}-pt Gap Fast`, desc: 'Identify which assessment pillars the top student outscores you on and focus your next study sessions there.' },
                    { icon: '📋', bg: '#fff0f0', title: 'Complete All Pending Assessments', desc: 'Unattempted assessments are guaranteed lost points. Each completed pillar adds directly to your total.' },
                    { icon: '🔬', bg: '#f0fff4', title: 'Boost Skill Verification Score', desc: 'Request re-verification of your top skills for a higher proficiency rating — this directly impacts portfolio points.' },
                    { icon: '💼', bg: '#f0f9ff', title: 'Add a High-Impact Project', desc: 'A well-documented, verified project with live links can earn substantial bonus portfolio points above rank #1.' },
                    { icon: '⚡', bg: '#fdf4ff', title: 'Retake the Aptitude or Technical Test', desc: 'Even a small 3–5 point improvement in any assessment can push you past the current leader.' },
                ];
            } else if (rank === 3) {
                return [
                    { icon: '🔝', bg: '#fff8e1', title: `Only ${gap} Points Behind #1`, desc: 'This gap is beatable in a single assessment round. Prioritise completing and optimising your weakest area today.' },
                    { icon: '📂', bg: '#fff0f0', title: 'Complete Your Portfolio', desc: 'Adding skills, projects and certifications that are still missing from your profile can yield immediate rank jumps.' },
                    { icon: '🤖', bg: '#f0fff4', title: 'Full AI Assessment Completion', desc: 'Ensure every Lakshya AI module is completed — Aptitude, HR, Technical, Skill Verification and Project Viva.' },
                    { icon: '🛠️', bg: '#f0f9ff', title: 'Improve Project Quality', desc: 'Update your existing projects with better descriptions, GitHub links and verified tech stacks for higher scores.' },
                ];
            } else if (rank <= 10) {
                return [
                    { icon: '📊', bg: '#fff8e1', title: 'Complete All AI Assessments', desc: 'Unattempted modules are free points on the table. Complete every available assessment to close the gap.' },
                    { icon: '📁', bg: '#fff0f0', title: 'Expand Your Portfolio', desc: 'Add at least 3 verified skills and 2 projects. Portfolio completeness is one of the highest-weighted scoring factors.' },
                    { icon: '🎓', bg: '#f0fff4', title: 'Add Industry Certifications', desc: 'Each verified certification boosts your portfolio section significantly and shows real-world credibility.' },
                    { icon: '🔄', bg: '#f0f9ff', title: 'Retake Your Lowest Assessment', desc: 'Review the feedback from your lowest-scoring round and retake to improve your pillar average.' },
                ];
            } else {
                return [
                    { icon: '🚀', bg: '#fff8e1', title: 'Start With Aptitude Assessment', desc: 'The Aptitude module is the fastest path to earning your first significant points. Complete it this week.' },
                    { icon: '📝', bg: '#fff0f0', title: 'Build Your Portfolio from Scratch', desc: 'Add at least 5 skills and 1 project to your portfolio. These are instant, high-value points available right now.' },
                    { icon: '💬', bg: '#f0fff4', title: 'Complete HR & Communication Round', desc: 'This round rewards communication and confidence. It is often overlooked — completing it gives an immediate rank boost.' },
                    { icon: '🔗', bg: '#f0f9ff', title: 'Link GitHub to Your Projects', desc: 'Verified GitHub links on your projects significantly increase your portfolio\'s assessed quality score.' },
                    { icon: '📅', bg: '#fdf4ff', title: 'Set a 7-Day Challenge', desc: 'Commit to completing one Lakshya module per day for a week. Consistent action is the fastest route to the top.' },
                ];
            }
        }

        /* ===== QUICK STATS ===== */
        function getQuickStats(student, leader, totalStudents) {
            const percentile = Math.round(((totalStudents - student.rank) / totalStudents) * 100);
            const gap = leader ? (parseFloat(leader.total) - parseFloat(student.total)).toFixed(1) : '—';
            const rankChange = (parseInt(student.previous_rank) || student.rank) - student.rank;

            return `
                <div class="stat-chip">
                    <div class="text-xl font-black outfit text-[#800000]">${percentile}%</div>
                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Percentile</div>
                </div>
                <div class="stat-chip">
                    <div class="text-xl font-black outfit text-[#800000]">${gap === '—' || gap == 0 ? '—' : gap + ' pts'}</div>
                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Gap from #1</div>
                </div>
                <div class="stat-chip">
                    <div class="text-xl font-black outfit ${rankChange > 0 ? 'text-green-500' : rankChange < 0 ? 'text-red-400' : 'text-slate-400'}">${rankChange > 0 ? '▲' + rankChange : rankChange < 0 ? '▼' + Math.abs(rankChange) : '—'}</div>
                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Rank Δ</div>
                </div>
                <div class="stat-chip">
                    <div class="text-xl font-black outfit text-[#800000]">#${student.rank}</div>
                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Current Rank</div>
                </div>
            `;
        }

        /* ===== RANK CHANGE CELEBRATION ===== */
        function checkRankChanges(newList) {
            if (currentData.length === 0) return;
            const oldMe = currentData.find(s => s.usn.toLowerCase() === myUsn.toLowerCase());
            const newMe = newList.find(s => s.usn.toLowerCase() === myUsn.toLowerCase());
            if (oldMe && newMe && oldMe.rank !== newMe.rank && newMe.rank <= 10 && oldMe.rank > 10) {
                triggerCelebration();
            }
        }

        function triggerCelebration() {
            const overlay = document.getElementById('celebration-overlay');
            overlay.style.display = 'flex';
            gsap.fromTo(overlay, { opacity: 0 }, { opacity: 1, duration: 0.5 });
            setTimeout(() => gsap.to(overlay, { opacity: 0, duration: 1, onComplete: () => overlay.style.display = 'none' }), 4000);
        }

        /* ===== VIEW TOGGLE ===== */
        function changeView(view) {
            currentView = view;
            document.querySelectorAll('.view-btn').forEach(b => {
                b.classList.remove('active');
                if (!b.classList.contains('active')) b.classList.add('text-slate-500');
            });
            const activeBtn = document.getElementById(view === 'local' ? 'btn-local' : 'btn-global');
            activeBtn.classList.remove('text-slate-500');
            activeBtn.classList.add('active');
            closeDrawer();
            fetchRankings(true);
        }

        // Keyboard ESC to close drawer
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

        // Init
        fetchRankings();
        setInterval(fetchRankings, 30000);
    </script>
</body>
</html>
