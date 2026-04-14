/**
 * resume_builder.js
 * FlowCV-style resume builder engine
 * – Live preview, 3 templates, tag skills, dynamic entries, autosave
 */

'use strict';

// ─────────────────────────────────────────────────────────
// STATE
// ─────────────────────────────────────────────────────────
let RD = {               // Resume Data
    full_name: '', email: '', phone: '', location: '',
    gender: '', address: '',
    linkedin_url: '', github_url: '', portfolio_url: '',
    professional_summary: '',
    template_id: 'professional_ats',
    education:       [],
    experience:      [],
    projects:        [],
    skills: { 
        technical: [
            { category: '', items: [] }
        ], 
        soft: [], 
        languages: [] 
    },
    certifications:  [],
    achievements:    [],
};

let previewTimer  = null;
let autoSaveTimer = null;
let entryCounters = { education:0, experience:0, projects:0, certifications:0, achievements:0 };

// ─────────────────────────────────────────────────────────
// INIT
// ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // We will show the onboarding modal after loadResume() if no resume exists.
    loadResume();
    // Autosave every 30s
    autoSaveTimer = setInterval(() => saveResume(true), 30000);
});

// ─────────────────────────────────────────────────────────
// LOAD FROM SERVER
// ─────────────────────────────────────────────────────────
async function loadResume() {
    try {
        const res  = await fetch(HANDLER_URL + '?action=load_resume');
        const data = await res.json();
        if (data.success && data.resume) {
            applyResumeData(data.resume);
            
            if (!data.autofilled) {
                // Resume already exists -> close modal and hide it
                const modal = document.getElementById('onboardingModal');
                if (modal) modal.style.display = 'none';
                sessionStorage.setItem('resume_onboarding_dismissed', 'true');
            } else {
                // Resume does NOT exist -> show onboarding modal if not dismissed
                const modal = document.getElementById('onboardingModal');
                if (modal && !sessionStorage.getItem('resume_onboarding_dismissed')) {
                    modal.style.display = 'flex';
                }
            }
        }
    } catch (e) {
        console.warn('Could not load resume:', e);
    }
    renderPreview();
}

function applyResumeData(r) {
    // Personal fields
    setVal('f_full_name', r.full_name);
    setVal('f_email',     r.email);
    setVal('f_phone',     r.phone);
    setVal('f_gender',    r.gender);
    setVal('f_address',   r.address);
    setVal('f_location',  r.location);
    setVal('f_linkedin',  r.linkedin_url);
    setVal('f_github',    r.github_url);
    setVal('f_portfolio', r.portfolio_url);
    setVal('f_summary',   r.professional_summary);

    // Template
    if (r.template_id) {
        RD.template_id = r.template_id;
        document.querySelectorAll('.tpl-btn').forEach(b => {
            b.classList.toggle('active', b.dataset.tpl === r.template_id);
        });
    }

    // Dynamic sections
    (r.education     || []).forEach(d => addEntry('education',     d));
    (r.experience    || []).forEach(d => addEntry('experience',    d));
    (r.projects      || []).forEach(d => addEntry('projects',      d));
    (r.certifications|| []).forEach(d => addEntry('certifications',d));
    (r.achievements  || []).forEach(d => addEntry('achievements',  d));

    // Skills tags
    const skills = r.skills || {};
    
    // Technical Skills (migrate if old flat array)
    const tech = skills.technical || [];
    const techList = document.getElementById('tech-groups-list');
    if (techList) techList.innerHTML = ''; // Clear default
    
    if (tech.length > 0 && typeof tech[0] === 'string') {
        // Migration: Old flat array -> one group
        addSkillGroup('', tech);
    } else if (tech.length > 0) {
        // New structure: Array of objects
        RD.skills.technical = []; // Clear default
        tech.forEach(g => addSkillGroup(g.category, g.items));
    } else {
        // Empty default
        addSkillGroup('');
    }

    (skills.soft      || []).forEach(s => addTag('soft', s));
    (skills.languages || []).forEach(s => addTag('languages', s));
}

