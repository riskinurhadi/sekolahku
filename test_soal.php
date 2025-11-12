<?php
/**
 * Test file untuk melihat data item_soal di database
 * Akses file ini untuk melihat apakah ada duplikasi data
 */

require_once 'config/database.php';

$conn = getConnection();

// Get all soal
$soal_list = $conn->query("SELECT * FROM soal ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

echo "<h2>Test Data Item Soal</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Soal ID</th><th>Judul</th><th>Item Soal</th></tr>";

foreach ($soal_list as $soal) {
    $soal_id = $soal['id'];
    $item_soal = $conn->query("SELECT * FROM item_soal WHERE soal_id = $soal_id ORDER BY urutan")->fetch_all(MYSQLI_ASSOC);
    
    echo "<tr>";
    echo "<td>" . $soal_id . "</td>";
    echo "<td>" . htmlspecialchars($soal['judul']) . "</td>";
    echo "<td>";
    echo "<ul>";
    foreach ($item_soal as $item) {
        echo "<li>ID: " . $item['id'] . " | Urutan: " . $item['urutan'] . " | Pertanyaan: " . htmlspecialchars(substr($item['pertanyaan'], 0, 50)) . "...</li>";
    }
    echo "</ul>";
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

$conn->close();
?>

