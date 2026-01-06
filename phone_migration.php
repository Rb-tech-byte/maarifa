<?php
/**
 * Phone Number Requirement Migration Script
 *
 * This script safely updates the users table to make phone numbers required.
 * It handles existing NULL values and updates the table structure.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_config.php';

try {
    echo "Starting phone number requirement migration...\n";

    // Step 1: Check current phone column structure
    $phoneColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch(PDO::FETCH_ASSOC);
    echo "Current phone column: " . ($phoneColumn['Null'] === 'YES' ? 'NULLABLE' : 'NOT NULL') . "\n";

    // Step 2: Update existing users with NULL phone numbers
    // For existing users without phone numbers, we'll set a placeholder
    // In courseion, you might want to handle this differently (e.g., require phone update)
    $updateStmt = $pdo->prepare("UPDATE users SET phone = CONCAT('pending_', id) WHERE phone IS NULL OR phone = ''");
    $updatedRows = $updateStmt->execute();
    echo "Updated $updatedRows users with placeholder phone numbers\n";

    // Step 3: Modify the phone column to be NOT NULL
    $alterStmt = $pdo->exec("ALTER TABLE users MODIFY COLUMN phone VARCHAR(30) NOT NULL");
    echo "Modified phone column to NOT NULL\n";

    // Step 4: Verify the changes
    $verifyColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch(PDO::FETCH_ASSOC);
    echo "Verification - Phone column is now: " . ($verifyColumn['Null'] === 'NO' ? 'NOT NULL' : 'NULLABLE') . "\n";

    // Step 5: Count users without proper phone numbers (for admin review)
    $pendingCount = $pdo->query("SELECT COUNT(*) as count FROM users WHERE phone LIKE 'pending_%'")->fetch(PDO::FETCH_ASSOC);
    echo "Users with pending phone numbers: " . $pendingCount['count'] . "\n";

    echo "Migration completed successfully!\n";
    echo "Note: Users with 'pending_' phone numbers should update their phone numbers.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