// ─── SYNC FROM PORTFOLIO ─────────────────────────────────
async function syncPortfolio() {
    const btn = document.getElementById('btn-sync-portfolio');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

    try {
        const res  = await fetch(HANDLER_URL + '?action=fetch_portfolio');
        const data = await res.json();
        
        if (!data.success) {
            showToast('Failed to fetch portfolio: ' + (data.error || 'Unknown error'), 'error');
            return;
        }

        let newSkills = 0;
        let newProjects = 0;
        let newCerts = 0;

        // 1. Sync Skills (Technical) - Add to first group
        const currentTechLower = RD.skills.technical.flatMap(g => g.items.map(s => s.toLowerCase()));
        (data.skills || []).forEach(s => {
            if (!currentTechLower.includes(s.toLowerCase())) {
                const firstGroupId = document.querySelector('[data-tech-group-index="0"]')?.id.replace('tech-group-','');
                if (firstGroupId) {
                    addTag('technical', s, firstGroupId);
                    newSkills++;
                }
            }
        });

        // 2. Sync Projects
        const currentProjectsLower = (RD.projects || []).map(p => (p.title || '').toLowerCase());
        (data.projects || []).forEach(p => {
            if (!currentProjectsLower.includes((p.title || '').toLowerCase())) {
                addEntry('projects', p);
                newProjects++;
            }
        });

        // 3. Sync Certifications
        // Automatically remove "My Certifications" placeholder if present
        RD.certifications = (RD.certifications || []).filter(c => (c.name || '').toLowerCase() !== 'my certifications');
        
        const currentCertsLower = (RD.certifications || []).map(c => (c.name || '').toLowerCase());
        (data.certifications || []).forEach(c => {
            if (!currentCertsLower.includes((c.name || '').toLowerCase())) {
                addEntry('certifications', c);
                newCerts++;
            }
        });

        const total = newSkills + newProjects + newCerts;
        if (total > 0) {
            showToast(`Synced! Added ${newSkills} skills, ${newProjects} projects, ${newCerts} certs.`);
            saveResume(true); // Trigger a save since we added data
        } else {
            showToast('Portfolio already up to date!', 'info');
        }

    } catch (e) {
        console.error('Sync error:', e);
        showToast('Error connecting to server', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

function setVal(id, val) {
    const el = document.getElementById(id);
    if (el && val) el.value = val;
}

// ─────────────────────────────────────────────────────────
// COLLECT DATA FROM FORM
// ─────────────────────────────────────────────────────────
function collectData() {
    RD.full_name            = gv('f_full_name');
    RD.email                = gv('f_email');
    RD.phone                = gv('f_phone');
    RD.gender               = gv('f_gender');
    RD.address              = gv('f_address');
    RD.location             = gv('f_location');
    RD.linkedin_url         = gv('f_linkedin');
    RD.github_url           = gv('f_github');
    RD.portfolio_url        = gv('f_portfolio');
    RD.professional_summary = gv('f_summary');

    RD.education      = collectEntries('education');
    RD.experience     = collectEntries('experience');
    RD.projects       = collectEntries('projects');
    RD.certifications = collectEntries('certifications');
    RD.achievements   = collectEntries('achievements');
    // Skills collected real-time via addTag/removeTag
}

function gv(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
}

function collectEntries(type) {
    const list = document.getElementById(type + '-list');
    if (!list) return [];
    const cards = list.querySelectorAll('.entry-card');
    const results = [];
    cards.forEach(card => {
        const entry = {};
        card.querySelectorAll('[data-field]').forEach(el => {
            const field = el.dataset.field;
            if (el.type === 'checkbox') {
                entry[field] = el.checked;
            } else {
                entry[field] = el.value.trim();
            }
        });
        // Only add if at least one meaningful field is filled
        const hasContent = Object.values(entry).some(v => typeof v === 'string' && v.length > 0);
        if (hasContent) results.push(entry);
    });
    return results;
}

// ─────────────────────────────────────────────────────────
// SAVE TO SERVER
// ─────────────────────────────────────────────────────────
// Expose to global scope
window.saveResume = async function(isAuto = false) {
    // 0) Pre-save: Ensure data and preview are fully updated
    collectData();
    renderPreview(); 

    // 1) Generate PDF blob
    let pdfBlob = null;
    try {
        const el = document.getElementById('resumePreview');
        if (el) {
            // Options for html2pdf
            const opt = {
                margin:       0,
                filename:     'resume.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { 
                    scale: 2, 
                    useCORS: true, 
                    letterRendering: true,
                    scrollY: -window.scrollY // Fix alignment if page is scrolled
                },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // Temporarily strip styles and hide editor-only visual aids
            const oldShadow = el.style.boxShadow;
            const oldBorder = el.style.borderRadius;
            el.style.boxShadow = 'none';
            el.style.borderRadius = '0';
            
            const helpers = el.querySelectorAll('.page-limit-line, .page-gap-simulator');
            helpers.forEach(h => h.style.display = 'none');
            
            // Generate blob
            pdfBlob = await html2pdf().set(opt).from(el).output('blob');
            
            // Restore styles and visual aids
            el.style.boxShadow = oldShadow;
            el.style.borderRadius = oldBorder;
            helpers.forEach(h => h.style.display = '');
        }
    } catch (e) {
        console.error('PDF Generation Error:', e);
    }

    // 2) Prepare form data for upload
    const formData = new FormData();
    formData.append('resume_data', JSON.stringify(RD));
    if (pdfBlob) {
        formData.append('resume_pdf', pdfBlob, 'resume.pdf');
    }

    try {
        const res  = await fetch(HANDLER_URL + '?action=save_resume', {
            method: 'POST',
            body: formData, // using FormData instead of JSON string
        });
        const data = await res.json();
        if (data.success) {
            const link = data.pdf_url ? ` | <a href="${data.pdf_url}" target="_blank" style="color:#fbbf24;text-decoration:underline;">View PDF</a>` : '';
            showToast(`Saved successfully!${link}`, 6000);

            // Background Skill Sync
            const syncData = new FormData();
            syncData.append('action', 'sync_skills');
            syncData.append('skill_groups', JSON.stringify(RD.skills.technical));
            fetch('portfolio_handler', { method: 'POST', body: syncData });

            // Background Project Sync
            const projSyncData = new FormData();
            projSyncData.append('action', 'sync_projects');
            projSyncData.append('projects', JSON.stringify(RD.projects));
            fetch('portfolio_handler', { method: 'POST', body: projSyncData });
            
            const btn = document.getElementById('saveBtn');
            btn.classList.add('saved');
            btn.innerHTML = '<i class="fas fa-check"></i> Saved';
            setTimeout(() => {
                btn.classList.remove('saved');
                btn.innerHTML = '<i class="fas fa-save"></i> Save';
            }, 2000);
        } else {
            if (!isAuto) showToast('Save failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        if (!isAuto) showToast('Network error – could not save');
    }
}

// ─────────────────────────────────────────────────────────
// LIVE PREVIEW
// ─────────────────────────────────────────────────────────
// Expose to global scope for HTML event handlers
window.schedulePreview = function() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(renderPreview, 250);
};

function renderPreview() {
    collectData();
    const el = document.getElementById('resumePreview');
    if (!el) return;
    
    // 1. Render Template HTML
    let html = '';
    switch (RD.template_id) {
        case 'modern_creative': html = tplModern();   break;
        case 'minimal_clean':   html = tplMinimal();  break;
        default:                html = tplATS();      break;
    }
    el.innerHTML = html;

    // 2. Add Page Limit Visuals (Editor only)
    const targetHeightPx = (297 * 96) / 25.4;
    const actualHeight   = el.scrollHeight;

    if (actualHeight > targetHeightPx - 100) { 
        // Only show if we're getting close to a second page
        const limitLine = document.createElement('div');
        limitLine.className = 'page-limit-line';
        limitLine.style.top = targetHeightPx + 'px';
        el.appendChild(limitLine);
    }

    // 3. Detect Overflow
    const warning = document.getElementById('overflowWarning');
    if (warning) {
        if (actualHeight > targetHeightPx + 5) { // 5px buffer
            warning.classList.add('show');
        } else {
            warning.classList.remove('show');
        }
    }
}

// ── TEMPLATE: ATS Classic ─────────────────────────────────
function tplATS() {
    const m = '#000000';
    return `
    <div style="font-family:'Times New Roman', Times, serif; color:#000; font-size:10pt; line-height:1.5;">

      <!-- HEADER -->
      <div style="text-align:center; padding-bottom:10px; border-bottom:2.5px solid ${m}; margin-bottom:14px;">
        <h1 style="font-size:22pt; font-weight:800; letter-spacing:1px; margin:0; text-transform:uppercase; color:${m};">${esc(RD.full_name || 'Your Name')}</h1>
        <div style="font-size:9pt; color:#000; margin-top:5px; display:flex; justify-content:center; flex-wrap:wrap; gap:10px 18px;">
          ${RD.email    ? `<span>${esc(RD.email)}</span>` : ''}
          ${RD.phone    ? `<span>${esc(RD.phone)}</span>` : ''}
          ${RD.gender   ? `<span>${esc(RD.gender)}</span>` : ''}
          ${RD.location ? `<span>${esc(RD.location)}</span>` : ''}
          ${RD.linkedin_url ? `<span><a href="${ensureProtocol(RD.linkedin_url)}" target="_blank" style="color:inherit; text-decoration:underline;">LinkedIn</a></span>` : ''}
          ${RD.github_url   ? `<span><a href="${ensureProtocol(RD.github_url)}" target="_blank" style="color:inherit; text-decoration:underline;">GitHub</a></span>` : ''}
          ${RD.portfolio_url ? `<span><a href="${ensureProtocol(RD.portfolio_url)}" target="_blank" style="color:inherit; text-decoration:underline;">Portfolio</a></span>` : ''}
        </div>
        ${RD.address ? `<div style="font-size:8.5pt; color:#444; margin-top:4px;">${esc(RD.address)}</div>` : ''}
      </div>

      ${RD.professional_summary ? sectionHeader('Summary', m) + `<p style="text-align:justify; color:#000; margin-bottom:12px;">${esc(RD.professional_summary)}</p>` : ''}

      ${RD.education.length ? sectionHeader('Education', m) + RD.education.map(e => `
        <div style="margin-bottom:8px;">
          <div style="display:flex; justify-content:space-between;">
            <strong>${esc(e.degree||'')}</strong>
            <span style="color:#000; font-size:9pt;">${esc(dateRange(e.start_date,e.end_date,e.ongoing))}</span>
          </div>
          <div style="color:#000; font-style:italic;">${esc(e.institution||'')} ${e.cgpa ? `<span style="float:right;">CGPA: ${esc(e.cgpa)}</span>` : ''}</div>
        </div>`).join('') : ''}

      ${RD.experience.length ? sectionHeader('Professional Experience', m) + RD.experience.map(e => `
        <div style="margin-bottom:10px;">
          <div style="display:flex; justify-content:space-between;">
            <strong>${esc(e.title||'')}${e.company ? ' @ ' + esc(e.company) : ''}</strong>
            <span style="color:#000; font-size:9pt;">${esc(dateRange(e.start_date,e.end_date,e.ongoing))}</span>
          </div>
          ${e.location ? `<div style="color:#000; font-size:9pt;">${esc(e.location)}</div>` : ''}
          ${bulletList(e.responsibilities)}
        </div>`).join('') : ''}

      ${RD.projects.length ? sectionHeader('Projects', m) + RD.projects.map(e => `
        <div style="margin-bottom:8px; break-inside:avoid;">
          <div style="display:flex; justify-content:space-between; align-items:baseline;">
            <strong>${esc(e.title||'')} ${e.link ? `<a href="${ensureProtocol(e.link)}" target="_blank" style="color:#0066cc; font-size:8.5pt; text-decoration:underline; font-weight:normal; margin-left:8px;">[View Project]</a>` : ''}</strong>
            <span style="color:#000; font-size:9pt;">${esc(dateRange(e.start_date,e.end_date,e.ongoing))}</span>
          </div>
          ${e.technologies ? `<div style="font-size:9pt; color:#000; font-style:italic;">Tech: ${esc(e.technologies)}</div>` : ''}
          ${e.description ? `<ul style="margin:2px 0 0 16px; color:#000;">${e.description.split('\n').filter(l=>l.trim()).map(l=>`<li style="margin-bottom:1px;">${esc(l)}</li>`).join('')}</ul>` : ''}
        </div>`).join('') : ''}

      ${skillsSection(m)}

      ${RD.certifications.length ? sectionHeader('Certifications', m) + RD.certifications.map(c => `
        <div style="margin-bottom:5px; break-inside:avoid;">
          <div style="display:flex; justify-content:space-between;">
            <strong>${esc(c.name||'')}</strong>
            ${c.date ? `<span style="color:#000; font-size:9pt;">${formatDate(c.date)}</span>` : ''}
          </div>
          <div style="font-size:9pt; color:#000;">${esc(c.issuer||'')} ${c.credential_url ? ` · <a href="${ensureProtocol(c.credential_url)}" target="_blank" style="color:#0066cc; text-decoration:underline;">[View Certificate]</a>` : ''}</div>
          ${c.description ? `<div style="font-size:9pt; color:#444; margin-top:1px;">${esc(c.description)}</div>` : ''}
        </div>`).join('') : ''}

      ${RD.achievements.length ? sectionHeader('Achievements', m) + `<ul style="margin:4px 0 12px 18px; color:#000;">` + RD.achievements.map(a => `<li><strong>${esc(a.title||'')}</strong>${a.date ? ' ('+formatDate(a.date)+')' : ''} ${a.description ? '– '+esc(a.description) : ''}</li>`).join('') + '</ul>' : ''}
    </div>`;
}

// ── TEMPLATE: Modern Creative (dark sidebar) ──────────────
function tplModern() {
    const sideColor = '#1e293b';
    const accentColor = '#000000';

    return `
    <div style="font-family:'Times New Roman', Times, serif; display:flex; min-height:100%; color:#000; font-size:10pt;">
      <!-- Sidebar -->
      <div style="width:38%; background:${sideColor}; color:#f1f5f9; padding:24px 18px; flex-shrink:0;">
        <div style="text-align:center; margin-bottom:20px;">
          <div style="width:72px; height:72px; background:#334155; border-radius:50%; margin:0 auto 12px; display:flex; align-items:center; justify-content:center; font-size:28px; font-weight:800; color:${accentColor};">
            ${(RD.full_name||'?').charAt(0).toUpperCase()}
          </div>
          <h2 style="font-size:14pt; color:#f1f5f9; margin:0; font-weight:700;">${esc(RD.full_name||'Your Name')}</h2>
        </div>

        <div style="font-size:8.5pt; color:#f1f5f9; margin-bottom:18px;">
          ${RD.email    ? `<div style="margin:5px 0;">✉ ${esc(RD.email)}</div>` : ''}
          ${RD.phone    ? `<div style="margin:5px 0;">📞 ${esc(RD.phone)}</div>` : ''}
          ${RD.gender   ? `<div style="margin:5px 0;">⚤ ${esc(RD.gender)}</div>` : ''}
          ${RD.address  ? `<div style="margin:5px 0;">🏠 ${esc(RD.address)}</div>` : ''}
          ${RD.location ? `<div style="margin:5px 0;">📍 ${esc(RD.location)}</div>` : ''}
          ${RD.linkedin_url ? `<div style="margin:5px 0;">🔗 <a href="${ensureProtocol(RD.linkedin_url)}" target="_blank" style="color:inherit; text-decoration:underline;">LinkedIn</a></div>` : ''}
          ${RD.github_url   ? `<div style="margin:5px 0;">💻 <a href="${ensureProtocol(RD.github_url)}" target="_blank" style="color:inherit; text-decoration:underline;">GitHub</a></div>` : ''}
          ${RD.portfolio_url ? `<div style="margin:5px 0;">🌐 <a href="${ensureProtocol(RD.portfolio_url)}" target="_blank" style="color:inherit; text-decoration:underline;">Portfolio</a></div>` : ''}
        </div>

        ${RD.skills.technical.length ? `
          <div style="color:${accentColor}; font-weight:700; font-size:9pt; text-transform:uppercase; border-bottom:1px solid #334155; padding-bottom:4px; margin-bottom:8px;">Technical Skills</div>
          <div style="margin-bottom:14px;">
            ${RD.skills.technical.map(g => `
              ${g.category ? `<div style="font-size:8pt; font-weight:700; color:${accentColor}; margin-top:6px; margin-bottom:4px; text-transform:uppercase;">${esc(g.category)}</div>` : ''}
              <div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:8px;">
                ${g.items.map(s => `<span style="background:#334155; padding:2px 8px; border-radius:8px; font-size:7.5pt; color:#e2e8f0;">${esc(s)}</span>`).join('')}
              </div>
            `).join('')}
          </div>` : ''}

        ${RD.certifications.length ? `
          <div style="color:${accentColor}; font-weight:700; font-size:9pt; text-transform:uppercase; border-bottom:1px solid #334155; padding-bottom:4px; margin-bottom:8px;">Certifications</div>
          <div style="font-size:8.5pt; color:#f1f5f9;">
            ${RD.certifications.map(c=>`
              <div style="margin-bottom:6px;">
                <div style="font-weight:600;">• ${esc(c.name||'')}</div>
                ${c.issuer ? `<div style="opacity:0.9; font-size:8pt;">${esc(c.issuer)}</div>` : ''}
                ${c.description ? `<div style="font-size:7.5pt; opacity:0.8; margin-top:2px;">${esc(c.description)}</div>` : ''}
                ${c.credential_url ? `<a href="${ensureProtocol(c.credential_url)}" target="_blank" style="color:#fbbf24; text-decoration:underline; font-size:7.5pt;">[View Certificate]</a>` : ''}
              </div>`).join('')}
          </div>` : ''}
      </div>

      <!-- Main -->
      <div style="flex:1; padding:24px 20px;">
        ${RD.professional_summary ? `
          <div style="border-bottom:2px solid #e2e8f0; padding-bottom:10px; margin-bottom:14px; color:#000; font-style:italic;">${esc(RD.professional_summary)}</div>` : ''}

        ${RD.education.length ? modernSection('Education', accentColor) + RD.education.map(e=>`
          <div style="margin-bottom:8px;">
            <strong>${esc(e.degree||'')}</strong> – ${esc(e.institution||'')}
            <span style="float:right; color:#000; font-size:9pt;">${esc(dateRange(e.start_date,e.end_date,e.ongoing))}</span>
            ${e.cgpa ? `<div style="color:#000; font-size:9pt;">CGPA: ${esc(e.cgpa)}</div>` : ''}
          </div>`).join('') : ''}

        ${RD.experience.length ? modernSection('Experience', accentColor) + RD.experience.map(e=>`
          <div style="margin-bottom:10px;">
            <strong>${esc(e.title||'')}</strong>${e.company ? ` <span style="color:#000;">@ ${esc(e.company)}</span>` : ''}
            <span style="float:right; color:#000; font-size:9pt;">${esc(dateRange(e.start_date,e.end_date,e.ongoing))}</span>
            ${bulletList(e.responsibilities, '#000')}
          </div>`).join('') : ''}

        ${RD.projects.length ? modernSection('Projects', accentColor) + RD.projects.map(e=>`
          <div style="margin-bottom:8px; break-inside:avoid;">
            <strong>${esc(e.title||'')}</strong>
            ${e.link ? `<a href="${ensureProtocol(e.link)}" target="_blank" style="color:#0066cc; font-size:8.5pt; text-decoration:underline; font-weight:normal; margin-left:6px;">[Link]</a>` : ''}
            ${e.technologies ? `<span style="font-size:8.5pt; color:#000; margin-left:6px;">| ${esc(e.technologies)}</span>` : ''}
            ${e.description ? `<ul style="margin:2px 0 0 14px; color:#000; font-size:9.5pt;">${e.description.split('\n').filter(l=>l.trim()).map(l=>`<li style="margin-bottom:1px;">${esc(l)}</li>`).join('')}</ul>` : ''}
            <span style="float:right; color:#000; font-size:9pt;">${esc(dateRange(e.start_date,e.end_date,e.ongoing))}</span>
          </div>`).join('') : ''}

        ${RD.achievements.length ? modernSection('Achievements', accentColor) + `<ul style="margin:4px 0 10px 16px; color:#000;">` + RD.achievements.map(a=>`<li>${esc(a.title||'')}${a.description ? ' – '+esc(a.description) : ''}</li>`).join('') + '</ul>' : ''}
      </div>
    </div>`;
}

// ── TEMPLATE: Minimal Clean ───────────────────────────────
function tplMinimal() {
    return `
    <div style="font-family:'Times New Roman', Times, serif; color:#000; font-size:10pt; line-height:1.6;">
      <h1 style="font-size:26pt; font-weight:300; letter-spacing:-0.5px; margin-bottom:4px; color:#000;">${esc(RD.full_name||'Your Name')}</h1>
      <div style="font-size:9pt; color:#000; margin-bottom:16px; display:flex; flex-wrap:wrap; gap:4px 14px;">
        ${[
            RD.email ? `<span>${esc(RD.email)}</span>` : null,
            RD.phone ? `<span>${esc(RD.phone)}</span>` : null,
            RD.gender ? `<span>${esc(RD.gender)}</span>` : null,
            RD.location ? `<span>${esc(RD.location)}</span>` : null,
            RD.linkedin_url ? `<a href="${ensureProtocol(RD.linkedin_url)}" target="_blank" style="color:inherit; text-decoration:underline;">LinkedIn</a>` : null,
            RD.github_url ? `<a href="${ensureProtocol(RD.github_url)}" target="_blank" style="color:inherit; text-decoration:underline;">GitHub</a>` : null,
            RD.portfolio_url ? `<a href="${ensureProtocol(RD.portfolio_url)}" target="_blank" style="color:inherit; text-decoration:underline;">Portfolio</a>` : null
        ].filter(Boolean).join('<span style="color:#000;">|</span>')}
      </div>
      <hr style="border:none; border-top:1px solid #000; margin-bottom:16px;">

      ${RD.professional_summary ? `<p style="color:#000; margin-bottom:16px; font-size:9.5pt;">${esc(RD.professional_summary)}</p><hr style="border:none; border-top:1px solid #000; margin-bottom:16px;">` : ''}

      ${RD.education.length ? minSection('EDUCATION') + RD.education.map(e=>`
        <div style="margin-bottom:8px; display:flex; justify-content:space-between; align-items:baseline; break-inside:avoid;">
          <div><strong style="color:#000;">${esc(e.degree||'')}</strong> <span style="color:#000;">• ${esc(e.institution||'')}</span>${e.cgpa?`<span style="color:#000; font-size:9pt;"> • ${esc(e.cgpa)}</span>`:''}</div>
          <div style="color:#000; font-size:9pt; white-space:nowrap; margin-left:10px;">${esc(dateRange(e.start_date,e.end_date,e.ongoing))}</div>
        </div>`).join('') : ''}

      ${RD.experience.length ? minSection('EXPERIENCE') + RD.experience.map(e=>`
        <div style="margin-bottom:10px; break-inside:avoid;">
          <div style="display:flex; justify-content:space-between;"><strong style="color:#000;">${esc(e.title||'')} ${e.company?`<span style="font-weight:400; color:#000;">@ ${esc(e.company)}</span>`:''}</strong><span style="color:#000; font-size:9pt;">${esc(dateRange(e.start_date,e.end_date,e.ongoing))}</span></div>
          ${bulletList(e.responsibilities, '#000')}
        </div>`).join('') : ''}

      ${RD.projects.length ? minSection('PROJECTS') + RD.projects.map(e=>`
        <div style="margin-bottom:6px; break-inside:avoid;">
          <div style="display:flex; justify-content:space-between; align-items:baseline;">
            <strong style="color:#000;">${esc(e.title||'')} ${e.link ? `<a href="${ensureProtocol(e.link)}" target="_blank" style="color:#0066cc; font-size:8.5pt; text-decoration:underline; font-weight:normal; margin-left:6px;">[View]</a>` : ''}</strong>
            ${e.technologies?`<span style="color:#000; font-size:9pt; margin-left:10px;">${esc(e.technologies)}</span>`:''}
          </div>
          ${e.description ? `<ul style="margin:2px 0 0 14px; color:#000; font-size:9.5pt;">${e.description.split('\n').filter(l=>l.trim()).map(l=>`<li style="margin-bottom:1px;">${esc(l)}</li>`).join('')}</ul>` : ''}
        </div>`).join('') : ''}

      ${(RD.skills.technical.length||RD.skills.soft.length||RD.skills.languages.length) ? minSection('SKILLS') + `
        ${RD.skills.technical.map(g => `
            <div style="margin-bottom:3px;"><span style="font-weight:600;">${g.category ? esc(g.category) + ': ' : 'Technical: '}</span>${g.items.map(s=>esc(s)).join(', ')}</div>
        `).join('')}
        ${RD.skills.soft.length ? `<div style="margin-bottom:3px;"><span style="font-weight:600;">Soft Skills: </span>${RD.skills.soft.map(s=>esc(s)).join(', ')}</div>` : ''}
        ${RD.skills.languages.length ? `<div style="margin-bottom:6px;"><span style="font-weight:600;">Languages: </span>${RD.skills.languages.map(s=>esc(s)).join(', ')}</div>` : ''}` : ''}

      ${RD.certifications.length ? minSection('CERTIFICATIONS') + RD.certifications.map(c=>`
        <div style="margin-bottom:6px; break-inside:avoid;">
          <div style="display:flex; justify-content:space-between; align-items:baseline;">
            <strong>${esc(c.name||'')}</strong>
            ${c.date?`<span style="color:#666; font-size:9pt;">(${formatDate(c.date)})</span>`:''}
          </div>
          <div style="font-size:9pt; color:#000;">${esc(c.issuer||'')} ${c.credential_url ? ` · <a href="${ensureProtocol(c.credential_url)}" target="_blank" style="color:#0066cc; text-decoration:underline;">[View]</a>` : ''}</div>
          ${c.description ? `<div style="font-size:9pt; color:#444; margin-top:1px;">${esc(c.description)}</div>` : ''}
        </div>`).join('') : ''}

      ${RD.achievements.length ? minSection('ACHIEVEMENTS') + RD.achievements.map(a=>`<div style="margin-bottom:4px;">• <strong>${esc(a.title||'')}</strong>${a.description?' – '+esc(a.description):''}</div>`).join('') : ''}
    </div>`;
}

// ─────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────
function esc(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function sectionHeader(title, color) {
    return `<h2 style="font-size:10.5pt; font-weight:800; color:${color}; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1.5px solid ${color}; padding-bottom:3px; margin:12px 0 6px; break-after:avoid;">${title}</h2>`;
}

function modernSection(title, accentColor) {
    return `<h3 style="font-size:10pt; font-weight:700; color:#1e293b; text-transform:uppercase; letter-spacing:0.5px; border-left:3px solid ${accentColor}; padding-left:8px; margin:12px 0 8px; break-after:avoid;">${title}</h3>`;
}

function minSection(title) {
    return `<div style="font-size:8pt; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:1px; margin:12px 0 6px; border-bottom:1px solid #f3f4f6; padding-bottom:4px; break-after:avoid;">${title}</div>`;
}

function skillsSection(color) {
    const hasTech = RD.skills.technical.some(g => g.items.length > 0);
    const hasSoft = RD.skills.soft.length > 0;
    const hasLang = RD.skills.languages.length > 0;
    if (!hasTech && !hasSoft && !hasLang) return '';

    let html = sectionHeader('Technical Skills', color);
    html += `<div style="margin-bottom:10px; font-size:9.5pt; color:#000;">`;
    
    if (hasTech) {
        RD.skills.technical.forEach(g => {
            if (g.items.length > 0) {
                html += `<div style="margin-bottom:4px;"><strong>${g.category ? esc(g.category) : 'Technical'}:</strong> ${g.items.map(s=>esc(s)).join(' · ')}</div>`;
            }
        });
    }
    
    if (hasSoft) {
        html += `<div style="margin-bottom:4px;"><strong>Soft Skills:</strong> ${RD.skills.soft.map(s=>esc(s)).join(' · ')}</div>`;
    }
    
    if (hasLang) {
        html += `<div><strong>Languages:</strong> ${RD.skills.languages.map(s=>esc(s)).join(' · ')}</div>`;
    }
    
    html += `</div>`;
    return html;
}

function bulletList(responsibilities, color = '#374151') {
    if (!responsibilities || !responsibilities.trim()) return '';
    const lines = responsibilities.split('\n').map(l=>l.trim()).filter(Boolean);
    if (!lines.length) return '';
    return `<ul style="margin:4px 0 0 16px; color:${color}; font-size:9.5pt;">` + lines.map(l=>`<li>${esc(l.replace(/^[•\-]\s*/,''))}</li>`).join('') + '</ul>';
}

function dateRange(start, end, ongoing) {
    if (!start) return '';
    const fmt = d => { if (!d) return ''; const [y,m] = d.split('-'); return new Date(y, m-1).toLocaleDateString('en-US', {month:'short', year:'numeric'}); };
    return fmt(start) + (ongoing ? ' – Present' : (end ? ' – ' + fmt(end) : ''));
}

function ensureProtocol(url) {
    if (!url) return '';
    if (!/^https?:\/\//i.test(url)) {
        return 'https://' + url;
    }
    return url;
}

function formatDate(d) {
    if (!d) return '';
    const [y,m] = d.split('-');
    if (!m) return y;
    return new Date(y, m-1).toLocaleDateString('en-US', {month:'short', year:'numeric'});
}

// ─────────────────────────────────────────────────────────
// SECTION TOGGLE
// ─────────────────────────────────────────────────────────
window.toggleSection = function(id) {
    document.getElementById(id).classList.toggle('open');
};

// ─────────────────────────────────────────────────────────
// TEMPLATE SWITCHER
// ─────────────────────────────────────────────────────────
window.switchTemplate = function(btn) {
    document.querySelectorAll('.tpl-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    RD.template_id = btn.dataset.tpl;
    renderPreview();
};

// ─────────────────────────────────────────────────────────
// DYNAMIC ENTRIES
// ─────────────────────────────────────────────────────────
const entryTemplates = {
    education: (id, d={}) => `
      <div class="entry-card" id="entry-${id}">
        <div class="entry-card-title">
          <span>Education</span>
          <div class="entry-actions">
            <button class="entry-move" onclick="moveEntry('${id}', 'up')" title="Move Up"><i class="fas fa-chevron-up"></i></button>
            <button class="entry-move" onclick="moveEntry('${id}', 'down')" title="Move Down"><i class="fas fa-chevron-down"></i></button>
            <button class="entry-remove" onclick="removeEntry('${id}')">✕ Remove</button>
          </div>
        </div>
        <div class="field-row">
          <div class="field-group"><label>Degree / Program</label><input type="text" data-field="degree" value="${esc(d.degree||'')}" placeholder="B.Tech Computer Science" oninput="schedulePreview()"></div>
          <div class="field-group"><label>Institution</label><input type="text" data-field="institution" value="${esc(d.institution||'')}" placeholder="GM University" oninput="schedulePreview()"></div>
        </div>
        <div class="field-row">
          <div class="field-group"><label>Start Date</label><input type="month" data-field="start_date" value="${esc(d.start_date||'')}" oninput="schedulePreview()"></div>
          <div class="field-group"><label>End Date</label><input type="month" data-field="end_date" value="${esc(d.end_date||'')}" id="edu_end_${id}" ${d.ongoing?'disabled':''} oninput="schedulePreview()"></div>
        </div>
        <label class="checkbox-label"><input type="checkbox" data-field="ongoing" ${d.ongoing?'checked':''} onchange="toggleEndDate('edu_end_${id}',this.checked); schedulePreview()"> Currently Pursuing</label>
        <div class="field-row" style="margin-top:10px;">
          <div class="field-group"><label>CGPA / %</label><input type="text" data-field="cgpa" value="${esc(d.cgpa||'')}" placeholder="8.5 / 10" oninput="schedulePreview()"></div>
          <div class="field-group"><label>Location</label><input type="text" data-field="location" value="${esc(d.location||'')}" placeholder="Bangalore" oninput="schedulePreview()"></div>
        </div>
      </div>`,
    experience: (id, d={}) => `
      <div class="entry-card" id="entry-${id}">
        <div class="entry-card-title">
          <span>Experience</span>
          <div class="entry-actions">
            <button class="entry-move" onclick="moveEntry('${id}', 'up')" title="Move Up"><i class="fas fa-chevron-up"></i></button>
            <button class="entry-move" onclick="moveEntry('${id}', 'down')" title="Move Down"><i class="fas fa-chevron-down"></i></button>
            <button class="entry-remove" onclick="removeEntry('${id}')">✕ Remove</button>
          </div>
        </div>
        <div class="field-row">
          <div class="field-group"><label>Job Title</label><input type="text" data-field="title" value="${esc(d.title||'')}" placeholder="Software Engineer Intern" oninput="schedulePreview()"></div>
          <div class="field-group"><label>Company</label><input type="text" data-field="company" value="${esc(d.company||'')}" placeholder="Tech Corp" oninput="schedulePreview()"></div>
        </div>
        <div class="field-row">
          <div class="field-group"><label>Start Date</label><input type="month" data-field="start_date" value="${esc(d.start_date||'')}" oninput="schedulePreview()"></div>
          <div class="field-group"><label>End Date</label><input type="month" data-field="end_date" value="${esc(d.end_date||'')}" id="exp_end_${id}" ${d.ongoing?'disabled':''} oninput="schedulePreview()"></div>
        </div>
        <label class="checkbox-label"><input type="checkbox" data-field="ongoing" ${d.ongoing?'checked':''} onchange="toggleEndDate('exp_end_${id}',this.checked); schedulePreview()"> Currently Working Here</label>
        <div class="field-group" style="margin-top:10px;"><label>Location</label><input type="text" data-field="location" value="${esc(d.location||'')}" placeholder="Remote / Bangalore" oninput="schedulePreview()"></div>
        <div class="field-group"><label>Responsibilities (one per line)</label><textarea data-field="responsibilities" placeholder="• Developed features using React..." oninput="schedulePreview()">${esc(d.responsibilities||'')}</textarea></div>
      </div>`,
    projects: (id, d={}) => `
      <div class="entry-card" id="entry-${id}">
        <div class="entry-card-title">
          <span>Project</span>
          <div class="entry-actions">
            <button class="entry-move" onclick="moveEntry('${id}', 'up')" title="Move Up"><i class="fas fa-chevron-up"></i></button>
            <button class="entry-move" onclick="moveEntry('${id}', 'down')" title="Move Down"><i class="fas fa-chevron-down"></i></button>
            <button class="entry-remove" onclick="removeEntry('${id}')">✕ Remove</button>
          </div>
        </div>
        <div class="field-group"><label>Title</label><input type="text" data-field="title" value="${esc(d.title||'')}" placeholder="E-Commerce Platform" oninput="schedulePreview()"></div>
        <div class="field-group"><label>Technologies Used</label><input type="text" data-field="technologies" value="${esc(d.technologies||(Array.isArray(d.technologies)?d.technologies.join(', '):''))}" placeholder="React, Node.js, MySQL" oninput="schedulePreview()"></div>
        <div class="field-group"><label>Description</label><textarea data-field="description" placeholder="Brief description..." oninput="schedulePreview()">${esc(d.description||'')}</textarea></div>
        <div class="field-row">
          <div class="field-group"><label>Start Date</label><input type="month" data-field="start_date" value="${esc(d.start_date||'')}" oninput="schedulePreview()"></div>
          <div class="field-group"><label>End Date</label><input type="month" data-field="end_date" value="${esc(d.end_date||'')}" id="proj_end_${id}" ${d.ongoing?'disabled':''} oninput="schedulePreview()"></div>
        </div>
        <label class="checkbox-label"><input type="checkbox" data-field="ongoing" ${d.ongoing?'checked':''} onchange="toggleEndDate('proj_end_${id}',this.checked); schedulePreview()"> Ongoing</label>
        <div class="field-group" style="margin-top:10px;"><label>Project Link</label><input type="url" data-field="link" value="${esc(d.link||'')}" placeholder="https://github.com/..." oninput="schedulePreview()"></div>
      </div>`,
    certifications: (id, d={}) => `
      <div class="entry-card" id="entry-${id}">
        <div class="entry-card-title">
          <span>Certification</span>
          <div class="entry-actions">
            <button class="entry-move" onclick="moveEntry('${id}', 'up')" title="Move Up"><i class="fas fa-chevron-up"></i></button>
            <button class="entry-move" onclick="moveEntry('${id}', 'down')" title="Move Down"><i class="fas fa-chevron-down"></i></button>
            <button class="entry-remove" onclick="removeEntry('${id}')">✕ Remove</button>
          </div>
        </div>
        <div class="field-row">
          <div class="field-group"><label>Name</label><input type="text" data-field="name" value="${esc(d.name||'')}" placeholder="AWS Solutions Architect" oninput="schedulePreview()"></div>
          <div class="field-group"><label>Issuer</label><input type="text" data-field="issuer" value="${esc(d.issuer||'')}" placeholder="Amazon Web Services" oninput="schedulePreview()"></div>
        </div>
        <div class="field-row">
          <div class="field-group"><label>Date</label><input type="month" data-field="date" value="${esc(d.date||d.credential_url&&''||'')}" oninput="schedulePreview()"></div>
          <div class="field-group"><label>Credential URL</label><input type="url" data-field="credential_url" value="${esc(d.credential_url||'')}" placeholder="https://..." oninput="schedulePreview()"></div>
        </div>
        <div class="field-group"><label>Short Description</label><textarea data-field="description" placeholder="Brief context or key skills..." oninput="schedulePreview()">${esc(d.description||'')}</textarea></div>
      </div>`,
    achievements: (id, d={}) => `
      <div class="entry-card" id="entry-${id}">
        <div class="entry-card-title">
          <span>Achievement</span>
          <div class="entry-actions">
            <button class="entry-move" onclick="moveEntry('${id}', 'up')" title="Move Up"><i class="fas fa-chevron-up"></i></button>
            <button class="entry-move" onclick="moveEntry('${id}', 'down')" title="Move Down"><i class="fas fa-chevron-down"></i></button>
            <button class="entry-remove" onclick="removeEntry('${id}')">✕ Remove</button>
          </div>
        </div>
        <div class="field-row">
          <div class="field-group"><label>Title</label><input type="text" data-field="title" value="${esc(d.title||'')}" placeholder="1st Prize – Hackathon" oninput="schedulePreview()"></div>
          <div class="field-group"><label>Date</label><input type="month" data-field="date" value="${esc(d.date||'')}" oninput="schedulePreview()"></div>
        </div>
        <div class="field-group"><label>Description</label><textarea data-field="description" placeholder="Brief context..." oninput="schedulePreview()">${esc(d.description||'')}</textarea></div>
      </div>`,
};

window.addEntry = function(type, data={}) {
    const id = type + '_' + (entryCounters[type]++);
    const list = document.getElementById(type + '-list');
    if (!list || !entryTemplates[type]) return;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = entryTemplates[type](id, data);
    list.appendChild(wrapper.firstElementChild);
    schedulePreview();
};

window.moveEntry = function(id, direction) {
    const el = document.getElementById('entry-' + id);
    if (!el) return;
    
    if (direction === 'up') {
        const prev = el.previousElementSibling;
        if (prev && prev.classList.contains('entry-card')) {
            el.parentNode.insertBefore(el, prev);
        }
    } else {
        const next = el.nextElementSibling;
        if (next && next.classList.contains('entry-card')) {
            el.parentNode.insertBefore(next, el);
        }
    }
    schedulePreview();
};

window.removeEntry = function(id) {
    const el = document.getElementById('entry-' + id);
    if (el) { el.remove(); schedulePreview(); }
};

window.toggleEndDate = function(fieldId, isOngoing) {
    const el = document.getElementById(fieldId);
    if (el) { el.disabled = isOngoing; if (isOngoing) el.value = ''; }
};

// ─────────────────────────────────────────────────────────
// SKILL GROUPS (Categorized Technical Skills)
// ─────────────────────────────────────────────────────────
let techGroupCounter = 0;

// ─────────────────────────────────────────────────────────
// SKILL SYNC
// ─────────────────────────────────────────────────────────
window.importSkillsFromProfile = async function() {
    showToast('Fetching skills from your profile...');
    try {
        const res = await fetch('portfolio_handler', {
            method: 'POST',
            body: new URLSearchParams({ action: 'list' })
        });
        const data = await res.json();
        if (data.success && data.items) {
            const portfolioSkills = data.items.filter(i => i.category === 'Skill');
            if (portfolioSkills.length === 0) {
                showToast('No skills found in your profile.');
                return;
            }

            // Group by category (sub_title)
            const groups = {};
            portfolioSkills.forEach(s => {
                const cat = s.sub_title || 'Technical Skills';
                if (!groups[cat]) groups[cat] = [];
                groups[cat].push(s.title);
            });

            // Update UI & State
            const techList = document.getElementById('tech-groups-list');
            if (techList) techList.innerHTML = '';
            RD.skills.technical = [];
            
            for (const cat in groups) {
                addSkillGroup(cat, groups[cat]);
            }
            showToast('✅ Skills imported successfully!');
            schedulePreview();
        }
    } catch (e) {
        showToast('❌ Failed to fetch portfolio skills');
    }
};

window.addSkillGroup = function(category = '', items = []) {
    const id = techGroupCounter++;
    const list = document.getElementById('tech-groups-list');
    if (!list) return;

    // Update state
    const groupIndex = RD.skills.technical.length;
    RD.skills.technical.push({ category, items: [] });

    const wrapper = document.createElement('div');
    wrapper.className = 'tech-group-row';
    wrapper.id = 'tech-group-' + id;
    wrapper.dataset.techGroupIndex = groupIndex;
    wrapper.innerHTML = `
        <div class="tech-group-header">
            <input type="text" class="tech-group-cat-input" placeholder="Category (e.g. Languages)" value="${esc(category)}" oninput="updateTechCategory(${groupIndex}, this.value)">
            <button class="entry-remove" onclick="removeSkillGroup(${id}, ${groupIndex})">✕</button>
        </div>
        <div class="tags-wrap" onclick="this.querySelector('.tag-input-el').focus()">
            <div class="tags-container"></div>
            <input class="tag-input-el" placeholder="Type skill + Enter" onkeydown="handleTagKey(event, 'technical', ${id})">
        </div>
    `;
    list.appendChild(wrapper);

    // Add existing items as tags
    items.forEach(val => addTag('technical', val, id));
};

window.updateTechCategory = function(index, value) {
    if (RD.skills.technical[index]) {
        RD.skills.technical[index].category = value;
        schedulePreview();
    }
};

window.removeSkillGroup = function(id, index) {
    const el = document.getElementById('tech-group-' + id);
    if (!el) return;
    el.remove();
    RD.skills.technical.splice(index, 1);
    
    // Re-index remaining groups in DOM
    document.querySelectorAll('.tech-group-row').forEach((row, i) => {
        row.dataset.techGroupIndex = i;
        const catInput = row.querySelector('.tech-group-cat-input');
        if (catInput) {
            catInput.setAttribute('oninput', `updateTechCategory(${i}, this.value)`);
        }
    });
    schedulePreview();
};

window.handleTagKey = function(e, type, groupId = null) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = e.target.value.replace(',','').trim();
        if (val) { 
            addTag(type, val, groupId); 
            e.target.value = ''; 
        }
    }
};

function addTag(type, value, groupId = null) {
    let containerSelector = '#tags-' + type;
    let targetItems = null;

    if (type === 'technical' && groupId !== null) {
        const groupEl = document.getElementById('tech-group-' + groupId);
        const index = groupEl ? parseInt(groupEl.dataset.techGroupIndex) : null;
        if (index !== null && RD.skills.technical[index]) {
            if (RD.skills.technical[index].items.includes(value)) return;
            RD.skills.technical[index].items.push(value);
            containerSelector = '#tech-group-' + groupId + ' .tags-container';
            targetItems = RD.skills.technical[index].items;
        }
    } else {
        if (!value || RD.skills[type].includes(value)) return;
        RD.skills[type].push(value);
        targetItems = RD.skills[type];
    }

    const container = document.querySelector(containerSelector);
    if (!container) return;

    const span = document.createElement('span');
    span.className = 'tag';
    span.innerHTML = `${esc(value)} <span class="tag-x">✕</span>`;
    
    // Find the tag-x and set its click handler
    span.querySelector('.tag-x').onclick = function() {
        if (type === 'technical' && groupId !== null) {
            const groupEl = document.getElementById('tech-group-' + groupId);
            const index = groupEl ? parseInt(groupEl.dataset.techGroupIndex) : null;
            if (index !== null && RD.skills.technical[index]) {
                RD.skills.technical[index].items = RD.skills.technical[index].items.filter(s => s !== value);
            }
        } else {
            RD.skills[type] = RD.skills[type].filter(s => s !== value);
        }
        span.remove();
        schedulePreview();
    };

    if (type === 'technical' && groupId !== null) {
        container.appendChild(span);
    } else {
        container.insertBefore(span, container.querySelector('.tag-input-el'));
    }
    schedulePreview();
}

function removeTag(type, value, el) {
    // This is now handled within addTag for new elements, 
    // but we can keep it for any legacy attachments if needed.
    RD.skills[type] = RD.skills[type].filter(s => s !== value);
    el.parentElement.remove();
    schedulePreview();
}
window.removeTag = removeTag;

// ─────────────────────────────────────────────────────────
// PDF PRINT
// ─────────────────────────────────────────────────────────
window.printResume = function() {
    const el = document.getElementById('resumePreview');
    if (!el) return;
    
    // Create a clone to strip editor-only elements
    const clone = el.cloneNode(true);
    clone.querySelectorAll('.page-limit-line, .page-gap-simulator').forEach(h => h.remove());
    
    const previewHTML = clone.innerHTML;
    const printWin = window.open('', '_blank', 'width=900,height=700');
    const scriptTagEnd = '<' + '/script>';
    printWin.document.write(`<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Times New Roman', Times, serif; background:white; }
  @page { size: A4; margin: 12mm 16mm 10mm; }
  @media print { 
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .resume-paper { padding: 0 !important; box-shadow: none !important; width: 100% !important; }
  }
</style></head>
<body><div style="font-family:'Times New Roman', Times, serif;">${previewHTML}</div>
<script>window.onload = function() { setTimeout(function() { window.print(); }, 400); }${scriptTagEnd}
</body></html>`);
    printWin.document.close();
};

// ── ONBOARDING & EXTERNAL UPLOAD ────────────────────────
window.closeOnboarding = function() {
    const modal = document.getElementById('onboardingModal');
    if (modal) modal.style.display = 'none';
    localStorage.setItem('resume_onboarding_dismissed', 'true');
};

window.uploadExistingPdf = async function(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    if (file.type !== 'application/pdf') {
        showToast('Please upload a valid PDF file');
        return;
    }

    const formData = new FormData();
    formData.append('resume_pdf', file);
    formData.append('action', 'upload_external_pdf');

    showToast('Uploading your resume...');
    
    try {
        const res = await fetch(HANDLER_URL, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast('✅ Resume uploaded successfully!');
            closeOnboarding();
            // Optionally reload or show success state
        } else {
            showToast('❌ Upload failed: ' + (data.error || 'Server error'));
        }
    } catch (e) {
        showToast('❌ Network error during upload');
    }
};

// ─────────────────────────────────────────────────────────
// TOAST
// ─────────────────────────────────────────────────────────
function showToast(msg, duration = 3000) {
    const t = document.getElementById('saveToast');
    const m = document.getElementById('toastMsg');
    if (!t) return;
    m.innerHTML = msg; // Changed to innerHTML to allow the link
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), duration);
}
