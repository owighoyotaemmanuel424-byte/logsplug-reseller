<?php
function admin_pill(string $label,string $tone='neutral'):string{return '<span class="admin-pill admin-pill-'.htmlspecialchars($tone).'">'.htmlspecialchars($label).'</span>';}
