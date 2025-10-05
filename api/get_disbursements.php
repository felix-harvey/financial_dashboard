<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$status = $_GET['status'] ?? 'all';

$query = "SELECT * FROM disbursement_requests";
if ($status !== 'all') {
    $query .= " WHERE status = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$status]);
} else {
    $stmt = $db->prepare($query);
    $stmt->execute();
}

$disbursements = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($disbursements);
?>