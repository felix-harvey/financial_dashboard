// js/ui-interactions.js
// Sidebar and UI interactions
const uiInteractions = {
    init() {
        this.initSidebar();
        this.initModals();
        this.initSubmenus();
        this.initPageNavigation();
        this.initRefreshButtons();
    },

    initSidebar() {
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const closeSidebar = document.getElementById('close-sidebar');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const mainContent = document.getElementById('main-content');

        if (!hamburgerBtn || !sidebar || !overlay || !closeSidebar || !mainContent) {
            console.error('Sidebar elements not found');
            return;
        }

        // Function to check screen size and toggle sidebar accordingly
        const toggleSidebar = () => {
            if (window.innerWidth < 769) {
                // Mobile behavior
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            } else {
                // Desktop behavior
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('full-width');
            }
        };

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
    },

    initModals() {
        // Notification functionality
        const notificationBtn = document.getElementById('notification-btn');
        const notificationModal = document.getElementById('notification-modal');
        const profileBtn = document.getElementById('profile-btn');
        const profileModal = document.getElementById('profile-modal');
        const closeModalButtons = document.querySelectorAll('.close-modal');
        
        if (notificationBtn && notificationModal) {
            notificationBtn.addEventListener('click', function() {
                const notificationList = document.getElementById('notification-list');
                const notificationCount = document.querySelector('.notification-count');

                // Clear notifications for now
                notificationList.innerHTML = `
                    <div class="py-3 text-center text-gray-500">No notifications yet</div>
                `;

                // Hide the red badge
                if (notificationCount) {
                    notificationCount.style.display = 'none';
                }

                // Show the modal
                notificationModal.style.display = 'block';
            });
        }

        // Profile functionality
        if (profileBtn && profileModal) {
            profileBtn.addEventListener('click', function() {
                profileModal.style.display = 'block';
            });
        }
        
        // Close modal functionality
        closeModalButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            });
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        // Logout functionality
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to logout?')) {
                    // Show loading state
                    document.body.innerHTML = `
                        <div class="flex justify-center items-center h-screen">
                            <div class="text-center">
                                <div class="spinner mx-auto mb-4"></div>
                                <p>Logging out...</p>
                            </div>
                        </div>
                    `;
                    
                    // Simulate logout process
                    setTimeout(() => {
                        alert('You have been logged out successfully.');
                        location.reload(); // In a real app, this would redirect to login page
                    }, 1500);
                }
            });
        }
    },

    initSubmenus() {
        // Sidebar submenu functionality
        const categoryToggles = document.querySelectorAll('.sidebar-category');
        
        categoryToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const category = this.getAttribute('data-category');
                const submenu = document.getElementById(`${category}-submenu`);
                const arrow = document.querySelector(`.category-arrow[data-category="${category}"]`);
                
                // Toggle the active class on the submenu
                if (submenu) {
                    submenu.classList.toggle('active');
                }
                
                // Rotate the arrow
                if (arrow) {
                    arrow.classList.toggle('rotate-180');
                }
            });
        });
    },

    initPageNavigation() {
        // Make submenu items clickable
        const submenuItems = document.querySelectorAll('.submenu-item');
        const sidebarItems = document.querySelectorAll('.sidebar-item');
        const dashboardContent = document.getElementById('dashboard-content');
        const pageContent = document.getElementById('page-content');
        
        // Function to load page content
        const loadPageContent = (pageId) => {
            // Show loading state
            pageContent.innerHTML = `<div class="flex justify-center items-center h-64"><div class="spinner"></div>Loading ${pageId.replace('-', ' ')}...</div>`;
            pageContent.classList.remove('hidden');
            if (dashboardContent) {
                dashboardContent.classList.add('hidden');
            }

            // Simulate API call delay
            setTimeout(() => {
                // Load the appropriate content based on pageId
                let content = pageContentManager.getPageContent(pageId);
                
                pageContent.innerHTML = content;
                
                // Add event listener for back button
                const backButton = document.querySelector('.back-to-dashboard');
                if (backButton) {
                    backButton.addEventListener('click', function() {
                        pageContent.classList.add('hidden');
                        if (dashboardContent) {
                            dashboardContent.classList.remove('hidden');
                        }
                        
                        // Remove active class from all items
                        submenuItems.forEach(item => item.classList.remove('active'));
                        sidebarItems.forEach(item => item.classList.remove('active'));
                    });
                }
                
                // Initialize any page-specific functionality
                pageContentManager.initializePageFunctionality(pageId);
            }, 1000);
        };
        
        submenuItems.forEach(item => {
            item.addEventListener('click', function() {
                // Remove active class from all items
                submenuItems.forEach(i => i.classList.remove('active'));
                sidebarItems.forEach(i => i.classList.remove('active'));
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Load the appropriate content
                const pageId = this.getAttribute('data-page');
                loadPageContent(pageId);
            });
        });
        
        sidebarItems.forEach(item => {
            item.addEventListener('click', function() {
                // Remove active class from all items
                submenuItems.forEach(i => i.classList.remove('active'));
                sidebarItems.forEach(i => i.classList.remove('active'));
                
                // Add active class to clicked item
                this.classList.add('active');
                
                const pageId = this.getAttribute('data-page');

                if (pageId === "financial") {
                    // Show dashboard directly
                    if (pageContent) {
                        pageContent.classList.add('hidden');
                    }
                    if (dashboardContent) {
                        dashboardContent.classList.remove('hidden');
                    }
                } else {
                    // Load the appropriate content
                    loadPageContent(pageId);
                }
            });
        });
    },

    initRefreshButtons() {
        // Refresh budget chart functionality
        const refreshBtn = document.getElementById('refresh-budget-chart');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                this.innerHTML = '<div class="spinner"></div>';
                
                // Simulate data refresh
                setTimeout(() => {
                    // Update chart with new random data
                    if (budgetChart) {
                        const newData = [
                            Math.floor(Math.random() * 30) + 10,
                            Math.floor(Math.random() * 30) + 10,
                            Math.floor(Math.random() * 30) + 10,
                            Math.floor(Math.random() * 30) + 10,
                            Math.floor(Math.random() * 30) + 10
                        ];
                        
                        budgetChart.data.datasets[0].data = newData;
                        budgetChart.update();
                    }
                    
                    this.innerHTML = '<i class="bx bx-refresh text-xl"></i>';
                }, 1000);
            });
        }
        
        // View all buttons functionality
        const viewAllTransactions = document.getElementById('view-all-transactions');
        if (viewAllTransactions) {
            viewAllTransactions.addEventListener('click', function() {
                // Navigate to all transactions page
                const allTransactionsItem = document.querySelector('.submenu-item[data-page="all-transactions"]');
                if (allTransactionsItem) {
                    allTransactionsItem.click();
                }
            });
        }
        
        const viewAllDueDates = document.getElementById('view-all-due-dates');
        if (viewAllDueDates) {
            viewAllDueDates.addEventListener('click', function() {
                // Navigate to due dates page
                const dueDatesItem = document.querySelector('.submenu-item[data-page="all-due-dates"]');
                if (dueDatesItem) {
                    dueDatesItem.click();
                }
            });
        }
        
        const viewAllNotifications = document.getElementById('view-all-notifications');
        if (viewAllNotifications) {
            viewAllNotifications.addEventListener('click', function() {
                // Open notifications modal
                const notificationBtn = document.getElementById('notification-btn');
                if (notificationBtn) {
                    notificationBtn.click();
                }
            });
        }
    }
};