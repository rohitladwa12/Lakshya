# 🎓 Placement Portal v2.0

**GM University Placement Portal (LAKSHYA)** - Restructured & Optimized

A comprehensive placement management system with AI-powered features for students, placement officers, and administrators.

---

## ✨ Features

### For Students
- 📝 Job & Internship Applications
- 🤖 AI-Powered Mock Interviews
- 📄 AI Resume Builder & Analysis
- 💬 Career Advisor Chatbot
- 📊 Analytics & Insights
- 📚 Learning Management System
- 📅 Calendar & Events

### For Placement Officers
- 💼 Job Posting Management
- 📋 Application Tracking
- 🗓️ Interview Scheduling
- 📈 Analytics & Reports
- 📢 Communication Hub

### For Admins
- 👥 User Management
- 📚 Content Management
- 📄 Resume Review
- ⚙️ System Configuration

---

## 🚀 Quick Start

### Prerequisites

- **XAMPP/WAMP/LAMP** (Apache + MySQL + PHP 7.4+)
- **PHP 7.4 or higher**
- **MySQL 5.7 or higher**
- **Composer** (optional, for future dependencies)

### Installation Steps

#### 1. Clone/Copy Project

```bash
# Copy the project to your htdocs folder
cp -r placement_final C:/xampp/htdocs/
```

#### 2. Configure Environment

Create a `.env` file in the root directory (optional):

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=placement_portal_v2
DB_USER=root
DB_PASS=

GEMINI_API_KEY=your_api_key_here

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_password

APP_ENV=development
APP_URL=http://localhost/placement_final
```

#### 3. Setup Database

**Option A: Using Setup Wizard (Recommended)**

1. Start XAMPP/WAMP
2. Open browser and navigate to:
   ```
   http://localhost/placement_final/database/setup.php
   ```
3. Click **"Create Schema"** button
4. Click **"Seed Data"** button
5. Done! ✅

**Option B: Manual Setup**

1. Open phpMyAdmin
2. Create new database: `placement_portal_v2`
3. Import `database/schema.sql`
4. Import `database/seed_data.sql`

#### 4. Access the Portal

Navigate to:
```
http://localhost/placement_final/public/login.php
```

Or simply:
```
http://localhost/placement_final/public/
```
(This will auto-redirect to login)

---

## 🔐 Default Login Credentials

### Admin
- **Username:** `admin`
- **Password:** `admin123`

### Placement Officer
- **Username:** `placement_officer`
- **Password:** `placement123`

### Internship Officer
- **Username:** `internship_officer`
- **Password:** `internship123`

### Students
- **Username:** `student1` to `student5`
- **Password:** `student123`

> ⚠️ **Important:** Change these passwords after first login!

---

## 📁 Project Structure

```
placement_final/
├── config/                  # Configuration files
│   ├── bootstrap.php       # Application initialization
│   ├── constants.php       # Application constants
│   ├── database.php        # Database connection
│   └── session.php         # Session management
│
├── database/               # Database files
│   ├── schema.sql         # Complete database schema
│   ├── seed_data.sql      # Sample data
│   └── setup.php          # Setup wizard
│
├── src/                    # Source code
│   ├── Models/            # Data models
│   │   ├── Model.php      # Base model class
│   │   ├── User.php       # User model
│   │   └── ...
│   │
│   ├── Controllers/       # Business logic
│   │   ├── AuthController.php
│   │   └── ...
│   │
│   ├── Services/          # Reusable services
│   │   ├── AIService.php
│   │   └── ...
│   │
│   └── Helpers/           # Utility functions
│       └── functions.php
│
├── public/                 # Public web root
│   ├── index.php          # Entry point
│   ├── login.php          # Login page
│   ├── assets/            # CSS, JS, images
│   ├── student/           # Student pages
│   ├── admin/             # Admin pages
│   ├── placement/         # Placement officer pages
│   └── internship/        # Internship officer pages
│
├── api/                    # API endpoints
│   ├── auth/
│   ├── jobs/
│   └── ai/
│
├── uploads/                # User uploads
│   ├── resumes/
│   ├── photos/
│   └── documents/
│
└── logs/                   # Application logs
```

---

## 🗄️ Database Schema

The new schema includes **25 properly normalized tables**:

### Core Tables
- `users` - User authentication
- `student_profiles` - Extended student info
- `skills` - Normalized skills
- `student_skills` - Student-skill mapping

### Job Management
- `companies` - Company information
- `job_postings` - Job listings
- `job_required_skills` - Job-skill mapping
- `job_applications` - Student applications

### Internship Management
- `internship_postings`
- `internship_required_skills`
- `internship_applications`

### Interview Management
- `interviews` - Interview scheduling
- `ai_interview_sessions` - AI mock interviews
- `ai_resume_analyses` - Resume analysis results

### Learning Management
- `learning_chapters`
- `learning_modules`
- `student_module_progress`

### Content & Communication
- `faqs`
- `announcements`
- `events`

### Student Portfolio
- `student_projects`
- `student_achievements`

### Bookmarks
- `saved_jobs`
- `saved_internships`

### System
- `activity_logs`

---

## 🔧 Key Improvements from v1.0

### ✅ Database
- **Proper normalization** - No redundant data
- **Consistent naming** - Clear, unambiguous column names
- **Foreign keys** - Data integrity enforced
- **Indexes** - Optimized query performance
- **Proper data types** - ENUM, DECIMAL, JSON support

### ✅ Code Architecture
- **MVC pattern** - Separated concerns
- **Base Model class** - Reusable CRUD operations
- **Centralized config** - Single source of truth
- **Helper functions** - DRY principle
- **Error handling** - Comprehensive logging

### ✅ Security
- **Password hashing** - bcrypt
- **Prepared statements** - SQL injection prevention
- **Session management** - Secure sessions
- **Input validation** - XSS prevention
- **File upload validation** - Safe uploads

---

## 🛠️ Development

### Adding a New Model

```php
<?php
require_once __DIR__ . '/Model.php';

