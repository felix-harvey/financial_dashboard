
function getStatusClass(status){
  const s = (status || '').toLowerCase();
  if (s === 'approved') return 'status-approved';
  if (s === 'pending') return 'status-pending';
  if (s === 'rejected') return 'status-rejected';
  if (s === 'overdue') return 'status-overdue';
  return 'status-neutral';
}

// js/dashboard.js
// API Configuration
// API helper: build URL compatible with either REST paths or single PHP endpoint


function makeUrl(url){
  if(!API_BASE_URL) return url;
  if(/\.php(\?.*)?$/i.test(API_BASE_URL)){
    const sep = API_BASE_URL.includes('?') ? '&' : '?';
    return `${API_BASE_URL}${sep}route=${encodeURIComponent(url)}`;
  }
  const base = API_BASE_URL.endsWith('/') ? API_BASE_URL.slice(0, -1) : API_BASE_URL;
  return `${base}/${url}`;
}
const API_BASE_URL = 'api.php';

// Global chart variables
let incomeExpenseChart, budgetChart;

// API service functions
const apiService = {
    async get(url) {
        try {
            const response = await fetch(makeUrl(url)));
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API GET Error:', error);
            throw error;
        }
    },

    async post(url, data) {
        try {
            const response = await fetch(makeUrl(url)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API POST Error:', error);
            throw error;
        }
    }
};

