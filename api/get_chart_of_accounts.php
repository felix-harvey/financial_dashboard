<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM chart_of_accounts ORDER BY account_code";
$stmt = $db->prepare($query);
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($accounts);
?>