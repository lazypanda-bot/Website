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
        $customization_id = null;

        // Discover available columns on customization table
        $hasCustomization = false; $custCols = [];
        if ($res = $conn->query('SHOW COLUMNS FROM customization')) {
            $hasCustomization = true;
            while ($r = $res->fetch_assoc()) { $custCols[strtolower($r['Field'])] = $r['Field']; }
            $res->free();
        }

        if ($hasCustomization) {
            // Build dynamic insert only with existing columns
            $cols = []; $place = []; $vals = []; $types = '';
            if (isset($custCols['font_text']))  { $cols[] = $custCols['font_text'];  $place[]='?'; $vals[] = null; $types.='s'; }
            if (isset($custCols['font_size']))  { $cols[] = $custCols['font_size'];  $place[]='?'; $vals[] = null; $types.='s'; }
            if (isset($custCols['font_color'])) { $cols[] = $custCols['font_color']; $place[]='?'; $vals[] = null; $types.='s'; }
            if (isset($custCols['color']))      { $cols[] = $custCols['color'];      $place[]='?'; $vals[] = $color; $types.='s'; }
            // Some databases may not have 'note'; if present, store meta JSON here; otherwise skip
            if (isset($custCols['note']))       { $cols[] = $custCols['note'];       $place[]='?'; $vals[] = $meta ?: null; $types.='s'; }
            // created_at may be auto-default; don't bind it if not needed
            $sql = 'INSERT INTO customization (' . implode(',', $cols) . ') VALUES (' . implode(',', $place) . ')';
            $stmt = $conn->prepare($sql);
            if (!$stmt) sd_json_err('Prepare failed: '.$conn->error,500);
            if ($types !== '') {
                // bind dynamically
                $stmt->bind_param($types, ...$vals);
            }
            if (!$stmt->execute()) sd_json_err('Failed to insert customization: '.$stmt->error,500);
            $customization_id = $stmt->insert_id; $stmt->close();
        }

        // Discover columns on designoption and insert
        $desCols = [];
        if ($res2 = $conn->query('SHOW COLUMNS FROM designoption')) {
            while ($r = $res2->fetch_assoc()) { $desCols[strtolower($r['Field'])] = $r['Field']; }
            $res2->free();
        }
        $cols2 = []; $place2 = []; $vals2 = []; $types2 = '';
        if ($customization_id && isset($desCols['customization_id'])) { $cols2[] = $desCols['customization_id']; $place2[]='?'; $vals2[] = (int)$customization_id; $types2.='i'; }
        if (isset($desCols['designfilepath'])) { $cols2[] = $desCols['designfilepath']; $place2[]='?'; $vals2[] = null; $types2.='s'; }
        if (isset($desCols['request_design'])) { $cols2[] = $desCols['request_design']; $place2[]='?'; $vals2[] = $meta ?: null; $types2.='s'; }
        if (isset($desCols['design_status']))  { $cols2[] = $desCols['design_status'];  $place2[]='?'; $vals2[] = 'Requested'; $types2.='s'; }
        $sql2 = 'INSERT INTO designoption (' . implode(',', $cols2) . ') VALUES (' . implode(',', $place2) . ')';
        $stmt2 = $conn->prepare($sql2);
        if (!$stmt2) sd_json_err('Prepare failed: '.$conn->error,500);
        if ($types2 !== '') { $stmt2->bind_param($types2, ...$vals2); }
        if (!$stmt2->execute()) sd_json_err('Failed to insert designoption: '.$stmt2->error,500);
        $designoption_id = $stmt2->insert_id; $stmt2->close();

        return ['customization_id'=>$customization_id,'designoption_id'=>$designoption_id];
    } catch (Throwable $e) {
        sd_json_err('Server error: '.$e->getMessage(),500);
    }
}

?>