// Data service functions
const dataService = {
    async loadDashboardStats() {
        try {
            const result = await apiService.get('dashboard-stats');
            if (result.success) {
                return result.data;
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('Failed to load dashboard stats:', error);
            // Return fallback data
            return {
                total_income: 0,
                total_expenses: 0,
                cash_flow: 0,
                upcoming_payments: 0
            };
        }
    },

    async loadRecentTransactions(limit = 10) {
        try {
            const result = await apiService.get(`transactions?limit=${limit}`);
            if (result.success) {
                return result.data;
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('Failed to load transactions:', error);
            return [];
        }
    },

    async loadChartsData() {
        try {
            const result = await apiService.get('charts-data');
            if (result.success) {
                return result.data;
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('Failed to load charts data:', error);
            return {
                income_expense: [],
                budget_distribution: []
            };
        }
    },

    async loadDisbursements(status = 'all') {
        try {
            const result = await apiService.get(`disbursements?status=${status}`);
            if (result.success) {
                return result.data;
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('Failed to load disbursements:', error);
            return [];
        }
    }
};

// UI Utility Functions
const uiUtils = {
    showError(message) {
        this.showMessage(message, 'error');
    },

    showSuccess(message) {
        this.showMessage(message, 'success');
    },

    showMessage(message, type) {
  const existing = document.querySelector('.error-message, .success-message');
  if (existing) existing.remove();
  const el = document.createElement('div');
  el.className = type === 'error' ? 'error-message' : 'success-message';
  el.textContent = message;
  const main = document.getElementById('main-content');
  if (main && main.firstElementChild) {
    main.insertBefore(el, main.firstElementChild);
  } else {
    document.body.prepend(el);
  }
  setTimeout(() => el.remove(), 5000);
}, 5000);
    },

    showLoading() {
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div class="loading-content">
                <div class="spinner"></div>
                <p>Loading...</p>
            </div>
        `;
        document.body.appendChild(overlay);
        return overlay;
    },

    hideLoading(overlay) {
        if (overlay && overlay.parentNode) {
            overlay.remove();
        }
    }
};

// Chart Management
const chartManager = {
    initIncomeExpenseChart() {
        const ctx = ctx;
        incomeExpenseChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
                datasets: [
                    {
                        label: 'Income',
                        data: [],
                        backgroundColor: '#2F855A',
                        borderRadius: 6,
                    },
                    {
                        label: 'Expenses',
                        data: [],
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
                                return '₱' + value + 'K';
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
    },

    initBudgetChart() {
        const ctx = ctx;
        budgetChart = new Chart(ctx, {
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
    },

    updateChartsWithRealData(chartsData) {
        // Update Income vs Expenses Chart
        if (incomeExpenseChart && chartsData.income_expense.length > 0) {
            const months = chartsData.income_expense.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('en-US', { month: 'short' });
            }).reverse();
            
            const incomeData = chartsData.income_expense.map(item => (item.income / 1000)).reverse();
            const expenseData = chartsData.income_expense.map(item => (item.expense / 1000)).reverse();

            incomeExpenseChart.data.labels = months;
            incomeExpenseChart.data.datasets[0].data = incomeData;
            incomeExpenseChart.data.datasets[1].data = expenseData;
            incomeExpenseChart.update();
        }

        // Update Budget Chart
        if (budgetChart && chartsData.budget_distribution.length > 0) {
            const labels = chartsData.budget_distribution.map(item => item.category);
            const data = chartsData.budget_distribution.map(item => parseFloat(item.amount));

            budgetChart.data.labels = labels;
            budgetChart.data.datasets[0].data = data;
            budgetChart.update();
        }
    }
};

// Dashboard Data Management
const dashboardManager = {
    async initializeDashboard() {
        try {
            // Show loading states
            document.querySelectorAll('.stat-value').forEach(el => {
                el.innerHTML = '<div class="spinner"></div>';
            });

            // Load all data in parallel
            const [stats, transactions, chartsData] = await Promise.all([
                dataService.loadDashboardStats(),
                dataService.loadRecentTransactions(5),
                dataService.loadChartsData()
            ]);

            // Update UI with real data
            this.updateStatsCards(stats);
            this.updateTransactionsTable(transactions);
            chartManager.updateChartsWithRealData(chartsData);
            
        } catch (error) {
            console.error('Failed to initialize dashboard:', error);
            uiUtils.showError('Failed to load dashboard data. Please try again.');
        }
    },

    updateStatsCards(stats) {
        const statElements = document.querySelectorAll('.stat-value');
        if (statElements.length >= 4) {
            statElements[0].textContent = `₱${stats.total_income.toLocaleString()}`;
            statElements[1].textContent = `₱${stats.total_expenses.toLocaleString()}`;
            statElements[2].textContent = `₱${stats.cash_flow.toLocaleString()}`;
            statElements[3].textContent = `₱${stats.upcoming_payments.toLocaleString()}`;
        }
    },

    updateTransactionsTable(transactions) {
        const tableBody = document.getElementById('transactions-table-body');
        
        if (!tableBody) return;

        if (transactions.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-gray-500">
                        No transactions found
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = transactions.map(transaction => `
            <tr class="border-b border-gray-200 hover:bg-gray-50">
                <td class="py-3">
                    <div class="font-medium text-dark-text">${this.escapeHtml(transaction.description)}</div>
                    <div class="text-xs text-gray-500">${this.escapeHtml(transaction.category)}</div>
                </td>
                <td class="py-3 text-sm">${new Date(transaction.date).toLocaleDateString()}</td>
                <td class="py-3 font-medium ${transaction.type === 'income' ? 'text-green-600' : 'text-red-600'}">
                    ${transaction.type === 'income' ? '+' : '-'}₱${parseFloat(transaction.amount).toLocaleString()}
                </td>
                <td class="py-3">
                    <span class="status-badge status-${transaction.status}">
                        ${transaction.status.charAt(0).toUpperCase() + transaction.status.slice(1)}
                    </span>
                </td>
                <td class="py-3">
                    <button class="action-btn view view-transaction" data-id="${transaction.id}">
                        View
                    </button>
                    ${transaction.status === 'pending' ? `
                        <button class="action-btn approve approve-transaction" data-id="${transaction.id}">
                            Approve
                        </button>
                    ` : ''}
                </td>
            </tr>
        `).join('');

        // Re-attach event listeners
        this.attachTransactionEventListeners();
    },

    attachTransactionEventListeners() {
        // View transaction
        document.querySelectorAll('.view-transaction').forEach(button => {
            button.addEventListener('click', function() {
                const transactionId = this.getAttribute('data-id');
                dashboardManager.viewTransactionDetails(transactionId, this);
            });
        });

        // Approve transaction
        document.querySelectorAll('.approve-transaction').forEach(button => {
            button.addEventListener('click', function() {
                const transactionId = this.getAttribute('data-id');
                dashboardManager.approveTransaction(transactionId, this);
            });
        });
    },

    async viewTransactionDetails(transactionId, button) {
        try {
            // Show loading
            const originalText = button.innerHTML;
            button.innerHTML = '<div class="spinner"></div>';
            
            // In a real app, you'd fetch transaction details
            // const transaction = await dataService.getTransaction(transactionId);
            
            setTimeout(() => {
                alert(`Viewing transaction #${transactionId} details.`);
                button.innerHTML = originalText;
            }, 500);
            
        } catch (error) {
            console.error('Failed to view transaction:', error);
            uiUtils.showError('Failed to load transaction details.');
        }
    },

    async approveTransaction(transactionId, button) {
        try {
            // Show loading
            const originalText = button.innerHTML;
            button.innerHTML = '<div class="spinner"></div>';
            
            // Simulate API call
            setTimeout(async () => {
                try {
                    // In a real app: await apiService.post(`transactions/${transactionId}/approve`, {});
                    
                    // Update UI
                    const row = button.closest('tr');
                    const statusCell = row.querySelector('.status-badge');
                    statusCell.className = 'status-badge status-completed';
                    statusCell.textContent = 'Completed';
                    
                    button.remove();
                    
                    // Show success message
                    uiUtils.showSuccess('Transaction approved successfully!');
                    
                    // Refresh stats
                    const stats = await dataService.loadDashboardStats();
                    this.updateStatsCards(stats);
                    
                } catch (error) {
                    button.innerHTML = originalText;
                    uiUtils.showError('Failed to approve transaction.');
                }
            }, 1000);
            
        } catch (error) {
            console.error('Failed to approve transaction:', error);
            uiUtils.showError('Failed to approve transaction.');
        }
    },

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
};