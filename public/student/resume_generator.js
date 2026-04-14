// Resume Generator JavaScript
// Handles multi-step form, dynamic fields, and data management

let currentStep = 1;
const totalSteps = 8;
let resumeData = {
    education: [],
    experience: [],
    projects: [],
    skills: { technical: [], soft: [], languages: [] },
    certifications: [],
    achievements: [],
    template_id: 'professional_ats'
};

// Load existing resume data if available (passed from PHP)
if (typeof existingResumeData !== 'undefined' && existingResumeData) {
    resumeData = Object.assign(resumeData, existingResumeData);
    if (existingResumeData.template_id) {
        resumeData.template_id = existingResumeData.template_id;
    }
}

// Auto-populate from Portfolio if sections are empty
let portfolioSkillsToInclude = { technical: [], soft: [], languages: [] };
if (typeof portfolioData !== 'undefined' && portfolioData) {
    if (resumeData.projects.length === 0 && portfolioData.projects.length > 0) {
        resumeData.projects = portfolioData.projects;
    }
    if (resumeData.certifications.length === 0 && portfolioData.certifications.length > 0) {
        resumeData.certifications = portfolioData.certifications;
    }
    // For skills, we store them separately and add them via addTag during init
    if (resumeData.skills.technical.length === 0) {
        portfolioSkillsToInclude.technical = portfolioData.skills.technical;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    updateProgress();
    updateNavButtons();

    // Set active template visually if existing
    if (resumeData.template_id) {
        const card = document.querySelector(`.template-card[data-template="${resumeData.template_id}"]`);
        if (card) selectTemplate(resumeData.template_id, card);
    }

    // Load existing data
    if (resumeData.education.length > 0) {
        resumeData.education.forEach(edu => addEducation(edu));
    } else {
        addEducation(); // Add one empty education field
    }

    if (resumeData.experience.length > 0) {
        resumeData.experience.forEach(exp => addExperience(exp));
    }

    if (resumeData.projects.length > 0) {
        resumeData.projects.forEach(proj => addProject(proj));
    }

    // Load skills
    if (resumeData.skills.technical) {
        resumeData.skills.technical.forEach(skill => {
            if (typeof skill === 'object') addTag('technical', skill.name, skill.is_verified);
            else addTag('technical', skill);
        });
    }
    // Also load from portfolio if it was empty
    portfolioSkillsToInclude.technical.forEach(skill => {
        if (typeof skill === 'object') addTag('technical', skill.name, skill.is_verified);
        else addTag('technical', skill);
    });

    if (resumeData.skills.soft) {
        resumeData.skills.soft.forEach(skill => addTag('soft', skill));
    }
    if (resumeData.skills.languages) {
        resumeData.skills.languages.forEach(lang => addTag('languages', lang));
    }

    if (resumeData.certifications.length > 0) {
        resumeData.certifications.forEach(cert => addCertification(cert));
    }

    if (resumeData.achievements.length > 0) {
        resumeData.achievements.forEach(ach => addAchievement(ach));
    }

    // Form submission
    document.getElementById('resumeForm').addEventListener('submit', saveResume);
});

// Navigation
function nextStep() {
    try {
        if (currentStep < totalSteps) {
            // Validate current step
            if (!validateStep(currentStep)) {
                return;
            }

            // Collect data from current step
            collectStepData(currentStep);

            currentStep++;
            showStep(currentStep);

            // Generate preview on last step
            if (currentStep === 8) {
                generatePreview();
            }
        }
    } catch (e) {
        console.error('Next step error:', e);
        alert('An error occurred: ' + e.message);
    }
}

function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        showStep(currentStep);
    }
}

function selectTemplate(templateId, element) {
    // Update data
    resumeData.template_id = templateId;
    document.getElementById('template_id').value = templateId;

    // Update UI
    document.querySelectorAll('.template-card').forEach(card => {
        card.style.borderColor = '#ddd';
        card.classList.remove('active');
    });
    element.style.borderColor = '#800000';
    element.classList.add('active');
}

function showStep(step) {
    // Hide all steps
    document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));

    // Show current step
    document.querySelector(`.form-step[data-step="${step}"]`).classList.add('active');

    // Update progress
    updateProgress();
    updateNavButtons();
}

function updateProgress() {
    const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
    document.getElementById('progressFill').style.width = progress + '%';

    // Update step indicators
    document.querySelectorAll('.step').forEach((el, index) => {
        const stepNum = index + 1;
        el.classList.remove('active', 'completed');

        if (stepNum < currentStep) {
            el.classList.add('completed');
        } else if (stepNum === currentStep) {
            el.classList.add('active');
        }
    });
}

function updateNavButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    prevBtn.style.display = currentStep === 1 ? 'none' : 'block';
    nextBtn.style.display = currentStep === 8 ? 'none' : 'block';
}

