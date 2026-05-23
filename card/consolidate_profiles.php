<?php
/**
 * Consolidate All Profile Data into Single data.json
 * 
 * This script merges all separate profile JSON files into a single
 * centralized data.json with a "profiles" section.
 */

$directory = __DIR__;
$mainFile = $directory . '/data.json';
$profileFiles = glob($directory . '/data_*.json');

echo "🔄 Starting Profile Consolidation...\n\n";

// Step 1: Load main data.json (default profile)
if (!file_exists($mainFile)) {
    die("❌ Error: data.json not found!\n");
}

$mainData = json_decode(file_get_contents($mainFile), true);
if (!$mainData) {
    die("❌ Error: Invalid data.json!\n");
}

echo "✅ Loaded data.json\n";

// Step 2: Create backup
$backupFile = $mainFile . '.pre-consolidation';
copy($mainFile, $backupFile);
echo "💾 Backup created: data.json.pre-consolidation\n\n";

// Step 3: Initialize profiles structure
$consolidatedData = [
    'users' => $mainData['users'] ?? [],
    'specifications' => $mainData['specifications'] ?? [],
    'materials' => $mainData['materials'] ?? [],
    'analytics' => $mainData['analytics'] ?? [],
    'compatibility' => $mainData['compatibility'] ?? [],
    'profiles' => []
];

// Step 4: Extract default profile from main data
echo "📦 Extracting default profile...\n";
$consolidatedData['profiles']['default'] = [
    'config' => $mainData['config'] ?? [],
    'prices' => $mainData['prices'] ?? new stdClass(),
    'rootRules' => $mainData['rootRules'] ?? new stdClass(),
    'steps' => $mainData['steps'] ?? [],
    'rootLabel' => $mainData['rootLabel'] ?? 'Material Foundation',
    'activeTab' => $mainData['activeTab'] ?? null,
    'selectedRoot' => $mainData['selectedRoot'] ?? null
];
echo "✅ Default profile extracted\n\n";

// Step 5: Process each profile file
foreach ($profileFiles as $filepath) {
    $filename = basename($filepath);
    
    // Extract profile name from filename (data_ProfileName.json)
    preg_match('/data_(.+)\.json$/', $filename, $matches);
    if (!$matches) {
        echo "⚠️  Skipped: $filename (invalid format)\n";
        continue;
    }
    
    $profileName = $matches[1];
    echo "📦 Processing profile: $profileName\n";
    
    $profileData = json_decode(file_get_contents($filepath), true);
    if (!$profileData) {
        echo "  ⚠️  Skipped - Invalid JSON\n\n";
        continue;
    }
    
    // Merge users (preserve all users)
    if (isset($profileData['users'])) {
        $consolidatedData['users'] = array_merge($consolidatedData['users'], $profileData['users']);
        echo "  ✅ Merged users\n";
    }
    
    // Merge specifications (preserve _source_project)
    if (isset($profileData['specifications']) && is_array($profileData['specifications'])) {
        foreach ($profileData['specifications'] as $spec) {
            // Ensure _source_project is set
            if (!isset($spec['_source_project'])) {
                $spec['_source_project'] = $profileName;
            }
            $consolidatedData['specifications'][] = $spec;
        }
        echo "  ✅ Merged specifications\n";
    }
    
    // Extract profile-specific UI config
    $consolidatedData['profiles'][$profileName] = [
        'config' => $profileData['config'] ?? [],
        'prices' => $profileData['prices'] ?? new stdClass(),
        'rootRules' => $profileData['rootRules'] ?? new stdClass(),
        'steps' => $profileData['steps'] ?? [],
        'rootLabel' => $profileData['rootLabel'] ?? $profileName,
        'activeTab' => $profileData['activeTab'] ?? null,
        'selectedRoot' => $profileData['selectedRoot'] ?? null
    ];
    echo "  ✅ Extracted profile config\n";
    
    // Backup original file
    $backupPath = $filepath . '.old';
    copy($filepath, $backupPath);
    echo "  💾 Backup created: $filename.old\n\n";
}

// Step 6: Write consolidated data
echo "💾 Writing consolidated data.json...\n";
$jsonData = json_encode($consolidatedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($mainFile, $jsonData)) {
    echo "✅ Successfully wrote " . strlen($jsonData) . " bytes\n\n";
} else {
    die("❌ Failed to write data.json!\n");
}

// Step 7: Summary
echo "📊 Consolidation Summary:\n";
echo "  • Users: " . count($consolidatedData['users']) . "\n";
echo "  • Specifications: " . count($consolidatedData['specifications']) . "\n";
echo "  • Profiles: " . count($consolidatedData['profiles']) . "\n";
echo "    - " . implode("\n    - ", array_keys($consolidatedData['profiles'])) . "\n\n";

echo "✅ Consolidation Complete!\n\n";
echo "Next Steps:\n";
echo "1. Refresh your admin panel\n";
echo "2. Test switching between profiles\n";
echo "3. Verify all data is accessible\n";
echo "4. If everything works, you can delete:\n";
echo "   - data_*.json.old (backup files)\n";
echo "   - data_*.json (original separate files)\n";
echo "   - data.json.pre-consolidation (backup)\n";
?>
