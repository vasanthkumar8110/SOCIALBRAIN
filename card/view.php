<?php
require_once __DIR__ . '/../functions.php';
require_once 'function.php';
sb_session_start_secure();

// Check if user is logged in
$currentUser = $_SESSION['user_id'] ?? 'guest';
$csrf = $_SESSION['csrf_token'] ?? null;
$csrf = is_string($csrf) && $csrf !== '' ? $csrf : bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf;
$cardBuilder = new CardBuilderFunctions();
$userProfile = $cardBuilder->getUserProfile($currentUser);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Business Card Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f4ff 0%, #e0e7ff 100%);
        }
        .option-card.selected {
            border-color: #4f46e5;
            background-color: #eef2ff;
            box-shadow: 0 0 0 2px #c7d2fe;
        }
        .animate-pulse-green {
            animation: pulse-green 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse-green {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
    </style>
</head>
<body class="min-h-screen text-gray-800">

    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🎴</span>
                <span class="font-bold text-xl text-indigo-900">CardBuilder Pro</span>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse-green"></span>
                    Live Sync Active
                </div>
                <div class="text-right">
                    <div class="font-medium"><?php echo htmlspecialchars($userProfile['profile']['name'] ?? 'Guest'); ?></div>
                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($userProfile['role'] ?? 'Guest'); ?></div>
                </div>
                <?php if(($userProfile['role'] ?? '') === 'admin'): ?>
                    <a href="admin.php" class="text-indigo-600 hover:text-indigo-800 font-medium">Admin Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col lg:flex-row gap-8">
        
        <!-- Selection Panel -->
        <div class="w-full lg:w-2/3 space-y-8">
            
            <!-- Materials -->
            <div class="bg-white rounded-2xl shadow p-6" id="section-material">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><span class="text-2xl">📄</span> Base Material</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4" id="grid-specialPaper">
                    <!-- Loaded dynamically -->
                    <div class="animate-pulse flex space-x-4">
                        <div class="flex-1 space-y-4 py-1">
                            <div class="h-24 bg-gray-200 rounded"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GSM -->
            <div class="bg-white rounded-2xl shadow p-6" id="section-gsm">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><span class="text-2xl">📏</span> Thickness (GSM)</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="grid-paper">
                    <!-- Loaded dynamically -->
                </div>
            </div>

            <!-- Print Color -->
            <div class="bg-white rounded-2xl shadow p-6" id="section-print">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><span class="text-2xl">🎨</span> Print Color</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4" id="grid-printColor">
                    <!-- Loaded dynamically -->
                </div>
            </div>

             <!-- Lamination -->
             <div class="bg-white rounded-2xl shadow p-6" id="section-lamination">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><span class="text-2xl">✨</span> Lamination</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4" id="grid-lamination">
                    <!-- Loaded dynamically -->
                </div>
            </div>

             <!-- Effect -->
             <div class="bg-white rounded-2xl shadow p-6" id="section-effect">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><span class="text-2xl">⚡</span> Special Effects</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4" id="grid-effect">
                    <!-- Loaded dynamically -->
                </div>
            </div>

             <!-- Corner -->
             <div class="bg-white rounded-2xl shadow p-6" id="section-corner">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><span class="text-2xl">🔄</span> Corner Style</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4" id="grid-corner">
                    <!-- Loaded dynamically -->
                </div>
            </div>

        </div>

        <!-- Preview & Summary Panel -->
        <div class="w-full lg:w-1/3">
            <div class="bg-white rounded-2xl shadow p-6 sticky top-24">
                <h2 class="text-xl font-bold mb-6">Your Specification</h2>
                
                <div class="space-y-4 mb-8" id="summary-list">
                    <div class="text-gray-500 italic text-sm">Start selecting options...</div>
                </div>

                <div class="border-t pt-4 mb-6">
                    <div class="flex justify-between items-center text-lg font-bold">
                        <span>Total Price</span>
                        <span class="text-2xl text-indigo-600">₹<span id="total-price">0</span></span>
                    </div>
                </div>

                <button onclick="saveSpecification()" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    Save Specification
                </button>
            </div>
        </div>

    </main>

    <script>
        const API_URL = 'function.php';
        const CSRF = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
        let currentState = {
            materials: {},
            selection: {},
            lastTimestamp: null
        };
        const userId = '<?php echo $currentUser; ?>';

        // Initial Load
        document.addEventListener('DOMContentLoaded', () => {
             // Immediate first load
            fetchData();
            // Start polling
            setInterval(fetchData, 2000); // 2 seconds poll
        });

        async function fetchData() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_realtime_data');
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData,
                    headers: CSRF ? {'X-CSRF-Token': CSRF} : {}
                });
                const result = await response.json();
                
                if (result.success) {
                    updateUI(result.data);
                }
            } catch (error) {
                console.error("Connection error", error);
            }
        }

        function updateUI(data) {
            // Only redraw if data changed or first load
            // For simplicity in this demo, we'll redraw the grids if the materials hash changes, 
            // or just intelligently update prices.
            // Let's implement a smart update:
            
            const categories = ['specialPaper', 'paper', 'printColor', 'lamination', 'effect', 'corner'];
            
            categories.forEach(cat => {
                const items = data.materials[cat] || [];
                const grid = document.getElementById(`grid-${cat}`);
                
                // If grid is empty, render all. 
                // In a real diff algorithm, we'd check ID by ID.
                if (grid.children.length === 0 || grid.querySelector('.animate-pulse')) {
                    grid.innerHTML = items.map(item => renderItem(cat, item)).join('');
                } else {
                    // Update prices/names in place if they exist, append if new
                    items.forEach(item => {
                        const existingEl = document.getElementById(`item-${cat}-${item.id}`);
                        if (existingEl) {
                            // Update price text
                            existingEl.querySelector('.price-tag').textContent = `₹${item.price}`;
                            existingEl.querySelector('.name-tag').textContent = item.name;
                        } else {
                            // New item appeared
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = renderItem(cat, item);
                            grid.appendChild(tempDiv.firstChild);
                        }
                    });

                    // Remove deleted items
                    const currentIds = items.map(i => i.id);
                    Array.from(grid.children).forEach(child => {
                         const childId = child.getAttribute('data-id');
                         if(!currentIds.includes(childId)) {
                             child.remove();
                         }
                    });
                }
            });

            // Update Global State for calculation
            currentState.materials = data.materials;
            
            // Re-calculate Total (in case admin changed a price of a selected item)
            calculateTotal();
        }

        function renderItem(category, item) {
            const isSelected = currentState.selection[category] === item.id ? 'selected' : '';
            return `
                <div id="item-${category}-${item.id}" 
                     data-id="${item.id}"
                     class="option-card ${isSelected} cursor-pointer border rounded-xl p-4 hover:shadow-md transition-all bg-white"
                     onclick="selectItem('${category}', '${item.id}')">
                    <div class="name-tag font-semibold text-gray-800 mb-1">${item.name}</div>
                    <div class="text-sm text-gray-500 mb-2">${item.description || ''}</div>
                    <div class="price-tag font-bold text-indigo-600 bg-indigo-50 inline-block px-2 py-1 rounded text-sm">₹${item.price}</div>
                </div>
            `;
        }

        function selectItem(category, id) {
            // Toggle logic or Radio logic? Assuming Radio for this app
            currentState.selection[category] = id;
            
            // Update Visuals
            const grid = document.getElementById(`grid-${category}`);
            Array.from(grid.children).forEach(el => el.classList.remove('selected'));
            document.getElementById(`item-${category}-${id}`).classList.add('selected');
            
            updateSummary();
            calculateTotal();
        }

        function updateSummary() {
            const list = document.getElementById('summary-list');
            list.innerHTML = '';
            
            Object.entries(currentState.selection).forEach(([cat, id]) => {
                // Find item details
                // We need to look through all categories in materials
                const materials = currentState.materials[cat] || [];
                const item = materials.find(m => m.id === id);
                
                if (item) {
                     list.innerHTML += `
                        <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg">
                            <span class="font-medium text-gray-700">${item.name}</span>
                            <span class="text-indigo-600 font-bold">₹${item.price}</span>
                        </div>
                     `;
                }
            });
        }

        function calculateTotal() {
            let total = 0;
            Object.entries(currentState.selection).forEach(([cat, id]) => {
                const materials = currentState.materials[cat] || [];
                const item = materials.find(m => m.id === id);
                if (item) total += parseInt(item.price);
            });
            
            document.getElementById('total-price').textContent = total;
            return total;
        }

        async function saveSpecification() {
            if (Object.keys(currentState.selection).length === 0) {
                alert("Please select at least one material.");
                return;
            }

            const total = calculateTotal();
            const formData = new FormData();
            formData.append('action', 'save_specification');
            formData.append('user_id', userId);
            formData.append('selection', JSON.stringify(currentState.selection));
            
            try {
                const response = await fetch(API_URL, { method: 'POST', body: formData, headers: CSRF ? {'X-CSRF-Token': CSRF} : {} });
                const result = await response.json();
                
                if (result.success) {
                    alert("Specification Saved! ID: " + result.data.specId);
                    // Reset?
                    currentState.selection = {};
                    updateSummary();
                    calculateTotal();
                    document.querySelectorAll('.option-card').forEach(el => el.classList.remove('selected'));
                }
            } catch (e) {
                alert("Error saving");
            }
        }
    </script>
</body>
</html>