// Validation
function validateStep(step) {
    try {
        const stepEl = document.querySelector(`.form-step[data-step="${step}"]`);
        if (!stepEl) return true; // Should not happen

        const requiredInputs = stepEl.querySelectorAll('[required]');

        for (let input of requiredInputs) {
            if (!input.value.trim()) {
                input.focus();
                alert('Please fill in all required fields');
                return false;
            }
        }
        return true;
    } catch (e) {
        console.error('Validation error:', e);
        return false;
    }
}

// Skill Tag Functions
function focusTagInput(container) {
    container.querySelector('input').focus();
}

function handleTagInput(e, type) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const input = e.target;
        const val = input.value.trim();
        if (val) {
            addTag(type, val);
            input.value = '';
        }
    }
}

function addTag(type, value, isVerified = false) {
    // Ensure the array exists
    if (!resumeData.skills[type]) {
        resumeData.skills[type] = [];
    }

    // Check for duplicates
    const existing = resumeData.skills[type].find(s => (typeof s === 'object' ? s.name : s) === value);
    if (existing) return;

    resumeData.skills[type].push(isVerified ? { name: value, is_verified: true } : value);

    const containerId = type === 'technical' ? 'technicalSkillsTags' :
        (type === 'soft' ? 'softSkillsTags' : 'languagesTags');
    const container = document.getElementById(containerId);
    if (!container) return;

    const tag = document.createElement('span');
    tag.className = 'tag';
    tag.innerHTML = `
        ${value}
        <span class="remove-tag" onclick="removeTag('${type}', '${value}', this)" style="margin-left:8px; cursor:pointer; opacity:0.7;">✕</span>
    `;

    // Insert before the input field
    const input = container.querySelector('input');
    container.insertBefore(tag, input);
}

function removeTag(type, value, element) {
    // Remove from resumeData
    if (resumeData.skills[type]) {
        resumeData.skills[type] = resumeData.skills[type].filter(s => (typeof s === 'object' ? s.name : s) !== value);
    }
    // Remove from DOM
    element.parentElement.remove();
}

// Data Collection
function collectStepData(step) {
    const form = document.getElementById('resumeForm');

    try {
        switch (step) {
            case 1: // Personal Info
                resumeData.full_name = form.querySelector('[name="full_name"]')?.value || '';
                resumeData.email = form.querySelector('[name="email"]')?.value || '';
                resumeData.phone = form.querySelector('[name="phone"]')?.value || '';
                resumeData.location = form.querySelector('[name="location"]')?.value || '';
                resumeData.linkedin_url = form.querySelector('[name="linkedin_url"]')?.value || '';
                resumeData.github_url = form.querySelector('[name="github_url"]')?.value || '';
                resumeData.portfolio_url = form.querySelector('[name="portfolio_url"]')?.value || '';
                resumeData.professional_summary = form.querySelector('[name="professional_summary"]')?.value || '';
                break;
            case 2: // Education
                resumeData.education = collectEducation();
                break;
            case 3: // Experience
                resumeData.experience = collectExperience();
                break;
            case 4: // Projects
                resumeData.projects = collectProjects();
                break;
            case 5: // Skills
                // Skills are already updated in real-time via addTag/removeTag
                break;
            case 6: // Certifications
                resumeData.certifications = collectCertifications();
                resumeData.achievements = collectAchievements();
                break;
            case 7: // Templates
                resumeData.template_id = document.getElementById('template_id')?.value || 'professional_ats';
                break;
        }
    } catch (e) {
        console.error('Error collecting data for step ' + step, e);
        // Do not block navigation, try to continue
    }
}

// Helper function to toggle end date field
function toggleEndDate(fieldId, isOngoing) {
    const endDateField = document.getElementById(fieldId);
    if (endDateField) {
        endDateField.disabled = isOngoing;
        if (isOngoing) {
            endDateField.value = '';
        }
    }
}

// Helper function to format date range
function formatDateRange(startDate, endDate, ongoing) {
    if (!startDate) return '';
    const start = new Date(startDate + '-01');
    const startStr = start.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });

    if (ongoing) {
        return `${startStr} - Present`;
    } else if (endDate) {
        const end = new Date(endDate + '-01');
        const endStr = end.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        return `${startStr} - ${endStr}`;
    }
    return startStr;
}

// Counters for unique IDs
let counters = {
    edu: 0,
    exp: 0,
    proj: 0,
    cert: 0,
    ach: 0
};


