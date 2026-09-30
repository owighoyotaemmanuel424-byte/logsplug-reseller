<?php
declare(strict_types=1);
function admin_layout_start(string $title):void{global $adminPageTitle;$adminPageTitle=$title;require __DIR__.'/../../includes/head.php';require __DIR__.'/admin_sidebar.php';}
function admin_layout_end():void{require __DIR__.'/../../includes/footer.php';}
