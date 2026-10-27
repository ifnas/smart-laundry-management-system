<?php
// Local integration checks. Every fixture and simulated corruption is rolled back.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../config/database.php';
require __DIR__.'/../includes/demo_accounts.php';
$pdo=db();
function expect_demo(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$message);
    echo 'PASS: '.$message."\n";
}
function rows_demo(PDO $pdo, string $table): array {
    return $pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
}
$beforeUsers=rows_demo($pdo,'users');
$beforeBookings=rows_demo($pdo,'bookings');
try {
    $pdo->beginTransaction();
    $first=seed_demo_accounts($pdo,true);
    $stable=rows_demo($pdo,'users');
    seed_demo_accounts($pdo,true);
    expect_demo($stable===rows_demo($pdo,'users'),'Second seed leaves IDs, hashes and rows unchanged');
    $customer=$pdo->query("SELECT id FROM users WHERE email='customer@laundry.com'")->fetchColumn();
    $pdo->prepare("INSERT INTO bookings(booking_number,customer_id,pickup_address,pickup_phone,pickup_date,total_amount,special_instructions) VALUES(?,?,?,'0771234567',CURDATE(),250,'Preservation test')")->execute(['REPAIR-'.bin2hex(random_bytes(6)),$customer,'Existing booking address']);
    $pdo->prepare("INSERT INTO users(name,email,phone,password,role,address) VALUES('Unrelated user',?,'0771234567',?,'customer','Keep this address')")->execute(['repair-test-'.bin2hex(random_bytes(6)).'@example.test',password_hash('FixtureOnly!123',PASSWORD_DEFAULT)]);
    $snapshot=rows_demo($pdo,'users');
    $bookings=rows_demo($pdo,'bookings');
    $corrupt=$pdo->prepare("UPDATE users SET password=?,role='customer',is_active=0 WHERE email=?");
    foreach(demo_accounts() as $demo) $corrupt->execute([password_hash('WrongFixturePassword!',PASSWORD_DEFAULT),$demo['email']]);
    seed_demo_accounts($pdo,true);
    foreach(check_demo_accounts($pdo) as $check) expect_demo($check['password_valid'] && $check['role_valid'] && $check['active'],$check['email'].' repaired and password_verify succeeds');
    $after=rows_demo($pdo,'users');
    expect_demo(count($snapshot)===count($after),'Repair creates no duplicate accounts');
    foreach($after as $i=>$row) {
        $prior=$snapshot[$i];
        if(in_array($row['email'],array_column(demo_accounts(),'email'),true)) {
            unset($row['password'],$prior['password']);
        }
        expect_demo($row===$prior,'User ID '.$row['id'].' and profile/other account data preserved');
    }
    expect_demo($bookings===rows_demo($pdo,'bookings'),'Existing bookings and customer relationships preserved');
    $stable=rows_demo($pdo,'users');
    seed_demo_accounts($pdo,true);
    expect_demo($stable===rows_demo($pdo,'users'),'Repeat after repair does not replace valid hashes');
    $pdo->rollBack();
    expect_demo($beforeUsers===rows_demo($pdo,'users') && $beforeBookings===rows_demo($pdo,'bookings'),'All test changes rolled back');
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,$e->getMessage()."\n"); exit(1);
}

// Optional end-to-end verification against the already running local application.
if(isset($argv[1])) {
    $base=rtrim($argv[1],'/');
    if(!in_array(parse_url($base,PHP_URL_HOST),['localhost','127.0.0.1'],true)) exit("Local URLs only.\n");
    foreach(demo_accounts() as $demo) {
        $jar=tempnam(sys_get_temp_dir(),'laundry-auth-');
        $curl=curl_init();
        try {
            curl_setopt_array($curl,[CURLOPT_URL=>$base.'/login.php',CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>15]);
            $html=curl_exec($curl);
            if(!is_string($html) || !preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$match)) throw new RuntimeException('Unable to read local login CSRF token.');
            curl_setopt_array($curl,[CURLOPT_URL=>$base.'/actions/handle.php',CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['action'=>'login','csrf'=>$match[1],'email'=>$demo['email'],'password'=>$demo['password']]),CURLOPT_FOLLOWLOCATION=>true]);
            $html=curl_exec($curl);
            expect_demo(curl_getinfo($curl,CURLINFO_HTTP_CODE)===200 && str_ends_with(curl_getinfo($curl,CURLINFO_EFFECTIVE_URL),'/'.$demo['role'].'/dashboard.php') && is_string($html) && str_contains($html,'workspace'),'Apache login reaches '.$demo['role'].' dashboard');
        } finally { curl_close($curl); if(is_file($jar)) unlink($jar); }
    }
    $curl=curl_init($base.'/setup.php');
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
    curl_exec($curl);
    $code=curl_getinfo($curl,CURLINFO_HTTP_CODE);
    curl_close($curl);
    expect_demo(in_array($code,[403,404],true),'Web setup stays blocked');
}
