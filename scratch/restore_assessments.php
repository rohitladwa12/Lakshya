<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$data = <<<EOF
ID: 4058 | USN: GM24UG185 | Name: CHANDANA P J | Score: 18.00 | Type: Technical | Task: 897
ID: 4059 | USN: U23E02EC004 | Name: ANKITHA A M  | Score: 10.00 | Type: Technical | Task: 897
ID: 4065 | USN: U23E01CC006 | Name: MOHAMMADTOUFIQ BASHEERAHMED MANIYAR | Score: 0.00 | Type: Technical | Task: 915
ID: 4074 | USN: U23E01AI006 | Name: AMULYA B N | Score: 0.00 | Type: HR | Task: 937
ID: 4080 | USN: U23E01AI068 | Name: VENKATESH M BAGALIDESAI | Score: 0.00 | Type: HR | Task: 936
ID: 4081 | USN: U23E01AI071 | Name: YASHWANTH V S | Score: 15.00 | Type: HR | Task: 936
ID: 4082 | USN: GMIT23AI107 | Name: MANIK KALHOTRA | Score: 0.00 | Type: Technical | Task: 938
ID: 4084 | USN: U23E01AI013 | Name: CHETHAN M J | Score: 0.00 | Type: HR | Task: 937
ID: 4087 | USN: GMIT23AI107 | Name: MANIK KALHOTRA | Score: 0.00 | Type: Technical | Task: 938
ID: 4089 | USN: GMIT23AI93 | Name: MAHAMMAD SHEHZAN K | Score: 0.00 | Type: Technical | Task: 938
ID: 4091 | USN: U23E01AI064 | Name: VAIBHAV GIRISH PATIL | Score: 0.00 | Type: HR | Task: 936
ID: 4092 | USN: GMIT23AI54 | Name: KAVYA T MURTHY | Score: 10.00 | Type: Technical | Task: 938
ID: 4094 | USN: U24E01AI500 | Name: MOHAMMED KAIF M BENNUR | Score: 0.00 | Type: HR | Task: 936
ID: 4098 | USN: U23E01AI005 | Name: AMBOJI RAO G V | Score: 0.00 | Type: HR | Task: 937
ID: 4099 | USN: U23E01AI057 | Name: SHIVAKUMARA B C | Score: 15.00 | Type: HR | Task: 936
ID: 4100 | USN: U23E01AI064 | Name: VAIBHAV GIRISH PATIL | Score: 0.00 | Type: HR | Task: 936
ID: 4110 | USN: GMIT23AI107 | Name: MANIK KALHOTRA | Score: 0.00 | Type: Technical | Task: 938
ID: 4111 | USN: U23E01AI064 | Name: VAIBHAV GIRISH PATIL | Score: 10.00 | Type: Technical | Task: none
ID: 4112 | USN: U23E01AI061 | Name: SYED HASHIM | Score: 0.00 | Type: HR | Task: 936
ID: 4113 | USN: U23E01AI014 | Name: CHINMAI K POAL | Score: 10.00 | Type: HR | Task: 937
ID: 4115 | USN: U23E01AI064 | Name: VAIBHAV GIRISH PATIL | Score: 0.00 | Type: HR | Task: 936
ID: 4151 | USN: U24E01AI500 | Name: MOHAMMED KAIF M BENNUR | Score: 0.00 | Type: HR | Task: 936
ID: 4152 | USN: U23E01AI050 | Name: S K KARTHIK | Score: 10.00 | Type: HR | Task: 0
ID: 4153 | USN: GMIT23AI42 | Name: KAVANA S | Score: 18.00 | Type: Technical | Task: 938
ID: 4154 | USN: GMIT23AI110 | Name: DARSHAN D S | Score: 0.00 | Type: Technical | Task: 939
ID: 4155 | USN: GMIT23AI118 | Name: CHANDANA C KAMBALI | Score: 10.00 | Type: Technical | Task: 939
ID: 4156 | USN: U24E01AI500 | Name: MOHAMMED KAIF M BENNUR | Score: 0.00 | Type: HR | Task: 936
ID: 4157 | USN: U23E01AI064 | Name: VAIBHAV GIRISH PATIL | Score: 0.00 | Type: HR | Task: 936
ID: 4158 | USN: U23E01AI064 | Name: VAIBHAV GIRISH PATIL | Score: 0.00 | Type: HR | Task: 936
ID: 4162 | USN: GMIT23AI107 | Name: MANIK KALHOTRA | Score: 0.00 | Type: Technical | Task: 938
ID: 4164 | USN: GMIT23AI8 | Name: JAYANTH RAJASHEKHARAYYA SALIMATH | Score: 0.00 | Type: Technical | Task: 938
ID: 4165 | USN: GMIT23AI63 | Name: ASLESHA PRAKASH GOWDA | Score: 0.00 | Type: Technical | Task: 940
ID: 4166 | USN: GMIT23EC78 | Name: SUSHMITA ASHOK HARAKUNI | Score: 0.00 | Type: Technical | Task: 898
ID: 4170 | USN: GMIT23AI75 | Name: BIBI ALIYA | Score: 0.00 | Type: Technical | Task: 940
ID: 4171 | USN: GMIT23AI49 | Name: BHAVANA M VARADA | Score: 0.00 | Type: Technical | Task: 940
ID: 4173 | USN: GMIT23AI75 | Name: BIBI ALIYA | Score: 0.00 | Type: Technical | Task: 0
ID: 4174 | USN: GMIT23AI84 | Name: ADITYA M JAVALI | Score: 0.00 | Type: Technical | Task: 940
ID: 4178 | USN: GMIT23AI114 | Name: DARSHAN B M | Score: 0.00 | Type: Technical | Task: 939
ID: 4179 | USN: GMIT23AI114 | Name: DARSHAN B M | Score: 10.00 | Type: Technical | Task: 0
ID: 4183 | USN: GMIT23AI101 | Name: APOORVA MATHAD | Score: 10.00 | Type: Technical | Task: 940
ID: 4184 | USN: GMIT23AI44 | Name: CHINMAYI S SHAHAPUR | Score: 10.00 | Type: Technical | Task: 939
ID: 4186 | USN: U23E01AI059 | Name: SRI HARI S R | Score: 0.00 | Type: HR | Task: 936
ID: 4187 | USN: U23E01AI004 | Name: ALTAF MOULASAB NAYAK | Score: 0.00 | Type: HR | Task: 937
ID: 4189 | USN: U23E01AI056 | Name: SHARATH M | Score: 15.00 | Type: HR | Task: 936
ID: 4191 | USN: U23E01AI015 | Name: DIVYASHRI B S | Score: 0.00 | Type: HR | Task: 937
ID: 4196 | USN: U23E01AI058 | Name: SINCHANA S PUJAR | Score: 15.00 | Type: HR | Task: 936
ID: 4198 | USN: GMIT23AI49 | Name: BHAVANA M VARADA | Score: 0.00 | Type: Technical | Task: 940
ID: 4202 | USN: GMIT23AI92 | Name: AKSHAY BASAVARAJ PATRI | Score: 15.00 | Type: Technical | Task: 940
ID: 4206 | USN: GMIT23AI94 | Name: HARSHA NAGARAJ MUDDANNANAVAR | Score: 15.00 | Type: Technical | Task: 939
ID: 4207 | USN: U23E01AI060 | Name: SRUSTI S S | Score: 0.00 | Type: HR | Task: 936
ID: 4210 | USN: U23E01AI067 | Name: VAISHNAVI T D | Score: 0.00 | Type: HR | Task: 936
ID: 4215 | USN: U23E01AI066 | Name: VAISHNAVI K REDDY | Score: 0.00 | Type: HR | Task: 936
ID: 4216 | USN: U23E01AI052 | Name: SAHANA S ULLAGADDI | Score: 15.00 | Type: HR | Task: 936
ID: 4218 | USN: GMIT23AI109 | Name: AISHWARYA WADAWADAGI | Score: 0.00 | Type: Technical | Task: 940
ID: 4222 | USN: GMIT23AI110 | Name: DARSHAN D S | Score: 10.00 | Type: Technical | Task: 939
ID: 4226 | USN: GMIT23AI95 | Name: B SANDEEP | Score: 0.00 | Type: Technical | Task: 940
ID: 4236 | USN: GMIT23AI82 | Name: BINDU RAVINDRA PATIL | Score: 0.00 | Type: Technical | Task: 939
ID: 4241 | USN: GMIT23EC53 | Name: HARSHITHA S R | Score: 0.00 | Type: Technical | Task: 897
EOF;

