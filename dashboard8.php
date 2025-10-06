<?php
session_start();
require_once 'database.php';

$database = new Database();
$db = $database->getConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Get user info
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: login.php");
    exit;
}



// Get disbursement data
function getDisbursementRequests($db) {
    $query = "SELECT dr.*, u.name as requested_by_name 
              FROM disbursement_requests dr 
              LEFT JOIN users u ON dr.requested_by = u.id 
              ORDER BY dr.date_requested DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPendingDisbursements($db) {
    $query = "SELECT dr.*, u.name as requested_by_name 
              FROM disbursement_requests dr 
              LEFT JOIN users u ON dr.requested_by = u.id 
              WHERE dr.status = 'Pending' 
              ORDER BY dr.date_requested DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getApprovedDisbursements($db) {
    $query = "SELECT dr.*, u.name as requested_by_name, u2.name as approved_by_name 
              FROM disbursement_requests dr 
              LEFT JOIN users u ON dr.requested_by = u.id 
              LEFT JOIN users u2 ON dr.approved_by = u2.id 
              WHERE dr.status = 'Approved' 
              ORDER BY dr.date_approved DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRejectedDisbursements($db) {
    $query = "SELECT dr.*, u.name as requested_by_name, u2.name as approved_by_name 
              FROM disbursement_requests dr 
              LEFT JOIN users u ON dr.requested_by = u.id 
              LEFT JOIN users u2 ON dr.approved_by = u2.id 
              WHERE dr.status = 'Rejected' 
              ORDER BY dr.date_approved DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get dashboard stats and other data
function getDashboardStats($db) {
    $stats = [];
    
    // Total Income (Revenue accounts)
    $query = "SELECT COALESCE(SUM(balance), 0) as total FROM chart_of_accounts WHERE account_type = 'Revenue'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_income'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total Expenses (Expense accounts)
    $query = "SELECT COALESCE(SUM(balance), 0) as total FROM chart_of_accounts WHERE account_type = 'Expense'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_expenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Cash Flow (Income - Expenses)
    $stats['cash_flow'] = $stats['total_income'] - $stats['total_expenses'];
    
    // Upcoming Payments
    $query = "SELECT COALESCE(SUM(amount), 0) as total FROM invoices WHERE due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'Pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['upcoming_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    return $stats;
}

$dashboard_stats = getDashboardStats($db);

// Get recent transactions
function getRecentTransactions($db) {
    $query = "SELECT 'Disbursement' as type, request_id as id, description as name, date_requested as date, amount, status 
              FROM disbursement_requests 
              UNION ALL 
              SELECT 'Payment' as type, payment_id as id, 'Payment received' as name, payment_date as date, amount, status 
              FROM payments 
              WHERE type = 'Receive'
              ORDER BY date DESC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$recent_transactions = getRecentTransactions($db);

// Get notifications
function getNotifications($db, $user_id) {
    $query = "SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$notifications = getNotifications($db, $user_id);

// Get disbursement data
$disbursement_requests = getDisbursementRequests($db);
$pending_disbursements = getPendingDisbursements($db);
$approved_disbursements = getApprovedDisbursements($db);
$rejected_disbursements = getRejectedDisbursements($db);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-green': '#28644c',
                        'sidebar-green': '#2f855A',
                        'white': '#ffffff',
                        'gray-bg': '#f3f4f6',
                        'notification-red': '#ef4444',
                        'hover-state': 'rgba(255, 255, 255, 0.3)',
                        'dark-text': '#1f2937',
                    }
                }
            }
        }
    </script>
    <style>
        /* Previous styles remain the same */
        .hamburger-line {
            width: 24px;
            height: 3px;
            background-color: #FFFFFF;
            margin: 4px 0;
            transition: all 0.3s;
        }
        .sidebar-item.active {
            background-color: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
        }
        .sidebar-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .card-shadow {
            box-shadow: 0px 2px 6px rgba(0,0,0,0.08);
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 9999px;
        }
        .status-completed {
            background-color: rgba(104, 211, 145, 0.1);
            color: #68D391;
        }
        .status-pending {
            background-color: rgba(229, 62, 62, 0.1);
            color: #E53E3E;
        }
        #sidebar {
            transition: transform 0.3s ease-in-out;
            background-color: #2f855A;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Always show hamburger button */
        #hamburger-btn {
            display: block !important;
        }
        
        @media (max-width: 768px) {
            #sidebar {
                transform: translateX(-100%);
                position: fixed;
                height: 100%;
                z-index: 40;
                min-height: 100vh;
            }
            #sidebar.active {
                transform: translateX(0);
            }
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 30;
            }
            .overlay.active {
                display: block;
            }
        }
        
        /* Desktop view with toggleable sidebar */
        @media (min-width: 769px) {
            #sidebar {
                transform: translateX(0);
            }
            #sidebar.hidden {
                transform: translateX(-100%);
                position: fixed;
            }
            .overlay {
                display: none;
            }
            #main-content.full-width {
                margin-left: 0;
                width: 100%;
            }
        }
        
        /* Make sidebar full height with color */
        .sidebar-container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #2f855A;
        }
        
        /* Footer styling */
        .main-footer {
            background-color: #28644c;
            color: white;
            padding: 1.5rem;
            margin-top: auto;
        }

        /* NEW: Ensure body and html take full height */
        html, body {
            height: 100%;
        }
        
        /* NEW: Flex container for the entire page */
        .page-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* NEW: Make sidebar content stretch properly */
        .sidebar-content {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        
        /* NEW: Submenu styling */
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .submenu.active {
            max-height: 500px;
        }
        
        .submenu-item {
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
        }
        
        .submenu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .submenu-item.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
        }
        
        .rotate-180 {
            transform: rotate(180deg);
        }
        
        /* Loading spinner */
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2f855A;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
        }
        
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* Form styling */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #2f855A;
            box-shadow: 0 0 0 3px rgba(47, 133, 90, 0.2);
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: #2f855A;
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: #28644c;
        }
        
        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
            border: none;
        }
        
        .btn-secondary:hover {
            background-color: #d1d5db;
        }
        
        /* NEW: Tab styling */
        .tab-container {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 1rem;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        
        .tab.active {
            border-bottom-color: #2f855A;
            color: #2f855A;
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* NEW: Table styling */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .data-table th {
            background-color: #f9fafb;
            font-weight: 500;
            color: #374151;
        }
        
        /* NEW: Status badges */
        .status-approved {
            background-color: rgba(104, 211, 145, 0.1);
            color: #68D391;
        }
        
        .status-rejected {
            background-color: rgba(229, 62, 62, 0.1);
            color: #E53E3E;
        }
        
        .status-pending {
            background-color: rgba(251, 191, 36, 0.1);
            color: #F59E0B;
        }
        
        .status-overdue {
            background-color: rgba(239, 68, 68, 0.1);
            color: #EF4444;
        }
        
        /* NEW: Action buttons */
        .action-btn {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            margin-right: 0.25rem;
            cursor: pointer;
        }
        
        .action-btn.view {
            background-color: #EFF6FF;
            color: #3B82F6;
            border: 1px solid #3B82F6;
        }
        
        .action-btn.edit {
            background-color: #F0FDF4;
            color: #10B981;
            border: 1px solid #10B981;
        }
        
        .action-btn.delete {
            background-color: #FEF2F2;
            color: #EF4444;
            border: 1px solid #EF4444;
        }
        
        .action-btn.approve {
            background-color: #F0FDF4;
            color: #10B981;
            border: 1px solid #10B981;
        }
        
        .action-btn.reject {
            background-color: #FEF2F2;
            color: #EF4444;
            border: 1px solid #EF4444;
        }
    </style>
</head>
<body class="bg-gray-bg">
    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Modal for notifications -->
    <div id="notification-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 class="text-xl font-bold mb-4">Notifications</h2>
            <div id="notification-list">
                <!-- Notifications will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Modal for user profile -->
    <div id="profile-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 class="text-xl font-bold mb-4">User Profile</h2>
            <div class="flex items-center mb-6">
                <i class="fa-solid fa-user text-[40px] bg-primary-green text-white px-3 py-3 rounded-full"></i>
                <div class="ml-4">
                    <h3 class="text-lg font-bold" id="profile-name"><?php echo htmlspecialchars($user['name']); ?></h3>
                    <p class="text-gray-500"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <h4 class="font-medium mb-2">Account Settings</h4>
                    <button class="btn btn-secondary w-full mb-2">Edit Profile</button>
                    <button class="btn btn-secondary w-full mb-2">Change Password</button>
                </div>
                <div>
                    <h4 class="font-medium mb-2">System</h4>
                    <button class="btn btn-secondary w-full mb-2">Preferences</button>
                    <button class="btn btn-secondary w-full" id="logout-btn">Logout</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal for Create Disbursement -->
<div id="create-disbursement-modal" class="modal">
  <div class="modal-content">
    <span class="close-modal">&times;</span>
    <h2 class="text-xl font-bold mb-4">Create Disbursement Request</h2>

    <form id="disbursement-form" method="POST">
      <input type="hidden" name="action" value="create_disbursement">

      <div class="form-group">
        <label class="form-label">Requested By</label>
        <input type="text" name="requested_by" class="form-input" placeholder="Enter requestor name" required>
      </div>

      <div class="form-group">
        <label class="form-label">Department</label>
        <select name="department" class="form-input" required>
          <option value="">Select Department</option>
          <option value="Marketing">Marketing</option>
          <option value="Operations">Operations</option>
          <option value="IT">IT</option>
          <option value="HR">HR</option>
          <option value="Finance">Finance</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-input" rows="3" placeholder="Enter description" required></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Amount</label>
        <input type="number" name="amount" class="form-input" placeholder="Enter amount" step="0.01" min="0" required>
      </div>

      <div class="flex space-x-4 mt-6">
        <button type="button" class="btn btn-secondary flex-1 close-modal">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">Submit Request</button>
      </div>
    </form>
  </div>
</div>

    
    <!-- Page Container -->
    <div class="page-container">
        <!-- Sidebar -->
        <div id="sidebar" class="w-64 flex flex-col fixed md:relative">
            <div class="sidebar-content">
                <div class="p-6 bg-sidebar-green">
                    <div class="flex justify-between items-center">
                        <h1 class="text-xl font-bold text-white flex items-center">
                            <i class='bx bx-wallet-alt text-white mr-2'></i>
                            Dashboard
                        </h1>
                        <button id="close-sidebar" class="text-white">
                            <i class='bx bx-x text-2xl'></i>
                        </button>
                    </div>
                    <p class="text-xs text-white/90 mt-1">Microfinancial Management System 1</p>
                </div>
                
                <!-- Navigation -->
                <div class="flex-1 overflow-y-auto px-2 py-4">
                    <div class="space-y-6">
                        <!-- Main Menu Item -->
                        <div class="sidebar-item py-3 px-4 rounded-lg cursor-pointer mx-2" data-page="financial">
                            <div class="flex items-center">
                                <i class='bx bx-home text-white mr-3'></i>
                                <span class="text-sm font-medium text-white">FINANCIAL</span>
                            </div>
                        </div>
                        
                        <!-- Disbursement Section -->
                        <div class="py-2 mx-2">
                            <div class="flex items-center justify-between mb-1 sidebar-category py-2 px-3 rounded cursor-pointer hover:bg-hover-state" data-category="disbursement">
                                <h3 class="text-xs font-semibold text-white uppercase tracking-wider">Disbursement</h3>
                                <i class='bx bx-chevron-down text-white text-sm category-arrow' data-category="disbursement"></i>
                            </div>
                            <div class="submenu" id="disbursement-submenu">
                                <div class="submenu-item" data-page="disbursement-request">Disbursement Request</div>
                                <div class="submenu-item" data-page="pending-disbursements">Pending Disbursements</div>
                                <div class="submenu-item" data-page="approved-disbursements">Approved Disbursements</div>
                                <div class="submenu-item" data-page="rejected-disbursements">Rejected Disbursements</div>
                                <div class="submenu-item" data-page="disbursement-reports">Disbursement Reports</div>
                            </div>
                        </div>
                        
                        <!-- General Ledger Section -->
                        <div class="py-2 mx-2">
                            <div class="flex items-center justify-between mb-1 sidebar-category py-2 px-3 rounded cursor-pointer hover:bg-hover-state" data-category="ledger">
                                <h3 class="text-xs font-semibold text-white uppercase tracking-wider">General Ledger</h3>
                                <i class='bx bx-chevron-down text-white text-sm category-arrow' data-category="ledger"></i>
                            </div>
                            <div class="submenu" id="ledger-submenu">
                                <div class="submenu-item" data-page="chart-of-accounts">Chart of Accounts</div>
                                <div class="submenu-item" data-page="journal-entry">Journal Entry</div>
                                <div class="submenu-item" data-page="ledger-table">Ledger Table</div>
                                <div class="submenu-item" data-page="financial-reports">Financial Reports</div>
                            </div>
                        </div>
                        
                        <!-- AP/AR Section -->
                        <div class="py-2 mx-2">
                            <div class="flex items-center justify-between mb-1 sidebar-category py-2 px-3 rounded cursor-pointer hover:bg-hover-state" data-category="ap-ar">
                                <h3 class="text-xs font-semibold text-white uppercase tracking-wider">AP/AR</h3>
                                <i class='bx bx-chevron-down text-white text-sm category-arrow' data-category="ap-ar"></i>
                            </div>
                            <div class="submenu" id="ap-ar-submenu">
                                <div class="submenu-item" data-page="vendors-customers">Vendors/Customers</div>
                                <div class="submenu-item" data-page="invoices">Invoices</div>
                                <div class="submenu-item" data-page="payment-entry">Payment Entry</div>
                                <div class="submenu-item" data-page="aging-reports">Aging Reports</div>
                            </div>
                        </div>
                        
                        <!-- Collection Section -->
                        <div class="py-2 mx-2">
                            <div class="flex items-center justify-between mb-1 sidebar-category py-2 px-3 rounded cursor-pointer hover:bg-hover-state" data-category="collection">
                                <h3 class="text-xs font-semibold text-white uppercase tracking-wider">Collection</h3>
                                <i class='bx bx-chevron-down text-white text-sm category-arrow' data-category="collection"></i>
                            </div>
                            <div class="submenu" id="collection-submenu">
                                <div class="submenu-item" data-page="payment-entry-collection">Payment Entry</div>
                                <div class="submenu-item" data-page="receipt-generation">Receipt Generation</div>
                                <div class="submenu-item" data-page="collection-dashboard">Collection Dashboard</div>
                                <div class="submenu-item" data-page="outstanding-balances">Outstanding Balances</div>
                                <div class="submenu-item" data-page="collection-reports">Collection Reports</div>
                            </div>
                        </div>
                        
                        <!-- Budget Section -->
                        <div class="py-2 mx-2">
                            <div class="flex items-center justify-between mb-1 sidebar-category py-2 px-3 rounded cursor-pointer hover:bg-hover-state" data-category="budget">
                                <h3 class="text-xs font-semibold text-white uppercase tracking-wider">Budget Management</h3>
                                <i class='bx bx-chevron-down text-white text-sm category-arrow' data-category="budget"></i>
                            </div>
                            <div class="submenu" id="budget-submenu">
                                <div class="submenu-item" data-page="budget-proposal">Budget Proposal</div>
                                <div class="submenu-item" data-page="approval-workflow">Approval Workflow</div>
                                <div class="submenu-item" data-page="budget-vs-actual">Budget vs Actual</div>
                                <div class="submenu-item" data-page="budget-reports">Budget Reports</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer inside sidebar with same color -->
                <div class="p-4 text-center text-xs text-white/80 border-t border-white/10 mt-auto">
                    <p>© 2025 Financial Dashboard. All rights reserved.</p>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div id="main-content" class="flex-1 overflow-y-auto flex flex-col">
            <!-- Header -->
            <div class="bg-primary-green text-white p-4 flex justify-between items-center">
                <div class="flex items-center">
                    <button id="hamburger-btn" class="mr-4">
                        <div class="hamburger-line"></div>
                        <div class="hamburger-line"></div>
                        <div class="hamburger-line"></div>
                    </button>
                    <div>
                        <h1 class="text-2xl font-bold text-white">Financial With Predictive Budgeting And Cash Flow Forecasting Using Time Series Analysis</h1>
                        <p class="text-sm text-white/90">Welcome back, here's your financial overview</p>
                    </div>
                </div>
                <div class="flex items-center space-x-1">
                    <button id="notification-btn" class="relative p-2 transition duration-200 focus:outline-none">
                        <i class="fa-solid fa-bell text-xl text-white"></i>
                    </button>
                    <div id="profile-btn" class="flex items-center space-x-2 cursor-pointer px-3 py-2 transition duration-200">
                        <i class="fa-solid fa-user text-[18px] bg-white text-primary-green px-2.5 py-2 rounded-full"></i>
                        <span class="text-white font-medium"><?php echo htmlspecialchars($user['name']); ?></span>
                        <i class="fa-solid fa-chevron-down text-sm text-white"></i>
                    </div>
                </div>
            </div>
            
            <div class="p-6 flex-1">
                <!-- Dashboard Content -->
                <section id="dashboard-content" class="space-y-6 mb-8">
                    <!-- Stats Overview -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-xl p-6 card-shadow">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 mr-4">
                                    <i class='bx bx-money text-green-600 text-2xl'></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Total Income</p>
                                    <p class="text-2xl font-bold text-dark-text stat-value">₱<?php echo number_format($dashboard_stats['total_income'], 2); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl p-6 card-shadow">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-red-100 mr-4">
                                    <i class='bx bx-credit-card text-red-600 text-2xl'></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Total Expenses</p>
                                    <p class="text-2xl font-bold text-dark-text stat-value">₱<?php echo number_format($dashboard_stats['total_expenses'], 2); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl p-6 card-shadow">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-yellow-100 mr-4">
                                    <i class='bx bx-wallet text-yellow-600 text-2xl'></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Cash Flow</p>
                                    <p class="text-2xl font-bold text-dark-text stat-value">₱<?php echo number_format($dashboard_stats['cash_flow'], 2); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl p-6 card-shadow">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100 mr-4">
                                    <i class='bx bx-calendar text-blue-600 text-2xl'></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Upcoming Payments</p>
                                    <p class="text-2xl font-bold text-dark-text stat-value">₱<?php echo number_format($dashboard_stats['upcoming_payments'], 2); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- Income vs Expenses Chart -->
                        <div class="bg-white rounded-xl p-6 card-shadow">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-dark-text">Income vs Expenses</h3>
                                <div class="flex items-center space-x-2">
                                    <span class="flex items-center">
                                        <span class="w-3 h-3 rounded-full bg-primary-green mr-2"></span>
                                        <span class="text-xs text-gray-500">Income</span>
                                    </span>
                                    <span class="flex items-center">
                                        <span class="w-3 h-3 rounded-full bg-green-600 mr-2"></span>
                                        <span class="text-xs text-gray-500">Expenses</span>
                                    </span>
                                </div>
                            </div>
                            <div class="h-80">
                                <canvas id="incomeExpenseChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Budget Distribution Chart -->
                        <div class="bg-white rounded-xl p-6 card-shadow">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-dark-text">Budget Distribution</h3>
                                <button id="refresh-budget-chart" class="text-xs text-green-600 flex items-center hover:opacity-85">
                                    <i class='bx bx-refresh text-xl'></i>
                                </button>
                            </div>
                            <div class="h-80">
                                <canvas id="budgetChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Transactions -->
                    <div class="bg-white rounded-xl p-6 card-shadow mb-8">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold text-dark-text">Recent Transactions</h3>
                            <button id="view-all-transactions" class="text-xs text-green-600 flex items-center hover:opacity-85">
                                View all <i class='bx bx-chevron-right text-xl'></i>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left text-sm text-gray-500 border-b border-gray-200">
                                        <th class="pb-3">Transaction</th>
                                        <th class="pb-3">Date</th>
                                        <th class="pb-3">Amount</th>
                                        <th class="pb-3">Status</th>
                                        <th class="pb-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="transactions-table-body">
                                    <!-- Transaction rows will be dynamically loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Upcoming Payments -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Upcoming Due Dates -->
                        <div class="bg-white rounded-xl p-6 card-shadow">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-dark-text">Upcoming Due Dates</h3>
                                <button id="view-all-due-dates" class="text-xs text-green-600 flex items-center hover:opacity-85">
                                    View all <i class='bx bx-chevron-right text-xl'></i>
                                </button>
                            </div>
                            <div class="space-y-4" id="due-dates-list">
                                <!-- Dynamic due dates will appear here -->
                            </div>
                        </div>
                        
                        <!-- Recent Notifications -->
                        <div class="bg-white rounded-xl p-6 card-shadow">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-dark-text">Recent Notifications</h3>
                                <button id="view-all-notifications" class="text-xs text-green-600 flex items-center hover:opacity-85">
                                    View all <i class='bx bx-chevron-right text-xl'></i>
                                </button>
                            </div>
                            <div class="space-y-4" id="notifications-list">
                                <!-- Dynamic notifications will appear here -->
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Page Content Container (for other pages) -->
                <div id="page-content" class="hidden">
                    <!-- Content for other pages will be loaded here -->
                </div>
            </div>
            
            <!-- Footer at the bottom of main content with dark green color -->
            <footer class="main-footer">
                <div class="text-center">
                    <p class="text-sm">© 2025 Financial Dashboard. All rights reserved.</p>
                    <p class="text-xs mt-1 opacity-80">Powered by Microfinancial Management System</p>
                </div>
            </footer>
        </div>
    </div>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Income vs Expenses Chart
            const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
            const incomeExpenseChart = new Chart(incomeExpenseCtx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
                    datasets: [
                        {
                            label: 'Income',
                            data: [120000, 135000, 110000, 140000, 125000, 130000, 150000, 145000],
                            backgroundColor: '#2F855A',
                            borderRadius: 6,
                        },
                        {
                            label: 'Expenses',
                            data: [80000, 90000, 85000, 95000, 100000, 105000, 110000, 100000],
                            backgroundColor: '#88BE3C',
                            borderRadius: 6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false,
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + (value/1000).toFixed(0) + 'K';
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Budget Distribution Chart
            // Function to initialize the budget chart
            function initBudgetChart() {
                const budgetCtx = document.getElementById('budgetChart').getContext('2d');
                return new Chart(budgetCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Operations', 'Marketing', 'Salaries', 'Rent', 'IT'],
                        datasets: [{
                            data: [30, 20, 25, 15, 10],
                            backgroundColor: ['#2F855A','#88BE3C','#68D391','#3182CE','#E53E3E'],
                            borderWidth: 0,
                            hoverOffset: 12
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    padding: 15,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            }
                        }
                    }
                });
            }

            // Initialize chart on page load
            let budgetChart = initBudgetChart();

            // Refresh button functionality
            document.getElementById('refresh-budget-chart').addEventListener('click', function() {
                this.innerHTML = '<div class="spinner"></div>';

                setTimeout(() => {
                    const newData = [
                        Math.floor(Math.random() * 30) + 10,
                        Math.floor(Math.random() * 30) + 10,
                        Math.floor(Math.random() * 30) + 10,
                        Math.floor(Math.random() * 30) + 10,
                        Math.floor(Math.random() * 30) + 10
                    ];
                    budgetChart.data.datasets[0].data = newData;
                    budgetChart.update();
                    this.innerHTML = '<i class="bx bx-refresh text-xl"></i>';
                }, 1000);
            });

            // Hamburger menu functionality
            const hamburgerBtn = document.getElementById('hamburger-btn');
            const closeSidebar = document.getElementById('close-sidebar');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const mainContent = document.getElementById('main-content');

            // Make sure elements exist before adding event listeners
            if (hamburgerBtn && sidebar && overlay && closeSidebar && mainContent) {
                // Function to check screen size and toggle sidebar accordingly
                function toggleSidebar() {
                    if (window.innerWidth < 769) {
                        // Mobile behavior
                        sidebar.classList.toggle('active');
                        overlay.classList.toggle('active');
                    } else {
                        // Desktop behavior
                        sidebar.classList.toggle('hidden');
                        mainContent.classList.toggle('full-width');
                    }
                }

                hamburgerBtn.addEventListener('click', toggleSidebar);

                closeSidebar.addEventListener('click', function() {
                    if (window.innerWidth < 769) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    } else {
                        sidebar.classList.add('hidden');
                        mainContent.classList.add('full-width');
                    }
                });

                overlay.addEventListener('click', function() {
                    if (window.innerWidth < 769) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    }
                });
                
                // Handle window resize
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 769) {
                        // On desktop, ensure overlay is hidden
                        overlay.classList.remove('active');
                    }
                });
            } else {
                console.error('One or more sidebar elements not found');
            }
            
            // Sidebar submenu functionality
            const categoryToggles = document.querySelectorAll('.sidebar-category');
            
            categoryToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const category = this.getAttribute('data-category');
                    const submenu = document.getElementById(`${category}-submenu`);
                    const arrow = document.querySelector(`.category-arrow[data-category="${category}"]`);
                    
                    // Toggle the active class on the submenu
                    submenu.classList.toggle('active');
                    
                    // Rotate the arrow
                    arrow.classList.toggle('rotate-180');
                });
            });
            
            // Make submenu items clickable
            const submenuItems = document.querySelectorAll('.submenu-item');
            const sidebarItems = document.querySelectorAll('.sidebar-item');
            const dashboardContent = document.getElementById('dashboard-content');
            const pageContent = document.getElementById('page-content');
            
            // Function to load page content
            function loadPageContent(pageId) {
                // Show loading state
                pageContent.innerHTML = `<div class="flex justify-center items-center h-64"><div class="spinner"></div>Loading ${pageId.replace('-', ' ')}...</div>`;
                pageContent.classList.remove('hidden');
                dashboardContent.classList.add('hidden');

                // Simulate API call delay
                setTimeout(() => {
                    // Load the appropriate content based on pageId
                    let content = '';
                    
                    switch(pageId) {
                        case 'disbursement-request':
                            content = getDisbursementRequestContent();
                            break;
                        case 'pending-disbursements':
                            content = getPendingDisbursementsContent();
                            break;
                        case 'approved-disbursements':
                            content = getApprovedDisbursementsContent();
                            break;
                        case 'rejected-disbursements':
                            content = getRejectedDisbursementsContent();
                            break;
                        case 'disbursement-reports':
                            content = getDisbursementReportsContent();
                            break;
                        case 'chart-of-accounts':
                            content = getChartOfAccountsContent();
                            break;
                        case 'journal-entry':
                            content = getJournalEntryContent();
                            break;
                        case 'ledger-table':
                            content = getLedgerTableContent();
                            break;
                        case 'financial-reports':
                            content = getFinancialReportsContent();
                            break;
                        case 'vendors-customers':
                            content = getVendorsCustomersContent();
                            break;
                        case 'invoices':
                            content = getInvoicesContent();
                            break;
                        case 'payment-entry':
                            content = getPaymentEntryContent();
                            break;
                        case 'aging-reports':
                            content = getAgingReportsContent();
                            break;
                        case 'payment-entry-collection':
                            content = getPaymentEntryCollectionContent();
                            break;
                        case 'receipt-generation':
                            content = getReceiptGenerationContent();
                            break;
                        case 'collection-dashboard':
                            content = getCollectionDashboardContent();
                            break;
                        case 'outstanding-balances':
                            content = getOutstandingBalancesContent();
                            break;
                        case 'collection-reports':
                            content = getCollectionReportsContent();
                            break;
                        case 'budget-proposal':
                            content = getBudgetProposalContent();
                            break;
                        case 'approval-workflow':
                            content = getApprovalWorkflowContent();
                            break;
                        case 'budget-vs-actual':
                            content = getBudgetVsActualContent();
                            break;
                        case 'budget-reports':
                            content = getBudgetReportsContent();
                            break;
                        default:
                            content = getDefaultPageContent(pageId);
                    }
                    
                    pageContent.innerHTML = content;
                    
                    // Initialize any interactive elements for the loaded page
                    initializePageSpecificScripts(pageId);
                }, 800);
            }
            
            // Function to go back to dashboard
            function goToDashboard() {
                pageContent.classList.add('hidden');
                dashboardContent.classList.remove('hidden');
                
                // Reset active states
                submenuItems.forEach(item => item.classList.remove('active'));
                sidebarItems.forEach(item => item.classList.remove('active'));
            }
            
            // Add click event listeners to sidebar items
            sidebarItems.forEach(item => {
                item.addEventListener('click', function() {
                    const pageId = this.getAttribute('data-page');
                    
                    // Remove active class from all items
                    sidebarItems.forEach(i => i.classList.remove('active'));
                    submenuItems.forEach(i => i.classList.remove('active'));
                    
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    if (pageId === 'financial') {
                        goToDashboard();
                    } else {
                        loadPageContent(pageId);
                    }
                });
            });
            
            // Add click event listeners to submenu items
            submenuItems.forEach(item => {
                item.addEventListener('click', function() {
                    const pageId = this.getAttribute('data-page');
                    
                    // Remove active class from all items
                    submenuItems.forEach(i => i.classList.remove('active'));
                    sidebarItems.forEach(i => i.classList.remove('active'));
                    
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    loadPageContent(pageId);
                    
                    // On mobile, close sidebar after selection
                    if (window.innerWidth < 769) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    }
                });
            });
            
            // Modal functionality
            const notificationBtn = document.getElementById('notification-btn');
            const profileBtn = document.getElementById('profile-btn');
            const notificationModal = document.getElementById('notification-modal');
            const profileModal = document.getElementById('profile-modal');
            const createDisbursementModal = document.getElementById('create-disbursement-modal');
            const closeButtons = document.querySelectorAll('.close-modal');
            
            if (notificationBtn && notificationModal) {
                notificationBtn.addEventListener('click', function() {
                    notificationModal.style.display = 'block';
                    loadNotifications();
                });
            }
            
            if (profileBtn && profileModal) {
                profileBtn.addEventListener('click', function() {
                    profileModal.style.display = 'block';
                });
            }
            
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    notificationModal.style.display = 'none';
                    profileModal.style.display = 'none';
                    createDisbursementModal.style.display = 'none';
                });
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === notificationModal) {
                    notificationModal.style.display = 'none';
                }
                if (event.target === profileModal) {
                    profileModal.style.display = 'none';
                }
                if (event.target === createDisbursementModal) {
                    createDisbursementModal.style.display = 'none';
                }
            });
            
            // FIXED: Logout button functionality
            const logoutBtn = document.getElementById('logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to logout?')) {
                        window.location.href = '?logout=true';
                    }
                });
            }
            
            // FIXED: Create Disbursement functionality
            document.addEventListener('click', function(e) {
                // Handle "New Request" button
                const button = e.target.closest('button');
                if (button && button.textContent.includes('New Request')) {
                    document.getElementById('create-disbursement-modal').style.display = 'block';
                }
                
                // Handle disbursement action buttons
                if (e.target.closest('.action-btn.approve')) {
                    if (confirm('Are you sure you want to approve this disbursement?')) {
                        const row = e.target.closest('tr');
                        const requestId = row.querySelector('td:first-child').textContent;
                        // You would need to implement AJAX call here or redirect to approval page
                        alert('Approval functionality would be implemented here for: ' + requestId);
                    }
                }
                
                if (e.target.closest('.action-btn.reject')) {
                    if (confirm('Are you sure you want to reject this disbursement?')) {
                        const row = e.target.closest('tr');
                        const requestId = row.querySelector('td:first-child').textContent;
                        // You would need to implement AJAX call here or redirect to rejection page
                        alert('Rejection functionality would be implemented here for: ' + requestId);
                    }
                }
            });
            
            // Handle disbursement form submission
            const disbursementForm = document.getElementById('disbursement-form');
            if (disbursementForm) {
                disbursementForm.addEventListener('submit', function(e) {
                    // Show loading state
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<div class="spinner"></div>Submitting...';
                    submitBtn.disabled = true;
                    
                    // Form will submit normally via PHP
                });
            }
            
            // Load initial data
            loadStatsData();
            loadTransactions();
            loadDueDates();
            loadNotifications();
        });
        
        // Function to load stats data from PHP
        function loadStatsData() {
            // Stats are now displayed directly in PHP in the HTML
            // This function is kept for any dynamic updates that might be needed
            console.log('Stats loaded from PHP');
        }
        
        // Function to load transactions from PHP
        function loadTransactions() {
            const transactions = <?php echo json_encode($recent_transactions); ?>;
            
            const tableBody = document.getElementById('transactions-table-body');
            if (tableBody) {
                tableBody.innerHTML = '';
                
                if (transactions.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="py-4 text-center text-gray-500">No recent transactions found</td>
                        </tr>
                    `;
                    return;
                }
                
                transactions.forEach(transaction => {
                    const statusClass = transaction.status === 'Completed' || transaction.status === 'Approved' ? 'status-completed' : 'status-pending';
                    
                    const row = document.createElement('tr');
                    row.className = 'border-b border-gray-100';
                    row.innerHTML = `
                        <td class="py-3">
                            <div class="font-medium text-dark-text">${transaction.name}</div>
                            <div class="text-xs text-gray-500">${transaction.type}</div>
                        </td>
                        <td class="py-3 text-gray-500">${transaction.date}</td>
                        <td class="py-3 font-medium">₱${parseFloat(transaction.amount).toLocaleString()}</td>
                        <td class="py-3">
                            <span class="status-badge ${statusClass}">${transaction.status}</span>
                        </td>
                        <td class="py-3">
                            <button class="text-green-600 hover:text-green-800 mr-2">
                                <i class='bx bx-show'></i>
                            </button>
                            <button class="text-blue-600 hover:text-blue-800">
                                <i class='bx bx-download'></i>
                            </button>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            }
        }
        
        // Function to load due dates
        function loadDueDates() {
            const dueDates = [
                { id: 1, name: 'Vendor Payment - XYZ Supplies', date: '2025-03-20', amount: 2500 },
                { id: 2, name: 'Loan Payment', date: '2025-03-25', amount: 5000 },
                { id: 3, name: 'Office Rent', date: '2025-04-01', amount: 8000 }
            ];
            
            const dueDatesList = document.getElementById('due-dates-list');
            if (dueDatesList) {
                dueDatesList.innerHTML = '';
                
                dueDates.forEach(item => {
                    const dueDate = document.createElement('div');
                    dueDate.className = 'flex justify-between items-center p-3 bg-gray-50 rounded-lg';
                    dueDate.innerHTML = `
                        <div>
                            <div class="font-medium text-dark-text">${item.name}</div>
                            <div class="text-sm text-gray-500">Due ${item.date}</div>
                        </div>
                        <div class="font-bold">₱${item.amount.toLocaleString()}</div>
                    `;
                    dueDatesList.appendChild(dueDate);
                });
            }
        }
        
        // Function to load notifications from PHP
        function loadNotifications() {
            const notifications = <?php echo json_encode($notifications); ?>;
            
            const notificationsList = document.getElementById('notifications-list');
            const notificationModalList = document.getElementById('notification-list');
            
            if (notificationsList) {
                notificationsList.innerHTML = '';
                
                if (notifications.length === 0) {
                    notificationsList.innerHTML = `
                        <div class="text-center text-gray-500 py-4">No notifications</div>
                    `;
                } else {
                    notifications.forEach(notification => {
                        const notificationEl = document.createElement('div');
                        notificationEl.className = 'flex items-start p-3 bg-gray-50 rounded-lg';
                        notificationEl.innerHTML = `
                            <div class="mr-3 mt-1">
                                <div class="w-2 h-2 rounded-full bg-green-600"></div>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm text-dark-text">${notification.message || 'Notification'}</div>
                                <div class="text-xs text-gray-500 mt-1">${new Date(notification.created_at).toLocaleDateString()}</div>
                            </div>
                        `;
                        notificationsList.appendChild(notificationEl);
                    });
                }
            }
            
            if (notificationModalList) {
                notificationModalList.innerHTML = '';
                
                if (notifications.length === 0) {
                    notificationModalList.innerHTML = `
                        <div class="text-center text-gray-500 py-4">No notifications</div>
                    `;
                } else {
                    notifications.forEach(notification => {
                        const notificationEl = document.createElement('div');
                        notificationEl.className = 'p-3 border-b border-gray-200';
                        notificationEl.innerHTML = `
                            <div class="font-medium">${notification.message || 'Notification'}</div>
                            <div class="text-sm text-gray-500 mt-1">${new Date(notification.created_at).toLocaleDateString()}</div>
                        `;
                        notificationModalList.appendChild(notificationEl);
                    });
                }
            }
        }
        
        // Function to initialize page-specific scripts
        function initializePageSpecificScripts(pageId) {
            // Initialize tabs for pages that have them
            initializeTabs();
            
            // Add any page-specific initialization here
            if (pageId.includes('disbursement')) {
                initializeDisbursementScripts();
            } else if (pageId.includes('ledger')) {
                initializeLedgerScripts();
            } else if (pageId.includes('ap-ar')) {
                initializeAPARScripts();
            } else if (pageId.includes('collection')) {
                initializeCollectionScripts();
            } else if (pageId.includes('budget')) {
                initializeBudgetScripts();
            }
        }
        
        // FIXED: Initialize tabs functionality
        function initializeTabs() {
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and tab contents
                    tabs.forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    const tabContent = document.getElementById(`${tabId}-tab`);
                    if (tabContent) {
                        tabContent.classList.add('active');
                    }
                });
            });
        }
        
        // Content generation functions for each page
        
        // Disbursement Pages
        function getDisbursementRequestContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-dark-text">Disbursement Request</h2>
                        <button class="btn btn-primary">New Request</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Requested By</th>
                                    <th>Department</th>
                                    <th>Amount</th>
                                    <th>Date Requested</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>DIS-2025-001</td>
                                    <td>John Smith</td>
                                    <td>Marketing</td>
                                    <td>₱5,000.00</td>
                                    <td>2025-03-15</td>
                                    <td><span class="status-badge status-pending">Pending</span></td>
                                    <td>
                                        <button class="action-btn approve">Approve</button>
                                        <button class="action-btn reject">Reject</button>
                                        <button class="action-btn view">View</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>DIS-2025-002</td>
                                    <td>Maria Garcia</td>
                                    <td>Operations</td>
                                    <td>₱12,500.00</td>
                                    <td>2025-03-14</td>
                                    <td><span class="status-badge status-pending">Pending</span></td>
                                    <td>
                                        <button class="action-btn approve">Approve</button>
                                        <button class="action-btn reject">Reject</button>
                                        <button class="action-btn view">View</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>DIS-2025-003</td>
                                    <td>Robert Johnson</td>
                                    <td>IT</td>
                                    <td>₱8,750.00</td>
                                    <td>2025-03-13</td>
                                    <td><span class="status-badge status-approved">Approved</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        function getPendingDisbursementsContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Pending Disbursements</h2>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Description</th>
                                    <th>Requested By</th>
                                    <th>Amount</th>
                                    <th>Date Requested</th>
                                    <th>Priority</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>DIS-2025-001</td>
                                    <td>Marketing Campaign Materials</td>
                                    <td>John Smith</td>
                                    <td>₱5,000.00</td>
                                    <td>2025-03-15</td>
                                    <td><span class="status-badge status-pending">High</span></td>
                                    <td>
                                        <button class="action-btn approve">Approve</button>
                                        <button class="action-btn reject">Reject</button>
                                        <button class="action-btn view">Details</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>DIS-2025-002</td>
                                    <td>Office Equipment Purchase</td>
                                    <td>Maria Garcia</td>
                                    <td>₱12,500.00</td>
                                    <td>2025-03-14</td>
                                    <td><span class="status-badge status-pending">Medium</span></td>
                                    <td>
                                        <button class="action-btn approve">Approve</button>
                                        <button class="action-btn reject">Reject</button>
                                        <button class="action-btn view">Details</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        function getApprovedDisbursementsContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Approved Disbursements</h2>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Description</th>
                                    <th>Requested By</th>
                                    <th>Amount</th>
                                    <th>Date Approved</th>
                                    <th>Approved By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>DIS-2025-003</td>
                                    <td>Software Licenses</td>
                                    <td>Robert Johnson</td>
                                    <td>₱8,750.00</td>
                                    <td>2025-03-13</td>
                                    <td>Sarah Wilson</td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>DIS-2025-004</td>
                                    <td>Training Materials</td>
                                    <td>David Brown</td>
                                    <td>₱3,200.00</td>
                                    <td>2025-03-10</td>
                                    <td>Sarah Wilson</td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        function getRejectedDisbursementsContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Rejected Disbursements</h2>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Description</th>
                                    <th>Requested By</th>
                                    <th>Amount</th>
                                    <th>Date Rejected</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>DIS-2025-005</td>
                                    <td>Team Building Activity</td>
                                    <td>Lisa Taylor</td>
                                    <td>₱15,000.00</td>
                                    <td>2025-03-08</td>
                                    <td>Exceeds department budget</td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Resubmit</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        function getDisbursementReportsContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Disbursement Reports</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-medium mb-2">Total Disbursements This Month</h3>
                            <p class="text-2xl font-bold text-primary-green">₱25,450.00</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-medium mb-2">Pending Approvals</h3>
                            <p class="text-2xl font-bold text-yellow-600">5</p>
                        </div>
                    </div>
                    <div class="mb-6">
                        <h3 class="font-medium mb-4">Disbursement by Department</h3>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span>Marketing</span>
                                    <span>₱8,500.00</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-primary-green h-2 rounded-full" style="width: 34%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span>Operations</span>
                                    <span>₱7,200.00</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: 29%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span>IT</span>
                                    <span>₱5,750.00</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: 23%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span>HR</span>
                                    <span>₱4,000.00</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-purple-500 h-2 rounded-full" style="width: 16%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-4">
                        <button class="btn btn-primary">Generate PDF Report</button>
                        <button class="btn btn-secondary">Export to Excel</button>
                    </div>
                </div>
            `;
        }
        
// Create Disbursement functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add event listener for create disbursement button
    const newRequestBtn = document.querySelector('button:contains("New Request")');
    if (newRequestBtn) {
        newRequestBtn.addEventListener('click', function() {
            document.getElementById('create-disbursement-modal').style.display = 'block';
        });
    }
    
    // Handle disbursement form submission
    const disbursementForm = document.getElementById('disbursement-form');
    if (disbursementForm) {
        disbursementForm.addEventListener('submit', function(e) {
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<div class="spinner"></div>Submitting...';
            submitBtn.disabled = true;
            
            // Form will submit normally via PHP
        });
    }
    
    // Handle disbursement action buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.action-btn.approve')) {
            if (confirm('Are you sure you want to approve this disbursement?')) {
                const row = e.target.closest('tr');
                const requestId = row.querySelector('td:first-child').textContent;
                // You would need to implement AJAX call here or redirect to approval page
                alert('Approval functionality would be implemented here for: ' + requestId);
            }
        }
        
        if (e.target.closest('.action-btn.reject')) {
            if (confirm('Are you sure you want to reject this disbursement?')) {
                const row = e.target.closest('tr');
                const requestId = row.querySelector('td:first-child').textContent;
                // You would need to implement AJAX call here or redirect to rejection page
                alert('Rejection functionality would be implemented here for: ' + requestId);
            }
        }
    });
});

        // General Ledger Pages
        function getChartOfAccountsContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-dark-text">Chart of Accounts</h2>
                        <button class="btn btn-primary">Add Account</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Account Code</th>
                                    <th>Account Name</th>
                                    <th>Account Type</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1001</td>
                                    <td>Cash on Hand</td>
                                    <td>Asset</td>
                                    <td>₱125,430.00</td>
                                    <td><span class="status-badge status-approved">Active</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>1002</td>
                                    <td>Bank Account</td>
                                    <td>Asset</td>
                                    <td>₱458,720.00</td>
                                    <td><span class="status-badge status-approved">Active</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>2001</td>
                                    <td>Accounts Payable</td>
                                    <td>Liability</td>
                                    <td>₱85,300.00</td>
                                    <td><span class="status-badge status-approved">Active</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>3001</td>
                                    <td>Owner's Equity</td>
                                    <td>Equity</td>
                                    <td>₱500,000.00</td>
                                    <td><span class="status-badge status-approved">Active</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>4001</td>
                                    <td>Service Revenue</td>
                                    <td>Revenue</td>
                                    <td>₱285,600.00</td>
                                    <td><span class="status-badge status-approved">Active</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>5001</td>
                                    <td>Salaries Expense</td>
                                    <td>Expense</td>
                                    <td>₱125,800.00</td>
                                    <td><span class="status-badge status-approved">Active</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        function getJournalEntryContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-dark-text">Journal Entry</h2>
                        <button class="btn btn-primary">New Entry</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Entry ID</th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Debit</th>
                                    <th>Credit</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>JE-2025-001</td>
                                    <td>2025-03-15</td>
                                    <td>Office Supplies Purchase</td>
                                    <td>₱1,250.00</td>
                                    <td>₱1,250.00</td>
                                    <td><span class="status-badge status-approved">Posted</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>JE-2025-002</td>
                                    <td>2025-03-14</td>
                                    <td>Client Payment Received</td>
                                    <td>₱5,000.00</td>
                                    <td>₱5,000.00</td>
                                    <td><span class="status-badge status-approved">Posted</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>JE-2025-003</td>
                                    <td>2025-03-13</td>
                                    <td>Equipment Depreciation</td>
                                    <td>₱2,500.00</td>
                                    <td>₱2,500.00</td>
                                    <td><span class="status-badge status-pending">Draft</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                        <button class="action-btn approve">Post</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        function getLedgerTableContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">General Ledger</h2>
                    <div class="flex space-x-4 mb-6">
                        <div class="form-group">
                            <label class="form-label">Account</label>
                            <select class="form-input">
                                <option>All Accounts</option>
                                <option>Cash on Hand</option>
                                <option>Bank Account</option>
                                <option>Accounts Payable</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-input" value="2025-03-01">
                        </div>
                        <div class="form-group">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-input" value="2025-03-15">
                        </div>
                        <div class="form-group flex items-end">
                            <button class="btn btn-primary">Apply Filter</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Account</th>
                                    <th>Description</th>
                                    <th>Reference</th>
                                    <th>Debit</th>
                                    <th>Credit</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2025-03-15</td>
                                    <td>Cash on Hand</td>
                                    <td>Office Supplies Purchase</td>
                                    <td>JE-2025-001</td>
                                    <td></td>
                                    <td>₱1,250.00</td>
                                    <td>₱124,180.00</td>
                                </tr>
                                <tr>
                                    <td>2025-03-14</td>
                                    <td>Cash on Hand</td>
                                    <td>Client Payment Received</td>
                                    <td>JE-2025-002</td>
                                    <td>₱5,000.00</td>
                                    <td></td>
                                    <td>₱125,430.00</td>
                                </tr>
                                <tr>
                                    <td>2025-03-10</td>
                                    <td>Cash on Hand</td>
                                    <td>Utility Payment</td>
                                    <td>DIS-2025-006</td>
                                    <td></td>
                                    <td>₱850.00</td>
                                    <td>₱120,430.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        function getFinancialReportsContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Financial Reports</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg text-center cursor-pointer hover:bg-gray-100">
                            <i class='bx bx-line-chart text-4xl text-primary-green mb-2'></i>
                            <h3 class="font-medium">Income Statement</h3>
                            <p class="text-sm text-gray-500 mt-1">Profit & Loss Report</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg text-center cursor-pointer hover:bg-gray-100">
                            <i class='bx bx-pie-chart-alt-2 text-4xl text-primary-green mb-2'></i>
                            <h3 class="font-medium">Balance Sheet</h3>
                            <p class="text-sm text-gray-500 mt-1">Assets, Liabilities & Equity</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg text-center cursor-pointer hover:bg-gray-100">
                            <i class='bx bx-trending-up text-4xl text-primary-green mb-2'></i>
                            <h3 class="font-medium">Cash Flow Statement</h3>
                            <p class="text-sm text-gray-500 mt-1">Cash Inflows & Outflows</p>
                        </div>
                    </div>
                    <div class="mb-6">
                         <h3 class="font-medium mb-4">Report Parameters</h3>
                         <div class="flex flex-wrap gap-4">
                             <div class="form-group">
                                 <label class="form-label">Report Type</label>
                                 <select class="form-input" id="reportType">
                                     <option>Income Statement</option>
                                     <option>Balance Sheet</option>
                                     <option>Cash Flow Statement</option>
                                     <option>Trial Balance</option>
                                     <option>Department Budget Report</option>
                                     <option>Budget vs Actual Report</option>
                                 </select>
                             </div>
                             <div class="form-group">
                                 <label class="form-label">Fiscal Year</label>
                                 <select class="form-input" id="fiscalYear">
                                     <option>2025</option><option>2024</option><option>2023</option>
                                 </select>
                             </div>
                             <div class="form-group">
                                 <label class="form-label">Period</label>
                                 <select class="form-input" id="period">
                                     <option>Q1</option><option>Q2</option><option>Q3</option><option>Q4</option><option>Full Year</option>
                                </select>
                             </div>
                             <div class="form-group flex items-end">
                                 <button class="btn btn-primary" id="generateReportBtn">Generate Report</button>
                             </div>
                         </div>
                     </div>
                    <div class="flex space-x-4">
                        <button class="btn btn-primary">Export to PDF</button>
                        <button class="btn btn-secondary">Export to Excel</button>
                    </div>
                </div>
            `;
        }
        
        // AP/AR Pages
        function getVendorsCustomersContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-dark-text">Vendors & Customers</h2>
                        <div class="flex space-x-2">
                            <button class="btn btn-primary">Add Vendor</button>
                            <button class="btn btn-primary">Add Customer</button>
                        </div>
                    </div>
                    <div class="tab-container">
                        <div class="tab active" data-tab="vendors">Vendors</div>
                        <div class="tab" data-tab="customers">Customers</div>
                    </div>
                    <div class="tab-content active" id="vendors-tab">
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Vendor ID</th>
                                        <th>Vendor Name</th>
                                        <th>Contact Person</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>V-001</td>
                                        <td>XYZ Supplies Inc.</td>
                                        <td>Michael Tan</td>
                                        <td>+63 912 345 6789</td>
                                        <td>michael@xyzsupplies.com</td>
                                        <td>₱12,500.00</td>
                                        <td><span class="status-badge status-approved">Active</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>V-002</td>
                                        <td>ABC Office Solutions</td>
                                        <td>Sarah Lim</td>
                                        <td>+63 917 654 3210</td>
                                        <td>sarah@abcoffice.com</td>
                                        <td>₱8,750.00</td>
                                        <td><span class="status-badge status-approved">Active</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-content" id="customers-tab">
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Customer ID</th>
                                        <th>Customer Name</th>
                                        <th>Contact Person</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>C-001</td>
                                        <td>Global Tech Solutions</td>
                                        <td>James Wilson</td>
                                        <td>+63 918 765 4321</td>
                                        <td>james@globaltech.com</td>
                                        <td>₱25,000.00</td>
                                        <td><span class="status-badge status-approved">Active</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>C-002</td>
                                        <td>Innovate Marketing Agency</td>
                                        <td>Lisa Garcia</td>
                                        <td>+63 919 876 5432</td>
                                        <td>lisa@innovatemktg.com</td>
                                        <td>₱15,750.00</td>
                                        <td><span class="status-badge status-approved">Active</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function getInvoicesContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-dark-text">Invoices</h2>
                        <button class="btn btn-primary">Create Invoice</button>
                    </div>
                    <div class="tab-container">
                        <div class="tab active" data-tab="receivable">Accounts Receivable</div>
                        <div class="tab" data-tab="payable">Accounts Payable</div>
                    </div>
                    <div class="tab-content active" id="receivable-tab">
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Customer</th>
                                        <th>Issue Date</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>INV-2025-001</td>
                                        <td>Global Tech Solutions</td>
                                        <td>2025-03-01</td>
                                        <td>2025-03-31</td>
                                        <td>₱25,000.00</td>
                                        <td><span class="status-badge status-overdue">Overdue</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>INV-2025-002</td>
                                        <td>Innovate Marketing Agency</td>
                                        <td>2025-03-05</td>
                                        <td>2025-04-05</td>
                                        <td>₱15,750.00</td>
                                        <td><span class="status-badge status-pending">Pending</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-content" id="payable-tab">
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Vendor</th>
                                        <th>Issue Date</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>V-INV-2025-001</td>
                                        <td>XYZ Supplies Inc.</td>
                                        <td>2025-03-10</td>
                                        <td>2025-03-25</td>
                                        <td>₱12,500.00</td>
                                        <td><span class="status-badge status-pending">Pending</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>V-INV-2025-002</td>
                                        <td>ABC Office Solutions</td>
                                        <td>2025-03-12</td>
                                        <td>2025-03-27</td>
                                        <td>₱8,750.00</td>
                                        <td><span class="status-badge status-pending">Pending</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function getPaymentEntryContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-dark-text">Payment Entry</h2>
                        <button class="btn btn-primary">New Payment</button>
                    </div>
                    <div class="tab-container">
                        <div class="tab active" data-tab="receivable">Receive Payment</div>
                        <div class="tab" data-tab="payable">Make Payment</div>
                    </div>
                    <div class="tab-content active" id="receivable-tab">
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Payment ID</th>
                                        <th>Customer</th>
                                        <th>Invoice #</th>
                                        <th>Payment Date</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>PMT-2025-001</td>
                                        <td>Global Tech Solutions</td>
                                        <td>INV-2025-001</td>
                                        <td>2025-03-15</td>
                                        <td>₱25,000.00</td>
                                        <td>Bank Transfer</td>
                                        <td><span class="status-badge status-approved">Completed</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>PMT-2025-002</td>
                                        <td>Innovate Marketing Agency</td>
                                        <td>INV-2025-002</td>
                                        <td>2025-03-16</td>
                                        <td>₱15,750.00</td>
                                        <td>Check</td>
                                        <td><span class="status-badge status-pending">Processing</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-content" id="payable-tab">
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Payment ID</th>
                                        <th>Vendor</th>
                                        <th>Invoice #</th>
                                        <th>Payment Date</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>V-PMT-2025-001</td>
                                        <td>XYZ Supplies Inc.</td>
                                        <td>V-INV-2025-001</td>
                                        <td>2025-03-20</td>
                                        <td>₱12,500.00</td>
                                        <td>Bank Transfer</td>
                                        <td><span class="status-badge status-pending">Scheduled</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>V-PMT-2025-002</td>
                                        <td>ABC Office Solutions</td>
                                        <td>V-INV-2025-002</td>
                                        <td>2025-03-22</td>
                                        <td>₱8,750.00</td>
                                        <td>Check</td>
                                        <td><span class="status-badge status-pending">Scheduled</span></td>
                                        <td>
                                            <button class="action-btn view">View</button>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function getAgingReportsContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Aging Reports</h2>
                    <div class="tab-container">
                        <div class="tab active" data-tab="receivable">Accounts Receivable Aging</div>
                        <div class="tab" data-tab="payable">Accounts Payable Aging</div>
                    </div>
                    <div class="tab-content active" id="receivable-tab">
                        <div class="mb-6">
                            <h3 class="font-medium mb-4">Accounts Receivable Aging Summary</h3>
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                <div class="bg-gray-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-primary-green">₱25,000</div>
                                    <div class="text-sm text-gray-500">Current</div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-yellow-600">₱15,750</div>
                                    <div class="text-sm text-gray-500">1-30 Days</div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-orange-500">₱8,500</div>
                                    <div class="text-sm text-gray-500">31-60 Days</div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-red-600">₱12,300</div>
                                    <div class="text-sm text-gray-500">61-90 Days</div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-purple-600">₱5,200</div>
                                    <div class="text-sm text-gray-500">Over 90 Days</div>
                                </div>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Current</th>
                                        <th>1-30 Days</th>
                                        <th>31-60 Days</th>
                                        <th>61-90 Days</th>
                                        <th>Over 90 Days</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Global Tech Solutions</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱25,000.00</td>
                                        <td>₱0.00</td>
                                        <td>₱25,000.00</td>
                                    </tr>
                                    <tr>
                                        <td>Innovate Marketing Agency</td>
                                        <td>₱0.00</td>
                                        <td>₱15,750.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱15,750.00</td>
                                    </tr>
                                    <tr>
                                        <td>Tech Innovators Inc.</td>
                                        <td>₱8,500.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱8,500.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-content" id="payable-tab">
                        <div class="mb-6">
                            <h3 class="font-medium mb-4">Accounts Payable Aging Summary</h3>
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                <div class="bg-gray-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-primary-green">₱18,250</div>
                                    <div class="text-sm text-gray-500">Current</div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-yellow-600">₱12,500</div>
                                    <div class="text-sm text-gray-500">1-30 Days</div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-orange-500">₱8,750</div>
                                    <div class="text-sm text-gray-500">31-60 Days</div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-red-600">₱5,200</div>
                                    <div class="text-sm text-gray-500">61-90 Days</div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg text-center">
                                    <div class="text-2xl font-bold text-purple-600">₱3,100</div>
                                    <div class="text-sm text-gray-500">Over 90 Days</div>
                                </div>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Vendor</th>
                                        <th>Current</th>
                                        <th>1-30 Days</th>
                                        <th>31-60 Days</th>
                                        <th>61-90 Days</th>
                                        <th>Over 90 Days</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>XYZ Supplies Inc.</td>
                                        <td>₱0.00</td>
                                        <td>₱12,500.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱12,500.00</td>
                                    </tr>
                                    <tr>
                                        <td>ABC Office Solutions</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱8,750.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱8,750.00</td>
                                    </tr>
                                    <tr>
                                        <td>Office Equipment Co.</td>
                                        <td>₱18,250.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱0.00</td>
                                        <td>₱18,250.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Collection Pages
        function getPaymentEntryCollectionContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-dark-text">Payment Entry - Collection</h2>
                        <button class="btn btn-primary">Receive Payment</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Customer</th>
                                    <th>Invoice #</th>
                                    <th>Amount Due</th>
                                    <th>Amount Paid</th>
                                    <th>Payment Date</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>COL-2025-001</td>
                                    <td>Global Tech Solutions</td>
                                    <td>INV-2025-001</td>
                                    <td>₱25,000.00</td>
                                    <td>₱25,000.00</td>
                                    <td>2025-03-15</td>
                                    <td>Bank Transfer</td>
                                    <td><span class="status-badge status-approved">Collected</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>COL-2025-002</td>
                                    <td>Innovate Marketing Agency</td>
                                    <td>INV-2025-002</td>
                                    <td>₱15,750.00</td>
                                    <td>₱10,000.00</td>
                                    <td>2025-03-16</td>
                                    <td>Check</td>
                                    <td><span class="status-badge status-pending">Partial</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>COL-2025-003</td>
                                    <td>Tech Innovators Inc.</td>
                                    <td>INV-2025-003</td>
                                    <td>₱8,500.00</td>
                                    <td>₱0.00</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td><span class="status-badge status-overdue">Overdue</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Send Reminder</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        function getReceiptGenerationContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Receipt Generation</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="bg-gray-50 p-6 rounded-lg">
                            <h3 class="font-medium mb-4">Generate New Receipt</h3>
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label class="form-label">Customer</label>
                                    <select class="form-input">
                                        <option>Select Customer</option>
                                        <option>Global Tech Solutions</option>
                                        <option>Innovate Marketing Agency</option>
                                        <option>Tech Innovators Inc.</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Payment Reference</label>
                                    <input type="text" class="form-input" placeholder="Enter payment reference">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Amount</label>
                                    <input type="number" class="form-input" placeholder="Enter amount">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Payment Method</label>
                                    <select class="form-input">
                                        <option>Cash</option>
                                        <option>Check</option>
                                        <option>Bank Transfer</option>
                                        <option>Credit Card</option>
                                    </select>
                                </div>
                                <button class="btn btn-primary w-full">Generate Receipt</button>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-6 rounded-lg">
                            <h3 class="font-medium mb-4">Recent Receipts</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center p-3 bg-white rounded">
                                    <div>
                                        <div class="font-medium">RC-2025-001</div>
                                        <div class="text-sm text-gray-500">Global Tech Solutions</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium">₱25,000.00</div>
                                        <div class="text-sm text-gray-500">2025-03-15</div>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-white rounded">
                                    <div>
                                        <div class="font-medium">RC-2025-002</div>
                                        <div class="text-sm text-gray-500">Innovate Marketing Agency</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium">₱10,000.00</div>
                                        <div class="text-sm text-gray-500">2025-03-16</div>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-white rounded">
                                    <div>
                                        <div class="font-medium">RC-2025-003</div>
                                        <div class="text-sm text-gray-500">Tech Solutions Ltd.</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium">₱12,500.00</div>
                                        <div class="text-sm text-gray-500">2025-03-10</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-4">
                        <button class="btn btn-primary">Print All Receipts</button>
                        <button class="btn btn-secondary">Export Receipts</button>
                    </div>
                </div>
            `;
        }
        
        function getCollectionDashboardContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Collection Dashboard</h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg text-center">
                            <div class="text-2xl font-bold text-primary-green">₱125,430</div>
                            <div class="text-sm text-gray-500">Total Receivables</div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg text-center">
                            <div class="text-2xl font-bold text-green-600">₱47,500</div>
                            <div class="text-sm text-gray-500">Collected This Month</div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg text-center">
                            <div class="text-2xl font-bold text-yellow-600">₱35,750</div>
                            <div class="text-sm text-gray-500">Pending Collection</div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg text-center">
                            <div class="text-2xl font-bold text-red-600">₱42,180</div>
                            <div class="text-sm text-gray-500">Overdue Amount</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-medium mb-4">Collection Efficiency</h3>
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span>Current Month</span>
                                        <span>68%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-primary-green h-2 rounded-full" style="width: 68%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span>Previous Month</span>
                                        <span>72%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: 72%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span>Quarter Average</span>
                                        <span>65%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: 65%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-medium mb-4">Top Customers by Outstanding Balance</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span>Global Tech Solutions</span>
                                    <span class="font-medium">₱25,000.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>Innovate Marketing Agency</span>
                                    <span class="font-medium">₱15,750.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>Tech Innovators Inc.</span>
                                    <span class="font-medium">₱8,500.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>Digital Solutions Co.</span>
                                    <span class="font-medium">₱7,200.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>Creative Media Agency</span>
                                    <span class="font-medium">₱5,800.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function getOutstandingBalancesContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Outstanding Balances</h2>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Total Balance</th>
                                    <th>Current</th>
                                    <th>1-30 Days</th>
                                    <th>31-60 Days</th>
                                    <th>61-90 Days</th>
                                    <th>Over 90 Days</th>
                                    <th>Last Payment Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Global Tech Solutions</td>
                                    <td>₱25,000.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>₱25,000.00</td>
                                    <td>₱0.00</td>
                                    <td>2025-02-15</td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Send Reminder</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Innovate Marketing Agency</td>
                                    <td>₱15,750.00</td>
                                    <td>₱0.00</td>
                                    <td>₱15,750.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>2025-03-05</td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Send Reminder</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Tech Innovators Inc.</td>
                                    <td>₱8,500.00</td>
                                    <td>₱8,500.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>2025-03-01</td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Send Reminder</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Digital Solutions Co.</td>
                                    <td>₱7,200.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>₱7,200.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>2025-01-20</td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Send Reminder</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Creative Media Agency</td>
                                    <td>₱5,800.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>₱0.00</td>
                                    <td>₱5,800.00</td>
                                    <td>2024-12-10</td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Send Reminder</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        function getCollectionReportsContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Collection Reports</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-medium mb-2">Collection Performance</h3>
                            <p class="text-2xl font-bold text-primary-green">72%</p>
                            <p class="text-sm text-gray-500 mt-1">Efficiency Rate</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-medium mb-2">Average Collection Period</h3>
                            <p class="text-2xl font-bold text-green-600">42 days</p>
                            <p class="text-sm text-gray-500 mt-1">Days Sales Outstanding</p>
                        </div>
                    </div>
                    <div class="mb-6">
                        <h3 class="font-medium mb-4">Collection Trends</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between mb-4">
                                <div>
                                    <div class="font-medium">Monthly Collection</div>
                                    <div class="text-sm text-gray-500">Last 6 months</div>
                                </div>
                                <button class="btn btn-secondary">View Details</button>
                            </div>
                            <div class="h-64 flex items-end justify-between">
                                <div class="flex flex-col items-center">
                                    <div class="w-10 bg-primary-green rounded-t" style="height: 120px"></div>
                                    <div class="text-xs mt-2">Oct</div>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="w-10 bg-primary-green rounded-t" style="height: 150px"></div>
                                    <div class="text-xs mt-2">Nov</div>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="w-10 bg-primary-green rounded-t" style="height: 130px"></div>
                                    <div class="text-xs mt-2">Dec</div>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="w-10 bg-primary-green rounded-t" style="height: 180px"></div>
                                    <div class="text-xs mt-2">Jan</div>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="w-10 bg-primary-green rounded-t" style="height: 160px"></div>
                                    <div class="text-xs mt-2">Feb</div>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="w-10 bg-primary-green rounded-t" style="height: 190px"></div>
                                    <div class="text-xs mt-2">Mar</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-4">
                        <button class="btn btn-primary">Generate Collection Report</button>
                        <button class="btn btn-secondary">Export to Excel</button>
                    </div>
                </div>
            `;
        }
        
        // Budget Management Pages
        function getBudgetProposalContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-dark-text">Budget Proposal</h2>
                        <button class="btn btn-primary">Create Proposal</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Proposal ID</th>
                                    <th>Department</th>
                                    <th>Fiscal Year</th>
                                    <th>Total Amount</th>
                                    <th>Submitted By</th>
                                    <th>Submission Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>BUD-2025-001</td>
                                    <td>Marketing</td>
                                    <td>2025</td>
                                    <td>₱250,000.00</td>
                                    <td>John Smith</td>
                                    <td>2025-02-15</td>
                                    <td><span class="status-badge status-pending">Under Review</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>BUD-2025-002</td>
                                    <td>Operations</td>
                                    <td>2025</td>
                                    <td>₱180,000.00</td>
                                    <td>Maria Garcia</td>
                                    <td>2025-02-18</td>
                                    <td><span class="status-badge status-approved">Approved</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>BUD-2025-003</td>
                                    <td>IT</td>
                                    <td>2025</td>
                                    <td>₱120,000.00</td>
                                    <td>Robert Johnson</td>
                                    <td>2025-02-20</td>
                                    <td><span class="status-badge status-rejected">Rejected</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Resubmit</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>BUD-2025-004</td>
                                    <td>HR</td>
                                    <td>2025</td>
                                    <td>₱95,000.00</td>
                                    <td>Lisa Taylor</td>
                                    <td>2025-02-22</td>
                                    <td><span class="status-badge status-pending">Under Review</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn edit">Edit</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        function getApprovalWorkflowContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Approval Workflow</h2>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Proposal ID</th>
                                    <th>Department</th>
                                    <th>Total Amount</th>
                                    <th>Current Stage</th>
                                    <th>Next Approver</th>
                                    <th>Deadline</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>BUD-2025-001</td>
                                    <td>Marketing</td>
                                    <td>₱250,000.00</td>
                                    <td>Department Head Review</td>
                                    <td>Sarah Wilson</td>
                                    <td>2025-03-20</td>
                                    <td><span class="status-badge status-pending">Pending</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn approve">Approve</button>
                                        <button class="action-btn reject">Reject</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>BUD-2025-004</td>
                                    <td>HR</td>
                                    <td>₱95,000.00</td>
                                    <td>Finance Review</td>
                                    <td>David Brown</td>
                                    <td>2025-03-25</td>
                                    <td><span class="status-badge status-pending">Pending</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn approve">Approve</button>
                                        <button class="action-btn reject">Reject</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>BUD-2025-005</td>
                                    <td>Sales</td>
                                    <td>₱150,000.00</td>
                                    <td>Final Approval</td>
                                    <td>CEO Office</td>
                                    <td>2025-03-18</td>
                                    <td><span class="status-badge status-pending">Pending</span></td>
                                    <td>
                                        <button class="action-btn view">View</button>
                                        <button class="action-btn approve">Approve</button>
                                        <button class="action-btn reject">Reject</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6">
                        <h3 class="font-medium mb-4">Approval History</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="space-y-4">
                                <div class="flex items-start">
                                    <div class="w-3 h-3 rounded-full bg-primary-green mt-1.5 mr-3"></div>
                                    <div>
                                        <div class="font-medium">BUD-2025-002 - Operations</div>
                                        <div class="text-sm text-gray-500">Approved by Sarah Wilson on 2025-03-10</div>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="w-3 h-3 rounded-full bg-red-600 mt-1.5 mr-3"></div>
                                    <div>
                                        <div class="font-medium">BUD-2025-003 - IT</div>
                                        <div class="text-sm text-gray-500">Rejected by David Brown on 2025-03-12 - Budget exceeds department allocation</div>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="w-3 h-3 rounded-full bg-primary-green mt-1.5 mr-3"></div>
                                    <div>
                                        <div class="font-medium">BUD-2025-006 - R&D</div>
                                        <div class="text-sm text-gray-500">Approved by CEO Office on 2025-03-08</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function getBudgetVsActualContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Budget vs Actual</h2>
                    <div class="flex space-x-4 mb-6">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select class="form-input">
                                <option>All Departments</option>
                                <option>Marketing</option>
                                <option>Operations</option>
                                <option>IT</option>
                                <option>HR</option>
                                <option>Sales</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Period</label>
                            <select class="form-input">
                                <option>Q1 2025</option>
                                <option>Q2 2025</option>
                                <option>Q3 2025</option>
                                <option>Q4 2025</option>
                                <option>Full Year 2025</option>
                            </select>
                        </div>
                        <div class="form-group flex items-end">
                            <button class="btn btn-primary">Apply Filter</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Category</th>
                                    <th>Budget</th>
                                    <th>Actual</th>
                                    <th>Variance</th>
                                    <th>Variance %</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Marketing</td>
                                    <td>Advertising</td>
                                    <td>₱50,000.00</td>
                                    <td>₱45,250.00</td>
                                    <td class="text-green-600">-₱4,750.00</td>
                                    <td class="text-green-600">-9.5%</td>
                                    <td><span class="status-badge status-approved">Under Budget</span></td>
                                </tr>
                                <tr>
                                    <td>Marketing</td>
                                    <td>Events</td>
                                    <td>₱30,000.00</td>
                                    <td>₱32,500.00</td>
                                    <td class="text-red-600">+₱2,500.00</td>
                                    <td class="text-red-600">+8.3%</td>
                                    <td><span class="status-badge status-pending">Over Budget</span></td>
                                </tr>
                                <tr>
                                    <td>Operations</td>
                                    <td>Supplies</td>
                                    <td>₱25,000.00</td>
                                    <td>₱22,800.00</td>
                                    <td class="text-green-600">-₱2,200.00</td>
                                    <td class="text-green-600">-8.8%</td>
                                    <td><span class="status-badge status-approved">Under Budget</span></td>
                                </tr>
                                <tr>
                                    <td>Operations</td>
                                    <td>Equipment</td>
                                    <td>₱40,000.00</td>
                                    <td>₱40,000.00</td>
                                    <td>₱0.00</td>
                                    <td>0%</td>
                                    <td><span class="status-badge status-approved">On Budget</span></td>
                                </tr>
                                <tr>
                                    <td>IT</td>
                                    <td>Software</td>
                                    <td>₱35,000.00</td>
                                    <td>₱38,200.00</td>
                                    <td class="text-red-600">+₱3,200.00</td>
                                    <td class="text-red-600">+9.1%</td>
                                    <td><span class="status-badge status-pending">Over Budget</span></td>
                                </tr>
                                <tr>
                                    <td>IT</td>
                                    <td>Hardware</td>
                                    <td>₱20,000.00</td>
                                    <td>₱18,500.00</td>
                                    <td class="text-green-600">-₱1,500.00</td>
                                    <td class="text-green-600">-7.5%</td>
                                    <td><span class="status-badge status-approved">Under Budget</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6">
                        <h3 class="font-medium mb-4">Budget Utilization</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex justify-between mb-2">
                                    <span class="font-medium">Marketing</span>
                                    <span>78%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-primary-green h-2 rounded-full" style="width: 78%"></div>
                                </div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex justify-between mb-2">
                                    <span class="font-medium">Operations</span>
                                    <span>65%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: 65%"></div>
                                </div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex justify-between mb-2">
                                    <span class="font-medium">IT</span>
                                    <span>92%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-600 h-2 rounded-full" style="width: 92%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function getBudgetReportsContent() {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">Budget Reports</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg text-center cursor-pointer hover:bg-gray-100">
                            <i class='bx bx-line-chart text-4xl text-primary-green mb-2'></i>
                            <h3 class="font-medium">Department Budget Report</h3>
                            <p class="text-sm text-gray-500 mt-1">Budget allocation by department</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg text-center cursor-pointer hover:bg-gray-100">
                            <i class='bx bx-pie-chart-alt-2 text-4xl text-primary-green mb-2'></i>
                            <h3 class="font-medium">Budget vs Actual Report</h3>
                            <p class="text-sm text-gray-500 mt-1">Comparison of budget and actual spending</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg text-center cursor-pointer hover:bg-gray-100">
                            <i class='bx bx-trending-up text-4xl text-primary-green mb-2'></i>
                            <h3 class="font-medium">Budget Utilization Report</h3>
                            <p class="text-sm text-gray-500 mt-1">Budget spending trends and utilization</p>
                        </div>
                    </div>
                    <div class="mb-6">
                        <h3 class="font-medium mb-4">Report Parameters</h3>
                        <div class="flex space-x-4">
                            <div class="form-group">
                                <label class="form-label">Report Type</label>
                                <select class="form-input">
                                    <option>Department Budget Report</option>
                                    <option>Budget vs Actual Report</option>
                                    <option>Budget Utilization Report</option>
                                    <option>Budget Variance Analysis</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fiscal Year</label>
                                <select class="form-input">
                                    <option>2025</option>
                                    <option>2024</option>
                                    <option>2023</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Period</label>
                                <select class="form-input">
                                    <option>Q1</option>
                                    <option>Q2</option>
                                    <option>Q3</option>
                                    <option>Q4</option>
                                    <option>Full Year</option>
                                </select>
                            </div>
                            <div class="form-group flex items-end">
                                <button class="btn btn-primary">Generate Report</button>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-4">
                        <button class="btn btn-primary">Export to PDF</button>
                        <button class="btn btn-secondary">Export to Excel</button>
                    </div>
                </div>
            `;
        }
        
        function getDefaultPageContent(pageId) {
            return `
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <h2 class="text-xl font-bold text-dark-text mb-6">${pageId.replace('-', ' ').toUpperCase()}</h2>
                    <div class="text-center py-12">
                        <i class='bx bx-wrench text-6xl text-gray-300 mb-4'></i>
                        <h3 class="text-lg font-medium text-gray-500">Page Under Development</h3>
                        <p class="text-gray-400 mt-2">This page is currently being developed and will be available soon.</p>
                    </div>
                </div>
            `;
        }
        
        // Initialize page-specific scripts
        function initializeDisbursementScripts() {
            console.log('Initializing disbursement scripts');
        }
        
        function initializeLedgerScripts() {
            console.log('Initializing ledger scripts');
        }
        
        function initializeAPARScripts() {
            console.log('Initializing AP/AR scripts');
        }
        
        function initializeCollectionScripts() {
            console.log('Initializing collection scripts');
        }
        
        function initializeBudgetScripts() {
            console.log('Initializing budget scripts');
        }
    </script>
</body>
</html>
