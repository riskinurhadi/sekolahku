<?php
/**
 * Test file untuk memverifikasi path CSS/JS
 * Akses file ini dari browser untuk melihat path yang dihasilkan
 */

require_once 'config/session.php';

echo "<h2>Test Path CSS/JS</h2>";
echo "<p><strong>SCRIPT_NAME:</strong> " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p><strong>dirname(SCRIPT_NAME):</strong> " . dirname($_SERVER['SCRIPT_NAME']) . "</p>";

$script_path = dirname($_SERVER['SCRIPT_NAME']);
$path_parts = array_filter(explode('/', $script_path));
$levels = count($path_parts);
$base_path = $levels > 0 ? str_repeat('../', $levels) : '';

echo "<p><strong>Path Parts:</strong> ";
print_r($path_parts);
echo "</p>";

echo "<p><strong>Levels:</strong> " . $levels . "</p>";
echo "<p><strong>Base Path:</strong> '" . $base_path . "'</p>";
echo "<p><strong>CSS Path:</strong> '" . $base_path . "assets/css/style.css'</p>";
echo "<p><strong>JS Path:</strong> '" . $base_path . "assets/js/main.js'</p>";

echo "<hr>";
echo "<h3>Test dari Dashboard:</h3>";
echo "<p>Dari dashboard/developer/index.php, path seharusnya: <code>../../assets/css/style.css</code></p>";

echo "<hr>";
echo "<h3>Fungsi getBasePath():</h3>";
echo "<p><strong>Result:</strong> '" . getBasePath() . "'</p>";
?>

