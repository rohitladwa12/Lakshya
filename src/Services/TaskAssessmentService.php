<?php
/**
 * TaskAssessmentService
 *
 * Timed coordinator tasks:
 *  - Aptitude: coordinator picks a duration; 1 question per minute (60 min = 60 questions).
 *  - Technical: fixed 60 minutes, 3 coding problem statements.
 *
 * Questions/problems are generated ONCE when the task is assigned (AI batches run
 * in parallel), stored against the task, and served instantly to every student.
 * A task is "timed" when coordinator_tasks.duration_minutes is set; older tasks
 * (duration NULL) keep their original behaviour.
 */

class TaskAssessmentService
{
    /** Durations (minutes) a coordinator may choose for an aptitude round */
    const APTITUDE_DURATIONS = [30, 40, 60, 90, 120, 150, 180];
    const QUESTIONS_PER_MINUTE = 1;

    const TECHNICAL_DURATION = 60;
    const TECHNICAL_PROBLEMS = 3;

    const AI_BATCH_SIZE = 10;
    const AI_CONCURRENCY = 6;

    /** Submissions arriving later than this after the timer ends are refused */
    const SUBMIT_GRACE_SECONDS = 600;

    /** A 'generating' claim older than this is treated as crashed and can be re-claimed */
    const STALE_GENERATION_MINUTES = 15;

    private static $schemaChecked = false;

    // ------------------------------------------------------------------
    // Schema
    // ------------------------------------------------------------------

