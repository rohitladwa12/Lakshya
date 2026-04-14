<?php
/**
 * Portfolio AJAX Handler
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();

if (!isPost()) {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

$action = post('action');
$userId = getUserId();
$username = getUsername();
$institution = $_SESSION['institution'] ?? 'GMU';

require_once __DIR__ . '/../../src/Models/Portfolio.php';
$portfolioModel = new Portfolio();

header('Content-Type: application/json');

/**
 * Helper to normalize YYYY-MM to YYYY-MM-01 for DB
 */
function normalizeDate($date) {
    if (empty($date) || $date === 'null') return null;
    if (preg_match('/^\d{4}-\d{2}$/', $date)) {
        return $date . '-01';
    }
    return $date;
}

try {
    switch ($action) {
        case 'add':
            $category = post('category');
            
            // 1. Check for Bulk Addition (JSON encoded)
            $isBulk = post('is_bulk') === 'true';
            if ($isBulk) {
                $items = json_decode(post('items'), true);
                if (!is_array($items)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid bulk data format.']);
                    exit;
                }
                
                $successCount = 0;
                $newIds = [];
                foreach ($items as $item) {
                    if (empty($item['title'])) continue;
                    
                    // Normalize dates for bulk
                    $startDate = normalizeDate($item['start_date'] ?? null);
                    $endDate = normalizeDate($item['end_date'] ?? null);

                    $data = [
                        'student_id' => $username,
                        'institution' => $institution,
                        'category' => $category,
                        'title' => $item['title'],
                        'sub_title' => $item['sub_title'] ?? '',
                        'description' => $item['description'] ?? '',
                        'link' => $item['link'] ?? '',
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ];
                    $id = $portfolioModel->addItem($data);
                    if ($id) {
                        $successCount++;
                        $newIds[] = ['id' => $id, 'title' => $item['title']];
                    }
                }
                echo json_encode([
                    'success' => true, 
                    'message' => "Added $successCount " . strtolower($category) . "s to your portfolio.",
                    'category' => $category,
                    'new_items' => $newIds
                ]);
                exit;
            }

            // Normalize dates for single
            $startDate = normalizeDate(post('start_date'));
            $endDate = normalizeDate(post('end_date'));

            // 3. Standard Single Addition
            $data = [
                'student_id' => $username,
                'institution' => $institution,
                'category' => $category,
                'title' => post('title'),
                'description' => post('description'),
                'link' => post('link'),
                'sub_title' => post('sub_title'),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'attachment_path' => null,
                'attachment_path_2' => null,
                'certificate_attachments' => null
            ];

            // File Upload Handling
            if ($category === 'Personal Intro') {
                $baseDir = __DIR__ . '/../../public/uploads/';
                $photoDir = $baseDir . 'profile photos/';
                $videoDir = $baseDir . 'self intro video/';

                if (!is_dir($photoDir)) mkdir($photoDir, 0777, true);
                if (!is_dir($videoDir)) mkdir($videoDir, 0777, true);

                // Handle Photo (Compulsory)
                if (isset($_FILES['file_upload_photo']) && $_FILES['file_upload_photo']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['file_upload_photo'];
                    $mime = mime_content_type($file['tmp_name']);
                    if (strpos($mime, 'image') === false) {
                        echo json_encode(['success' => false, 'message' => 'Profile Photo must be an image.']);
                        exit;
                    }
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $username . '_intro_photo_' . time() . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $photoDir . $filename)) {
                        $data['attachment_path'] = 'uploads/profile photos/' . $filename;
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Profile Photo is required.']);
                    exit;
                }

                // Handle Video (Compulsory)
                if (isset($_FILES['file_upload_video']) && $_FILES['file_upload_video']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['file_upload_video'];
                    $mime = mime_content_type($file['tmp_name']);
                    if (strpos($mime, 'video') === false) {
                        echo json_encode(['success' => false, 'message' => 'Intro Video must be a video file.']);
                        exit;
                    }
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $username . '_intro_video_' . time() . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $videoDir . $filename)) {
                        $data['attachment_path_2'] = 'uploads/self intro video/' . $filename; // Store video in path 2
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Intro Video is required.']);
                    exit;
                }
            }

            if ($category === 'Certification') {
                $uploadDir = __DIR__ . '/../../public/uploads/certificates/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $paths = [];
                if (isset($_FILES['certificate_files'])) {
                    foreach ($_FILES['certificate_files']['tmp_name'] as $key => $tmpName) {
                        if ($_FILES['certificate_files']['error'][$key] === UPLOAD_ERR_OK) {
                            $name = $_FILES['certificate_files']['name'][$key];
                            $ext = pathinfo($name, PATHINFO_EXTENSION);
                            $filename = $username . '_cert_' . time() . '_' . $key . '.' . $ext;
                            if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                                $paths[] = 'uploads/certificates/' . $filename;
                            }
                        }
                    }
                }

                $newCert = [
                    'id' => uniqid(),
                    'title' => $data['title'],
                    'sub_title' => $data['sub_title'],
                    'description' => $data['description'],
                    'link' => $data['link'],
                    'files' => $paths,
                    'added_at' => date('Y-m-d H:i:s')
                ];

                // Check for existing Certification row
                $existing = array_filter($portfolioModel->getStudentPortfolio($username, $institution), function($item) {
                    return $item['category'] === 'Certification';
                });

                if (!empty($existing)) {
                    $row = reset($existing);
                    $id = $row['id'];
                    $currentCerts = json_decode($row['certificate_attachments'] ?? '[]', true);
                    if (!is_array($currentCerts)) $currentCerts = [];
                    
                    $currentCerts[] = $newCert;
                    $data['certificate_attachments'] = json_encode($currentCerts);
                    $data['title'] = 'My Certifications'; // Set a generic title for the container row
                    
                    if ($portfolioModel->updateItem($id, $data)) {
                        echo json_encode([
                            'success' => true, 
                            'message' => 'Certification added to existing list.',
                            'category' => $category,
                            'new_items' => [['id' => $id, 'title' => $newCert['title']]]
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update certifications.']);
                    }
                    exit;
                } else {
                    // Create new row with first cert in JSON array
                    $data['certificate_attachments'] = json_encode([$newCert]);
                    $data['title'] = 'My Certifications';
                }
            }

            if (empty($data['title']) || empty($data['category'])) {
                echo json_encode(['success' => false, 'message' => 'Title and Category are required.']);
                exit;
            }

            $id = $portfolioModel->addItem($data);
            if ($id) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Item added successfully.',
                    'category' => $category,
                    'new_items' => [['id' => $id, 'title' => $category === 'Certification' ? $newCert['title'] : $data['title']]]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add item.']);
            }
            break;

        case 'delete':
            $id = (int)post('id');
            if ($portfolioModel->deleteItem($id, $username)) {
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete item.']);
            }
            break;

        case 'delete_cert_subitem':
            $rowId = (int)post('row_id');
            $certId = post('cert_id');
            
            // Get the row
            $sql = "SELECT * FROM student_portfolio WHERE id = ? AND student_id = ?";
            $db = $portfolioModel->getDB();
            $stmt = $db->prepare($sql);
            $stmt->execute([$rowId, $username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $certs = json_decode($row['certificate_attachments'] ?? '[]', true);
                if (is_array($certs)) {
                    $newCerts = array_filter($certs, function($c, $idx) use ($certId) {
                        return ($c['id'] ?? $idx) != $certId;
                    }, ARRAY_FILTER_USE_BOTH);
                    
                    // Re-index array
                    $newCerts = array_values($newCerts);
                    
                    if (empty($newCerts)) {
                        // If no certs left, delete the row
                        $portfolioModel->deleteItem($rowId, $username);
                    } else {
                        $row['certificate_attachments'] = json_encode($newCerts);
                        $portfolioModel->updateItem($rowId, $row);
                    }
                    echo json_encode(['success' => true, 'message' => 'Certificate removed.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid data format.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Row not found.']);
            }
            break;

        case 'list':
            $items = $portfolioModel->getStudentPortfolio($username, $institution);
            echo json_encode(['success' => true, 'items' => $items]);
            break;

        case 'sync_skills':
            $skillGroups = json_decode(post('skill_groups'), true);
            if (!is_array($skillGroups)) {
                echo json_encode(['success' => false, 'message' => 'Invalid skill groups data.']);
                exit;
            }
            if ($portfolioModel->syncSkills($username, $institution, $skillGroups)) {
                echo json_encode(['success' => true, 'message' => 'Skills synchronized with profile.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to synchronize skills.']);
            }
            break;

        case 'sync_projects':
            $projects = json_decode(post('projects'), true);
            if (!is_array($projects)) {
                echo json_encode(['success' => false, 'message' => 'Invalid projects data.']);
                exit;
            }
            if ($portfolioModel->syncProjects($username, $institution, $projects)) {
                echo json_encode(['success' => true, 'message' => 'Projects synchronized with profile.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to synchronize projects.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
