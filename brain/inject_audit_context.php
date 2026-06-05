<?php
$file = 'src/Services/AIService.php';
$content = file_get_contents($file);

$methods = [
    'analyzeResume', 'refineResumeAnalysis', 'advancedATSAnalysis', 
    'getTechnicalInterviewResponse', 'generateTechnicalInterviewReport', 
    'getCompanyAptitudeQuestions', 'generateSkillQuiz', 'generateProjectViva', 
    'evaluateProjectViva', 'getTechnicalQuestion', 'evaluateCode', 
    'getHRQuestion', 'generateHRReport', 'generateCodingSolution', 
    'analyzeTargetFit', 'predictCareerPath', 'analyzeProfileMatch', 
    'mutateAptitudeBatch', 'mutateCodingChallenge'
];

foreach ($methods as $method) {
    // Look for callAPI calls within the specific method
    // This is a bit complex with regex, so we'll do a simpler replacement for now
    // but ensure we add the audit_method to the options array
    
    // We'll use a more precise strategy: Find the method block, then replace callAPI inside it.
}

// Better strategy: replace all $this->callAPI($messages, [ ... ]) with $this->callAPI($messages, array_merge([ ... ], ['audit_method' => '...']))
// Actually, since most are already defined, we can just inject it.

// Let's do it manually for the most important ones to be safe
$content = str_replace(
    "return \$this->callAPI(\$messages, [", 
    "return \$this->callAPI(\$messages, [\n            'audit_method' => __FUNCTION__,", 
    $content
);

// Catch the ones that use a variable $response = $this->callAPI(...)
$content = str_replace(
    "\$response = \$this->callAPI(\$messages, [", 
    "\$response = \$this->callAPI(\$messages, [\n            'audit_method' => __FUNCTION__,", 
    $content
);

file_put_contents($file, $content);
echo "Injected audit_method into AIService methods.";