    /**
     * Adds the columns/tables this feature needs. Safe to call on every request:
     * it only inspects INFORMATION_SCHEMA and alters what is missing.
     * (Same changes as database/migrations/add_timed_task_assessments.sql)
     */
    public static function ensureSchema(PDO $db)
    {
        if (self::$schemaChecked) {
            return;
        }

        $columns = function ($table) use ($db) {
            $stmt = $db->prepare("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $stmt->execute([$table]);
            return array_change_key_case($stmt->fetchAll(PDO::FETCH_KEY_PAIR), CASE_LOWER);
        };

        $taskCols = $columns('coordinator_tasks');
        $add = [
            'concept' => "ADD COLUMN concept TEXT NULL",
            'difficulty' => "ADD COLUMN difficulty VARCHAR(20) DEFAULT 'Medium'",
            'duration_minutes' => "ADD COLUMN duration_minutes INT NULL COMMENT 'Round duration; NULL = legacy untimed task'",
            'question_count' => "ADD COLUMN question_count INT NULL COMMENT 'Aptitude: MCQs, Technical: coding problems'",
            'generation_status' => "ADD COLUMN generation_status VARCHAR(20) NULL COMMENT 'pending|generating|ready|failed'",
            'generation_progress' => "ADD COLUMN generation_progress INT NOT NULL DEFAULT 0",
            'generation_note' => "ADD COLUMN generation_note VARCHAR(500) NULL",
            'generation_started_at' => "ADD COLUMN generation_started_at DATETIME NULL",
        ];
        $alters = [];
        foreach ($add as $col => $sql) {
            if (!isset($taskCols[$col])) {
                $alters[] = $sql;
            }
        }
        if ($alters) {
            $db->exec("ALTER TABLE coordinator_tasks " . implode(', ', $alters));
        }

        $qCols = $columns('task_manual_questions');
        if (empty($qCols)) {
            $db->exec("CREATE TABLE IF NOT EXISTS task_manual_questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                task_id INT NOT NULL,
                question_text TEXT NOT NULL,
                option_a TEXT NOT NULL,
                option_b TEXT NOT NULL,
                option_c TEXT NOT NULL,
                option_d TEXT NOT NULL,
                correct_option CHAR(1) NOT NULL COMMENT 'A, B, C, or D',
                explanation TEXT,
                category VARCHAR(150) NULL,
                source VARCHAR(10) NULL COMMENT 'manual|ai|bank',
                question_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (task_id) REFERENCES coordinator_tasks(id) ON DELETE CASCADE,
                INDEX idx_task (task_id),
                INDEX idx_order (question_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            $qAlters = [];
            // AI options can exceed 255 chars; VARCHAR(255) would fail the insert in strict mode
            foreach (['option_a', 'option_b', 'option_c', 'option_d'] as $opt) {
                if (isset($qCols[$opt]) && strtolower($qCols[$opt]) === 'varchar') {
                    $qAlters[] = "MODIFY COLUMN $opt TEXT NOT NULL";
                }
            }
            if (!isset($qCols['category'])) $qAlters[] = "ADD COLUMN category VARCHAR(150) NULL";
            if (!isset($qCols['source'])) $qAlters[] = "ADD COLUMN source VARCHAR(10) NULL COMMENT 'manual|ai|bank'";
            if ($qAlters) {
                $db->exec("ALTER TABLE task_manual_questions " . implode(', ', $qAlters));
            }
        }

        $db->exec("CREATE TABLE IF NOT EXISTS task_coding_problems (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            problem_order INT NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            problem_statement TEXT NOT NULL,
            constraints TEXT NULL,
            example_input TEXT NULL,
            example_output TEXT NULL,
            source VARCHAR(10) NULL COMMENT 'ai|bank',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (task_id) REFERENCES coordinator_tasks(id) ON DELETE CASCADE,
            INDEX idx_task_order (task_id, problem_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS task_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            started_at DATETIME NOT NULL,
            UNIQUE KEY unique_attempt (task_id, student_id),
            FOREIGN KEY (task_id) REFERENCES coordinator_tasks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::$schemaChecked = true;
    }

    // ------------------------------------------------------------------
    // Task configuration
    // ------------------------------------------------------------------

    /**
     * Validates the coordinator's choice and returns [duration_minutes, question_count, generation_status]
     * for a new task. HR tasks stay untimed (all NULL).
     */
    public static function resolveTaskConfig($taskType, $durationInput)
    {
        if ($taskType === 'aptitude') {
            $duration = (int) $durationInput;
            if (!in_array($duration, self::APTITUDE_DURATIONS, true)) {
                throw new InvalidArgumentException('Please choose a valid aptitude round duration.');
            }
            return [$duration, $duration * self::QUESTIONS_PER_MINUTE, 'pending'];
        }
        if ($taskType === 'technical') {
            return [self::TECHNICAL_DURATION, self::TECHNICAL_PROBLEMS, 'pending'];
        }
        return [null, null, null];
    }

    public static function isTimed($task)
    {
        return is_array($task) && !empty($task['duration_minutes']) && in_array($task['task_type'] ?? '', ['aptitude', 'technical'], true);
    }

    public static function formatDuration($minutes)
    {
        $minutes = (int) $minutes;
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h && $m) return "{$h} hr {$m} min";
        if ($h) return $h === 1 ? '1 hour' : "{$h} hours";
        return "{$m} min";
    }

    public static function getTask(PDO $db, $taskId)
    {
        $stmt = $db->prepare("SELECT * FROM coordinator_tasks WHERE id = ?");
        $stmt->execute([(int) $taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ------------------------------------------------------------------
    // Generation
    // ------------------------------------------------------------------

    /**
     * Atomically claims generation for a task so two requests never generate the
     * same task at once. Returns true if this request owns the generation.
     */
    public static function claimGeneration(PDO $db, $taskId)
    {
        $stmt = $db->prepare("UPDATE coordinator_tasks
                              SET generation_status = 'generating', generation_progress = 0,
                                  generation_note = NULL, generation_started_at = NOW()
                              WHERE id = ?
                                AND (generation_status IN ('pending', 'failed')
                                     OR (generation_status = 'generating'
                                         AND (generation_started_at IS NULL OR generation_started_at < NOW() - INTERVAL " . self::STALE_GENERATION_MINUTES . " MINUTE)))");
        $stmt->execute([(int) $taskId]);
        return $stmt->rowCount() === 1;
    }

    /**
     * Generates and stores the questions/problems for a timed task.
     * Returns ['success' => bool, 'status' => ..., 'message' => ...].
     * If another request is already generating, returns status 'generating'.
     */
    public static function generate(PDO $db, $taskId)
    {
        self::ensureSchema($db);
        $task = self::getTask($db, $taskId);
        if (!$task || !self::isTimed($task)) {
            return ['success' => false, 'status' => null, 'message' => 'This task does not use pre-generated questions.'];
        }
        if ($task['generation_status'] === 'ready') {
            return ['success' => true, 'status' => 'ready', 'message' => 'Questions are ready.'];
        }
        if (!self::claimGeneration($db, $taskId)) {
            $task = self::getTask($db, $taskId);
            return ['success' => $task['generation_status'] === 'ready', 'status' => $task['generation_status'], 'message' => 'Generation is already in progress.'];
        }

        @set_time_limit(900);
        @ignore_user_abort(true);

        try {
            if ($task['task_type'] === 'aptitude') {
                $note = self::generateAptitude($db, $task);
            } else {
                $note = self::generateTechnical($db, $task);
            }
            $db->prepare("UPDATE coordinator_tasks SET generation_status = 'ready', generation_note = ? WHERE id = ?")
                ->execute([mb_substr($note, 0, 500), $taskId]);
            return ['success' => true, 'status' => 'ready', 'message' => $note];
        } catch (Throwable $e) {
            error_log("TaskAssessmentService::generate task $taskId failed: " . $e->getMessage());
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $db->prepare("UPDATE coordinator_tasks SET generation_status = 'failed', generation_note = ? WHERE id = ?")
                ->execute([mb_substr('Generation failed: ' . $e->getMessage(), 0, 500), $taskId]);
            return ['success' => false, 'status' => 'failed', 'message' => 'Question generation failed. Please retry.'];
        }
    }

    private static function normalizeKey($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        return trim($text);
    }

    /** Removes exact and near-duplicate questions (token overlap >= 85%) */
    private static function dedupe(array $questions, array $existing = [])
    {
        $kept = [];
        $seen = [];
        $tokenSets = [];
        foreach ($existing as $q) {
            $k = self::normalizeKey($q['question']);
            $seen[$k] = true;
            $tokenSets[] = array_flip(explode(' ', $k));
        }
        foreach ($questions as $q) {
            $k = self::normalizeKey($q['question']);
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $tokens = array_flip(explode(' ', $k));
            $dup = false;
            foreach ($tokenSets as $other) {
                $inter = count(array_intersect_key($tokens, $other));
                $union = count($tokens) + count($other) - $inter;
                if ($union > 0 && $inter / $union >= 0.85) {
                    $dup = true;
                    break;
                }
            }
            if ($dup) {
                continue;
            }
            $seen[$k] = true;
            $tokenSets[] = $tokens;
            $kept[] = $q;
        }
        return $kept;
    }

    /**
     * The AI puts most correct answers in A/B, so a student who always picks A/B scores well.
     * Shuffle option order (and re-point the answer) unless an option depends on its position
     * ("All of the above", "Both A and B", "None of these"...).
     */
    public static function shuffleOptionsSafely(array $q)
    {
        foreach ($q['options'] as $opt) {
            if (preg_match('/\b(above|below|both|neither|either|all\s+of\s+(these|them|the)|none\s+of\s+(these|them|the)|[A-D]\s*(and|&|or)\s*[A-D]|only\s+[A-D1-4]|options?)\b/i', $opt)) {
                return $q;
            }
        }
        // An explanation naming a letter ("Option B is correct") would be wrong after shuffling
        if (preg_match('/\b(option|answer|choice)\s*\(?[A-D]\)?(?![A-Za-z])/i', $q['explanation'] ?? '')) {
            return $q;
        }
        $correctText = $q['options'][$q['answer']];
        $order = [0, 1, 2, 3];
        shuffle($order);
        $q['options'] = array_map(fn($i) => $q['options'][$i], $order);
        $q['answer'] = array_search($correctText, $q['options'], true);
        return $q;
    }

    private static function generateAptitude(PDO $db, array $task)
    {
        $taskId = (int) $task['id'];
        $target = (int) $task['question_count'];
        $concept = trim((string) ($task['concept'] ?? '')) ?: 'Quantitative Aptitude, Logical Reasoning, Verbal Ability';
        $company = trim((string) ($task['company_name'] ?? '')) ?: 'General';
        $difficulty = $task['difficulty'] ?? 'Medium';

        $ai = new AIService();
        $progress = $db->prepare("UPDATE coordinator_tasks SET generation_progress = ? WHERE id = ?");

        // ~10% extra so dropping invalid/duplicate questions still leaves enough
        $batches = (int) ceil(($target * 1.1) / self::AI_BATCH_SIZE);
        $subTopics = $ai->planSubTopics('Aptitude', $concept, $batches);

        $res = $ai->generateMCQsParallel('Aptitude', $concept, $subTopics, self::AI_BATCH_SIZE, $company, $difficulty,
            function ($n) use ($progress, $taskId, $target) {
                $progress->execute([min($n, $target), $taskId]);
            }, self::AI_CONCURRENCY);
        $questions = self::dedupe($res['questions']);

        // One retry round for whatever is still missing
        if (count($questions) < $target) {
            $missing = $target - count($questions);
            $retryBatches = (int) ceil(($missing * 1.2) / self::AI_BATCH_SIZE);
            $retryTopics = array_slice(array_merge(array_reverse($subTopics), $subTopics), 0, $retryBatches);
            $base = count($questions);
            $retry = $ai->generateMCQsParallel('Aptitude', $concept, $retryTopics, self::AI_BATCH_SIZE, $company, $difficulty,
                function ($n) use ($progress, $taskId, $target, $base) {
                    $progress->execute([min($base + $n, $target), $taskId]);
                }, self::AI_CONCURRENCY);
            $questions = array_merge($questions, self::dedupe($retry['questions'], $questions));
        }

        $aiCount = min(count($questions), $target);
        foreach ($questions as &$q) {
            $q['source'] = 'ai';
        }
        unset($q);
        $questions = array_slice($questions, 0, $target);

        // Top up from the question bank so the paper always has the full count
        if (count($questions) < $target) {
            $stmt = $db->query("SELECT question, option_a, option_b, option_c, option_d, correct_option, topic FROM aptitude_questions ORDER BY RAND() LIMIT " . (int) ($target * 3));
            $bank = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $letter = strtoupper(trim((string) $row['correct_option']));
                $q = AIService::validateMCQ([
                    'question' => $row['question'],
                    'options' => [$row['option_a'], $row['option_b'], $row['option_c'], $row['option_d']],
                    'answer' => $letter,
                    'explanation' => 'Standard aptitude question from the question bank.',
                    'category' => $row['topic'] ?: 'General Aptitude',
                ]);
                if ($q) {
                    $q['source'] = 'bank';
                    $bank[] = $q;
                }
            }
            $questions = array_merge($questions, array_slice(self::dedupe($bank, $questions), 0, $target - count($questions)));
        }

        if (count($questions) < $target) {
            throw new RuntimeException("Only " . count($questions) . " of $target questions could be prepared" . ($res['last_error'] ? " (AI: " . mb_substr($res['last_error'], 0, 150) . ")" : ''));
        }

        // Spread correct answers evenly across A-D
        $questions = array_map([self::class, 'shuffleOptionsSafely'], $questions);

        $db->beginTransaction();
        $db->prepare("DELETE FROM task_manual_questions WHERE task_id = ?")->execute([$taskId]);
        $ins = $db->prepare("INSERT INTO task_manual_questions
            (task_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, category, source, question_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($questions as $i => $q) {
            $ins->execute([
                $taskId, $q['question'], $q['options'][0], $q['options'][1], $q['options'][2], $q['options'][3],
                chr(65 + $q['answer']), $q['explanation'], $q['category'], $q['source'], $i + 1
            ]);
        }
        $db->prepare("UPDATE coordinator_tasks SET generation_progress = ? WHERE id = ?")->execute([count($questions), $taskId]);
        $db->commit();

        $bankCount = count($questions) - $aiCount;
        return $bankCount > 0
            ? "$target questions ready ($aiCount AI-generated, $bankCount from question bank)."
            : "$target AI-generated questions ready.";
    }

    private static function generateTechnical(PDO $db, array $task)
    {
        $taskId = (int) $task['id'];
        $target = (int) $task['question_count'] ?: self::TECHNICAL_PROBLEMS;
        $company = trim((string) ($task['company_name'] ?? '')) ?: 'General';
        $difficulty = $task['difficulty'] ?? 'Medium';

        $ai = new AIService();
        $res = $ai->generateCodingProblems($task['concept'] ?? '', $target, $difficulty, $company);
        $problems = [];
        foreach ($res['problems'] as $p) {
            $p['source'] = 'ai';
            $problems[] = $p;
        }
        $aiCount = count($problems);

        // Top up from the coding problem bank
        if (count($problems) < $target) {
            $bankDifficulty = match (strtolower((string) $difficulty)) { 'low' => 'Easy', 'high' => 'Hard', default => 'Medium' };
            $stmt = $db->prepare("SELECT title, problem_statement, constraints, example_input, example_output FROM coding_problems
                                  ORDER BY (difficulty = ?) DESC, RAND() LIMIT " . (int) ($target * 2));
            $stmt->execute([$bankDifficulty]);
            $titles = array_map(fn($p) => mb_strtolower($p['title']), $problems);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (count($problems) >= $target) break;
                if (in_array(mb_strtolower($row['title']), $titles, true)) continue;
                $row['source'] = 'bank';
                $problems[] = $row;
            }
        }

        if (count($problems) < $target) {
            throw new RuntimeException("Only " . count($problems) . " of $target coding problems could be prepared" . (!empty($res['message']) ? " (AI: " . mb_substr($res['message'], 0, 150) . ")" : ''));
        }

        $db->beginTransaction();
        $db->prepare("DELETE FROM task_coding_problems WHERE task_id = ?")->execute([$taskId]);
        $ins = $db->prepare("INSERT INTO task_coding_problems
            (task_id, problem_order, title, problem_statement, constraints, example_input, example_output, source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach (array_values($problems) as $i => $p) {
            $ins->execute([$taskId, $i + 1, $p['title'], $p['problem_statement'], $p['constraints'] ?? '', $p['example_input'] ?? '', $p['example_output'] ?? '', $p['source']]);
        }
        $db->prepare("UPDATE coordinator_tasks SET generation_progress = ? WHERE id = ?")->execute([count($problems), $taskId]);
        $db->commit();

        $bankCount = count($problems) - $aiCount;
        return $bankCount > 0
            ? "$target coding problems ready ($aiCount AI-generated, $bankCount from problem bank)."
            : "$target AI-generated coding problems ready.";
    }

    // ------------------------------------------------------------------
    // Serving to students
    // ------------------------------------------------------------------

    /** Stored row -> validated MCQ (null if broken: placeholder options, missing passage, bad key...) */
    private static function validateStoredRow(array $r)
    {
        return AIService::validateMCQ([
            'question' => $r['question_text'],
            'options' => [$r['option_a'], $r['option_b'], $r['option_c'], $r['option_d']],
            'answer' => strtoupper(trim((string) $r['correct_option'])),
            'explanation' => $r['explanation'] ?? '',
            'category' => $r['category'] ?? '',
        ], $r['category'] ?: 'General Aptitude');
    }

    /**
     * Papers generated before the validator was tightened can contain broken questions
     * (options literally "A","B","C","D", or "...in the passage?" with no passage).
     * Replaces those AI/bank rows with valid question-bank questions, keeping the paper's
     * size and order. Coordinator-written (manual) questions are never touched.
     * Returns the number of questions replaced or removed.
     */
    public static function repairAptitudePaper(PDO $db, $taskId)
    {
        $select = $db->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, category, source
                                FROM task_manual_questions WHERE task_id = ? ORDER BY question_order, id");
        $findBroken = function () use ($select, $taskId) {
            $select->execute([(int) $taskId]);
            $bad = [];
            $good = [];
            foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $q = self::validateStoredRow($r);
                if ($q === null && in_array($r['source'], ['ai', 'bank'], true)) {
                    $bad[] = (int) $r['id'];
                } elseif ($q !== null) {
                    $good[] = $q;
                }
            }
            return [$bad, $good];
        };

        // Cheap check first; only lock when something needs fixing
        [$bad] = $findBroken();
        if (empty($bad)) {
            return 0;
        }

        $db->beginTransaction();
        try {
            // Serialise repairs of the same paper (concurrent student loads)
            $db->prepare("SELECT id FROM coordinator_tasks WHERE id = ? FOR UPDATE")->execute([(int) $taskId]);
            [$bad, $good] = $findBroken();
            if (empty($bad)) {
                $db->commit();
                return 0;
            }

            $stmt = $db->query("SELECT question, option_a, option_b, option_c, option_d, correct_option, topic FROM aptitude_questions ORDER BY RAND() LIMIT " . (int) (count($bad) * 4 + 20));
            $bank = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $q = AIService::validateMCQ([
                    'question' => $row['question'],
                    'options' => [$row['option_a'], $row['option_b'], $row['option_c'], $row['option_d']],
                    'answer' => strtoupper(trim((string) $row['correct_option'])),
                    'explanation' => 'Standard aptitude question from the question bank.',
                    'category' => $row['topic'] ?: 'General Aptitude',
                ]);
                if ($q) {
                    $bank[] = self::shuffleOptionsSafely($q);
                }
            }
            $bank = self::dedupe($bank, $good);

            $upd = $db->prepare("UPDATE task_manual_questions
                                 SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?,
                                     correct_option = ?, explanation = ?, category = ?, source = 'bank'
                                 WHERE id = ?");
            $del = $db->prepare("DELETE FROM task_manual_questions WHERE id = ?");
            foreach ($bad as $i => $rowId) {
                if (isset($bank[$i])) {
                    $q = $bank[$i];
                    $upd->execute([$q['question'], $q['options'][0], $q['options'][1], $q['options'][2], $q['options'][3],
                        chr(65 + $q['answer']), $q['explanation'], $q['category'], $rowId]);
                } else {
                    // Better a slightly shorter paper than a question students can't answer
                    $del->execute([$rowId]);
                }
            }
            $db->commit();
            error_log("TaskAssessmentService::repairAptitudePaper task $taskId: fixed " . count($bad) . " broken question(s)");
            return count($bad);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Stored MCQs in the format the aptitude test uses (answer = 0-3 index) */
    public static function getAptitudeQuestions(PDO $db, $taskId)
    {
        $stmt = $db->prepare("SELECT question_text, option_a, option_b, option_c, option_d, correct_option, explanation, category
                              FROM task_manual_questions WHERE task_id = ? ORDER BY question_order, id");
        $stmt->execute([(int) $taskId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $letter = strtoupper(trim((string) $r['correct_option']));
            $out[] = [
                'question' => $r['question_text'],
                'options' => [$r['option_a'], $r['option_b'], $r['option_c'], $r['option_d']],
                'answer' => in_array($letter, ['A', 'B', 'C', 'D'], true) ? ord($letter) - 65 : 0,
                'explanation' => $r['explanation'] ?? '',
                'category' => $r['category'] ?: 'General Aptitude',
            ];
        }
        return $out;
    }

    public static function getCodingProblems(PDO $db, $taskId)
    {
        $stmt = $db->prepare("SELECT problem_order, title, problem_statement, constraints, example_input, example_output
                              FROM task_coding_problems WHERE task_id = ? ORDER BY problem_order, id");
        $stmt->execute([(int) $taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Starts (or resumes) the student's timer for a task. The start time is stored
     * server-side, so refreshing the page never resets the clock.
     * Returns ['started_at' => ..., 'elapsed' => sec, 'remaining' => sec, 'duration' => sec]
     */
    public static function startAttempt(PDO $db, $taskId, $studentId, $durationMinutes)
    {
        $db->prepare("INSERT IGNORE INTO task_attempts (task_id, student_id, started_at) VALUES (?, ?, NOW())")
            ->execute([(int) $taskId, (string) $studentId]);
        return self::getAttempt($db, $taskId, $studentId, $durationMinutes);
    }

    public static function getAttempt(PDO $db, $taskId, $studentId, $durationMinutes)
    {
        $stmt = $db->prepare("SELECT started_at, TIMESTAMPDIFF(SECOND, started_at, NOW()) AS elapsed FROM task_attempts WHERE task_id = ? AND student_id = ?");
        $stmt->execute([(int) $taskId, (string) $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $duration = (int) $durationMinutes * 60;
        $elapsed = max(0, (int) $row['elapsed']);
        return [
            'started_at' => $row['started_at'],
            'elapsed' => $elapsed,
            'remaining' => max(0, $duration - $elapsed),
            'duration' => $duration,
        ];
    }

    /**
     * All identifiers a student's task rows may be stored under (login username, USN, Aadhar) —
     * the same set the dashboard and assigned-task page use.
     */
    public static function studentIdentifiers()
    {
        static $ids = null;
        if ($ids !== null) {
            return $ids;
        }
        $ids = [(string) getUsername(), (string) getStudentIdForAssessment()];
        try {
            $history = (new StudentProfile())->getAcademicHistory(getUserId(), getInstitution() ?: 'GMU');
            $main = $history[0] ?? null;
            if ($main) {
                $ids[] = (string) ($main['usn'] ?? '');
                $ids[] = (string) ($main['aadhar'] ?? '');
            }
        } catch (Throwable $e) {
            error_log("TaskAssessmentService::studentIdentifiers profile lookup failed: " . $e->getMessage());
        }
        $ids = array_values(array_unique(array_filter($ids, 'strlen')));
        return $ids;
    }

    /** True if the task was assigned to this student */
    public static function isAssignedTo(array $task, array $studentIds)
    {
        $targets = array_map('strval', json_decode($task['target_students'] ?? '[]', true) ?: []);
        return (bool) array_intersect($studentIds, $targets);
    }

    /**
     * Score of a timed coding round from its saved submissions: best evaluated score per
     * problem (each out of 10) as a percentage. Returns [score 0-100, report markdown].
     */
    public static function scoreTimedTechnical(array $details)
    {
        $problemsList = $details['problems'] ?? [];
        $total = 0.0;
        $lines = [];
        foreach ($problemsList as $i => $p) {
            $sub = $details['submissions'][$i] ?? null;
            $best = $sub ? (float) ($sub['best_score'] ?? 0) : 0.0;
            $total += $best;
            $lines[] = "### Problem " . ($i + 1) . ": " . ($p['title'] ?? '') . "\n"
                . ($sub
                    ? "- Best score: **" . round($best, 1) . "/10** (" . (!empty($sub['passed']) ? 'passed' : 'not passed') . ", " . (int) ($sub['attempts'] ?? 0) . " submission(s))\n"
                        . (!empty($sub['best_feedback']) ? "- Feedback: " . $sub['best_feedback'] . "\n" : '')
                    : "- Not submitted\n");
        }
        $maxTotal = max(1, count($problemsList)) * 10;
        $score = (int) round(($total / $maxTotal) * 100);
        $content = "## Timed Coding Round Report\n\nScore: **$score%** (" . round($total, 1) . " / $maxTotal)\n\n" . implode("\n", $lines);
        return [$score, $content];
    }

    /**
     * Timed technical sessions whose clock ran out but were never submitted (browser closed,
     * crash, lost connection) stayed 'active' forever and showed 0 — even when the student had
     * evaluated solutions saved. Finalises them from those saved results with the same scoring
     * and proctoring penalty as a normal submission. Returns the number finalised.
     */
    public static function finalizeExpiredTechnicalSessions(PDO $db, array $task)
    {
        if (!self::isTimed($task) || $task['task_type'] !== 'technical') {
            return 0;
        }
        $taskId = (int) $task['id'];
        $stmt = $db->prepare("SELECT id, student_id, usn, details, started_at FROM unified_ai_assessments
                              WHERE assessment_type = 'Technical' AND status = 'active'
                                AND JSON_UNQUOTE(JSON_EXTRACT(details, '$.task_id')) = ?");
        $stmt->execute([(string) $taskId]);
        $done = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $details = json_decode($row['details'], true) ?: [];
            if (empty($details['timed'])) {
                continue;
            }
            $ids = array_values(array_unique(array_filter([(string) $row['usn'], (string) $row['student_id']], 'strlen')));
            $attempt = null;
            foreach ($ids as $sid) {
                $attempt = self::getAttempt($db, $taskId, $sid, $task['duration_minutes']);
                if ($attempt) break;
            }
            // Only once the clock (plus a grace period for a final submit) has fully run out
            if (!$attempt || $attempt['elapsed'] <= $attempt['duration'] + self::SUBMIT_GRACE_SECONDS) {
                continue;
            }

            [$raw, $content] = self::scoreTimedTechnical($details);

            // Same proctoring rule as a normal submission
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $p = $db->prepare("SELECT status, strike_count, penalty_pct FROM proctor_sessions
                               WHERE student_id IN ($ph) AND assessment_type = 'technical' AND created_at >= ? - INTERVAL 10 MINUTE
                               ORDER BY created_at DESC LIMIT 1");
            $p->execute(array_merge($ids, [$row['started_at']]));
            $ps = $p->fetch(PDO::FETCH_ASSOC) ?: null;
            $penalty = $ps ? (float) $ps['penalty_pct'] : 0.0;
            $terminated = $ps && ($ps['status'] === 'terminated' || (int) $ps['strike_count'] >= 3);
            // Connection-loss terminations (no 3 strikes) get no termination penalty
            $strikeTerminated = $terminated && (int) $ps['strike_count'] >= \App\Services\ProctoringService::MAX_STRIKES;
            if ($strikeTerminated) {
                $penalty = max($penalty, \App\Services\ProctoringService::PENALTY_TERMINATED);
            }
            $final = round(\App\Services\ProctoringService::finalScoreAfterProctoring($raw, $penalty, $strikeTerminated));

            $details['report_content'] = $content . "\n\n_Finalised automatically after the time limit (the round was not submitted from the browser)._";
            $details['raw_score'] = $raw;
            $details['penalty_pct'] = $penalty;
            $details['strike_count'] = $ps ? (int) $ps['strike_count'] : 0;
            $details['final_score'] = $final;
            $details['auto_finalized'] = true;
            $details['proctor_review'] = $terminated;

            $upd = $db->prepare("UPDATE unified_ai_assessments SET score = ?, feedback = 'Report Generated', status = 'completed', completed_at = CURRENT_TIMESTAMP, details = ?
                                 WHERE id = ? AND status = 'active'");
            $upd->execute([$final, json_encode($details), $row['id']]);
            if ($upd->rowCount() !== 1) {
                continue; // finalised concurrently by the student's own submit
            }
            $db->prepare("INSERT INTO task_completions (task_id, student_id, score, time_taken) VALUES (?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE score = VALUES(score), time_taken = VALUES(time_taken), completed_at = CURRENT_TIMESTAMP")
                ->execute([$taskId, $row['usn'] ?: $row['student_id'], $final, $attempt['duration']]);
            $done++;
        }
        return $done;
    }

    /** True if any of the student's identifiers already has a completion for the task */
    public static function isCompleted(PDO $db, $taskId, array $studentIds)
    {
        $studentIds = array_values(array_unique(array_filter(array_map('strval', $studentIds), 'strlen')));
        if (empty($studentIds)) {
            return false;
        }
        $ph = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $db->prepare("SELECT 1 FROM task_completions WHERE task_id = ? AND student_id IN ($ph) LIMIT 1");
        $stmt->execute(array_merge([(int) $taskId], $studentIds));
        return (bool) $stmt->fetchColumn();
    }
}
