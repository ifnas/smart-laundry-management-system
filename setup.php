<?php
// Deliberately CLI-only: web visitors can never create demo accounts.
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/config/database.php';
require __DIR__.'/includes/demo_accounts.php';
$mode=$argv[1]??'';
if(count($argv)>2 || !in_array($mode,['','--repair-demo','--check-demo'],true)) {
    fwrite(STDERR,"Usage: php setup.php [--repair-demo|--check-demo]\n"); exit(1);
}
if($mode==='--repair-demo' && !in_array(strtolower(getenv('DB_HOST')?:'127.0.0.1'),['127.0.0.1','localhost','::1'],true)) {
    fwrite(STDERR,"Demo repair is restricted to a local database host.\n"); exit(1);
}
$lock=getenv('SETUP_LOCK_PATH')?:__DIR__.'/database/.installed';
if($mode==='' && is_file($lock)) { fwrite(STDERR,"Setup already completed. Use --check-demo to verify or --repair-demo to repair local demo logins.\n"); exit(1); }
try {
    if($mode==='--check-demo') {
        $valid=true;
        foreach(check_demo_accounts(db()) as $check) {
            $ok=$check['password_valid'] && $check['role_valid'] && $check['active'];
            $valid=$valid && $ok;
            echo $check['email'].': '.($ok?'PASS (password_verify, role, active)':'FAIL (missing account, wrong password, role, or inactive)')."\n";
        }
        exit($valid?0:1);
    }
    if($mode==='--repair-demo') {
        // No schema import and no installation-lock removal on the repair path.
        foreach(seed_demo_accounts(db(),true) as $email=>$outcome) echo "$email: $outcome; password_verify PASS\n";
        echo "Local demo logins verified. Existing IDs, profile details and bookings preserved. Web setup remains disabled.\n";
        exit;
    }
    $pdo=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306').';charset=utf8mb4',getenv('DB_USER')?:'root',getenv('DB_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS=>true]);
    $schemaFile = is_file(__DIR__.'/database/schema.sql') ? __DIR__.'/database/schema.sql' : __DIR__.'/database/database.sql';
    $pdo->exec(file_get_contents($schemaFile));
    foreach(seed_demo_accounts($pdo) as $email=>$outcome) echo "$email: $outcome; password_verify PASS\n";
    if(file_put_contents($lock,date(DATE_ATOM))===false) throw new RuntimeException('Unable to write installation lock.');
    echo "Installed smart_laundry_db and local demo accounts. See README.md.\n";
} catch(Throwable $e) { if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); fwrite(STDERR,"Setup failed: ".$e->getMessage()."\n"); exit(1); }
