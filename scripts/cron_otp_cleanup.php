<?php
// Usage: php scripts/cron_otp_cleanup.php [days]
// Schedules: run daily via OS scheduler or cron
try {
  require_once __DIR__ . '/../includes/db_config.php';
  require_once __DIR__ . '/../includes/otp.php';
  $days = isset($argv[1]) ? max(1, (int)$argv[1]) : 7;
  ak23_otp_cleanup($days);
  echo "OK: cleaned OTPs older than {$days} days\n";
} catch (Throwable $e) {
  fwrite(STDERR, "ERROR: ".$e->getMessage()."\n");
  exit(1);
}
