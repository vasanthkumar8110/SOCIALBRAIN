<?php
require_once __DIR__ . '/../functions.php';
sb_session_start_secure();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit;
}
if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once 'function.php';
$cardBuilder = new CardBuilderFunctions();
$specs = $cardBuilder->getAllSpecifications();
$analytics = $cardBuilder->getAnalytics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Card Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .tab-btn.active { border-bottom: 2px solid #4f46e5; color: #4f46e5; font-weight: 600; }
        .glass-panel { background: white; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        @keyframes pulseOnce {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); background-color: #eef2ff; }
            100% { transform: scale(1); }
        }
        .animate-pulse-once { animation: pulseOnce 0.6s ease-out; }
        
        #notification-area::-webkit-scrollbar { width: 4px; }
        #notification-area::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 h-screen flex overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 z-10 hidden md:flex flex-col">
        <div class="h-16 flex items-center px-6 border-b border-gray-100">
            <h1 class="text-xl font-bold text-indigo-600">AdminPanel</h1>
        </div>
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <a href="#dashboard" onclick="showSection('dashboard')" class="nav-item flex items-center px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition-colors">
                <span class="mr-3">📊</span> Dashboard
            </a>
            <a href="#hierarchy" onclick="showSection('hierarchy')" class="nav-item flex items-center px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition-colors">
                <span class="mr-3">⚙️</span> Hierarchy Rules
            </a>
            <a href="#specifications" onclick="showSection('specifications')" class="nav-item flex items-center px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition-colors">
                <span class="mr-3">📝</span> Specifications
            </a>
            <a href="#design" onclick="showSection('design')" class="nav-item flex items-center px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition-colors">
                <span class="mr-3">🎨</span> Product Design
            </a>
        </nav>
        <!-- Real-time Notifications Center -->
        <div class="p-4 border-t border-gray-100 flex-none bg-gray-50">
            <h4 class="text-xs font-bold text-gray-400 mb-2 uppercase tracking-wider">Live Activity</h4>
            <div id="notification-area" class="space-y-2 max-h-48 overflow-y-auto pr-1">
                <p class="text-[10px] text-gray-400 italic">Listening for updates...</p>
            </div>
        </div>
        <div class="p-4 border-t border-gray-100 space-y-1">
             <!-- <a href="index.php" target="_blank" class="flex items-center px-4 py-3 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                <span class="mr-3">🔗</span> User View
            </a> -->
            <a href="logout.php" class="flex items-center px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors">
                <span class="mr-3">🚪</span> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-hidden">
        <!-- Header -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-30">
            <div class="flex items-center gap-4">
                <h2 id="page-title" class="text-lg font-semibold text-gray-800">Dashboard</h2>
                
                <!-- Project Switcher -->
                <div class="relative group ml-4">
                    <button class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition-colors border border-gray-200">
                        <span class="text-gray-500 text-xs uppercase tracking-wider">Profile:</span>
                        <span id="current-project-name" class="text-indigo-600 font-bold">Loading...</span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    
                    <!-- Dropdown -->
                    <div class="absolute top-full left-0 mt-2 w-56 bg-white border border-gray-200 rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all transform origin-top-left z-50">
                        <div class="p-2 border-b border-gray-100">
                            <span class="text-xs font-semibold text-gray-400 px-2">SWITCH PROFILE</span>
                        </div>
                        <div class="p-1 max-h-60 overflow-y-auto" id="project-list">
                            <!-- Projects loaded via JS -->
                        </div>
                        <div class="border-t border-gray-100 p-2 bg-gray-50 rounded-b-xl">
                            <button onclick="createNewProject()" class="w-full text-left px-3 py-2 text-sm text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg flex items-center justify-center gap-2 shadow-sm transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                <span>Create New Profile</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="text-xs text-gray-400 hidden md:block">All changes autosave</div>
                <div class="h-6 w-px bg-gray-200 hidden md:block"></div>
                <!-- Desktop View Store -->
                <a href="index.php?project=<?php echo htmlspecialchars($_GET['project'] ?? 'default'); ?>" target="_blank" class="text-sm font-medium text-gray-500 hover:text-indigo-600 flex items-center gap-1 transition-colors">
                    <span>View Store</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
            </div>
        </header>

        <!-- Content Area -->
        <div class="flex-1 overflow-auto p-8 bg-gray-50">
            
            <!-- DASHBOARD SECTION -->
            <div id="dashboard-section" class="section-content space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                     <div class="glass-panel p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Total Users</div>
                        <div class="text-3xl font-bold"><?php echo $analytics['totalUsers'] ?? 0; ?></div>
                     </div>
                     <div class="glass-panel p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Total Orders</div>
                        <div class="text-3xl font-bold"><?php echo $analytics['totalSpecifications'] ?? 0; ?></div>
                     </div>
                     <div class="glass-panel p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Revenue</div>
                        <div class="text-3xl font-bold text-green-600">₹<?php echo $analytics['revenue'] ?? 0; ?></div>
                     </div>
                </div>
                <div class="glass-panel p-6">
                    <h3 class="text-lg font-bold mb-4">Popular Materials</h3>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <?php 
                        if (!empty($analytics['popularMaterials'])) {
                            arsort($analytics['popularMaterials']);
                            $i=0;
                            foreach ($analytics['popularMaterials'] as $mat => $usage) {
                                if($i++ > 7) break;
                                echo "<div class='flex justify-between p-3 bg-gray-50 rounded text-sm'><span>".htmlspecialchars($mat)."</span><span class='font-bold'>$usage</span></div>";
                            }
                        } else { echo "<p class='text-gray-400 text-sm'>No data yet.</p>"; }
                        ?>
                    </div>
                </div>
            </div>

            <!-- PRODUCT DESIGN SECTION -->
            <div id="design-section" class="section-content hidden space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Dimensions -->
                    <div class="glass-panel p-6">
                        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                            <span>📐</span> Card Dimensions
                        </h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Width (cm)</label>
                                <input type="number" step="0.1" id="design-width" onchange="updateDesignField('dimensions', 'width', this.value)" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Height (cm)</label>
                                <input type="number" step="0.1" id="design-height" onchange="updateDesignField('dimensions', 'height', this.value)" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-3 italic">* This controls the aspect ratio of the 3D preview.</p>
                    </div>

                    <!-- Assets -->
                    <div class="glass-panel p-6">
                        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                            <span>📸</span> Preview Assets
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Background Image URL</label>
                                <div class="flex gap-2">
                                    <input type="text" id="design-image" onchange="updateDesignField('assets', 'previewImage', this.value)" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none" placeholder="https://...">
                                    <label class="bg-gray-100 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-200 text-xs flex items-center">
                                        📁
                                        <input type="file" class="hidden" accept="image/*" onchange="uploadAsset('previewImage', this)">
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Video Overlay URL (Optional)</label>
                                <div class="flex gap-2">
                                    <input type="text" id="design-video" onchange="updateDesignField('assets', 'previewVideo', this.value)" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none" placeholder="https://...">
                                    <label class="bg-gray-100 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-200 text-xs flex items-center">
                                        📹
                                        <input type="file" class="hidden" accept="video/*" onchange="uploadAsset('previewVideo', this)">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Block -->
                <div class="glass-panel p-6">
                    <h3 class="text-lg font-bold mb-4">Live Preview</h3>
                    <div class="flex justify-center bg-gray-100 rounded-xl p-10 min-h-[300px] items-center">
                         <div id="admin-card-preview" class="bg-white shadow-xl transition-all duration-500 flex items-center justify-center overflow-hidden relative" style="width: 170px; height: 110px; border-radius: 4px;">
                             <span class="text-gray-300 text-xs">Card Face</span>
                             <div id="admin-card-bg" class="absolute inset-0 bg-cover bg-center"></div>
                             <video id="admin-card-video" class="absolute inset-0 w-full h-full object-cover hidden" loop muted autoplay></video>
                         </div>
                    </div>
                </div>
            </div>

            <!-- HIERARCHY MANAGER (NEW) -->
            <div id="hierarchy-section" class="section-content hidden h-full flex flex-col">
                <div class="flex flex-col lg:flex-row gap-6 h-full">
                    
                    <!-- LEFT: Root Papers List -->
                    <div class="w-full lg:w-1/3 glass-panel flex flex-col h-full">
                        <div class="p-4 border-b border-gray-100 bg-gray-50 rounded-t-xl">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="font-bold text-gray-700">1. Root Materials</h3>
                                <button onclick="addRootItem()" class="text-xs bg-indigo-600 text-white px-2 py-1 rounded hover:bg-indigo-700">+ Add</button>
                            </div>
                            <!-- Root Label Editor -->
                             <div class="flex items-center gap-2">
                                 <span class="text-xs text-gray-400">Label:</span>
                                 <input type="text" id="root-label-input" value="Material Foundation" onchange="updateRootLabel(this.value)" class="flex-1 bg-transparent border-b border-gray-300 text-xs font-semibold text-gray-600 focus:border-indigo-500 focus:outline-none py-1">
                            </div>
                        </div>
                        <div class="p-0 overflow-y-auto flex-1">
                             <div id="root-list" class="divide-y divide-gray-100">
                                 <!-- Loaded JS -->
                             </div>
                        </div>
                    </div>

                    <!-- RIGHT: Details & Children -->
                    <div class="w-full lg:w-2/3 glass-panel flex flex-col h-full relative">
                        <!-- Blocked Overlay if no root selected -->
                        <div id="no-root-selected" class="absolute inset-0 bg-white/50 backdrop-blur-sm z-20 flex items-center justify-center rounded-xl">
                            <div class="text-center text-gray-500">
                                <div class="text-4xl mb-2">👈</div>
                                <p>Select a Root Material to configure its rules</p>
                            </div>
                        </div>

                        <!-- Header -->
                        <div class="p-6 border-b border-gray-100 flex justify-between items-start">
                             <div>
                                 <h2 id="selected-root-name" class="text-2xl font-bold text-gray-800">Select Root</h2>
                                 <p class="text-sm text-gray-500 mt-1">Configure available options for this material</p>
                             </div>
                             <div class="flex flex-col items-end gap-2">
                                 <div class="flex gap-2">
                                     <button onclick="editRootPrice()" class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded text-gray-700 font-medium flex items-center">
                                        💰 <span id="selected-root-price" class="ml-1">0</span>
                                     </button>
                                     <button onclick="deleteSelectedRoot()" class="text-sm bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded text-red-600 font-medium">Delete</button>
                                 </div>
                                 <div class="flex flex-col items-end gap-2">
                                     <!-- Texture Uploader (Engine) -->
                                     <div class="flex items-center gap-2">
                                         <span class="text-[10px] text-gray-400 font-bold uppercase">3D Texture</span>
                                         <img id="root-texture-preview" src="" class="w-8 h-8 rounded border border-gray-200 object-cover hidden">
                                         <label class="cursor-pointer text-xs bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded hover:bg-indigo-100 border border-indigo-200">
                                             Upload
                                             <input type="file" class="hidden" accept="image/*" onchange="uploadTexture(this)">
                                         </label>
                                     </div>
                                     <!-- Thumbnail Uploader (UI) -->
                                     <div class="flex items-center gap-2">
                                         <span class="text-[10px] text-gray-400 font-bold uppercase">Display Thumb</span>
                                         <img id="root-thumbnail-preview" src="" class="w-8 h-8 rounded border border-gray-200 object-cover hidden">
                                         <label class="cursor-pointer text-xs bg-orange-50 text-orange-600 px-3 py-1.5 rounded hover:bg-orange-100 border border-orange-200">
                                             Upload
                                             <input type="file" class="hidden" accept="image/*" onchange="uploadThumbnail(this)">
                                         </label>
                                     </div>
                                 </div>
                             </div>
                        </div>

                        <!-- Dynamic Tabs -->
                        <div class="flex border-b border-gray-200 px-6 overflow-x-auto" id="dynamic-tabs-container">
                             <!-- Loaded JS -->
                        </div>
                        <div class="px-6 py-2 bg-gray-50 border-b border-gray-200 flex justify-end">
                            <button onclick="addNewStep()" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Add New Step</button>
                        </div>

                        <!-- Tab Content -->
                        <div class="p-6 flex-1 overflow-y-auto bg-gray-50/50">
                            
                            <!-- Search / Add bar -->
                            <div class="flex gap-2 mb-4">
                                <input type="text" id="new-option-input" placeholder="Add new option..." class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <input type="number" id="new-option-price" placeholder="Price" class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <button onclick="addNewOption()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">Add</button>
                            </div>

                            <div id="options-list" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <!-- Options checkboxes loaded here -->
                            </div>
                            
                            <div class="mt-8 pt-4 border-t border-gray-200">
                                <button onclick="deleteCurrentStep()" class="text-xs text-red-400 hover:text-red-600">Delete this Step Category</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SPECIFICATIONS SECTION -->
            <div id="specifications-section" class="section-content hidden">
                 <!-- ... (Unchanged) ... -->
                 <div class="glass-panel overflow-hidden">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-4">ID</th>
                                <th class="px-6 py-4">Customer</th> <!-- Changed from User -->
                                <th class="px-6 py-4">Details</th>
                                <th class="px-6 py-4">Total</th>
                                <th class="px-6 py-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" id="specs-table-body">
                            <?php foreach ($specs as $spec): ?>
                            <tr class="bg-white hover:bg-gray-50">
                                <td class="px-6 py-4 font-mono text-xs text-gray-400">#<?php echo substr($spec['id'], -6); ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($spec['customer']['name'] ?? 'Guest'); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($spec['customer']['phone'] ?? ''); ?></div>
                                </td>
                                <td class="px-6 py-4 text-xs leading-relaxed max-w-xs">
                                    <?php 
                                    $flat = array_filter(array_values($spec['selection']), fn($v) => !is_null($v));
                                    echo implode('<span class="mx-1 text-gray-300">/</span>', $flat);
                                    ?>
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-900">₹<?php echo $spec['totalPrice']; ?></td>
                                <td class="px-6 py-4"><span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Active</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- JS LOGIC -->
    <script>
        const API = 'function.php';
        const CSRF = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
        let state = {
            config: {},
            prices: {},
            rootRules: {},
            steps: [], 
            activeTab: null,
            selectedRoot: null,
            dimensions: { width: 8.5, height: 5.5, unit: 'cm' },
            assets: { previewImage: null, previewVideo: null }
        };

        const NOTIFICATION_INTERVAL = 30000; // 30 seconds
        let lastTimestamp = null;

        // Toast Notification System
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-20 right-4 px-6 py-3 rounded-lg shadow-xl text-white font-medium z-[9999] transition-all transform`;
            toast.style.cssText = `
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
                animation: slideIn 0.3s ease-out forwards;
            `;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-in forwards';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function showError(message) {
            showToast('❌ ' + message, 'error');
        }

        function showSuccess(message) {
            showToast('✅ ' + message, 'success');
        }

        function showWarning(message) {
            showToast('⚠️ ' + message, 'warning');
        }

        // Add animation styles
        if (!document.getElementById('toast-animations')) {
            const style = document.createElement('style');
            style.id = 'toast-animations';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(400px); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(400px); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }

        // --- Init ---
        async function loadData() {
            try {
                console.log('🔄 Loading data...');
                
                // Get current project from URL
                const urlParams = new URLSearchParams(window.location.search);
                const currentProject = urlParams.get('project') || 'default';
                console.log('🏷️ Current project:', currentProject);
                
                // Load Projects List
                loadProjects(currentProject);

                const fd = new FormData();
                fd.append('action', 'get_ui_config');
                fd.append('project', currentProject);
                
                console.log('🌐 Fetching config from API...');
                const res = await fetch(API, {method:'POST', body:fd});
                console.log('📡 Response status:', res.status, res.statusText);
                
                const json = await res.json();
                console.log('📥 Received data:', json);
                
                if(json.success && json.data?.config) {
                    console.log('✅ Valid config received');
                    
                    // Force normalization before merge
                    if (Array.isArray(json.data.rootRules)) {
                        console.log('⚠️ Normalizing rootRules from array to object');
                        json.data.rootRules = {};
                    }
                    if (Array.isArray(json.data.prices)) {
                        console.log('⚠️ Normalizing prices from array to object');
                        json.data.prices = {};
                    }
                    
                    state = { ...state, ...json.data };
                    
                    // Normalize dimensions & assets
                    if(!state.dimensions) state.dimensions = { width: 8.5, height: 5.5, unit: 'cm' };
                    if(!state.assets) state.assets = { previewImage: null, previewVideo: null };

                    // Root Label Handling
                    if(!state.rootLabel) state.rootLabel = "Material Foundation";
                    const labelInput = document.getElementById('root-label-input');
                    if(labelInput) labelInput.value = state.rootLabel;

                    // Normalize steps if missing
                    if(!state.steps || state.steps.length === 0) {
                        console.log('⚠️ No steps found, using defaults');
                        state.steps = [
                            {id:'papers', label:'2. GSM / Paper'},
                            {id:'lamination', label:'3. Lamination'},
                            {id:'effects', label:'4. Effects'},
                            {id:'corners', label:'5. Corners'}
                        ];
                    }
                    if(!state.rootRules) {
                        console.log('⚠️ No rootRules found, initializing empty object');
                        state.rootRules = {};
                    }
                } else {
                    console.log('⚠️ No valid config, using defaults');
                    initDefaults();
                }
                
                console.log('🎨 Rendering UI...');
                renderRoots();
                renderTabs();
                renderDesign(); // New: Populate design tab
                startNotificationPolling(); // New: Start live activity

                if(state.selectedRoot) {
                    console.log('🎯 Selecting root:', state.selectedRoot);
                    selectRoot(state.selectedRoot);
                }
                console.log('✅ Load complete');
            } catch(e) { 
                console.error('❌ Load error:', e);
                console.error('Error stack:', e.stack);
                showError('Failed to load data: ' + e.message);
            }
        }

        function updateRootLabel(val) {
            state.rootLabel = val || "Material Foundation";
            saveState();
        }

        async function loadProjects(current) {
            const fd = new FormData();
            fd.append('action', 'get_projects');
            const res = await (await fetch(API, {method:'POST', body:fd})).json();
            if(res.success) {
                document.getElementById('current-project-name').textContent = current === 'default' ? 'Default Profile' : current;
                
                const list = document.getElementById('project-list');
                list.innerHTML = res.data.map(p => {
                    const isDefault = p === 'default';
                    return `
                    <div class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-50 group">
                        <a href="?project=${p}" class="text-sm text-gray-700 flex-1 ${p === current ? 'font-bold text-indigo-700' : ''}">
                            ${p === 'default' ? 'Default Profile' : p}
                            ${p === current ? '<span class="ml-2 inline-block w-2 h-2 rounded-full bg-indigo-500"></span>' : ''}
                        </a>
                        ${!isDefault ? `
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="renameProject('${p}')" class="p-1 hover:bg-gray-200 rounded text-gray-500 hover:text-indigo-600" title="Rename">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </button>
                            <button onclick="deleteProject('${p}')" class="p-1 hover:bg-red-50 rounded text-gray-500 hover:text-red-600" title="Delete">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                        ` : ''}
                    </div>
                `;}).join('');
            }
        }

        async function createNewProject() {
            const name = prompt("Enter new Profile Name (e.g. 'BusinessCards', 'Weddings'):");
            if(name) {
                const fd = new FormData();
                fd.append('action', 'create_project');
                fd.append('name', name);
                const res = await (await fetch(API, {method:'POST', body:fd})).json();
                if(res.success) {
                    window.location.href = `?project=${name}`;
                } else {
                    alert(res.message || 'Failed to create profile');
                }
            }
        }

        async function renameProject(oldName) {
            const newName = prompt(`Rename profile "${oldName}" to:`, oldName);
            if(newName && newName !== oldName) {
                const fd = new FormData();
                fd.append('action', 'rename_project');
                fd.append('old_name', oldName);
                fd.append('new_name', newName);
                const res = await (await fetch(API, {method:'POST', body:fd})).json();
                if(res.success) {
                    // Redirect if current
                    const urlParams = new URLSearchParams(window.location.search);
                    if(urlParams.get('project') === oldName) {
                        window.location.href = `?project=${newName}`;
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert('Failed to rename.');
                }
            }
        }

        async function deleteProject(name) {
            if(confirm(`Are you sure you want to DELETE profile "${name}"?\nThis cannot be undone!`)) {
                const fd = new FormData();
                fd.append('action', 'delete_project');
                fd.append('name', name);
                const res = await (await fetch(API, {method:'POST', body:fd})).json();
                if(res.success) {
                    // Redirect if current
                    const urlParams = new URLSearchParams(window.location.search);
                    if(urlParams.get('project') === name) {
                        window.location.href = `?project=default`;
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert('Failed to delete.');
                }
            }
        }
        
        // Wrap all fetch calls to include project + CSRF
        const _origFetch = window.fetch;
        window.fetch = async (url, options) => {
            if(url === API && options.method === 'POST' && options.body instanceof FormData) {
                const urlParams = new URLSearchParams(window.location.search);
                const p = urlParams.get('project');
                if(p) options.body.append('project', p);
                if (CSRF) {
                    options.headers = options.headers || {};
                    if (!('X-CSRF-Token' in options.headers)) {
                        options.headers['X-CSRF-Token'] = CSRF;
                    }
                }
            }
            return _origFetch(url, options);
        };

        function initDefaults() {
            state.config = {
                specialPapers: ['Kraft Paper', 'Textured Paper', 'PVC Card'],
                papers: ['300 GSM', '400 GSM'],
                lamination: ['Matt', 'Gloss'],
                effects: ['None', 'Spot UV'],
                corners: ['Sharp']
            };
            state.steps = [
                {id:'papers', label:'2. GSM / Paper'},
                {id:'lamination', label:'3. Lamination'},
                {id:'effects', label:'4. Effects'},
                {id:'corners', label:'5. Corners'}
            ];
            state.prices = {};
            state.rootRules = {};
            saveState();
        }

        async function saveState() {
            try {
                console.log('💾 Saving state...', {
                    roots: state.config.specialPapers?.length || 0,
                    steps: state.steps.length,
                    selectedRoot: state.selectedRoot,
                    rootRulesKeys: Object.keys(state.rootRules || {}).length,
                    pricesKeys: Object.keys(state.prices || {}).length
                });

                // Log the actual state being saved
                console.log('📦 State object:', JSON.parse(JSON.stringify(state)));

                const fd = new FormData();
                fd.append('action', 'save_ui_config');
                
                const stateJSON = JSON.stringify(state);
                console.log('📝 State JSON length:', stateJSON.length, 'bytes');
                fd.append('config', stateJSON);
                
                // Add project context if exists
                const urlParams = new URLSearchParams(window.location.search);
                const project = urlParams.get('project');
                if(project) {
                    fd.append('project', project);
                    console.log('🏷️ Saving to project:', project);
                }

                console.log('🌐 Sending request to API...');
                const response = await fetch(API, {method:'POST', body:fd});
                
                console.log('📡 Response status:', response.status, response.statusText);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const json = await response.json();
                console.log('📥 Response data:', json);
                
                if (!json.success) {
                    throw new Error(json.message || 'Unknown server error');
                }

                console.log('✅ State saved successfully');
                return true;
            } catch (error) {
                console.error('❌ Save failed:', error);
                console.error('Error stack:', error.stack);
                showError('Failed to save: ' + error.message);
                throw error;
            }
        }
        
        // --- Tab Management ---
        function renderTabs() {
            const container = document.getElementById('dynamic-tabs-container');
            if(!state.steps.length) { container.innerHTML = '<div class="p-4 text-xs text-gray-400">No steps defined. Add one.</div>'; return; }
            
            if(!state.activeTab && state.steps.length > 0) state.activeTab = state.steps[0].id;

            container.innerHTML = state.steps.map((step, index) => `
                <button onclick="switchTab('${step.id}')" 
                    class="tab-btn px-4 py-3 text-sm font-medium whitespace-nowrap cursor-move ${state.activeTab === step.id ? 'active border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700'}
                    transition-all duration-200" 
                    data-tab="${step.id}"
                    data-index="${index}"
                    draggable="true"
                    ondragstart="handleDragStart(event, ${index})"
                    ondragover="handleDragOver(event)"
                    ondrop="handleDrop(event, ${index})"
                    ondragend="handleDragEnd(event)"
                    oncontextmenu="renameStep('${step.id}'); return false;">
                    <span class="drag-handle mr-2 opacity-40">⋮⋮</span>${step.label}
                </button>
            `).join('');
            
            renderOptions();
        }

        // Drag-and-Drop Handlers
        let draggedIndex = null;

        function handleDragStart(event, index) {
            draggedIndex = index;
            event.currentTarget.style.opacity = '0.5';
        }

        function handleDragOver(event) {
            event.preventDefault(); // Allow drop
            event.currentTarget.style.background = '#f0f7ff';
        }

        function handleDrop(event, dropIndex) {
            event.preventDefault();
            event.currentTarget.style.background = '';
            
            if (draggedIndex === null || draggedIndex === dropIndex) return;

            // Reorder steps array
            const draggedStep = state.steps[draggedIndex];
            state.steps.splice(draggedIndex, 1);
            state.steps.splice(dropIndex, 0, draggedStep);

            // Update labels with new numbering
            state.steps.forEach((step, idx) => {
                // Extract the label without number prefix
                const labelWithoutNumber = step.label.replace(/^\d+\.\s*/, '');
                step.label = `${idx + 2}. ${labelWithoutNumber}`;
            });

            draggedIndex = null;
            saveState();
            renderTabs();
        }

        function handleDragEnd(event) {
            event.currentTarget.style.opacity = '1';
            // Clear all backgrounds
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.style.background = '';
            });
        }

        function switchTab(tabId) {
            state.activeTab = tabId;
            renderTabs(); 
        }

        function addNewStep() {
            const label = prompt("Step Name (e.g. 'Size', 'Packaging'):");
            if(label) {
                // Generate ID
                const id = label.toLowerCase().replace(/[^a-z0-9]/g, '') + '_' + Date.now().toString().slice(-4);
                state.steps.push({id, label});
                state.config[id] = []; // Init empty options array
                state.activeTab = id;
                saveState();
                renderTabs();
            }
        }
        
        function renameStep(id) {
            const step = state.steps.find(s => s.id === id);
            if(!step) return;
            const newLabel = prompt("Rename Step:", step.label);
            if(newLabel) {
                 step.label = newLabel;
                 saveState();
                 renderTabs();
            }
        }

        function deleteCurrentStep() {
            if(!state.activeTab) return;
            const stepIdx = state.steps.findIndex(s => s.id === state.activeTab);
            const step = state.steps[stepIdx];
            
            if(confirm(`Delete Step "${step.label}" and all its data?`)) {
                // Remove from steps
                state.steps.splice(stepIdx, 1);
                // Remove from config (options list)
                delete state.config[step.id];
                // Remove from rules
                Object.keys(state.rootRules).forEach(r => {
                    if(state.rootRules[r][step.id]) delete state.rootRules[r][step.id];
                });
                // Remove prices
                if(state.prices[step.id]) delete state.prices[step.id];

                // Set new active
                state.activeTab = state.steps[0]?.id || null;
                saveState();
                renderTabs();
            }
        }

        // Delete individual option from a step
        function deleteGlobalOption(categoryKey, optionName) {
            if(!confirm(`Delete "${optionName}" from this step?\n\nThis will remove it from all root materials and delete its price.`)) {
                return;
            }

            // 1. Remove from config (global options list)
            if(state.config[categoryKey]) {
                const idx = state.config[categoryKey].indexOf(optionName);
                if(idx > -1) {
                    state.config[categoryKey].splice(idx, 1);
                }
            }

            // 2. Remove from all root rules
            Object.keys(state.rootRules).forEach(rootName => {
                if(state.rootRules[rootName][categoryKey]) {
                    const ruleIdx = state.rootRules[rootName][categoryKey].indexOf(optionName);
                    if(ruleIdx > -1) {
                        state.rootRules[rootName][categoryKey].splice(ruleIdx, 1);
                    }
                }
            });

            // 3. Remove price
            if(state.prices[categoryKey] && state.prices[categoryKey][optionName]) {
                delete state.prices[categoryKey][optionName];
            }

            // 4. Remove metadata (thickness, etc.)
            Object.keys(state.rootRules).forEach(rootName => {
                if(state.rootRules[rootName].meta) {
                    const metaKey = `thick_${categoryKey}_${optionName}`;
                    if(state.rootRules[rootName].meta[metaKey]) {
                        delete state.rootRules[rootName].meta[metaKey];
                    }
                }
            });

            saveState();
            renderOptions(); // Refresh the list
        }

        // --- Sections & Tabs Helpers ---
        function showSection(id) {
            document.querySelectorAll('.section-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(id + '-section').classList.remove('hidden');
            document.getElementById('page-title').textContent = id.charAt(0).toUpperCase() + id.slice(1).replace('-', ' ');
            
            // Highlight nav item
            document.querySelectorAll('.nav-item').forEach(el => {
                const href = el.getAttribute('href');
                if(href === '#' + id) {
                    el.classList.add('bg-indigo-50', 'text-indigo-600');
                } else {
                    el.classList.remove('bg-indigo-50', 'text-indigo-600');
                }
            });
        }

        // --- Design Management ---
        function renderDesign() {
            if(!state.dimensions) return;
            
            document.getElementById('design-width').value = state.dimensions.width;
            document.getElementById('design-height').value = state.dimensions.height;
            document.getElementById('design-image').value = state.assets.previewImage || '';
            document.getElementById('design-video').value = state.assets.previewVideo || '';
            
            updateLiveDesignPreview();
        }

        function updateDesignField(category, field, value) {
            if(!state[category]) state[category] = {};
            state[category][field] = value;
            updateLiveDesignPreview();
            saveState();
        }

        function updateLiveDesignPreview() {
            const preview = document.getElementById('admin-card-preview');
            const bg = document.getElementById('admin-card-bg');
            const video = document.getElementById('admin-card-video');
            
            // Proportional scaling for preview (base 170x110)
            const w = parseFloat(state.dimensions.width) || 8.5;
            const h = parseFloat(state.dimensions.height) || 5.5;
            const ratio = h / w;
            
            const baseW = 200;
            preview.style.width = baseW + 'px';
            preview.style.height = (baseW * ratio) + 'px';
            
            if(state.assets.previewImage) {
                bg.style.backgroundImage = `url('${state.assets.previewImage}')`;
                bg.classList.remove('hidden');
            } else {
                bg.classList.add('hidden');
            }
            
            if(state.assets.previewVideo) {
                video.src = state.assets.previewVideo;
                video.classList.remove('hidden');
                video.play();
            } else {
                video.classList.add('hidden');
            }
        }

        async function uploadAsset(field, input) {
            if(!input.files[0]) return;
            
            const fd = new FormData();
            fd.append('action', 'upload_texture'); // Reuse existing uploader
            fd.append('texture', input.files[0]);
            
            try {
                const res = await (await fetch(API, {method:'POST', body:fd})).json();
                if(res.success) {
                    state.assets[field] = res.data.url;
                    renderDesign();
                    saveState();
                    showSuccess('Asset uploaded successfully');
                } else {
                    showError('Upload failed');
                }
            } catch(e) { showError('Network error'); }
        }

        // --- Real-time Notifications ---
        function startNotificationPolling() {
            pollNotifications();
            setInterval(pollNotifications, NOTIFICATION_INTERVAL);
        }

        async function pollNotifications() {
            const fd = new FormData();
            fd.append('action', 'get_realtime_data');
            
            try {
                const res = await (await fetch(API, {method:'POST', body:fd})).json();
                if(res.success && res.data.events) {
                    const area = document.getElementById('notification-area');
                    const events = res.data.events;
                    
                    // Check for new events
                    if(lastTimestamp) {
                        const newEvents = events.filter(e => e.timestamp > lastTimestamp);
                        newEvents.forEach(e => {
                            showToast(e.message, e.type === 'order' ? 'success' : 'info');
                        });
                    }
                    
                    lastTimestamp = res.data.timestamp;
                    
                    // Render list
                    area.innerHTML = events.map(e => `
                        <div class="p-2 bg-white rounded border border-gray-100 shadow-sm animate-pulse-once">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="text-[10px] uppercase font-bold ${e.type === 'order' ? 'text-green-600' : 'text-blue-600'}">
                                    ${e.type}
                                </span>
                                <span class="text-[9px] text-gray-400 font-mono">${e.timestamp.split(' ')[1] || ''}</span>
                            </div>
                            <p class="text-[11px] text-gray-700 leading-tight">${e.message}</p>
                        </div>
                    `).join('') || '<p class="text-[10px] text-gray-400 italic">No recent activity</p>';
                }
            } catch(e) { console.warn('Polling failed', e); }
        }

        // --- Root Management ---
        function renderRoots() {
            const list = document.getElementById('root-list');
            list.innerHTML = (state.config.specialPapers || []).map(item => `
                <div onclick="selectRoot('${item}')" 
                     class="p-4 cursor-pointer hover:bg-gray-50 flex justify-between items-center transition-colors ${state.selectedRoot === item ? 'bg-indigo-50 border-l-4 border-indigo-500' : 'border-l-4 border-transparent'}">
                    <span class="font-medium text-sm text-gray-800">${item}</span>
                    <span class="text-xs text-gray-400">₹${getPrice('specialPaper', item)}</span>
                </div>
            `).join('');
        }

        function selectRoot(root) {
            state.selectedRoot = root;
            document.getElementById('no-root-selected').classList.add('hidden');
            document.getElementById('selected-root-name').textContent = root;
            document.getElementById('selected-root-price').textContent = '₹' + getPrice('specialPaper', root);
            renderRoots(); // Refresh active state
            renderOptions();
        }

        function addRootItem() {
            const name = prompt("Enter new Root Material Name:");
            if(name && !state.config.specialPapers.includes(name)) {
                state.config.specialPapers.push(name);
                setPrice('specialPaper', name, 100);
                saveState();
                renderRoots();
                selectRoot(name);
            }
        }

        function deleteSelectedRoot() {
             if(!state.selectedRoot) return;
             if(confirm(`Delete ${state.selectedRoot}?`)) {
                state.config.specialPapers = state.config.specialPapers.filter(x => x !== state.selectedRoot);
                delete state.rootRules[state.selectedRoot];
                state.selectedRoot = null;
                document.getElementById('no-root-selected').classList.remove('hidden');
                saveState();
                renderRoots();
            }
        }
        
        function editRootPrice() {
             if(!state.selectedRoot) return;
            const current = getPrice('specialPaper', state.selectedRoot);
            const val = prompt("Enter Base Price for " + state.selectedRoot, current);
            if(val !== null) {
                setPrice('specialPaper', state.selectedRoot, parseFloat(val) || 0);
                document.getElementById('selected-root-price').textContent = '₹' + (parseFloat(val) || 0);
                saveState();
                renderRoots();
            }
        }

        // --- Option / Rules Management ---
        function renderOptions() {
            if(!state.selectedRoot || !state.activeTab) return;

            const categoryKey = state.activeTab;
            // Init config array for this step if missing
            if(!state.config[categoryKey]) state.config[categoryKey] = [];
            
            const allOptions = state.config[categoryKey];
            const root = state.selectedRoot;
        // --- New Metadata Handling ---
        function uploadTexture(input) {
            if(!state.selectedRoot || !input.files[0]) return;
            const fd = new FormData();
            fd.append('action', 'upload_texture');
            fd.append('texture', input.files[0]);
            fetch(API, {method:'POST', body:fd})
                .then(r => r.json())
                .then(res => {
                    if(res.success) {
                        setMeta(state.selectedRoot, 'texture', res.data.url);
                        saveState();
                        renderRootMeta();
                    } else { alert('Upload failed'); }
                });
        }

        function uploadThumbnail(input) {
            if(!state.selectedRoot || !input.files[0]) return;
            const fd = new FormData();
            fd.append('action', 'upload_texture'); // Reuse the generic image uploader
            fd.append('texture', input.files[0]);
            fetch(API, {method:'POST', body:fd})
                .then(r => r.json())
                .then(res => {
                    if(res.success) {
                        setMeta(state.selectedRoot, 'thumbnail', res.data.url);
                        saveState();
                        renderRootMeta();
                    } else { alert('Upload failed'); }
                });
        }

        function renderRootMeta() {
            if(!state.selectedRoot) return;
            
            // Texture Preview
            const tex = getMeta(state.selectedRoot, 'texture');
            const imgTex = document.getElementById('root-texture-preview');
            if(tex) {
                imgTex.src = tex;
                imgTex.classList.remove('hidden');
            } else { imgTex.classList.add('hidden'); }

            // Thumbnail Preview
            const thumb = getMeta(state.selectedRoot, 'thumbnail');
            const imgThumb = document.getElementById('root-thumbnail-preview');
            if(thumb) {
                imgThumb.src = thumb;
                imgThumb.classList.remove('hidden');
            } else { imgThumb.classList.add('hidden'); }
        }

        // Helper for deep metadata
        function getMeta(root, key) {
            return state.rootRules[root]?.meta?.[key];
        }
        function setMeta(root, key, val) {
            if(!state.rootRules[root]) state.rootRules[root] = {};
            if(!state.rootRules[root].meta) state.rootRules[root].meta = {};
            state.rootRules[root].meta[key] = val;
        }

        // Updated selectRoot to show meta
        const _origSelectRoot = selectRoot;
        selectRoot = function(root) {
            _origSelectRoot(root);
            renderRootMeta();
        }

        // Updated renderOptions to show Thickness for 'papers' or GSM tab
        const _origRenderOptions = renderOptions;
        renderOptions = function() {
            _origRenderOptions();
            
            // If current tab is 'papers' (GSM), add thickness inputs
            if(state.activeTab === 'papers' || state.activeTab.includes('gsm')) { // loose check
                // Inject inputs into rendered list
                const list = document.getElementById('options-list');
                const items = list.children;
                // re-loop to match rendered items
                const opts = state.config[state.activeTab] || [];
                
                // This is a bit hacky to patch existing DOM, but cleaner than complete rewrite
                Array.from(items).forEach((div, i) => {
                     const optName = opts[i]; // Assuming order preserved
                     // Check if verified name match in DOM to be safe
                     if(div.innerHTML.includes(optName)) {
                         const currentThick = getMeta(state.selectedRoot, `thick_${state.activeTab}_${optName}`) || 0;
                         
                         const thickDiv = document.createElement('div');
                         thickDiv.className = 'mt-2 border-t border-gray-100 pt-2 flex items-center gap-2';
                         thickDiv.innerHTML = `
                            <span class="text-xs text-gray-400">Thickness (px):</span>
                            <input type="number" class="w-16 border rounded text-xs px-2 py-1" value="${currentThick}" 
                                onchange="setMeta('${state.selectedRoot}', 'thick_${state.activeTab}_${optName}', this.value); saveState();">
                         `;
                         div.appendChild(thickDiv);
                     }
                });
            }
        }
            if(!state.rootRules[root]) state.rootRules[root] = {};
            if(!state.rootRules[root][categoryKey]) state.rootRules[root][categoryKey] = []; // Default empty? Or default All? New steps default empty is safer.
            
            const allowed = state.rootRules[root][categoryKey];

            const container = document.getElementById('options-list');
            container.innerHTML = allOptions.map(opt => {
                const isChecked = allowed.includes(opt);
                // Use categoryKey directly for price key in new dynamic system
                const price = getPrice(categoryKey, opt);
                
                return `
                <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg hover:border-indigo-300 transition-colors">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" onchange="toggleRule('${categoryKey}', '${opt}')" ${isChecked ? 'checked' : ''} class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300">
                        <span class="text-sm font-medium text-gray-700">${opt}</span>
                    </div>
                    <div class="flex items-center gap-3">
                         <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded cursor-pointer hover:bg-gray-200" onclick="editOptionPrice('${categoryKey}', '${opt}')">₹${price}</span>
                         <button onclick="deleteGlobalOption('${categoryKey}', '${opt}')" class="text-gray-400 hover:text-red-500">×</button>
                    </div>
                </div>
                `;
            }).join('');
        }

        function toggleRule(catKey, option) {
            try {
                const root = state.selectedRoot;
                
                if (!root) {
                    showError('No root material selected');
                    return;
                }

                if (!state.rootRules[root]) {
                    state.rootRules[root] = {};
                }
                
                if (!state.rootRules[root][catKey]) {
                    state.rootRules[root][catKey] = [];
                }
                
                const list = state.rootRules[root][catKey];
                const wasEnabled = list.includes(option);
                
                if (wasEnabled) {
                    state.rootRules[root][catKey] = list.filter(x => x !== option);
                    console.log(`🔴 Disabled: ${option} for ${root} → ${catKey}`);
                } else {
                    state.rootRules[root][catKey].push(option);
                    console.log(`🟢 Enabled: ${option} for ${root} → ${catKey}`);
                }
                
                // Save with feedback
                saveState()
                    .then(() => {
                        showSuccess(`${wasEnabled ? 'Disabled' : 'Enabled'}: ${option}`);
                    })
                    .catch(err => {
                        console.error('Save error:', err);
                        showError('Failed to save changes');
                        // Revert on error
                        if (wasEnabled) {
                            state.rootRules[root][catKey].push(option);
                        } else {
                            state.rootRules[root][catKey] = state.rootRules[root][catKey].filter(x => x !== option);
                        }
                        renderOptions();
                    });
            } catch (error) {
                console.error('❌ toggleRule error:', error);
                showError('Error: ' + error.message);
            }
        }

        function addNewOption() {
            const name = document.getElementById('new-option-input').value.trim();
            const price = parseFloat(document.getElementById('new-option-price').value) || 0;
            const catKey = state.activeTab;

            if(name && !state.config[catKey].includes(name)) {
                state.config[catKey].push(name);
                setPrice(catKey, name, price);
                toggleRule(catKey, name); // Auto-enable
                
                document.getElementById('new-option-input').value = '';
                document.getElementById('new-option-price').value = '';
                saveState();
                renderOptions();
            }
        }

        function deleteGlobalOption(catKey, option) {
             if(confirm(`Remove "${option}" globally?`)) {
                 state.config[catKey] = state.config[catKey].filter(x => x !== option);
                 // Cleanup rules
                 Object.keys(state.rootRules).forEach(r => {
                     if(state.rootRules[r][catKey]) {
                         state.rootRules[r][catKey] = state.rootRules[r][catKey].filter(x => x !== option);
                     }
                 });
                 saveState();
                 renderOptions();
             }
        }
        
        function editOptionPrice(catKey, option) {
            const current = getPrice(catKey, option);
            const val = prompt(`Edit price for "${option}":`, current);
            if(val !== null) {
                setPrice(catKey, option, parseFloat(val)||0);
                saveState();
                renderOptions();
            }
        }

        // --- Helpers ---
        function getPrice(type, item) {
            return state.prices[type]?.[item] ?? 0;
        }
        
        function setPrice(type, item, val) {
            if(!state.prices[type]) state.prices[type] = {};
            state.prices[type][item] = val;
        }

        // Init
        loadData();
        
        // --- Live Notifications & Polling ---
        let lastSpecCount = <?php echo count($specs); ?>;

        setInterval(async () => {
            try {
                const fd = new FormData();
                fd.append('action', 'get_all_specs');
                const res = await fetch(API, {method:'POST', body:fd});
                const json = await res.json();
                
                if(json.success && json.data) {
                    const newSpecs = json.data;
                    if(newSpecs.length > lastSpecCount) {
                        // New order received!
                        showNotification(`🔔 New Order Received! (${newSpecs.length - lastSpecCount} new)`);
                        updateSpecTable(newSpecs);
                        lastSpecCount = newSpecs.length;
                    }
                }
            } catch(e) {}
        }, 5000); // Check every 5s

        function showNotification(msg) {
            // Simple alert toast logic
            const div = document.createElement('div');
            div.className = 'fixed top-4 right-4 bg-gray-900 text-white px-6 py-4 rounded-xl shadow-2xl z-50 flex items-center animate-bounce';
            div.innerHTML = `<span class="text-xl mr-3">🎉</span> <div><div class="font-bold">New Notification</div><div class="text-sm opacity-90">${msg}</div></div>`;
            document.body.appendChild(div);
            // Play sound?
            // const audio = new Audio('path/to/sound.mp3'); audio.play();
            setTimeout(() => div.remove(), 5000);
        }

        function updateSpecTable(specs) {
            const tbody = document.getElementById('specs-table-body');
            tbody.innerHTML = specs.map(spec => {
                const flat = Object.values(spec.selection).filter(v => v).join('<span class="mx-1 text-gray-300">/</span>');
                const custName = spec.customer?.name || 'Guest';
                const source = spec._source_project || 'default';
                
                // Color badge for source
                const badges = {
                    'default': 'bg-gray-100 text-gray-600',
                    'Wedding': 'bg-pink-100 text-pink-600',
                    'Business': 'bg-blue-100 text-blue-600'
                };
                const badgeClass = badges[source] || 'bg-indigo-100 text-indigo-600';

                return `
                <tr class="bg-white hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badgeClass}">
                            ${source}
                        </span>
                    </td>
                    <td class="px-6 py-4 font-mono text-xs text-gray-400">#${spec.id.slice(-6)}</td>
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900">${custName}</div>
                        <div class="text-xs text-gray-500">${spec.customer?.phone || ''}</div>
                    </td>
                    <td class="px-6 py-4 text-xs leading-relaxed max-w-xs text-gray-500">${flat}</td>
                    <td class="px-6 py-4 font-bold text-gray-900">₹${spec.totalPrice}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Active</span>
                        <!-- Actions -->
                        <!-- If we want to edit status, we need to pass project context via hidden field or switch context? -->
                        <!-- For now just view -->
                    </td>
                </tr>`;
            }).join('');
        }
    </script>
</body>
</html>
