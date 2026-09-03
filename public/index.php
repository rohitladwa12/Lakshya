<?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAKSHYA — Internship & Placement Portal | GM University</title>
    <meta name="google-site-verification" content="gHmW8ge9TTkAZxbUI9hHCRMMCfednoa9ByU0zyWfUAw" />
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta name="description"
        content="GM University's premier platform for internships, placements, AI interview prep, and career development.">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="https://leap.gmu.ac.in/Lakshya/">
    <meta property="og:title" content="LAKSHYA — Internship & Placement Portal | GM University">
    <meta property="og:description" content="GM University's premier platform for internships, placements, AI interview prep, and career development.">
    <meta property="og:url" content="https://leap.gmu.ac.in/Lakshya/">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://leap.gmu.ac.in/Lakshya/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <!-- Schema.org JSON-LD Structured Data for SEO -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "EducationalOrganization",
          "@id": "https://gmu.ac.in/#organization",
          "name": "GM University",
          "url": "https://gmu.ac.in/",
          "logo": "https://leap.gmu.ac.in/Lakshya/assets/img/favicon.png",
          "email": "placement@gmu.ac.in",
          "telephone": "+918310793613"
        },
        {
          "@type": "WebSite",
          "@id": "https://leap.gmu.ac.in/Lakshya/#website",
          "url": "https://leap.gmu.ac.in/Lakshya/",
          "name": "LAKSHYA — Internship & Placement Portal | GM University",
          "description": "GM University's premier platform for internships, placements, AI interview prep, and career development.",
          "publisher": {
            "@id": "https://gmu.ac.in/#organization"
          }
        }
      ]
    }
    </script>

    <!-- Animation Libraries (load before body) -->
    <script src="https://cdn.jsdelivr.net/npm/@studio-freight/lenis@1.0.42/dist/lenis.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <style>
        /* ============================================
           BASE & TOKENS
        ============================================ */
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        a, button, .btn, .nav__toggle, .mobile-link, .story__dot, input, select, textarea {
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }

        :root {
            --maroon: #800000;
            --maroon-dark: #5b1f1f;
            --maroon-deep: #1a0a0a;
            --gold: #D4AF37;
            --gold-light: #f0d060;
            --gold-pale: rgba(212, 175, 55, 0.12);
            --white: #ffffff;
            --off-white: #f7f7f5;
            --text: #111111;
            --text-muted: #6b7280;
            --border: rgba(0, 0, 0, 0.08);
            --glass: rgba(255, 255, 255, 0.06);
            --glass-border: rgba(255, 255, 255, 0.12);
            --radius-sm: 12px;
            --radius-md: 20px;
            --radius-lg: 32px;
            --shadow-sm: 0 2px 12px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 24px 60px rgba(0, 0, 0, 0.14);
            --speed: 0.4s;
            --ease: cubic-bezier(0.4, 0, 0.2, 1);
        }

        html {
            scroll-behavior: auto;
            width: 100%;
            max-width: 100vw;
            overflow-x: clip;
            box-sizing: border-box;
        }

        /* let Lenis handle this */

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--white);
            color: var(--text);
            width: 100%;
            max-width: 100vw;
            overflow-x: clip;
            position: relative;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================
           NAVIGATION — FLOATING PILL
        ============================================ */

        /* Outer wrapper — centers the pill */
        .nav {
            position: fixed;
            top: 16px;
            left: 0;
            right: 0;
            width: 100%;
            max-width: 100vw;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0 16px;
            pointer-events: none;
            box-sizing: border-box;
        }

        /* The pill itself */
        .nav__pill {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0;
            height: 50px;
            max-width: 100%;
            background: rgba(13, 4, 4, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 100px;
            padding: 0 6px 0 16px;
            pointer-events: all;
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.06);
            box-sizing: border-box;
        }

        /* Scrolled: slightly more opaque, subtle gold tint border */
        .nav.scrolled .nav__pill {
            background: rgba(8, 2, 2, 0.88);
            border-color: rgba(212, 175, 55, 0.22);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(212, 175, 55, 0.08);
        }

        /* Logo */
        .nav__logo {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 0.88rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            color: var(--white);
            text-decoration: none;
            white-space: nowrap;
            padding-right: 14px;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            margin-right: 4px;
            flex-shrink: 0;
        }

        .nav__logo-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 7px rgba(212, 175, 55, 0.7);
            flex-shrink: 0;
            animation: pulse 2.5s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                box-shadow: 0 0 7px rgba(212, 175, 55, 0.7);
            }

            50% {
                box-shadow: 0 0 14px rgba(212, 175, 55, 1);
            }
        }

        /* Nav links */
        .nav__links {
            display: flex;
            align-items: center;
            gap: 0;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav__links li a {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 50px;
            transition: all 0.22s ease;
            white-space: nowrap;
        }

        .nav__links li a:hover {
            color: var(--white);
            background: rgba(255, 255, 255, 0.08);
        }

        /* Divider before actions */
        .nav__sep {
            width: 1px;
            height: 18px;
            background: rgba(255, 255, 255, 0.12);
            margin: 0 8px;
            flex-shrink: 0;
        }

        /* Demo link */
        .nav__demo {
            font-size: 0.78rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.4);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 50px;
            transition: all 0.22s ease;
            white-space: nowrap;
        }

        .nav__demo:hover {
            color: var(--white);
        }

        /* Right actions cluster inside pill */
        .nav__actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        /* CTA button */
        .nav__cta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0 16px;
            height: 34px;
            border-radius: 50px;
            background: var(--gold);
            color: #1a0808 !important;
            text-decoration: none;
            letter-spacing: 0.04em;
            transition: all 0.22s ease;
            box-shadow: 0 2px 10px rgba(212, 175, 55, 0.35);
            flex-shrink: 0;
        }

        .nav__cta:hover {
            background: var(--gold-light);
            box-shadow: 0 4px 18px rgba(212, 175, 55, 0.5);
            transform: translateY(-1px);
        }

        /* Mobile hamburger — inside pill */
        .nav__toggle {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 1010;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .nav__toggle:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .nav__toggle span {
            display: block;
            width: 16px;
            height: 1.5px;
            background: rgba(255, 255, 255, 0.85);
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .nav__toggle.open span:nth-child(1) {
            transform: translateY(5.5px) rotate(45deg);
        }

        .nav__toggle.open span:nth-child(2) {
            opacity: 0;
        }

        .nav__toggle.open span:nth-child(3) {
            transform: translateY(-5.5px) rotate(-45deg);
        }

        /* Full-screen dark mobile menu */
        .nav__mobile-menu {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(13, 4, 4, 0.97);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            z-index: 1005;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 24px;
        }

        .nav__mobile-menu.open {
            display: flex;
        }

        /* Dedicated top-right close button in mobile menu */
        .nav__mobile-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: var(--white);
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            touch-action: manipulation;
            z-index: 1010;
        }

        .nav__mobile-close:hover,
        .nav__mobile-close:active {
            background: rgba(212, 175, 55, 0.2);
            border-color: var(--gold);
            color: var(--gold-light);
            transform: scale(1.08);
        }

        .nav__mobile-brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
            font-weight: 900;
            letter-spacing: 0.14em;
            color: var(--white);
            margin-bottom: 24px;
            opacity: 0.7;
        }

        .nav__mobile-menu a {
            font-size: 1.6rem;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            letter-spacing: -0.02em;
            padding: 10px 24px;
            border-radius: 14px;
            transition: all 0.2s ease;
            text-align: center;
        }

        .nav__mobile-menu a:hover {
            color: var(--white);
            background: rgba(255, 255, 255, 0.08);
        }

        .nav__mobile-menu .mobile-cta {
            margin-top: 16px;
            font-size: 0.95rem;
            font-weight: 700;
            background: var(--gold);
            color: #1a0808 !important;
            padding: 14px 36px;
            border-radius: 50px;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.4);
        }

        .nav__mobile-menu .mobile-cta:hover {
            background: var(--gold-light);
        }

        /* Navigation Responsive rules */
        @media (max-width: 900px) {
            .nav {
                top: 12px;
                padding: 0 12px;
                width: 100vw;
                max-width: 100vw;
                box-sizing: border-box;
            }

            .nav__pill {
                width: 100%;
                max-width: 100%;
                padding: 0 6px 0 14px;
            }

            .nav__logo {
                border-right: none;
                padding-right: 0;
                margin-right: 0;
                font-size: 0.85rem;
            }

            .nav__links,
            .nav__demo,
            .nav__sep {
                display: none !important;
            }

            .nav__toggle {
                display: flex;
            }

            .nav__cta {
                padding: 0 12px;
                height: 32px;
                font-size: 0.72rem;
            }

            /* Prevent any mobile background bleeding / right white space */
            .hero__orb,
            .stats::before,
            .stats::after,
            .story__right::before {
                display: none !important;
            }

            .cta-section::before {
                font-size: clamp(3.5rem, 14vw, 7rem) !important;
                max-width: 95vw !important;
            }
        }

        @media (max-width: 360px) {
            .nav__logo {
                font-size: 0.78rem;
            }
            .nav__cta {
                padding: 0 10px;
                font-size: 0.68rem;
            }
            .nav__toggle {
                width: 32px;
                height: 32px;
            }
        }

        /* ============================================
           HERO
        ============================================ */
        .hero {
            min-height: 100vh;
            background: radial-gradient(ellipse at 20% 40%, #4a1d1d 0%, #1a0808 50%, #0d0404 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 120px 40px 80px;
        }

        /* Grid overlay */
        .hero__grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(212, 175, 55, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(212, 175, 55, 0.06) 1px, transparent 1px);
            background-size: 80px 80px;
            pointer-events: none;
        }

        /* Glow orbs */
        .hero__orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            pointer-events: none;
        }

        .hero__orb--1 {
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(128, 0, 0, 0.35), transparent 70%);
            top: -200px;
            left: -150px;
        }

        .hero__orb--2 {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15), transparent 70%);
            bottom: -100px;
            right: -100px;
        }

        .hero__inner {
            max-width: 1100px;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .hero__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--gold-light);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 28px;
        }

        .hero__badge::before {
            content: '';
            width: 6px;
            height: 6px;
            background: var(--gold);
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.5;
                transform: scale(0.8);
            }
        }

        .hero__title {
            font-size: clamp(3rem, 7vw, 6rem);
            font-weight: 900;
            line-height: 1.05;
            letter-spacing: -0.04em;
            color: var(--white);
            margin-bottom: 24px;
        }

        .hero__title .hero__highlight {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 50%, var(--gold) 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 4s linear infinite;
        }

        @keyframes shimmer {
            0% {
                background-position: 0% center;
            }

            100% {
                background-position: 200% center;
            }
        }

        .hero__sub {
            font-size: clamp(1rem, 2vw, 1.2rem);
            font-weight: 400;
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.8;
            max-width: 600px;
            margin: 0 auto 40px;
        }

        .hero__actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
            font-size: 0.925rem;
            font-weight: 700;
            padding: 14px 30px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            letter-spacing: 0.01em;
            transition: all 0.3s var(--ease);
        }

        .btn--primary {
            background: linear-gradient(135deg, var(--gold), #b8960c);
            color: #1a0808;
        }

        .btn--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(212, 175, 55, 0.4);
        }

        .btn--ghost {
            background: transparent;
            border: 1.5px solid rgba(255, 255, 255, 0.25);
            color: rgba(255, 255, 255, 0.85);
        }

        .btn--ghost:hover {
            border-color: rgba(255, 255, 255, 0.6);
            background: rgba(255, 255, 255, 0.06);
            transform: translateY(-2px);
        }

        /* Scroll indicator */
        .hero__scroll {
            position: absolute;
            bottom: 36px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.3);
            font-size: 0.72rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .hero__scroll-line {
            width: 1px;
            height: 48px;
            background: linear-gradient(to bottom, rgba(212, 175, 55, 0.6), transparent);
            animation: scrollLine 2s ease-in-out infinite;
        }

        @keyframes scrollLine {
            0% {
                transform: scaleY(0);
                transform-origin: top;
            }

            50% {
                transform: scaleY(1);
                transform-origin: top;
            }

            51% {
                transform-origin: bottom;
            }

            100% {
                transform: scaleY(0);
                transform-origin: bottom;
            }
        }

        /* ============================================
           STATS — PREMIUM DARK CINEMATIC
        ============================================ */
        .stats {
            background: #ffffff;
            padding: 0;
            overflow: hidden;
            position: relative;
        }

        /* ambient orbs — light */
        .stats::before {
            content: '';
            position: absolute;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(128, 0, 0, 0.05) 0%, transparent 65%);
            top: -200px;
            left: -150px;
            border-radius: 50%;
            pointer-events: none;
        }

        .stats::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.06) 0%, transparent 65%);
            bottom: -100px;
            right: -100px;
            border-radius: 50%;
            pointer-events: none;
        }

        .stats__inner {
            max-width: 1440px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* Top editorial row */
        .stats__top {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 40px;
            padding: 100px 80px 60px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.07);
            flex-wrap: wrap;
        }

        .stats__headline {
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 900;
            letter-spacing: -0.04em;
            line-height: 1.1;
            color: var(--text);
            max-width: 500px;
        }

        .stats__headline em {
            font-style: normal;
            background: linear-gradient(135deg, var(--maroon), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stats__intro {
            font-size: 0.925rem;
            color: var(--text-muted);
            line-height: 1.85;
            max-width: 340px;
        }

        /* Numbers grid — always 4 columns */
        .stats__grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
        }

        .stat {
            padding: 60px 56px;
            position: relative;
            border-right: 1px solid rgba(0, 0, 0, 0.07);
            overflow: hidden;
            cursor: default;
        }

        .stat:last-child {
            border-right: none;
        }

        /* subtle hover glow */
        .stat::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 50%, rgba(128, 0, 0, 0.04), transparent 60%);
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .stat:hover::before {
            opacity: 1;
        }

        .stat__icon {
            font-size: 1.1rem;
            margin-bottom: 20px;
            display: block;
            color: var(--maroon);
            opacity: 0.5;
        }

        .stat__number {
            font-size: clamp(3.5rem, 5.5vw, 6rem);
            font-weight: 900;
            letter-spacing: -0.05em;
            line-height: 0.9;
            margin-bottom: 16px;
            color: var(--text);
            background: none;
            -webkit-text-fill-color: unset;
            white-space: nowrap;
        }

        .stat:nth-child(1) .stat__number {
            color: var(--maroon-dark);
        }

        .stat__label {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .stat__bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 2px;
            width: 0;
            background: linear-gradient(90deg, var(--maroon), var(--gold));
            transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat.in-view .stat__bar {
            width: 100%;
        }

        /* Ticker tape */
        .stats__ticker {
            border-top: 1px solid rgba(0, 0, 0, 0.07);
            padding: 22px 0;
            overflow: hidden;
            white-space: nowrap;
        }

        .stats__ticker-track {
            display: inline-flex;
            gap: 0;
            animation: ticker 28s linear infinite;
        }

        .stats__ticker-item {
            display: inline-flex;
            align-items: center;
            gap: 24px;
            padding: 0 40px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: rgba(0, 0, 0, 0.2);
        }

        .stats__ticker-item::after {
            content: '·';
            font-size: 1rem;
            color: rgba(128, 0, 0, 0.25);
        }

        @keyframes ticker {
            from {
                transform: translateX(0);
            }

            to {
                transform: translateX(-50%);
            }
        }

        /* ============================================
           FEATURES SECTION
        ============================================ */
        .features {
            padding: 140px 40px;
            background: var(--white);
        }

        .section-label {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--maroon);
            margin-bottom: 16px;
        }

        .section-title {
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 900;
            letter-spacing: -0.035em;
            line-height: 1.1;
            color: var(--text);
            margin-bottom: 20px;
        }

        .section-sub {
            font-size: 1.05rem;
            color: var(--text-muted);
            line-height: 1.8;
            max-width: 540px;
        }

        .features__header {
            max-width: 1280px;
            margin: 0 auto 80px;
        }

        .features__grid {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            grid-template-rows: auto;
            gap: 20px;
        }

        /* Bento layout spans */
        .fc:nth-child(1) {
            grid-column: span 3;
        }

        .fc:nth-child(2) {
            grid-column: span 3;
        }

        .fc:nth-child(3) {
            grid-column: span 2;
        }

        .fc:nth-child(4) {
            grid-column: span 2;
        }

        .fc:nth-child(5) {
            grid-column: span 2;
        }

        .fc:nth-child(6) {
            grid-column: span 6;
        }

        /* ── Feature Card Base ── */
        .fc {
            border-radius: var(--radius-md);
            overflow: hidden;
            position: relative;
            padding: 44px 40px;
            min-height: 280px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            transition: transform 0.35s var(--ease), box-shadow 0.35s var(--ease);
        }

        .fc:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        /* Variants */
        .fc--dark {
            background: var(--maroon-deep);
            color: var(--white);
        }

        .fc--maroon {
            background: linear-gradient(135deg, var(--maroon-dark) 0%, #3d0f0f 100%);
            color: var(--white);
        }

        .fc--gold {
            background: linear-gradient(135deg, #2a1f00 0%, #1a1200 100%);
            color: var(--white);
            border: 1px solid rgba(212, 175, 55, 0.2);
        }

        .fc--light {
            background: var(--off-white);
            color: var(--text);
            border: 1.5px solid var(--border);
        }

        .fc--wide {
            background: linear-gradient(100deg, #0d0404 60%, #1a0808 100%);
            color: var(--white);
            padding: 56px 60px;
            min-height: 200px;
            justify-content: center;
        }

        /* Noise texture overlay for dark cards */
        .fc--dark::before,
        .fc--maroon::before,
        .fc--gold::before,
        .fc--wide::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            opacity: 0.4;
        }

        /* Big icon display in feature cards */
        .fc__visual {
            position: absolute;
            top: 28px;
            right: 28px;
            font-size: 2.2rem;
            opacity: 0.15;
            user-select: none;
            line-height: 1;
        }

        .fc--light .fc__visual {
            opacity: 0.1;
            color: var(--text);
        }

        .fc--dark .fc__visual,
        .fc--maroon .fc__visual {
            color: var(--white);
        }

        .fc--gold .fc__visual {
            color: var(--gold);
            opacity: 0.2;
        }

        /* Accent chip */
        .fc__chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 50px;
            margin-bottom: 18px;
            width: fit-content;
        }

        .fc--dark .fc__chip,
        .fc--maroon .fc__chip,
        .fc--wide .fc__chip {
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.55);
        }

        .fc--gold .fc__chip {
            background: rgba(212, 175, 55, 0.15);
            color: var(--gold-light);
        }

        .fc--light .fc__chip {
            background: rgba(128, 0, 0, 0.08);
            color: var(--maroon);
        }

        .fc__title {
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.2;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }

        .fc--dark .fc__title,
        .fc--maroon .fc__title,
        .fc--gold .fc__title,
        .fc--wide .fc__title {
            color: var(--white);
        }

        .fc--light .fc__title {
            color: var(--text);
        }

        .fc__desc {
            font-size: 0.88rem;
            line-height: 1.75;
            position: relative;
            z-index: 1;
            max-width: 420px;
        }

        .fc--dark .fc__desc,
        .fc--maroon .fc__desc,
        .fc--gold .fc__desc,
        .fc--wide .fc__desc {
            color: rgba(255, 255, 255, 0.5);
        }

        .fc--light .fc__desc {
            color: var(--text-muted);
        }

        /* Gold accent line on light card */
        .fc--light:hover {
            border-color: var(--gold);
        }

        /* Wide card layout */
        .fc--wide .fc__inner {
            display: flex;
            align-items: center;
            gap: 60px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .fc--wide .fc__left {
            flex: 0 0 auto;
        }

        .fc--wide .fc__right {
            flex: 1;
        }

        .fc--wide .fc__big-stat {
            font-size: clamp(3rem, 5vw, 5rem);
            font-weight: 900;
            letter-spacing: -0.05em;
            line-height: 1;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .fc--wide .fc__big-label {
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.3);
            margin-top: 6px;
        }

        }

        /* ============================================
           PINNED STORYTELLING SECTION — PREMIUM
        ============================================ */
        .story {
            position: relative;
        }

        .story__sticky {
            position: sticky;
            top: 0;
            height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            background: #0a0404;
        }

        /* LEFT PANEL — large step counter */
        .story__left {
            position: relative;
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
            padding: 80px 60px;
            border-right: 1px solid rgba(255, 255, 255, 0.06);
            overflow: hidden;
        }

        /* Animated grid */
        .story__left-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(212, 175, 55, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(212, 175, 55, 0.04) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* Giant background number */
        .story__bg-num {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(18rem, 28vw, 36rem);
            font-weight: 900;
            letter-spacing: -0.08em;
            line-height: 1;
            color: rgba(255, 255, 255, 0.015);
            user-select: none;
            pointer-events: none;
            transition: opacity 0.6s ease;
        }

        /* Step pill in left panel */
        .story__left-meta {
            position: relative;
            z-index: 2;
        }

        .story__num-display {
            font-size: clamp(5rem, 10vw, 9rem);
            font-weight: 900;
            letter-spacing: -0.05em;
            line-height: 0.85;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 60%, var(--gold) 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 4s linear infinite;
        }

        .story__num-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.25);
            margin-top: 12px;
        }

        /* RIGHT PANEL — content */
        .story__right {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 80px 70px;
            overflow: hidden;
        }

        /* Radial glow in right panel */
        .story__right::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(128, 0, 0, 0.2), transparent 60%);
            pointer-events: none;
        }

        /* Step content — overlapping, GSAP controls opacity */
        .story__step {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 80px 70px;
            opacity: 0;
            pointer-events: none;
        }

        .story__step-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 28px;
        }

        .story__step-tag::before {
            content: '';
            width: 28px;
            height: 1.5px;
            background: var(--gold);
            flex-shrink: 0;
        }

        .story__step-title {
            font-size: clamp(2.2rem, 4vw, 3.8rem);
            font-weight: 900;
            letter-spacing: -0.04em;
            color: var(--white);
            line-height: 1.05;
            margin-bottom: 24px;
        }

        .story__step-text {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.45);
            line-height: 1.85;
            max-width: 440px;
        }

        /* Icon visual per step */
        .story__step-icon {
            font-size: 1.6rem;
            margin-bottom: 28px;
            display: block;
            color: var(--gold);
            opacity: 0.7;
        }

        /* Progress bar at bottom */
        .story__progress-track {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(255, 255, 255, 0.06);
            z-index: 20;
        }

        .story__progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--maroon), var(--gold));
            transition: width 0.1s linear;
        }

        /* Right sidebar dots */
        .story__dots {
            position: absolute;
            right: 28px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            gap: 12px;
            z-index: 20;
        }

        .story__dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            transition: all 0.4s var(--ease);
            cursor: pointer;
        }

        .story__dot.active {
            background: var(--gold);
            transform: scale(1.8);
            box-shadow: 0 0 8px rgba(212, 175, 55, 0.5);
        }

        .story__scroll-space {
            height: 400vh;
        }

        }

        /* ============================================
           CTA SECTION
        ============================================ */
        .cta-section {
            padding: 160px 40px;
            background: var(--off-white);
            overflow: hidden;
            position: relative;
        }

        .cta-section::before {
            content: 'LAKSHYA';
            position: absolute;
            font-size: clamp(8rem, 16vw, 18rem);
            font-weight: 900;
            letter-spacing: -0.06em;
            color: rgba(128, 0, 0, 0.04);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            white-space: nowrap;
        }

        .cta-section__inner {
            max-width: 680px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .cta-section h2 {
            font-size: clamp(2.4rem, 5vw, 4rem);
            font-weight: 900;
            letter-spacing: -0.04em;
            color: var(--text);
            margin-bottom: 20px;
            line-height: 1.1;
        }

        .cta-section p {
            font-size: 1.05rem;
            color: var(--text-muted);
            line-height: 1.8;
            margin-bottom: 40px;
        }

        /* ============================================
           FOOTER
        ============================================ */
        footer {
            background: var(--maroon-deep);
            padding: 80px 40px 40px;
        }

        .footer__inner {
            max-width: 1280px;
            margin: 0 auto;
        }

        .footer__top {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 50px 80px;
            margin-bottom: 50px;
            padding-bottom: 50px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .footer__brand {
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: -0.04em;
            color: var(--white);
            margin-bottom: 16px;
        }

        .footer__brand-sub {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.4);
            line-height: 1.7;
        }

        .footer__col h4 {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.35);
            margin-bottom: 20px;
        }

        .footer__col a {
            display: block;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.55);
            text-decoration: none;
            margin-bottom: 12px;
            transition: color 0.2s;
        }

        .footer__col a:hover {
            color: var(--gold-light);
        }

        .footer__bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.825rem;
            color: rgba(255, 255, 255, 0.25);
        }

        .footer__bottom .footer__gold {
            color: var(--gold);
            font-weight: 600;
        }

        /* ============================================
           GSAP INITIAL STATES
        ============================================ */
        .gsap-fade {
            opacity: 0;
        }

        .gsap-up {
            opacity: 0;
            transform: translateY(60px);
        }

        .gsap-up-sm {
            opacity: 0;
            transform: translateY(30px);
        }

        .gsap-left {
            opacity: 0;
            transform: translateX(-40px);
        }

        .gsap-scale {
            opacity: 0;
            transform: scale(0.92);
        }

        /* ============================================
           RESPONSIVE & MOBILE POLISH
        ============================================ */
        @media (max-width: 1024px) {
            .stats__top {
                padding: 60px 40px 40px;
            }

            .stats__grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat {
                padding: 44px 36px;
            }

            .stat:nth-child(2) {
                border-right: none;
            }

            .stat:nth-child(1), .stat:nth-child(2) {
                border-bottom: 1px solid rgba(0, 0, 0, 0.07);
            }

            .features__grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .fc:nth-child(1), .fc:nth-child(2), .fc:nth-child(6) {
                grid-column: span 2;
            }

            .fc:nth-child(3), .fc:nth-child(4), .fc:nth-child(5) {
                grid-column: span 1;
            }

            .footer__top {
                grid-template-columns: 1fr 1fr;
                gap: 40px;
            }
        }

        @media (max-width: 768px) {
            .hero {
                padding: 110px 20px 70px;
                min-height: 90vh;
            }

            .hero__badge {
                font-size: 0.72rem;
                padding: 5px 14px;
                margin-bottom: 20px;
            }

            .hero__title {
                font-size: clamp(2.4rem, 9vw, 3.8rem);
                margin-bottom: 18px;
            }

            .hero__sub {
                font-size: 0.95rem;
                line-height: 1.7;
                margin-bottom: 30px;
            }

            .hero__actions {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
                max-width: 320px;
                margin: 0 auto;
                gap: 12px;
            }

            .hero__actions .btn {
                width: 100%;
                justify-content: center;
                padding: 14px 24px;
                font-size: 0.95rem;
            }

            .hero__scroll {
                display: none;
            }

            /* Stats Section Mobile */
            .stats__top {
                flex-direction: column;
                align-items: flex-start;
                padding: 50px 24px 30px;
                gap: 16px;
            }

            .stats__headline {
                font-size: 1.85rem;
                line-height: 1.2;
            }

            .stats__intro {
                font-size: 0.88rem;
                line-height: 1.65;
            }

            .stats__grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat {
                padding: 32px 20px;
            }

            .stat__number {
                font-size: 2.2rem;
            }

            .stat__label {
                font-size: 0.7rem;
            }

            /* Features Section Mobile */
            .features {
                padding: 70px 20px;
            }

            .features__header {
                margin-bottom: 40px;
            }

            .section-title {
                font-size: 1.9rem;
                line-height: 1.2;
            }

            .section-sub {
                font-size: 0.92rem;
            }

            .features__grid {
                grid-template-columns: 1fr !important;
                gap: 16px;
            }

            .fc:nth-child(n) {
                grid-column: span 1 !important;
            }

            .fc {
                padding: 30px 24px;
                min-height: auto;
                border-radius: var(--radius-sm);
            }

            .fc__title {
                font-size: 1.3rem;
            }

            .fc__desc {
                font-size: 0.85rem;
            }

            .fc--wide {
                padding: 32px 24px;
            }

            .fc--wide .fc__inner {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .fc--wide .fc__big-stat {
                font-size: 3rem;
            }

            /* Story Section Mobile */
            .story__sticky {
                grid-template-columns: 1fr;
            }

            .story__left {
                display: none;
            }

            .story__right {
                padding: 50px 24px;
            }

            .story__step {
                padding: 50px 24px;
            }

            .story__step-tag {
                margin-bottom: 16px;
            }

            .story__step-title {
                font-size: 1.9rem;
                line-height: 1.2;
                margin-bottom: 16px;
            }

            .story__step-text {
                font-size: 0.92rem;
                line-height: 1.7;
            }

            .story__dots {
                right: 14px;
            }

            /* CTA Mobile */
            .cta-section {
                padding: 80px 20px;
            }

            .cta-section h2 {
                font-size: 2rem;
            }

            .cta-section p {
                font-size: 0.92rem;
                margin-bottom: 30px;
            }

            /* Footer Mobile 2x2 */
            footer {
                padding: 50px 20px 32px;
            }

            .footer__top {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 36px 24px;
                margin-bottom: 36px;
                padding-bottom: 36px;
            }

            .footer__bottom {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .stats__grid {
                grid-template-columns: 1fr;
            }

            .stat {
                border-right: none;
                border-bottom: 1px solid rgba(0, 0, 0, 0.07);
                padding: 28px 20px;
            }

            .stat:last-child {
                border-bottom: none;
            }

            .stat__number {
                font-size: 2.4rem;
            }

            .hero__title {
                font-size: 2.3rem;
            }
        }
    </style>
</head>

<body>

    <!-- ============ NAVIGATION ============ -->
    <nav class="nav" id="navbar">
        <div class="nav__pill">

            <!-- Logo -->
            <a href="#" class="nav__logo">
                <span class="nav__logo-dot"></span>
                LAKSHYA
            </a>

            <!-- Nav links -->
            <ul class="nav__links">
                <li><a href="#about" class="mobile-link">Stats</a></li>
                <li><a href="#features" class="mobile-link">Features</a></li>
                <li><a href="#story" class="mobile-link">How it works</a></li>
            </ul>

            <!-- Divider -->
            <div class="nav__sep"></div>

            <!-- Try Demo 
        <a href="login?demo=1" class="nav__demo mobile-link">Try Demo</a> -->

            <!-- Actions -->
            <div class="nav__actions">
                <a href="login" class="nav__cta mobile-link">
                    Login <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
                </a>
                <div class="nav__toggle" id="navToggle" aria-label="Menu">
                    <span></span><span></span><span></span>
                </div>
            </div>

        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="nav__mobile-menu" id="mobileMenu">
        <button class="nav__mobile-close" id="mobileMenuClose" aria-label="Close menu">
            <i class="fas fa-times"></i>
        </button>
        <div class="nav__mobile-brand">
            <span class="nav__logo-dot"></span> LAKSHYA
        </div>
        <a href="#about" class="mobile-link">Stats</a>
        <a href="#features" class="mobile-link">Features</a>
        <a href="#story" class="mobile-link">How It Works</a>
        <a href="login" class="mobile-cta mobile-link">Sign In / Login <i class="fas fa-arrow-right"></i></a>
    </div>

    <!-- ============ HERO ============ -->
    <section class="hero" id="home">
        <div class="hero__grid"></div>
        <div class="hero__orb hero__orb--1"></div>
        <div class="hero__orb hero__orb--2"></div>

        <div class="hero__inner">
            <div class="hero__badge gsap-fade">GM University · Placement Portal</div>
            <h1 class="hero__title gsap-up">
                LAKSHYA<br>
                <span class="hero__highlight" style="font-size: clamp(1.6rem, 5.2vw, 4.2rem); letter-spacing: -0.04em; display: inline-block;">Internship & Placement Portal</span>
            </h1>
            <p class="hero__sub gsap-up">
                GM University's official platform for internships, campus placements, AI mock interviews, and career development.
            </p>
            <div class="hero__actions gsap-up">
                <a href="login" class="btn btn--primary">
                    Get Started <i class="fas fa-arrow-right"></i>
                </a>
                <a href="#features" class="btn btn--ghost">
                    Explore Features
                </a>
            </div>
        </div>

        <div class="hero__scroll">
            <div class="hero__scroll-line"></div>
            <span>Scroll</span>
        </div>
    </section>

    <!-- ============ STATS ============ -->
    <section class="stats" id="about">
        <div class="stats__inner">

            <!-- Editorial header -->
            <div class="stats__top">
                <h2 class="stats__headline gsap-up">
                    Numbers that<br><em>speak for themselves.</em>
                </h2>
                <p class="stats__intro gsap-up">
                    LAKSHYA has helped thousands of GM University students land their dream roles at India's top
                    companies.
                </p>
            </div>

            <!-- Stats grid -->
            <div class="stats__grid">
                <div class="stat gsap-up">
                    <i class="stat__icon fas fa-user-graduate"></i>
                    <div class="stat__number" data-count="1000" data-suffix="+">1000+</div>
                    <div class="stat__label">Students Placed</div>
                    <div class="stat__bar"></div>
                </div>
                <div class="stat gsap-up">
                    <i class="stat__icon fas fa-building"></i>
                    <div class="stat__number" data-count="100" data-suffix="+">100+</div>
                    <div class="stat__label">Partner Companies</div>
                    <div class="stat__bar"></div>
                </div>
                <div class="stat gsap-up">
                    <i class="stat__icon fas fa-chart-line"></i>
                    <div class="stat__number" data-count="95" data-suffix="%">95%</div>
                    <div class="stat__label">Placement Rate</div>
                    <div class="stat__bar"></div>
                </div>
                <div class="stat gsap-up">
                    <i class="stat__icon fas fa-indian-rupee-sign"></i>
                    <div class="stat__number" data-count="10" data-suffix=" LPA">10 LPA</div>
                    <div class="stat__label">Average Package</div>
                    <div class="stat__bar"></div>
                </div>
            </div>

            <!-- Ticker tape -->
            <div class="stats__ticker">
                <div class="stats__ticker-track">
                    <span class="stats__ticker-item">TCS</span>
                    <span class="stats__ticker-item">Infosys</span>
                    <span class="stats__ticker-item">Wipro</span>
                    <span class="stats__ticker-item">Accenture</span>
                    <span class="stats__ticker-item">Cognizant</span>
                    <span class="stats__ticker-item">HCL</span>
                    <span class="stats__ticker-item">Capgemini</span>
                    <span class="stats__ticker-item">IBM</span>
                    <span class="stats__ticker-item">Deloitte</span>
                    <span class="stats__ticker-item">Microsoft</span>
                    <span class="stats__ticker-item">Google</span>
                    <span class="stats__ticker-item">Amazon</span>
                    <span class="stats__ticker-item">TCS</span>
                    <span class="stats__ticker-item">Infosys</span>
                    <span class="stats__ticker-item">Wipro</span>
                    <span class="stats__ticker-item">Accenture</span>
                    <span class="stats__ticker-item">Cognizant</span>
                    <span class="stats__ticker-item">HCL</span>
                    <span class="stats__ticker-item">Capgemini</span>
                    <span class="stats__ticker-item">IBM</span>
                    <span class="stats__ticker-item">Deloitte</span>
                    <span class="stats__ticker-item">Microsoft</span>
                    <span class="stats__ticker-item">Google</span>
                    <span class="stats__ticker-item">Amazon</span>
                </div>
            </div>

        </div>
    </section>

    <!-- ============ FEATURES ============ -->
    <section class="features" id="features">
        <div class="features__header">
            <div class="section-label gsap-fade">What we offer</div>
            <h2 class="section-title gsap-up">Everything you need<br>to land your first offer.</h2>
            <p class="section-sub gsap-up">A complete career toolkit built for GM University students — from prep to
                placement.</p>
        </div>

        <div class="features__grid" id="featuresGrid">

            <!-- Card 1: AI Interview — dark -->
            <div class="fc fc--dark gsap-up">
                <div class="fc__visual"><i class="fas fa-robot"></i></div>
                <div class="fc__chip">AI-Powered</div>
                <h3 class="fc__title">AI Mock Interviews &<br>Interview Coach</h3>
                <p class="fc__desc">Practice with our AI trained on 500+ real placement patterns. Get instant, honest
                    feedback on every answer — available 24/7.</p>
            </div>

            <!-- Card 2: Aptitude — maroon gradient -->
            <div class="fc fc--maroon gsap-up">
                <div class="fc__visual"><i class="fas fa-pen-to-square"></i></div>
                <div class="fc__chip">Adaptive</div>
                <h3 class="fc__title">Smart Aptitude Tests &<br>Company Practice</h3>
                <p class="fc__desc">Company-specific aptitude, verbal, and reasoning tests. Difficulty adjusts in real
                    time so you're always challenged correctly.</p>
            </div>

            <!-- Card 3: Jobs — light -->
            <div class="fc fc--light gsap-up">
                <div class="fc__visual"><i class="fas fa-briefcase"></i></div>
                <div class="fc__chip">Verified</div>
                <h3 class="fc__title">Internship & Placement Opportunities</h3>
                <p class="fc__desc">Browse 100+ verified listings. Apply in one click using your Lakshya resume.</p>
            </div>

            <!-- Card 4: Analytics — light -->
            <div class="fc fc--light gsap-up">
                <div class="fc__visual"><i class="fas fa-chart-bar"></i></div>
                <div class="fc__chip">Real-time</div>
                <h3 class="fc__title">Placement Performance Analytics</h3>
                <p class="fc__desc">See exactly where you stand — readiness score, weak areas, and improvement over
                    time.</p>
            </div>

            <!-- Card 5: Career Roadmaps — gold dark -->
            <div class="fc fc--gold gsap-up">
                <div class="fc__visual"><i class="fas fa-bullseye"></i></div>
                <div class="fc__chip">Personalized</div>
                <h3 class="fc__title">Personalized Career Roadmaps</h3>
                <p class="fc__desc">AI-curated learning paths built for your target role. No guesswork, just a clear
                    plan.</p>
            </div>

            <!-- Card 6: Wide resume strip -->
            <div class="fc fc--wide gsap-up">
                <div class="fc__inner">
                    <div class="fc__left">
                        <div class="fc__big-stat">2 min</div>
                        <div class="fc__big-label">to build your resume</div>
                    </div>
                    <div class="fc__right">
                        <div class="fc__chip">ATS-Optimized</div>
                        <h3 class="fc__title" style="font-size:clamp(1.4rem,3vw,2.2rem);">ATS Resume Builder & Portfolio Tools</h3>
                        <p class="fc__desc">Create a professional resume in minutes. Projects and certifications sync
                            automatically — no copy-paste needed.</p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ============ PINNED STORY SECTION — PREMIUM ============ -->
    <section class="story" id="story">
        <div class="story__sticky" id="storySticky">

            <!-- LEFT PANEL: step counter -->
            <div class="story__left">
                <div class="story__left-grid"></div>
                <div class="story__bg-num" id="storyBgNum">01</div>
                <div class="story__left-meta">
                    <div class="story__num-display" id="storyNumDisplay">01</div>
                    <div class="story__num-label">of 04 steps</div>
                </div>
            </div>

            <!-- RIGHT PANEL: overlapping step content, GSAP controls opacity -->
            <div class="story__right" id="storyRight">

                <div class="story__step" id="step-0">
                    <i class="story__step-icon fas fa-id-card"></i>
                    <div class="story__step-tag">Step 01</div>
                    <h2 class="story__step-title">Create your profile.</h2>
                    <p class="story__step-text">Sign up with your university credentials. Your academic data, skills,
                        and portfolio sync automatically — no manual setup required.</p>
                </div>

                <div class="story__step" id="step-1">
                    <i class="story__step-icon fas fa-robot"></i>
                    <div class="story__step-tag">Step 02</div>
                    <h2 class="story__step-title">Train with AI.</h2>
                    <p class="story__step-text">Practice aptitude tests and mock interviews powered by real company
                        patterns. Our AI gives you honest, constructive feedback after every session.</p>
                </div>

                <div class="story__step" id="step-2">
                    <i class="story__step-icon fas fa-briefcase"></i>
                    <div class="story__step-tag">Step 03</div>
                    <h2 class="story__step-title">Apply with confidence.</h2>
                    <p class="story__step-text">Browse curated opportunities matched to your placement score and skill
                        profile. Apply in one click — your resume is ready and waiting.</p>
                </div>

                <div class="story__step" id="step-3">
                    <i class="story__step-icon fas fa-trophy"></i>
                    <div class="story__step-tag">Step 04</div>
                    <h2 class="story__step-title">Land your offer.</h2>
                    <p class="story__step-text">Track every application, get interview reminders, and celebrate your
                        placement. We'll be there every step of the way.</p>
                </div>

                <!-- Progress dots (inside right panel, right edge) -->
                <div class="story__dots" id="storyDots">
                    <div class="story__dot active" data-index="0"></div>
                    <div class="story__dot" data-index="1"></div>
                    <div class="story__dot" data-index="2"></div>
                    <div class="story__dot" data-index="3"></div>
                </div>
            </div>

            <!-- Bottom progress bar -->
            <div class="story__progress-track">
                <div class="story__progress-bar" id="storyProgressBar"></div>
            </div>

        </div>
        <div class="story__scroll-space"></div>
    </section>

    <!-- ============ CTA ============ -->
    <section class="cta-section">
        <div class="cta-section__inner gsap-up">
            <div class="section-label">Ready?</div>
            <h2>Your next chapter<br>starts here.</h2>
            <p>Join thousands of GM University students who have already landed their dream jobs through Lakshya.</p>
            <a href="login" class="btn btn--primary" style="font-size:1rem; padding:16px 40px;">
                Get Started Free <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>

    <!-- ============ FOOTER ============ -->
    <footer>
        <div class="footer__inner">
            <div class="footer__top">
                <div>
                    <div class="footer__brand">LAKSHYA</div>
                    <p class="footer__brand-sub">GM University's comprehensive Internship and Placement Portal.
                        Empowering students to achieve their career goals.</p>
                </div>
                <div class="footer__col">
                    <h4>Platform</h4>
                    <a href="#features">Features</a>
                    <a href="login">Student Login</a>
                    <a href="login">Officer Login</a>
                </div>
                <div class="footer__col">
                    <h4>Resources</h4>
                    <a href="#features">Placement & Internship Tools</a>
                    <a href="#features">AI Interview Practice</a>
                    <a href="#features">ATS Resume Builder</a>
                    <a href="#story">How It Works</a>
                </div>
                <div class="footer__col">
                    <h4>Contact</h4>
                    <a href="https://gmu.ac.in/" target="_blank" rel="noopener">GM University</a>
                    <a href="mailto:placement@gmu.ac.in">placement@gmu.ac.in</a>
                    <a href="tel:+918310793613">+91 8310793613</a>
                    <a href="tel:+919901191487">+91 9901191487</a>
                    <p
                        style="font-size: 0.72rem; color: var(--gold); margin-top: 12px; font-weight: 800; letter-spacing: 0.08em; opacity: 0.8;">
                        ANY QUERIES CALL US</p>
                </div>
            </div>
            <div class="footer__bottom">
                <span>© 2026 LAKSHYA — GM University. All rights reserved.</span>
                <span>Built with <span class="footer__gold">♥</span> for students.</span>
            </div>
        </div>
    </footer>

    <!-- ============ JAVASCRIPT ============ -->
    <script>
        (function () {
            'use strict';

            // ─── Register ScrollTrigger ───────────────────────────────────────────────
            gsap.registerPlugin(ScrollTrigger);

            // ─── Lenis smooth scroll ─────────────────────────────────────────────────
            const lenis = new Lenis({
                duration: 1.15,
                easing: t => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
                smoothWheel: true,
                smoothTouch: false, // Native touch scroll on mobile for fast INP < 200ms
                syncTouch: false
            });

            // Sync Lenis RAF with GSAP ticker
            gsap.ticker.add(time => lenis.raf(time * 1000));
            gsap.ticker.lagSmoothing(0);

            // ─── Navbar scroll state ─────────────────────────────────────────────────
            const navbar = document.getElementById('navbar');
            lenis.on('scroll', ({ scroll }) => {
                if (navbar) navbar.classList.toggle('scrolled', scroll > 60);
            });

            // ─── Mobile menu ─────────────────────────────────────────────────────────
            const navToggle = document.getElementById('navToggle');
            const mobileMenu = document.getElementById('mobileMenu');
            const mobileMenuClose = document.getElementById('mobileMenuClose');
            let menuOpen = false;

            function toggleMenu() {
                menuOpen = !menuOpen;
                if (navToggle) navToggle.classList.toggle('open', menuOpen);
                if (mobileMenu) mobileMenu.classList.toggle('open', menuOpen);
                document.body.style.overflow = menuOpen ? 'hidden' : '';
                menuOpen ? lenis.stop() : lenis.start();
            }

            if (navToggle) navToggle.addEventListener('click', toggleMenu, { passive: true });
            if (mobileMenuClose) mobileMenuClose.addEventListener('click', toggleMenu, { passive: true });
            document.querySelectorAll('.mobile-link').forEach(l => l.addEventListener('click', () => {
                if (menuOpen) toggleMenu();
            }, { passive: true }));

            // ─── Lenis / Native anchor scrolling ──────────────────────────────────────
            document.querySelectorAll('a[href^="#"]').forEach(a => {
                a.addEventListener('click', e => {
                    const href = a.getAttribute('href');
                    if (!href || href === '#') return;
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        lenis.scrollTo(target, { offset: -72, duration: 1.4 });
                        if (menuOpen) toggleMenu();
                    }
                });
            });

            // ─── Hero entrance timeline ───────────────────────────────────────────────
            const heroTl = gsap.timeline({ delay: 0.1, defaults: { ease: 'power3.out' } });
            heroTl
                .to('.hero .gsap-fade', { opacity: 1, duration: 0.8 })
                .to('.hero .gsap-up', { opacity: 1, y: 0, duration: 1, stagger: 0.18 }, '-=0.4');

            // ─── Hero parallax on scroll ──────────────────────────────────────────────
            gsap.to('.hero__inner', {
                yPercent: -18,
                ease: 'none',
                scrollTrigger: {
                    trigger: '.hero',
                    start: 'top top',
                    end: 'bottom top',
                    scrub: 1,
                }
            });

            // ─── Helper: generic reveal ───────────────────────────────────────────────
            function reveal(selector, options = {}) {
                gsap.to(selector, {
                    opacity: 1,
                    y: 0,
                    x: 0,
                    scale: 1,
                    duration: options.duration || 0.8,
                    ease: options.ease || 'power2.out',
                    stagger: options.stagger || 0,
                    scrollTrigger: {
                        trigger: options.trigger || selector,
                        start: options.start || 'top 85%',
                        toggleActions: 'play none none none',
                        once: true,
                        ...(options.st || {}),
                    }
                });
            }

            // ─── Stats — animated counters ────────────────────────────────────────────
            reveal('.stats .gsap-up', { stagger: 0.1, duration: 0.7, start: 'top 85%' });

            document.querySelectorAll('.stat').forEach(el => {
                const numEl = el.querySelector('.stat__number');
                const target = numEl ? parseInt(numEl.dataset.count) : 0;
                const suffix = numEl ? (numEl.dataset.suffix || '') : '';
                if (!numEl || !target) return;

                ScrollTrigger.create({
                    trigger: el,
                    start: 'top 85%',
                    once: true,
                    onEnter() {
                        el.classList.add('in-view');
                        gsap.to({ val: 0 }, {
                            val: target,
                            duration: 1.5,
                            ease: 'power2.out',
                            onUpdate() {
                                numEl.textContent = Math.round(this.targets()[0].val) + suffix;
                            }
                        });
                    }
                });
            });

            // ─── Features header & grid cards ────────────────────────────────────────
            reveal('.features .gsap-fade', { trigger: '.features', start: 'top 80%' });
            gsap.to('.features__header .gsap-up', {
                opacity: 1, y: 0, duration: 0.8, ease: 'power2.out', stagger: 0.15,
                scrollTrigger: { trigger: '.features__header', start: 'top 80%', toggleActions: 'play none none none', once: true }
            });

            gsap.to('.features__grid .gsap-up', {
                opacity: 1, y: 0, duration: 0.7, ease: 'power2.out', stagger: 0.08,
                scrollTrigger: { trigger: '.features__grid', start: 'top 80%', toggleActions: 'play none none none', once: true }
            });

            // ─── CTA section ─────────────────────────────────────────────────────────
            reveal('.cta-section .gsap-up', { trigger: '.cta-section', duration: 0.8, start: 'top 80%' });

            // ─── PINNED STORY SECTION ─────────────────────────────────────────────────
            const steps = document.querySelectorAll('.story__step');
            const dots = document.querySelectorAll('.story__dot');
            const numDisplay = document.getElementById('storyNumDisplay');
            const bgNum = document.getElementById('storyBgNum');
            const progressBar = document.getElementById('storyProgressBar');
            const nSteps = steps.length;
            const stepLabels = ['01', '02', '03', '04'];

            // Set step 0 visible initially
            gsap.set('#step-0', { opacity: 1, pointerEvents: 'auto' });

            let lastIdx = -1;
            let lastPct = -1;

            const storyTl = gsap.timeline({
                scrollTrigger: {
                    trigger: '.story',
                    start: 'top top',
                    end: 'bottom bottom',
                    scrub: 0.6,
                    onUpdate(self) {
                        const progress = self.progress;
                        const raw = progress * (nSteps - 1) * 1.15;
                        const idx = Math.min(Math.floor(raw + 0.3), nSteps - 1);
                        const pct = Math.round(progress * 100);

                        // Only mutate DOM when step index actually changes
                        if (idx !== lastIdx) {
                            lastIdx = idx;
                            dots.forEach((d, i) => d.classList.toggle('active', i === idx));
                            if (numDisplay) numDisplay.textContent = stepLabels[idx];
                            if (bgNum) bgNum.textContent = stepLabels[idx];
                        }

                        // Only mutate progress bar width when percentage changes
                        if (pct !== lastPct) {
                            lastPct = pct;
                            if (progressBar) progressBar.style.width = pct + '%';
                        }
                    }
                }
            });

            // Build cross-fades between steps
            for (let i = 0; i < nSteps - 1; i++) {
                storyTl
                    .to(`#step-${i}`, { opacity: 0, y: -24, ease: 'power1.in', duration: 0.5 })
                    .fromTo(`#step-${i + 1}`, { opacity: 0, y: 40 },
                        {
                            opacity: 1, y: 0, ease: 'power2.out', duration: 0.5,
                            onStart() { this.targets()[0].style.pointerEvents = 'auto'; },
                            onReverseComplete() { this.targets()[0].style.pointerEvents = 'none'; }
                        });
            }

            // ─── Refresh ScrollTrigger ───────────────────────────────────────────────
            ScrollTrigger.refresh();

        })();
    </script>
</body>

</html>
