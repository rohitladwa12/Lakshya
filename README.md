# 🎓 LAKSHYA (Placement Portal v2.0)

[![Project Status: Active](https://img.shields.io/badge/Status-Active-brightgreen.svg)](https://github.com/rohitladwa12/Lakshya)
[![Framework: PHP MVC](https://img.shields.io/badge/Framework-Vanilla_PHP_7.4+-blue.svg)](https://www.php.net/)
[![UI: Glassmorphism](https://img.shields.io/badge/UI-Premium_Glassmorphism-purple.svg)](https://css-tricks.com/glassmorphism-creative-bar-charts-and-glassmorphic-design/)

**LAKSHYA** is a high-performance, AI-driven recruitment ecosystem designed for **GM University**. It seamlessly bridges the gap between students, placement officers, and industry partners through advanced automation and predictive analytics.

---

## 💎 Premium Features

### 🤖 AI-Powered Career Suite
- **Smart Resume Builder & Analyzer**: Dynamic resume generation with real-time ATS scoring and brutal AI critiques.
- **Async Mock Interviews**: Voice-enabled technical, aptitude, and HR rounds with non-blocking AI evaluation.
- **Career Advisor Bot**: Personalized roadmap generation based on student USN, SGPA, and registered projects.
- **Unified Assessment Engine**: Centralized tracking for Project Defense (Viva) and Certification Verification.

### 🏢 Corporate & Management Tools
- **Robust Job/Internship Portal**: Full lifecycle management from posting to final selection.
- **Smart Bulk Import**: High-performance CSV/XLSX processing via `PhpSpreadsheet` with USN-based profile linking.
- **Real-time Analytics**: Interactive glassmorphic dashboards for placement trends, department performance, and NQT analytics.
- **Security Hardening**: Secure exam conditions with copy/paste blocking, fullscreen enforcement, and tab-switch detection.

---

## 🏛️ System Architecture

### ⚡ Performance & Scalability
- **PHP 7.4+ MVC**: Clean separation of concerns for maintainability.
- **Redis Integration**: High-speed session handling and background AI processing.
- **Async Queue Pattern**: Uses `QueueService` and `AIWorker` to handle intensive AI tasks without blocking the UI.
- **Smart Caching**: Daily JSON snapshots for leaderboard performance tracking (Rank Up/Down indicators).

### 🎨 Design System
- **Glassmorphism**: Premium aesthetics with backdropped blurs, vibrant gradients, and micro-animations.
- **GSAP Powered**: Smooth transitions and gamified leaderboard podiums.
- **Responsive Core**: Mobile-first architecture using modern CSS Grid and Flexbox.

---

## 🚀 Installation & Setup

### Prerequisites
- **Web Server**: Apache 2.4+ (XAMPP / WAMP)
- **Database**: MySQL 5.7+ / MariaDB
- **Caching**: Redis Server 6.0+ (Required for AI features)
- **PHP Extensions**: `pdo_mysql`, `curl`, `json`, `mbstring`, `zip`, `gd`.

### Setup Steps

1. **Clone the Repository**
   ```bash
   git clone https://github.com/rohitladwa12/Lakshya.git
   cd Lakshya
   ```

2. **Configure Environment**
   Rename `.env.example` to `.env` and update your credentials:
   ```env
   # Database
   DB_NAME=placement_portal_v2
   DB_USER=root
   DB_PASS=
   
   # AI Services (Required)
   OPENAI_API_KEY=your_sk_key
   GEMINI_API_KEY=your_gemini_key
   
   # Redis
   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379
   ```

3. **Database Initialization**

4. **Start the AI Worker** (For async features)
   ```bash
   # Run the worker script to process AI queues
   php scripts/start_worker.php
   ```

---

## 🔐 Security Standards

- **Credential Safety**: Strictly manages secrets via `.env`. DO NOT commit API keys to version control.
- **OWASP Compliance**: Integrated protection against SQL Injection (PDO), XSS (Input Sanitization), and CSRF.
- **Assessment Hardening**: Assessment pages implement strict input blocking and event monitoring to ensure academic integrity.

---

## 📁 Project Structure

```
Lakshya/
├── config/             # Core configurations (bootstrap, constants)
├── database/           # SQL schemas and setup migrations
├── public/             # Web Root (login, student, officer, vc portals)
│   ├── assets/         # Compiled CSS/JS and UI components
│   └── student/        # Feature-rich student dashboard
├── scripts/            # Background workers and maintenance scripts
├── src/                # Backend Core
│   ├── Models/         # Database entities (User, Resume, etc.)
│   ├── Services/       # Business logic (AIService, QueueService)
│   ├── Workers/        # Background process handlers
│   └── Helpers/        # Global utility functions
└── uploads/            # Secure storage for resumes and documents
```

---

## 🤝 Contribution & License

Developed with ❤️ by the **GM University Placement Cell**. 
Current Version: **2.0 (Modernized)**

---

**Empowering Careers through Innovation. 🎉**
