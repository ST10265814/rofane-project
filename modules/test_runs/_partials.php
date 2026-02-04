<?php
function e($s){return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');}
function status_badge($s){
  $map = [
    'Not Run'=>'secondary','In Progress'=>'info','Pass'=>'success','Fail'=>'danger','Blocked'=>'warning'
  ];
  $cls = $map[$s] ?? 'secondary';
  return '<span class="badge bg-'.$cls.'">'.e($s).'</span>';
}
?>