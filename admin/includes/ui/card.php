<?php
function admin_card(string $title,string $body):string{return '<section class="admin-card"><h2 class="admin-card-title">'.htmlspecialchars($title).'</h2><div>'.$body.'</div></section>';}