class YourModel extends Model {
    protected $table = 'your_table';
    protected $fillable = ['column1', 'column2'];
    
    // Add custom methods here
}
```

### Creating a Controller

```php
<?php
class YourController {
    private $model;
    
    public function __construct() {
        $this->model = new YourModel();
    }
    
    public function index() {
        $items = $this->model->all();
        // Your logic here
    }
}
```

---

## 📝 API Documentation

### Authentication

**POST** `/api/auth/login.php`
```json
{
  "username": "student1",
  "password": "student123"
}
```

**POST** `/api/auth/logout.php`

### Jobs

**GET** `/api/jobs/list.php?page=1&limit=20`

**POST** `/api/jobs/apply.php`
```json
{
  "job_id": 1,
  "cover_letter": "..."
}
```

---

## 🐛 Troubleshooting

### Database Connection Error

1. Ensure MySQL is running
2. Check database credentials in `config/database.php`
3. Verify database `placement_portal_v2` exists

### Permission Errors

```bash
# Linux/Mac
chmod -R 755 uploads/
chmod -R 755 logs/

# Windows
# Right-click folders → Properties → Security → Edit permissions
```

### Session Issues

1. Clear browser cookies
2. Check `session.save_path` in `php.ini`
3. Ensure `config/session.php` is loaded

---

## 📊 Performance Tips

1. **Enable OPcache** in `php.ini`
2. **Use indexes** for frequently queried columns
3. **Implement caching** for static data
4. **Optimize images** before uploading
5. **Use CDN** for static assets in production

---

## 🔒 Security Checklist

- [ ] Change default passwords
- [ ] Set `APP_ENV=production` in production
- [ ] Use HTTPS in production
- [ ] Restrict database user permissions
- [ ] Enable error logging (disable display)
- [ ] Implement rate limiting for APIs
- [ ] Regular security updates

---

## 📞 Support

For issues or questions:
- Check logs in `/logs/` directory
- Review error messages in browser console
- Verify database schema matches `schema.sql`

---

## 📄 License

This project is developed for GM University Placement Cell.

---

## 🙏 Credits

**Developed by:** GM University IT Team  
**Version:** 2.0  
**Last Updated:** January 2026

---

## 🚀 Next Steps

1. ✅ Setup database using wizard
2. ✅ Login with default credentials
3. ✅ Change default passwords
4. ✅ Configure AI API keys
5. ✅ Add company data
6. ✅ Post jobs/internships
7. ✅ Test all features
8. ✅ Deploy to production

---

**Happy Coding! 🎉**
