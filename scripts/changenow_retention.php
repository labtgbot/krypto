<?php

$root = dirname(__DIR__);

function changenow_retention_usage(){
  echo "Usage: php scripts/changenow_retention.php [--dry-run] [--json] [--db=NAME] [--anonymous-days=N] [--completed-days=N] [--batch-size=N]\n";
  echo "  --dry-run           Count rows that would be changed without deleting or anonymizing data.\n";
  echo "  --json              Print the result as JSON for cron log parsers.\n";
  echo "  --db=NAME           Override KRYPTO_DB_NAME/KRYPTO_TEST_DB_NAME when KRYPTO_ENV_CONFIG is used.\n";
  echo "  --anonymous-days=N  Override changenow_retention_anonymous_days for this run.\n";
  echo "  --completed-days=N  Override changenow_retention_completed_days for this run.\n";
  echo "  --batch-size=N      Process transaction rows in batches, default 500.\n";
}

function changenow_retention_parse_args($argv){
  $args = [
    'help' => false,
    'dry_run' => false,
    'json' => false,
    'db' => null,
    'anonymous_retention_days' => null,
    'completed_retention_days' => null,
    'batch_size' => null
  ];

  for($i = 1; $i < count($argv); $i++){
    $arg = $argv[$i];
    if($arg == '--help' || $arg == '-h'){
      $args['help'] = true;
      continue;
    }
    if($arg == '--dry-run'){
      $args['dry_run'] = true;
      continue;
    }
    if($arg == '--json'){
      $args['json'] = true;
      continue;
    }

    foreach ([
      '--db' => 'db',
      '--anonymous-days' => 'anonymous_retention_days',
      '--completed-days' => 'completed_retention_days',
      '--batch-size' => 'batch_size'
    ] as $option => $key) {
      if($arg == $option && array_key_exists($i + 1, $argv)){
        $args[$key] = $argv[$i + 1];
        $i++;
        continue 2;
      }
      if(strpos($arg, $option.'=') === 0){
        $args[$key] = substr($arg, strlen($option) + 1);
        continue 2;
      }
    }

    throw new Exception('Unknown ChangeNOW retention argument: '.$arg, 1);
  }

  return $args;
}

function changenow_retention_apply_db_override($dbName){
  $dbName = trim((string) $dbName);
  if($dbName == '') return;

  putenv('KRYPTO_ENV_CONFIG=1');
  putenv('KRYPTO_DB_NAME='.$dbName);
  putenv('KRYPTO_TEST_DB_NAME='.$dbName);
  $_ENV['KRYPTO_ENV_CONFIG'] = '1';
  $_ENV['KRYPTO_DB_NAME'] = $dbName;
  $_ENV['KRYPTO_TEST_DB_NAME'] = $dbName;
}

try {
  $args = changenow_retention_parse_args($argv);
  if($args['help']){
    changenow_retention_usage();
    exit(0);
  }

  changenow_retention_apply_db_override($args['db']);

  require_once $root.'/config/config.settings.php';
  require_once $root.'/vendor/autoload.php';
  require_once $root.'/app/src/MySQL/MySQL.php';
  require_once $root.'/app/src/App/App.php';
  require_once $root.'/app/src/App/AppModule.php';
  require_once $root.'/app/modules/kr-changenow/src/ChangeNowSettings.php';
  require_once $root.'/app/modules/kr-changenow/src/ChangeNowRetention.php';

  $_SERVER['DOCUMENT_ROOT'] = (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : $root);

  $App = new App(false);
  $overrides = [
    'dry_run' => $args['dry_run'],
    'anonymous_retention_days' => $args['anonymous_retention_days'],
    'completed_retention_days' => $args['completed_retention_days'],
    'batch_size' => $args['batch_size']
  ];

  $Retention = new ChangeNowRetention(null, ChangeNowRetention::_optionsFromSettings($App->_getChangeNowSettings(), $overrides));
  $result = $Retention->_run();

  if($args['json']){
    echo json_encode(['error' => 0, 'retention' => $result])."\n";
    exit(0);
  }

  echo 'ChangeNOW retention completed: '
    .'quote_cache_deleted='.$result['quoteCacheDeleted'].', '
    .'anonymous_transactions_anonymized='.$result['anonymousTransactionsAnonymized'].', '
    .'anonymous_events_deleted='.$result['anonymousEventsDeleted'].', '
    .'completed_transactions_deleted='.$result['completedTransactionsDeleted'].', '
    .'completed_events_deleted='.$result['completedEventsDeleted'].', '
    .'dry_run='.($result['dryRun'] ? '1' : '0').".\n";
} catch (Exception $e) {
  if(isset($args) && is_array($args) && array_key_exists('json', $args) && $args['json']){
    echo json_encode(['error' => 1, 'msg' => $e->getMessage()])."\n";
  } else {
    fwrite(STDERR, 'ChangeNOW retention failed: '.$e->getMessage()."\n");
  }
  exit(1);
}

?>
