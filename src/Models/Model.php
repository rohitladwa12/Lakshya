<?php
/**
 * Base Model Class
 * Provides common CRUD operations for all models
 */

abstract class Model {
    protected $db;
    protected $connection = 'default'; // Default connection name
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $timestamps = true;
    
    public function __construct() {
        $this->db = getDB($this->connection);
    }

    /**
     * Get the database connection
     */
    public function getDB() {
        return $this->db;
    }
    
    /**
     * Find record by ID
     */
    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Find record by column value
     */
    public function findBy($column, $value) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        return $stmt->fetch();
    }
    
    /**
     * Find all records matching criteria
     */
    public function findAll($where = [], $orderBy = null, $limit = null) {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all records
     */
    public function all($orderBy = null) {
        return $this->findAll([], $orderBy);
    }
    
    /**
     * Create new record
     */
    public function create($data) {
        // Filter only fillable fields
        $data = $this->filterFillable($data);
        
        // Add timestamps
        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Update record
     */
    public function update($id, $data) {
        // Filter only fillable fields
        $data = $this->filterFillable($data);
        
        // Add updated timestamp
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $sets = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . 
               " WHERE {$this->primaryKey} = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Delete record
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Count records
     */
    public function count($where = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return (int)$result['count'];
    }
    
    /**
     * Check if record exists
     */
    public function exists($id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    /**
     * Paginate results
     */
    public function paginate($page = 1, $perPage = ITEMS_PER_PAGE, $where = [], $orderBy = null) {
        $total = $this->count($where);
        $totalPages = ceil($total / $perPage);
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        
        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $total,
                'total_pages' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages
            ]
        ];
    }
    
    /**
     * Execute raw query
     */
    public function query($sql, $params = [], $fetchMode = PDO::FETCH_ASSOC, ...$fetchArgs) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll($fetchMode, ...$fetchArgs);
    }
    
    /**
     * Execute raw query (single result)
     */
    public function queryOne($sql, $params = [], $fetchMode = PDO::FETCH_ASSOC, ...$fetchArgs) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch($fetchMode, ...$fetchArgs);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->db->rollBack();
    }
    
    /**
     * Filter data to only fillable fields
     */
    protected function filterFillable($data) {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Get table name
     */
    public function getTable() {
        return $this->table;
    }
    
    /**
     * Get primary key
     */
    public function getPrimaryKey() {
        return $this->primaryKey;
    }
}
