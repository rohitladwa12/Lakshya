# 🚀 Quick Start Guide

## Step-by-Step Setup

### 1. Ensure XAMPP is Running
- Start **Apache**
- Start **MySQL**

### 2. Run Database Setup

Open your browser and navigate to:
```
http://localhost/placement_final/database/setup.php
```

Click these buttons in order:
1. **"Create Schema"** - Creates all 25 tables
2. **"Seed Data"** - Inserts sample data

Wait for success messages after each step!

### 3. Access the Portal

Navigate to:
```
http://localhost/placement_final/public/login.php
```

Or simply:
```
http://localhost/placement_final/public/
```

### 4. Login

Use these credentials:

**Student:**
- Username: `student1`
- Password: `student123`

**Admin:**
- Username: `admin`
- Password: `admin123`

**Placement Officer:**
- Username: `placement_officer`
- Password: `placement123`

---

## Common Issues & Solutions

### ❌ "Database connection failed"
**Solution:** 
- Make sure MySQL is running in XAMPP
- Check that database name is `placement_portal_v2`
- Verify credentials in `config/database.php`

### ❌ "Foreign key constraint fails" during seed data
**Solution:**
- This is already fixed! Just re-run the seed data
- All skill IDs are now correct

### ❌ "Page not found" or redirect issues
**Solution:**
- Use the correct URL: `http://localhost/placement_final/public/login.php`
- All redirect paths have been fixed to use relative URLs

### ❌ "Class 'User' not found"
**Solution:**
- Make sure `config/bootstrap.php` is being loaded
- Check that `src/Models/User.php` exists

---

## What's Working ✅

- ✅ Database schema (25 tables)
- ✅ Sample data (users, jobs, internships, companies)
- ✅ Login system
- ✅ Logout functionality
- ✅ Student dashboard
- ✅ Role-based access control
- ✅ Session management

---

## Next Steps After Login

### For Students:
- View your dashboard
- Browse jobs and internships
- Update your profile
- (More features coming soon!)

### For Admins:
- Manage users
- Review applications
- Post content
- (Dashboard coming soon!)

---

## Need Help?

Check these files:
- `README.md` - Full documentation
- `database/SKILL_IDS.md` - Skill reference
- `logs/` - Error logs

---

**Happy Testing! 🎉**
