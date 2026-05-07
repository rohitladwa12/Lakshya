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
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Goal Form - Student Portal</title>
    <style>
        :root {
            --primary: #800000;
            --primary-light: #a00000;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Outfit', sans-serif; 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            color: #333;
            padding-top: 72px; /* Navbar height offset */
        }

        .form-container {
            max-width: 800px;
            margin: 60px auto;
            padding: 0 20px;
        }
        
        .form-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 50px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 45px;
        }
        
        .form-header h1 {
            font-size: 36px;
            color: var(--primary);
            margin-bottom: 12px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .form-header p {
            color: #666;
            font-size: 17px;
        }
        
        .form-group {
            margin-bottom: 30px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #444;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.1);
            background: #fff;
        }
        
        .skills-input {
            display: flex;
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .skills-input button {
            padding: 0 25px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .skills-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .skill-tag {
            background: #fff;
            padding: 8px 16px;
            border-radius: 12px;
            border: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .skill-tag .remove {
            cursor: pointer;
            color: var(--primary);
            font-weight: 800;
            font-size: 18px;
            line-height: 1;
        }
        
        .submit-btn {
            width: 100%;
            padding: 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.2);
        }
        
        .submit-btn:hover {
            background: var(--primary-light);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(128, 0, 0, 0.3);
        }
        
        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .loading {
            text-align: center;
            padding: 50px 0;
            display: none;
        }
        
        .loading.active {
            display: block;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
            margin: 0 auto 30px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading h2 { color: var(--primary); margin-bottom: 10px; }
        .loading p { color: #888; }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="form-container">
    <div class="form-card">
        <div id="formSection">
            <div class="form-header">
                <h1>🎯 Design Your Career</h1>
                <p>Unlock an AI-crafted roadmap tailored to your destiny</p>
            </div>
            
            <form id="careerGoalForm">
                <div class="form-group">
                    <label for="targetRole">Target Dream Role *</label>
                    <input type="text" id="targetRole" name="target_role" required 
                           placeholder="e.g., Software Engineer, Cybersecurity Expert, Data Scientist">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label for="targetCompany">Preferred Company Type</label>
                        <select id="targetCompany" name="target_company_type" onchange="toggleOtherInput('targetCompany', 'otherCompanyGroup')">
                            <option value="MNC">MNC (Google, Microsoft, etc.)</option>
                            <option value="Startup">Innovative Startup</option>
                            <option value="Product">Product-Based Company</option>
                            <option value="Service">Service-Based Company</option>
                            <option value="Other">Other Type</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="targetIndustry">Industry Sector</label>
                        <select id="targetIndustry" name="target_industry" onchange="toggleOtherInput('targetIndustry', 'otherIndustryGroup')">
                            <option value="Technology">Technology & IT</option>
                            <option value="Finance">FinTech & Finance</option>
                            <option value="Healthcare">HealthTech</option>
                            <option value="E-commerce">E-commerce</option>
                            <option value="Security">Cybersecurity</option>
                            <option value="Automotive">Automotive & EV</option>
                            <option value="Construction">Construction & Civil</option>
                            <option value="Other">Other Sector</option>
                        </select>
                    </div>
                </div>

                <!-- Hidden Other Inputs -->
                <div id="otherCompanyGroup" style="display: none;" class="form-group">
                    <label for="otherCompany">Specify Company Type</label>
                    <input type="text" id="otherCompany" placeholder="e.g. Government, Research Lab, NGO">
                </div>
                
                <div id="otherIndustryGroup" style="display: none;" class="form-group">
                    <label for="otherIndustry">Specify Industry Sector</label>
                    <input type="text" id="otherIndustry" placeholder="e.g. Aerospace, Agriculture, Fashion">
                </div>
                
                <div class="form-group">
                    <label for="experienceLevel">Experience Ambition</label>
                    <select id="experienceLevel" name="experience_level">
                        <option value="Entry">Entry Level (Fresh Graduate)</option>
                        <option value="Mid">Associate / Mid-Level</option>
                        <option value="Senior">Senior Leadership</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Skills you already possess</label>
                    <div class="skills-input">
                        <input type="text" id="skillInput" placeholder="Add a skill...">
                        <button type="button" onclick="addSkill()">Add</button>
                    </div>
                    <div class="skills-tags" id="skillsTags"></div>
                    <input type="hidden" id="currentSkills" name="current_skills" value="[]">
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    ✨ Generate My Path
                </button>
            </form>
        </div>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <h2>Building Your Future...</h2>
            <p>Our AI is architecting a 6-phase journey for your role</p>
            <p style="margin-top: 10px; font-size: 13px;">Analyzing target role: <strong id="pollRoleDisplay"></strong></p>
        </div>
    </div>
</div>

<script>
let skills = <?php echo json_encode($prepopulatedSkills); ?>;

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

function toggleOtherInput(selectId, targetId) {
    const select = document.getElementById(selectId);
    const target = document.getElementById(targetId);
    if (select.value === 'Other') {
        target.style.display = 'block';
        target.querySelector('input').focus();
    } else {
        target.style.display = 'none';
    }
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

document.getElementById('skillInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); addSkill(); }
});

document.getElementById('careerGoalForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    let targetCompany = formData.get('target_company_type');
    let targetIndustry = formData.get('target_industry');
    
    if (targetCompany === 'Other') {
        const otherVal = document.getElementById('otherCompany').value.trim();
        if (otherVal) targetCompany = otherVal;
    }
    
    if (targetIndustry === 'Other') {
        const otherVal = document.getElementById('otherIndustry').value.trim();
        if (otherVal) targetIndustry = otherVal;
    }

    const data = {
        target_role: formData.get('target_role'),
        target_company_type: targetCompany,
        target_industry: targetIndustry,
        experience_level: formData.get('experience_level'),
        current_skills: JSON.parse(formData.get('current_skills'))
    };
    
    document.getElementById('pollRoleDisplay').textContent = data.target_role;
    document.getElementById('formSection').style.display = 'none';
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
            pollJobStatus(result.job_id, (finalResult) => {
                // finalResult is now {'success': true, 'roadmap_id': ID, 'roadmap': {...}}
                if (finalResult && finalResult.roadmap_id) {
                    window.location.href = 'career_roadmap.php?id=' + finalResult.roadmap_id;
                } else {
                    alert('Error: Roadmap ID not found in result.');
                    resetForm();
                }
            }, (err) => {
                alert('Generation failed: ' + err);
                resetForm();
            });
        } else {
            alert('Error: ' + (result.error || result.message));
            resetForm();
        }
    } catch (error) {
        alert('Exception: ' + error.message);
        resetForm();
    }
});

function resetForm() {
    document.getElementById('formSection').style.display = 'block';
    document.getElementById('loading').classList.remove('active');
    document.getElementById('submitBtn').disabled = false;
}

async function pollJobStatus(jobId, onSuccess, onError) {
    const poll = async () => {
        try {
            const res = await fetch(`ai_job_status.php?job_id=${jobId}`);
            const data = await res.json();
            if (data.status === 'completed') {
                onSuccess(data.result);
            } else if (data.status === 'failed') {
                onError(data.error);
            } else {
                setTimeout(poll, 1500);
            }
        } catch (e) { onError("Connection interrupted. Still retrying..."); setTimeout(poll, 3000); }
    };
    poll();
}
</script>
</body>
</html>

</body>
</html>

