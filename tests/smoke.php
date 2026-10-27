<?php
// Run only against a local demonstration database. Fixtures are removed in finally.
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
date_default_timezone_set('Asia/Colombo');
require __DIR__.'/../config/database.php';
$base=rtrim($argv[1]??'http://localhost/smart-laundry','/');
if(!in_array(parse_url($base,PHP_URL_HOST),['localhost','127.0.0.1'],true)) exit("Local URLs only.\n");
$tag='smoke'.bin2hex(random_bytes(4)); $cookies=[]; $checks=0; $userIds=[]; $serviceId=0;
function check(bool $ok,string $message): void { global $checks; if(!$ok) throw new RuntimeException('FAIL: '.$message); $checks++; echo "PASS: $message\n"; }
function sql(string $sql,array $params=[]): PDOStatement { $s=db()->prepare($sql);$s->execute($params);return $s; }
function request(string $who,string $path,?array $data=null): array {
    global $base,$cookies;
    if(!isset($cookies[$who])) $cookies[$who]=tempnam(sys_get_temp_dir(),'laundry-test-');
    $c=curl_init($base.'/'.$path); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEFILE=>$cookies[$who],CURLOPT_COOKIEJAR=>$cookies[$who],CURLOPT_TIMEOUT=>20]);
    if($data!==null) {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($data));}
    $body=curl_exec($c); if($body===false) throw new RuntimeException(curl_error($c));
    $code=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);return [$code,$body];
}
function token(string $who,string $path): string { [$code,$body]=request($who,$path); if(!preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m)) throw new RuntimeException('No CSRF token on '.$path.' HTTP '.$code);return $m[1]; }
function post(string $who,string $page,array $data): array { $data['csrf']=token($who,$page);return request($who,'actions/handle.php',$data); }
function login(string $who,string $email,string $pass): void { [$code,$body]=post($who,'login.php',['action'=>'login','email'=>$email,'password'=>$pass]); check($code===200 && str_contains($body,'workspace'),'Login '.$who); }
function state(int $id): array { return sql('SELECT * FROM bookings WHERE id=?',[$id])->fetch(); }
function status(string $who,int $id,string $next,bool $cod=false): array { return post($who,($who==='admin'?'admin/booking_details.php':($who==='driver'?'delivery/order_details.php':'customer/booking_details.php')).'?id='.$id,['action'=>'status','booking_id'=>$id,'status'=>$next,'cod_confirm'=>$cod?'1':'0']); }
try {
    check(request('guest','admin/dashboard.php')[0]===200 && str_contains(request('guest','admin/dashboard.php')[1],'Good to see you again'),'Anonymous users redirected to login');
    check(in_array(request('guest','setup.php')[0],[403,404],true),'Web installer disabled');
    foreach(['index.php','services.php','about.php','register.php','login.php'] as $path) check(request('guest',$path)[0]===200,'Public page '.$path);
    [$code,$body]=post('new','register.php',['action'=>'register','name'=>'Smoke Customer','email'=>$tag.'@example.test','phone'=>'0771234567','password'=>'TestPass@123','address'=>'Smoke test address']);
    $cid=(int)sql('SELECT id FROM users WHERE email=?',[$tag.'@example.test'])->fetchColumn(); if($cid) $userIds[]=$cid;
    check($code===200 && $cid>0,'Customer registration persists');
    login('customer',$tag.'@example.test','TestPass@123');
    login('admin','admin@laundry.com','Admin@123');
    login('driver','delivery@laundry.com','Delivery@123');
    check(request('customer','admin/dashboard.php')[0]===403,'Customer cannot access admin pages');
    check(request('driver','customer/dashboard.php')[0]===403,'Driver cannot access customer pages');
    check(request('customer','actions/handle.php',['action'=>'create_booking','csrf'=>'bad'])[0]===419,'CSRF enforced');
    check(request('customer','actions/handle.php')[0]===405,'State changes require POST');
    foreach(['admin'=>['dashboard','services','bookings','customers','delivery_staff','reports'],'customer'=>['dashboard','new_booking','bookings','profile'],'driver'=>['dashboard','orders','completed']] as $who=>$pages) foreach($pages as $p) check(request($who,($who==='driver'?'delivery':$who).'/'.$p.'.php')[0]===200,'Protected page '.$who.'/'.$p);
    $services=sql('SELECT id,price FROM services WHERE is_active=1 ORDER BY id LIMIT 2')->fetchAll();
    $s1=$services[0];$s2=$services[1];$expected=3*(float)$s1['price']+2*(float)$s2['price'];
    $form=['action'=>'create_booking','quantity'=>[$s1['id']=>3,$s2['id']=>2],'pickup_address'=>'<script>alert(1)</script> Test lane','pickup_phone'=>'0771234567','pickup_date'=>date('Y-m-d',strtotime('+1 day')),'special_instructions'=>'Test care','total_amount'=>'1','price'=>'1'];
    [$code,$body]=post('customer','customer/new_booking.php',$form);
    $id=(int)sql('SELECT id FROM bookings WHERE customer_id=? ORDER BY id DESC LIMIT 1',[$cid])->fetchColumn();
    check($code===200 && $id>0,'Customer creates persisted booking');
    check((float)state($id)['total_amount']===$expected,'Server calculates total and ignores posted price');
    check(sql('SELECT COUNT(*) FROM booking_items WHERE booking_id=?',[$id])->fetchColumn()==2,'Booking items saved');
    check(str_contains($body,'&lt;script&gt;') && !str_contains($body,'<script>alert(1)</script>'),'Customer content HTML escaped');
    foreach([['quantity'=>[$s1['id']=>-1]],['quantity'=>[$s1['id']=>'1.5']],['quantity'=>[$s1['id']=>101]],['quantity'=>[$s1['id']=>0]],['pickup_date'=>'2020-01-01'],['pickup_date'=>'2026-02-30']] as $invalid) post('customer','customer/new_booking.php',array_replace($form,$invalid));
    check(sql('SELECT COUNT(*) FROM bookings WHERE customer_id=?',[$cid])->fetchColumn()==1,'Invalid quantities and pickup dates rejected without partial bookings');
    login('other','customer@laundry.com','Customer@123');
    check(request('other','customer/booking_details.php?id='.$id)[0]===404,'Other customers cannot view booking');
    post('other','customer/dashboard.php',['action'=>'status','booking_id'=>$id,'status'=>'Cancelled']);
    check(state($id)['booking_status']==='Pending','Other customers cannot cancel booking');
    status('customer',$id,'Delivered',true); check(state($id)['booking_status']==='Pending','Customer cannot skip workflow');
    check(str_contains(request('admin','admin/bookings.php')[1],state($id)['booking_number']),'Admin sees customer booking');
    status('admin',$id,'Accepted'); check(state($id)['booking_status']==='Accepted','Admin accepts booking');
    status('customer',$id,'Cancelled'); check(state($id)['booking_status']==='Accepted','Cancellation blocked after acceptance');
    check(request('driver','delivery/order_details.php?id='.$id)[0]===404,'Unassigned driver cannot view booking');
    $driver=(int)sql("SELECT id FROM users WHERE email='delivery@laundry.com'")->fetchColumn();
    post('admin','admin/booking_details.php?id='.$id,['action'=>'assign','booking_id'=>$id,'delivery_person_id'=>$driver]);
    check(sql('SELECT delivery_person_id FROM delivery_assignments WHERE booking_id=?',[$id])->fetchColumn()==$driver,'Admin assigns driver');
    check(str_contains(request('driver','delivery/orders.php')[1],state($id)['booking_number']),'Driver sees assigned order');
    status('driver',$id,'Delivered',true);check(state($id)['booking_status']==='Accepted','Driver cannot skip pickup/processing');
    foreach([['driver','Picked Up'],['admin','Washing'],['admin','Ironing'],['admin','Ready for Delivery'],['driver','Out for Delivery']] as [$who,$next]) { status($who,$id,$next);check(state($id)['booking_status']===$next,'Workflow '.$next); }
    status('driver',$id,'Delivered');check(state($id)['payment_status']==='Unpaid','Delivery requires COD confirmation');
    $before=(float)sql("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE payment_status='Paid'")->fetchColumn();
    status('driver',$id,'Delivered',true);$paid=state($id);
    check($paid['booking_status']==='Delivered' && $paid['payment_status']==='Paid' && $paid['collected_by']==$driver && $paid['paid_at']!==null,'Delivery atomically records COD collection');
    status('driver',$id,'Delivered',true);
    check(sql("SELECT COUNT(*) FROM booking_status_history WHERE booking_id=? AND status='Delivered'",[$id])->fetchColumn()==1,'Repeated COD collection blocked');
    $after=(float)sql("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE payment_status='Paid'")->fetchColumn();
    check(abs($after-$before-$expected)<0.001,'Collected revenue increased exactly once');
    check(str_contains(request('admin','admin/reports.php')[1],state($id)['booking_number']),'Sales report includes collected order');
    check(str_contains(request('driver','delivery/completed.php')[1],state($id)['booking_number']),'Completed delivery history');
    check(str_contains(request('customer','customer/booking_details.php?id='.$id)[1],'Delivered'),'Customer sees completed order tracking');
    post('customer','customer/new_booking.php',$form);$cancel=(int)sql('SELECT MAX(id) FROM bookings WHERE customer_id=?',[$cid])->fetchColumn();status('customer',$cancel,'Cancelled');check(state($cancel)['booking_status']==='Cancelled','Pending customer cancellation works');
    post('customer','customer/new_booking.php',$form);$reject=(int)sql('SELECT MAX(id) FROM bookings WHERE customer_id=?',[$cid])->fetchColumn();status('admin',$reject,'Rejected');check(state($reject)['booking_status']==='Rejected','Admin rejection works');
    post('customer','customer/profile.php',['action'=>'profile','name'=>'Updated Customer','email'=>$tag.'@example.test','phone'=>'0777654321','address'=>'Updated address']);check(sql('SELECT name FROM users WHERE id=?',[$cid])->fetchColumn()==='Updated Customer','Customer profile updates');
    post('admin','admin/services.php',['action'=>'service_save','service_name'=>$tag,'description'=>'Smoke service','price'=>'123.45','is_active'=>'on']);$serviceId=(int)sql('SELECT id FROM services WHERE service_name=?',[$tag])->fetchColumn();check($serviceId>0,'Admin creates service');
    post('admin','admin/services.php',['action'=>'service_save','id'=>$serviceId,'service_name'=>$tag,'description'=>'Updated','price'=>'150','is_active'=>'on']);check((float)sql('SELECT price FROM services WHERE id=?',[$serviceId])->fetchColumn()===150.0,'Admin edits service price');
    post('admin','admin/services.php',['action'=>'service_delete','id'=>$serviceId]);check(sql('SELECT is_active FROM services WHERE id=?',[$serviceId])->fetchColumn()==0,'Admin removes service from catalogue');
    post('admin','admin/delivery_staff.php',['action'=>'staff_save','name'=>'Smoke Driver','email'=>$tag.'driver@example.test','phone'=>'0771234567','password'=>'TestPass@123','is_active'=>'on']);$newDriver=(int)sql('SELECT id FROM users WHERE email=?',[$tag.'driver@example.test'])->fetchColumn();if($newDriver)$userIds[]=$newDriver;check($newDriver>0,'Admin creates delivery account');
    login('secondDriver',$tag.'driver@example.test','TestPass@123');check(request('secondDriver','delivery/order_details.php?id='.$id)[0]===404,'Other driver cannot access assigned order');
    post('admin','admin/delivery_staff.php',['action'=>'staff_save','id'=>$newDriver,'name'=>'Smoke Driver','email'=>$tag.'driver@example.test','phone'=>'0771234567','password'=>'']);check(sql('SELECT is_active FROM users WHERE id=?',[$newDriver])->fetchColumn()==0,'Admin deactivates delivery account');
    check(str_contains(request('secondDriver','delivery/dashboard.php')[1],'Good to see you again'),'Deactivated driver session loses access');
    post('customer','customer/dashboard.php',['action'=>'logout']);check(str_contains(request('customer','customer/dashboard.php')[1],'Good to see you again'),'Logout ends access');
    echo "\n$checks checks passed.\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n");$failed=true;
} finally {
    foreach($userIds as $uid) sql('DELETE FROM bookings WHERE customer_id=?',[$uid]);
    foreach($userIds as $uid) sql('DELETE FROM users WHERE id=?',[$uid]);
    if($serviceId) sql('DELETE FROM services WHERE id=?',[$serviceId]);
    foreach($cookies as $file) if(is_file($file)) unlink($file);
}
exit(isset($failed)?1:0);