// Education Functions
function addEducation(data = {}) {
    const list = document.getElementById('educationList');
    const id = counters.edu++; // Unique ID

    const item = document.createElement('div');
    item.className = 'list-item';
    item.innerHTML = `
        <button type="button" class="remove-item-btn" onclick="this.parentElement.remove()">✕ Remove</button>
        <div class="form-row">
            <div class="form-group">
                <label>Degree/Program</label>
                <input type="text" class="field-degree" value="${data.degree || ''}" placeholder="e.g., B.Tech in Computer Science">
            </div>
            <div class="form-group">
                <label>Institution</label>
                <input type="text" class="field-institution" value="${data.institution || ''}" placeholder="e.g., GM University">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Start Date</label>
                <input type="month" class="field-start" value="${data.start_date || ''}">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="month" id="edu_end_${id}" class="field-end" value="${data.end_date || ''}" ${data.ongoing ? 'disabled' : ''}>
            </div>
        </div>
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" class="field-ongoing" onchange="toggleEndDate('edu_end_${id}', this.checked)" ${data.ongoing ? 'checked' : ''}>
                <span>Currently Pursuing</span>
            </label>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>CGPA/Percentage</label>
                <input type="text" class="field-cgpa" value="${data.cgpa || ''}" placeholder="e.g., 8.5/10">
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" class="field-location" value="${data.location || ''}" placeholder="e.g., Bangalore, Karnataka">
            </div>
        </div>
    `;

    list.appendChild(item);
}

function collectEducation() {
    const items = document.querySelectorAll('#educationList .list-item');
    const education = [];

    items.forEach((item) => {
        const degree = item.querySelector('.field-degree')?.value;
        const institution = item.querySelector('.field-institution')?.value;

        if (degree && institution) {
            const startDate = item.querySelector('.field-start')?.value || '';
            const endDate = item.querySelector('.field-end')?.value || '';
            const ongoing = item.querySelector('.field-ongoing')?.checked || false;

            education.push({
                degree: degree,
                institution: institution,
                start_date: startDate,
                end_date: endDate,
                ongoing: ongoing,
                year: formatDateRange(startDate, endDate, ongoing),
                cgpa: item.querySelector('.field-cgpa')?.value || '',
                location: item.querySelector('.field-location')?.value || ''
            });
        }
    });

    return education;
}

// Experience Functions (Refactored)
function addExperience(data = {}) {
    const list = document.getElementById('experienceList');
    const id = counters.exp++;

    const item = document.createElement('div');
    item.className = 'list-item';
    item.innerHTML = `
        <button type="button" class="remove-item-btn" onclick="this.parentElement.remove()">✕ Remove</button>
        <div class="form-row">
            <div class="form-group">
                <label>Job Title</label>
                <input type="text" class="field-title" value="${data.title || ''}" placeholder="e.g., Software Engineer Intern">
            </div>
            <div class="form-group">
                <label>Company</label>
                <input type="text" class="field-company" value="${data.company || ''}" placeholder="e.g., Tech Corp">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Start Date</label>
                <input type="month" class="field-start" value="${data.start_date || ''}">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="month" id="exp_end_${id}" class="field-end" value="${data.end_date || ''}" ${data.ongoing ? 'disabled' : ''}>
            </div>
        </div>
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" class="field-ongoing" onchange="toggleEndDate('exp_end_${id}', this.checked)" ${data.ongoing ? 'checked' : ''}>
                <span>Currently Working Here</span>
            </label>
        </div>
        <div class="form-group">
            <label>Location</label>
            <input type="text" class="field-location" value="${data.location || ''}" placeholder="e.g., Remote / Bangalore, India">
        </div>
        <div class="form-group">
            <label>Responsibilities (one per line)</label>
            <textarea class="field-responsibilities" placeholder="• Developed features using React...">${(data.responsibilities || []).join('\n')}</textarea>
        </div>
    `;

    list.appendChild(item);
}

function collectExperience() {
    const items = document.querySelectorAll('#experienceList .list-item');
    const experience = [];

    items.forEach((item) => {
        const title = item.querySelector('.field-title')?.value;
        const company = item.querySelector('.field-company')?.value;

        if (title && company) {
            const respText = item.querySelector('.field-responsibilities')?.value || '';
            const responsibilities = respText.split('\n').filter(r => r.trim());
            const startDate = item.querySelector('.field-start')?.value || '';
            const endDate = item.querySelector('.field-end')?.value || '';
            const ongoing = item.querySelector('.field-ongoing')?.checked || false;

            experience.push({
                title: title,
                company: company,
                start_date: startDate,
                end_date: endDate,
                ongoing: ongoing,
                duration: formatDateRange(startDate, endDate, ongoing),
                location: item.querySelector('.field-location')?.value || '',
                responsibilities: responsibilities
            });
        }
    });

    return experience;
}

