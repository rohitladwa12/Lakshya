<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAKSHYA — Internship & Placement Portal | GM University</title>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta name="description"
        content="GM University's premier platform for internships, placements, AI interview prep, and career development.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Animation Libraries (load before body) -->
    <script src="https://cdn.jsdelivr.net/npm/@studio-freight/lenis@1.0.42/dist/lenis.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>

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
        }

        /* let Lenis handle this */

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--white);
            color: var(--text);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================
           NAVIGATION — FLOATING PILL
        ============================================ */

        /* Outer wrapper — centers the pill */
        .nav {
            position: fixed;
            top: 20px;
            left: 0;
            width: 100%;
            z-index: 1000;
            display: flex;
            justify-content: center;
            padding: 0 24px;
            pointer-events: none;
            /* let clicks pass through gaps */
        }

        /* The pill itself */
        .nav__pill {
            display: flex;
            align-items: center;
            gap: 0;
            height: 52px;
            background: rgba(13, 4, 4, 0.65);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 100px;
            padding: 0 6px 0 18px;
            pointer-events: all;
            transition: background 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }

        /* Scrolled: slightly more opaque, subtle gold tint border */
        .nav.scrolled .nav__pill {
            background: rgba(8, 2, 2, 0.82);
            border-color: rgba(212, 175, 55, 0.18);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(212, 175, 55, 0.08);
        }

        /* Logo */
        .nav__logo {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 0.9rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            color: var(--white);
            text-decoration: none;
            white-space: nowrap;
            padding-right: 16px;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            margin-right: 4px;
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
            padding: 8px 14px;
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
            height: 20px;
            background: rgba(255, 255, 255, 0.12);
            margin: 0 10px;
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

        /* CTA button */
        .nav__cta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0 18px;
            height: 36px;
            border-radius: 50px;
            background: var(--gold);
            color: #1a0808 !important;
            text-decoration: none;
            letter-spacing: 0.04em;
            transition: all 0.22s ease;
            box-shadow: 0 2px 10px rgba(212, 175, 55, 0.35);
            flex-shrink: 0;
            margin-left: 6px;
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
            gap: 4px;
            cursor: pointer;
            padding: 10px 8px;
            z-index: 1010;
            margin-left: 4px;
        }

        .nav__toggle span {
            display: block;
            width: 18px;
            height: 1.5px;
            background: rgba(255, 255, 255, 0.75);
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
            background: var(--maroon-deep);
            z-index: 1005;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .nav__mobile-menu.open {
            display: flex;
        }

        .nav__mobile-menu a {
            font-size: 2rem;
            font-weight: 900;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            letter-spacing: -0.03em;
            padding: 10px 24px;
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .nav__mobile-menu a:hover {
            color: var(--white);
            background: rgba(255, 255, 255, 0.05);
        }

        .nav__mobile-menu .mobile-cta {
            margin-top: 24px;
            font-size: 1rem;
            font-weight: 700;
            background: var(--gold);
            color: #1a0808 !important;
            padding: 14px 40px;
            border-radius: 50px;
        }

        .nav__mobile-menu .mobile-cta:hover {
            background: var(--gold-light);
        }

        /* Mobile: show hamburger, hide desktop links */
        @media (max-width: 768px) {
            .nav {
                top: 12px;
                padding: 0 16px;
            }

            .nav__links,
            .nav__demo,
            .nav__sep {
                display: none;
            }

            .nav__toggle {
                display: flex;
            }

            .nav__pill {
                padding: 0 6px 0 16px;
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
            max-width: 900px;
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

        /* Responsive — keep 4 cols, only shrink padding + text */
        @media (max-width: 1024px) {
            .stats__top {
                padding: 60px 40px 40px;
            }

            .stat {
                padding: 40px 28px;
            }

            .stat__number {
                font-size: clamp(2.4rem, 4vw, 4rem);
            }
        }

        @media (max-width: 600px) {
            .stats__top {
                flex-direction: column;
                padding: 40px 20px 28px;
            }

            .stats__headline {
                font-size: 1.8rem;
            }

            .stat {
                padding: 28px 16px;
            }

            .stat__number {
                font-size: 2rem;
            }

            .stat__label {
                font-size: 0.6rem;
                letter-spacing: 0.08em;
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

        /* Responsive bento */
        @media (max-width: 1024px) {
            .features__grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .fc:nth-child(1) {
                grid-column: span 4;
            }

            .fc:nth-child(2) {
                grid-column: span 4;
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
                grid-column: span 2;
            }
        }

        @media (max-width: 768px) {
            .features {
                padding: 80px 24px;
            }

            .features__grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .fc:nth-child(n) {
                grid-column: span 1;
            }

            .fc {
                min-height: 220px;
            }

            .fc--wide {
                padding: 40px 32px;
                min-height: 180px;
            }

            .fc--wide .fc__inner {
                gap: 30px;
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

        /* Mobile story */
        @media (max-width: 768px) {
            .story__sticky {
                grid-template-columns: 1fr;
            }

            .story__left {
                display: none;
            }

            .story__right {
                padding: 40px 24px;
            }

            .story__step {
                padding: 40px 24px;
            }

            .story__step-title {
                font-size: 2.2rem;
            }

            .story__dots {
                right: 16px;
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
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 60px;
            margin-bottom: 60px;
            padding-bottom: 60px;
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
           RESPONSIVE
        ============================================ */
        @media (max-width: 1024px) {
            .features__grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer__top {
                grid-template-columns: 1fr 1fr;
                gap: 40px;
            }

            .stats__inner {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat:first-child {
                border-radius: var(--radius-md) 0 0 0;
            }

            .stat:last-child {
                border-radius: 0 0 var(--radius-md) 0;
            }

            .stat:nth-child(2) {
                border-radius: 0 var(--radius-md) 0 0;
            }

            .stat:nth-child(3) {
                border-radius: 0 0 0 var(--radius-md);
            }
        }

        @media (max-width: 768px) {
            .nav {
                padding: 0 24px;
            }

            .nav__links {
                display: none;
            }

            .nav__toggle {
                display: flex;
            }

            .hero {
                padding: 100px 24px 80px;
            }

            .hero__actions {
                flex-direction: column;
                align-items: center;
            }

            .features {
                padding: 80px 24px;
            }

            .features__grid {
                grid-template-columns: 1fr;
            }

            .stats {
                padding: 60px 24px;
            }

            .stats__inner {
                grid-template-columns: 1fr 1fr;
                gap: 2px;
            }

            .stat:first-child {
                border-radius: var(--radius-sm) 0 0 0;
            }

            .stat:last-child {
                border-radius: 0 0 var(--radius-sm) 0;
            }

            .cta-section {
                padding: 100px 24px;
            }

            footer {
                padding: 60px 24px 32px;
            }

            .footer__top {
                grid-template-columns: 1fr 1fr;
                gap: 32px;
            }

            .footer__bottom {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }

            .story__dots {
                right: 16px;
            }

            .story__step-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .stats__inner {
                grid-template-columns: 1fr;
                gap: 2px;
            }

            .stat:first-child {
                border-radius: var(--radius-sm) var(--radius-sm) 0 0;
            }

            .stat:last-child {
                border-radius: 0 0 var(--radius-sm) var(--radius-sm);
            }

            .stat:nth-child(2),
            .stat:nth-child(3) {
                border-radius: 0;
            }

            .footer__top {
                grid-template-columns: 1fr;
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

            <!-- CTA -->
            <a href="login" class="nav__cta mobile-link">
                Login <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
            </a>

            <!-- Mobile toggle -->
            <div class="nav__toggle" id="navToggle" aria-label="Menu">
                <span></span><span></span><span></span>
            </div>

        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="nav__mobile-menu" id="mobileMenu">
        <a href="#features" class="mobile-link">Features</a>
        <a href="#about" class="mobile-link">About</a>
        <a href="login" class="mobile-link">Login</a>
    </div>

    <!-- ============ HERO ============ -->
    <section class="hero" id="home">
        <div class="hero__grid"></div>
        <div class="hero__orb hero__orb--1"></div>
        <div class="hero__orb hero__orb--2"></div>

        <div class="hero__inner">
            <div class="hero__badge gsap-fade">GM University · Placement Portal</div>
            <h1 class="hero__title gsap-up">
                Launch Your<br>
                <span class="hero__highlight">Dream Career</span>
            </h1>
            <p class="hero__sub gsap-up">
                AI-powered interview prep, smart aptitude tests, and direct connections
                to 100+ top companies — all in one platform.
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
                    <div class="stat__number" data-count="1000" data-suffix="+">0+</div>
                    <div class="stat__label">Students Placed</div>
                    <div class="stat__bar"></div>
                </div>
                <div class="stat gsap-up">
                    <i class="stat__icon fas fa-building"></i>
                    <div class="stat__number" data-count="100" data-suffix="+">0+</div>
                    <div class="stat__label">Partner Companies</div>
                    <div class="stat__bar"></div>
                </div>
                <div class="stat gsap-up">
                    <i class="stat__icon fas fa-chart-line"></i>
                    <div class="stat__number" data-count="95" data-suffix="%">0%</div>
                    <div class="stat__label">Placement Rate</div>
                    <div class="stat__bar"></div>
                </div>
                <div class="stat gsap-up">
                    <i class="stat__icon fas fa-indian-rupee-sign"></i>
                    <div class="stat__number" data-count="10" data-suffix=" LPA">0 LPA</div>
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
                <h3 class="fc__title">Interview Coach<br>that never sleeps.</h3>
                <p class="fc__desc">Practice with our AI trained on 500+ real placement patterns. Get instant, honest
                    feedback on every answer — available 24/7.</p>
            </div>

            <!-- Card 2: Aptitude — maroon gradient -->
            <div class="fc fc--maroon gsap-up">
                <div class="fc__visual"><i class="fas fa-pen-to-square"></i></div>
                <div class="fc__chip">Adaptive</div>
                <h3 class="fc__title">Tests that adapt<br>to your level.</h3>
                <p class="fc__desc">Company-specific aptitude, verbal, and reasoning tests. Difficulty adjusts in real
                    time so you're always challenged correctly.</p>
            </div>

            <!-- Card 3: Jobs — light -->
            <div class="fc fc--light gsap-up">
                <div class="fc__visual"><i class="fas fa-briefcase"></i></div>
                <div class="fc__chip">Verified</div>
                <h3 class="fc__title">Job & Internship Portal</h3>
                <p class="fc__desc">Browse 100+ verified listings. Apply in one click using your Lakshya resume.</p>
            </div>

            <!-- Card 4: Analytics — light -->
            <div class="fc fc--light gsap-up">
                <div class="fc__visual"><i class="fas fa-chart-bar"></i></div>
                <div class="fc__chip">Real-time</div>
                <h3 class="fc__title">Performance Analytics</h3>
                <p class="fc__desc">See exactly where you stand — readiness score, weak areas, and improvement over
                    time.</p>
            </div>

            <!-- Card 5: Career Roadmaps — gold dark -->
            <div class="fc fc--gold gsap-up">
                <div class="fc__visual"><i class="fas fa-bullseye"></i></div>
                <div class="fc__chip">Personalized</div>
                <h3 class="fc__title">Career Roadmaps</h3>
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
                        <h3 class="fc__title" style="font-size:clamp(1.4rem,3vw,2.2rem);">Resume Builder & Portfolio
                            Sync</h3>
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
                    <a href="#">Placement Guide</a>
                    <a href="#">Interview Tips</a>
                    <a href="#">Resume Templates</a>
                    <a href="#">FAQs</a>
                </div>
                <div class="footer__col">
                    <h4>Contact</h4>
                    <a href="#">GM University</a>
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
            });

            // Sync Lenis RAF with GSAP ticker
            gsap.ticker.add(time => lenis.raf(time * 1000));
            gsap.ticker.lagSmoothing(0);

            // ─── Navbar scroll state ─────────────────────────────────────────────────
            const navbar = document.getElementById('navbar');
            lenis.on('scroll', ({ scroll }) => {
                navbar.classList.toggle('scrolled', scroll > 60);
            });

            // ─── Mobile menu ─────────────────────────────────────────────────────────
            const navToggle = document.getElementById('navToggle');
            const mobileMenu = document.getElementById('mobileMenu');
            let menuOpen = false;

            function toggleMenu() {
                menuOpen = !menuOpen;
                navToggle.classList.toggle('open', menuOpen);
                mobileMenu.classList.toggle('open', menuOpen);
                document.body.style.overflow = menuOpen ? 'hidden' : '';
                menuOpen ? lenis.stop() : lenis.start();
            }

            navToggle.addEventListener('click', toggleMenu);
            document.querySelectorAll('.mobile-link').forEach(l => l.addEventListener('click', () => {
                if (menuOpen) toggleMenu();
            }));

            // ─── Lenis anchor scrolling ───────────────────────────────────────────────
            document.querySelectorAll('a[href^="#"]').forEach(a => {
                a.addEventListener('click', e => {
                    const target = document.querySelector(a.getAttribute('href'));
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
                    duration: options.duration || 0.9,
                    ease: options.ease || 'power2.out',
                    stagger: options.stagger || 0,
                    scrollTrigger: {
                        trigger: options.trigger || selector,
                        start: options.start || 'top 82%',
                        toggleActions: 'play none none reset',
                        ...(options.st || {}),
                    }
                });
            }

            // ─── Stats — animated counters ────────────────────────────────────────────
            reveal('.stats .gsap-up', { stagger: 0.1, duration: 0.7, start: 'top 85%' });

            // Animated number counters + bar trigger
            document.querySelectorAll('.stat[data-count]').forEach(el => {
                // Use intersection observer so it fires once when visible
            });
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
                            duration: 1.8,
                            ease: 'power2.out',
                            onUpdate() {
                                numEl.textContent = Math.round(this.targets()[0].val) + suffix;
                            }
                        });
                    }
                });
            });

            // ─── Features header ──────────────────────────────────────────────────────
            reveal('.features .gsap-fade', { trigger: '.features', start: 'top 80%' });
            // Feature header text (the two .gsap-up elements in features__header)
            gsap.to('.features__header .gsap-up', {
                opacity: 1, y: 0, duration: 0.9, ease: 'power2.out', stagger: 0.15,
                scrollTrigger: { trigger: '.features__header', start: 'top 80%', toggleActions: 'play none none reset' }
            });

            // ─── Features grid cards ─────────────────────────────────────────────────
            gsap.to('.features__grid .gsap-up', {
                opacity: 1, y: 0, duration: 0.75, ease: 'power2.out', stagger: 0.1,
                scrollTrigger: { trigger: '.features__grid', start: 'top 78%', toggleActions: 'play none none reset' }
            });

            // ─── CTA section ─────────────────────────────────────────────────────────
            reveal('.cta-section .gsap-up', { trigger: '.cta-section', duration: 1, start: 'top 75%' });

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

            // Build a scroll-scrubbed timeline that cycles through steps
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

                        // Update dots
                        dots.forEach((d, i) => d.classList.toggle('active', i === idx));

                        // Update left-panel gold number
                        if (numDisplay) numDisplay.textContent = stepLabels[idx];
                        if (bgNum) bgNum.textContent = stepLabels[idx];

                        // Update progress bar
                        if (progressBar) progressBar.style.width = (progress * 100) + '%';
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


            // ─── ScrollTrigger.refresh after Lenis is set up ─────────────────────────
            ScrollTrigger.refresh();

        })();
    </script>
</body>

</html>
