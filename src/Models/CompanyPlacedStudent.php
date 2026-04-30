<?php
/**
 * CompanyPlacedStudent Model
 * Handles company placed students data from Excel/CSV uploads
 */

require_once __DIR__ . '/Model.php';

class CompanyPlacedStudent extends Model {
    protected $table = 'company_placed_students';
    protected $primaryKey = 'sl_no';
    protected $fillable = [
        'name',
        'contact_no',
        'mail_id',
        'usn',
        'yop',
        'qualification',
        'specialisation',
        'company_name',
        'designation',
        'ctc_in_lakhs',
        'gender',
        'college_name'
    ];
    protected $timestamps = false;

    /**
     * Get all placed students
     */
    public function getAllPlacedStudents($orderBy = 'sl_no DESC') {
        return $this->all($orderBy);
    }

    /**
     * Get by company
     */
    public function getByCompany($companyName) {
        return $this->findAll(['company_name' => $companyName]);
    }

    /**
     * Get by institution (college_name)
     */
    public function getByInstitution($collegeName) {
        return $this->findAll(['college_name' => $collegeName]);
    }

    /**
     * Get statistics
     */
    public function getStatistics() {
        $stats = [
            'total_placed' => 0,
            'total_companies' => 0,
            'by_company' => [],
            'by_college' => [],
            'average_ctc' => 0
        ];

        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $stats['total_placed'] = $this->queryOne($sql)['count'] ?? 0;

        $sql = "SELECT COUNT(DISTINCT company_name) as count FROM {$this->table} WHERE company_name IS NOT NULL AND company_name != ''";
        $stats['total_companies'] = $this->queryOne($sql)['count'] ?? 0;

        $sql = "SELECT company_name, COUNT(*) as count FROM {$this->table} WHERE company_name IS NOT NULL AND company_name != '' GROUP BY company_name ORDER BY count DESC LIMIT 10";
        $stats['by_company'] = $this->query($sql);

        $sql = "SELECT college_name, COUNT(*) as count FROM {$this->table} WHERE college_name IS NOT NULL AND college_name != '' GROUP BY college_name ORDER BY count DESC LIMIT 10";
        $stats['by_college'] = $this->query($sql);

        $sql = "SELECT AVG(ctc_in_lakhs) as avg FROM {$this->table} WHERE ctc_in_lakhs > 0";
        $avg = $this->queryOne($sql)['avg'] ?? 0;
        $stats['average_ctc'] = $avg ? round($avg, 2) : 0;

        return $stats;
    }

    /**
     * Bulk insert from array
     */
    public function bulkInsert($dataArray) {
        if (empty($dataArray)) {
            return 0;
        }

        $inserted = 0;
        $this->beginTransaction();

        try {
            foreach ($dataArray as $data) {
                $this->create($data);
                $inserted++;
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }

        return $inserted;
    }

    /**
     * Clear all data
     */
    public function clearAll() {
        $sql = "TRUNCATE TABLE {$this->table}";
        return $this->db->exec($sql);
    }
}