// Project Functions (Refactored)
function addProject(data = {}) {
    const list = document.getElementById('projectsList');
    const id = counters.proj++;

    const item = document.createElement('div');
    item.className = 'list-item';
    item.innerHTML = `
        <button type="button" class="remove-item-btn" onclick="this.parentElement.remove()">✕ Remove</button>
        <div class="form-group">
            <label>Project Title</label>
            <input type="text" class="field-title" value="${data.title || ''}" placeholder="e.g., E-commerce Website">
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea class="field-description" placeholder="Brief description...">${data.description || ''}</textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Start Date</label>
                <input type="month" class="field-start" value="${data.start_date || ''}">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="month" id="proj_end_${id}" class="field-end" value="${data.end_date || ''}" ${data.ongoing ? 'disabled' : ''}>
            </div>
        </div>
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" class="field-ongoing" onchange="toggleEndDate('proj_end_${id}', this.checked)" ${data.ongoing ? 'checked' : ''}>
                <span>Ongoing Project</span>
            </label>
        </div>
        <div class="form-group">
            <label>Technologies Used (comma-separated)</label>
            <input type="text" class="field-technologies" value="${(data.technologies || []).join(', ')}" placeholder="e.g., React, Node.js">
        </div>
        <div class="form-group">
            <label>Project Link</label>
            <input type="url" class="field-link" value="${data.link || ''}" placeholder="https://...">
        </div>
    `;

    list.appendChild(item);
}

function collectProjects() {
    const items = document.querySelectorAll('#projectsList .list-item');
    const projects = [];

    items.forEach((item) => {
        const title = item.querySelector('.field-title')?.value;

        if (title) {
            const techText = item.querySelector('.field-technologies')?.value || '';
            const technologies = techText.split(',').map(t => t.trim()).filter(t => t);
            const startDate = item.querySelector('.field-start')?.value || '';
            const endDate = item.querySelector('.field-end')?.value || '';
            const ongoing = item.querySelector('.field-ongoing')?.checked || false;

            projects.push({
                title: title,
                description: item.querySelector('.field-description')?.value || '',
                start_date: startDate,
                end_date: endDate,
                ongoing: ongoing,
                duration: formatDateRange(startDate, endDate, ongoing),
                technologies: technologies,
                link: item.querySelector('.field-link')?.value || ''
            });
        }
    });

    return projects;
}

// Certifications Functions (Refactored)
function addCertification(data = {}) {
    const list = document.getElementById('certificationsList');
    // const id = counters.cert++; // Not needed as no toggles

    const item = document.createElement('div');
    item.className = 'list-item';
    item.innerHTML = `
        <button type="button" class="remove-item-btn" onclick="this.parentElement.remove()">✕ Remove</button>
        <div class="form-row">
            <div class="form-group">
                <label>Certification Name</label>
                <input type="text" class="field-name" value="${data.name || ''}" placeholder="e.g., AWS Certified Developer">
            </div>
            <div class="form-group">
                <label>Issuing Organization</label>
                <input type="text" class="field-issuer" value="${data.issuer || ''}" placeholder="e.g., Amazon Web Services">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Date</label>
                <input type="month" class="field-date" value="${data.date || ''}">
            </div>
            <div class="form-group">
                <label>Credential URL</label>
                <input type="url" class="field-url" value="${data.credential_url || ''}" placeholder="https://...">
            </div>
        </div>
    `;

    list.appendChild(item);
}

function collectCertifications() {
    const items = document.querySelectorAll('#certificationsList .list-item');
    const certifications = [];

    items.forEach((item) => {
        const name = item.querySelector('.field-name')?.value;
        if (name) {
            certifications.push({
                name: name,
                issuer: item.querySelector('.field-issuer')?.value || '',
                date: item.querySelector('.field-date')?.value || '',
                credential_url: item.querySelector('.field-url')?.value || ''
            });
        }
    });

    return certifications;
}

// Achievements Functions (Refactored)
function addAchievement(data = {}) {
    const list = document.getElementById('achievementsList');

    const item = document.createElement('div');
    item.className = 'list-item';
    item.innerHTML = `
        <button type="button" class="remove-item-btn" onclick="this.parentElement.remove()">✕ Remove</button>
        <div class="form-row">
            <div class="form-group">
                <label>Achievement Title</label>
                <input type="text" class="field-title" value="${data.title || ''}" placeholder="e.g., First Prize in Hackathon">
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="month" class="field-date" value="${data.date || ''}">
            </div>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea class="field-description" placeholder="Brief description...">${data.description || ''}</textarea>
        </div>
    `;

    list.appendChild(item);
}

function collectAchievements() {
    const items = document.querySelectorAll('#achievementsList .list-item');
    const achievements = [];

    items.forEach((item) => {
        const title = item.querySelector('.field-title')?.value;
        if (title) {
            achievements.push({
                title: title,
                date: item.querySelector('.field-date')?.value || '',
                description: item.querySelector('.field-description')?.value || ''
            });
        }
    });

    return achievements;
}

// Preview Generation
function generatePreview() {
    // Collect all data
    for (let i = 1; i <= 7; i++) {
        collectStepData(i);
    }

    const templateId = resumeData.template_id || 'professional_ats';
    const preview = document.getElementById('resumePreview');

    let html = '';

    if (templateId === 'modern_creative') {
        html = renderModernPreview();
    } else if (templateId === 'minimal_clean') {
        html = renderMinimalPreview();
    } else {
        html = renderATSPreview();
    }

    preview.innerHTML = html;
}

