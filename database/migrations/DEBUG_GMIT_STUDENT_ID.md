# Debug Instructions for GMIT Student ID Issue

## Problem
GMIT students are getting `student_id` stored as `0` in the `career_roadmaps` table instead of their actual student ID from `ad_student_details`.

## Debug Logging Added
Added logging to [`career_handler.php`](file:///c:/xampp/htdocs/placement_final/public/student/career_handler.php#L45-L51) to capture:
1. The complete student profile data retrieved
2. The institution detected
3. The final `roadmapStudentId` value being used

## Testing Steps

### 1. Test with GMIT Student
- Log in as a GMIT student
- Navigate to the AI Career Advisor
- Try to generate a career roadmap

### 2. Check Error Logs
The debug information will be logged to the PHP error log. Check one of these locations:
- `C:\xampp\apache\logs\error.log`
- `C:\xampp\php\logs\php_error_log`
- Or run this command to see recent logs:
  ```powershell
  Get-Content C:\xampp\apache\logs\error.log -Tail 50
  ```

### 3. Look for These Log Lines
```
Career Handler - Student Profile: {"id":...,"student_id":"...","usn":"...","institution":"gmit_new",...}
Career Handler - Institution: gmit_new, Roadmap Student ID: <value>
```

## What to Check

From the log output, verify:
1. **Is `student_id` present in the profile?** (Check if it's null, empty, or has a value)
2. **Is `usn` present as a fallback?** 
3. **What is the final `Roadmap Student ID` value?**

## Possible Issues

Based on the `student_id` value in the log:
- **If `student_id` is `null` or empty**: The query to `ad_student_details` isn't finding the student record
- **If `student_id` has a value but still stores as 0**: There might be a type casting issue
- **If `student_id` is "0"**: The database column actually contains "0"

## Next Steps
Once you have the log output, share it so we can identify the exact issue and fix it.
