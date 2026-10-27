<?php
require __DIR__.'/includes/bootstrap.php';
verify_post();
$_SESSION=[];
session_destroy();
redirect('login.php');