function renderATSPreview() {
    return `
        <div style="font-family: 'Times New Roman', Times, serif; color: #333; line-height: 1.5; padding: 20px; border: 1px solid #eee; background: white; min-height: 800px;">
            <div style="text-align: center; border-bottom: 2px solid #800000; padding-bottom: 10px; margin-bottom: 20px;">
                <h1 style="font-size: 28px; margin: 0; text-transform: uppercase;">${resumeData.full_name || 'Your Name'}</h1>
                <div style="font-size: 14px; margin-top: 5px; color: #555;">
                    ${resumeData.email} | ${resumeData.phone} | ${resumeData.location}
                </div>
                <div style="font-size: 13px; margin-top: 5px; display: flex; justify-content: center; gap: 15px;">
                    ${resumeData.linkedin_url ? `<a href="${ensureProtocol(resumeData.linkedin_url)}" target="_blank" style="color: #0066cc; text-decoration: underline;">LinkedIn</a>` : ''}
                    ${resumeData.github_url ? `<a href="${ensureProtocol(resumeData.github_url)}" target="_blank" style="color: #0066cc; text-decoration: underline;">GitHub</a>` : ''}
                    ${resumeData.portfolio_url ? `<a href="${ensureProtocol(resumeData.portfolio_url)}" target="_blank" style="color: #0066cc; text-decoration: underline;">Portfolio</a>` : ''}
                </div>
            </div>
            
            ${resumeData.professional_summary ? `
            <div style="margin-bottom: 20px;">
                <h2 style="font-size: 16px; font-weight: bold; color: #800000; border-bottom: 1px solid #ccc; text-transform: uppercase;">Summary</h2>
                <div style="margin-top: 5px; text-align: justify; white-space: pre-line;">${resumeData.professional_summary}</div>
            </div>
            ` : ''}

            ${renderSectionsStandard()}
        </div>
    `;
}

function renderModernPreview() {
    return `
        <div style="font-family: 'Times New Roman', Times, serif; display: flex; min-height: 800px; background: white; border: 1px solid #eee;">
            <div style="width: 35%; background: #2c3e50; color: white; padding: 25px;">
                <div style="width: 80px; height: 80px; background: #3e5871; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: bold; color: #e9c66f;">
                    ${(resumeData.full_name || 'Y').charAt(0).toUpperCase()}
                </div>
                
                <h3 style="color: #e9c66f; border-bottom: 1px solid #456; padding-bottom: 5px; font-size: 14px; text-transform: uppercase;">Contact</h3>
                <div style="font-size: 12px; margin: 10px 0;">
                    <div style="margin-bottom: 5px;">✉️ ${resumeData.email}</div>
                    <div style="margin-bottom: 5px;">📞 ${resumeData.phone}</div>
                    <div style="margin-bottom: 5px;">📍 ${resumeData.location}</div>
                </div>

                ${resumeData.skills.technical.length > 0 ? `
                <h3 style="color: #e9c66f; border-bottom: 1px solid #456; padding-bottom: 5px; font-size: 14px; text-transform: uppercase; margin-top: 25px;">Skills</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 10px;">
                    ${resumeData.skills.technical.map(s => `<span style="background: #3e5871; padding: 2px 8px; border-radius: 4px; font-size: 11px;">${s}</span>`).join('')}
                </div>
                ` : ''}
                
                <h3 style="color: #e9c66f; border-bottom: 1px solid #456; padding-bottom: 5px; font-size: 14px; text-transform: uppercase; margin-top: 25px;">Links</h3>
                <div style="font-size: 12px; margin-top: 10px;">
                    ${resumeData.linkedin_url ? `<div style="margin-bottom: 5px;"><a href="${ensureProtocol(resumeData.linkedin_url)}" target="_blank" style="color: #e9c66f; text-decoration: underline;">🔗 LinkedIn</a></div>` : ''}
                    ${resumeData.github_url ? `<div style="margin-bottom: 5px;"><a href="${ensureProtocol(resumeData.github_url)}" target="_blank" style="color: #e9c66f; text-decoration: underline;">🔗 GitHub</a></div>` : ''}
                    ${resumeData.portfolio_url ? `<div style="margin-bottom: 5px;"><a href="${ensureProtocol(resumeData.portfolio_url)}" target="_blank" style="color: #e9c66f; text-decoration: underline;">🔗 Portfolio</a></div>` : ''}
                </div>
            </div>
            <div style="width: 65%; padding: 30px; color: #333;">
                <h1 style="font-size: 32px; color: #800000; margin: 0;">${resumeData.full_name || 'Your Name'}</h1>
                <p style="color: #666; font-style: italic; margin-top: 5px;">${resumeData.professional_summary ? resumeData.professional_summary.substring(0, 100) + '...' : ''}</p>
                
                ${renderSectionsModern()}
            </div>
        </div>
    `;
}

