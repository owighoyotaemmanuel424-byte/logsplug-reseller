<?php
function admin_table(array $headers,array $rows):string{$html='<div class="admin-table-wrap"><table class="admin-table"><thead><tr>';foreach($headers as $h)$html.='<th>'.htmlspecialchars((string)$h).'</th>';$html.='</tr></thead><tbody>';foreach($rows as $row){$html.='<tr>';foreach($row as $cell)$html.='<td>'.$cell.'</td>';$html.='</tr>';}return $html.'</tbody></table></div>';}
