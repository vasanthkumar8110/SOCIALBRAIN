<?php
/**
 * Profile Data Migration Script
 * 
 * This script migrates profile JSON files from the old nested structure
 * (with ui_config) to the new flat structure at root level.
 * 
 * Run this once to fix existing profiles.
 */

$directory = __DIR__;
$files = glob($directory . '/data_*.json');

echo "🔄 Starting Profile Migration...\n\n";

foreach ($files as $filepath) {
    $filename = basename($filepath);
    echo "Processing: $filename\n";
    
    $data = json_decode(file_get_contents($filepath), true);
    
    if (!$data) {
        echo "  ⚠️  Skipped - Invalid JSON\n\n";
        continue;
    }
    
    $modified = false;
    
    // Check if ui_config is nested
    if (isset($data['ui_config'])) {
        echo "  📦 Found nested ui_config structure\n";
        
        // Extract ui_config to root level
        $uiConfig = $data['ui_config'];
        unset($data['ui_config']);
        
        // Merge ui_config fields to root
        $data = array_merge($data, $uiConfig);
        $modified = true;
        
        echo "  ✅ Extracted ui_config to root level\n";
    }
    
    // Normalize data structures
    if (!isset($data['config'])) {
        $data['config'] = [];
        $modified = true;
    }
    
    if (!isset($data['prices']) || (is_array($data['prices']) && empty($data['prices']))) {
        $data['prices'] = new stdClass();
        $modified = true;
        echo "  ✅ Converted prices to object\n";
    }
    
    if (!isset($data['rootRules']) || (is_array($data['rootRules']) && empty($data['rootRules']))) {
        $data['rootRules'] = new stdClass();
        $modified = true;
        echo "  ✅ Converted rootRules to object\n";
    }
    
    if (!isset($data['steps'])) {
        $data['steps'] = [
            ['id' => 'papers', 'label' => '2. GSM / Paper'],
            ['id' => 'lamination', 'label' => '3. Lamination'],
            ['id' => 'effects', 'label' => '4. Effects'],
            ['id' => 'corners', 'label' => '5. Corners']
        ];
        $modified = true;
        echo "  ✅ Added default steps\n";
    }
    
    if (!isset($data['rootLabel'])) {
        $data['rootLabel'] = 'Material Foundation';
        $modified = true;
    }
    
    // Ensure critical data structures exist
    if (!isset($data['users'])) {
        $data['users'] = [];
    }
    if (!isset($data['specifications'])) {
        $data['specifications'] = [];
    }
    if (!isset($data['materials'])) {
        $data['materials'] = [];
    }
    if (!isset($data['analytics'])) {
        $data['analytics'] = [];
    }
    
    if ($modified) {
        // Backup original file
        $backupPath = $filepath . '.backup';
        copy($filepath, $backupPath);
        echo "  💾 Backup created: $filename.backup\n";
        
        // Save migrated data
        if (file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
            echo "  ✅ Migration successful!\n";
        } else {
            echo "  ❌ Failed to save migrated data\n";
        }
    } else {
        echo "  ℹ️  No migration needed\n";
    }
    
    echo "\n";
}

echo "✅ Migration Complete!\n";
echo "\nYou can now:\n";
echo "1. Refresh your admin panel\n";
echo "2. All profiles should work like the default profile\n";
echo "3. Backup files (.backup) can be deleted if everything works\n";
?>
