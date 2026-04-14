<?php
/**
 * Unified Login Page
 * Handles both Students and Officers using the native application structure
 */

require_once __DIR__ . '/../config/bootstrap.php';

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    $role = Session::getRole();
    if ($role === ROLE_VC) {
        redirect('/Lakshya/vc/index.php');
    } elseif ($role === 'placement_officer') {
        redirect('officer/dashboard');
    } elseif ($role === 'admin') {
        redirect('admin/dashboard.php');
    } elseif ($role === 'internship_officer') {
        redirect('internship_officer/dashboard.php');
    } elseif ($role === 'dept_coordinator') {
        redirect('coordinator/dashboard.php');
    } elseif ($role === ROLE_DEMO) {
        redirect('student/dashboard');
    } else {
        redirect('student/dashboard');
    }
}

$error   = Session::flash('error');
$success = Session::flash('success');

// Handle login form submission
if (isPost()) {
    $username = clean(post('username'));
    $password = post('password');

    if (empty($username) || empty($password)) {
        $error = 'Please enter your credentials.';
    } else {
        $userModel = new User();
        $result    = $userModel->authenticate($username, $password);

        if ($result['success']) {
            $user       = $result['user'];
            $department = isset($user['department']) ? $user['department'] : null;
            Session::setUser($user['id'], $user['username'], $user['role'], $user['full_name'], $user['institution'] ?? null, $department);
            trackActivity('login', 'User logged in successfully', ['role' => $user['role'], 'institution' => $user['institution'] ?? 'N/A']);

            if ($user['role'] === ROLE_VC) {
                redirect('/Lakshya/vc/index.php');
            } elseif ($user['role'] === 'placement_officer') {
                redirect('officer/dashboard');
            } elseif ($user['role'] === 'admin') {
                redirect('admin/dashboard.php');
            } elseif ($user['role'] === 'internship_officer') {
                redirect('internship_officer/dashboard.php');
            } elseif ($user['role'] === 'dept_coordinator') {
                redirect('coordinator/dashboard.php');
            } elseif ($user['role'] === ROLE_DEMO) {
                redirect('student/dashboard');
            } else {
                redirect('student/dashboard');
            }
        } else {
            $error = $result['message'];
            trackActivity('login_failed', "Failed login for: $username", ['reason' => $error]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — LAKSHYA | GM University</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --maroon:      #800000;
            --maroon-dark: #5b1a1a;
            --maroon-deep: #0d0404;
            --gold:        #d4af37;
            --gold-light:  #f0d060;
            --white:       #ffffff;
            --text:        #1a1a1a;
            --text-muted:  #6b7280;
            --border:      #e5e7eb;
            --ease:        cubic-bezier(0.4, 0, 0.2, 1);
        }

        html, body { height: 100%; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            display: flex;
            min-height: 100vh;
            background: var(--maroon-deep);
        }

        /* ── LEFT PANEL ── */
        .login__left {
            flex: 1;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 52px 60px;
            overflow: hidden;
            background:
                radial-gradient(ellipse at 10% 20%, rgba(128,0,0,0.45) 0%, transparent 50%),
                radial-gradient(ellipse at 90% 85%, rgba(212,175,55,0.09) 0%, transparent 45%),
                radial-gradient(ellipse at 60% 50%, rgba(60,0,0,0.3) 0%, transparent 55%),
                #080202;
        }

        /* Fine gold grid */
        .login__left::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(212,175,55,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(212,175,55,0.04) 1px, transparent 1px);
            background-size: 56px 56px;
            pointer-events: none;
        }

        /* Ghost watermark */
        .login__watermark {
            position: absolute;
            bottom: -40px;
            right: -40px;
            font-size: clamp(7rem, 14vw, 13rem);
            font-weight: 900;
            letter-spacing: -0.06em;
            color: rgba(255,255,255,0.025);
            user-select: none;
            pointer-events: none;
            line-height: 1;
            z-index: 0;
        }

        /* Decorative glow orb */
        .login__orb {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(80px);
        }

        .login__orb--1 {
            width: 420px; height: 420px;
            background: radial-gradient(circle, rgba(128,0,0,0.28), transparent);
            top: -120px; left: -100px;
        }

        .login__orb--2 {
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(212,175,55,0.10), transparent);
            bottom: 80px; right: -60px;
        }

        /* Top: back link + logo */
        .login__left-top {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .login__back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 0.72rem;
            font-weight: 600;
            color: rgba(255,255,255,0.28);
            text-decoration: none;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            transition: color 0.2s ease;
        }

        .login__back:hover { color: rgba(255,255,255,0.6); }

        .login__brand {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .login__brand-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 10px rgba(212,175,55,0.7);
            animation: pulse 2.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%,100% { box-shadow: 0 0 8px rgba(212,175,55,0.7); }
            50%      { box-shadow: 0 0 18px rgba(212,175,55,1);  }
        }

        .login__brand-name {
            font-size: 0.88rem;
            font-weight: 900;
            letter-spacing: 0.16em;
            color: var(--white);
        }

        /* Feature chips row */
        .login__chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .login__chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: rgba(255,255,255,0.55);
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 50px;
            padding: 5px 12px;
        }

        .login__chip i {
            color: var(--gold);
            font-size: 0.65rem;
        }

        /* Big headline */
        .login__headline {
            position: relative;
            z-index: 1;
        }

        .login__headline h2 {
            font-size: clamp(2.4rem, 3.8vw, 3.8rem);
            font-weight: 900;
            line-height: 1.05;
            letter-spacing: -0.05em;
            color: var(--white);
            margin-bottom: 20px;
        }

        .login__headline h2 em {
            font-style: normal;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login__headline p {
            font-size: 0.875rem;
            color: rgba(255,255,255,0.35);
            line-height: 1.85;
            max-width: 380px;
        }

        /* Social proof card */
        .login__proof {
            display: flex;
            align-items: center;
            gap: 14px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 16px 20px;
            position: relative;
            z-index: 1;
        }

        .login__proof-avatars {
            display: flex;
        }

        .login__proof-avatars span {
            width: 32px; height: 32px;
            border-radius: 50%;
            border: 2px solid #0d0404;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--white);
            margin-left: -8px;
            background: var(--maroon-dark);
        }

        .login__proof-avatars span:first-child { margin-left: 0; }
        .login__proof-avatars span:nth-child(2) { background: #3d1a1a; }
        .login__proof-avatars span:nth-child(3) { background: var(--maroon); }
        .login__proof-avatars span:nth-child(4) {
            background: var(--maroon-dark);
            font-size: 0.55rem;
            letter-spacing: 0;
        }

        .login__proof-text {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.55);
            line-height: 1.5;
        }

        .login__proof-text strong {
            display: block;
            font-weight: 700;
            color: rgba(255,255,255,0.85);
            font-size: 0.82rem;
        }

        /* Bottom stats + ticker */
        .login__bottom {
            position: relative;
            z-index: 1;
        }

        .login__stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .login__stat {
            padding: 18px 20px;
            border-right: 1px solid rgba(255,255,255,0.07);
        }

        .login__stat:last-child { border-right: none; }

        .login__stat-num {
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: -0.04em;
            color: var(--white);
            line-height: 1;
        }

        .login__stat:first-child .login__stat-num {
            color: var(--gold);
        }

        .login__stat-label {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.25);
            margin-top: 5px;
        }

        /* Scrolling company ticker */
        .login__ticker {
            overflow: hidden;
            white-space: nowrap;
        }

        .login__ticker-track {
            display: inline-flex;
            animation: ticker 22s linear infinite;
        }

        .login__ticker-item {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.16);
            padding: 0 20px;
        }

        .login__ticker-item::after {
            content: '·';
            margin-left: 20px;
            color: rgba(212,175,55,0.25);
        }

        @keyframes ticker {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }

        /* ── RIGHT PANEL ── */
        .login__right {
            width: 480px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 48px;
            background: var(--white);
            position: relative;
        }

        .login__form-wrap {
            width: 100%;
            max-width: 380px;
        }

        /* Form header */
        .login__form-header {
            margin-bottom: 40px;
        }

        .login__form-header h1 {
            font-size: 1.8rem;
            font-weight: 900;
            letter-spacing: -0.04em;
            color: var(--text);
            margin-bottom: 8px;
        }

        .login__form-header p {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        /* Alert */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 28px;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .alert-error {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
        }

        .alert-error i { margin-top: 2px; flex-shrink: 0; }

        .alert-success {
            background: #f0fff4;
            color: #276749;
            border: 1px solid #c6f6d5;
        }

        /* Form field */
        .field {
            margin-bottom: 22px;
            position: relative;
        }

        .field label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .field__input-wrap {
            position: relative;
        }

        .field__icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(0,0,0,0.25);
            font-size: 0.85rem;
            pointer-events: none;
        }

        .field input {
            width: 100%;
            height: 50px;
            padding: 0 44px 0 42px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            color: var(--text);
            background: #fafafa;
            transition: all 0.22s ease;
            outline: none;
        }

        .field input::placeholder { color: rgba(0,0,0,0.22); }

        .field input:focus {
            border-color: var(--maroon);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(128,0,0,0.08);
        }

        /* Toggle password visibility */
        .field__eye {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: rgba(0,0,0,0.3);
            font-size: 0.85rem;
            padding: 4px;
            transition: color 0.2s ease;
        }

        .field__eye:hover { color: var(--maroon); }

        /* Login button */
        .btn-login {
            width: 100%;
            height: 52px;
            background: var(--maroon-dark);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login:hover {
            background: #3d1010;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(91,26,26,0.35);
        }

        .btn-login:active { transform: translateY(0); }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 28px 0;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        /* Demo button */
        .btn-demo {
            width: 100%;
            height: 48px;
            background: transparent;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.22s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-demo:hover {
            border-color: var(--gold);
            color: var(--maroon-dark);
            background: rgba(212,175,55,0.04);
        }

        /* Footer note */
        .login__note {
            margin-top: 32px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .login__note a {
            color: var(--maroon);
            font-weight: 600;
            text-decoration: none;
        }

        .login__note a:hover { text-decoration: underline; }

        /* Responsive */
        @media (max-width: 900px) {
            .login__left { display: none; }
            .login__right { width: 100%; padding: 40px 24px; }
        }

        @media (max-width: 480px) {
            .login__right { padding: 32px 20px; }
        }
    </style>
</head>
<body>

<!-- Left Brand Panel -->
<div class="login__left">

    <!-- Decorative orbs -->
    <div class="login__orb login__orb--1"></div>
    <div class="login__orb login__orb--2"></div>

    <!-- Ghost watermark -->
    <div class="login__watermark">LK</div>

    <!-- Top row: back + brand -->
    <div class="login__left-top">
        <a href="/Lakshya/" class="login__back">
            <i class="fas fa-arrow-left"></i> Home
        </a>
        <div class="login__brand">
            <span class="login__brand-dot"></span>
            <span class="login__brand-name">LAKSHYA</span>
        </div>
    </div>

    <!-- Feature chips -->
    <div class="login__chips">
        <div class="login__chip"><i class="fas fa-robot"></i> AI Interview Coach</div>
        <div class="login__chip"><i class="fas fa-pen-to-square"></i> Aptitude Prep</div>
        <div class="login__chip"><i class="fas fa-briefcase"></i> 100+ Companies</div>
        <div class="login__chip"><i class="fas fa-chart-line"></i> Live Analytics</div>
        <div class="login__chip"><i class="fas fa-file-lines"></i> Resume Builder</div>
    </div>

    <!-- Headline -->
    <div class="login__headline">
        <h2>Your career<br>starts with<br><em>one login.</em></h2>
        <p>GM University's complete placement ecosystem. AI prep, verified listings, performance analytics — all in one dashboard.</p>
    </div>

    <!-- Social proof card -->
    <div class="login__proof">
        <div class="login__proof-avatars">
            <span>RK</span>
            <span>AM</span>
            <span>SP</span>
            <span>+997</span>
        </div>
        <div class="login__proof-text">
            <strong>1,000+ students placed this year</strong>
            Join the GMU students who landed roles at top companies.
        </div>
    </div>

    <!-- Bottom: stats + ticker -->
    <div class="login__bottom">
        <div class="login__stats">
            <div class="login__stat">
                <div class="login__stat-num">1000+</div>
                <div class="login__stat-label">Placed</div>
            </div>
            <div class="login__stat">
                <div class="login__stat-num">95%</div>
                <div class="login__stat-label">Rate</div>
            </div>
            <div class="login__stat">
                <div class="login__stat-num">10 LPA</div>
                <div class="login__stat-label">Avg Package</div>
            </div>
        </div>

        <div class="login__ticker">
            <div class="login__ticker-track">
                <span class="login__ticker-item">TCS</span>
                <span class="login__ticker-item">Infosys</span>
                <span class="login__ticker-item">Wipro</span>
                <span class="login__ticker-item">Accenture</span>
                <span class="login__ticker-item">IBM</span>
                <span class="login__ticker-item">Deloitte</span>
                <span class="login__ticker-item">Google</span>
                <span class="login__ticker-item">Microsoft</span>
                <span class="login__ticker-item">Amazon</span>
                <span class="login__ticker-item">Capgemini</span>
                <span class="login__ticker-item">TCS</span>
                <span class="login__ticker-item">Infosys</span>
                <span class="login__ticker-item">Wipro</span>
                <span class="login__ticker-item">Accenture</span>
                <span class="login__ticker-item">IBM</span>
                <span class="login__ticker-item">Deloitte</span>
                <span class="login__ticker-item">Google</span>
                <span class="login__ticker-item">Microsoft</span>
                <span class="login__ticker-item">Amazon</span>
                <span class="login__ticker-item">Capgemini</span>
            </div>
        </div>
    </div>

</div>

<!-- Right Form Panel -->
<div class="login__right">
    <div class="login__form-wrap">

        <div class="login__form-header">
            <h1>Welcome back</h1>
            <p>Sign in to access your dashboard</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-circle-check"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">

            <div class="field">
                <label for="username">Username / USN / Email</label>
                <div class="field__input-wrap">
                    <i class="field__icon fas fa-user"></i>
                    <input type="text" id="username" name="username" required
                           placeholder="Students: USN or Aadhar — Staff: email"
                           value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="field__input-wrap">
                    <i class="field__icon fas fa-lock"></i>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter your password">
                    <button type="button" class="field__eye" id="togglePwd" aria-label="Toggle password">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                Sign In <i class="fas fa-arrow-right" style="font-size:0.75rem;"></i>
            </button>

        </form>



        <div class="login__note">
            Issues accessing your account?<br>
            Contact your <a href="#">placement coordinator</a> or <a href="#">support team</a>.
        </div>

    </div>
</div>

<script>
    // Toggle password visibility
    const togglePwd = document.getElementById('togglePwd');
    const pwdInput  = document.getElementById('password');
    const eyeIcon   = document.getElementById('eyeIcon');

    togglePwd.addEventListener('click', () => {
        const show = pwdInput.type === 'password';
        pwdInput.type = show ? 'text' : 'password';
        eyeIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
</script>

</body>
</html>
