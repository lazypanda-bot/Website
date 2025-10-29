<?php
// admin/save-design.php
// Helper to insert customization + designoption and return IDs
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../database.php';

function sd_json_err($msg,$code=400){ http_response_code($code); echo json_encode(['status'=>'error','message'=>$msg]); exit; }

/**
 * Save design: insert into customization and designoption
 * @param mysqli $conn
 * @param string|null $color
 * @param string|null $size
 * @param string|null $meta JSON string
 * @param string|null $name
 * @return array ['customization_id'=>int,'designoption_id'=>int]
 */
function save_design_to_db($conn, $color=null, $size=null, $meta=null, $name=null) {
    try {
        $stmt = $conn->prepare('INSERT INTO customization (font_text,font_size,font_color,color,note,created_at) VALUES (?,?,?,?,?,NOW())');
        if (!$stmt) sd_json_err('Prepare failed: '.$conn->error,500);
        $font_text = null; $font_size = null; $font_color = null; $note = $meta ? $meta : null;
        $stmt->bind_param('sssss', $font_text, $font_size, $font_color, $color, $note);
        if (!$stmt->execute()) sd_json_err('Failed to insert customization: '.$stmt->error,500);
        $customization_id = $stmt->insert_id; $stmt->close();

        $req_design = $meta ? $meta : null;
        $stmt2 = $conn->prepare('INSERT INTO designoption (customization_id, designfilepath, request_design, design_status, created_at) VALUES (?,?,?,?,NOW())');
        if (!$stmt2) sd_json_err('Prepare failed: '.$conn->error,500);
        $designfilepath = null; $design_status = 'Requested';
        $stmt2->bind_param('isss', $customization_id, $designfilepath, $req_design, $design_status);
        if (!$stmt2->execute()) sd_json_err('Failed to insert designoption: '.$stmt2->error,500);
        $designoption_id = $stmt2->insert_id; $stmt2->close();

        return ['customization_id'=>$customization_id,'designoption_id'=>$designoption_id];
    } catch (Throwable $e) {
        sd_json_err('Server error: '.$e->getMessage(),500);
    }
}

?>
