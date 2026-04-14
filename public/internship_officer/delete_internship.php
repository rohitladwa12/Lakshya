<?php
/**
 * Delete Internship Handler
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole('internship_officer');

$id = get('id');
if ($id) {
    $internshipModel = new Internship();
    // Optional: Delete associated applications or handle foreign keys if needed
    // However, keeping applications for history is usually better. 
    // Maybe just mark as 'Closed' or 'Deleted'?
    // The user asked to "delete", so I will delete the record.
    
    $internshipModel->delete($id);
}

redirect('dashboard.php');
