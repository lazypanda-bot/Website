<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'database.php';
header('Content-Type: application/json'); // send JSON always
// Adaptive cart table/columns detection
if (!defined('CART_TABLE')) {
    define('CART_TABLE', 'cart');
    $cartCols = [];
    if ($res = $conn->query('SHOW COLUMNS FROM ' . CART_TABLE)) {
        while ($r = $res->fetch_assoc()) { $cartCols[strtolower($r['Field'])] = $r['Field']; }
        $res->free();
    }
    $pk = 'id'; foreach(['id','cart_id'] as $c){ if(isset($cartCols[$c])) { $pk = $cartCols[$c]; break; } }
    define('CART_PK_COL', $pk);
    $userFk = 'user_id'; foreach(['customer_id','user_id','account_id'] as $c){ if(isset($cartCols[$c])) { $userFk=$cartCols[$c]; break; } }
    define('CART_USER_FK_COL', $userFk);
    $prodFk = 'product_id'; foreach(['product_id','prod_id','item_id'] as $c){ if(isset($cartCols[$c])) { $prodFk=$cartCols[$c]; break; } }
    define('CART_PRODUCT_FK_COL', $prodFk);
    $qtyCol = 'quantity'; foreach(['quantity','qty','amount'] as $c){ if(isset($cartCols[$c])) { $qtyCol=$cartCols[$c]; break; } }
    define('CART_QTY_COL', $qtyCol);
    $sizeCol = 'size'; foreach(['size','sizes'] as $c){ if(isset($cartCols[$c])) { $sizeCol=$cartCols[$c]; break; } }
    define('CART_SIZE_COL', $sizeCol);
    $colorCol = 'color'; foreach(['color','colour','variant'] as $c){ if(isset($cartCols[$c])) { $colorCol=$cartCols[$c]; break; } }
    define('CART_COLOR_COL', $colorCol);
    // detect design-related column on cart table so we can persist a design preview/path
    $cartDesignCol = null;
    foreach (['designoption_id','design_option_id','design_id','designfilepath','design_file','designpath','design','design_png'] as $c) {
        if (isset($cartCols[$c])) { $cartDesignCol = $cartCols[$c]; break; }
    }
}
session_start();
require_once __DIR__ . '/includes/auth.php';
$user_id = require_valid_user_json();

function json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

$user_id    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$size       = isset($_POST['size']) ? trim($_POST['size']) : '';
$color      = isset($_POST['color']) ? trim($_POST['color']) : '';
$quantity   = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

if ($user_id <= 0) {
    json_error('You must be logged in to add items to the cart.', 401);
}
if ($product_id <= 0) {
    json_error('Invalid or missing product id.');
}
if ($quantity <= 0) {
    $quantity = 1; // normalize
}
// Provide safe defaults
if ($size === '')  { $size = 'Default'; }
if ($color === '') { $color = 'Standard'; }

// Verify product exists (prevents foreign key failure)
// Detect products PK column adaptively and verify existence
$productCols = [];
if ($resPC = $conn->query('SHOW COLUMNS FROM products')) { while($r=$resPC->fetch_assoc()){ $productCols[strtolower($r['Field'])] = $r['Field']; } $resPC->free(); }
$productPk = 'product_id'; foreach(['product_id','id','prod_id','products_id'] as $c){ if(isset($productCols[$c])) { $productPk = $productCols[$c]; break; } }
$sqlCheck = 'SELECT 1 FROM products WHERE ' . $productPk . ' = ? LIMIT 1';
$prodCheck = $conn->prepare($sqlCheck);
if ($prodCheck) {
    $prodCheck->bind_param('i', $product_id);
    $prodCheck->execute();
    $prodCheck->store_result();
    if ($prodCheck->num_rows === 0) {
        $prodCheck->close();
        json_error('Product does not exist (maybe removed).');
    }
    $prodCheck->close();
} else {
    json_error('Server error preparing product validation.');
}

// Read optional design info from the request (if present)
$design = isset($_POST['design']) ? trim($_POST['design']) : '';
$designOptionId = isset($_POST['designoption_id']) ? (int)$_POST['designoption_id'] : 0;

