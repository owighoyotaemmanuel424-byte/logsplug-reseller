<?php
function admin_metric(string $label,string $value,string $detail=''):string{return '<div class="master-stat"><span>'.htmlspecialchars($label).'</span><strong>'.htmlspecialchars($value).'</strong><small>'.htmlspecialchars($detail).'</small></div>';}
