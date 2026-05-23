<?php
require_once __DIR__ . '/../functions.php';
sb_session_start_secure();
$currentUser = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BAAB Studio | Premium Card Builder</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Black & White Color System */
            --bg-primary: #FFFFFF;
            --bg-secondary: #FAFAFA;
            --bg-tertiary: #000000;
            --text-primary: #000000;
            --text-secondary: #666666;
            --text-tertiary: #999999;
            --border-light: #E5E5E5;
            --border-medium: #CCCCCC;
            --border-dark: #000000;
            --accent: #000000;
            --hover-bg: #F5F5F5;
            --success: #000000;
            --error: #000000;
            
            /* Shadows */
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.10);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
            --shadow-xl: 0 16px 48px rgba(0,0,0,0.15);
            
            /* Transitions */
            --transition-fast: 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Spacing */
            --space-1: 4px;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-6: 24px;
            --space-8: 32px;
            --space-12: 48px;
            --space-16: 64px;
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* --- Header --- */
        header {
            position: fixed;
            top: 0; 
            left: 0; 
            right: 0;
            padding: var(--space-6) var(--space-8);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-light);
        }

        .brand {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            color: var(--text-primary);
        }

        .brand-dot {
            width: 6px; 
            height: 6px; 
            background: var(--text-primary); 
            border-radius: 50%;
        }

        /* Product Switcher */
        .product-select-btn {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            background: var(--bg-primary);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-base);
            color: var(--text-primary);
        }

        .product-select-btn:hover {
            border-color: var(--border-dark);
            background: var(--hover-bg);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .product-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            min-width: 200px;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: all var(--transition-base);
            z-index: 1000;
        }

        .product-dropdown.open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .product-option {
            display: block;
            width: 100%;
            text-align: left;
            padding: var(--space-3) var(--space-4);
            background: none;
            border: none;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            transition: background var(--transition-fast);
            border-bottom: 1px solid var(--border-light);
        }

        .product-option:last-child {
            border-bottom: none;
        }

        .product-option:hover {
            background: var(--hover-bg);
        }

        .product-option.active {
            font-weight: 600;
            background: var(--bg-secondary);
        }

        /* --- Layout --- */
        .app-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: calc(80px + var(--space-8)) var(--space-8) var(--space-8);
            display: grid;
            grid-template-columns: 1fr 440px;
            gap: var(--space-12);
            width: 100%;
            min-height: 100vh;
        }

        @media (max-width: 1200px) {
            .app-container {
                grid-template-columns: 1fr;
                gap: var(--space-8);
            }
        }

        /* --- Left Panel: Builder --- */
        .builder-panel {
            overflow-y: auto;
            padding-bottom: var(--space-16);
            scrollbar-width: thin;
            scrollbar-color: var(--border-light) transparent;
        }

        .builder-panel::-webkit-scrollbar {
            width: 6px;
        }

        .builder-panel::-webkit-scrollbar-track {
            background: transparent;
        }

        .builder-panel::-webkit-scrollbar-thumb {
            background: var(--border-light);
            border-radius: 3px;
        }

        .builder-panel::-webkit-scrollbar-thumb:hover {
            background: var(--border-medium);
        }

        .heading-block {
            margin-bottom: var(--space-12);
        }

        .heading-block h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 42px;
            font-weight: 700;
            letter-spacing: -1.5px;
            margin-bottom: var(--space-3);
            line-height: 1.1;
        }

        .heading-block p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.6;
            max-width: 600px;
        }

        .section-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
            color: var(--text-tertiary);
            margin-bottom: var(--space-6);
            margin-top: var(--space-12);
            padding-bottom: var(--space-3);
            border-bottom: 1px solid var(--border-light);
        }

        .section-title:first-child { 
            margin-top: 0; 
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: var(--space-4);
        }

        .option-card {
            background: var(--bg-primary);
            border: 1.5px solid var(--border-light);
            border-radius: 12px;
            padding: var(--space-6);
            cursor: pointer;
            transition: all var(--transition-base);
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: var(--space-3);
        }

        .option-card:hover {
            border-color: var(--border-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .option-card.selected {
            border-color: var(--border-dark);
            background: var(--bg-tertiary);
            color: var(--bg-primary);
            box-shadow: var(--shadow-lg);
        }

        .option-card.selected .option-price {
            background: rgba(255,255,255,0.15);
            color: var(--bg-primary);
            border-color: rgba(255,255,255,0.3);
        }

        .option-thumbnail {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: var(--space-2);
            background: #f8f9fa;
        }

        /* States */
        .option-card.locked {
            opacity: 0.4;
            pointer-events: none;
            filter: grayscale(100%);
            border-style: dashed;
            border-color: var(--border-light);
        }

        .option-card.blocked {
            opacity: 0.5;
            pointer-events: none;
            background: var(--bg-secondary);
            border-color: var(--border-medium);
            cursor: not-allowed;
            position: relative;
        }

        .option-card.blocked::after {
            content: '⚠';
            position: absolute;
            top: var(--space-2);
            right: var(--space-2);
            font-size: 14px;
            opacity: 0.5;
        }

        .option-name {
            font-weight: 500;
            font-size: 14px;
            line-height: 1.4;
        }

        .option-price {
            font-size: 11px;
            font-weight: 600;
            padding: var(--space-1) var(--space-3);
            border-radius: 6px;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border: 1px solid var(--border-light);
            transition: all var(--transition-fast);
        }

        /* --- Right Panel: Summary --- */
        .summary-panel {
            padding-top: 80px;
            display: flex;
            flex-direction: column;
            gap: 30px;
        /* --- 3D Scene --- */
        .preview-card-container {
            perspective: 1000px; width: 100%; height: 400px; display: flex; align-items: center; justify-content: center;
            overflow: visible; 
        }
        
        .card-3d-scene {
            width: 320px; height: 190px; position: relative;
            transform-style: preserve-3d;
            transform: rotateX(20deg) rotateY(-15deg);
            transition: transform 0.1s ease-out, width 0.5s ease-in-out, height 0.5s ease-in-out;
        }

        .face-front, .face-back { width: 100%; height: 100%; }
        .face-front { 
            transform: translateZ(var(--depth, 1px)); 
            overflow: hidden; 
            background: #fff; /* Base background for clear layers */
        }
        
        /* Layered children for face-front */
        .face-front > .layer {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            pointer-events: none;
        }
        .layer-material { z-index: 1; }
        .layer-design { 
            z-index: 2; 
            background-size: 100px 100px; 
            background-repeat: no-repeat; 
            background-position: center; 
        }
        .layer-video { z-index: 3; }
        .layer-finish { z-index: 4; mix-blend-mode: overlay; opacity: 0; transition: opacity 0.3s; }
        .layer-gloss { z-index: 5; opacity: 0; pointer-events: none; }
        
        .face-back { transform: rotateY(180deg) translateZ(var(--depth, 1px)); }

        .face-left, .face-right { width: calc(var(--depth, 1px) * 2); height: 100%; left: 0; top: 0; }
        .face-left { transform: translateX(calc(var(--depth, 1px) * -1)) rotateY(-90deg); transform-origin: right center; }
        .face-right { transform: translateX(100%) rotateY(90deg); transform-origin: left center; }

        .face-top, .face-bottom { width: 100%; height: calc(var(--depth, 1px) * 2); top: 0; left: 0; }
        .face-top { transform: translateY(calc(var(--depth, 1px) * -1)) rotateX(90deg); transform-origin: center bottom; }
        .face-bottom { transform: translateY(100%) rotateX(-90deg); transform-origin: center top; }

        .rounded-edges .card-face { border-radius: 6px; }

        /* Shading / Finishes - Now using .layer class */
        .layer-gloss {
            background: linear-gradient(135deg, rgba(255,255,255,0) 40%, rgba(255,255,255,0.4) 50%, rgba(255,255,255,0) 60%);
        }

        /* Material / Color Palettes */
        .preview-card-container { overflow: visible !important; } /* Ensure rotation doesn't clip */

        .cart-box {
            background: white;
            border-radius: 24px;
            padding: 32px;
            box-shadow: var(--shadow-lg);
        }

        .cart-header {
            display: flex; justify-content: space-between; align-items: baseline;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 16px;
        }

        .stat-label { 
            font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary); 
            display: block; margin-bottom: 4px;
        }
        
        .stat-value { font-size: 16px; font-weight: 500; }

        .steps-list {
            list-style: none;
            display: flex; flex-direction: column; gap: 16px;
            margin-bottom: 30px;
        }

        .step-item {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 14px;
        }
        .step-item.empty { color: #ccc; }
        .step-name { font-weight: 500; }
        .step-price { font-family: 'Space Grotesk', monospace; }

        .total-row {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color);
        }
        .total-price { font-family: 'Space Grotesk'; font-size: 32px; font-weight: 600; }

        .checkout-btn {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 18px;
            border-radius: 12px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 24px;
        }
        .checkout-btn:hover { background: #333; transform: translateY(-2px); }
        .checkout-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* --- Toast --- */
        .toast-container {
            position: fixed; bottom: 40px; right: 40px;
            display: flex; flex-direction: column; gap: 10px; z-index: 100;
        }
        .toast {
            background: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            display: flex; align-items: center; gap: 12px;
            transform: translateY(20px); opacity: 0;
            animation: slideUp 0.3s forwards cubic-bezier(0.16, 1, 0.3, 1);
            min-width: 300px;
        }
        .toast.error { border-left: 4px solid var(--error-color); }
        .toast.success { border-left: 4px solid var(--success-color); }
        
        @keyframes slideUp { to { transform: translateY(0); opacity: 1; } }
        
        @keyframes pulseOnce {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); background-color: #fefce8; }
            100% { transform: scale(1); }
        }
        .animate-pulse-once { animation: pulseOnce 0.5s ease-out; }

        /* --- Modal --- */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 200; display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: var(--transition);
        }
        .modal-overlay.active { opacity: 1; pointer-events: auto; }
        
        .modal-content {
            background: white; width: 100%; max-width: 400px; padding: 32px; border-radius: 24px;
            box-shadow: var(--shadow-lg); transform: scale(0.95); transition: var(--transition);
        }
        .modal-overlay.active .modal-content { transform: scale(1); }

        .modal-title { font-size: 24px; font-weight: 600; font-family: 'Space Grotesk'; margin-bottom: 8px; }
        .modal-desc { color: var(--text-secondary); font-size: 14px; margin-bottom: 24px; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; font-weight: 500; margin-bottom: 8px; color: var(--text-secondary); }
        .form-group input { 
            width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;
            font-family: inherit; transition: var(--transition);
        }
        .form-group input:focus { outline: none; border-color: var(--primary-color); }

        .modal-actions { display: flex; gap: 12px; margin-top: 24px; }
        .btn-confirm { flex: 1; background: var(--primary-color); color: white; padding: 12px; border-radius: 8px; border: none; font-weight: 500; cursor: pointer; }
        .btn-cancel { flex: 1; background: transparent; border: 1px solid var(--border-color); color: var(--text-primary); padding: 12px; border-radius: 8px; font-weight: 500; cursor: pointer; }

        /* Responsive */
        @media (max-width: 1200px) {
            .app-container { grid-template-columns: 1fr; height: auto; overflow: auto; }
            .builder-panel { padding-bottom: 40px; }
            .summary-panel { position: relative; width: 100%; }
        }
    </style>
</head>
<body>

    <header>
        <div class="brand">
            <span class="brand-dot"></span>
            baab.
        </div>
        
        <div style="display: flex; align-items: center; gap: 20px;">
            <!-- Product Switcher (Hidden by default, shown via JS if >1 project) -->
            <div style="position: relative;" id="product-switcher-container">
                <button class="product-select-btn" onclick="toggleProductDropdown()">
                    <span id="current-product-label">Default Profile</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="product-dropdown" id="product-dropdown-list">
                    <!-- Loaded via JS -->
                </div>
            </div>
            
            <div style="font-size: 14px; font-weight: 500; color: var(--text-primary);">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span>👋 Hi, <?php echo htmlspecialchars($_SESSION['user_id']); ?></span>
                    <a href="logout.php" style="margin-left: 10px; color: var(--text-secondary); text-decoration: none; font-size: 12px;">(Logout)</a>
                <?php else: ?>
                    <!-- Guest -->
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="app-container">
        
        <!-- Left: Builder -->
        <main class="builder-panel">
            
            <div class="heading-block" style="margin-bottom: 40px;">
                <h1 style="font-size: 48px; font-weight: 300; letter-spacing: -2px; margin-bottom: 12px;">Build your card.</h1>
                <p style="color: var(--text-secondary); max-width: 500px; line-height: 1.6;">
                    Select materials carefully. Our hierarchy system ensures only compatible finishes are available for your chosen paper.
                </p>
            </div>

            <!-- DYNAMIC BUILDER CONTAINER -->
            <div id="dynamic-builder">
                <!-- JS Load -->
            </div>

        </main>

        <!-- Right: Summary -->
        <aside class="summary-panel">
            <!-- ... (Preview unchanged) ... -->
            
            <!-- 3D Preview -->
            <div class="preview-card-container" id="scene-container">
                <div class="card-3d-scene" id="card-3d">
                    <div class="card-face face-front" id="face-front">
                        <div class="layer layer-material" id="layer-material"></div>
                        <div class="layer layer-design" id="layer-design"></div>
                        <div class="layer layer-video" id="layer-video-container"></div>
                        <div class="layer layer-finish" id="layer-finish"></div>
                        <div class="layer layer-gloss" id="layer-gloss"></div>
                    </div>
                    <div class="card-face face-back" id="face-back"></div>
                    <div class="card-face face-left" id="face-left"></div>
                    <div class="card-face face-right" id="face-right"></div>
                    <div class="card-face face-top" id="face-top"></div>
                    <div class="card-face face-bottom" id="face-bottom"></div>
                </div>
            </div>

            <div class="cart-box">
                <div class="cart-header">
                    <div>
                        <span class="stat-label">Order Summary</span>
                        <div class="stat-value">Specification</div>
                    </div>
                </div>

                <ul class="steps-list" id="cart-steps">
                    <!-- JS Load -->
                </ul>

                <div class="total-row">
                    <span style="font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Total Estimate</span>
                    <span class="total-price" id="total-price">₹0</span>
                </div>

                <button id="checkout-btn" class="checkout-btn" onclick="openModal()">Proceed to Checkout</button>
            </div>

        </aside>
    </div>

    <!-- Checkout Modal -->
    <div id="checkout-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h2 class="modal-title">Finalize Details</h2>
            <p class="modal-desc">Please provide your contact information to process the order.</p>
            
            <form id="checkout-form" onsubmit="confirmCheckout(event)">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="cust-name" required placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="cust-phone" required placeholder="+91 98765 43210">
                </div>
                <div class="form-group">
                    <label>Email (Optional)</label>
                    <input type="email" id="cust-email" placeholder="john@example.com">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Back</button>
                    <button type="submit" class="btn-confirm">Confirm Order</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-area"></div>

    <script>
        const CSRF = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
        const API = 'function.php';
        
        let state = {
            config: {},
            prices: {},
            rootRules: {},
            steps: [], 
            selection: { specialPaper: null },
            rootLabel: "Material Foundation",
            dimensions: { width: 8.5, height: 5.5, unit: 'cm' },
            assets: { previewImage: null, previewVideo: null }
        };

        // --- Init ---
        async function init() {
            await loadProjects(); // Load available profiles first
            await loadConfig();
            
            // Poll
            setInterval(async () => {
                const changed = await loadConfig();
                if(changed) renderAll();
            }, 5000);
        }

        // --- Multi-Profile Logic ---
        async function loadProjects() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const current = urlParams.get('project') || 'default';
                window.CURRENT_PROJECT = current; // Global

                const fd = new FormData();
                fd.append('action', 'get_projects');
                const res = await (await fetch(API, {method:'POST', body:fd, headers: CSRF ? {'X-CSRF-Token': CSRF} : {} })).json();
                
                if(res.success && res.data && res.data.length > 1) { // Only show if > 1 profile
                    const container = document.getElementById('product-switcher-container');
                    const label = document.getElementById('current-product-label');
                    const list = document.getElementById('product-dropdown-list');
                    
                    if(container && label && list) {
                        container.style.display = 'block';
                        label.textContent = current === 'default' ? 'Default Profile' : current;
                        
                        list.innerHTML = res.data.map(p => `
                            <button class="product-option ${p === current ? 'active' : ''}" onclick="switchProject('${p}')">
                                ${p === 'default' ? 'Default Profile' : p}
                            </button>
                        `).join('');
                    }
                } else {
                    // Hide switcher if only default profile exists
                    const container = document.getElementById('product-switcher-container');
                    if(container) container.style.display = 'none';
                }
            } catch(e) { console.error("Project Load Error", e); }
        }

        function toggleProductDropdown() {
            const list = document.getElementById('product-dropdown-list');
            if(list) list.classList.toggle('open');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if(!e.target.closest('#product-switcher-container')) {
                const dropdown = document.getElementById('product-dropdown-list');
                if(dropdown) dropdown.classList.remove('open');
            }
        });

        function switchProject(name) {
            if(name === 'default') {
                window.location.href = 'index.php';
            } else {
                window.location.href = `index.php?project=${name}`;
            }
        }

        async function loadConfig() {
            try {
                // Multi-Project Support
                const urlParams = new URLSearchParams(window.location.search);
                const currentProject = urlParams.get('project');
                if(currentProject) window.CURRENT_PROJECT = currentProject; // Global for wrapper

                const fd = new FormData();
                fd.append('action', 'get_ui_config');
                if(currentProject) fd.append('project', currentProject); 

                const res = await fetch(API, {method:'POST', body:fd, headers: CSRF ? {'X-CSRF-Token': CSRF} : {} });
                const json = await res.json();
                
                if(json.success && json.data?.config) {
                    const s = json.data;
                    
                    // Force normalization
                    if(Array.isArray(s.prices)) s.prices = {};
                    if(Array.isArray(s.rootRules)) s.rootRules = {};

                    const old = JSON.stringify({c:state.config, r:state.rootRules, s:state.steps});
                    
                    state.config = s.config || {};
                    state.prices = s.prices || {};
                    state.rootRules = s.rootRules || {}; 
                    state.rootLabel = s.rootLabel || "Material Foundation";
                    state.dimensions = s.dimensions || { width: 8.5, height: 5.5, unit: 'cm' };
                    state.assets = s.assets || { previewImage: null, previewVideo: null };
                    state.steps = s.steps || [
                         // Fallback compat
                        {id:'papers', label:'2. GSM / Paper'},
                        {id:'lamination', label:'3. Lamination'},
                        {id:'effects', label:'4. Effects'},
                        {id:'corners', label:'5. Corners'}
                    ];
                    
                    const now = JSON.stringify({c:state.config, r:state.rootRules, s:state.steps, l:state.rootLabel}); // Include label in diff
                    if(old !== now) { renderAll(); return true; }
                }
            } catch(e) { console.error(e); }
            return false;
        }

        // --- Render ---
        function renderAll() {
            const container = document.getElementById('dynamic-builder');
            container.innerHTML = '';

            // 1. Render Root (Always First) - Use dynamic label
            const label = state.rootLabel || "01. Material Foundation";
            const rootSec = document.createElement('div');
            // Check if label starts with number, if not add 01. prefix? Or let user decide?
            // User said "renameable". Let's show raw label but maybe style it?
            // Let's assume user types full string.
            rootSec.innerHTML = `<div class="section-title">${label}</div><div class="options-grid" id="grid-specialPaper"></div>`;
            container.appendChild(rootSec);
            renderSection('grid-specialPaper', state.config.specialPapers, 'specialPaper');

            // 2. Render Dynamic Steps
            state.steps.forEach((step, idx) => {
                const sec = document.createElement('div');
                sec.innerHTML = `<div class="section-title">${step.label}</div><div class="options-grid" id="grid-${step.id}"></div>`;
                container.appendChild(sec);
                renderSection(`grid-${step.id}`, state.config[step.id], step.id);
            });
            
            updateSummary();
            updatePreview();
        }

        function renderSection(elId, items = [], type) {
            const container = document.getElementById(elId);
            container.innerHTML = '';
            
            if(!items) return;

            items.forEach(item => {
                const price = state.prices[type]?.[item] ?? 0;
                const isSelected = state.selection[type] === item;
                
                let className = 'option-card';
                if(isSelected) className += ' selected';

                // Check Locked/Blocked status
                let isLocked = false;
                let isBlocked = false;

                if(type !== 'specialPaper') {
                    if(!state.selection.specialPaper) {
                         // Waiting for parent
                         isLocked = true;
                         className += ' locked';
                    } else {
                         // Validate against Root Rules
                         const root = state.selection.specialPaper;
                         const allowedList = state.rootRules[root]?.[type] || [];
                         
                         if(!allowedList.includes(item)) {
                             isBlocked = true;
                             className += ' blocked';
                             if(isSelected) {
                                 state.selection[type] = null;
                                 setTimeout(renderAll, 0); 
                             }
                         }
                    }
                }

                // Create Card
                const div = document.createElement('div');
                div.className = className;
                
                let thumbHtml = '';
                if(type === 'specialPaper' && state.rootRules[item]?.meta?.thumbnail) {
                    thumbHtml = `<img src="${state.rootRules[item].meta.thumbnail}" class="option-thumbnail" alt="${item}">`;
                }

                div.innerHTML = `
                    ${thumbHtml}
                    <div class="option-name">${item}</div>
                    <div class="option-price">₹${price}</div>
                `;

                if(!isLocked && !isBlocked) {
                    div.onclick = () => toggleSelection(type, item);
                } else if (isBlocked) {
                    div.onclick = () => showToast(`🚫 "${item}" is not available for ${state.selection.specialPaper}`, 'error');
                }

                container.appendChild(div);
            });
        }

        function toggleSelection(type, item) {
            if(state.selection[type] === item) {
                state.selection[type] = null;
            } else {
                state.selection[type] = item;
                // If Root changes, reset all children
                if(type === 'specialPaper') {
                    state.steps.forEach(s => state.selection[s.id] = null);
                }
            }
            renderAll();
        }

        function updateSummary() {
            let total = 0;
            const list = document.getElementById('cart-steps');
            list.innerHTML = '';
            let hasSelection = false;

            // Root
            if(state.selection.specialPaper) {
                hasSelection = true;
                const p = state.prices.specialPaper?.[state.selection.specialPaper] ?? 0;
                total += parseFloat(p);
                addCartItem(list, 'Material', state.selection.specialPaper, p);
            } else {
                addCartItem(list, 'Material', '-', 0, true);
            }

            // Steps
            state.steps.forEach(s => {
                const val = state.selection[s.id];
                if(val) {
                    const p = state.prices[s.id]?.[val] ?? 0;
                    total += parseFloat(p);
                    addCartItem(list, s.label.replace(/^\d+\.\s*/, ''), val, p);
                } else {
                    addCartItem(list, s.label.replace(/^\d+\.\s*/, ''), '-', 0, true);
                }
            });

            document.getElementById('total-price').textContent = '₹' + total;
            document.getElementById('checkout-btn').disabled = !hasSelection;
        }

        function addCartItem(list, label, val, price, empty=false) {
            list.innerHTML += `
                <li class="step-item ${empty ? 'empty' : ''}">
                    <div>
                        <span class="stat-label">${label}</span>
                        <span class="step-name">${val}</span>
                    </div>
                    ${!empty ? `<span class="step-price">₹${price}</span>` : ''}
                </li>
            `;
        }

        // --- 3D Engine ---
        function updatePreview() {
            const root = state.selection.specialPaper;
            const card = document.getElementById('card-3d');
            const layerMaterial = document.getElementById('layer-material');
            const layerDesign = document.getElementById('layer-design');
            const videoContainer = document.getElementById('layer-video-container');
            const finish = document.getElementById('layer-finish');
            const gloss = document.getElementById('layer-gloss');

            // 1. Layers (Flexibility)
            const designImg = state.assets.previewImage;
            const designVid = state.assets.previewVideo;
            const rootTexUrl = state.rootRules[root]?.meta?.texture;

            // Base Material
            if(rootTexUrl) {
                layerMaterial.style.backgroundImage = `url('${rootTexUrl}')`;
                layerMaterial.style.display = designImg ? 'none' : 'block'; // Hide on front if design exists
                // Side/Back faces
                document.querySelectorAll('.card-face').forEach(f => {
                    if(f.id !== 'face-front') f.style.backgroundImage = `url('${rootTexUrl}')`;
                });
            } else {
                layerMaterial.style.backgroundImage = 'none';
                const baseColor = root === 'Black Card' ? '#1a1a1a' : (root === 'Kraft Paper' ? '#d4b595' : '#fff');
                layerMaterial.style.backgroundColor = baseColor;
                layerMaterial.style.display = designImg ? 'none' : 'block'; // Hide color if design exists
                document.querySelectorAll('.card-face').forEach(f => {
                    if(f.id !== 'face-front') {
                        f.style.backgroundImage = 'none';
                        f.style.backgroundColor = baseColor;
                    }
                });
            }

            // Design Overlay
            if(designImg) {
                layerDesign.style.backgroundImage = `url('${designImg}')`;
                layerDesign.style.display = 'block';
            } else {
                layerDesign.style.display = 'none';
            }

            // Video Overlay
            let video = document.getElementById('preview-video-active');
            if(designVid) {
                if(!video) {
                    video = document.createElement('video');
                    video.id = 'preview-video-active';
                    video.className = 'absolute inset-0 w-full h-full object-cover pointer-events-none';
                    video.loop = true;
                    video.muted = true;
                    video.autoplay = true;
                    videoContainer.appendChild(video);
                }
                if(video.src !== designVid) video.src = designVid;
                videoContainer.style.display = 'block';
                video.play().catch(e => console.warn("Video play blocked", e));
            } else {
                videoContainer.style.display = 'none';
            }

            // 2. Dimensions (Proportions)
            const w = parseFloat(state.dimensions.width) || 8.5;
            const h = parseFloat(state.dimensions.height) || 5.5;
            const ratio = h / w;
            const baseW = 340;
            card.style.width = baseW + 'px';
            card.style.height = (baseW * ratio) + 'px';

            // 3. Thickness (Dynamic Depth)
            let depth = 1;
            const gsmStep = state.steps.find(s => s.id === 'papers' || s.label.toLowerCase().includes('gsm'));
            if(gsmStep && state.selection[gsmStep.id]) {
                const opt = state.selection[gsmStep.id];
                const metaThick = state.rootRules[root]?.meta?.[`thick_${gsmStep.id}_${opt}`];
                if(metaThick) depth = parseFloat(metaThick) * 0.5;
            }
            card.style.setProperty('--depth', `${depth}px`);

            // 4. Lamination / Finish
            finish.style.opacity = 0;
            gloss.style.opacity = 0;
            
            const lamStep = state.steps.find(s => s.id === 'lamination');
            if(lamStep && state.selection[lamStep.id]) {
                const val = state.selection[lamStep.id].toLowerCase();
                if(val.includes('gloss')) {
                    gloss.style.opacity = 0.6;
                } else if (val.includes('matt')) {
                    finish.style.background = 'rgba(255,255,255,0.1)';
                    finish.style.opacity = 0.3;
                }
            }

            // 5. Corners
             const cornStep = state.steps.find(s => s.id === 'corners');
             if(cornStep && state.selection[cornStep.id] === 'Rounded') {
                 card.classList.add('rounded-edges');
             } else {
                 card.classList.remove('rounded-edges');
             }
        }

        // --- Mouse Interaction (Rotate & Zoom) ---
        const sceneContainer = document.getElementById('scene-container');
        const card3dRef = document.getElementById('card-3d');
        let isHovered = false;
        
        sceneContainer.addEventListener('mouseenter', () => isHovered = true);
        sceneContainer.addEventListener('mouseleave', () => {
            isHovered = false;
            card3dRef.style.transform = `rotateX(20deg) rotateY(-15deg) scale(1)`;
        });

        sceneContainer.addEventListener('mousemove', (e) => {
            const rect = sceneContainer.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            // Rotation (-25 to 25 deg)
            const rx = -((y / rect.height) - 0.5) * 50;
            const ry = ((x / rect.width) - 0.5) * 50;
            
            // Zoom Scale (1 to 1.25)
            const scale = isHovered ? 1.25 : 1;

            card3dRef.style.transform = `rotateX(${rx}deg) rotateY(${ry}deg) scale(${scale})`;
        });

        function openModal() {
            const total = parseFloat(document.getElementById('total-price').textContent.replace('₹',''));
            if(total === 0) {
                showToast('Please select items first', 'error');
                return;
            }
            const modal = document.getElementById('checkout-modal');
            modal.classList.remove('hidden');
            // Small delay to allow display:flex to apply before transition
            setTimeout(() => modal.classList.add('active'), 10);
        }

        function closeModal() {
            const modal = document.getElementById('checkout-modal');
            modal.classList.remove('active');
            setTimeout(() => modal.classList.add('hidden'), 300); // Wait for transition
        }

        async function confirmCheckout(e) {
            e.preventDefault();
            const btn = document.querySelector('.btn-confirm');
            btn.textContent = 'Processing...';
            btn.disabled = true;

            const customer = {
                name: document.getElementById('cust-name').value,
                phone: document.getElementById('cust-phone').value,
                email: document.getElementById('cust-email').value
            };

            await saveSpecification(customer);
            
            btn.textContent = 'Confirm Order';
            btn.disabled = false;
            closeModal();
        }

        async function saveSpecification(customerData) {
            const total = parseFloat(document.getElementById('total-price').textContent.replace('₹',''));
            
            const fd = new FormData();
            fd.append('action', 'save_specification');
            fd.append('user_id', '<?php echo $currentUser ?: "guest"; ?>');
            fd.append('selection', JSON.stringify(state.selection));
            fd.append('customer', JSON.stringify(customerData));
            fd.append('totalPrice', total);
            
            // Include project context so order is saved to correct profile
            if(window.CURRENT_PROJECT) {
                fd.append('project', window.CURRENT_PROJECT);
            }

            try {
                const res = await fetch(API, {method:'POST', body:fd, headers: CSRF ? {'X-CSRF-Token': CSRF} : {} });
                const json = await res.json();
                if(json.success) {
                    showToast('Order placed successfully! ID: ' + json.data.specId, 'success');
                    // Reset
                    state.selection = {specialPaper:null}; // Reset root clears all
                    state.steps.forEach(s => state.selection[s.id] = null);
                    document.getElementById('checkout-form').reset();
                    renderAll();
                } else {
                    showToast('Failed: ' + json.message, 'error');
                }
            } catch(e) {
                showToast('Network error', 'error');
            }
        }

        // --- Helpers ---
        function getCatKey(type) {
            const map = { 'paper':'papers', 'lamination':'lamination', 'effect':'effects', 'corner':'corners', 'specialPaper':'specialPaper' };
            return map[type] || type;
        }

        function showToast(msg, type='success') {
            const area = document.getElementById('toast-area');
            const el = document.createElement('div');
            el.className = `toast ${type}`;
            el.textContent = msg;
            area.appendChild(el);
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                setTimeout(() => el.remove(), 300);
            }, 3000);
        }

        // Boot
        init();

    </script>
</body>
</html>