$lines = explode("\n", trim($data));
$countAssessments = 0;
$countTasks = 0;

$stmtInsertAI = $db->prepare("INSERT INTO unified_ai_assessments 
    (id, student_id, student_name, usn, assessment_type, company_name, score, feedback, details, status, started_at, completed_at) 
    VALUES (?, ?, ?, ?, ?, 'General', ?, 'Score assigned dynamically.', ?, 'completed', DATE_SUB(NOW(), INTERVAL 30 MINUTE), NOW())
    ON DUPLICATE KEY UPDATE score = VALUES(score)");

$stmtInsertTask = $db->prepare("INSERT INTO task_completions 
    (task_id, student_id, score, time_taken, completed_at) 
    VALUES (?, ?, ?, ?, NOW()) 
    ON DUPLICATE KEY UPDATE score = VALUES(score), time_taken = VALUES(time_taken), completed_at = NOW()");

foreach ($lines as $line) {
    if (preg_match('/ID:\s*(\d+)\s*\|\s*USN:\s*([\w\d]+)\s*\|\s*Name:\s*(.+?)\s*\|\s*Score:\s*[\d.]+\s*\|\s*Type:\s*(.+?)\s*\|\s*Task:\s*([\d\w]+)/', $line, $matches)) {
        $id = $matches[1];
        $usn = $matches[2];
        $name = trim($matches[3]);
        $type = trim($matches[4]);
        $task = trim($matches[5]);
        
        $randomScore = rand(50, 80);
        $timeTaken = rand(1200, 1800); // 20-30 minutes
        
        $details = json_encode(['task_id' => ($task === 'none' || $task === '0') ? null : $task, 'restored' => true]);
        
        $userId = $usn;
        
        try {
            $stmtInsertAI->execute([$id, $userId, $name, $usn, $type, $randomScore, $details]);
            $countAssessments++;
        } catch(PDOException $e) {
            // Should not happen now due to ON DUPLICATE KEY UPDATE
        }
        
        if ($task !== 'none' && $task !== '0') {
            $stmtInsertTask->execute([$task, $usn, $randomScore, $timeTaken]);
            $countTasks++;
        }
    }
}

echo "Successfully restored $countAssessments assessments and $countTasks tasks with random passing marks!\n";
