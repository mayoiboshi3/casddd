<?php
session_start();
require '../config/db_config.php';

// Security Check: User must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate request is JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            // Update user profile information
            $full_name = sanitize($input['fullname'] ?? '');
            $email = sanitize($input['email'] ?? '');
            $phone_number = sanitize($input['phone_number'] ?? '');
            
            if (empty($full_name) || empty($email)) {
                $response['message'] = 'Full name and email are required.';
                break;
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'Invalid email format.';
                break;
            }
            
            try {
                $stmt = $conn->prepare(
                    "UPDATE users SET full_name = ?, email = ?, phone_number = ?, updated_at = CURRENT_TIMESTAMP 
                     WHERE user_id = ?"
                );
                $stmt->bind_param('sssi', $full_name, $email, $phone_number, $user_id);
                
                if ($stmt->execute()) {
                    // Update session
                    $_SESSION['full_name'] = $full_name;
                    $response['success'] = true;
                    $response['message'] = 'Profile updated successfully!';
                } else {
                    $response['message'] = 'Database error: ' . $stmt->error;
                }
                $stmt->close();
            } catch (Exception $e) {
                $response['message'] = 'Error updating profile: ' . $e->getMessage();
            }
            break;
        
        case 'change_password':
            // Change password
            $current_password = $input['current_password'] ?? '';
            $new_password = $input['new_password'] ?? '';
            $confirm_password = $input['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $response['message'] = 'All password fields are required.';
                break;
            }
            
            if ($new_password !== $confirm_password) {
                $response['message'] = 'New passwords do not match.';
                break;
            }
            
            if (strlen($new_password) < 8) {
                $response['message'] = 'Password must be at least 8 characters long.';
                break;
            }
            
            try {
                // Get current password hash
                $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
                
                // Verify current password
                if (!password_verify($current_password, $user['password'])) {
                    $response['message'] = 'Current password is incorrect.';
                    break;
                }
                
                // Hash new password with bcrypt
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Update password
                $stmt = $conn->prepare(
                    "UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?"
                );
                $stmt->bind_param('si', $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Password changed successfully!';
                } else {
                    $response['message'] = 'Database error: ' . $stmt->error;
                }
                $stmt->close();
            } catch (Exception $e) {
                $response['message'] = 'Error changing password: ' . $e->getMessage();
            }
            break;
        
        case 'upload_photo':
            // Handle profile photo upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                $file_tmp = $_FILES['photo']['tmp_name'];
                $file_size = $_FILES['photo']['size'];
                
                // Validate file size (max 5MB)
                if ($file_size > 5242880) {
                    $response['message'] = 'File size exceeds 5MB limit.';
                    break;
                }
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_type = mime_content_type($file_tmp);
                
                if (!in_array($file_type, $allowed_types)) {
                    $response['message'] = 'Only JPEG, PNG, and GIF files are allowed.';
                    break;
                }
                
                // Read file and convert to binary
                $photo_data = file_get_contents($file_tmp);
                
                try {
                    $stmt = $conn->prepare(
                        "UPDATE users SET profile_photo = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?"
                    );
                    $stmt->send_long_data(0, $photo_data);
                    $stmt->bind_param('bi', $user_id);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Profile photo updated successfully!';
                    } else {
                        $response['message'] = 'Database error: ' . $stmt->error;
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    $response['message'] = 'Error uploading photo: ' . $e->getMessage();
                }
            } else {
                $response['message'] = 'No file uploaded or upload error occurred.';
            }
            break;
        
        default:
            $response['message'] = 'Invalid action.';
    }
} else {
    // GET request: Return current user profile
    try {
        $stmt = $conn->prepare("SELECT user_id, username, full_name, email, phone_number, role FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response['success'] = true;
            $response['data'] = $result->fetch_assoc();
        } else {
            $response['message'] = 'User not found.';
        }
        $stmt->close();
    } catch (Exception $e) {
        $response['message'] = 'Error fetching profile: ' . $e->getMessage();
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Helper function to sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

$conn->close();
?> 