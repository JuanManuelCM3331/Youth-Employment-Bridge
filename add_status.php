<?php
require "vendor/autoload.php";
spl_autoload_register(function($c){
    $p=["App\\"=>"app/","Modules\\"=>"Modules/"];
    foreach($p as $pr=>$b){ if(str_starts_with($c,$pr)){ $f=$b.str_replace("\\","/",substr($c,strlen($pr))).".php"; if(is_file($f)) require $f; }}
});
require "config/database.php";
$db = App\Core\Database::connection();
try{
    $db->exec("ALTER TABLE users ADD COLUMN status ENUM('active','suspended') NOT NULL DEFAULT 'active'");
    echo "added\n";
}catch(Exception $e){
    echo $e->getMessage()."\n";
}
