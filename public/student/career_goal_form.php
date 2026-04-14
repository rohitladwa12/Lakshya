<?php
/**
 * Career Goal Form
 * Student inputs their career aspirations
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();

// Load student profile
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
require_once __DIR__ . '/../../src/Models/Portfolio.php';

$studentModel = new StudentProfile();
$portfolioModel = new Portfolio();

$profile = $studentModel->getProfile($userId);
$institution = $profile['institution'] ?? getInstitution();
$username = getUsername();

// Fetch existing skills from portfolio
$portfolioSkills = $portfolioModel->getStudentSkills($username, $institution);
$prepopulatedSkills = array_column($portfolioSkills, 'title');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Goal Form - Student Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .navbar { background: linear-gradient(135deg, #800000 0%, #a00000 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h1 { font-size: 24px; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; transition: opacity 0.3s; }
        .navbar a:hover { opacity: 0.8; }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<?php
?>

<style>
    .form-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 0 20px;
    }
    
    .form-card {
        background: white;
        border-radius: 12px;
        padding: 40px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    }
    
    .form-header {
        text-align: center;
        margin-bottom: 40px;
    }
    
    .form-header h1 {
        color: #800000;
        margin-bottom: 10px;
    }
    
    .form-header p {
        color: #666;
        font-size: 16px;
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 15px;
        transition: border-color 0.3s;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #800000;
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .skills-input {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .skills-input input {
        flex: 1;
    }
    
    .skills-input button {
        padding: 12px 20px;
        background: #800000;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    
    .skills-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }
    
    .skill-tag {
        background: #f0f0f0;
        padding: 8px 15px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .skill-tag .remove {
        cursor: pointer;
        color: #800000;
        font-weight: bold;
    }
    
    .submit-btn {
        width: 100%;
        padding: 15px;
        background: #800000;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 18px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .submit-btn:hover {
        background: #a00000;
        transform: translateY(-2px);
    }
    
    .submit-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
    }
    
    .loading {
        text-align: center;
        padding: 40px;
        display: none;
    }
    
    .loading.active {
        display: block;
    }
    
    .spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #800000;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h1>🎯 Tell Us Your Career Goal</h1>
            <p>Share your aspirations and we'll create a personalized roadmap</p>
        </div>
        
        <form id="careerGoalForm">
            <div class="form-group">
                <label for="targetRole">Target Role *</label>
                <input type="text" id="targetRole" name="target_role" required 
                       placeholder="e.g., Full Stack Developer, Data Scientist, Product Manager">
            </div>
            
            <div class="form-group">
                <label for="targetCompany">Target Company Type</label>
                <input type="text" id="targetCompany" name="target_company_type" 
                       placeholder="e.g., Startup, MNC, Product Company">
            </div>
            
            <div class="form-group">
                <label for="targetIndustry">Industry</label>
                <select id="targetIndustry" name="target_industry">
                    <option value="Technology">Technology</option>
                    <option value="Finance">Finance</option>
                    <option value="Healthcare">Healthcare</option>
                    <option value="E-commerce">E-commerce</option>
                    <option value="Education">Education</option>
                    <option value="Consulting">Consulting</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="experienceLevel">Experience Level</label>
                <select id="experienceLevel" name="experience_level">
                    <option value="Entry">Entry Level (0-2 years)</option>
                    <option value="Mid">Mid Level (2-5 years)</option>
                    <option value="Senior">Senior Level (5+ years)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Current Skills (Optional)</label>
                <div class="skills-input">
                    <input type="text" id="skillInput" placeholder="Enter a skill and press Add">
                    <button type="button" onclick="addSkill()">Add</button>
                </div>
                <div class="skills-tags" id="skillsTags"></div>
                <input type="hidden" id="currentSkills" name="current_skills" value="[]">
            </div>
            
            <button type="submit" class="submit-btn" id="submitBtn">
                🚀 Generate My Roadmap
            </button>
        </form>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>🤖 AI is creating your personalized roadmap...</p>
            <p style="color: #666; font-size: 14px;">This may take 10-15 seconds</p>
        </div>
    </div>
</div>

<script>
let skills = <?php echo json_encode($prepopulatedSkills); ?>;

// Initialize skills tags on load
document.addEventListener('DOMContentLoaded', updateSkillsTags);

function addSkill() {
    const input = document.getElementById('skillInput');
    const skill = input.value.trim();
    
    if (skill && !skills.includes(skill)) {
        skills.push(skill);
        updateSkillsTags();
        input.value = '';
    }
}

function removeSkill(skill) {
    skills = skills.filter(s => s !== skill);
    updateSkillsTags();
}

function updateSkillsTags() {
    const container = document.getElementById('skillsTags');
    container.innerHTML = skills.map(skill => `
        <div class="skill-tag">
            <span>${skill}</span>
            <span class="remove" onclick="removeSkill('${skill}')">×</span>
        </div>
    `).join('');
    
    document.getElementById('currentSkills').value = JSON.stringify(skills);
}



// Allow Enter key to add skill
document.getElementById('skillInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addSkill();
    }
});



// Form submission
document.getElementById('careerGoalForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        target_role: formData.get('target_role'),
        target_company_type: formData.get('target_company_type'),
        target_industry: formData.get('target_industry'),
        experience_level: formData.get('experience_level'),
        current_skills: JSON.parse(formData.get('current_skills'))
    };
    
    // Show loading
    document.getElementById('careerGoalForm').style.display = 'none';
    document.getElementById('loading').classList.add('active');
    document.getElementById('submitBtn').disabled = true;
    
    try {
        const response = await fetch('career_handler', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ action: 'generate_roadmap', goalData: data })
        });
        
        const result = await response.json();
        
        if (result.success && result.job_id) {
            // Poll for result
            pollJobStatus(result.job_id, (finalResult) => {
                // finalResult is the roadmap_id 
                window.location.href = 'career_roadmap.php?id=' + finalResult;
            }, (err) => {
                alert('Planning failed: ' + err);
                resetForm();
            });
        } else {
            alert('Error: ' + (result.error || result.message));
            resetForm();
        }
    } catch (error) {
        alert('Error generating roadmap: ' + error.message);
        resetForm();
    }
});

function resetForm() {
    document.getElementById('careerGoalForm').style.display = 'block';
    document.getElementById('loading').classList.remove('active');
    document.getElementById('submitBtn').disabled = false;
}

async function pollJobStatus(jobId, onSuccess, onError) {
    const poll = async () => {
        try {
            const res = await fetch(`ai_job_status.php?job_id=${jobId}`);
            const data = await res.json();
            if (data.status === 'completed') onSuccess(data.result);
            else if (data.status === 'failed') onError(data.error);
            else setTimeout(poll, 1500);
        } catch (e) { onError("Polling error"); }
    };
    poll();
}
</script>

</body>
</html>
