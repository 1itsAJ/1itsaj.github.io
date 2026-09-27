<?php
// Turn on error reporting to see if anything fails
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>Static Site Generator</h1>";

// 1. Generate index.html
echo "<p>Generating index.html...</p>";
ob_start(); // Start capturing output
include 'index.php'; // Run the file
$indexHtml = ob_get_clean(); // Stop capturing and save to variable

if (file_put_contents('index.html', $indexHtml)) {
    echo "<p style='color: green;'>✅ index.html successfully created!</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to create index.html. Check folder permissions.</p>";
}

// 2. Generate press.html
echo "<p>Generating press.html...</p>";
ob_start();
include 'press.php';
$pressHtml = ob_get_clean();

if (file_put_contents('press.html', $pressHtml)) {
    echo "<p style='color: green;'>✅ press.html successfully created!</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to create press.html.</p>";
}

echo "<h3>Done! You can now open your .html files.</h3>";
?>