function renderMinimalPreview() {
    return `
        <div style="font-family: 'Times New Roman', Times, serif; padding: 40px; background: white; min-height: 800px; border: 1px solid #eee;">
            <div style="text-align: left; margin-bottom: 30px;">
                <h1 style="font-size: 36px; font-weight: 300; letter-spacing: 1px;">${resumeData.full_name || 'Your Name'}</h1>
                <div style="font-size: 13px; color: #666; margin-top: 5px;">
                    ${resumeData.email} • ${resumeData.phone} • ${resumeData.location}
                </div>
            </div>
            
            ${renderSectionsMinimal()}
        </div>
    `;
}

function renderSectionsStandard() {
    let html = '';

    // Education
    if (resumeData.education.length > 0) {
        html += `
            <div style="margin-bottom: 20px;">
                <h2 style="font-size: 16px; font-weight: bold; color: #800000; border-bottom: 1px solid #ccc; text-transform: uppercase;">Education</h2>
                ${resumeData.education.map(edu => `
                    <div style="margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; font-weight: bold;">
                            <span>${edu.degree}</span>
                            <span>${edu.year}</span>
                        </div>
                        <div style="font-style: italic; color: #555;">${edu.institution}</div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    // Experience
    if (resumeData.experience.length > 0) {
        html += `
            <div style="margin-bottom: 20px;">
                <h2 style="font-size: 16px; font-weight: bold; color: #800000; border-bottom: 1px solid #ccc; text-transform: uppercase;">Experience</h2>
                ${resumeData.experience.map(exp => `
                    <div style="margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; font-weight: bold;">
                            <span>${exp.title}</span>
                            <span>${exp.duration}</span>
                        </div>
                        <div style="color: #555;">${exp.company}</div>
                        <ul style="margin-top: 5px; margin-left: 20px;">
                            ${exp.responsibilities.map(r => `<li>${r}</li>`).join('')}
                        </ul>
                    </div>
                `).join('')}
            </div>
        `;
    }

    // Projects
    if (resumeData.projects.length > 0) {
        html += `
            <div style="margin-bottom: 15px;">
                <h2 style="font-size: 16px; font-weight: bold; color: #800000; border-bottom: 1px solid #ccc; text-transform: uppercase; break-after: avoid;">Projects</h2>
                ${resumeData.projects.map(p => `
                    <div style="margin-top: 8px; break-inside: avoid;">
                        <div style="display: flex; justify-content: space-between; font-weight: bold; align-items: baseline;">
                            <span>${p.title} ${p.link ? `<a href="${ensureProtocol(p.link)}" target="_blank" style="color: #0066cc; font-size: 11px; text-decoration: underline; font-weight: normal; margin-left: 8px;">[View]</a>` : ''}</span>
                            <span style="font-size: 12px; color: #666;">${p.duration}</span>
                        </div>
                        <ul style="margin: 3px 0 0 20px; font-size: 13px;">${p.description.split('\n').filter(l=>l.trim()).map(l=>`<li style="margin-bottom:1px;">${l}</li>`).join('')}</ul>
                    </div>
                `).join('')}
            </div>
        `;
    }

    // Skills
    if (resumeData.skills.technical.length > 0) {
        const skillList = resumeData.skills.technical.map(s => {
            const name = typeof s === 'object' ? s.name : s;
            const verified = typeof s === 'object' && s.is_verified;
            return `${name}`;
        }).join(', ');

        html += `
            <div style="margin-bottom: 20px;">
                <h2 style="font-size: 16px; font-weight: bold; color: #800000; border-bottom: 1px solid #ccc; text-transform: uppercase;">Skills</h2>
                <p style="margin-top: 10px;"><strong>Technical:</strong> ${skillList}</p>
                ${resumeData.skills.soft.length > 0 ? `<p><strong>Soft Skills:</strong> ${resumeData.skills.soft.map(s => typeof s === 'object' ? s.name : s).join(', ')}</p>` : ''}
            </div>
        `;
    }

    // Certifications & Achievements
    if (resumeData.certifications.length > 0 || resumeData.achievements.length > 0) {
        html += `
            <div style="margin-bottom: 15px;">
                <h2 style="font-size: 16px; font-weight: bold; color: #800000; border-bottom: 1px solid #ccc; text-transform: uppercase; break-after: avoid;">Certifications & Achievements</h2>
                ${resumeData.certifications.map(c => `
                    <div style="margin-top: 5px; break-inside: avoid;">
                        • <strong>${c.name}</strong> (${c.issuer}, ${c.date}) ${c.credential_url ? `<a href="${ensureProtocol(c.credential_url)}" target="_blank" style="color: #0066cc; font-size: 11px; text-decoration: underline; margin-left: 8px;">[View]</a>` : ''}
                    </div>`).join('')}
                ${resumeData.achievements.map(a => `<div style="margin-top: 5px; break-inside: avoid;">• ${a.title} (${a.date})</div>`).join('')}
            </div>
        `;
    }

    return html;
}

function renderSectionsModern() {
    let html = '';

    if (resumeData.education.length > 0) {
        html += `
            <div style="margin-top: 25px;">
                <h3 style="font-size: 16px; font-weight: bold; color: #34495e; border-left: 4px solid #800000; padding-left: 10px; text-transform: uppercase;">Education</h3>
                ${resumeData.education.map(edu => `
                    <div style="margin-top: 10px;">
                        <div style="font-weight: bold; color: #1a1a1a;">${edu.degree}</div>
                        <div style="font-size: 13px; color: #800000;">${edu.institution} | ${edu.year}</div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    if (resumeData.experience.length > 0) {
        html += `
            <div style="margin-top: 25px;">
                <h3 style="font-size: 16px; font-weight: bold; color: #34495e; border-left: 4px solid #800000; padding-left: 10px; text-transform: uppercase;">Experience</h3>
                ${resumeData.experience.map(exp => `
                    <div style="margin-top: 10px;">
                        <div style="font-weight: bold; color: #1a1a1a;">${exp.title}</div>
                        <div style="font-size: 13px; color: #666;">${exp.company} • ${exp.duration}</div>
                        <ul style="margin-top: 5px; font-size: 13px; color: #444;">
                            ${exp.responsibilities.map(r => `<li>${r}</li>`).join('')}
                        </ul>
                    </div>
                `).join('')}
            </div>
        `;
    }

    if (resumeData.projects.length > 0) {
        html += `
            <div style="margin-top: 18px; break-inside: auto;">
                <h3 style="font-size: 16px; font-weight: bold; color: #34495e; border-left: 4px solid #800000; padding-left: 10px; text-transform: uppercase; break-after: avoid;">Projects</h3>
                ${resumeData.projects.map(p => `
                    <div style="margin-top: 8px; break-inside: avoid;">
                        <div style="font-weight: bold; color: #1a1a1a;">${p.title} ${p.link ? `<a href="${ensureProtocol(p.link)}" target="_blank" style="color: #0066cc; font-size: 11px; text-decoration: underline; margin-left: 8px; font-weight: normal;">[View]</a>` : ''}</div>
                        <ul style="margin: 2px 0 0 16px; color: #444; font-size: 13px;">${p.description.split('\n').filter(l=>l.trim()).map(l=>`<li style="margin-bottom:1px;">${l}</li>`).join('')}</ul>
                    </div>
                `).join('')}
            </div>
        `;
    }

    if (resumeData.skills.soft.length > 0) {
        html += `
            <div style="margin-top: 25px;">
                <h3 style="font-size: 16px; font-weight: bold; color: #34495e; border-left: 4px solid #800000; padding-left: 10px; text-transform: uppercase;">Soft Skills</h3>
                <p style="font-size: 13px; color: #444; margin-top: 10px;">${resumeData.skills.soft.join(', ')}</p>
            </div>
        `;
    }

    if (resumeData.certifications.length > 0 || resumeData.achievements.length > 0) {
        html += `
            <div style="margin-top: 25px;">
                <h3 style="font-size: 16px; font-weight: bold; color: #34495e; border-left: 4px solid #800000; padding-left: 10px; text-transform: uppercase;">Certifications & Achievements</h3>
                <div style="font-size: 13px; color: #444; margin-top: 10px;">
                    ${resumeData.certifications.map(c => `<div style="margin-bottom: 5px;">• ${c.name} (${c.issuer})</div>`).join('')}
                    ${resumeData.achievements.map(a => `<div style="margin-bottom: 5px;">• ${a.title}</div>`).join('')}
                </div>
            </div>
        `;
    }

    return html;
}

function renderSectionsMinimal() {
    let html = '';

    if (resumeData.education.length > 0) {
        html += `
            <div style="margin-top: 25px;">
                <h3 style="font-size: 12px; font-weight: bold; color: #666; letter-spacing: 2px; text-transform: uppercase;">Education</h3>
                <div style="height: 1px; background: #eee; width: 100%; margin: 5px 0 10px;"></div>
                ${resumeData.education.map(edu => `
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: 600;">${edu.degree}</span>
                            <span style="color: #888; font-size: 12px;">${edu.year}</span>
                        </div>
                        <div style="color: #666; font-size: 13px;">${edu.institution}</div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    if (resumeData.experience.length > 0) {
        html += `
            <div style="margin-top: 25px;">
                <h3 style="font-size: 12px; font-weight: bold; color: #666; letter-spacing: 2px; text-transform: uppercase;">Experience</h3>
                <div style="height: 1px; background: #eee; width: 100%; margin: 5px 0 10px;"></div>
                ${resumeData.experience.map(exp => `
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: 600;">${exp.title}</span>
                            <span style="color: #888; font-size: 12px;">${exp.duration}</span>
                        </div>
                        <div style="font-weight: 500;">${exp.company}</div>
                        <ul style="margin-top: 5px; font-size: 13px; color: #444; padding-left: 20px;">
                            ${exp.responsibilities.map(r => `<li>${r}</li>`).join('')}
                        </ul>
                    </div>
                `).join('')}
            </div>
        `;
    }

    if (resumeData.projects.length > 0) {
        html += `
            <div style="margin-top: 18px;">
                <h3 style="font-size: 12px; font-weight: bold; color: #666; letter-spacing: 2px; text-transform: uppercase; break-after: avoid;">Projects</h3>
                <div style="height: 1px; background: #eee; width: 100%; margin: 5px 0 10px;"></div>
                ${resumeData.projects.map(p => `
                    <div style="margin-bottom: 8px; break-inside: avoid;">
                        <div style="font-weight: 600;">${p.title} ${p.link ? `<a href="${ensureProtocol(p.link)}" target="_blank" style="color: #0066cc; font-size: 11px; text-decoration: underline; font-weight: normal; margin-left: 8px;">[View]</a>` : ''}</div>
                        <ul style="margin: 2px 0 0 16px; color: #666; font-size: 13px;">${p.description.split('\n').filter(l=>l.trim()).map(l=>`<li style="margin-bottom:1px;">${l}</li>`).join('')}</ul>
                    </div>
                `).join('')}
            </div>
        `;
    }

    if (resumeData.skills.technical.length > 0 || resumeData.skills.soft.length > 0) {
        html += `
            <div style="margin-top: 25px;">
                <h3 style="font-size: 12px; font-weight: bold; color: #666; letter-spacing: 2px; text-transform: uppercase;">Skills</h3>
                <div style="height: 1px; background: #eee; width: 100%; margin: 5px 0 10px;"></div>
                <div style="font-size: 13px; color: #444;">
                    ${resumeData.skills.technical.length > 0 ? `<div><strong>Technical:</strong> ${resumeData.skills.technical.join(', ')}</div>` : ''}
                    ${resumeData.skills.soft.length > 0 ? `<div style="margin-top: 5px;"><strong>Soft Skills:</strong> ${resumeData.skills.soft.join(', ')}</div>` : ''}
                </div>
            </div>
        `;
    }

    if (resumeData.certifications.length > 0 || resumeData.achievements.length > 0) {
        html += `
            <div style="margin-top: 25px;">
                <h3 style="font-size: 12px; font-weight: bold; color: #666; letter-spacing: 2px; text-transform: uppercase;">Certifications & Achievements</h3>
                <div style="height: 1px; background: #eee; width: 100%; margin: 5px 0 10px;"></div>
                <div style="font-size: 13px; color: #444;">
                    ${resumeData.certifications.map(c => `<div style="margin-bottom: 5px;">• ${c.name} (${c.issuer})</div>`).join('')}
                    ${resumeData.achievements.map(a => `<div style="margin-bottom: 5px;">• ${a.title}</div>`).join('')}
                </div>
            </div>
        `;
    }

    return html;
}

// Save Resume
async function saveResume(e) {
    if (e) e.preventDefault();

    // Collect all data
    for (let i = 1; i <= 7; i++) {
        collectStepData(i);
    }

    try {
        const response = await fetch('resume_handler', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'save',
                resumeData: resumeData
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ Resume saved successfully!');
        } else {
            alert('❌ Error saving resume: ' + result.message);
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
}

// Download PDF
async function downloadPDF() {
    // Collect all data
    for (let i = 1; i <= 7; i++) {
        collectStepData(i);
    }

    try {
        // Create a hidden form to submit and open in new tab
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'resume_handler';
        form.target = '_blank';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'data';
        input.value = JSON.stringify({
            action: 'generate_pdf',
            resumeData: resumeData
        });

        form.appendChild(input);
        document.body.appendChild(form);

        // Since resume_handler expects JSON in php://input, we might need to adjust or use fetch and open window
        // But resume_handler.php currently uses json_decode(file_get_contents('php://input'), true)

        // Alternative: Use fetch to get HTML and write to new window
        const response = await fetch('resume_handler', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'generate_pdf', resumeData: resumeData })
        });

        if (response.ok) {
            const html = await response.text();
            const win = window.open('', '_blank');
            win.document.write(html);
            win.document.close();
        } else {
            alert('❌ Error generating PDF');
        }

    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
}

/**
 * Ensures a URL has a protocol (http:// or https://)
 */
function ensureProtocol(url) {
    if (!url) return '';
    if (!/^https?:\/\//i.test(url)) {
        return 'https://' + url;
    }
    return url;
}