// If this product actually has defined sizes in products_sub, avoid using the
// placeholder 'Default' size by auto-picking the first defined size value.
// Also, later after insert/update, we'll clean up any lingering 'Default' rows
// for this user+product to prevent duplicates like "Size: Default".
$productHasSizes = false;
try {
    if ($resTbl = $conn->query("SHOW TABLES LIKE 'products_sub'")) {
        if ($resTbl->num_rows > 0) {
            $resTbl->free();
            // Detect columns for products_sub
            $psCols = [];
            if ($psC = $conn->query('SHOW COLUMNS FROM products_sub')) {
                while ($r = $psC->fetch_assoc()) { $psCols[strtolower($r['Field'])] = $r['Field']; }
                $psC->free();
            }
            $psProd  = isset($psCols['product_id']) ? $psCols['product_id'] : 'product_id';
            $psKind  = isset($psCols['kind']) ? $psCols['kind'] : 'kind';
            $psValue = isset($psCols['value']) ? $psCols['value'] : 'value';

            // Check if any non-default sizes are defined for this product
            if ($chk = $conn->prepare("SELECT 1 FROM products_sub WHERE $psProd=? AND $psKind IN ('size','sizes') AND TRIM(COALESCE($psValue,''))<>'' AND LOWER($psValue) <> 'default' LIMIT 1")) {
                $chk->bind_param('i', $product_id);
                $chk->execute();
                $chk->store_result();
                $productHasSizes = ($chk->num_rows > 0);
                $chk->close();
            }

            // If product has sizes and current request is using 'Default', pick the first available size
            if ($productHasSizes && ('' === $size || strcasecmp($size, 'Default') === 0)) {
                if ($fs = $conn->prepare("SELECT $psValue FROM products_sub WHERE $psProd=? AND $psKind IN ('size','sizes') AND TRIM(COALESCE($psValue,''))<>'' AND LOWER($psValue) <> 'default' ORDER BY $psValue ASC LIMIT 1")) {
                    $fs->bind_param('i', $product_id);
                    if ($fs->execute()) {
                        $rs = $fs->get_result();
                        if ($row = $rs->fetch_row()) {
                            $size = $row[0];
                        }
                    }
                    $fs->close();
                }
            }
        } else {
            $resTbl->free();
        }
    }
} catch (Throwable $e) {
    // non-fatal; proceed with defaults if products_sub isn't usable
}

// Normalize matching behavior so that empty/null sizes/colors are treated the same as the
// canonical defaults ('Default' for size, 'Standard' for color). This prevents creating
// duplicate cart rows when older rows used empty values while newer requests use the
// explicit default strings.
$matchSql = "SELECT " . CART_PK_COL . ", " . CART_QTY_COL . " FROM " . CART_TABLE . " WHERE " . CART_USER_FK_COL . "=? AND " . CART_PRODUCT_FK_COL . "=? AND (COALESCE(NULLIF(" . CART_SIZE_COL . ",''),'Default') = COALESCE(NULLIF(?,'') ,'Default')) AND (COALESCE(NULLIF(" . CART_COLOR_COL . ",''),'Standard') = COALESCE(NULLIF(?,'') ,'Standard'))";
$match = $conn->prepare($matchSql);
if (!$match) json_error('Failed to prepare cart match query: ' . $conn->error, 500);
$match->bind_param("iiss", $user_id, $product_id, $size, $color);
$match->execute();
$res = $match->get_result();
$existingRows = [];
while ($r = $res->fetch_assoc()) {
    $existingRows[] = [$r[CART_PK_COL], $r[CART_QTY_COL]];
}
$match->close();

