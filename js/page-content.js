
function getStatusClass(status){
  const s = (status || '').toLowerCase();
  if (s === 'approved') return 'status-approved';
  if (s === 'pending') return 'status-pending';
  if (s === 'rejected') return 'status-rejected';
  if (s === 'overdue') return 'status-overdue';
  return 'status-neutral';
}

// js/page-content.js
// Page content management
const pageContentManager = {
    getPageContent(pageId) {
        // This would contain all your page content generation functions
        // For now, return a simple placeholder
        return `
            <div class="bg-white rounded-xl p-6 card-shadow">
                <h2 class="text-xl font-bold mb-4">${pageId.replace(/-/g, ' ').toUpperCase()}</h2>
                <p class="mb-4">This is the ${pageId.replace(/-/g, ' ')} page content.</p>
                <p>In a real application, this would contain specific functionality and data related to this section.</p>
                <div class="mt-6">
                    <button class="btn btn-secondary back-to-dashboard">Back to Dashboard</button>
                </div>
            </div>
        `;
    },

    initializePageFunctionality(pageId) {
        // Initialize page-specific functionality
        switch(pageId) {
            case 'disbursement-request':
                this.initializeDisbursementRequest();
                break;
            case 'pending-disbursements':
                this.initializePendingDisbursements();
                break;
            // Add more cases as needed
        }
    },

    initializeDisbursementRequest() {
        const form = document.getElementById('disbursement-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                alert('Disbursement request submitted successfully!');
                form.reset();
            });
        }
    },

    initializePendingDisbursements() {
        // Implementation for pending disbursements
        const tableBody = document.getElementById('pending-disbursements-table');
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td>D-001</td>
                    <td>ABC Supplies</td>
                    <td>₱5,000</td>
                    <td>Office supplies</td>
                    <td>2025-03-15</td>
                    <td>
                        <button class="action-btn approve">Approve</button>
                        <button class="action-btn reject">Reject</button>
                    </td>
                </tr>
            `;
        }
    }
};