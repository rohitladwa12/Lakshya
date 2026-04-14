<?php

require_once __DIR__ . '/Model.php';

class AptitudeQuestion extends Model {
    protected $table = 'aptitude_questions';
    protected $timestamps = false;
    
    /**
     * Fetch random aptitude questions
     */
    public function getRandomQuestions($limit = 10) {
        $sql = "SELECT id, question, option_a, option_b, option_c, option_d, correct_option, topic 
                FROM {$this->table} 
                WHERE correct_option != 'e' 
                ORDER BY RAND() 
                LIMIT ?";
        $stmt = $this->db->prepare($sql); 
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
