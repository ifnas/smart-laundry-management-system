param([int]$Port=8095,[int]$DatabasePort=3307,[string]$XamppPath='C:\xampp')
$ErrorActionPreference='Stop'
$project=$PSScriptRoot
$runtime=Join-Path (Split-Path $project -Parent) '.runtime'
$data=Join-Path $runtime 'mysql'
$php=Join-Path $XamppPath 'php\php.exe'
$mysql=Join-Path $XamppPath 'mysql\bin\mysqld.exe'
function Test-LocalPort([int]$Number){
    $client=New-Object Net.Sockets.TcpClient
    try {$client.Connect('127.0.0.1',$Number);return $true} catch {return $false} finally {$client.Dispose()}
}
if(!(Test-Path -LiteralPath $php) -or !(Test-Path -LiteralPath $mysql)){throw 'XAMPP PHP and MariaDB were not found. Pass -XamppPath with your installation folder.'}
New-Item -ItemType Directory -Force -Path $runtime,(Join-Path $runtime 'sessions') | Out-Null
if(!(Test-Path -LiteralPath (Join-Path $data 'mysql'))){
    & (Join-Path $XamppPath 'mysql\bin\mysql_install_db.exe') "--datadir=$data" "--port=$DatabasePort"
    if($LASTEXITCODE -ne 0){throw 'Unable to initialize the isolated local database.'}
}
$ownedDatabase=$null
try {
    if(Test-LocalPort $DatabasePort){
        $activeData=& (Join-Path $XamppPath 'mysql\bin\mysql.exe') -h 127.0.0.1 -P $DatabasePort -u root -N -r -e 'SELECT @@datadir;'
        if($LASTEXITCODE -ne 0 -or !$activeData -or $activeData.TrimEnd('\','/').Replace('/','\') -ne $data.TrimEnd('\','/')){throw "Port $DatabasePort is used by a different database. Choose another -DatabasePort."}
    } else {
        $arguments=@('--no-defaults',('--basedir="'+(Join-Path $XamppPath 'mysql')+'"'),('--datadir="'+$data+'"'),"--port=$DatabasePort",'--bind-address=127.0.0.1','--console')
        $ownedDatabase=Start-Process -FilePath $mysql -ArgumentList $arguments -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtime 'mysql.stdout.log') -RedirectStandardError (Join-Path $runtime 'mysql.stderr.log')
        $ready=$false
        for($i=0;$i -lt 30;$i++){
            if(Test-LocalPort $DatabasePort){$ready=$true;break}
            Start-Sleep -Milliseconds 300
        }
        if(!$ready){throw 'Database startup failed. Check .runtime/mysql.stderr.log.'}
    }
    if(Test-LocalPort $Port){throw "Port $Port is already in use. Pass -Port with another port."}
    $env:DB_PORT=[string]$DatabasePort
    $env:SESSION_PATH=Join-Path $runtime 'sessions'
    $env:SETUP_LOCK_PATH=Join-Path $runtime '.installed'
    if(!(Test-Path -LiteralPath $env:SETUP_LOCK_PATH)){
        & $php (Join-Path $project 'setup.php')
        if($LASTEXITCODE -ne 0){throw 'Database setup failed.'}
    }
    Write-Host "Smart Laundry: http://localhost:$Port/smart-laundry/" -ForegroundColor Cyan
    Write-Host 'Keep this window open. Press Ctrl+C to stop. This uses a separate local database.'
    & $php -S "127.0.0.1:$Port" -t $project (Join-Path $project 'tools\router.php')
} finally {
    if($ownedDatabase -and !$ownedDatabase.HasExited){
        & (Join-Path $XamppPath 'mysql\bin\mysqladmin.exe') -h 127.0.0.1 -P $DatabasePort -u root shutdown
    }
}
