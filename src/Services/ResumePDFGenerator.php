<?php
/**
 * Resume PDF Generator Service
 * Generates professional PDF resumes from resume data
 */

class ResumePDFGenerator {
    
    /**
     * Generate PDF from resume data
     * Returns HTML that can be converted to PDF using browser print or external library
     */
    public static function generateHTML($resumeData) {
        $template = $resumeData['template_id'] ?? 'professional_ats';
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Resume - <?php echo htmlspecialchars($resumeData['full_name']); ?></title>
            <style>
                /* A4 Page Setup */
                @page { size: A4; margin: 0; }
                @media print {
                    body { width: 210mm; height: 297mm; margin: 0; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                }
                
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Times New Roman', Times, serif; font-size: 10pt; line-height: 1.4; color: #333; }
                
                .resume-container { width: 210mm; min-height: 297mm; height: auto; padding: 12mm 15mm 10mm; margin: 0 auto; background: white; }
                
                /* Template 1: Professional ATS (Standard) */
                .tm-ats .header { text-align: center; border-bottom: 2px solid #800000; padding-bottom: 8px; margin-bottom: 15px; }
                .tm-ats .header h1 { font-size: 24pt; color: #1a1a1a; text-transform: uppercase; }
                .tm-ats .section-title { font-size: 11pt; font-weight: bold; color: #800000; border-bottom: 1.5px solid #ccc; margin-top: 12px; margin-bottom: 8px; text-transform: uppercase; border-bottom: 1px solid #ccc; break-after: avoid; }
                
                /* Template 2: Modern Creative */
                .tm-modern { padding: 0; display: flex; }
                .sidebar { width: 75mm; background: #2c3e50; color: white; padding: 18px; min-height: 297mm; }
                .main-content { width: 135mm; padding: 25px 18px; background: white; }
                .tm-modern .header { margin-bottom: 20px; }
                .tm-modern .header h1 { font-size: 20pt; color: #800000; margin-bottom: 5px; }
                .tm-modern .section-title { font-size: 12pt; font-weight: 700; color: #34495e; border-left: 4px solid #800000; padding-left: 10px; margin: 15px 0 8px; text-transform: uppercase; break-after: avoid; }
                .sidebar .section-title { color: #e9c66f; border-left: none; padding-left: 0; border-bottom: 1px solid #555; padding-bottom: 4px; font-size: 10pt; break-after: avoid; }
                .sidebar-item { margin-bottom: 12px; font-size: 9pt; }
                
                /* Template 3: Minimal Clean */
                .tm-minimal .header { text-align: left; margin-bottom: 25px; }
                .tm-minimal .header h1 { font-size: 26pt; font-weight: 300; letter-spacing: 1px; color: #333; }
                .tm-minimal .section-title { font-size: 10.5pt; font-weight: 600; color: #666; letter-spacing: 2px; text-transform: uppercase; margin: 18px 0 8px; break-after: avoid; }
                .minimal-line { height: 1px; background: #eee; width: 100%; margin: 4px 0 12px; }
                
                /* Common Styles */
                .contact-row { display: flex; justify-content: center; gap: 10px; font-size: 9pt; color: #555; margin-top: 5px; }
                .tm-minimal .contact-row { justify-content: flex-start; }
                .sidebar .contact-row { flex-direction: column; gap: 5px; }
                .entry { margin-bottom: 10px; break-inside: avoid; }
                .entry-header { display: flex; justify-content: space-between; font-weight: 600; align-items: baseline; }
                .entry-subtitle { font-style: italic; color: #666; font-size: 9.5pt; }
                .entry-date { font-weight: normal; color: #555; }
                ul { margin-left: 15px; margin-top: 4px; }
                li { margin-bottom: 2px; font-size: 9.5pt; }
                .pill { display: inline-block; background: #f0f0f0; padding: 2px 8px; border-radius: 4px; margin: 1px; font-size: 8pt; border: 1px solid #ddd; }
                .sidebar .pill { background: #3e5871; border-color: #555; color: white; }
                a { color: #0066cc; text-decoration: underline; }
            </style>
        </head>
        <body class="<?php echo $template === 'modern_creative' ? '' : 'resume-container'; ?>">
            <?php if ($template === 'professional_ats'): ?>
                <!-- ATS Template -->
                <div class="tm-ats">
                    <div class="header">
                        <h1><?php echo htmlspecialchars($resumeData['full_name']); ?></h1>
                        <div class="contact-row">
                            <span><?php echo htmlspecialchars($resumeData['email']); ?></span>
                            <?php if (!empty($resumeData['phone'])): ?>| <span><?php echo htmlspecialchars($resumeData['phone']); ?></span><?php endif; ?>
                            <?php if (!empty($resumeData['location'])): ?>| <span><?php echo htmlspecialchars($resumeData['location']); ?></span><?php endif; ?>
                        </div>
                        <div class="contact-row" style="margin-top: 5px;">
                            <?php if (!empty($resumeData['linkedin_url'])): ?><a href="<?php echo self::ensureProtocol($resumeData['linkedin_url']); ?>" target="_blank" style="color: #0066cc; text-decoration: underline; margin: 0 5px;">LinkedIn</a><?php endif; ?>
                            <?php if (!empty($resumeData['github_url'])): ?><a href="<?php echo self::ensureProtocol($resumeData['github_url']); ?>" target="_blank" style="color: #0066cc; text-decoration: underline; margin: 0 5px;">GitHub</a><?php endif; ?>
                            <?php if (!empty($resumeData['portfolio_url'])): ?><a href="<?php echo self::ensureProtocol($resumeData['portfolio_url']); ?>" target="_blank" style="color: #0066cc; text-decoration: underline; margin: 0 5px;">Portfolio</a><?php endif; ?>
                        </div>
                    </div>
                    <?php renderStandardSections($resumeData); ?>
                </div>

            <?php elseif ($template === 'modern_creative'): ?>
                <!-- Modern Template -->
                <div class="tm-modern">
                    <div class="sidebar">
                        <div style="width: 80px; height: 80px; background: #3e5871; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: bold; color: #e9c66f;">
                            <?php echo strtoupper(substr($resumeData['full_name'], 0, 1)); ?>
                        </div>
                        
                        <div class="sidebar-item">
                            <div class="section-title">Contact</div>
                            <div style="margin-top: 10px;">✉️ <?php echo htmlspecialchars($resumeData['email']); ?></div>
                            <?php if (!empty($resumeData['phone'])): ?><div>📞 <?php echo htmlspecialchars($resumeData['phone']); ?></div><?php endif; ?>
                            <?php if (!empty($resumeData['location'])): ?><div>📍 <?php echo htmlspecialchars($resumeData['location']); ?></div><?php endif; ?>
                        </div>

                        <div class="sidebar-item">
                            <div class="section-title">Links</div>
                            <div style="margin-top: 10px;">
                                <?php if (!empty($resumeData['linkedin_url'])): ?><div style="margin-bottom: 5px;"><a href="<?php echo self::ensureProtocol($resumeData['linkedin_url']); ?>" target="_blank" style="color: #e9c66f; text-decoration: underline;">🔗 LinkedIn</a></div><?php endif; ?>
                                <?php if (!empty($resumeData['github_url'])): ?><div style="margin-bottom: 5px;"><a href="<?php echo self::ensureProtocol($resumeData['github_url']); ?>" target="_blank" style="color: #e9c66f; text-decoration: underline;">🔗 GitHub</a></div><?php endif; ?>
                                <?php if (!empty($resumeData['portfolio_url'])): ?><div style="margin-bottom: 5px;"><a href="<?php echo self::ensureProtocol($resumeData['portfolio_url']); ?>" target="_blank" style="color: #e9c66f; text-decoration: underline;">🔗 Portfolio</a></div><?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($resumeData['skills']['technical'])): ?>
                        <div class="sidebar-item">
                            <div class="section-title">Technical Skills</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 10px;">
                                <?php foreach ($resumeData['skills']['technical'] as $s): ?><span class="pill"><?php echo htmlspecialchars($s); ?></span><?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($resumeData['skills']['soft'])): ?>
                        <div class="sidebar-item">
                            <div class="section-title">Soft Skills</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 10px;">
                                <?php foreach ($resumeData['skills']['soft'] as $s): ?><span class="pill"><?php echo htmlspecialchars($s); ?></span><?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="main-content">
                        <div class="header">
                            <h1><?php echo htmlspecialchars($resumeData['full_name']); ?></h1>
                            <div style="color: #666; font-size: 10pt; line-height: 1.4;"><?php echo nl2br(htmlspecialchars($resumeData['professional_summary'] ?? '')); ?></div>
                        </div>
                        <?php renderMainSections($resumeData, false); ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Minimal Template -->
                <div class="tm-minimal">
                    <div class="header">
                        <h1><?php echo htmlspecialchars($resumeData['full_name']); ?></h1>
                        <div class="contact-row">
                            <span><?php echo htmlspecialchars($resumeData['email']); ?></span>
                            <?php if (!empty($resumeData['phone'])): ?> &bull; <span><?php echo htmlspecialchars($resumeData['phone']); ?></span><?php endif; ?>
                            <?php if (!empty($resumeData['location'])): ?> &bull; <span><?php echo htmlspecialchars($resumeData['location']); ?></span><?php endif; ?>
                        </div>
                        <div class="contact-row" style="margin-top: 5px;">
                            <?php if (!empty($resumeData['linkedin_url'])): ?><a href="<?php echo self::ensureProtocol($resumeData['linkedin_url']); ?>" target="_blank" style="color: #666; text-decoration: underline; margin-right: 15px;">LinkedIn</a><?php endif; ?>
                            <?php if (!empty($resumeData['github_url'])): ?><a href="<?php echo self::ensureProtocol($resumeData['github_url']); ?>" target="_blank" style="color: #666; text-decoration: underline; margin-right: 15px;">GitHub</a><?php endif; ?>
                            <?php if (!empty($resumeData['portfolio_url'])): ?><a href="<?php echo self::ensureProtocol($resumeData['portfolio_url']); ?>" target="_blank" style="color: #666; text-decoration: underline;">Portfolio</a><?php endif; ?>
                        </div>
                    </div>
                    <?php renderMainSections($resumeData, true); ?>
                </div>
            <?php endif; ?>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    /**
     * Ensure URL has a protocol
     */
    private static function ensureProtocol($url) {
        if (empty($url)) return '';
        $url = trim($url);
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "https://" . $url;
        }
        return htmlspecialchars($url);
    }
}

/**
 * Helper to render sections in a standard way
 */
function renderStandardSections($data) {
    if (!empty($data['professional_summary'])): ?>
        <div class="section-title">Summary</div>
        <p style="text-align: justify;"><?php echo nl2br(htmlspecialchars($data['professional_summary'])); ?></p>
    <?php endif;

    if (!empty($data['education'])): ?>
        <div class="section-title">Education</div>
        <?php foreach ($data['education'] as $edu): ?>
            <div class="entry">
                <div class="entry-header">
                    <span><?php echo htmlspecialchars($edu['degree']); ?></span>
                    <span class="entry-date"><?php echo htmlspecialchars($edu['year']); ?></span>
                </div>
                <div class="entry-subtitle"><?php echo htmlspecialchars($edu['institution']); ?></div>
            </div>
        <?php endforeach;
    endif;

    if (!empty($data['experience'])): ?>
        <div class="section-title">Experience</div>
        <?php foreach ($data['experience'] as $exp): ?>
            <div class="entry">
                <div class="entry-header">
                    <span><?php echo htmlspecialchars($exp['title']); ?></span>
                    <span class="entry-date"><?php echo htmlspecialchars($exp['duration']); ?></span>
                </div>
                <div class="entry-subtitle"><?php echo htmlspecialchars($exp['company']); ?></div>
                <ul>
                    <?php foreach ($exp['responsibilities'] as $r): ?><li><?php echo htmlspecialchars($r); ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach;
    endif;

    if (!empty($data['projects'])): ?>
        <div class="section-title">Projects</div>
        <?php foreach ($data['projects'] as $p): ?>
            <div class="entry">
                <div class="entry-header">
                    <span><?php echo htmlspecialchars($p['title']); ?> <?php if (!empty($p['link'])): ?><a href="<?php echo self::ensureProtocol($p['link']); ?>" target="_blank" style="font-size: 8.5pt; font-weight: normal; margin-left: 8px;">[View Project]</a><?php endif; ?></span>
                    <span class="entry-date"><?php echo htmlspecialchars($p['duration']); ?></span>
                </div>
                <ul style="margin: 2px 0 0 16px; color: #333;">
                    <?php 
                    $lines = explode("\n", $p['description']);
                    foreach ($lines as $line) {
                        if (trim($line)) {
                            echo '<li style="font-size: 9.5pt; margin-bottom: 1px;">' . htmlspecialchars(trim($line)) . '</li>';
                        }
                    }
                    ?>
                </ul>
            </div>
        <?php endforeach;
    endif;
    
    // Skills for ATS
    if (!empty($data['skills']['technical'])): ?>
        <div class="section-title">Skills</div>
        <p><strong>Technical:</strong> <?php echo implode(', ', array_map('htmlspecialchars', $data['skills']['technical'])); ?></p>
        <?php if (!empty($data['skills']['soft'])): ?><p><strong>Soft Skills:</strong> <?php echo implode(', ', array_map('htmlspecialchars', $data['skills']['soft'])); ?></p><?php endif; ?>
    <?php endif;

    if (!empty($data['certifications']) || !empty($data['achievements'])): ?>
        <div class="section-title">Certifications & Achievements</div>
        <?php foreach ($data['certifications'] as $c): ?>
            <div style="margin-top: 5px; break-inside: avoid;">• <strong><?php echo htmlspecialchars($c['name']); ?></strong> (<?php echo htmlspecialchars($c['issuer'] . ', ' . $c['date']); ?>) <?php if (!empty($c['credential_url'])): ?><a href="<?php echo self::ensureProtocol($c['credential_url']); ?>" target="_blank" style="font-size: 8.5pt; margin-left: 6px;">[View Certificate]</a><?php endif; ?></div>
        <?php endforeach; ?>
        <?php foreach ($data['achievements'] as $a): ?>
            <div style="margin-top: 5px;">• <?php echo htmlspecialchars($a['title']); ?> (<?php echo htmlspecialchars($a['date']); ?>)</div>
        <?php endforeach; ?>
    <?php endif;
}

/**
 * Helper for Modern and Minimal templates
 */
function renderMainSections($data, $showSkills = true) {
    if ($showSkills && !empty($data['skills']['technical'])): ?>
        <div class="section-title">Skills</div>
        <div class="minimal-line"></div>
        <div style="margin-bottom: 20px;">
            <?php foreach ($data['skills']['technical'] as $s): ?><span class="pill"><?php echo htmlspecialchars($s); ?></span><?php endforeach; ?>
        </div>
    <?php endif;

    if (!empty($data['education'])): ?>
        <div class="section-title">Education</div>
        <div class="minimal-line"></div>
        <?php foreach ($data['education'] as $edu): ?>
            <div class="entry">
                <div class="entry-header">
                    <span><?php echo htmlspecialchars($edu['degree']); ?></span>
                    <span class="entry-date"><?php echo htmlspecialchars($edu['year']); ?></span>
                </div>
                <div class="entry-subtitle"><?php echo htmlspecialchars($edu['institution']); ?></div>
            </div>
        <?php endforeach;
    endif;

    if (!empty($data['experience'])): ?>
        <div class="section-title">Experience</div>
        <div class="minimal-line"></div>
        <?php foreach ($data['experience'] as $exp): ?>
            <div class="entry">
                <div class="entry-header">
                    <span><?php echo htmlspecialchars($exp['title']); ?></span>
                    <span class="entry-date"><?php echo htmlspecialchars($exp['duration']); ?></span>
                </div>
                <div style="font-weight: 500; font-size: 10pt;"><?php echo htmlspecialchars($exp['company']); ?></div>
                <ul style="color: #444;">
                    <?php foreach ($exp['responsibilities'] as $r): ?><li><?php echo htmlspecialchars($r); ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach;
    endif;

    if (!empty($data['projects'])): ?>
        <div class="section-title">Projects</div>
        <div class="minimal-line"></div>
        <?php foreach ($data['projects'] as $p): ?>
            <div class="entry">
                <div class="entry-header">
                    <span><?php echo htmlspecialchars($p['title']); ?> <?php if (!empty($p['link'])): ?><a href="<?php echo self::ensureProtocol($p['link']); ?>" target="_blank" style="font-size: 8.5pt; font-weight: normal; margin-left: 6px;">[View]</a><?php endif; ?></span>
                    <span class="entry-date"><?php echo htmlspecialchars($p['duration']); ?></span>
                </div>
                <ul style="margin: 2px 0 0 16px; color: #444;">
                    <?php 
                    $lines = explode("\n", $p['description']);
                    foreach ($lines as $line) {
                        if (trim($line)) {
                            echo '<li style="font-size: 9.5pt; margin-bottom: 1px;">' . htmlspecialchars(trim($line)) . '</li>';
                        }
                    }
                    ?>
                </ul>
            </div>
        <?php endforeach;
    endif;

    if (!empty($data['certifications']) || !empty($data['achievements'])): ?>
        <div class="section-title">Certifications & Achievements</div>
        <div class="minimal-line"></div>
        <?php foreach ($data['certifications'] as $c): ?>
            <div style="margin-top: 5px; font-size: 9.5pt; break-inside: avoid;">• <strong><?php echo htmlspecialchars($c['name']); ?></strong> (<?php echo htmlspecialchars($c['issuer'] . ', ' . $c['date']); ?>) <?php if (!empty($c['credential_url'])): ?><a href="<?php echo self::ensureProtocol($c['credential_url']); ?>" target="_blank" style="font-size: 8.5pt; margin-left: 6px;">[View]</a><?php endif; ?></div>
        <?php endforeach; ?>
        <?php foreach ($data['achievements'] as $a): ?>
            <div style="margin-top: 5px; font-size: 9.5pt;">• <?php echo htmlspecialchars($a['title']); ?> (<?php echo htmlspecialchars($a['date']); ?>)</div>
        <?php endforeach; ?>
    <?php endif;
}