try {
    if (count($existingRows) > 0) {
        // If multiple matching rows exist (legacy duplicates), consolidate them by keeping
        // one record and removing others. The Add-to-Cart action is a "replace" operation
        // (not cumulative), so we set the kept row's quantity to the requested value.
        $keeper = $existingRows[0][0];
        // If there are extras, delete them
        if (count($existingRows) > 1) {
            $idsToDelete = array_map(function($r){ return (int)$r[0]; }, $existingRows);
            // remove keeper from delete list
            $idsToDelete = array_values(array_filter($idsToDelete, function($id) use ($keeper){ return $id !== (int)$keeper; }));
            if (count($idsToDelete) > 0) {
                $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
                $delSql = "DELETE FROM " . CART_TABLE . " WHERE " . CART_PK_COL . " IN ($placeholders)";
                $delStmt = $conn->prepare($delSql);
                if ($delStmt) {
                    // bind params dynamically as integers
                    $types = str_repeat('i', count($idsToDelete));
                    $delStmt->bind_param($types, ...$idsToDelete);
                    $delStmt->execute();
                    $delStmt->close();
                }
            }
        }
        // Update keeper quantity (replace mode)
        $newQty = max(1, $quantity);
        if ($cartDesignCol) {
            $upd = $conn->prepare("UPDATE " . CART_TABLE . " SET " . CART_QTY_COL . "=? , " . CART_SIZE_COL . "=?, " . CART_COLOR_COL . "=?, " . $cartDesignCol . "=? WHERE " . CART_PK_COL . "=?");
            if (!$upd) json_error('Failed to prepare update: ' . $conn->error, 500);
            $designBindVal = $designOptionId ? (string)$designOptionId : $design;
            $upd->bind_param("isssi", $newQty, $size, $color, $designBindVal, $keeper);
        } else {
            $upd = $conn->prepare("UPDATE " . CART_TABLE . " SET " . CART_QTY_COL . "=? , " . CART_SIZE_COL . "=?, " . CART_COLOR_COL . "=? WHERE " . CART_PK_COL . "=?");
            if (!$upd) json_error('Failed to prepare update: ' . $conn->error, 500);
            $upd->bind_param("issi", $newQty, $size, $color, $keeper);
        }
        if ($upd->execute()) {
            // If a design was provided, propagate it to other cart rows for the same
            // user+product that currently lack a design value. This prevents the
            // UX issue where one row holds the preview and deleting it removes the
            // preview from other rows that logically should show it.
            $designBindVal = $designOptionId ? (string)$designOptionId : $design;
            if ($cartDesignCol && $designBindVal !== '') {
                $propSql = "UPDATE " . CART_TABLE . " SET " . $cartDesignCol . "=? WHERE " . CART_USER_FK_COL . "=? AND " . CART_PRODUCT_FK_COL . "=? AND (" . $cartDesignCol . " IS NULL OR " . $cartDesignCol . "='')";
                $prop = $conn->prepare($propSql);
                if ($prop) {
                    $prop->bind_param('sii', $designBindVal, $user_id, $product_id);
                    $prop->execute();
                    $prop->close();
                }
            }
            // Cleanup: if this product has defined sizes, remove any lingering 'Default' size
            // rows for the same user+product to avoid duplicate lines like "Size: Default".
            if ($productHasSizes) {
                $delDefaultSql = "DELETE FROM " . CART_TABLE . " WHERE " . CART_USER_FK_COL . "=? AND " . CART_PRODUCT_FK_COL . "=? AND (" . CART_SIZE_COL . " IS NULL OR " . CART_SIZE_COL . "='' OR LOWER(" . CART_SIZE_COL . ")='default') AND " . CART_PK_COL . "<>?";
                if ($dd = $conn->prepare($delDefaultSql)) {
                    $dd->bind_param('iii', $user_id, $product_id, $keeper);
                    $dd->execute();
                    $dd->close();
                }
            }
            echo json_encode(['status'=>'ok','action'=>'replaced','quantity'=>$newQty]);
        } else {
            json_error('DB update error: ' . $upd->error, 500);
        }
        $upd->close();
    } else {
        if ($cartDesignCol) {
            $sql = "INSERT INTO " . CART_TABLE . " (" . CART_USER_FK_COL . ", " . CART_PRODUCT_FK_COL . ", " . CART_SIZE_COL . ", " . CART_COLOR_COL . ", " . CART_QTY_COL . ", " . $cartDesignCol . ") VALUES (?, ?, ?, ?, ?, ?)";
        } else {
            $sql = "INSERT INTO " . CART_TABLE . " (" . CART_USER_FK_COL . ", " . CART_PRODUCT_FK_COL . ", " . CART_SIZE_COL . ", " . CART_COLOR_COL . ", " . CART_QTY_COL . ") VALUES (?, ?, ?, ?, ?)";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) json_error('Failed to prepare insert: ' . $conn->error, 500);
        if ($cartDesignCol) {
            $designBindVal = $designOptionId ? (string)$designOptionId : $design;
            // types: user_id (i), product_id (i), size (s), color (s), quantity (i), design (s)
            $stmt->bind_param("iissis", $user_id, $product_id, $size, $color, $quantity, $designBindVal);
        } else {
            $stmt->bind_param("iissi", $user_id, $product_id, $size, $color, $quantity);
        }
        if ($stmt->execute()) {
            // propagate design to other rows without design, same as above
            $insertId = $stmt->insert_id;
            $designBindVal = $designOptionId ? (string)$designOptionId : $design;
            if ($cartDesignCol) {
                if ($designBindVal !== '') {
                    $propSql = "UPDATE " . CART_TABLE . " SET " . $cartDesignCol . "=? WHERE " . CART_USER_FK_COL . "=? AND " . CART_PRODUCT_FK_COL . "=? AND (" . $cartDesignCol . " IS NULL OR " . $cartDesignCol . "='')";
                    $prop = $conn->prepare($propSql);
                    if ($prop) {
                        $prop->bind_param('sii', $designBindVal, $user_id, $product_id);
                        $prop->execute();
                        $prop->close();
                    }
                } else {
                    // No design provided in this request. If another cart row for this
                    // user+product already has a design, copy that design onto the
                    // newly inserted row so previews remain consistent when one is deleted.
                    $selSql = "SELECT " . $cartDesignCol . " FROM " . CART_TABLE . " WHERE " . CART_USER_FK_COL . "=? AND " . CART_PRODUCT_FK_COL . "=? AND " . $cartDesignCol . " IS NOT NULL AND " . $cartDesignCol . "<>'' LIMIT 1";
                    $sel = $conn->prepare($selSql);
                    if ($sel) {
                        $sel->bind_param('ii', $user_id, $product_id);
                        $sel->execute();
                        $resd = $sel->get_result();
                        if ($dr = $resd->fetch_assoc()) {
                            $existingDesign = $dr[$cartDesignCol];
                            if ($existingDesign) {
                                $updSql = "UPDATE " . CART_TABLE . " SET " . $cartDesignCol . "=? WHERE " . CART_PK_COL . "=?";
                                $upd2 = $conn->prepare($updSql);
                                if ($upd2) {
                                    $upd2->bind_param('si', $existingDesign, $insertId);
                                    $upd2->execute();
                                    $upd2->close();
                                }
                            }
                        }
                        $sel->close();
                    }
                }
            }
            // Cleanup: if this product has defined sizes, remove any lingering 'Default' size
            // rows for the same user+product to avoid duplicate lines like "Size: Default".
            if ($productHasSizes) {
                $delDefaultSql = "DELETE FROM " . CART_TABLE . " WHERE " . CART_USER_FK_COL . "=? AND " . CART_PRODUCT_FK_COL . "=? AND (" . CART_SIZE_COL . " IS NULL OR " . CART_SIZE_COL . "='' OR LOWER(" . CART_SIZE_COL . ")='default') AND " . CART_PK_COL . "<>?";
                if ($dd = $conn->prepare($delDefaultSql)) {
                    $dd->bind_param('iii', $user_id, $product_id, $insertId);
                    $dd->execute();
                    $dd->close();
                }
            }
            echo json_encode(['status'=>'ok','action'=>'inserted','id'=>$insertId,'quantity'=>$quantity]);
        } else {
            json_error('DB insert error: ' . $stmt->error, 500);
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    json_error('Unexpected server error: ' . $e->getMessage(), 500);
}
$conn->close();
?>